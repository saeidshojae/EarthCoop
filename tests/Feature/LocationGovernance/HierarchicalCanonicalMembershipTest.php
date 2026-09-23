<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\ExperienceField;
use App\Models\GovernanceArea;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Location;
use App\Models\OccupationalField;
use App\Models\User;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use App\Services\Groups\PendingLocationGroupRequestService;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\LocationStructureClaimService;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\MembershipFixture;
use Tests\TestCase;

class HierarchicalCanonicalMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_leaf_profession_and_specialty_expand_to_parent_and_grandparent_values(): void
    {
        config(['location-governance.groups_enabled' => true]);

        ['user' => $user] = MembershipFixture::canonicalUser();

        $professionRoot = OccupationalField::create(['name' => 'فرهنگیان', 'status' => 1]);
        $professionMiddle = OccupationalField::create(['name' => 'معلمان ابتدایی', 'parent_id' => $professionRoot->id, 'status' => 1]);
        $professionLeaf = OccupationalField::create(['name' => 'معلمان پایه اول', 'parent_id' => $professionMiddle->id, 'status' => 1]);

        $specialtyRoot = ExperienceField::create(['name' => 'آموزش', 'status' => 1]);
        $specialtyMiddle = ExperienceField::create(['name' => 'آموزش ابتدایی', 'parent_id' => $specialtyRoot->id, 'status' => 1]);
        $specialtyLeaf = ExperienceField::create(['name' => 'آموزش پایه اول', 'parent_id' => $specialtyMiddle->id, 'status' => 1]);

        $user->occupationalFields()->sync([$professionLeaf->id]);
        $user->experienceFields()->sync([$specialtyLeaf->id]);

        $professionValues = app(\App\Services\Membership\ProfessionDimensionResolver::class)
            ->valuesFor($user)
            ->values()
            ->all();
        $specialtyValues = app(\App\Services\Membership\SpecialtyDimensionResolver::class)
            ->valuesFor($user)
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing([
            'occupational_field:'.$professionRoot->id,
            'occupational_field:'.$professionMiddle->id,
            'occupational_field:'.$professionLeaf->id,
        ], $professionValues);

        $this->assertEqualsCanonicalizing([
            'experience_field:'.$specialtyRoot->id,
            'experience_field:'.$specialtyMiddle->id,
            'experience_field:'.$specialtyLeaf->id,
        ], $specialtyValues);
    }

    public function test_shared_profession_ancestors_are_deduplicated_across_multiple_leaf_selections(): void
    {
        ['user' => $user] = MembershipFixture::canonicalUser();

        $root = OccupationalField::create(['name' => 'فرهنگیان', 'status' => 1]);
        $middle = OccupationalField::create(['name' => 'معلمان ابتدایی', 'parent_id' => $root->id, 'status' => 1]);
        $first = OccupationalField::create(['name' => 'معلمان پایه اول', 'parent_id' => $middle->id, 'status' => 1]);
        $second = OccupationalField::create(['name' => 'معلمان پایه دوم', 'parent_id' => $middle->id, 'status' => 1]);

        $user->occupationalFields()->sync([$first->id, $second->id]);

        $values = app(\App\Services\Membership\ProfessionDimensionResolver::class)
            ->valuesFor($user)
            ->values()
            ->all();

        $this->assertCount(4, $values);
        $this->assertEqualsCanonicalizing([
            'occupational_field:'.$root->id,
            'occupational_field:'.$middle->id,
            'occupational_field:'.$first->id,
            'occupational_field:'.$second->id,
        ], $values);
    }


    public function test_official_reconciliation_never_deactivates_opted_in_community_membership(): void
    {
        config(['location-governance.groups_enabled' => true]);

        ['user' => $user] = MembershipFixture::canonicalUser();

        $community = GovernanceArea::create([
            'key' => 'community-reconciler-isolation',
            'country_code' => 'IR',
            'governance_type' => 'community',
            'area_kind' => 'community',
            'canonical_name' => 'Community reconciler isolation',
            'rank' => 1000,
            'status' => 'active',
        ]);
        $group = Group::create([
            'governance_area_id' => $community->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
            'name' => 'Local community',
            'group_type' => '0',
            'is_open' => 1,
        ]);
        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 1,
            'status' => 1,
        ]);

        app(CanonicalGroupMembershipReconciler::class)->reconcile($user);

        $membership = GroupUser::query()
            ->where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->sole();

        $this->assertSame(1, (int) $membership->status);
        $this->assertSame(1, (int) $membership->role);
    }

    public function test_minimum_three_level_profession_and_specialty_contract_materializes_81_memberships_on_nine_governance_scopes(): void
    {
        config(['location-governance.groups_enabled' => true]);

        ['user' => $user, 'area' => $baseArea] = MembershipFixture::canonicalUser();

        $professionRoot = OccupationalField::create(['name' => 'فرهنگیان', 'status' => 1]);
        $professionMiddle = OccupationalField::create(['name' => 'معلمان ابتدایی', 'parent_id' => $professionRoot->id, 'status' => 1]);
        $professionLeaf = OccupationalField::create(['name' => 'معلمان پایه اول', 'parent_id' => $professionMiddle->id, 'status' => 1]);
        $user->occupationalFields()->sync([$professionLeaf->id]);

        $specialtyRoot = ExperienceField::create(['name' => 'آموزش', 'status' => 1]);
        $specialtyMiddle = ExperienceField::create(['name' => 'آموزش ابتدایی', 'parent_id' => $specialtyRoot->id, 'status' => 1]);
        $specialtyLeaf = ExperienceField::create(['name' => 'آموزش پایه اول', 'parent_id' => $specialtyMiddle->id, 'status' => 1]);
        $user->experienceFields()->sync([$specialtyLeaf->id]);

        $parent = null;
        foreach ([
            ['global', null, 'Global', 100],
            ['continent', null, 'Asia', 200],
            ['country', 'IR', 'Iran', 300],
            ['province', 'IR', 'Mazandaran', 400],
            ['county', 'IR', 'Sari County', 500],
            ['section', 'IR', 'Central Sari Section', 600],
            ['city', 'IR', 'Sari', 700],
            ['urban_region', 'IR', 'Sari Urban Region 1', 800],
        ] as [$type, $countryCode, $name, $rank]) {
            $area = GovernanceArea::create([
                'parent_id' => $parent?->id,
                'key' => 'test.'.str_replace(' ', '-', strtolower($name)),
                'country_code' => $countryCode,
                'governance_type' => $type,
                'area_kind' => 'official',
                'canonical_name' => $name,
                'rank' => $rank,
                'status' => 'active',
            ]);
            $parent = $area;
        }

        // Rewire the fixture's mapped base area beneath the eight upstream areas.
        $baseArea->update([
            'parent_id' => $parent->id,
            'governance_type' => 'local',
            'rank' => 900,
        ]);

        app(CanonicalGroupMembershipReconciler::class)->reconcile($user);

        $canonicalMemberships = GroupUser::query()
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->whereHas('group', fn ($query) => $query->whereNotNull('governance_area_id'))
            ->get();

        $this->assertCount(81, $canonicalMemberships);
        $this->assertSame(9, $canonicalMemberships->where('role', 1)->count());
        $this->assertSame(72, $canonicalMemberships->where('role', 0)->count());
    }

    public function test_pending_no_neighborhood_structural_base_is_not_formally_active_until_claim_approval(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.groups_enabled' => true,
        ]);

        ['user' => $user, 'area' => $cityArea, 'endpoint' => $city] = MembershipFixture::canonicalUser();
        $schema = $city->schema()->firstOrFail();
        $regionType = $schema->types()->where('key', 'urban_region')->firstOrFail();

        $region = Location::query()->create([
            'parent_id' => $city->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $regionType->id,
            'country_code' => 'IR',
            'canonical_name' => 'منطقه بدون محله در انتظار',
            'status' => 'active',
        ]);
        $regionArea = GovernanceArea::query()->create([
            'parent_id' => $cityArea->id,
            'key' => 'ir.pending-no-neighborhood-region',
            'country_code' => 'IR',
            'governance_type' => 'urban_region',
            'area_kind' => 'official',
            'canonical_name' => 'منطقه بدون محله در انتظار',
            'rank' => 20,
            'status' => 'active',
        ]);
        $regionArea->locations()->attach($region->id);

        $user->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->update(['ended_at' => now()->subSecond()]);

        $claim = app(LocationStructureClaimService::class)
            ->findOrCreateOpenClaim($region, 'no_neighborhood', $user);

        app(ResidenceService::class)->setInitialPrimaryResidence(
            $user,
            $region,
            ['source' => 'pending_no_neighborhood_test'],
            [$claim],
        );

        app(CanonicalGroupMembershipReconciler::class)->reconcile($user);

        $user->locationScopedGroupRequests()
            ->where('location_structure_claim_id', $claim->id)
            ->delete();
        $healedRequests = app(PendingLocationGroupRequestService::class)->openForUser($user)
            ->where('location_structure_claim_id', $claim->id);
        $this->assertNotEmpty($healedRequests, 'Current residence metadata must self-heal a missing pending structural group shell.');

        $regionPublic = Group::query()
            ->where('governance_area_id', $regionArea->id)
            ->where('dimension_key', 'public')
            ->where('dimension_value_key', 'public')
            ->firstOrFail();
        $regionPivot = $user->groups()->whereKey($regionPublic->id)->firstOrFail()->pivot;
        $this->assertSame(0, (int) $regionPivot->role, 'Pending structural base must not gain formal active membership.');

        $pending = app(PendingLocationGroupRequestService::class)->openForUser($user)
            ->where('location_structure_claim_id', $claim->id);
        $this->assertNotEmpty($pending);
        $pendingPublic = app(PendingLocationGroupRequestService::class)
            ->presentationGroups($pending)
            ->first(fn (Group $group): bool => $group->dimension_key === 'public');
        $this->assertNotNull($pendingPublic);
        $this->assertSame(1, (int) $pendingPublic->pivot->role, 'Chosen pending base must still be presented as the pending base.');

        app(LocationStructureClaimService::class)->approve(
            $claim,
            User::factory()->create(),
            'ساختار بدون محله تایید شد',
        );

        app(CanonicalGroupMembershipReconciler::class)->reconcile($user);
        $this->assertSame(
            1,
            (int) $user->groups()->whereKey($regionPublic->id)->firstOrFail()->pivot->role,
            'Approved structural base must become the formal active membership.',
        );
        $this->assertSame(
            0,
            app(PendingLocationGroupRequestService::class)->openForUser($user)
                ->where('location_structure_claim_id', $claim->id)
                ->count(),
        );
    }

    public function test_pending_official_residence_base_keeps_nearest_approved_ancestor_as_observer(): void
    {
        config(['location-governance.groups_enabled' => true]);

        ['user' => $user, 'area' => $cityArea, 'endpoint' => $city] = MembershipFixture::canonicalUser();
        $schema = $city->schema()->firstOrFail();

        $region = app(LocationProposalService::class)->propose(
            $user,
            $city,
            $schema->types()->where('key', 'urban_region')->firstOrFail(),
            ['canonical_name' => 'منطقه در انتظار'],
        );
        $neighborhood = app(LocationProposalService::class)->proposeUnderProposal(
            $user,
            $region,
            $schema->types()->where('key', 'neighborhood')->firstOrFail(),
            ['canonical_name' => 'محله در انتظار'],
        );
        app(ResidenceService::class)->setPendingResidenceIntent($user, $neighborhood);

        app(CanonicalGroupMembershipReconciler::class)->reconcile($user);

        $cityMemberships = GroupUser::query()
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->whereHas('group', fn ($query) => $query->where('governance_area_id', $cityArea->id))
            ->get();

        $this->assertNotEmpty($cityMemberships);
        $this->assertSame(0, $cityMemberships->where('role', 1)->count());
        $this->assertSame($cityMemberships->count(), $cityMemberships->where('role', 0)->count());
        $this->assertTrue($cityMemberships->every(
            fn ($membership) => str_contains((string) $membership->group?->name, 'شهر ')
        ));
    }
}
