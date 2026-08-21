<?php

namespace Tests\Feature\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Enums\Elections\ElectionPosition;
use App\Models\Election;
use App\Models\ElectionEligibilitySnapshot;
use App\Models\ElectionTallyResult;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\User;
use App\Models\Vote;
use App\Services\Elections\ElectionTallyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ElectionTallyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_input_always_produces_same_ranked_snapshot_with_verifiable_draw_evidence(): void
    {
        [$election, $voterA, $voterB, $candidateA, $candidateB, $candidateC] = $this->fixture();

        $this->vote($election, $voterA, $candidateA, ElectionPosition::Manager);
        $this->vote($election, $voterB, $candidateA, ElectionPosition::Manager);
        $this->vote($election, $voterA, $candidateB, ElectionPosition::Manager);
        $this->vote($election, $voterB, $candidateC, ElectionPosition::Manager);

        $service = app(ElectionTallyService::class);
        $fields = [
            'candidate_user_id', 'position', 'vote_count', 'rank',
            'within_seat_cutoff', 'cycle_identifier', 'stopped_at',
            'vote_snapshot_hash', 'draw_seed_version', 'draw_seed',
            'tie_break_version', 'tie_break_key',
        ];

        $first = $service->tally($election)->map->only($fields)->values()->all();
        $second = $service->tally($election->refresh())->map->only($fields)->values()->all();

        $this->assertSame($first, $second);
        $this->assertSame(6, ElectionTallyResult::where('election_id', $election->id)->count());

        $managerRows = ElectionTallyResult::where('election_id', $election->id)
            ->where('position', 'manager')
            ->orderBy('rank')
            ->get();

        $this->assertSame($candidateA->id, $managerRows[0]->candidate_user_id);
        $this->assertSame(2, $managerRows[0]->vote_count);
        $this->assertTrue($managerRows[0]->within_seat_cutoff);

        $evidence = $managerRows[0];
        $this->assertSame('election:'.$election->id, $evidence->cycle_identifier);
        $this->assertSame(ElectionTallyService::DRAW_SEED_VERSION, $evidence->draw_seed_version);
        $this->assertSame(ElectionTallyService::TIE_BREAK_VERSION, $evidence->tie_break_version);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $evidence->vote_snapshot_hash);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $evidence->draw_seed);

        $tied = $managerRows->where('vote_count', 1)->values();
        $this->assertCount(2, $tied);
        $this->assertLessThanOrEqual(0, strcmp($tied[0]->tie_break_key, $tied[1]->tie_break_key));
        $this->assertSame($evidence->draw_seed, $tied[0]->draw_seed);
        $this->assertSame('tallying', $election->refresh()->lifecycle_status->value);
    }

    public function test_tally_fails_closed_on_unresolved_legacy_vote_identity(): void
    {
        [$election, $voterA] = $this->fixture();

        Vote::create([
            'election_id' => $election->id,
            'voter_id' => $voterA->id,
            'candidate_id' => 999999,
            'candidate_user_id' => null,
            'position' => ElectionPosition::Manager->legacyVotePosition(),
        ]);

        $service = app(ElectionTallyService::class);

        $this->expectException(RuntimeException::class);
        try {
            $service->tally($election);
        } finally {
            $this->assertSame(0, ElectionTallyResult::where('election_id', $election->id)->count());
        }
    }

    public function test_tally_fails_closed_when_stop_time_cannot_be_proved(): void
    {
        [$election] = $this->fixture();
        $election->forceFill(['ends_at' => null])->save();

        $this->expectException(RuntimeException::class);
        app(ElectionTallyService::class)->tally($election->refresh());
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
            'is_closed' => true,
            'lifecycle_status' => ElectionLifecycleStatus::Closed,
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
