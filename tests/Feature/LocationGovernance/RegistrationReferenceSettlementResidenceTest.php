<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\LocationExternalId;
use App\Models\PendingResidenceIntent;
use App\Models\ReferenceSettlement;
use App\Models\ReferenceSettlementResidenceClaim;
use App\Models\MembershipDimension;
use App\Services\LocationGovernance\LocationStructureClaimService;
use App\Services\Membership\PublicDimensionResolver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

final class RegistrationReferenceSettlementResidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config()->set('location-governance.registration_enabled', true);
        config()->set('location-governance.runtime_enabled', true);
        config()->set('iran_settlement_catalog.enabled', true);
        config()->set('iran_settlement_catalog.claims_enabled', true);
    }

    private function anchor(string $externalId = 'IR-MAZ-SARI-CHAHARDANGEH-RD')
    {
        $schema = LocationFixture::iranSchema();
        $location = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'rural_district',
        ])->last();

        LocationExternalId::query()->create([
            'location_id' => $location->id,
            'source' => 'earthcoop-reference',
            'dataset_version' => 'v1',
            'external_id' => $externalId,
            'metadata' => ['fixture' => true],
        ]);

        return $location;
    }

    private function settlement(string $parentExternalId = 'IR-1404-1938'): ReferenceSettlement
    {
        return ReferenceSettlement::query()->create([
            'source' => 'IranCountryDivisions/geo_1404',
            'dataset_version' => 'v2',
            'external_id' => 'IR-1404-88001',
            'parent_external_id' => $parentExternalId,
            'source_code' => '88001',
            'source_row_id' => 88001,
            'name_fa' => 'آبادی ثبت‌نام نمونه',
            'search_name' => 'آبادی ثبت‌نام نمونه',
            'classification' => 'unverified_settlement',
            'residential_eligibility' => 'unverified',
            'governance_authorized' => false,
            'operational_promotion_allowed' => false,
            'provenance' => ['source' => 'fixture'],
        ]);
    }

    private function noNeighborhoodClaim(ReferenceSettlement $settlement, User $user)
    {
        return app(LocationStructureClaimService::class)
            ->findOrCreateOpenReferenceSettlementClaim($settlement, 'no_neighborhood', $user);
    }

    public function test_step3_settlement_picker_is_visible_only_when_both_feature_flags_are_enabled(): void
    {
        $user = User::factory()->create();

        $enabled = $this->actingAs($user)->get(route('register.step3'));
        $enabled->assertOk()
            ->assertSee('data-reference-settlement-picker', false)
            ->assertSee('name="reference_settlement_external_id"', false)
            ->assertSee('data-reference-settlement-search', false);

        config()->set('iran_settlement_catalog.claims_enabled', false);
        $disabled = $this->actingAs($user)->get(route('register.step3'));
        $disabled->assertOk()
            ->assertDontSee('data-reference-settlement-picker', false)
            ->assertSee('name="reference_settlement_external_id"', false);
    }

    public function test_reference_settlement_can_complete_registration_only_via_verified_parent_crosswalk(): void
    {
        $anchor = $this->anchor();
        $settlement = $this->settlement();
        $user = User::factory()->create();
        config()->set('location-governance.groups_enabled', true);
        MembershipDimension::query()->create([
            'key' => 'public',
            'name' => 'Public',
            'resolver_class' => PublicDimensionResolver::class,
            'enabled' => true,
        ]);
        $locationsBefore = DB::table('locations')->count();

        $structuralClaim = $this->noNeighborhoodClaim($settlement, $user);
        $response = $this->actingAs($user)->post(route('register.step3.process'), [
            'reference_settlement_external_id' => $settlement->external_id,
            'location_structure_claim_ids' => [$structuralClaim->id],
        ]);

        $response->assertRedirect(route('home'));
        $relationship = $user->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();

        $this->assertSame($anchor->id, $relationship->location_id);
        $this->assertSame($locationsBefore, DB::table('locations')->count());
        $claim = ReferenceSettlementResidenceClaim::query()
            ->where('reference_settlement_id', $settlement->id)
            ->where('user_id', $user->id)
            ->sole();
        $this->assertSame('pending', $claim->status);

        $intent = PendingResidenceIntent::query()->where('user_id', $user->id)->sole();
        $this->assertNull($intent->location_proposal_id);
        $this->assertSame($claim->id, $intent->reference_settlement_residence_claim_id);
        $this->assertSame($settlement->external_id, $intent->metadata['reference_settlement_external_id']);

        $pendingGroupRequest = DB::table('location_scoped_group_requests')
            ->where('requester_user_id', $user->id)
            ->where('reference_settlement_residence_claim_id', $claim->id)
            ->where('dimension_key', 'public')
            ->sole();
        $this->assertSame('pending_location', $pendingGroupRequest->status);
        $this->assertNull($pendingGroupRequest->group_id);
        $this->assertNull($pendingGroupRequest->governance_area_id);
        $this->assertNull($pendingGroupRequest->location_id);
        $this->assertSame(0, DB::table('governance_areas')->count());

        $this->actingAs($user)->get(route('location-governance.me'))
            ->assertOk()
            ->assertSee('data-pending-reference-settlement', false)
            ->assertSee('آبادی ثبت‌نام نمونه')
            ->assertSee('حوزهٔ رسمی و حق رأی این آبادی تا تصمیم مستقل حکمرانی ایجاد نمی‌شود.');

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('data-pending-reference-settlement-state', false)
            ->assertSee('آبادی ثبت‌نام نمونه');

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/location-governance')
            ->assertOk()
            ->assertViewHas('healthDiagnostics', fn (array $diagnostics): bool =>
                ($diagnostics['invalid_pending_residence_intents'] ?? null) === 0
                && ($diagnostics['pending_residence_intents'] ?? null) === 1
            );
    }

    public function test_reference_settlement_alone_cannot_finish_step3_without_neighborhood_decision(): void
    {
        $this->anchor();
        $settlement = $this->settlement();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('register.step3'))
            ->post(route('register.step3.process'), [
                'reference_settlement_external_id' => $settlement->external_id,
            ])
            ->assertRedirect(route('register.step3'))
            ->assertSessionHasErrors('reference_settlement_external_id');

        $this->assertSame(0, $user->fresh()->locationRelationships()->count());
        $this->assertDatabaseCount('pending_residence_intents', 0);
    }

    public function test_reference_settlement_can_anchor_directly_to_imported_v2_parent_without_crosswalk(): void
    {
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
        $settlement = $this->settlement('IR-1404-5555');
        $user = User::factory()->create();

        $structuralClaim = $this->noNeighborhoodClaim($settlement, $user);
        $this->actingAs($user)->post(route('register.step3.process'), [
            'reference_settlement_external_id' => $settlement->external_id,
            'location_structure_claim_ids' => [$structuralClaim->id],
        ])->assertRedirect(route('home'));

        $relationship = $user->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();

        $this->assertSame($anchor->id, $relationship->location_id);
        $this->assertDatabaseHas('reference_settlement_residence_claims', [
            'reference_settlement_id' => $settlement->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    public function test_pending_settlement_base_has_no_canonical_active_base_area(): void
    {
        $this->anchor();
        $settlement = $this->settlement();
        $user = User::factory()->create();
        config()->set('location-governance.groups_enabled', true);

        MembershipDimension::query()->create([
            'key' => 'public',
            'name' => 'Public',
            'resolver_class' => PublicDimensionResolver::class,
            'enabled' => true,
        ]);

        $structuralClaim = $this->noNeighborhoodClaim($settlement, $user);
        $this->actingAs($user)->post(route('register.step3.process'), [
            'reference_settlement_external_id' => $settlement->external_id,
            'location_structure_claim_ids' => [$structuralClaim->id],
        ])->assertRedirect(route('home'));

        $reconciler = app(\App\Services\Groups\CanonicalGroupMembershipReconciler::class);
        $method = new \ReflectionMethod($reconciler, 'baseGovernanceAreaId');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($reconciler, $user->fresh()));

        $claim = ReferenceSettlementResidenceClaim::query()
            ->where('reference_settlement_id', $settlement->id)
            ->where('user_id', $user->id)
            ->sole();

        $this->assertDatabaseHas('location_scoped_group_requests', [
            'requester_user_id' => $user->id,
            'reference_settlement_residence_claim_id' => $claim->id,
            'dimension_key' => 'public',
            'status' => 'pending_location',
        ]);
    }

    public function test_switching_pending_exact_residence_source_does_not_leave_ghost_group_shells(): void
    {
        $anchorLocation = $this->anchor();
        $settlement = $this->settlement();
        $user = User::factory()->create();
        config()->set('location-governance.groups_enabled', true);

        MembershipDimension::query()->create([
            'key' => 'public',
            'name' => 'Public',
            'resolver_class' => PublicDimensionResolver::class,
            'enabled' => true,
        ]);

        $schema = \App\Models\LocationSchema::query()->findOrFail($anchorLocation->location_schema_id);
        $villageType = $schema->types()->where('key', 'village')->firstOrFail();
        $proposal = app(\App\Services\LocationGovernance\LocationProposalService::class)
            ->propose($user, $anchorLocation, $villageType, ['canonical_name' => 'روستای پیشنهادی تعویض']);

        $proposalShell = \App\Models\LocationScopedGroupRequest::query()->create([
            'requester_user_id' => $user->id,
            'location_proposal_id' => $proposal->id,
            'scope_kind' => 'official_system',
            'dimension_key' => 'public',
            'dimension_value_key' => 'all',
            'status' => 'pending_location',
            'metadata' => ['source' => 'old-proposal'],
        ]);

        $claim = ReferenceSettlementResidenceClaim::query()->create([
            'reference_settlement_id' => $settlement->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $service = app(\App\Services\Groups\PendingLocationGroupRequestService::class);
        $service->syncForReferenceSettlementClaim($user, $claim);

        $this->assertSame('cancelled', $proposalShell->fresh()->status);
        $referenceShell = \App\Models\LocationScopedGroupRequest::query()
            ->where('requester_user_id', $user->id)
            ->where('reference_settlement_residence_claim_id', $claim->id)
            ->where('status', 'pending_location')
            ->firstOrFail();

        $service->syncForPendingResidence($user, $proposal);

        $this->assertSame('cancelled', $referenceShell->fresh()->status);
        $this->assertDatabaseHas('location_scoped_group_requests', [
            'requester_user_id' => $user->id,
            'location_proposal_id' => $proposal->id,
            'dimension_key' => 'public',
            'status' => 'pending_location',
        ]);
    }

    public function test_unmapped_settlement_parent_fails_closed_without_partial_residence_or_claim(): void
    {
        $this->anchor();
        $settlement = $this->settlement('IR-1404-999999');
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('register.step3'))
            ->post(route('register.step3.process'), [
                'reference_settlement_external_id' => $settlement->external_id,
            ]);

        $response->assertRedirect(route('register.step3'))
            ->assertSessionHasErrors('reference_settlement_external_id');

        $this->assertSame(0, $user->fresh()->locationRelationships()->count());
        $this->assertDatabaseCount('reference_settlement_residence_claims', 0);
        $this->assertDatabaseCount('pending_residence_intents', 0);
    }

    public function test_reference_settlement_selection_is_mutually_exclusive_with_location_or_proposal(): void
    {
        $anchor = $this->anchor();
        $settlement = $this->settlement();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('register.step3'))
            ->post(route('register.step3.process'), [
                'location_id' => $anchor->id,
                'reference_settlement_external_id' => $settlement->external_id,
            ])
            ->assertRedirect(route('register.step3'))
            ->assertSessionHasErrors('location_id');

        $this->assertSame(0, $user->fresh()->locationRelationships()->count());
        $this->assertDatabaseCount('reference_settlement_residence_claims', 0);
    }

    public function test_feature_flags_block_reference_settlement_registration(): void
    {
        $this->anchor();
        $settlement = $this->settlement();
        $user = User::factory()->create();
        config()->set('iran_settlement_catalog.claims_enabled', false);

        $this->actingAs($user)
            ->from(route('register.step3'))
            ->post(route('register.step3.process'), [
                'reference_settlement_external_id' => $settlement->external_id,
            ])
            ->assertRedirect(route('register.step3'))
            ->assertSessionHasErrors('reference_settlement_external_id');

        $this->assertDatabaseCount('reference_settlement_residence_claims', 0);
        $this->assertSame(0, $user->fresh()->locationRelationships()->count());
    }
}
