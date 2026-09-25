<?php

namespace App\Data\LocationGovernance;

use InvalidArgumentException;

final class ReferenceDataset
{
    private static ?self $iran1404Cache = null;

    private const IR_1404_GIT_BLOB = 'ca9f4a0d69c7c9d77e6434447c7fe123a322271e';
    private const IR_1404_COUNTS = [
        0 => 1,
        1 => 31,
        2 => 484,
        3 => 1193,
        4 => 2777,
        5 => 1481,
        6 => 99317,
        7 => 191,
    ];
    private const IR_1404_TYPES = [
        0 => 'country',
        1 => 'province',
        2 => 'county',
        3 => 'section',
        4 => 'rural_district',
        5 => 'city',
        6 => 'settlement',
        7 => 'urban_region',
    ];
    private const IR_1404_PARENT_TYPES = [
        0 => [],
        1 => [0],
        2 => [1],
        3 => [2],
        4 => [3],
        5 => [3],
        6 => [4],
        7 => [5],
    ];

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

        if (
            $countryCode === 'IR'
            && $version === 'v2'
            && (! is_file($schemaPath) || ! is_file($locationsPath))
        ) {
            return self::iran1404AdministrativeDataset();
        }

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

    private static function iran1404AdministrativeDataset(): self
    {
        if (self::$iran1404Cache instanceof self) {
            return self::$iran1404Cache;
        }

        $sourceDir = dirname(__DIR__, 3).'/database/reference/source/ir/1404';
        $payload = '';
        for ($part = 1; $part <= 9; $part++) {
            $path = $sourceDir.'/iran.part-'.str_pad((string) $part, 2, '0', STR_PAD_LEFT).'.csv';
            if (! is_file($path) || ! is_readable($path)) {
                throw new InvalidArgumentException('Iran 1404 source chunk is missing: '.basename($path));
            }
            $bytes = file_get_contents($path);
            if ($bytes === false) {
                throw new InvalidArgumentException('Iran 1404 source chunk cannot be read: '.basename($path));
            }
            $payload .= $bytes;
        }

        $gitBlob = sha1('blob '.strlen($payload)."\0".$payload);
        if (! hash_equals(self::IR_1404_GIT_BLOB, $gitBlob)) {
            throw new InvalidArgumentException("Iran 1404 source Git blob mismatch: {$gitBlob}");
        }

        $stream = fopen('php://temp/maxmemory:8388608', 'w+b');
        if ($stream === false) {
            throw new InvalidArgumentException('Cannot allocate Iran 1404 CSV parser stream.');
        }
        fwrite($stream, $payload);
        rewind($stream);

        $header = fgetcsv($stream);
        if (! is_array($header)) {
            fclose($stream);
            throw new InvalidArgumentException('Iran 1404 CSV header is missing.');
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $expectedHeader = ['Id', 'ParentCountryDivisionId', 'Name', 'Code', 'DivisionType'];
        if ($header !== $expectedHeader) {
            fclose($stream);
            throw new InvalidArgumentException('Iran 1404 CSV header is invalid.');
        }

        $seen = [];
        $counts = array_fill_keys(array_keys(self::IR_1404_COUNTS), 0);
        $rows = [];
        $line = 1;

        while (($csv = fgetcsv($stream)) !== false) {
            $line++;
            if ($csv === [null] || $csv === []) {
                continue;
            }
            if (count($csv) !== 5) {
                fclose($stream);
                throw new InvalidArgumentException("Iran 1404 malformed CSV row at line {$line}.");
            }

            [$idRaw, $parentRaw, $nameRaw, $codeRaw, $typeRaw] = $csv;
            $id = filter_var($idRaw, FILTER_VALIDATE_INT);
            $parent = trim((string) $parentRaw) === '' ? null : filter_var($parentRaw, FILTER_VALIDATE_INT);
            $type = filter_var($typeRaw, FILTER_VALIDATE_INT);
            $name = trim((string) $nameRaw);
            $code = trim((string) $codeRaw);

            if ($id === false || $id < 1 || $type === false || ! array_key_exists($type, self::IR_1404_TYPES) || $name === '' || $code === '') {
                fclose($stream);
                throw new InvalidArgumentException("Iran 1404 invalid identity/name/type at line {$line}.");
            }
            if ($parent !== null && $parent === false) {
                fclose($stream);
                throw new InvalidArgumentException("Iran 1404 invalid parent at line {$line}.");
            }
            if (isset($seen[$id])) {
                fclose($stream);
                throw new InvalidArgumentException("Iran 1404 duplicate source ID {$id}.");
            }
            if ($parent === null) {
                if ($type !== 0) {
                    fclose($stream);
                    throw new InvalidArgumentException("Iran 1404 non-country root {$id}.");
                }
            } elseif (! isset($seen[$parent]) || ! in_array($seen[$parent], self::IR_1404_PARENT_TYPES[$type], true)) {
                fclose($stream);
                throw new InvalidArgumentException("Iran 1404 invalid or out-of-order parent for {$id}.");
            }

            $seen[$id] = $type;
            $counts[$type]++;

            if ($type === 6) {
                continue;
            }

            $externalId = 'IR-1404-'.$id;
            $rows[] = [
                'external_id' => $externalId,
                'parent_external_id' => $parent === null ? null : 'IR-1404-'.$parent,
                'type' => self::IR_1404_TYPES[$type],
                'canonical_name' => $name,
                'localized_names' => ['fa' => $name],
                'status' => 'active',
                'provenance' => [
                    'source' => 'IranCountryDivisions/geo_1404',
                    'source_commit' => '68687cf96cc1852d5d38c7283353c80829331758',
                    'source_row_id' => $id,
                    'source_code' => $code,
                    'source_division_type' => $type,
                    'dataset_year' => 1404,
                ],
                'metadata' => [
                    'governance_authorized' => true,
                    'source_authoritative' => true,
                ],
            ];
        }
        fclose($stream);

        foreach (self::IR_1404_COUNTS as $type => $expected) {
            if (($counts[$type] ?? 0) !== $expected) {
                throw new InvalidArgumentException("Iran 1404 source count mismatch for division type {$type}.");
            }
        }
        if (count($rows) !== 6158) {
            throw new InvalidArgumentException('Iran 1404 administrative dataset must contain exactly 6158 rows.');
        }

        $v1SchemaPath = dirname(__DIR__, 3).'/database/reference/ir/v1/schema.json';
        $schema = json_decode((string) file_get_contents($v1SchemaPath), true);
        if (! is_array($schema) || ($schema['country_code'] ?? null) !== 'IR') {
            throw new InvalidArgumentException('Reviewed IR schema template is missing.');
        }
        $schema['key'] = 'ir-reference-v2';
        $schema['version'] = 'v2';
        $schema['name'] = 'Iran 1404 authoritative administrative geography';

        $rawLocations = implode("\n", array_map(
            fn (array $row): string => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $rows,
        ))."\n";

        return self::$iran1404Cache = new self('IR', 'v2', $schema, $rows, $rawLocations);
    }
}
