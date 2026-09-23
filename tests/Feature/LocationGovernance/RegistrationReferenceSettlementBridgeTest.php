<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\Location;
use App\Models\LocationExternalId;
use App\Models\ReferenceSettlement;
use App\Models\ReferenceSettlementResidenceClaim;
use App\Models\User;
use App\Services\LocationGovernance\Import\ReferenceGeographyImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

final class RegistrationReferenceSettlementBridgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
            'location-governance.groups_enabled' => false,
            'iran_settlement_catalog.enabled' => true,
            'iran_settlement_catalog.claims_enabled' => true,
            'iran_settlement_catalog.registration_bridge_enabled' => true,
        ]);
    }

    /** @return array{0: Location, 1: ReferenceSettlement} */
    private function scenario(): array
    {
        $schema = LocationFixture::iranSchema();
        $anchor = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'rural_district',
        ])->last();
        $anchor->forceFill(['country_code' => 'IR', 'status' => 'active'])->save();

        LocationExternalId::create([
            'location_id' => $anchor->id,
            'source' => ReferenceGeographyImporter::SOURCE,
            'dataset_version' => 'v2',
            'external_id' => 'IR-1404-100',
            'metadata' => ['fixture' => true],
        ]);

        $settlement = ReferenceSettlement::create([
            'source' => 'IranCountryDivisions/geo_1404',
            'dataset_version' => 'v2',
            'external_id' => 'IR-1404-201',
            'parent_external_id' => 'IR-1404-100',
            'source_code' => '201',
            'source_row_id' => 201,
            'name_fa' => 'آبادی پل ثبت نام',
            'search_name' => 'آبادی پل ثبت نام',
            'classification' => 'unverified_settlement',
            'residential_eligibility' => 'unverified',
            'governance_authorized' => false,
            'operational_promotion_allowed' => false,
            'provenance' => ['source' => 'fixture'],
        ]);

        return [$anchor, $settlement];
    }

    public function test_registration_completes_on_existing_settlement_without_creating_duplicate_location_or_governance(): void
    {
        [$anchor, $settlement] = $this->scenario();
        $user = User::factory()->create();
        $locationsBefore = Location::count();
        $governanceBefore = GovernanceArea::count();

        $response = $this->actingAs($user)->post(route('register.step3.process'), [
            'reference_settlement_external_id' => $settlement->external_id,
        ]);

        $response->assertRedirect(route('home'));
        $relationship = $user->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')->whereNull('ended_at')->sole();
        $claim = ReferenceSettlementResidenceClaim::query()
            ->where('user_id', $user->id)->where('reference_settlement_id', $settlement->id)->sole();

        $this->assertSame($anchor->id, $relationship->location_id);
        $this->assertSame('registration_step3_reference_settlement_anchor', $relationship->evidence['source']);
        $this->assertSame($settlement->external_id, $relationship->evidence['reference_settlement_external_id']);
        $this->assertSame($relationship->id, $claim->anchor_relationship_id);
        $this->assertSame('pending', $claim->status);
        $this->assertSame($locationsBefore, Location::count());
        $this->assertSame($governanceBefore, GovernanceArea::count());
        $this->assertDatabaseCount('location_proposals', 0);
    }

    public function test_registration_bridge_is_independently_disabled_by_default_gate(): void
    {
        [, $settlement] = $this->scenario();
        config()->set('iran_settlement_catalog.registration_bridge_enabled', false);
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('register.step3'))->post(route('register.step3.process'), [
            'reference_settlement_external_id' => $settlement->external_id,
        ])->assertRedirect(route('register.step3'))
          ->assertSessionHasErrors('reference_settlement_external_id');

        $this->assertSame(0, $user->fresh()->locationRelationships()->count());
        $this->assertDatabaseCount('reference_settlement_residence_claims', 0);
    }

    public function test_registration_fails_closed_when_settlement_parent_has_no_active_v2_mapping(): void
    {
        [, $settlement] = $this->scenario();
        LocationExternalId::query()
            ->where('source', ReferenceGeographyImporter::SOURCE)
            ->where('dataset_version', 'v2')
            ->delete();
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('register.step3'))->post(route('register.step3.process'), [
            'reference_settlement_external_id' => $settlement->external_id,
        ])->assertRedirect(route('register.step3'))
          ->assertSessionHasErrors('reference_settlement_external_id');

        $this->assertSame(0, $user->fresh()->locationRelationships()->count());
        $this->assertDatabaseCount('reference_settlement_residence_claims', 0);
    }

    public function test_registration_accepts_exactly_one_of_location_proposal_or_reference_settlement(): void
    {
        [$anchor, $settlement] = $this->scenario();
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('register.step3'))->post(route('register.step3.process'), [
            'location_id' => $anchor->id,
            'reference_settlement_external_id' => $settlement->external_id,
        ])->assertRedirect(route('register.step3'))->assertSessionHasErrors('location_id');

        $this->assertSame(0, $user->fresh()->locationRelationships()->count());
        $this->assertDatabaseCount('reference_settlement_residence_claims', 0);
    }

    public function test_verified_residential_evidence_still_does_not_convert_claim_to_official_residence(): void
    {
        [$anchor, $settlement] = $this->scenario();
        $settlement->forceFill([
            'classification' => 'verified_residential_village',
            'residential_eligibility' => 'verified',
        ])->save();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('register.step3.process'), [
            'reference_settlement_external_id' => $settlement->external_id,
        ])->assertRedirect(route('home'));

        $relationship = $user->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')->whereNull('ended_at')->sole();
        $claim = ReferenceSettlementResidenceClaim::query()->where('user_id', $user->id)->sole();

        $this->assertSame($anchor->id, $relationship->location_id);
        $this->assertSame('residential_evidence_verified', $claim->status);
        $this->assertFalse($settlement->fresh()->governance_authorized);
        $this->assertFalse($settlement->fresh()->operational_promotion_allowed);
    }
}
