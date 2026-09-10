<?php

namespace App\Services\LocationGovernance\Import;

use App\Data\LocationGovernance\ReferenceImportSummary;
use App\Models\Location;
use App\Models\LocationExternalId;
use App\Models\LocationSchema;
use App\Models\LocationSchemaType;
use App\Models\LocationType;
use App\Models\LocationTypeRelation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReferenceGeographyImporter
{
    public const SOURCE = 'earthcoop-reference';

    private const TYPE_DEFINITIONS = [
        'country' => ['Country', true, false],
        'province' => ['Province', false, false],
        'county' => ['County', false, false],
        'section' => ['Section', false, false],
        'city' => ['City', false, true],
        'rural_district' => ['Rural district', false, false],
        'village' => ['Village', false, true],
        'urban_region' => ['Urban region', false, false],
        'neighborhood' => ['Neighborhood', false, true],
        'street' => ['Street', false, true],
        'alley' => ['Alley', false, true],
        'complex' => ['Residential complex', false, true],
        'building' => ['Building', false, true],
    ];

    private const TYPE_RELATIONS = [
        ['country', 'province'],
        ['province', 'county'],
        ['county', 'section'],
        ['section', 'city'],
        ['section', 'rural_district'],
        ['rural_district', 'village'],
        ['city', 'urban_region'],
        ['urban_region', 'neighborhood'],
        ['village', 'neighborhood'],
        ['neighborhood', 'street'],
        ['street', 'alley'],
        ['street', 'complex'],
        ['alley', 'complex'],
        ['complex', 'building'],
    ];

    public function __construct(private readonly ReferenceGeographyNormalizer $normalizer)
    {
    }

    public function import(string $countryCode, string $version, bool $apply = false): ReferenceImportSummary
    {
        $countryCode = strtoupper(trim($countryCode));
        $version = trim($version);
        $path = database_path('reference/'.strtolower($countryCode).'/'.$version.'/locations.jsonl');

        if (! is_file($path)) {
            throw new InvalidArgumentException("Reference geography dataset not found: {$countryCode}/{$version}");
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new InvalidArgumentException("Reference geography dataset cannot be read: {$countryCode}/{$version}");
        }

        $rows = $this->parseDataset($raw, $countryCode, $version);
        [$actions, $summary] = $this->diff($rows, $countryCode, $version);

        if (! $apply) {
            return $summary;
        }

        return DB::transaction(function () use ($rows, $actions, $summary, $countryCode, $version, $raw): ReferenceImportSummary {
            [$schema, $types] = $this->ensureSchema($countryCode, $version);
            $resolved = [];

            foreach ($rows as $row) {
                if (($actions[$row['external_id']]['action'] ?? 'conflict') === 'conflict') {
                    continue;
                }

                $parent = null;
                if ($row['parent_external_id'] !== null && $row['parent_external_id'] !== '') {
                    $parent = $resolved[$row['parent_external_id']] ?? $this->locationForExternalId($row['parent_external_id'], $version);
                    if (! $parent) {
                        continue;
                    }
                }

                $existingIdentity = LocationExternalId::query()
                    ->where('source', self::SOURCE)
                    ->where('dataset_version', $version)
                    ->where('external_id', $row['external_id'])
                    ->first();

                $location = $existingIdentity?->location;
                $attributes = [
                    'parent_id' => $parent?->id,
                    'location_schema_id' => $schema->id,
                    'location_type_id' => $types[$row['type_key']]->id,
                    'country_code' => $countryCode,
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
                        'dataset_version' => $version,
                        'external_id' => $row['external_id'],
                        'metadata' => ['country_code' => $countryCode],
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
                    ->where('dataset_version', $version)
                    ->where('external_id', $externalId)
                    ->first();

                if ($identity?->location && $identity->location->country_code === $countryCode) {
                    $identity->location->update([
                        'status' => 'inactive',
                        'valid_to' => now(),
                    ]);
                }
            }

            $runId = DB::table('location_import_runs')->insertGetId([
                'country_code' => $countryCode,
                'source' => self::SOURCE,
                'dataset_version' => $version,
                'mode' => 'apply',
                'status' => $summary->conflicts > 0 ? 'completed_with_conflicts' : 'completed',
                'creates' => $summary->creates,
                'updates' => $summary->updates,
                'deactivates' => $summary->deactivates,
                'conflicts' => $summary->conflicts,
                'unchanged' => $summary->unchanged,
                'dataset_hash' => hash('sha256', $raw),
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

            return $summary;
        });
    }

    private function parseDataset(string $raw, string $countryCode, string $version): array
    {
        $rows = [];
        $seen = [];

        // JSONL is line-oriented. Split only on actual CR/LF bytes; PCRE \R in
        // non-UTF mode can treat UTF-8 continuation byte 0x85 as a line break.
        foreach (preg_split('/\r\n|\n|\r/', trim($raw)) ?: [] as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (! is_array($decoded)) {
                throw new InvalidArgumentException('Invalid reference geography JSON on line '.($index + 1));
            }

            $row = $this->normalizer->normalize($decoded, $countryCode, $version);
            if ($row['external_id'] === '' || $row['type_key'] === '' || $row['canonical_name'] === '') {
                throw new InvalidArgumentException('Reference geography row is missing required identity fields on line '.($index + 1));
            }

            if (isset($seen[$row['external_id']])) {
                throw new InvalidArgumentException("Duplicate reference geography external ID: {$row['external_id']}");
            }

            $seen[$row['external_id']] = true;
            $rows[] = $row;
        }

        return $rows;
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

            if (! isset(self::TYPE_DEFINITIONS[$row['type_key']]) || ! $parentKnown) {
                $actions[$row['external_id']] = [
                    'action' => 'conflict',
                    'after' => $row,
                    'message' => ! isset(self::TYPE_DEFINITIONS[$row['type_key']]) ? 'Unknown location type.' : 'Parent reference is missing or appears after its child.',
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
                $actions[$row['external_id']] = ['action' => 'conflict', 'after' => $row, 'message' => 'Existing external identity belongs to another country or missing location.'];
                continue;
            }

            $parentExternalId = $location->parent_id
                ? LocationExternalId::query()->where('location_id', $location->parent_id)->where('source', self::SOURCE)->where('dataset_version', $version)->value('external_id')
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

        return [$actions, new ReferenceImportSummary(
            creates: $counts['create'] ?? 0,
            updates: $counts['update'] ?? 0,
            deactivates: $counts['deactivate'] ?? 0,
            conflicts: $counts['conflict'] ?? 0,
            unchanged: $counts['unchanged'] ?? 0,
        )];
    }

    private function ensureSchema(string $countryCode, string $version): array
    {
        $schema = LocationSchema::firstOrCreate(
            ['key' => strtolower($countryCode).'-reference-'.$version],
            [
                'country_code' => $countryCode,
                'name' => $countryCode.' reference geography',
                'version' => $version,
                'status' => 'active',
                'metadata' => ['source' => self::SOURCE],
            ]
        );

        $types = [];
        foreach (self::TYPE_DEFINITIONS as $key => [$name, $isRoot, $isEndpoint]) {
            $type = LocationType::firstOrCreate(
                ['key' => $key],
                ['canonical_name' => $name, 'is_residence_endpoint' => $isEndpoint]
            );
            $types[$key] = $type;

            LocationSchemaType::firstOrCreate(
                ['location_schema_id' => $schema->id, 'location_type_id' => $type->id],
                ['is_root' => $isRoot, 'is_residence_endpoint' => $isEndpoint, 'sort_order' => array_search($key, array_keys(self::TYPE_DEFINITIONS), true)]
            );
        }

        foreach (self::TYPE_RELATIONS as [$parentKey, $childKey]) {
            LocationTypeRelation::firstOrCreate([
                'location_schema_id' => $schema->id,
                'parent_type_id' => $types[$parentKey]->id,
                'child_type_id' => $types[$childKey]->id,
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
