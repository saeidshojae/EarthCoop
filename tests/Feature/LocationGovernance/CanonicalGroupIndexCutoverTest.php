<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\Group;
use App\Models\Location;
use App\Models\LocationStructureClaim;
use App\Models\User;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use App\Services\Groups\PendingLocationGroupRequestService;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\LocationStructureClaimService;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\MembershipFixture;
use Tests\TestCase;

class CanonicalGroupIndexCutoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_groups_index_resolves_canonical_memberships_when_groups_cutover_is_enabled(): void
    {
        $this->enableStageC();

        ['user' => $user, 'area' => $area] = MembershipFixture::canonicalUser();

        $legacyGroup = Group::query()->create([
            'name' => 'Legacy spatial public group',
            'group_type' => '0',
            'location_level' => 'city',
            'is_open' => 1,
        ]);
        $user->groups()->attach($legacyGroup->id, [
            'role' => 1,
            'status' => 1,
        ]);

        $response = $this->actingAs($user)->get('/groups');

        $response->assertOk();
        $response->assertViewHas('generalGroups', function ($groups) use ($area, $legacyGroup): bool {
            $groups = collect($groups);

            $hasCanonicalPublic = $groups->contains(fn (Group $group): bool =>
                (int) $group->governance_area_id === (int) $area->id
                && $group->dimension_key === 'public'
                && $group->dimension_value_key === 'public'
            );

            $hasLegacySpatial = $groups->contains(fn (Group $group): bool => $group->is($legacyGroup));

            return $hasCanonicalPublic && ! $hasLegacySpatial;
        });
    }

    public function test_my_groups_same_response_includes_canonical_region_healed_from_pre_deploy_ready_shell(): void
    {
        $this->enableStageC();

        ['user' => $user, 'area' => $cityArea, 'endpoint' => $city] = MembershipFixture::canonicalUser();
        $schema = $city->schema()->firstOrFail();
        $regionType = $schema->types()->where('key', 'urban_region')->firstOrFail();
        $neighborhoodType = $schema->types()->where('key', 'neighborhood')->firstOrFail();

        $proposals = app(LocationProposalService::class);
        $region = $proposals->propose($user, $city, $regionType, ['canonical_name' => '۵ ساری']);
        $neighborhood = $proposals->proposeUnderProposal($user, $region, $neighborhoodType, ['canonical_name' => 'آزمایشی ۲']);
        app(ResidenceService::class)->setPendingResidenceIntent($user, $neighborhood, ['source' => 'uat-regression']);

        $pending = app(PendingLocationGroupRequestService::class);
        $pending->syncForPendingResidence($user, $neighborhood);
        $resolvedRegion = $proposals->approve($region, User::factory()->create(), 'تأیید منطقه');

        $regionArea = $resolvedRegion->governanceAreas()->official()->active()->sole();
        $regionArea->locations()->detach($resolvedRegion->id);
        $regionArea->delete();

        foreach ($user->locationScopedGroupRequests()->where('location_id', $resolvedRegion->id)->get() as $request) {
            $request->forceFill([
                'status' => 'ready_to_materialize',
                'group_id' => null,
                'governance_area_id' => null,
            ])->save();
        }

        $response = $this->actingAs($user)->get('/groups');

        $response->assertOk();
        $healedArea = $resolvedRegion->fresh()->governanceAreas()->official()->active()->sole();
        $this->assertSame($cityArea->id, $healedArea->parent_id);

        $response->assertViewHas('generalGroups', function ($groups) use ($healedArea, $neighborhood): bool {
            $groups = collect($groups);

            $canonicalRegion = $groups->first(fn (Group $group): bool =>
                (int) $group->governance_area_id === (int) $healedArea->id
                && $group->dimension_key === 'public'
                && ! (bool) $group->getAttribute('pending_location')
            );

            $pendingNeighborhood = $groups->first(fn (Group $group): bool =>
                (bool) $group->getAttribute('pending_location')
                && str_contains((string) $group->name, $neighborhood->canonical_name)
            );

            return $canonicalRegion !== null
                && (int) $canonicalRegion->pivot->role === 0
                && $pendingNeighborhood !== null
                && (int) $pendingNeighborhood->pivot->role === 1;
        });
    }

    public function test_approved_pending_region_waits_for_its_no_neighborhood_claim_before_pending_base_materializes(): void
    {
        $this->enableStageC();

        ['user' => $user, 'area' => $cityArea, 'endpoint' => $city] = MembershipFixture::canonicalUser();
        $schema = $city->schema()->firstOrFail();
        $regionType = $schema->types()->where('key', 'urban_region')->firstOrFail();

        $proposal = app(LocationProposalService::class)->propose(
            $user,
            $city,
            $regionType,
            ['canonical_name' => 'منطقه پیشنهادی بدون محله'],
        );
        $claim = LocationStructureClaim::query()->create([
            'location_id' => null,
            'location_proposal_id' => $proposal->id,
            'claim_type' => 'no_neighborhood',
            'status' => 'pending',
            'proposer_user_id' => $user->id,
            'audit_log' => [],
        ]);

        app(ResidenceService::class)->setPendingResidenceIntent(
            $user,
            $proposal,
            ['source' => 'pending_region_without_neighborhood'],
            [$claim],
        );
        $pending = app(PendingLocationGroupRequestService::class);
        $pending->syncForPendingResidence($user, $proposal);

        $publicRequest = $user->locationScopedGroupRequests()
            ->where('location_proposal_id', $proposal->id)
            ->where('dimension_key', 'public')
            ->where('dimension_value_key', 'public')
            ->sole();
        $this->assertTrue((bool) data_get($publicRequest->metadata, 'is_pending_base'));

        $resolved = app(LocationProposalService::class)->approve(
            $proposal,
            User::factory()->create(),
            'تأیید مکان منطقه',
        );
        $claim->refresh();
        $publicRequest->refresh();

        $this->assertSame($resolved->id, $claim->location_id);
        $this->assertNull($claim->location_proposal_id);
        $this->assertSame($claim->id, $publicRequest->location_structure_claim_id);
        $this->assertNull($publicRequest->location_proposal_id);
        $this->assertSame('pending_location', $publicRequest->status);

        $regionArea = $resolved->governanceAreas()->official()->active()->sole();
        $this->assertSame($cityArea->id, $regionArea->parent_id);
        app(CanonicalGroupMembershipReconciler::class)->reconcile($user);

        $regionPublic = Group::query()
            ->where('governance_area_id', $regionArea->id)
            ->where('dimension_key', 'public')
            ->where('dimension_value_key', 'public')
            ->firstOrFail();
        $this->assertSame(0, (int) $user->groups()->whereKey($regionPublic->id)->firstOrFail()->pivot->role);

        app(LocationStructureClaimService::class)->approve(
            $claim,
            User::factory()->create(),
            'تأیید ساختار بدون محله',
        );

        $publicRequest->refresh();
        $this->assertSame('materialized', $publicRequest->status);
        $this->assertSame($regionArea->id, $publicRequest->governance_area_id);
        $this->assertSame($regionPublic->id, $publicRequest->group_id);
        $this->assertSame(1, (int) $user->groups()->whereKey($regionPublic->id)->firstOrFail()->pivot->role);
    }

    public function test_rejected_structural_dependency_cancels_pending_base_when_location_itself_is_later_approved(): void
    {
        $this->enableStageC();

        ['user' => $user, 'endpoint' => $city] = MembershipFixture::canonicalUser();
        $schema = $city->schema()->firstOrFail();
        $regionType = $schema->types()->where('key', 'urban_region')->firstOrFail();

        $proposal = app(LocationProposalService::class)->propose(
            $user,
            $city,
            $regionType,
            ['canonical_name' => 'منطقه با ادعای ساختاری مردود'],
        );
        $claim = LocationStructureClaim::query()->create([
            'location_id' => null,
            'location_proposal_id' => $proposal->id,
            'claim_type' => 'no_neighborhood',
            'status' => 'pending',
            'proposer_user_id' => $user->id,
            'audit_log' => [],
        ]);

        $intent = app(ResidenceService::class)->setPendingResidenceIntent(
            $user,
            $proposal,
            ['source' => 'rejected_structural_dependency'],
            [$claim],
        );
        app(PendingLocationGroupRequestService::class)->syncForPendingResidence($user, $proposal);

        app(LocationStructureClaimService::class)->reject(
            $claim,
            User::factory()->create(),
            'این منطقه در واقع محله دارد',
        );
        $resolved = app(LocationProposalService::class)->approve(
            $proposal,
            User::factory()->create(),
            'خود منطقه معتبر است',
        );

        $this->assertSame('cancelled', $intent->fresh()->status);
        $this->assertSame('structural_claim_rejected', data_get($intent->fresh()->metadata, 'cancellation_reason'));
        $this->assertSame($city->id, $user->fresh()->locationRelationships()->whereNull('ended_at')->sole()->location_id);
        $this->assertNotSame($resolved->id, $city->id);

        $request = $user->locationScopedGroupRequests()
            ->where('location_structure_claim_id', $claim->id)
            ->where('dimension_key', 'public')
            ->where('dimension_value_key', 'public')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('rejected', $request->status);
    }

    public function test_residence_transfer_atomically_deactivates_stale_canonical_memberships_and_activates_current_scope(): void
    {
        $this->enableStageC();

        ['user' => $user, 'area' => $oldArea, 'endpoint' => $oldEndpoint] = MembershipFixture::canonicalUser();

        $this->actingAs($user)->get('/groups')->assertOk();

        $oldPublicGroup = Group::query()
            ->where('governance_area_id', $oldArea->id)
            ->where('dimension_key', 'public')
            ->where('dimension_value_key', 'public')
            ->firstOrFail();

        $this->assertSame(1, (int) $user->groups()->whereKey($oldPublicGroup->id)->firstOrFail()->pivot->status);

        $newEndpoint = Location::factory()->create([
            'parent_id' => $oldEndpoint->parent_id,
            'location_schema_id' => $oldEndpoint->location_schema_id,
            'location_type_id' => $oldEndpoint->location_type_id,
            'country_code' => 'IR',
            'name' => 'ساری جدید',
            'canonical_name' => 'New Sari',
            'level' => $oldEndpoint->level,
            'status' => 'active',
        ]);

        $newArea = GovernanceArea::create([
            'key' => 'ir.new-sari',
            'country_code' => 'IR',
            'governance_type' => 'city',
            'area_kind' => 'official',
            'canonical_name' => 'New Sari',
            'rank' => 10,
            'status' => 'active',
        ]);
        $newArea->locations()->attach($newEndpoint->id);

        app(ResidenceService::class)->transferPrimaryResidence(
            $user,
            $newEndpoint,
            $user,
            'stage_c_reconciliation_test',
        );

        $newPublicGroup = Group::query()
            ->where('governance_area_id', $newArea->id)
            ->where('dimension_key', 'public')
            ->where('dimension_value_key', 'public')
            ->firstOrFail();

        $this->assertSame(0, (int) $user->groups()->whereKey($oldPublicGroup->id)->firstOrFail()->pivot->status);
        $this->assertSame(1, (int) $user->groups()->whereKey($newPublicGroup->id)->firstOrFail()->pivot->status);
    }

    public function test_base_scope_is_active_and_all_upstream_canonical_memberships_are_observer_roles(): void
    {
        $this->enableStageC();

        ['user' => $user, 'area' => $baseArea] = MembershipFixture::canonicalUser();

        $country = GovernanceArea::create([
            'key' => 'ir.country',
            'country_code' => 'IR',
            'governance_type' => 'country',
            'area_kind' => 'official',
            'canonical_name' => 'Iran',
            'rank' => 1,
            'status' => 'active',
        ]);
        $baseArea->update(['parent_id' => $country->id, 'rank' => 10]);

        $this->actingAs($user)->get('/groups')->assertOk();

        foreach (['public', 'profession', 'specialty', 'age', 'gender'] as $dimensionKey) {
            $baseGroup = Group::query()
                ->where('governance_area_id', $baseArea->id)
                ->where('dimension_key', $dimensionKey)
                ->firstOrFail();
            $upstreamGroup = Group::query()
                ->where('governance_area_id', $country->id)
                ->where('dimension_key', $dimensionKey)
                ->firstOrFail();

            $basePivot = $user->groups()->whereKey($baseGroup->id)->firstOrFail()->pivot;
            $upstreamPivot = $user->groups()->whereKey($upstreamGroup->id)->firstOrFail()->pivot;

            $this->assertSame(1, (int) $basePivot->role, "Base {$dimensionKey} group must be active.");
            $this->assertSame(0, (int) $upstreamPivot->role, "Upstream {$dimensionKey} group must be observer.");
        }
    }

    public function test_active_privileged_upstream_role_is_not_overwritten_by_membership_reconciliation(): void
    {
        $this->enableStageC();

        ['user' => $user, 'area' => $baseArea] = MembershipFixture::canonicalUser();

        $country = GovernanceArea::create([
            'key' => 'ir.country.privileged',
            'country_code' => 'IR',
            'governance_type' => 'country',
            'area_kind' => 'official',
            'canonical_name' => 'Iran',
            'rank' => 1,
            'status' => 'active',
        ]);
        $baseArea->update(['parent_id' => $country->id, 'rank' => 10]);

        $this->actingAs($user)->get('/groups')->assertOk();

        $countryPublic = Group::query()
            ->where('governance_area_id', $country->id)
            ->where('dimension_key', 'public')
            ->where('dimension_value_key', 'public')
            ->firstOrFail();

        $user->groups()->updateExistingPivot($countryPublic->id, [
            'status' => 1,
            'role' => 3,
        ]);

        $this->actingAs($user)->get('/groups')->assertOk();

        $this->assertSame(
            3,
            (int) $user->groups()->whereKey($countryPublic->id)->firstOrFail()->pivot->role,
        );
    }

    public function test_canonical_profession_filters_use_governance_levels_instead_of_legacy_location_level(): void
    {
        $this->enableStageC();

        ['user' => $user, 'area' => $baseArea] = MembershipFixture::canonicalUser();

        $country = GovernanceArea::create([
            'key' => 'ir.country.filter',
            'country_code' => 'IR',
            'governance_type' => 'country',
            'area_kind' => 'official',
            'canonical_name' => 'Iran',
            'rank' => 1,
            'status' => 'active',
        ]);
        $baseArea->update([
            'parent_id' => $country->id,
            'governance_type' => 'city',
            'rank' => 10,
        ]);

        $response = $this->actingAs($user)->get('/groups');

        $response->assertOk();
        $response->assertSee('data-filter-value="country"', false);
        $response->assertSee('data-filter-value="city"', false);
    }

    private function enableStageC(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
            'location-governance.groups_enabled' => true,
        ]);
    }
}
