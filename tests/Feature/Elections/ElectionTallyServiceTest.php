<?php

namespace Tests\Feature\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Enums\Elections\ElectionPosition;
use App\Models\Election;
use App\Models\ElectionEligibilitySnapshot;
use App\Models\ElectionTallyResult;
use App\Models\ElectionVoteSnapshotRun;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\User;
use App\Models\Vote;
use App\Services\Elections\ElectionLifecycleService;
use App\Services\Elections\ElectionTallyService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ElectionTallyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_stop_snapshot_always_produces_same_ranked_result_with_verifiable_draw_evidence(): void
    {
        [$election, $voterA, $voterB, $candidateA, $candidateB, $candidateC] = $this->fixture();

        $this->vote($election, $voterA, $candidateA, ElectionPosition::Manager);
        $this->vote($election, $voterB, $candidateA, ElectionPosition::Manager);
        $this->vote($election, $voterA, $candidateB, ElectionPosition::Manager);
        $this->vote($election, $voterB, $candidateC, ElectionPosition::Manager);

        $election = app(ElectionLifecycleService::class)->transition(
            $election,
            ElectionLifecycleStatus::Closed,
            'test_stop',
            'test',
        );

        $run = ElectionVoteSnapshotRun::where('election_id', $election->id)->firstOrFail();
        $this->assertSame(4, $run->vote_count);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $run->snapshot_hash);

        $service = app(ElectionTallyService::class);
        $first = $this->normaliseTallyRows($service->tally($election));
        $second = $this->normaliseTallyRows($service->tally($election->refresh()));

        $this->assertSame($first, $second);
        $this->assertSame(6, ElectionTallyResult::where('election_id', $election->id)->count());

        $managerRows = ElectionTallyResult::where('election_id', $election->id)
            ->where('position', 'manager')->orderBy('rank')->get();
        $this->assertSame($candidateA->id, $managerRows[0]->candidate_user_id);
        $this->assertSame(2, $managerRows[0]->vote_count);
        $this->assertTrue($managerRows[0]->within_seat_cutoff);

        $evidence = $managerRows[0];
        $this->assertSame($run->cycle_identifier, $evidence->cycle_identifier);
        $this->assertSame($run->snapshot_hash, $evidence->vote_snapshot_hash);
        $this->assertSame(
            $run->stopped_at->copy()->utc()->format('Y-m-d\\TH:i:s.u\\Z'),
            $evidence->stopped_at->copy()->utc()->format('Y-m-d\\TH:i:s.u\\Z'),
        );
        $this->assertSame(ElectionTallyService::DRAW_SEED_VERSION, $evidence->draw_seed_version);
        $this->assertSame(ElectionTallyService::TIE_BREAK_VERSION, $evidence->tie_break_version);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $evidence->draw_seed);
        $this->assertSame('tallying', $election->refresh()->lifecycle_status->value);
    }

    public function test_votes_written_after_stop_do_not_change_tally_for_the_stopped_snapshot(): void
    {
        [$election, $voterA, $voterB, $candidateA, $candidateB] = $this->fixture();
        $this->vote($election, $voterA, $candidateA, ElectionPosition::Manager);

        $election = app(ElectionLifecycleService::class)->transition(
            $election,
            ElectionLifecycleStatus::Closed,
            'test_stop',
            'test',
        );

        $this->vote($election, $voterB, $candidateB, ElectionPosition::Manager);

        $rows = app(ElectionTallyService::class)->tally($election);
        $managerB = $rows->first(fn ($row) => $row->position === 'manager' && $row->candidate_user_id === $candidateB->id);

        $this->assertNotNull($managerB);
        $this->assertSame(0, $managerB->vote_count);
        $this->assertSame(1, ElectionVoteSnapshotRun::where('election_id', $election->id)->value('vote_count'));
    }

    public function test_stop_snapshot_fails_closed_on_unresolved_legacy_vote_identity(): void
    {
        [$election, $voterA] = $this->fixture();
        Vote::create([
            'election_id' => $election->id,
            'voter_id' => $voterA->id,
            'candidate_id' => 999999,
            'candidate_user_id' => null,
            'position' => ElectionPosition::Manager->legacyVotePosition(),
        ]);

        $this->expectException(RuntimeException::class);
        try {
            app(ElectionLifecycleService::class)->transition(
                $election,
                ElectionLifecycleStatus::Closed,
                'test_stop',
                'test',
            );
        } finally {
            $this->assertSame(0, ElectionVoteSnapshotRun::where('election_id', $election->id)->count());
            $this->assertSame('open', $election->refresh()->lifecycle_status->value);
        }
    }

    private function normaliseTallyRows($rows): array
    {
        return $rows->map(function ($row): array {
            $stoppedAt = $row->stopped_at;
            $canonicalStoppedAt = $stoppedAt instanceof CarbonInterface
                ? $stoppedAt->copy()->utc()->format('Y-m-d\\TH:i:s.u\\Z')
                : (string) $stoppedAt;

            return [
                'candidate_user_id' => (int) $row->candidate_user_id,
                'position' => (string) $row->position,
                'vote_count' => (int) $row->vote_count,
                'rank' => (int) $row->rank,
                'within_seat_cutoff' => (bool) $row->within_seat_cutoff,
                'cycle_identifier' => (string) $row->cycle_identifier,
                'stopped_at' => $canonicalStoppedAt,
                'vote_snapshot_hash' => (string) $row->vote_snapshot_hash,
                'draw_seed_version' => (string) $row->draw_seed_version,
                'draw_seed' => (string) $row->draw_seed,
                'tie_break_version' => (string) $row->tie_break_version,
                'tie_break_key' => (string) $row->tie_break_key,
            ];
        })->values()->all();
    }

    private function fixture(): array
    {
        $group = Group::create([
            'name' => 'Deterministic tally group',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);

        GroupSetting::create([
            'level' => 'neighborhood',
            'manager_count' => 2,
            'inspector_count' => 1,
            'election_time' => 10,
            'max_for_election' => 1,
            'election_status' => 1,
        ]);

        $election = Election::create([
            'group_id' => $group->id,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subMinute(),
            'is_closed' => false,
            'lifecycle_status' => ElectionLifecycleStatus::Open,
            'eligibility_snapshot_captured_at' => now()->subDays(10),
            'eligibility_snapshot_version' => 1,
        ]);

        $voterA = User::factory()->create();
        $voterB = User::factory()->create();
        $candidateA = User::factory()->create();
        $candidateB = User::factory()->create();
        $candidateC = User::factory()->create();

        foreach ([$voterA, $voterB, $candidateA, $candidateB, $candidateC] as $user) {
            ElectionEligibilitySnapshot::create([
                'election_id' => $election->id,
                'user_id' => $user->id,
                'voter_eligible' => true,
                'selectable_eligible' => in_array($user->id, [$candidateA->id, $candidateB->id, $candidateC->id], true),
                'membership_role' => 1,
                'membership_status' => 1,
                'snapshot_version' => 1,
                'captured_at' => now()->subDays(10),
            ]);
        }

        return [$election, $voterA, $voterB, $candidateA, $candidateB, $candidateC];
    }

    private function vote(Election $election, User $voter, User $candidate, ElectionPosition $position): void
    {
        Vote::create([
            'election_id' => $election->id,
            'voter_id' => $voter->id,
            'candidate_id' => $candidate->id,
            'candidate_user_id' => $candidate->id,
            'position' => $position->legacyVotePosition(),
        ]);
    }
}
