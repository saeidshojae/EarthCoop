<?php

namespace App\Services\LocationGovernance\Import;

final class ReferenceGeographyNormalizer
{
    public function normalize(array $row, string $countryCode, string $version): array
    {
        $localizedNames = [];
        foreach (($row['localized_names'] ?? []) as $locale => $name) {
            $localizedNames[trim((string) $locale)] = trim((string) $name);
        }

        $provenance = array_merge(
            is_array($row['provenance'] ?? null) ? $row['provenance'] : [],
            [
                'source' => 'earthcoop-reference',
                'dataset_version' => trim($version),
            ]
        );

        if (array_key_exists('legacy_numeric_id', $row)) {
            $provenance['legacy_numeric_id'] = $row['legacy_numeric_id'];
        }

        return [
            'external_id' => trim((string) ($row['external_id'] ?? '')),
            'parent_external_id' => ($row['parent_external_id'] ?? null) === null
                ? null
                : trim((string) $row['parent_external_id']),
            'type_key' => trim((string) ($row['type'] ?? $row['type_key'] ?? '')),
            'canonical_name' => trim((string) ($row['canonical_name'] ?? $row['name'] ?? '')),
            'localized_names' => $localizedNames,
            'status' => trim((string) ($row['status'] ?? 'active')),
            'country_code' => strtoupper(trim($countryCode)),
            'dataset_version' => trim($version),
            'centroid_latitude' => $row['centroid_latitude'] ?? null,
            'centroid_longitude' => $row['centroid_longitude'] ?? null,
            'metadata' => is_array($row['metadata'] ?? null) ? $row['metadata'] : [],
            'provenance' => $provenance,
        ];
    }
}
