<?php

namespace Tests\Feature\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Enums\Elections\ElectionResponsibilityOfferStatus;
use App\Models\Election;
use App\Models\ElectionAppointment;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\ElectionResponsibilityOffer;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Elections\ElectionAppointmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionAppointmentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_manager_and_inspector_are_atomically_appointed_before_election_becomes_filled(): void
    {
        $group = Group::create([
            'group_type' => '0',
            'name' => 'Global assembly',
            'location_level' => 'global',
            'address_id' => null,
        ]);
        GroupSetting::create([
            'level' => 'global',
            'manager_count' => 1,
            'inspector_count' => 1,
            'election_time' => 10,
            'max_for_election' => 1,
            'election_status' => 1,
        ]);

        $manager = User::factory()->create();
        $inspector = User::factory()->create();
        foreach ([$manager, $inspector] as $user) {
            GroupUser::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => 1,
                'status' => 1,
            ]);
        }

        $election = Election::create([
            'group_id' => $group->id,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
            'is_closed' => true,
            'lifecycle_status' => ElectionLifecycleStatus::AwaitingAcceptance,
        ]);

        $this->acceptedOffer($election, $manager, 'manager', 1);
        $this->acceptedOffer($election, $inspector, 'inspector', 1);

        $summary = app(ElectionAppointmentService::class)->process($election);

        $this->assertSame('filled', $summary['lifecycle_status']);
        $this->assertSame(2, $summary['direct_appointments']);
        $this->assertSame(0, $summary['inherited_appointments']);
        $this->assertSame(ElectionLifecycleStatus::Filled, $election->refresh()->lifecycle_status);
        $this->assertSame(2, (int) GroupUser::where('group_id', $group->id)->where('user_id', $manager->id)->value('role'));
        $this->assertSame(3, (int) GroupUser::where('group_id', $group->id)->where('user_id', $inspector->id)->value('role'));
        $this->assertSame(2, ElectionAppointment::where('election_id', $election->id)->where('status', 'active')->count());

        $transitions = $election->lifecycleTransitions()
            ->orderBy('id')
            ->get()
            ->map(fn ($transition) => $transition->to_status->value)
            ->all();
        $this->assertContains('appointing', $transitions);
        $this->assertContains('filled', $transitions);
    }

    private function acceptedOffer(Election $election, User $user, string $position, int $rank): ElectionResponsibilityOffer
    {
        $contract = ElectionResponsibilityContractVersion::query()->firstOrCreate(
            ['position' => $position, 'version' => 1],
            [
                'body' => "{$position} contract",
                'is_active' => true,
                'published_at' => now()->subDay(),
            ],
        );

        return ElectionResponsibilityOffer::create([
            'election_id' => $election->id,
            'candidate_user_id' => $user->id,
            'position' => $position,
            'ranking_position' => $rank,
            'contract_version_id' => $contract->id,
            'status' => ElectionResponsibilityOfferStatus::Accepted,
            'offered_at' => now()->subHour(),
            'expires_at' => now()->addDays(6),
            'responded_at' => now(),
            'eligibility_checked_at' => now(),
            'resolution_reason' => 'candidate_accepted_contract',
        ]);
    }
}
