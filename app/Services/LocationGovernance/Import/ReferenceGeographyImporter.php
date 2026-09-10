<?php

namespace App\Services\LocationGovernance\Import;

use App\Data\LocationGovernance\ReferenceDataset;
use App\Data\LocationGovernance\ReferenceImportResult;
use App\Models\Location;
use App\Models\LocationExternalId;
use App\Models\LocationSchema;
use App\Models\LocationSchemaType;
use App\Models\LocationType;
use App\Models\LocationTypeRelation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class ReferenceGeographyImporter
{
    public const SOURCE = 'earthcoop-reference';

    public function __construct(
        private readonly ReferenceGeographyNormalizer $normalizer,
        private readonly ReferenceGeographyValidator $validator,
    ) {
    }

    public function import(string $countryCode, string $version, bool $apply = false): ReferenceImportResult
    {
        $dataset = ReferenceDataset::fromCountryVersion($countryCode, $version);
        $errors = $this->validator->validate($dataset);
        if ($errors !== []) {
            throw new InvalidArgumentException('Invalid reference geography dataset: '.implode(' ', $errors));
        }

        $rows = array_map(
            fn (array $row): array => $this->normalizer->normalize($row, $dataset->countryCode, $dataset->version),
            $dataset->rows,
        );
        [$actions, $result] = $this->diff($rows, $dataset->countryCode, $dataset->version);

        if (! $apply) {
            return $result;
        }

        return DB::transaction(function () use ($dataset, $rows, $actions, $result): ReferenceImportResult {
            [$schema, $types] = $this->ensureSchema($dataset);
            $resolved = [];

            foreach ($rows as $row) {
                if (($actions[$row['external_id']]['action'] ?? 'conflict') === 'conflict') {
                    continue;
                }

                $parent = null;
                if ($row['parent_external_id'] !== null && $row['parent_external_id'] !== '') {
                    $parent = $resolved[$row['parent_external_id']]
                        ?? $this->locationForExternalId($row['parent_external_id'], $dataset->version);
                    if (! $parent) {
                        throw new RuntimeException("Validated reference parent disappeared during import: {$row['parent_external_id']}");
                    }
                }

                $existingIdentity = LocationExternalId::query()
                    ->where('source', self::SOURCE)
                    ->where('dataset_version', $dataset->version)
                    ->where('external_id', $row['external_id'])
                    ->first();

                $location = $existingIdentity?->location;
                $attributes = [
                    'parent_id' => $parent?->id,
                    'location_schema_id' => $schema->id,
                    'location_type_id' => $types[$row['type_key']]->id,
                    'country_code' => $dataset->countryCode,
                    'name' => $row['canonical_name'],
                    'canonical_name' => $row['canonical_name'],
                    'localized_names' => $row['localized_names'],
                    'level' => $row['type_key'],
                    'status' => $row['status'] ?: 'active',
                    'centroid_latitude' => $row['centroid_latitude'],
                    'centroid_longitude' => $row['centroid_longitude'],
                    'valid_to' => null,
                    'provenance' => $row['provenance'],
                    'metadata' => $row['metadata'],
                ];

                if ($location) {
                    if (($actions[$row['external_id']]['action'] ?? null) === 'update') {
                        $location->fill($attributes);
                        if ($location->isDirty()) {
                            $location->save();
                        }
                    }
                } else {
                    $location = Location::create($attributes);
                    LocationExternalId::create([
                        'location_id' => $location->id,
                        'source' => self::SOURCE,
                        'dataset_version' => $dataset->version,
                        'external_id' => $row['external_id'],
                        'metadata' => ['country_code' => $dataset->countryCode],
                    ]);
                }

                $resolved[$row['external_id']] = $location;
            }

            foreach ($actions as $externalId => $action) {
                if ($action['action'] !== 'deactivate') {
                    continue;
                }

                $identity = LocationExternalId::query()
                    ->where('source', self::SOURCE)
                    ->where('dataset_version', $dataset->version)
                    ->where('external_id', $externalId)
                    ->first();

                if ($identity?->location && $identity->location->country_code === $dataset->countryCode) {
                    $identity->location->update([
                        'status' => 'inactive',
                        'valid_to' => now(),
                    ]);
                }
            }

            $runId = DB::table('location_import_runs')->insertGetId([
                'country_code' => $dataset->countryCode,
                'source' => self::SOURCE,
                'dataset_version' => $dataset->version,
                'mode' => 'apply',
                'status' => $result->conflicts > 0 ? 'completed_with_conflicts' : 'completed',
                'creates' => $result->creates,
                'updates' => $result->updates,
                'deactivates' => $result->deactivates,
                'conflicts' => $result->conflicts,
                'unchanged' => $result->unchanged,
                'dataset_hash' => hash('sha256', $dataset->rawLocations),
                'started_at' => now(),
                'finished_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($actions as $externalId => $action) {
                DB::table('location_import_items')->insert([
                    'location_import_run_id' => $runId,
                    'external_id' => $externalId,
                    'action' => $action['action'],
                    'before' => isset($action['before']) ? json_encode($action['before'], JSON_UNESCAPED_UNICODE) : null,
                    'after' => isset($action['after']) ? json_encode($action['after'], JSON_UNESCAPED_UNICODE) : null,
                    'message' => $action['message'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $result;
        });
    }

    private function diff(array $rows, string $countryCode, string $version): array
    {
        $actions = [];
        $datasetIds = array_fill_keys(array_column($rows, 'external_id'), true);
        $knownDatasetIds = [];

        foreach ($rows as $row) {
            $knownDatasetIds[$row['external_id']] = true;
            $parentKnown = $row['parent_external_id'] === null
                || isset($knownDatasetIds[$row['parent_external_id']])
                || $this->locationForExternalId($row['parent_external_id'], $version) !== null;

            if (! $parentKnown) {
                $actions[$row['external_id']] = [
                    'action' => 'conflict',
                    'after' => $row,
                    'message' => 'Parent reference is missing or appears after its child.',
                ];
                continue;
            }

            $identity = LocationExternalId::query()
                ->with('location.type')
                ->where('source', self::SOURCE)
                ->where('dataset_version', $version)
                ->where('external_id', $row['external_id'])
                ->first();

            if (! $identity) {
                $actions[$row['external_id']] = ['action' => 'create', 'after' => $row];
                continue;
            }

            $location = $identity->location;
            if (! $location || $location->country_code !== $countryCode) {
                $actions[$row['external_id']] = [
                    'action' => 'conflict',
                    'after' => $row,
                    'message' => 'Existing external identity belongs to another country or missing location.',
                ];
                continue;
            }

            $parentExternalId = $location->parent_id
                ? LocationExternalId::query()
                    ->where('location_id', $location->parent_id)
                    ->where('source', self::SOURCE)
                    ->where('dataset_version', $version)
                    ->value('external_id')
                : null;

            $current = [
                'parent_external_id' => $parentExternalId,
                'type_key' => $location->type?->key,
                'canonical_name' => $location->canonical_name,
                'localized_names' => $location->localized_names ?? [],
                'status' => $location->status,
                'centroid_latitude' => $location->centroid_latitude === null ? null : (float) $location->centroid_latitude,
                'centroid_longitude' => $location->centroid_longitude === null ? null : (float) $location->centroid_longitude,
                'metadata' => $location->metadata ?? [],
                'provenance' => $location->provenance ?? [],
            ];
            $desired = [
                'parent_external_id' => $row['parent_external_id'],
                'type_key' => $row['type_key'],
                'canonical_name' => $row['canonical_name'],
                'localized_names' => $row['localized_names'],
                'status' => $row['status'] ?: 'active',
                'centroid_latitude' => $row['centroid_latitude'] === null ? null : (float) $row['centroid_latitude'],
                'centroid_longitude' => $row['centroid_longitude'] === null ? null : (float) $row['centroid_longitude'],
                'metadata' => $row['metadata'],
                'provenance' => $row['provenance'],
            ];

            $actions[$row['external_id']] = $current === $desired
                ? ['action' => 'unchanged', 'before' => $current, 'after' => $desired]
                : ['action' => 'update', 'before' => $current, 'after' => $desired];
        }

        $existingIdentities = LocationExternalId::query()
            ->where('source', self::SOURCE)
            ->where('dataset_version', $version)
            ->whereHas('location', fn ($query) => $query->where('country_code', $countryCode))
            ->get();

        foreach ($existingIdentities as $identity) {
            if (! isset($datasetIds[$identity->external_id])) {
                $actions[$identity->external_id] = [
                    'action' => 'deactivate',
                    'before' => ['location_id' => $identity->location_id],
                ];
            }
        }

        $counts = array_count_values(array_column($actions, 'action'));

        return [$actions, new ReferenceImportResult(
            creates: $counts['create'] ?? 0,
            updates: $counts['update'] ?? 0,
            deactivates: $counts['deactivate'] ?? 0,
            conflicts: $counts['conflict'] ?? 0,
            unchanged: $counts['unchanged'] ?? 0,
        )];
    }

    private function ensureSchema(ReferenceDataset $dataset): array
    {
        $schemaDefinition = $dataset->schema;
        $schemaKey = trim((string) ($schemaDefinition['key'] ?? ''));
        if ($schemaKey === '') {
            throw new InvalidArgumentException('Reference geography schema key is required.');
        }

        $schema = LocationSchema::query()->where('key', $schemaKey)->first();
        if ($schema && ($schema->country_code !== $dataset->countryCode || $schema->version !== $dataset->version)) {
            throw new RuntimeException("Reference geography schema key collision: {$schemaKey}");
        }

        if (! $schema) {
            $schema = LocationSchema::create([
                'key' => $schemaKey,
                'country_code' => $dataset->countryCode,
                'name' => (string) ($schemaDefinition['name'] ?? $dataset->countryCode.' reference geography'),
                'version' => $dataset->version,
                'status' => 'active',
                'metadata' => ['source' => self::SOURCE],
            ]);
        }

        $types = [];
        foreach ($schemaDefinition['types'] as $typeDefinition) {
            $key = trim((string) $typeDefinition['key']);
            $type = LocationType::firstOrCreate(
                ['key' => $key],
                [
                    'canonical_name' => (string) ($typeDefinition['canonical_name'] ?? $key),
                    'is_residence_endpoint' => (bool) ($typeDefinition['is_residence_endpoint'] ?? false),
                ]
            );
            $types[$key] = $type;

            LocationSchemaType::updateOrCreate(
                ['location_schema_id' => $schema->id, 'location_type_id' => $type->id],
                [
                    'is_root' => (bool) ($typeDefinition['is_root'] ?? false),
                    'is_residence_endpoint' => (bool) ($typeDefinition['is_residence_endpoint'] ?? false),
                    'sort_order' => (int) ($typeDefinition['sort_order'] ?? 0),
                ]
            );
        }

        foreach ($schemaDefinition['relations'] as $relation) {
            LocationTypeRelation::firstOrCreate([
                'location_schema_id' => $schema->id,
                'parent_type_id' => $types[$relation['parent']]->id,
                'child_type_id' => $types[$relation['child']]->id,
            ]);
        }

        return [$schema, $types];
    }

    private function locationForExternalId(?string $externalId, string $version): ?Location
    {
        if ($externalId === null || $externalId === '') {
            return null;
        }

        return LocationExternalId::query()
            ->where('source', self::SOURCE)
            ->where('dataset_version', $version)
            ->where('external_id', $externalId)
            ->first()?->location;
    }
}
