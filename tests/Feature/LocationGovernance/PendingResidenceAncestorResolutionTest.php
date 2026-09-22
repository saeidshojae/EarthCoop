<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\GroupUser;
use App\Models\PendingResidenceIntent;
use App\Models\User;
use App\Models\UserLocationRelationship;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\Support\LocationGovernance\MembershipFixture;
use Tests\TestCase;

class PendingResidenceAncestorResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_pending_parent_materializes_its_canonical_observer_groups_while_deepest_child_stays_pending(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.groups_enabled' => true,
        ]);

        ['user' => $user, 'endpoint' => $city, 'area' => $cityArea] = MembershipFixture::canonicalUser();
        $schema = $city->schema()->firstOrFail();
        $regionType = $schema->types()->where('key', 'urban_region')->firstOrFail();
        $neighborhoodType = $schema->types()->where('key', 'neighborhood')->firstOrFail();

        $proposals = app(LocationProposalService::class);
        $region = $proposals->propose($user, $city, $regionType, ['canonical_name' => '۵ ساری']);
        $neighborhood = $proposals->proposeUnderProposal($user, $region, $neighborhoodType, ['canonical_name' => 'آزمایشی ۲']);
        app(ResidenceService::class)->setPendingResidenceIntent($user, $neighborhood, ['source' => 'uat-regression']);
        app(\App\Services\Groups\PendingLocationGroupRequestService::class)->syncForPendingResidence($user, $neighborhood);

        $resolvedRegion = $proposals->approve($region, User::factory()->create(), 'تأیید منطقه');

        $regionArea = $resolvedRegion->governanceAreas()->official()->active()->sole();
        $this->assertSame($cityArea->id, $regionArea->parent_id);
        $this->assertSame('urban_region', $regionArea->governance_type);

        $regionRequests = $user->locationScopedGroupRequests()
            ->where('location_id', $resolvedRegion->id)
            ->get();
        $this->assertNotEmpty($regionRequests);
        $this->assertTrue($regionRequests->every(fn ($request) => $request->status === 'materialized'));

        $pendingChildRequests = $user->locationScopedGroupRequests()
            ->where('location_proposal_id', $neighborhood->id)
            ->get();
        $this->assertNotEmpty($pendingChildRequests);
        $this->assertTrue($pendingChildRequests->every(fn ($request) => $request->status === 'pending_location'));

        $regionGroupIds = $regionRequests->pluck('group_id')->filter()->all();
        $this->assertNotEmpty($regionGroupIds);
        $this->assertSame(
            count($regionGroupIds),
            GroupUser::query()->where('user_id', $user->id)->whereIn('group_id', $regionGroupIds)
                ->where('status', 1)->where('role', 0)->count(),
        );

        $intent = PendingResidenceIntent::query()->where('user_id', $user->id)->where('status', 'pending')->sole();
        $this->assertSame($neighborhood->id, $intent->location_proposal_id);
    }

    public function test_approving_pending_parent_advances_anchor_without_resolving_deepest_intent(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $regionType = $schema->types->firstWhere('key', 'urban_region');
        $neighborhoodType = $schema->types->firstWhere('key', 'neighborhood');
        $user = User::factory()->create();

        $anchor = UserLocationRelationship::create([
            'user_id' => $user->id,
            'location_id' => $city->id,
            'relationship_type' => 'primary_residence',
            'started_at' => now()->subMinute(),
        ]);

        $proposals = app(LocationProposalService::class);
        $region = $proposals->propose($user, $city, $regionType, ['canonical_name' => 'منطقه در انتظار']);
        $neighborhood = $proposals->proposeUnderProposal($user, $region, $neighborhoodType, ['canonical_name' => 'محله در انتظار']);
        app(ResidenceService::class)->setPendingResidenceIntent($user, $neighborhood, ['source' => 'test']);

        $resolvedRegion = $proposals->approve($region, User::factory()->create(), 'تأیید منطقه');

        $intent = PendingResidenceIntent::query()->where('user_id', $user->id)->sole();
        $current = UserLocationRelationship::query()
            ->where('user_id', $user->id)
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();

        $this->assertSame('pending', $intent->status);
        $this->assertSame($resolvedRegion->id, $current->location_id);
        $this->assertSame($current->id, $intent->anchor_relationship_id);
        $this->assertNotSame($anchor->id, $current->id);
        $this->assertSame($resolvedRegion->id, $neighborhood->fresh()->parent_location_id);
        $this->assertNull($neighborhood->fresh()->parent_location_proposal_id);

        $resolvedNeighborhood = $proposals->approve($neighborhood->fresh(), User::factory()->create(), 'تأیید محله');

        $intent->refresh();
        $current = UserLocationRelationship::query()
            ->where('user_id', $user->id)
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();

        $this->assertSame('resolved', $intent->status);
        $this->assertSame($resolvedNeighborhood->id, $intent->resolved_location_id);
        $this->assertSame($resolvedNeighborhood->id, $current->location_id);
    }
}
