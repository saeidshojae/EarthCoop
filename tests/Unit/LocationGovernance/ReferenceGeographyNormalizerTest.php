<?php

namespace Tests\Unit\LocationGovernance;

use App\Services\LocationGovernance\Import\ReferenceGeographyNormalizer;
use PHPUnit\Framework\TestCase;

class ReferenceGeographyNormalizerTest extends TestCase
{
    public function test_normalizer_produces_deterministic_reference_identity_without_using_display_name_as_identity(): void
    {
        $row = [
            'external_id' => ' IR-SARI-001 ',
            'parent_external_id' => ' IR-MAZ-001 ',
            'type' => ' city ',
            'canonical_name' => ' Sari ',
            'localized_names' => ['fa' => ' ساری '],
            'status' => ' active ',
            'legacy_numeric_id' => 123,
        ];

        $normalized = (new ReferenceGeographyNormalizer())->normalize($row, 'IR', 'v1');

        $this->assertSame('IR-SARI-001', $normalized['external_id']);
        $this->assertSame('IR-MAZ-001', $normalized['parent_external_id']);
        $this->assertSame('city', $normalized['type_key']);
        $this->assertSame('Sari', $normalized['canonical_name']);
        $this->assertSame('ساری', $normalized['localized_names']['fa']);
        $this->assertSame('active', $normalized['status']);
        $this->assertSame('IR', $normalized['country_code']);
        $this->assertSame('v1', $normalized['dataset_version']);
        $this->assertSame(123, $normalized['provenance']['legacy_numeric_id']);
        $this->assertArrayNotHasKey('legacy_numeric_id', array_diff_key($normalized, ['provenance' => true]));
    }
}
