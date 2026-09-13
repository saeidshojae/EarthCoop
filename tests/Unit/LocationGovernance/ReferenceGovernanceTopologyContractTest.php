<?php

namespace Tests\Unit\LocationGovernance;

use PHPUnit\Framework\TestCase;

class ReferenceGovernanceTopologyContractTest extends TestCase
{
    public function test_iran_reference_topology_contains_the_complete_urban_governance_chain(): void
    {
        $dataset = json_decode(
            (string) file_get_contents(dirname(__DIR__, 3).'/database/reference/ir/v1/governance.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $areas = collect($dataset['areas'])->keyBy('key');

        $expectedParents = [
            'earthcoop-global' => null,
            'earthcoop-continent-asia' => 'earthcoop-global',
            'ir-reference-v1-country' => 'earthcoop-continent-asia',
            'ir-reference-v1-mazandaran' => 'ir-reference-v1-country',
            'ir-reference-v1-sari-county' => 'ir-reference-v1-mazandaran',
            'ir-reference-v1-sari-central-section' => 'ir-reference-v1-sari-county',
            'ir-reference-v1-sari' => 'ir-reference-v1-sari-central-section',
            'ir-reference-v1-sari-urban-region-01' => 'ir-reference-v1-sari',
            'ir-reference-v1-sari-neighborhood-01' => 'ir-reference-v1-sari-urban-region-01',
        ];

        foreach ($expectedParents as $key => $parentKey) {
            $this->assertTrue($areas->has($key), "Missing governance area: {$key}");
            $this->assertSame($parentKey, $areas[$key]['parent_key'] ?? null, "Unexpected parent for {$key}");
        }

        $this->assertNull($areas['earthcoop-global']['country_code'] ?? null);
        $this->assertNull($areas['earthcoop-continent-asia']['country_code'] ?? null);
        $this->assertSame('IR', $areas['ir-reference-v1-country']['country_code'] ?? 'IR');
    }

    public function test_iran_reference_topology_contains_the_complete_rural_governance_chain(): void
    {
        $dataset = json_decode(
            (string) file_get_contents(dirname(__DIR__, 3).'/database/reference/ir/v1/governance.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $areas = collect($dataset['areas'])->keyBy('key');

        $expectedParents = [
            'ir-reference-v1-chahardangeh-section' => 'ir-reference-v1-sari-county',
            'ir-reference-v1-chahardangeh-rural-district' => 'ir-reference-v1-chahardangeh-section',
            'ir-reference-v1-chahardangeh-village-01' => 'ir-reference-v1-chahardangeh-rural-district',
            'ir-reference-v1-chahardangeh-village-no-neighborhood' => 'ir-reference-v1-chahardangeh-rural-district',
        ];

        foreach ($expectedParents as $key => $parentKey) {
            $this->assertTrue($areas->has($key), "Missing governance area: {$key}");
            $this->assertSame($parentKey, $areas[$key]['parent_key'] ?? null, "Unexpected parent for {$key}");
        }
    }
}
