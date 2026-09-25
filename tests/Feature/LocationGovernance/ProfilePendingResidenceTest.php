<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\Location;
use App\Models\LocationExternalId;
use App\Models\LocationSchema;
use App\Models\ReferenceSettlement;
use App\Models\ReferenceSettlementResidenceClaim;
use App\Models\PendingResidenceIntent;
use App\Models\LocationProposal;
use App\Models\User;
use App\Services\LocationGovernance\LocationStructureClaimService;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class ProfilePendingResidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
        ]);
    }

    public function test_proposal_under_current_anchor_sets_pending_intent_without_transfer(): void
    {
        [$user, $anchor, $proposal] = $this->makeCurrentAnchorScenario();

        $response = $this->actingAs($user)->put(route('profile.update.address'), [
            'location_proposal_id' => $proposal->id,
        ]);

        $response->assertSessionHasNoErrors();
        $current = $user->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();
        $intent = $user->fresh()->pendingResidenceIntents()->where('status', 'pending')->sole();

        $this->assertSame($anchor->id, $current->location_id);
        $this->assertFalse((bool) $current->explicit_transfer);
        $this->assertSame($proposal->id, $intent->location_proposal_id);
        $this->assertSame($current->id, $intent->anchor_relationship_id);
    }

    public function test_proposal_under_different_approved_anchor_performs_one_real_transfer_then_sets_intent(): void
    {
        $schema = LocationFixture::iranSchema();
        $oldHome = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city'],
            ['Old Iran', 'Old Province', 'Old County', 'Old Section', 'Old City'],
        )->last();
        $newAnchor = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'],
            ['New Iran', 'New Province', 'New County', 'New Section', 'New City', 'New Region', 'New Neighborhood'],
        )->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $user = User::factory()->create();
        app(ResidenceService::class)->setInitialPrimaryResidence($user, $oldHome, ['source' => 'test']);
        $proposal = app(LocationProposalService::class)->propose($user, $newAnchor, $streetType, [
            'canonical_name' => 'خیابان محل جدید پیشنهادی',
        ]);

        $response = $this->actingAs($user)->put(route('profile.update.address'), [
            'location_proposal_id' => $proposal->id,
        ]);

        $response->assertSessionHasNoErrors();
        $current = $user->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();
        $intent = $user->fresh()->pendingResidenceIntents()->where('status', 'pending')->sole();

        $this->assertSame($newAnchor->id, $current->location_id);
        $this->assertTrue((bool) $current->explicit_transfer);
        $this->assertSame('profile_pending_residence_anchor', $current->change_reason);
        $this->assertSame($current->id, $intent->anchor_relationship_id);
        $this->assertSame(1, $user->fresh()->locationRelationships()->where('explicit_transfer', true)->count());
    }

    public function test_pending_direct_street_under_city_requires_and_persists_complete_structural_claim_chain(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $user = User::factory()->create();
        $regionClaim = app(LocationStructureClaimService::class)->findOrCreateOpenClaim($city, 'no_urban_region', $user);
        $neighborhoodClaim = app(LocationStructureClaimService::class)->findOrCreateOpenClaim($city, 'no_neighborhood', $user);
        $proposal = app(LocationProposalService::class)->propose(
            $user,
            $city,
            $streetType,
            [
                'canonical_name' => 'خیابان مستقیم پیشنهادی پروفایل',
                'localized_names' => ['fa' => 'خیابان مستقیم پیشنهادی پروفایل'],
            ],
            [$regionClaim, $neighborhoodClaim],
        );

        $response = $this->actingAs($user)->put(route('profile.update.address'), [
            'location_proposal_id' => $proposal->id,
            'location_structure_claim_ids' => [$regionClaim->id, $neighborhoodClaim->id],
        ]);

        $response->assertSessionHasNoErrors();
        $current = $user->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();

        $this->assertSame($city->id, $current->location_id);
        $this->assertEqualsCanonicalizing(
            [$regionClaim->id, $neighborhoodClaim->id],
            $current->metadata['structural_claim_ids']
        );
        $this->assertTrue($regionClaim->fresh()->evidence()->where('user_id', $user->id)->exists());
        $this->assertTrue($neighborhoodClaim->fresh()->evidence()->where('user_id', $user->id)->exists());
        $this->assertSame($proposal->id, $user->fresh()->pendingResidenceIntents()->where('status', 'pending')->sole()->location_proposal_id);
    }

    public function test_pending_structural_proposal_refreshes_claims_when_profile_anchor_is_unchanged(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $user = User::factory()->create();
        app(ResidenceService::class)->setInitialPrimaryResidence($user, $city, ['source' => 'test']);

        $regionClaim = app(LocationStructureClaimService::class)->findOrCreateOpenClaim($city, 'no_urban_region', $user);
        $neighborhoodClaim = app(LocationStructureClaimService::class)->findOrCreateOpenClaim($city, 'no_neighborhood', $user);
        $proposal = app(LocationProposalService::class)->propose(
            $user,
            $city,
            $streetType,
            ['canonical_name' => 'خیابان ساختاری پیشنهادی'],
            [$regionClaim, $neighborhoodClaim],
        );

        $response = $this->actingAs($user)->put(route('profile.update.address'), [
            'location_proposal_id' => $proposal->id,
            'location_structure_claim_ids' => [$regionClaim->id, $neighborhoodClaim->id],
        ]);

        $response->assertSessionHasNoErrors();
        $current = $user->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();

        $this->assertSame($city->id, $current->location_id);
        $this->assertEqualsCanonicalizing(
            [$regionClaim->id, $neighborhoodClaim->id],
            $current->metadata['structural_claim_ids']
        );
        $this->assertTrue($regionClaim->fresh()->evidence()->where('user_id', $user->id)->exists());
        $this->assertTrue($neighborhoodClaim->fresh()->evidence()->where('user_id', $user->id)->exists());
        $this->assertSame(0, $user->fresh()->locationRelationships()->where('explicit_transfer', true)->count());
        $this->assertSame($proposal->id, $user->fresh()->pendingResidenceIntents()->where('status', 'pending')->sole()->location_proposal_id);
    }

    public function test_selecting_current_approved_location_cancels_old_pending_intent_without_transfer(): void
    {
        [$user, $anchor, $proposal] = $this->makeCurrentAnchorScenario();
        $intent = app(ResidenceService::class)->setPendingResidenceIntent($user, $proposal, ['source' => 'test']);

        $response = $this->actingAs($user)->put(route('profile.update.address'), [
            'location_id' => $anchor->id,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $intent->fresh()->status);
        $this->assertSame(0, $user->fresh()->locationRelationships()->where('explicit_transfer', true)->count());
        $this->assertSame($anchor->id, $user->fresh()->locationRelationships()->whereNull('ended_at')->sole()->location_id);
    }

    public function test_rejected_proposal_cannot_change_profile_residence(): void
    {
        [$user, $anchor, $proposal] = $this->makeCurrentAnchorScenario();
        app(LocationProposalService::class)->reject($proposal, User::factory()->create(), 'rejected');

        $response = $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.update.address'), [
                'location_proposal_id' => $proposal->id,
            ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHasErrors('location_proposal_id');
        $this->assertSame($anchor->id, $user->fresh()->locationRelationships()->whereNull('ended_at')->sole()->location_id);
        $this->assertSame(0, $user->fresh()->pendingResidenceIntents()->where('status', 'pending')->count());
    }

    public function test_profile_edit_shows_approved_anchor_and_pending_exact_location_as_distinct_states(): void
    {
        [$user, $anchor, $proposal] = $this->makeCurrentAnchorScenario();
        app(ResidenceService::class)->setPendingResidenceIntent($user, $proposal, ['source' => 'test']);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee($anchor->canonical_name);
        $response->assertSee($proposal->canonical_name);
        $response->assertSee('در انتظار بررسی');
    }


    public function test_profile_edit_hydrates_verified_v1_anchor_through_active_v2_path(): void
    {
        $v1Schema = LocationFixture::iranSchema();
        $v1Path = LocationFixture::createPath(
            $v1Schema,
            ['country', 'province', 'county', 'section', 'rural_district'],
            ['Iran v1', 'Mazandaran v1', 'Sari v1', 'Chahardangeh v1', 'RD v1'],
        );
        $v1Anchor = $v1Path->last();
        LocationExternalId::query()->create([
            'location_id' => $v1Anchor->id,
            'source' => 'earthcoop-reference',
            'dataset_version' => 'v1',
            'external_id' => 'IR-MAZ-SARI-CHAHARDANGEH-RD',
            'metadata' => ['fixture' => true],
        ]);

        $v2Schema = LocationSchema::query()->create([
            'key' => 'ir-reference-v2',
            'country_code' => 'IR',
            'name' => 'Iran 1404',
            'version' => 'v2',
            'status' => 'active',
            'metadata' => ['runtime_active' => true],
        ]);
        $types = $v1Schema->types->keyBy('key');
        $parent = null;
        $v2Path = collect();
        foreach ([
            ['country', 'ایران'],
            ['province', 'مازندران'],
            ['county', 'ساری'],
            ['section', 'چهاردانگه'],
            ['rural_district', 'دهستان چهاردانگه'],
        ] as [$typeKey, $name]) {
            $parent = Location::factory()->create([
                'parent_id' => $parent?->id,
                'location_schema_id' => $v2Schema->id,
                'location_type_id' => $types[$typeKey]->id,
                'country_code' => 'IR',
                'name' => $name,
                'canonical_name' => $name,
                'localized_names' => ['fa' => $name],
                'level' => $typeKey,
                'status' => 'active',
            ]);
            $v2Path->push($parent);
        }
        $v2Anchor = $v2Path->last();
        LocationExternalId::query()->create([
            'location_id' => $v2Anchor->id,
            'source' => 'earthcoop-reference',
            'dataset_version' => 'v2',
            'external_id' => 'IR-1404-1938',
            'metadata' => ['fixture' => true],
        ]);

        $global = GovernanceArea::factory()->official()->create([
            'key' => 'earthcoop-global',
            'parent_id' => null,
            'governance_type' => 'global',
            'canonical_name' => 'EarthCoop Global',
            'status' => 'active',
        ]);
        $asia = GovernanceArea::factory()->official()->create([
            'key' => 'earthcoop-continent-asia',
            'parent_id' => $global->id,
            'governance_type' => 'continent',
            'canonical_name' => 'Asia',
            'status' => 'active',
        ]);
        $countryArea = GovernanceArea::factory()->official()->create([
            'key' => 'ir-reference-v2-ir-1404-1',
            'parent_id' => $asia->id,
            'country_code' => 'IR',
            'governance_type' => 'country',
            'canonical_name' => 'ایران',
            'metadata' => ['reference_topology' => true, 'source' => 'earthcoop-reference-governance', 'dataset_version' => 'v2'],
            'status' => 'active',
        ]);
        $countryArea->locations()->attach($v2Path->first()->id);

        $user = User::factory()->create();
        app(ResidenceService::class)->setInitialPrimaryResidence($user, $v1Anchor, ['source' => 'test']);

        $response = $this->actingAs($user)->get(route('profile.edit'))->assertOk();
        $path = $response->viewData('residenceHydrationPath');

        $this->assertSame('governance:'.$asia->id, $path[0]);
        $this->assertSame('location:'.$v2Path->first()->id, $path[1]);
        $this->assertSame('location:'.$v2Anchor->id, $path[array_key_last($path)]);
        $this->assertNotContains('location:'.$v1Anchor->id, $path);
    }

    public function test_profile_reference_settlement_hydration_prefers_its_v2_parent_over_stale_primary_anchor(): void
    {
        config([
            'iran_settlement_catalog.enabled' => true,
            'iran_settlement_catalog.claims_enabled' => true,
        ]);

        $schema = LocationFixture::iranSchema();
        $staleAnchor = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'rural_district'],
            ['ایران قدیمی', 'مازندران قدیمی', 'ساری قدیمی', 'چهاردانگه قدیمی', 'دهستان قدیمی'],
        )->last();

        $v2Schema = LocationSchema::query()->create([
            'key' => 'ir-reference-v2',
            'country_code' => 'IR',
            'name' => 'Iran 1404',
            'version' => 'v2',
            'status' => 'active',
        ]);
        $types = $schema->types->keyBy('key');
        $parent = null;
        $v2Path = collect();
        foreach ([
            ['country', 'ایران'],
            ['province', 'مازندران'],
            ['county', 'ساری'],
            ['section', 'چهاردانگه'],
            ['rural_district', 'دهستان چهاردانگه'],
        ] as [$typeKey, $name]) {
            $parent = Location::factory()->create([
                'parent_id' => $parent?->id,
                'location_schema_id' => $v2Schema->id,
                'location_type_id' => $types[$typeKey]->id,
                'country_code' => 'IR',
                'name' => $name,
                'canonical_name' => $name,
                'localized_names' => ['fa' => $name],
                'level' => $typeKey,
                'status' => 'active',
            ]);
            $v2Path->push($parent);
        }
        $v2Anchor = $v2Path->last();
        LocationExternalId::query()->create([
            'location_id' => $v2Anchor->id,
            'source' => 'earthcoop-reference',
            'dataset_version' => 'v2',
            'external_id' => 'IR-1404-1938',
            'metadata' => ['fixture' => true],
        ]);

        $settlement = ReferenceSettlement::query()->create([
            'source' => 'IranCountryDivisions/geo_1404',
            'dataset_version' => 'v2',
            'external_id' => 'IR-1404-99011',
            'parent_external_id' => 'IR-1404-1938',
            'source_code' => '99011',
            'source_row_id' => 99011,
            'name_fa' => 'آبادی مسیر بازیابی',
            'search_name' => 'آبادی مسیر بازیابی',
            'classification' => 'unverified_settlement',
            'residential_eligibility' => 'unverified',
            'governance_authorized' => false,
            'operational_promotion_allowed' => false,
            'provenance' => ['source' => 'fixture'],
        ]);

        $user = User::factory()->create();
        $relationship = app(ResidenceService::class)->setInitialPrimaryResidence($user, $staleAnchor, ['source' => 'test']);
        $claim = ReferenceSettlementResidenceClaim::query()->create([
            'reference_settlement_id' => $settlement->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
        PendingResidenceIntent::query()->create([
            'user_id' => $user->id,
            'anchor_relationship_id' => $relationship->id,
            'reference_settlement_residence_claim_id' => $claim->id,
            'status' => 'pending',
            'selected_at' => now(),
            'metadata' => [],
        ]);

        $response = $this->actingAs($user)->get(route('profile.edit'))->assertOk();
        $path = $response->viewData('residenceHydrationPath');

        $this->assertSame('location:'.$v2Path->first()->id, $path[0]);
        $this->assertSame('location:'.$v2Anchor->id, $path[array_key_last($path)]);
        $this->assertNotContains('location:'.$staleAnchor->id, $path);
    }


    public function test_profile_can_select_reference_settlement_and_exposes_same_picker_as_registration(): void
    {
        config([
            'iran_settlement_catalog.enabled' => true,
            'iran_settlement_catalog.claims_enabled' => true,
        ]);

        $schema = LocationFixture::iranSchema();
        $anchor = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'rural_district',
        ])->last();
        LocationExternalId::query()->create([
            'location_id' => $anchor->id,
            'source' => 'earthcoop-reference',
            'dataset_version' => 'v2',
            'external_id' => 'IR-1404-5555',
            'metadata' => ['fixture' => true],
        ]);
        $settlement = ReferenceSettlement::query()->create([
            'source' => 'IranCountryDivisions/geo_1404',
            'dataset_version' => 'v2',
            'external_id' => 'IR-1404-99001',
            'parent_external_id' => 'IR-1404-5555',
            'source_code' => '99001',
            'source_row_id' => 99001,
            'name_fa' => 'آبادی پروفایل نمونه',
            'search_name' => 'آبادی پروفایل نمونه',
            'classification' => 'unverified_settlement',
            'residential_eligibility' => 'unverified',
            'governance_authorized' => false,
            'operational_promotion_allowed' => false,
            'provenance' => ['source' => 'fixture'],
        ]);
        $user = User::factory()->create();
        app(ResidenceService::class)->setInitialPrimaryResidence($user, $anchor, ['source' => 'test']);

        $this->actingAs($user)->put(route('profile.update.address'), [
            'reference_settlement_external_id' => $settlement->external_id,
        ])->assertSessionHasNoErrors();

        $intent = $user->fresh()->pendingResidenceIntents()->where('status', 'pending')->sole();
        $this->assertNotNull($intent->reference_settlement_residence_claim_id);
        $this->assertNull($intent->location_proposal_id);
        $this->assertSame($anchor->id, $user->fresh()->locationRelationships()->whereNull('ended_at')->sole()->location_id);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('data-reference-settlement-picker', false)
            ->assertSee('name="reference_settlement_external_id"', false)
            ->assertSee('data-reference-settlement-current-external-id="'.$settlement->external_id.'"', false)
            ->assertSee('آبادی پروفایل نمونه');
    }


    public function test_profile_reference_settlement_upgrade_from_verified_v1_anchor_is_not_counted_as_transfer(): void
    {
        config([
            'iran_settlement_catalog.enabled' => true,
            'iran_settlement_catalog.claims_enabled' => true,
        ]);

        $schema = LocationFixture::iranSchema();
        $v1Anchor = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'rural_district',
        ])->last();
        LocationExternalId::query()->create([
            'location_id' => $v1Anchor->id,
            'source' => 'earthcoop-reference',
            'dataset_version' => 'v1',
            'external_id' => 'IR-MAZ-SARI-CHAHARDANGEH-RD',
            'metadata' => ['fixture' => true],
        ]);

        $v2Schema = LocationSchema::query()->create([
            'key' => 'ir-reference-v2',
            'country_code' => 'IR',
            'name' => 'Iran 1404',
            'version' => 'v2',
            'status' => 'active',
        ]);
        $ruralType = $schema->types->firstWhere('key', 'rural_district');
        $v2Anchor = Location::factory()->create([
            'location_schema_id' => $v2Schema->id,
            'location_type_id' => $ruralType->id,
            'country_code' => 'IR',
            'name' => 'دهستان چهاردانگه',
            'canonical_name' => 'دهستان چهاردانگه',
            'localized_names' => ['fa' => 'دهستان چهاردانگه'],
            'level' => 'rural_district',
            'status' => 'active',
        ]);
        LocationExternalId::query()->create([
            'location_id' => $v2Anchor->id,
            'source' => 'earthcoop-reference',
            'dataset_version' => 'v2',
            'external_id' => 'IR-1404-1938',
            'metadata' => ['fixture' => true],
        ]);

        $settlement = ReferenceSettlement::query()->create([
            'source' => 'IranCountryDivisions/geo_1404',
            'dataset_version' => 'v2',
            'external_id' => 'IR-1404-99002',
            'parent_external_id' => 'IR-1404-1938',
            'source_code' => '99002',
            'source_row_id' => 99002,
            'name_fa' => 'آبادی ارتقای نسخه',
            'search_name' => 'آبادی ارتقای نسخه',
            'classification' => 'unverified_settlement',
            'residential_eligibility' => 'unverified',
            'governance_authorized' => false,
            'operational_promotion_allowed' => false,
            'provenance' => ['source' => 'fixture'],
        ]);

        $user = User::factory()->create();
        app(ResidenceService::class)->setInitialPrimaryResidence($user, $v1Anchor, ['source' => 'test']);

        $this->actingAs($user)->put(route('profile.update.address'), [
            'reference_settlement_external_id' => $settlement->external_id,
        ])->assertSessionHasNoErrors();

        $current = $user->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();

        $this->assertSame($v2Anchor->id, $current->location_id);
        $this->assertFalse((bool) $current->explicit_transfer);
        $this->assertSame('reference_dataset_identity_upgrade', $current->change_reason);
        $this->assertSame(0, $user->fresh()->locationRelationships()->where('explicit_transfer', true)->count());
        $this->assertSame(
            $settlement->external_id,
            $user->fresh()->pendingResidenceIntents()->where('status', 'pending')->sole()->metadata['reference_settlement_external_id']
        );
    }

    private function makeCurrentAnchorScenario(): array
    {
        $schema = LocationFixture::iranSchema();
        $anchor = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $user = User::factory()->create();
        app(ResidenceService::class)->setInitialPrimaryResidence($user, $anchor, ['source' => 'test']);
        $proposal = app(LocationProposalService::class)->propose($user, $anchor, $streetType, [
            'canonical_name' => 'خیابان دقیق پیشنهادی',
        ]);

        $this->assertNotInstanceOf(Location::class, $proposal);

        return [$user, $anchor, $proposal];
    }
}
