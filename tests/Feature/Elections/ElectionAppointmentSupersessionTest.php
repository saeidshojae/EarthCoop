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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ElectionAppointmentSupersessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_higher_direct_seat_ends_lower_direct_and_its_inherited_chain(): void
    {
        $user = User::factory()->create();
        $regionId = 8100;
        $neighborhoodId = 8101;
        $otherNeighborhoodId = 8102;
        $streetId = 8103;

        DB::table('neighborhoods')->insert([
            ['id' => $neighborhoodId, 'name' => 'N1', 'parent_id' => $regionId, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $otherNeighborhoodId, 'name' => 'N2', 'parent_id' => $regionId, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('streets')->insert([
            'id' => $streetId, 'name' => 'Only street', 'parent_id' => $neighborhoodId,
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Address::create([
            'user_id' => $user->id,
            'continent_id' => 1, 'country_id' => 10, 'province_id' => 100,
            'county_id' => 1000, 'section_id' => 2000, 'city_id' => 2500,
            'region_id' => $regionId, 'neighborhood_id' => $neighborhoodId,
            'street_id' => $streetId, 'status' => 1,
        ]);

        $street = $this->group('street', $streetId);
        $neighborhood = $this->group('neighborhood', $neighborhoodId);
        $region = $this->group('region', $regionId);
        GroupUser::create(['group_id' => $street->id, 'user_id' => $user->id, 'role' => 1, 'status' => 1]);

        $service = app(ElectionAppointmentService::class);
        $lowerOffer = $this->offer($street, $user);
        $lowerDirect = $service->appoint($lowerOffer);
        $oldInherited = ElectionAppointment::where('responsibility_offer_id', $lowerOffer->id)
            ->where('group_id', $neighborhood->id)
            ->where('appointment_kind', 'inherited')
            ->firstOrFail();
        $oldRepresentation = ElectionRepresentationAssignment::where('appointment_id', $oldInherited->id)->firstOrFail();

        $higherOffer = $this->offer($neighborhood, $user);
        $higherDirect = $service->appoint($higherOffer);

        $this->assertSame('superseded', $lowerDirect->refresh()->status);
        $this->assertSame('superseded', $oldInherited->refresh()->status);
        $this->assertNotNull($oldInherited->ended_at);
        $this->assertSame('ended', $oldRepresentation->refresh()->status);

        $this->assertSame(1, ElectionAppointment::query()
            ->where('user_id', $user->id)
            ->where('group_id', $neighborhood->id)
            ->where('position', 'manager')
            ->where('status', 'active')
            ->count());
        $this->assertSame($higherDirect->id, ElectionAppointment::query()
            ->where('user_id', $user->id)
            ->where('group_id', $neighborhood->id)
            ->where('position', 'manager')
            ->where('status', 'active')
            ->value('id'));
        $this->assertSame(2, (int) GroupUser::where('group_id', $neighborhood->id)->where('user_id', $user->id)->value('role'));
        $this->assertSame(1, (int) GroupUser::where('group_id', $region->id)->where('user_id', $user->id)->value('role'));
    }

    private function offer(Group $group, User $user): ElectionResponsibilityOffer
    {
        $election = Election::create([
            'group_id' => $group->id,
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDay(),
            'is_closed' => true,
            'lifecycle_status' => ElectionLifecycleStatus::AwaitingAcceptance,
        ]);
        $contract = ElectionResponsibilityContractVersion::query()->firstOrCreate(
            ['position' => 'manager', 'version' => 1],
            ['body' => 'manager contract', 'is_active' => true, 'published_at' => now()->subDay()],
        );

        return ElectionResponsibilityOffer::create([
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
    }

    private function group(string $level, int $addressId): Group
    {
        return Group::create([
            'group_type' => '0',
            'name' => 'Group '.$level,
            'location_level' => $level,
            'address_id' => $addressId,
        ]);
    }
}
