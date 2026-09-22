<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\LocationScopedGroupRequest;
use App\Models\User;
use App\Services\Groups\PendingLocationGroupRequestService;
use App\Services\LocationGovernance\LocationProposalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class PendingRegistrationGroupContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['location-governance.runtime_enabled' => true, 'location-governance.registration_enabled' => true, 'location-governance.groups_enabled' => true]);
    }

    public function test_pending_region_alone_cannot_complete_registration(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $user = User::factory()->create();
        $region = app(LocationProposalService::class)->propose($user, $city, $schema->types->firstWhere('key', 'urban_region'), ['canonical_name' => 'منطقه در انتظار']);

        $this->actingAs($user)->from(route('register.step3'))->post(route('register.step3.process'), ['location_proposal_id' => $region->id])
            ->assertRedirect(route('register.step3'))->assertSessionHasErrors('location_proposal_id');
        $this->assertDatabaseMissing('pending_residence_intents', ['user_id' => $user->id]);
    }

    public function test_pending_official_chain_gets_non_materialized_group_shells(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $user = User::factory()->create();
        $region = app(LocationProposalService::class)->propose($user, $city, $schema->types->firstWhere('key', 'urban_region'), ['canonical_name' => 'منطقه در انتظار']);
        $neighborhood = app(LocationProposalService::class)->proposeUnderProposal($user, $region, $schema->types->firstWhere('key', 'neighborhood'), ['canonical_name' => 'محله در انتظار']);

        $requests = app(PendingLocationGroupRequestService::class)->syncForPendingResidence($user, $neighborhood);

        $this->assertCount(2, $requests);
        $this->assertSame(2, LocationScopedGroupRequest::query()->where('requester_user_id', $user->id)->where('status', 'pending_location')->count());
        $this->assertTrue($requests->every(fn (LocationScopedGroupRequest $request) => $request->group_id === null && $request->governance_area_id === null));
    }
}
