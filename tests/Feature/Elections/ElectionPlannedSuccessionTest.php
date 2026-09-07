<?php

namespace Tests\Feature\Elections;

use App\Models\Election;
use App\Models\ElectionAppointment;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\ElectionResponsibilityOffer;
use App\Models\ElectionTallyResult;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Elections\ElectionResponsibilityAcceptanceEvidenceService;
use App\Services\Elections\ElectionResponsibilityContractVersionService;
use App\Services\Elections\ElectionResponsibilityOfferService;
use App\Services\Elections\ElectionVacancyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionPlannedSuccessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_incumbent_remains_active_until_replacement_is_appointed(): void
    {
        $group = Group::create([
            'name' => 'Planned succession group',
            'group_type' => '0',
            'location_level' => 'global',
            'address_id' => null,
        ]);
        GroupSetting::create([
            'level' => 'global',
            'manager_count' => 1,
            'inspector_count' => 0,
            'election_time' => 10,
            'max_for_election' => 1,
            'election_status' => 1,
            'second_election_time' => 3,
        ]);

        $incumbent = User::factory()->create(['is_system' => false]);
        $replacement = User::factory()->create(['is_system' => false]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $incumbent->id, 'role' => 2, 'status' => 1]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $replacement->id, 'role' => 1, 'status' => 1]);

        $election = Election::create([
            'group_id' => $group->id,
            'cycle_number' => 1,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
            'is_closed' => true,
            'lifecycle_status' => 'filled',
        ]);
        $contract = $this->publishManagerContract($incumbent);
        $incumbentOffer = ElectionResponsibilityOffer::create([
            'election_id' => $election->id,
            'candidate_user_id' => $incumbent->id,
            'position' => 'manager',
            'ranking_position' => 1,
            'contract_version_id' => $contract->id,
            'status' => 'accepted',
            'offered_at' => now()->subMonth(),
            'expires_at' => now()->subWeeks(3),
            'responded_at' => now()->subMonth(),
            'eligibility_checked_at' => now()->subMonth(),
        ]);
        foreach ([[$incumbent, 1], [$replacement, 2]] as [$user, $rank]) {
            ElectionTallyResult::create([
                'election_id' => $election->id,
                'candidate_user_id' => $user->id,
                'position' => 'manager',
                'vote_count' => 3 - $rank,
                'rank' => $rank,
                'within_seat_cutoff' => $rank === 1,
                'cycle_identifier' => 'planned-succession-cycle',
                'stopped_at' => now()->subMonth(),
                'vote_snapshot_hash' => str_repeat('a', 64),
                'draw_seed_version' => 'planned-succession-seed-v1',
                'draw_seed' => str_repeat('b', 64),
                'tie_break_version' => 'planned-succession-tie-v1',
                'tie_break_key' => hash('sha256', 'planned-'.$rank),
                'tallied_at' => now()->subMonth(),
            ]);
        }

        $appointment = ElectionAppointment::create([
            'election_id' => $election->id,
            'responsibility_offer_id' => $incumbentOffer->id,
            'user_id' => $incumbent->id,
            'group_id' => $group->id,
            'position' => 'manager',
            'group_role' => 2,
            'appointment_kind' => 'direct',
            'status' => 'active',
            'appointed_at' => now()->subWeeks(3),
        ]);

        $vacancies = app(ElectionVacancyService::class);
        $vacancy = $vacancies->openPlannedSuccession($appointment, 'term_rotation', 'system_test');

        $this->assertSame('planned', $vacancy->continuity_mode);
        $this->assertSame('active', $appointment->refresh()->status);
        $this->assertSame(2, (int) GroupUser::where('group_id', $group->id)->where('user_id', $incumbent->id)->value('role'));

        $this->assertSame('offer_pending', $vacancies->processOne($vacancy->id));
        $offer = $vacancy->refresh()->replacementOffer;
        $this->assertSame($replacement->id, (int) $offer->candidate_user_id);
        $this->assertSame('active', $appointment->refresh()->status);

        app(ElectionResponsibilityAcceptanceEvidenceService::class)
            ->confirm($offer, $replacement, (int) $offer->contract_version_id);
        app(ElectionResponsibilityOfferService::class)->accept($offer->refresh(), $replacement->id);
        $this->assertSame('filled', $vacancies->processOne($vacancy->id));

        $this->assertSame('revoked', $appointment->refresh()->status);
        $this->assertSame('filled', $vacancy->refresh()->status);
        $this->assertNotNull($vacancy->replacement_appointment_id);
        $this->assertDatabaseHas('election_appointments', [
            'id' => $vacancy->replacement_appointment_id,
            'user_id' => $replacement->id,
            'group_id' => $group->id,
            'position' => 'manager',
            'status' => 'active',
        ]);
    }

    private function publishManagerContract(User $actor): ElectionResponsibilityContractVersion
    {
        $clauses = array_fill_keys(ElectionResponsibilityContractVersion::REQUIRED_CLAUSES, 'متن کامل قرارداد آزمون جانشینی');

        return app(ElectionResponsibilityContractVersionService::class)
            ->publish('manager', $clauses, $actor, 'planned succession test contract');
    }
}
