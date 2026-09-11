<?php

namespace App\Data\LocationGovernance;

use InvalidArgumentException;

final class ReferenceDataset
{
    public function __construct(
        public readonly string $countryCode,
        public readonly string $version,
        public readonly array $schema,
        public readonly array $rows,
        public readonly string $rawLocations,
    ) {
    }

    public static function fromCountryVersion(string $countryCode, string $version): self
    {
        $countryCode = strtoupper(trim($countryCode));
        $version = trim($version);

        if ($countryCode === '' || $version === '') {
            throw new InvalidArgumentException('Reference geography country and version are required.');
        }

        $base = dirname(__DIR__, 3).'/database/reference/'.strtolower($countryCode).'/'.$version;
        $schemaPath = $base.'/schema.json';
        $locationsPath = $base.'/locations.jsonl';

        if (! is_file($schemaPath) || ! is_readable($schemaPath)) {
            throw new InvalidArgumentException("Reference geography schema not found: {$countryCode}/{$version}");
        }
        if (! is_file($locationsPath) || ! is_readable($locationsPath)) {
            throw new InvalidArgumentException("Reference geography dataset not found: {$countryCode}/{$version}");
        }

        $rawSchema = file_get_contents($schemaPath);
        $rawLocations = file_get_contents($locationsPath);
        if ($rawSchema === false || $rawLocations === false) {
            throw new InvalidArgumentException("Reference geography files cannot be read: {$countryCode}/{$version}");
        }

        $schema = json_decode($rawSchema, true);
        if (! is_array($schema)) {
            throw new InvalidArgumentException("Invalid reference geography schema JSON: {$countryCode}/{$version}");
        }

        $rows = [];
        foreach (preg_split('/\r\n|\n|\r/', $rawLocations) ?: [] as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (! is_array($decoded)) {
                throw new InvalidArgumentException('Invalid reference geography JSON on line '.($index + 1));
            }
            $rows[] = $decoded;
        }

        if ($rows === []) {
            throw new InvalidArgumentException("Reference geography dataset is empty: {$countryCode}/{$version}");
        }

        return new self($countryCode, $version, $schema, $rows, $rawLocations);
    }
}
