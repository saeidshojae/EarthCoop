<?php

namespace Tests\Feature\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Enums\Elections\ElectionResponsibilityOfferStatus;
use App\Events\Elections\ElectionAppointmentApplied;
use App\Events\Elections\ElectionRepresentationActivated;
use App\Models\Address;
use App\Models\Election;
use App\Models\ElectionAppointment;
use App\Models\ElectionRepresentationAssignment;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\ElectionResponsibilityOffer;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Elections\ElectionAppointmentService;
use App\Services\Elections\ElectionGroupHierarchyResolver;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ElectionAppointmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_structural_street_inherits_neighborhood_then_stops_at_multi_neighborhood_region(): void
    {
        $user = User::factory()->create();

        $neighborhoodA = DB::table('neighborhoods')->insertGetId([
            'name' => 'A', 'parent_id' => 3001, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('neighborhoods')->insert([
            'name' => 'B', 'parent_id' => 3001, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $street = DB::table('streets')->insertGetId([
            'name' => 'Only street', 'parent_id' => $neighborhoodA, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Address::create([
            'user_id' => $user->id,
            'continent_id' => 1, 'country_id' => 10, 'province_id' => 100,
            'county_id' => 1000, 'section_id' => 2000, 'city_id' => 2500,
            'region_id' => 3001, 'neighborhood_id' => $neighborhoodA,
            'street_id' => $street, 'status' => 1,
        ]);

        $streetGroup = $this->group('Street', 'street', $street);
        $neighborhoodGroup = $this->group('Neighborhood A', 'neighborhood', $neighborhoodA);
        $regionGroup = $this->group('Region', 'region', 3001);

        GroupUser::create(['group_id' => $streetGroup->id, 'user_id' => $user->id, 'role' => 1, 'status' => 1]);

        $offer = $this->acceptedOffer($streetGroup, $user, 'manager');
        $service = app(ElectionAppointmentService::class);
        $direct = $service->appoint($offer);

        $this->assertSame('direct', $direct->appointment_kind);
        $this->assertSame(2, (int) GroupUser::where('group_id', $streetGroup->id)->where('user_id', $user->id)->value('role'));
        $this->assertSame(2, (int) GroupUser::where('group_id', $neighborhoodGroup->id)->where('user_id', $user->id)->value('role'));
        $this->assertSame(1, (int) GroupUser::where('group_id', $regionGroup->id)->where('user_id', $user->id)->value('role'));

        $inherited = ElectionAppointment::where('responsibility_offer_id', $offer->id)
            ->where('group_id', $neighborhoodGroup->id)->firstOrFail();
        $this->assertSame('inherited', $inherited->appointment_kind);
        $this->assertSame($direct->id, (int) $inherited->source_appointment_id);

        $representation = ElectionRepresentationAssignment::where('appointment_id', $inherited->id)->firstOrFail();
        $this->assertSame($neighborhoodGroup->id, (int) $representation->source_group_id);
        $this->assertSame($regionGroup->id, (int) $representation->represented_group_id);

        $service->appoint($offer);
        $this->assertSame(2, ElectionAppointment::where('responsibility_offer_id', $offer->id)->count());
        $this->assertSame(1, ElectionRepresentationAssignment::where('user_id', $user->id)->count());
    }

    public function test_structural_compression_is_not_driven_by_current_population_and_counts_parallel_city_rural_branches(): void
    {
        $this->seedUpperTopology();

        $county = $this->group('County', 'county', 1);
        $section = $this->group('Section', 'section', 1);
        $city = $this->group('City', 'city', 1);
        $resolver = app(ElectionGroupHierarchyResolver::class);

        $this->assertSame(1, $resolver->structuralConstituencyCount($county, 'section'));
        $this->assertTrue($resolver->isSoleStructuralConstituency($section, $county));

        $this->assertSame(2, $resolver->structuralConstituencyCount($section, 'city'));
        $this->assertFalse($resolver->isSoleStructuralConstituency($city, $section));
    }

    public function test_missing_optional_region_is_skipped_and_neighborhood_resolves_directly_to_city(): void
    {
        $user = User::factory()->create();
        $cityId = 501;
        $neighborhoodId = DB::table('neighborhoods')->insertGetId([
            'name' => 'Direct city neighborhood', 'parent_id' => $cityId, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Address::create([
            'user_id' => $user->id,
            'continent_id' => 1, 'country_id' => 10, 'province_id' => 100,
            'county_id' => 1000, 'section_id' => 2000, 'city_id' => $cityId,
            'region_id' => null, 'village_id' => null,
            'neighborhood_id' => $neighborhoodId, 'status' => 1,
        ]);

        $neighborhood = $this->group('Neighborhood', 'neighborhood', $neighborhoodId);
        $city = $this->group('City', 'city', $cityId);

        $resolved = app(ElectionGroupHierarchyResolver::class)->higherGroup($neighborhood, $user);
        $this->assertSame($city->id, $resolved?->id);
    }

    public function test_higher_valid_direct_seat_supersedes_lower_independent_seat_on_same_track(): void
    {
        $user = User::factory()->create();
        $regionId = 700;
        $neighborhoodA = DB::table('neighborhoods')->insertGetId([
            'name' => 'A', 'parent_id' => $regionId, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('neighborhoods')->insert([
            'name' => 'B', 'parent_id' => $regionId, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $streetA = DB::table('streets')->insertGetId([
            'name' => 'A1', 'parent_id' => $neighborhoodA, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('streets')->insert([
            'name' => 'A2', 'parent_id' => $neighborhoodA, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Address::create([
            'user_id' => $user->id,
            'continent_id' => 1, 'country_id' => 10, 'province_id' => 100,
            'county_id' => 1000, 'section_id' => 2000, 'city_id' => 2500,
            'region_id' => $regionId, 'neighborhood_id' => $neighborhoodA,
            'street_id' => $streetA, 'status' => 1,
        ]);

        $streetGroup = $this->group('Street A1', 'street', $streetA);
        $neighborhoodGroup = $this->group('Neighborhood A', 'neighborhood', $neighborhoodA);
        $regionGroup = $this->group('Region', 'region', $regionId);
        GroupUser::create(['group_id' => $streetGroup->id, 'user_id' => $user->id, 'role' => 1, 'status' => 1]);

        $service = app(ElectionAppointmentService::class);
        $lowerOffer = $this->acceptedOffer($streetGroup, $user, 'manager');
        $lower = $service->appoint($lowerOffer);
        $this->assertSame('active', $lower->status);
        $this->assertSame(1, (int) GroupUser::where('group_id', $neighborhoodGroup->id)->where('user_id', $user->id)->value('role'));

        $higherOffer = $this->acceptedOffer($neighborhoodGroup, $user, 'manager');
        $higher = $service->appoint($higherOffer);

        $this->assertSame('superseded', $lower->refresh()->status);
        $this->assertSame($higher->id, (int) $lower->superseded_by_appointment_id);
        $this->assertNotNull($lower->ended_at);
        $this->assertSame(1, (int) GroupUser::where('group_id', $streetGroup->id)->where('user_id', $user->id)->value('role'));
        $this->assertSame(2, (int) GroupUser::where('group_id', $neighborhoodGroup->id)->where('user_id', $user->id)->value('role'));
        $this->assertSame(1, (int) GroupUser::where('group_id', $regionGroup->id)->where('user_id', $user->id)->value('role'));
    }

    public function test_official_e8_events_are_commit_safe_domain_events(): void
    {
        $this->assertTrue(is_subclass_of(ElectionAppointmentApplied::class, ShouldDispatchAfterCommit::class));
        $this->assertTrue(is_subclass_of(ElectionRepresentationActivated::class, ShouldDispatchAfterCommit::class));
    }

    private function acceptedOffer(Group $group, User $user, string $position): ElectionResponsibilityOffer
    {
        $election = Election::create([
            'group_id' => $group->id,
            'starts_at' => now()->subDays(10), 'ends_at' => now()->subDay(),
            'is_closed' => true,
            'lifecycle_status' => ElectionLifecycleStatus::AwaitingAcceptance,
        ]);

        $contract = ElectionResponsibilityContractVersion::query()->firstOrCreate(
            ['position' => $position, 'version' => 1],
            ['body' => "{$position} contract", 'is_active' => true, 'published_at' => now()->subDay()],
        );

        return ElectionResponsibilityOffer::create([
            'election_id' => $election->id,
            'candidate_user_id' => $user->id,
            'position' => $position,
            'ranking_position' => 1,
            'contract_version_id' => $contract->id,
            'status' => ElectionResponsibilityOfferStatus::Accepted,
            'offered_at' => now()->subHour(),
            'expires_at' => now()->addDays(6),
            'responded_at' => now(),
            'eligibility_checked_at' => now(),
            'resolution_reason' => 'candidate_accepted_contract',
        ]);
    }

    private function seedUpperTopology(): void
    {
        DB::table('continents')->insert(['id' => 1, 'name' => 'C', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('countries')->insert(['id' => 1, 'name' => 'Country', 'continent_id' => 1, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('provinces')->insert(['id' => 1, 'name' => 'Province', 'country_id' => 1, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('counties')->insert(['id' => 1, 'name' => 'County', 'province_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('districts')->insert(['id' => 1, 'name' => 'Section', 'province_id' => 1, 'county_id' => 1, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('cities')->insert([
            'id' => 1, 'name' => 'City', 'province_id' => 1, 'county_id' => 1, 'district_id' => 1,
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('rurals')->insert([
            'id' => 1, 'name' => 'Rural', 'province_id' => 1, 'county_id' => 1, 'district_id' => 1,
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function group(string $name, string $level, int $addressId): Group
    {
        return Group::create([
            'group_type' => '0',
            'name' => $name,
            'location_level' => $level,
            'address_id' => $addressId,
        ]);
    }
}
