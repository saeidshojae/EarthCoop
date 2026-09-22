<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Location;
use App\Models\MembershipDimension;
use App\Services\Membership\PublicDimensionResolver;
use App\Models\User;
use App\Services\Groups\PendingLocationGroupRequestService;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\LocationStructureClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class PendingLocationGroupLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        MembershipDimension::create(['key' => 'public', 'name' => 'Public', 'resolver_class' => PublicDimensionResolver::class, 'enabled' => true]);
    }

    public function test_approved_proposal_moves_pending_shell_to_ready_without_premature_group(): void
    {
        [$user, $parent, $type] = $this->proposalContext();
        $proposal = app(LocationProposalService::class)->propose($user, $parent, $type, ['canonical_name' => 'محله چرخه تأیید']);
        app(PendingLocationGroupRequestService::class)->syncForPendingResidence($user, $proposal);

        $location = app(LocationProposalService::class)->approve($proposal, User::factory()->create(), 'تأیید آزمون');

        $request = $user->locationScopedGroupRequests()->sole();
        $this->assertSame('ready_to_materialize', $request->status);
        $this->assertSame($location->id, $request->location_id);
        $this->assertNull($request->location_proposal_id);
        $this->assertNull($request->group_id);
        $this->assertNull($request->governance_area_id);
    }

    public function test_rejected_proposal_rejects_pending_shell_without_materializing_group(): void
    {
        [$user, $parent, $type] = $this->proposalContext();
        $proposal = app(LocationProposalService::class)->propose($user, $parent, $type, ['canonical_name' => 'محله چرخه رد']);
        app(PendingLocationGroupRequestService::class)->syncForPendingResidence($user, $proposal);

        app(LocationProposalService::class)->reject($proposal, User::factory()->create(), 'رد آزمون');

        $request = $user->locationScopedGroupRequests()->sole();
        $this->assertSame('rejected', $request->status);
        $this->assertNull($request->group_id);
    }

    public function test_merge_follows_existing_scope_and_materializes_only_existing_active_membership(): void
    {
        config(['location-governance.groups_enabled' => false]);
        [$user, $parent, $type] = $this->proposalContext();
        $proposal = app(LocationProposalService::class)->propose($user, $parent, $type, ['canonical_name' => 'نام جایگزین محله']);
        app(PendingLocationGroupRequestService::class)->syncForPendingResidence($user, $proposal);

        $existing = Location::factory()->create([
            'parent_id' => $parent->id,
            'location_schema_id' => $parent->location_schema_id,
            'location_type_id' => $type->id,
            'country_code' => 'IR',
            'name' => 'محله موجود',
            'canonical_name' => 'محله موجود',
            'level' => 'neighborhood',
            'status' => 'active',
        ]);
        $area = GovernanceArea::create([
            'key' => 'test.pending.merge',
            'country_code' => 'IR',
            'governance_type' => 'neighborhood',
            'area_kind' => 'official',
            'canonical_name' => 'محله موجود',
            'rank' => 90,
            'status' => 'active',
        ]);
        $area->locations()->attach($existing->id);
        $group = Group::create([
            'name' => 'مجمع عمومی محله موجود',
            'group_type' => '0',
            'governance_area_id' => $area->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
            'is_open' => 1,
        ]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $user->id, 'role' => 1, 'status' => 1]);

        app(LocationProposalService::class)->merge($proposal, $existing, User::factory()->create(), 'ادغام آزمون');

        $request = $user->locationScopedGroupRequests()->sole();
        $this->assertSame('materialized', $request->status);
        $this->assertSame($existing->id, $request->location_id);
        $this->assertSame($area->id, $request->governance_area_id);
        $this->assertSame($group->id, $request->group_id);
    }

    public function test_structural_claim_shell_tracks_approval_and_rejection(): void
    {
        $schema = LocationFixture::iranSchema();
        $village = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'rural_district', 'village'])->last();
        $service = app(LocationStructureClaimService::class);
        $groups = app(PendingLocationGroupRequestService::class);
        $reviewer = User::factory()->create();

        $approvedUser = User::factory()->create();
        $approved = $service->findOrCreateOpenClaim($village, 'no_neighborhood', $approvedUser);
        $groups->syncForStructuralClaims($approvedUser, $village, [$approved]);
        $service->approve($approved, $reviewer, 'تأیید ساختاری');

        $this->assertSame('ready_to_materialize', $approvedUser->locationScopedGroupRequests()->sole()->status);

        $otherVillage = Location::factory()->create([
            'parent_id' => $village->parent_id,
            'location_schema_id' => $village->location_schema_id,
            'location_type_id' => $village->location_type_id,
            'country_code' => 'IR',
            'name' => 'روستای دوم',
            'canonical_name' => 'روستای دوم',
            'level' => 'village',
            'status' => 'active',
        ]);
        $rejectedUser = User::factory()->create();
        $rejected = $service->findOrCreateOpenClaim($otherVillage, 'no_neighborhood', $rejectedUser);
        $groups->syncForStructuralClaims($rejectedUser, $otherVillage, [$rejected]);
        $service->reject($rejected, $reviewer, 'رد ساختاری');

        $this->assertSame('rejected', $rejectedUser->locationScopedGroupRequests()->sole()->status);
    }

    private function proposalContext(): array
    {
        $schema = LocationFixture::iranSchema();
        $parent = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region',
        ])->last();

        return [User::factory()->create(), $parent, $schema->types->firstWhere('key', 'neighborhood')];
    }
}
