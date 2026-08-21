<?php

namespace Tests\Feature\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Enums\Elections\ElectionResponsibilityOfferStatus;
use App\Models\Election;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\ElectionResponsibilityOffer;
use App\Models\ElectionTallyResult;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Elections\ElectionResponsibilityOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionResponsibilityOfferServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_decline_immediately_invites_next_rank_and_acceptance_does_not_install_role(): void
    {
        [$election, $managerA, $managerB, $inspectorA] = $this->fixture();
        $service = app(ElectionResponsibilityOfferService::class);

        $service->start($election);
        $this->assertSame('awaiting_acceptance', $election->refresh()->lifecycle_status->value);

        $managerOffer = ElectionResponsibilityOffer::where('election_id', $election->id)
            ->where('position', 'manager')->where('status', 'pending')->firstOrFail();
        $this->assertSame($managerA->id, $managerOffer->candidate_user_id);
        $this->assertSame(1, $managerOffer->ranking_position);
        $this->assertSame(7, $managerOffer->offered_at->diffInDays($managerOffer->expires_at));

        $service->decline($managerOffer, $managerA->id);
        $replacement = ElectionResponsibilityOffer::where('election_id', $election->id)
            ->where('position', 'manager')->where('status', 'pending')->firstOrFail();
        $this->assertSame($managerB->id, $replacement->candidate_user_id);
        $this->assertSame(2, $replacement->ranking_position);

        $inspectorOffer = ElectionResponsibilityOffer::where('election_id', $election->id)
            ->where('position', 'inspector')->where('status', 'pending')->firstOrFail();
        $service->accept($inspectorOffer, $inspectorA->id);
        $this->assertSame(
            ElectionResponsibilityOfferStatus::Accepted,
            $inspectorOffer->refresh()->status,
        );

        // Appointment is E8. E7 acceptance must not mutate group responsibility role.
        $this->assertSame(1, (int) GroupUser::where('group_id', $election->group_id)
            ->where('user_id', $inspectorA->id)->value('role'));
    }

    public function test_silence_expires_server_side_and_candidate_who_lost_live_eligibility_is_skipped(): void
    {
        [$election, $managerA, $managerB] = $this->fixture();
        $service = app(ElectionResponsibilityOfferService::class);
        $service->start($election);

        $first = ElectionResponsibilityOffer::where('election_id', $election->id)
            ->where('position', 'manager')->where('status', 'pending')->firstOrFail();
        $first->forceFill(['expires_at' => now()->subSecond()])->save();

        GroupUser::where('group_id', $election->group_id)
            ->where('user_id', $managerB->id)->update(['status' => 0]);

        $this->assertSame(1, $service->expireDue());
        $this->assertSame(ElectionResponsibilityOfferStatus::Expired, $first->refresh()->status);

        $second = ElectionResponsibilityOffer::where('election_id', $election->id)
            ->where('position', 'manager')->where('candidate_user_id', $managerB->id)->firstOrFail();
        $this->assertSame(ElectionResponsibilityOfferStatus::Ineligible, $second->status);
        $this->assertSame('awaiting_acceptance', $election->refresh()->lifecycle_status->value);
    }

    private function fixture(): array
    {
        $group = Group::create(['name' => 'Offers group', 'group_type' => 'public', 'location_level' => 'neighborhood']);
        GroupSetting::create([
            'level' => 'neighborhood', 'manager_count' => 1, 'inspector_count' => 1,
            'election_time' => 10, 'max_for_election' => 1, 'election_status' => 1,
        ]);

        $election = Election::create([
            'group_id' => $group->id,
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subMinute(),
            'is_closed' => true,
            'lifecycle_status' => ElectionLifecycleStatus::Tallying,
        ]);

        $managerA = User::factory()->create();
        $managerB = User::factory()->create();
        $inspectorA = User::factory()->create();
        $inspectorB = User::factory()->create();
        foreach ([$managerA, $managerB, $inspectorA, $inspectorB] as $user) {
            GroupUser::create(['group_id' => $group->id, 'user_id' => $user->id, 'role' => 1, 'status' => 1]);
        }

        foreach (['manager', 'inspector'] as $position) {
            ElectionResponsibilityContractVersion::create([
                'position' => $position,
                'version' => 1,
                'body' => "contract {$position} v1",
                'is_active' => true,
                'published_at' => now()->subDay(),
            ]);
        }

        $this->tallyRow($election, $managerA, 'manager', 1, 10);
        $this->tallyRow($election, $managerB, 'manager', 2, 8);
        $this->tallyRow($election, $inspectorA, 'inspector', 1, 9);
        $this->tallyRow($election, $inspectorB, 'inspector', 2, 5);

        return [$election, $managerA, $managerB, $inspectorA, $inspectorB];
    }

    private function tallyRow(Election $election, User $candidate, string $position, int $rank, int $votes): void
    {
        ElectionTallyResult::create([
            'election_id' => $election->id,
            'candidate_user_id' => $candidate->id,
            'position' => $position,
            'vote_count' => $votes,
            'rank' => $rank,
            'within_seat_cutoff' => $rank === 1,
            'cycle_identifier' => 'election:'.$election->id,
            'stopped_at' => now()->subMinute(),
            'vote_snapshot_hash' => str_repeat('a', 64),
            'draw_seed_version' => 'stop-cycle-snapshot-sha256-v1',
            'draw_seed' => str_repeat('b', 64),
            'tie_break_version' => 'verifiable-draw-sha256-v1',
            'tie_break_key' => hash('sha256', $position.'-'.$rank),
            'tallied_at' => now()->subMinute(),
        ]);
    }
}
