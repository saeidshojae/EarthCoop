<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\ReferenceSettlement;
use App\Models\LocationExternalId;
use Tests\Support\LocationGovernance\LocationFixture;
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

    public function test_registration_search_can_resolve_verified_v1_parent_location_to_v2_source_parent(): void
    {
        config()->set('iran_settlement_catalog.enabled', true);
        $schema = LocationFixture::iranSchema();
        $parent = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'rural_district',
        ])->last();

        LocationExternalId::query()->create([
            'location_id' => $parent->id,
            'source' => 'earthcoop-reference',
            'dataset_version' => 'v1',
            'external_id' => 'IR-MAZ-SARI-CHAHARDANGEH-RD',
            'metadata' => ['fixture' => true],
        ]);
        $this->settlement('IR-1404-201', 'IR-1404-1938', 'آبادی بهار');

        $this->getJson('/location/reference-settlements?parent_location_id='.$parent->id.'&q='.rawurlencode('آبادی'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.external_id', 'IR-1404-201');
    }

    public function test_registration_search_uses_direct_v2_parent_identity_without_crosswalk(): void
    {
        config()->set('iran_settlement_catalog.enabled', true);
        $schema = LocationFixture::iranSchema();
        $parent = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'rural_district',
        ])->last();

        LocationExternalId::query()->create([
            'location_id' => $parent->id,
            'source' => 'earthcoop-reference',
            'dataset_version' => 'v2',
            'external_id' => 'IR-1404-5555',
            'metadata' => ['fixture' => true],
        ]);
        $this->settlement('IR-1404-99001', 'IR-1404-5555', 'آبادی مستقیم نسخه دو');

        $this->getJson('/location/reference-settlements?parent_location_id='.$parent->id.'&q='.rawurlencode('آبادی'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.external_id', 'IR-1404-99001');
    }

    public function test_registration_search_fails_closed_for_unmapped_parent_location(): void
    {
        config()->set('iran_settlement_catalog.enabled', true);
        $schema = LocationFixture::iranSchema();
        $parent = LocationFixture::createPath($schema, ['country'])->last();

        $this->getJson('/location/reference-settlements?parent_location_id='.$parent->id.'&q='.rawurlencode('آبادی'))
            ->assertUnprocessable()
            ->assertJsonPath('data', []);
    }

    public function test_search_requires_source_parent_and_rejects_wildcards(): void
    {
        config()->set('iran_settlement_catalog.enabled', true);
        $this->getJson('/location/reference-settlements?q=ab')->assertUnprocessable();
        $this->getJson('/location/reference-settlements?parent_external_id=IR-1404-100&q=%25')->assertUnprocessable();
    }
}
