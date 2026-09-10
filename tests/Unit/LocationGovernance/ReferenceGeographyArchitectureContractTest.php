<?php

namespace Tests\Unit\LocationGovernance;

use App\Data\LocationGovernance\ReferenceDataset;
use App\Data\LocationGovernance\ReferenceImportResult;
use App\Services\LocationGovernance\Import\ReferenceGeographyValidator;
use PHPUnit\Framework\TestCase;

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
