<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\LocationScopedGroupRequest;
use App\Models\AgeGroup;
use App\Models\ExperienceField;
use App\Models\MembershipDimension;
use App\Models\OccupationalField;
use App\Services\Membership\AgeDimensionResolver;
use App\Services\Membership\GenderDimensionResolver;
use App\Services\Membership\ProfessionDimensionResolver;
use App\Services\Membership\PublicDimensionResolver;
use App\Services\Membership\SpecialtyDimensionResolver;
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
        MembershipDimension::create(['key' => 'public', 'name' => 'Public', 'resolver_class' => PublicDimensionResolver::class, 'enabled' => true]);
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

    public function test_two_pending_official_levels_expand_to_eighteen_profile_memberships(): void
    {
        foreach ([
            'profession' => ProfessionDimensionResolver::class,
            'specialty' => SpecialtyDimensionResolver::class,
            'age' => AgeDimensionResolver::class,
            'gender' => GenderDimensionResolver::class,
        ] as $key => $resolver) {
            MembershipDimension::create(['key' => $key, 'name' => ucfirst($key), 'resolver_class' => $resolver, 'enabled' => true]);
        }

        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $user = User::factory()->create(['birth_date' => now()->subYears(30)->toDateString(), 'gender' => 'male']);

        $p1 = OccupationalField::create(['name' => 'صنف ۱', 'status' => 1]);
        $p2 = OccupationalField::create(['name' => 'صنف ۲', 'parent_id' => $p1->id, 'status' => 1]);
        $p3 = OccupationalField::create(['name' => 'صنف ۳', 'parent_id' => $p2->id, 'status' => 1]);
        $user->occupationalFields()->attach($p3->id);

        $s1 = ExperienceField::create(['name' => 'تخصص ۱', 'status' => 1]);
        $s2 = ExperienceField::create(['name' => 'تخصص ۲', 'parent_id' => $s1->id, 'status' => 1]);
        $s3 = ExperienceField::create(['name' => 'تخصص ۳', 'parent_id' => $s2->id, 'status' => 1]);
        $user->experienceFields()->attach($s3->id);
        AgeGroup::create(['title' => '۲۵ تا ۳۵', 'min_age' => 25, 'max_age' => 35]);

        $region = app(LocationProposalService::class)->propose($user, $city, $schema->types->firstWhere('key', 'urban_region'), ['canonical_name' => '۵ ساری']);
        $neighborhood = app(LocationProposalService::class)->proposeUnderProposal($user, $region, $schema->types->firstWhere('key', 'neighborhood'), ['canonical_name' => 'آزمایشی ۲']);

        $requests = app(PendingLocationGroupRequestService::class)->syncForPendingResidence($user, $neighborhood);

        $this->assertCount(18, $requests);
        $this->assertSame(2, $requests->where('dimension_key', 'public')->count());
        $this->assertSame(6, $requests->where('dimension_key', 'profession')->count());
        $this->assertSame(6, $requests->where('dimension_key', 'specialty')->count());
        $this->assertSame(2, $requests->where('dimension_key', 'age')->count());
        $this->assertSame(2, $requests->where('dimension_key', 'gender')->count());
        $this->assertTrue($requests->every(fn (LocationScopedGroupRequest $request) => $request->group_id === null && $request->governance_area_id === null));

        $presented = app(PendingLocationGroupRequestService::class)->presentationGroups($requests);
        $this->assertCount(18, $presented);
        $this->assertTrue($presented->every(fn ($group) => $group->pending_location === true));
        $neighborhoodGroups = $presented->where('presentation_rank', 9);
        $regionGroups = $presented->where('presentation_rank', 8);
        $this->assertCount(9, $neighborhoodGroups);
        $this->assertCount(9, $regionGroups);
        $this->assertTrue($neighborhoodGroups->every(fn ($group) => (int) $group->pivot->role === 1));
        $this->assertTrue($regionGroups->every(fn ($group) => (int) $group->pivot->role === 0));
        $this->assertTrue($neighborhoodGroups->every(fn ($group) => str_contains($group->name, 'محله آزمایشی ۲')));
        $this->assertTrue($regionGroups->every(fn ($group) => str_contains($group->name, 'منطقه ۵ ساری')));
    }
}
