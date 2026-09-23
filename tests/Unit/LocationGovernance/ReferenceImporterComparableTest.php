<?php

namespace Tests\Unit\LocationGovernance;

use App\Services\LocationGovernance\Import\ReferenceGeographyImporter;
use ReflectionMethod;
use Tests\TestCase;

final class ReferenceImporterComparableTest extends TestCase
{
    public function test_reordered_json_object_keys_do_not_trigger_an_update(): void
    {
        $method = new ReflectionMethod(ReferenceGeographyImporter::class, 'normalizeComparable');
        $importer = app(ReferenceGeographyImporter::class);

        $fromDatabase = [
            'provenance' => [
                'dataset_version' => 'v2',
                'source_code' => '07',
                'source' => 'earthcoop-reference',
                'nested' => ['b' => false, 'a' => 1404],
            ],
            'metadata' => ['municipal_reconciliation_required' => false, 'governance_authorized' => false],
        ];
        $fromReference = [
            'metadata' => ['governance_authorized' => false, 'municipal_reconciliation_required' => false],
            'provenance' => [
                'source' => 'earthcoop-reference',
                'nested' => ['a' => 1404, 'b' => false],
                'source_code' => '07',
                'dataset_version' => 'v2',
            ],
        ];

        $this->assertSame(
            $method->invoke($importer, $fromDatabase),
            $method->invoke($importer, $fromReference),
        );

        $changed = $fromReference;
        $changed['provenance']['source_code'] = '08';
        $this->assertNotSame(
            $method->invoke($importer, $fromDatabase),
            $method->invoke($importer, $changed),
        );

        $wrongType = $fromReference;
        $wrongType['metadata']['governance_authorized'] = 0;
        $this->assertNotSame(
            $method->invoke($importer, $fromDatabase),
            $method->invoke($importer, $wrongType),
        );
    }

    public function test_order_of_json_arrays_remains_semantically_significant(): void
    {
        $method = new ReflectionMethod(ReferenceGeographyImporter::class, 'normalizeComparable');
        $importer = app(ReferenceGeographyImporter::class);
        $this->assertNotSame(
            $method->invoke($importer, ['members' => [1, 2]]),
            $method->invoke($importer, ['members' => [2, 1]]),
        );
    }
}
