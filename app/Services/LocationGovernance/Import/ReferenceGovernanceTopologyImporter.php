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

        foreach ($dataset['areas'] as $definition) {
            $locations = $this->locationsFor($definition, $datasetVersion);
            if ($locations === null) {
                $counts['conflict']++;
                continue;
            }

            $area = GovernanceArea::query()->where('key', $definition['key'])->first();
            if ($area === null) {
                $counts['create']++;
                continue;
            }

            if (! $this->isOwnedByDataset($area, $dataset)) {
                $counts['conflict']++;
                continue;
            }

            $parentKey = $area->parent?->key;
            $expectedLocationIds = collect($locations)->pluck('id')->sort()->values()->all();
            $actualLocationIds = $area->locations()->pluck('locations.id')->sort()->values()->all();

            $matches = $area->country_code === $dataset['country']
                && $area->governance_type === $definition['governance_type']
                && $area->area_kind === $definition['area_kind']
                && $area->canonical_name === $definition['canonical_name']
                && $area->rank === (int) $definition['rank']
                && $area->status === $definition['status']
                && $parentKey === ($definition['parent_key'] ?? null)
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
            $resolved = [];

            foreach ($dataset['areas'] as $definition) {
                $parentId = null;
                if (($definition['parent_key'] ?? null) !== null) {
                    $parent = $resolved[$definition['parent_key']]
                        ?? GovernanceArea::query()->where('key', $definition['parent_key'])->first();
                    if ($parent === null) {
                        throw new RuntimeException('Reference governance topology parent is missing: '.$definition['parent_key']);
                    }
                    $parentId = $parent->id;
                }

                $locations = $this->locationsFor($definition, $datasetVersion);
                if ($locations === null) {
                    throw new RuntimeException('Reference governance topology location mapping is missing for '.$definition['key']);
                }

                $area = GovernanceArea::query()->updateOrCreate(
                    ['key' => $definition['key']],
                    [
                        'parent_id' => $parentId,
                        'country_code' => $dataset['country'],
                        'governance_type' => $definition['governance_type'],
                        'area_kind' => $definition['area_kind'],
                        'canonical_name' => $definition['canonical_name'],
                        'localized_names' => $definition['localized_names'] ?? null,
                        'rank' => (int) $definition['rank'],
                        'status' => $definition['status'],
                        'metadata' => [
                            'reference_topology' => true,
                            'source' => $dataset['source'],
                            'dataset_version' => $dataset['dataset_version'],
                        ],
                    ],
                );

                $area->locations()->sync(collect($locations)->pluck('id')->all());
                $resolved[$area->key] = $area;
            }
        });

        return $diff;
    }

    private function load(string $country, string $datasetVersion): array
    {
        $country = strtoupper($country);
        $path = database_path('reference/'.strtolower($country).'/'.$datasetVersion.'/governance.json');
        if (! is_file($path)) {
            throw new RuntimeException('Reference governance topology dataset not found.');
        }

        $dataset = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (($dataset['country'] ?? null) !== $country || ($dataset['dataset_version'] ?? null) !== $datasetVersion) {
            throw new RuntimeException('Reference governance topology dataset identity mismatch.');
        }

        return $dataset;
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

    private function isOwnedByDataset(GovernanceArea $area, array $dataset): bool
    {
        $metadata = $area->metadata ?? [];

        return ($metadata['reference_topology'] ?? false) === true
            && ($metadata['source'] ?? null) === $dataset['source']
            && ($metadata['dataset_version'] ?? null) === $dataset['dataset_version'];
    }
}
