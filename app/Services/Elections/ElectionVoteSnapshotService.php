<?php

namespace App\Services\Elections;

use App\Models\Election;
use App\Models\ElectionVoteSnapshotEntry;
use App\Models\ElectionVoteSnapshotRun;
use App\Models\Vote;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ElectionVoteSnapshotService
{
    public function capture(Election $election, CarbonInterface $stoppedAt): ElectionVoteSnapshotRun
    {
        return DB::transaction(function () use ($election, $stoppedAt): ElectionVoteSnapshotRun {
            $existing = ElectionVoteSnapshotRun::query()
                ->where('election_id', $election->id)
                ->where('snapshot_version', 1)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $votes = Vote::query()
                ->where('election_id', $election->id)
                ->orderBy('voter_id')
                ->orderBy('candidate_user_id')
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($votes->contains(fn (Vote $vote) => $vote->candidate_user_id === null)) {
                throw new RuntimeException('Election contains unresolved legacy vote identity; stop snapshot is fail-closed.');
            }

            $canonicalRows = $votes->map(fn (Vote $vote) => [
                'voter_id' => (int) $vote->voter_id,
                'candidate_user_id' => (int) $vote->candidate_user_id,
                'position' => (string) $vote->position,
            ])->values();

            $canonical = $canonicalRows
                ->map(fn (array $row) => implode(':', [$row['voter_id'], $row['candidate_user_id'], $row['position']]))
                ->implode('|');

            $run = ElectionVoteSnapshotRun::create([
                'election_id' => $election->id,
                'snapshot_version' => 1,
                'cycle_identifier' => 'election:'.$election->id,
                'stopped_at' => $stoppedAt,
                'snapshot_hash' => hash('sha256', $canonical),
                'vote_count' => $canonicalRows->count(),
            ]);

            foreach ($canonicalRows as $row) {
                ElectionVoteSnapshotEntry::create([
                    'snapshot_run_id' => $run->id,
                    'election_id' => $election->id,
                    'voter_id' => $row['voter_id'],
                    'candidate_user_id' => $row['candidate_user_id'],
                    'position' => $row['position'],
                ]);
            }

            return $run->refresh();
        }, 3);
    }
}
