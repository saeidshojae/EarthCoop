<?php

namespace Tests\Unit\LocationGovernance;

use App\Data\LocationGovernance\ReferenceDataset;
use App\Data\LocationGovernance\ReferenceImportResult;
use App\Services\LocationGovernance\Import\ReferenceGeographyImporter;
use App\Services\LocationGovernance\Import\ReferenceGeographyValidator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ReferenceGeographyArchitectureContractTest extends TestCase
{
    public function test_reference_dataset_loads_versioned_schema_and_locations(): void
    {
        $dataset = ReferenceDataset::fromCountryVersion('IR', 'v1');

        $this->assertSame('IR', $dataset->countryCode);
        $this->assertSame('v1', $dataset->version);
        $this->assertNotEmpty($dataset->schema['types'] ?? []);
        $this->assertNotEmpty($dataset->schema['relations'] ?? []);
        $this->assertNotEmpty($dataset->rows);
    }

    public function test_validator_accepts_the_committed_iran_reference_dataset(): void
    {
        $dataset = ReferenceDataset::fromCountryVersion('IR', 'v1');
        $errors = (new ReferenceGeographyValidator())->validate($dataset);

        $this->assertSame([], $errors);
    }

    public function test_validator_rejects_duplicate_identity_and_disallowed_hierarchy(): void
    {
        $dataset = new ReferenceDataset(
            countryCode: 'IR',
            version: 'v-test',
            schema: [
                'country_code' => 'IR',
                'version' => 'v-test',
                'types' => [
                    ['key' => 'country', 'is_root' => true],
                    ['key' => 'province', 'is_root' => false],
                    ['key' => 'city', 'is_root' => false],
                ],
                'relations' => [
                    ['parent' => 'country', 'child' => 'province'],
                ],
            ],
            rows: [
                ['external_id' => 'ROOT', 'parent_external_id' => null, 'type' => 'country', 'canonical_name' => 'Iran'],
                ['external_id' => 'DUP', 'parent_external_id' => 'ROOT', 'type' => 'city', 'canonical_name' => 'Invalid City'],
                ['external_id' => 'DUP', 'parent_external_id' => 'ROOT', 'type' => 'province', 'canonical_name' => 'Duplicate Province'],
            ],
            rawLocations: '',
        );

        $errors = (new ReferenceGeographyValidator())->validate($dataset);
        $joined = implode(' ', $errors);

        $this->assertStringContainsString('country -> city', $joined);
        $this->assertStringContainsString('Duplicate reference geography external ID: DUP', $joined);
    }

    public function test_importer_does_not_embed_type_or_relation_definitions(): void
    {
        $constants = (new ReflectionClass(ReferenceGeographyImporter::class))->getConstants();

        $this->assertArrayNotHasKey('TYPE_DEFINITIONS', $constants);
        $this->assertArrayNotHasKey('TYPE_RELATIONS', $constants);
    }

    public function test_import_result_is_the_public_count_contract(): void
    {
        $result = new ReferenceImportResult(1, 2, 3, 4, 5);

        $this->assertSame([
            'creates' => 1,
            'updates' => 2,
            'deactivates' => 3,
            'conflicts' => 4,
            'unchanged' => 5,
        ], $result->toArray());
    }
}
