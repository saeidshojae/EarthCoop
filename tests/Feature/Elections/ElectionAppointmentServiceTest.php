<?php

namespace Tests\Feature\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Enums\Elections\ElectionResponsibilityOfferStatus;
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

        $election = Election::create([
            'group_id' => $streetGroup->id,
            'starts_at' => now()->subDays(10), 'ends_at' => now()->subDay(),
            'is_closed' => true,
            'lifecycle_status' => ElectionLifecycleStatus::AwaitingAcceptance,
        ]);

        $contract = ElectionResponsibilityContractVersion::create([
            'position' => 'manager', 'version' => 1, 'body' => 'manager contract',
            'is_active' => true, 'published_at' => now()->subDay(),
        ]);

        $offer = ElectionResponsibilityOffer::create([
            'election_id' => $election->id,
            'candidate_user_id' => $user->id,
            'position' => 'manager',
            'ranking_position' => 1,
            'contract_version_id' => $contract->id,
            'status' => ElectionResponsibilityOfferStatus::Accepted,
            'offered_at' => now()->subHour(),
            'expires_at' => now()->addDays(6),
            'responded_at' => now(),
            'eligibility_checked_at' => now(),
            'resolution_reason' => 'candidate_accepted_contract',
        ]);

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

        // Retry is idempotent: no duplicate direct/inherited appointment or representation.
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

        // Exactly one configured section in this county: section can inherit county.
        $this->assertSame(1, $resolver->structuralConstituencyCount($county, 'section'));
        $this->assertTrue($resolver->isSoleStructuralConstituency($section, $county));

        // The section has one city AND one rural: two structural constituencies.
        // It must run a genuine election instead of inheriting the city office.
        $this->assertSame(2, $resolver->structuralConstituencyCount($section, 'city'));
        $this->assertFalse($resolver->isSoleStructuralConstituency($city, $section));
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
