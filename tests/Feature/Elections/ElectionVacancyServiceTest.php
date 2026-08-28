<?php

namespace Tests\Feature\Elections;

use App\Models\Election;
use App\Models\ElectionAppointment;
use App\Models\ElectionPolicyVersion;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\ElectionResponsibilityOffer;
use App\Models\ElectionTallyResult;
use App\Models\ElectionVacancy;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Elections\ElectionAppointmentService;
use App\Services\Elections\ElectionResponsibilityAcceptanceEvidenceService;
use App\Services\Elections\ElectionResponsibilityContractVersionService;
use App\Services\Elections\ElectionResponsibilityOfferService;
use App\Services\Elections\ElectionVacancyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionVacancyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_revocation_creates_one_auditable_vacancy_and_inherited_revocation_does_not(): void
    {
        [$election, $direct] = $this->completedElectionFixture();

        app(ElectionAppointmentService::class)->revoke($direct, 'resigned', 'test_operator');

        $vacancy = ElectionVacancy::where('source_appointment_id', $direct->id)->firstOrFail();
        $this->assertSame('open', $vacancy->status);
        $this->assertSame('resigned', $vacancy->reason);
        $this->assertSame('test_operator', $vacancy->actor);
        $this->assertSame($election->id, $vacancy->election_id);

        app(ElectionAppointmentService::class)->revoke($direct, 'retry', 'retry');
        $this->assertSame(1, ElectionVacancy::where('source_appointment_id', $direct->id)->count());
    }

    public function test_vacancy_walks_same_cycle_ranking_after_decline_and_fills_without_new_election(): void
    {
        [$election, $direct, $rankTwo, $rankThree] = $this->completedElectionFixture();

        app(ElectionAppointmentService::class)->revoke($direct, 'resigned', 'test_operator');
        $vacancy = ElectionVacancy::where('source_appointment_id', $direct->id)->firstOrFail();
        $service = app(ElectionVacancyService::class);

        $this->assertSame('offer_pending', $service->processOne($vacancy->id));
        $firstReplacement = $vacancy->refresh()->replacementOffer;
        $this->assertSame($rankTwo->id, (int) $firstReplacement->candidate_user_id);
        $this->assertSame(2, (int) $firstReplacement->ranking_position);
        $this->assertSame('post_appointment_vacancy_ranked_backfill', $firstReplacement->resolution_reason);

        app(ElectionResponsibilityOfferService::class)->decline($firstReplacement, $rankTwo->id);

        $this->assertSame('offer_pending', $service->processOne($vacancy->id));
        $secondReplacement = $vacancy->refresh()->replacementOffer;
        $this->assertSame($rankThree->id, (int) $secondReplacement->candidate_user_id);
        $this->assertSame(3, (int) $secondReplacement->ranking_position);

        app(ElectionResponsibilityAcceptanceEvidenceService::class)
            ->confirm($secondReplacement, $rankThree, (int) $secondReplacement->contract_version_id);
        app(ElectionResponsibilityOfferService::class)->accept($secondReplacement->refresh(), $rankThree->id);
        $this->assertSame('filled', $service->processOne($vacancy->id));

        $vacancy->refresh();
        $this->assertSame('filled', $vacancy->status);
        $this->assertNotNull($vacancy->replacement_appointment_id);
        $this->assertSame('filled', $election->refresh()->lifecycle_status->value);
        $this->assertSame(1, Election::where('group_id', $election->group_id)->count());
        $this->assertDatabaseHas('election_appointments', [
            'id' => $vacancy->replacement_appointment_id,
            'user_id' => $rankThree->id,
            'group_id' => $election->group_id,
            'position' => 'manager',
            'appointment_kind' => 'direct',
            'status' => 'active',
        ]);
    }

    public function test_vacancy_backfill_uses_frozen_cycle_contract_and_response_window(): void
    {
        [$election, $direct, $rankTwo] = $this->completedElectionFixture();
        $setting = GroupSetting::where('level', 'global')->firstOrFail();
        $frozenContract = ElectionResponsibilityOffer::where('election_id', $election->id)
            ->where('candidate_user_id', $direct->user_id)
            ->firstOrFail()
            ->contractVersion;

        $policy = ElectionPolicyVersion::create([
            'group_setting_id' => $setting->id,
            'level_key' => 'global',
            'version' => 1,
            'election_status' => true,
            'manager_count' => 1,
            'inspector_count' => 0,
            'voting_duration_days' => 10,
            'start_threshold' => 1,
            'cycle_interval_months' => 3,
            'response_duration_days' => 4,
            'report_min_distinct_voters' => 10,
            'report_bucket_days' => 7,
            'meaningful_trend_min_net_change' => 3,
            'manager_contract_version_id' => $frozenContract->id,
            'effective_at' => now()->subMonths(2),
            'change_reason' => 'freeze vacancy contract fixture',
        ]);
        $election->forceFill(['policy_version_id' => $policy->id])->save();

        $newClauses = array_fill_keys(ElectionResponsibilityContractVersion::REQUIRED_CLAUSES, 'متن قرارداد جدید که نباید به چرخه قدیمی نشت کند');
        $newContract = app(ElectionResponsibilityContractVersionService::class)
            ->publish('manager', $newClauses, $rankTwo, 'newer global contract');
        $this->assertNotSame($frozenContract->id, $newContract->id);

        app(ElectionAppointmentService::class)->revoke($direct, 'resigned', 'test_operator');
        $vacancy = ElectionVacancy::where('source_appointment_id', $direct->id)->firstOrFail();
        $before = now();

        $this->assertSame('offer_pending', app(ElectionVacancyService::class)->processOne($vacancy->id));
        $replacement = $vacancy->refresh()->replacementOffer;

        $this->assertSame($rankTwo->id, (int) $replacement->candidate_user_id);
        $this->assertSame($frozenContract->id, (int) $replacement->contract_version_id);
        $this->assertSame($policy->id, (int) data_get($replacement->response_metadata, 'policy_version_id'));
        $this->assertSame(4, (int) data_get($replacement->response_metadata, 'response_duration_days'));
        $this->assertTrue((bool) data_get($replacement->response_metadata, 'contract_frozen_by_policy'));
        $this->assertTrue($replacement->expires_at->betweenIncluded($before->copy()->addDays(4)->subMinute(), now()->addDays(4)->addMinute()));
    }

    public function test_vacancy_exhaustion_is_recorded_without_forcing_early_full_cycle(): void
    {
        [$election, $direct, $rankTwo, $rankThree] = $this->completedElectionFixture();
        GroupUser::where('group_id', $election->group_id)
            ->whereIn('user_id', [$rankTwo->id, $rankThree->id])
            ->update(['status' => 0]);

        app(ElectionAppointmentService::class)->revoke($direct, 'resigned', 'test_operator');
        $vacancy = ElectionVacancy::where('source_appointment_id', $direct->id)->firstOrFail();

        $this->assertSame('exhausted', app(ElectionVacancyService::class)->processOne($vacancy->id));
        $this->assertSame('exhausted', $vacancy->refresh()->status);
        $this->assertNotNull($vacancy->resolved_at);
        $this->assertSame('filled', $election->refresh()->lifecycle_status->value);
        $this->assertSame(1, Election::where('group_id', $election->group_id)->count());
    }

    private function completedElectionFixture(): array
    {
        $group = Group::create([
            'name' => 'Global vacancy test group',
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
        $rankTwo = User::factory()->create(['is_system' => false]);
        $rankThree = User::factory()->create(['is_system' => false]);
        foreach ([$incumbent, $rankTwo, $rankThree] as $user) {
            GroupUser::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => $user->id === $incumbent->id ? 2 : 1,
                'status' => 1,
            ]);
        }

        $election = Election::create([
            'group_id' => $group->id,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
            'is_closed' => true,
            'lifecycle_status' => 'filled',
        ]);
        $clauses = array_fill_keys(ElectionResponsibilityContractVersion::REQUIRED_CLAUSES, 'متن کامل قرارداد آزمون جای‌خالی');
        $contract = app(ElectionResponsibilityContractVersionService::class)
            ->publish('manager', $clauses, $incumbent, 'vacancy test contract');
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

        foreach ([[$incumbent, 1], [$rankTwo, 2], [$rankThree, 3]] as [$user, $rank]) {
            ElectionTallyResult::create([
                'election_id' => $election->id,
                'candidate_user_id' => $user->id,
                'position' => 'manager',
                'vote_count' => 10 - $rank,
                'rank' => $rank,
                'within_seat_cutoff' => $rank === 1,
                'cycle_identifier' => 'vacancy-test-cycle',
                'stopped_at' => now()->subMonth(),
                'vote_snapshot_hash' => str_repeat('a', 64),
                'draw_seed_version' => 'vacancy-test-seed-v1',
                'draw_seed' => str_repeat('b', 64),
                'tie_break_version' => 'vacancy-test-tie-v1',
                'tie_break_key' => hash('sha256', 'vacancy-'.$rank),
                'tallied_at' => now()->subMonth(),
            ]);
        }

        $direct = ElectionAppointment::create([
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

        return [$election, $direct, $rankTwo, $rankThree];
    }
}
