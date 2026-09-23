<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\Location;
use App\Models\LocationExternalId;
use App\Models\ReferenceSettlement;
use App\Services\LocationGovernance\Import\ReferenceGeographyImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IranSettlementCatalogSearchTest extends TestCase
{
    use RefreshDatabase;

    private function settlement(string $id, string $parent, string $name): void
    {
        ReferenceSettlement::create([
            'source' => 'IranCountryDivisions/geo_1404',
            'dataset_version' => 'v2',
            'external_id' => $id,
            'parent_external_id' => $parent,
            'source_code' => str_replace('IR-1404-', '', $id),
            'source_row_id' => (int) str_replace('IR-1404-', '', $id),
            'name_fa' => $name,
            'search_name' => $name,
            'classification' => 'unverified_settlement',
            'residential_eligibility' => 'unverified',
            'governance_authorized' => false,
            'operational_promotion_allowed' => false,
            'provenance' => ['source' => 'fixture'],
        ]);
    }

    public function test_catalog_search_is_disabled_by_default(): void
    {
        config()->set('iran_settlement_catalog.enabled', false);
        $this->getJson('/location/reference-settlements?parent_external_id=IR-1404-100')->assertNotFound();
    }

    public function test_parent_scoped_search_never_exposes_official_residence_or_governance(): void
    {
        config()->set('iran_settlement_catalog.enabled', true);
        $this->settlement('IR-1404-201', 'IR-1404-100', 'آبادی بهار');
        $this->settlement('IR-1404-202', 'IR-1404-999', 'آبادی دیگر');
        $this->getJson('/location/reference-settlements?parent_external_id=IR-1404-100&q='.rawurlencode('آبادی'))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.external_id', 'IR-1404-201')
            ->assertJsonPath('data.0.residence_endpoint_allowed', false)
            ->assertJsonPath('data.0.governance_authorized', false)
            ->assertJsonPath('data.0.requires_residence_review', true);
    }

    public function test_catalog_can_resolve_parent_from_canonical_v2_location_without_exposing_source_id_to_ui(): void
    {
        config()->set('iran_settlement_catalog.enabled', true);
        $parent = Location::factory()->create(['country_code' => 'IR', 'status' => 'active']);
        LocationExternalId::create([
            'location_id' => $parent->id,
            'source' => ReferenceGeographyImporter::SOURCE,
            'dataset_version' => 'v2',
            'external_id' => 'IR-1404-100',
            'metadata' => [],
        ]);
        $this->settlement('IR-1404-201', 'IR-1404-100', 'آبادی وابسته به والد رسمی');

        $this->getJson('/location/reference-settlements?parent_location_id='.$parent->id.'&q='.rawurlencode('آبادی'))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.external_id', 'IR-1404-201');
    }

    public function test_catalog_rejects_canonical_parent_without_v2_identity_or_two_parent_modes(): void
    {
        config()->set('iran_settlement_catalog.enabled', true);
        $parent = Location::factory()->create(['country_code' => 'IR', 'status' => 'active']);

        $this->getJson('/location/reference-settlements?parent_location_id='.$parent->id)
            ->assertUnprocessable();
        $this->getJson('/location/reference-settlements?parent_location_id='.$parent->id.'&parent_external_id=IR-1404-100')
            ->assertUnprocessable();
    }

    public function test_search_requires_source_parent_and_rejects_wildcards(): void
    {
        config()->set('iran_settlement_catalog.enabled', true);
        $this->getJson('/location/reference-settlements?q=ab')->assertUnprocessable();
        $this->getJson('/location/reference-settlements?parent_external_id=IR-1404-100&q=%25')->assertUnprocessable();
    }
}
