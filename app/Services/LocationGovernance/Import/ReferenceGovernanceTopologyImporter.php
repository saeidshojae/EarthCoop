<?php

namespace App\Services\LocationGovernance\Import;

use App\Models\GovernanceArea;
use App\Models\LocationExternalId;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReferenceGovernanceTopologyImporter
{
    public function diff(string $country, string $datasetVersion): array
    {
        $dataset = $this->load($country, $datasetVersion);
        $counts = ['create' => 0, 'update' => 0, 'conflict' => 0, 'unchanged' => 0];
        $definitions = collect($dataset['areas']);
        $areaKeys = $definitions->pluck('key')->all();

        $areas = GovernanceArea::query()
            ->with(['parent:id,key', 'locations:id'])
            ->whereIn('key', $areaKeys)
            ->get()
            ->keyBy('key');

        $locationIdsByExternalId = LocationExternalId::query()
            ->where('source', 'earthcoop-reference')
            ->where('dataset_version', $datasetVersion)
            ->whereIn(
                'external_id',
                $definitions->flatMap(fn (array $definition): array => $definition['location_external_ids'] ?? [])->unique()->values()->all(),
            )
            ->pluck('location_id', 'external_id');

        foreach ($definitions as $definition) {
            $expectedLocationIds = [];
            $mappingMissing = false;
            foreach ($definition['location_external_ids'] ?? [] as $externalId) {
                $locationId = $locationIdsByExternalId->get($externalId);
                if ($locationId === null) {
                    $mappingMissing = true;
                    break;
                }
                $expectedLocationIds[] = (int) $locationId;
            }
            if ($mappingMissing) {
                $counts['conflict']++;
                continue;
            }

            $area = $areas->get($definition['key']);
            if ($area === null) {
                $counts['create']++;
                continue;
            }

            if (! $this->isOwnedByDataset($area, $dataset, $definition)) {
                $counts['conflict']++;
                continue;
            }

            sort($expectedLocationIds);
            $actualLocationIds = $area->locations->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all();

            $matches = $area->country_code === $this->countryCodeFor($definition, $dataset)
                && $area->governance_type === $definition['governance_type']
                && $area->area_kind === $definition['area_kind']
                && $area->canonical_name === $definition['canonical_name']
                && collect($area->localized_names ?? [])->sortKeys()->all()
                    === collect($definition['localized_names'] ?? [])->sortKeys()->all()
                && $area->rank === (int) $definition['rank']
                && $area->status === $definition['status']
                && $area->parent?->key === ($definition['parent_key'] ?? null)
                && $expectedLocationIds === $actualLocationIds;

            $counts[$matches ? 'unchanged' : 'update']++;
        }

        return $counts;
    }

    public function apply(string $country, string $datasetVersion): array
    {
        $dataset = $this->load($country, $datasetVersion);
        $diff = $this->diff($country, $datasetVersion);

        if ($diff['conflict'] > 0) {
            throw new RuntimeException('Reference governance topology has unresolved conflicts.');
        }

        DB::transaction(function () use ($dataset, $datasetVersion): void {
            $definitions = collect($dataset['areas']);
            $locationIdsByExternalId = LocationExternalId::query()
                ->where('source', 'earthcoop-reference')
                ->where('dataset_version', $datasetVersion)
                ->whereIn(
                    'external_id',
                    $definitions->flatMap(fn (array $definition): array => $definition['location_external_ids'] ?? [])->unique()->values()->all(),
                )
                ->pluck('location_id', 'external_id');

            $resolvedIds = GovernanceArea::query()
                ->whereIn('key', $definitions->pluck('key')->all())
                ->pluck('id', 'key')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $rankGroups = $definitions
                ->groupBy(fn (array $definition): int => (int) $definition['rank'])
                ->sortKeys();

            foreach ($rankGroups as $group) {
                $rows = [];
                foreach ($group as $definition) {
                    $parentId = null;
                    if (($definition['parent_key'] ?? null) !== null) {
                        $parentId = $resolvedIds[$definition['parent_key']] ?? null;
                        if ($parentId === null) {
                            throw new RuntimeException('Reference governance topology parent is missing: '.$definition['parent_key']);
                        }
                    }

                    $countryCode = $this->countryCodeFor($definition, $dataset);
                    $rows[] = [
                        'parent_id' => $parentId,
                        'key' => $definition['key'],
                        'country_code' => $countryCode,
                        'governance_type' => $definition['governance_type'],
                        'area_kind' => $definition['area_kind'],
                        'canonical_name' => $definition['canonical_name'],
                        'localized_names' => isset($definition['localized_names'])
                            ? json_encode($definition['localized_names'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                            : null,
                        'rank' => (int) $definition['rank'],
                        'status' => $definition['status'],
                        'metadata' => json_encode([
                            'reference_topology' => true,
                            'source' => $dataset['source'],
                            'dataset_version' => $dataset['dataset_version'],
                            'shared_scope' => $countryCode === null,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('governance_areas')->upsert(
                        $chunk,
                        ['key'],
                        [
                            'parent_id',
                            'country_code',
                            'governance_type',
                            'area_kind',
                            'canonical_name',
                            'localized_names',
                            'rank',
                            'status',
                            'metadata',
                            'updated_at',
                        ],
                    );
                }

                $keys = $group->pluck('key')->all();
                foreach (array_chunk($keys, 1000) as $keyChunk) {
                    foreach (GovernanceArea::query()->whereIn('key', $keyChunk)->pluck('id', 'key') as $key => $id) {
                        $resolvedIds[$key] = (int) $id;
                    }
                }
            }

            $v2AreaIds = [];
            $pivotRows = [];
            $now = now();
            foreach ($definitions as $definition) {
                if (($definition['location_external_ids'] ?? []) === []) {
                    continue;
                }

                $areaId = $resolvedIds[$definition['key']] ?? null;
                if ($areaId === null) {
                    throw new RuntimeException('Reference governance topology area ID is missing after bulk upsert: '.$definition['key']);
                }

                $v2AreaIds[] = $areaId;
                foreach ($definition['location_external_ids'] as $externalId) {
                    $locationId = $locationIdsByExternalId->get($externalId);
                    if ($locationId === null) {
                        throw new RuntimeException('Reference governance topology location mapping is missing for '.$definition['key']);
                    }
                    $pivotRows[] = [
                        'governance_area_id' => $areaId,
                        'location_id' => (int) $locationId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk(array_values(array_unique($v2AreaIds)), 1000) as $areaIdChunk) {
                DB::table('governance_area_locations')->whereIn('governance_area_id', $areaIdChunk)->delete();
            }
            foreach (array_chunk($pivotRows, 1000) as $chunk) {
                DB::table('governance_area_locations')->insert($chunk);
            }
        });

        return $diff;
    }

    private function load(string $country, string $datasetVersion): array
    {
        $country = strtoupper($country);
        $path = database_path('reference/'.strtolower($country).'/'.$datasetVersion.'/governance.json');
        if (! is_file($path)) {
            if ($country === 'IR' && $datasetVersion === 'v2') {
                return $this->iran1404AdministrativeTopology();
            }

            throw new RuntimeException('Reference governance topology dataset not found.');
        }

        $dataset = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (($dataset['country'] ?? null) !== $country || ($dataset['dataset_version'] ?? null) !== $datasetVersion) {
            throw new RuntimeException('Reference governance topology dataset identity mismatch.');
        }

        return $dataset;
    }

    private function iran1404AdministrativeTopology(): array
    {
        $identities = LocationExternalId::query()
            ->with(['location.type', 'location.parent'])
            ->where('source', 'earthcoop-reference')
            ->where('dataset_version', 'v2')
            ->whereHas('location', fn ($query) => $query->where('country_code', 'IR')->where('status', 'active'))
            ->get();

        if ($identities->count() !== 6158) {
            throw new RuntimeException('Iran 1404 v2 governance topology requires exactly 6158 imported administrative locations.');
        }

        $rankByType = [
            'country' => 100,
            'province' => 200,
            'county' => 300,
            'section' => 400,
            'city' => 500,
            'rural_district' => 500,
            'urban_region' => 700,
        ];
        $keyFor = fn (string $externalId): string => 'ir-reference-v2-'.strtolower($externalId);

        $externalByLocationId = $identities->mapWithKeys(
            fn (LocationExternalId $identity): array => [(int) $identity->location_id => (string) $identity->external_id]
        );

        $ordered = $identities->sortBy(function (LocationExternalId $identity) use ($rankByType): string {
            $type = (string) $identity->location?->type?->key;
            $rank = $rankByType[$type] ?? 9999;
            $tail = (int) preg_replace('/^IR-1404-/', '', (string) $identity->external_id);

            return sprintf('%04d:%010d', $rank, $tail);
        })->values();

        $areas = [
            [
                'key' => 'earthcoop-global',
                'parent_key' => null,
                'country_code' => null,
                'governance_type' => 'global',
                'area_kind' => 'official',
                'canonical_name' => 'EarthCoop Global',
                'localized_names' => ['fa' => 'جهانی', 'en' => 'EarthCoop Global'],
                'rank' => 0,
                'status' => 'active',
                'location_external_ids' => [],
            ],
            [
                'key' => 'earthcoop-continent-asia',
                'parent_key' => 'earthcoop-global',
                'country_code' => null,
                'governance_type' => 'continent',
                'area_kind' => 'official',
                'canonical_name' => 'Asia',
                'localized_names' => ['fa' => 'آسیا', 'en' => 'Asia'],
                'rank' => 50,
                'status' => 'active',
                'location_external_ids' => [],
            ],
        ];

        foreach ($ordered as $identity) {
            $location = $identity->location;
            $type = (string) $location?->type?->key;
            if ($location === null || ! isset($rankByType[$type])) {
                throw new RuntimeException('Iran 1404 v2 contains a non-administrative location in the governance candidate.');
            }

            $parentKey = 'earthcoop-continent-asia';
            if ($type !== 'country') {
                $parentExternalId = $externalByLocationId->get((int) $location->parent_id);
                if (! is_string($parentExternalId) || $parentExternalId === '') {
                    throw new RuntimeException('Iran 1404 v2 governance parent identity is missing for '.$identity->external_id);
                }
                $parentKey = $keyFor($parentExternalId);
            }

            $areas[] = [
                'key' => $keyFor((string) $identity->external_id),
                'parent_key' => $parentKey,
                'governance_type' => $type,
                'area_kind' => 'official',
                'canonical_name' => (string) $location->canonical_name,
                'localized_names' => $location->localized_names ?? ['fa' => (string) $location->canonical_name],
                'rank' => $rankByType[$type],
                'status' => 'active',
                'location_external_ids' => [(string) $identity->external_id],
            ];
        }

        return [
            'country' => 'IR',
            'dataset_version' => 'v2',
            'source' => 'earthcoop-reference-governance',
            'areas' => $areas,
        ];
    }

    private function locationsFor(array $definition, string $datasetVersion): ?array
    {
        $locations = [];
        foreach ($definition['location_external_ids'] ?? [] as $externalId) {
            $mapping = LocationExternalId::query()
                ->with('location')
                ->where('source', 'earthcoop-reference')
                ->where('dataset_version', $datasetVersion)
                ->where('external_id', $externalId)
                ->first();

            if ($mapping === null || $mapping->location === null) {
                return null;
            }
            $locations[] = $mapping->location;
        }

        return $locations;
    }

    private function countryCodeFor(array $definition, array $dataset): ?string
    {
        return array_key_exists('country_code', $definition)
            ? $definition['country_code']
            : $dataset['country'];
    }

    private function isOwnedByDataset(GovernanceArea $area, array $dataset, array $definition): bool
    {
        $metadata = $area->metadata ?? [];
        $sharedScope = $this->countryCodeFor($definition, $dataset) === null;

        if (($metadata['reference_topology'] ?? false) !== true
            || ($metadata['source'] ?? null) !== $dataset['source']) {
            return false;
        }

        if ($sharedScope) {
            return ($metadata['shared_scope'] ?? false) === true
                || $area->country_code === null;
        }

        return ($metadata['dataset_version'] ?? null) === $dataset['dataset_version'];
    }
}
