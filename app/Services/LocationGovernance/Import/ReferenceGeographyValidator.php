<?php

namespace App\Services\LocationGovernance\Import;

use App\Data\LocationGovernance\ReferenceDataset;

final class ReferenceGeographyValidator
{
    public function validate(ReferenceDataset $dataset): array
    {
        $errors = [];
        $schema = $dataset->schema;

        if (strtoupper((string) ($schema['country_code'] ?? '')) !== $dataset->countryCode) {
            $errors[] = 'Schema country_code does not match the requested dataset.';
        }
        if ((string) ($schema['version'] ?? '') !== $dataset->version) {
            $errors[] = 'Schema version does not match the requested dataset.';
        }

        $types = $schema['types'] ?? null;
        $relations = $schema['relations'] ?? null;
        if (! is_array($types) || $types === []) {
            $errors[] = 'Schema must declare location types.';
            $types = [];
        }
        if (! is_array($relations) || $relations === []) {
            $errors[] = 'Schema must declare location type relations.';
            $relations = [];
        }

        $typeMap = [];
        $rootCount = 0;
        foreach ($types as $index => $type) {
            $key = is_array($type) ? trim((string) ($type['key'] ?? '')) : '';
            if ($key === '') {
                $errors[] = "Schema type at index {$index} is missing a key.";
                continue;
            }
            if (isset($typeMap[$key])) {
                $errors[] = "Duplicate schema location type: {$key}.";
                continue;
            }
            $typeMap[$key] = $type;
            if ((bool) ($type['is_root'] ?? false)) {
                $rootCount++;
            }
        }
        if ($rootCount !== 1) {
            $errors[] = 'Schema must declare exactly one root location type.';
        }

        $allowed = [];
        foreach ($relations as $index => $relation) {
            $parent = is_array($relation) ? trim((string) ($relation['parent'] ?? '')) : '';
            $child = is_array($relation) ? trim((string) ($relation['child'] ?? '')) : '';
            if (! isset($typeMap[$parent]) || ! isset($typeMap[$child])) {
                $errors[] = "Schema relation at index {$index} references an unknown type.";
                continue;
            }
            $allowed[$parent][$child] = true;
        }

        $seen = [];
        $rowTypes = [];
        $rootRows = 0;
        foreach ($dataset->rows as $index => $row) {
            if (! is_array($row)) {
                $errors[] = 'Reference geography row '.($index + 1).' must be an object.';
                continue;
            }

            $externalId = trim((string) ($row['external_id'] ?? ''));
            $type = trim((string) ($row['type'] ?? ''));
            $name = trim((string) ($row['canonical_name'] ?? ''));
            $parentId = isset($row['parent_external_id']) && $row['parent_external_id'] !== null
                ? trim((string) $row['parent_external_id'])
                : null;

            if ($externalId === '' || $type === '' || $name === '') {
                $errors[] = 'Reference geography row '.($index + 1).' is missing required identity fields.';
                continue;
            }
            if (isset($seen[$externalId])) {
                $errors[] = "Duplicate reference geography external ID: {$externalId}.";
                continue;
            }
            if (! isset($typeMap[$type])) {
                $errors[] = "Reference geography {$externalId} uses unknown type {$type}.";
            }

            if ($parentId === null || $parentId === '') {
                $rootRows++;
                if (isset($typeMap[$type]) && ! (bool) ($typeMap[$type]['is_root'] ?? false)) {
                    $errors[] = "Reference geography {$externalId} has no parent but its type is not root.";
                }
            } elseif (! isset($seen[$parentId])) {
                $errors[] = "Reference geography parent {$parentId} must appear before child {$externalId}.";
            } else {
                $parentType = $rowTypes[$parentId] ?? null;
                if ($parentType !== null && ! isset($allowed[$parentType][$type])) {
                    $errors[] = "Reference geography relation {$parentType} -> {$type} is not allowed for {$externalId}.";
                }
            }

            $seen[$externalId] = true;
            $rowTypes[$externalId] = $type;
        }

        if ($rootRows !== 1) {
            $errors[] = 'Reference geography dataset must contain exactly one root row.';
        }

        return array_values(array_unique($errors));
    }
}
