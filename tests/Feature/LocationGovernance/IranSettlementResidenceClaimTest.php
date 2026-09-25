<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\Location;
use App\Models\ReferenceSettlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class IranSettlementResidenceClaimTest extends TestCase
{
    use RefreshDatabase;

    private function settlement(array $overrides = []): ReferenceSettlement
    {
        return ReferenceSettlement::create(array_merge([
            'source' => 'IranCountryDivisions/geo_1404',
            'dataset_version' => 'v2',
            'external_id' => 'IR-1404-201',
            'parent_external_id' => 'IR-1404-100',
            'source_code' => '201',
            'source_row_id' => 201,
            'name_fa' => 'آبادی نمونه',
            'search_name' => 'آبادی نمونه',
            'classification' => 'unverified_settlement',
            'residential_eligibility' => 'unverified',
            'governance_authorized' => false,
            'operational_promotion_allowed' => false,
            'provenance' => ['source' => 'fixture'],
        ], $overrides));
    }

    public function test_claims_are_disabled_by_default_even_for_authenticated_users(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/location/reference-settlement-residence-claims', ['external_id' => 'IR-1404-201'])
            ->assertNotFound();
        $this->assertDatabaseCount('reference_settlement_residence_claims', 0);
    }

    public function test_authenticated_claim_is_idempotent_without_location_membership_or_governance(): void
    {
        config()->set('iran_settlement_catalog.enabled', true);
        config()->set('iran_settlement_catalog.claims_enabled', true);
        $settlement = $this->settlement();
        $user = User::factory()->create();
        $before = Location::count();

        $response = $this->actingAs($user)->postJson('/location/reference-settlement-residence-claims', [
            'external_id' => $settlement->external_id,
        ]);
        $response->assertCreated()->assertJsonPath('status', 'pending')
            ->assertJsonPath('residence_confirmed', false)
            ->assertJsonPath('official_groups_activated', false);
        $this->actingAs($user)->postJson('/location/reference-settlement-residence-claims', [
            'external_id' => $settlement->external_id,
        ])->assertOk()->assertJsonPath('claim_id', $response->json('claim_id'));
        $this->assertDatabaseCount('reference_settlement_residence_claims', 1);
        $this->assertSame($before, Location::count());
        $this->assertSame(0, $user->locationRelationships()->count());
        $this->assertSame(0, DB::table('governance_areas')->count());
    }

    public function test_private_claim_status_does_not_expose_other_users(): void
    {
        config()->set('iran_settlement_catalog.enabled', true);
        config()->set('iran_settlement_catalog.claims_enabled', true);
        $settlement = $this->settlement();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->actingAs($first)->postJson('/location/reference-settlement-residence-claims', [
            'external_id' => $settlement->external_id,
        ])->assertCreated();
        $this->actingAs($second)->getJson('/location/reference-settlement-residence-claims')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($second)->postJson('/location/reference-settlement-residence-claims', [
            'external_id' => $settlement->external_id,
        ])->assertCreated();
        $this->actingAs($first)->getJson('/location/reference-settlement-residence-claims')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.external_id', $settlement->external_id)
            ->assertJsonPath('data.0.residence_confirmed', false);
        $this->assertDatabaseCount('reference_settlement_residence_claims', 2);
    }

    public function test_needs_review_settlement_remains_open_for_additional_distinct_claimants(): void
    {
        config()->set('iran_settlement_catalog.enabled', true);
        config()->set('iran_settlement_catalog.claims_enabled', true);
        $settlement = $this->settlement([
            'classification' => 'needs_review',
            'residential_eligibility' => 'unverified',
        ]);

        $this->actingAs(User::factory()->create())->postJson('/location/reference-settlement-residence-claims', [
            'external_id' => $settlement->external_id,
        ])->assertCreated()->assertJsonPath('status', 'pending');

        $this->actingAs(User::factory()->create())->postJson('/location/reference-settlement-residence-claims', [
            'external_id' => $settlement->external_id,
        ])->assertCreated()->assertJsonPath('status', 'pending');

        $this->assertDatabaseCount('reference_settlement_residence_claims', 2);
        $this->assertSame('needs_review', $settlement->fresh()->classification);
        $this->assertSame(0, DB::table('governance_areas')->count());
    }

    public function test_verified_residential_evidence_can_accept_claim_without_confirming_residence(): void
    {
        config()->set('iran_settlement_catalog.enabled', true);
        config()->set('iran_settlement_catalog.claims_enabled', true);
        $settlement = $this->settlement([
            'classification' => 'verified_residential_village',
            'residential_eligibility' => 'verified',
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/location/reference-settlement-residence-claims', [
            'external_id' => $settlement->external_id,
        ])->assertCreated()
            ->assertJsonPath('status', 'residential_evidence_verified')
            ->assertJsonPath('residence_confirmed', false)
            ->assertJsonPath('official_groups_activated', false);

        $this->assertSame(0, $user->locationRelationships()->count());
        $this->assertSame(0, DB::table('governance_areas')->count());
    }

    public function test_unauthenticated_or_nonresidential_records_cannot_create_claim(): void
    {
        config()->set('iran_settlement_catalog.enabled', true);
        config()->set('iran_settlement_catalog.claims_enabled', true);
        $settlement = $this->settlement(['classification' => 'verified_nonresidential_place', 'residential_eligibility' => 'ineligible']);
        $this->postJson('/location/reference-settlement-residence-claims', [
            'external_id' => $settlement->external_id,
        ])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson('/location/reference-settlement-residence-claims', [
            'external_id' => $settlement->external_id,
        ])->assertUnprocessable();
        $this->assertDatabaseCount('reference_settlement_residence_claims', 0);
    }

    public function test_claimant_cannot_claim_a_nonexistent_source_identity(): void
    {
        config()->set('iran_settlement_catalog.enabled', true);
        config()->set('iran_settlement_catalog.claims_enabled', true);
        $this->actingAs(User::factory()->create())->postJson('/location/reference-settlement-residence-claims', [
            'external_id' => 'IR-1404-99999999',
        ])->assertNotFound();
        $this->assertDatabaseCount('reference_settlement_residence_claims', 0);
    }
}
