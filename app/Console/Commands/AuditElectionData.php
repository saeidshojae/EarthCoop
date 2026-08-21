<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditElectionData extends Command
{
    protected $signature = 'elections:audit-data
        {--json : Emit machine-readable JSON}
        {--fail-on-issues : Exit non-zero when unsafe structural records exist}';

    protected $description = 'Audit legacy election identity and raw lifecycle data without modifying records';

    public function handle(): int
    {
        if (! Schema::hasTable('votes') || ! Schema::hasTable('candidates') || ! Schema::hasTable('elections')) {
            return $this->finish([
                'schema_ready' => false,
                'error' => 'Election tables are missing.',
            ], true);
        }

        if (! Schema::hasColumn('votes', 'candidate_user_id')) {
            return $this->finish([
                'schema_ready' => false,
                'error' => 'votes.candidate_user_id is missing; run migrations first.',
            ], true);
        }

        $report = [
            'schema_ready' => true,
            'votes_total' => DB::table('votes')->count(),
            'votes_unresolved_candidate_user' => DB::table('votes')
                ->whereNull('candidate_user_id')
                ->count(),
            'votes_missing_candidate_user' => DB::table('votes as v')
                ->leftJoin('users as u', 'u.id', '=', 'v.candidate_user_id')
                ->whereNotNull('v.candidate_user_id')
                ->whereNull('u.id')
                ->count(),
            'votes_missing_voter' => DB::table('votes as v')
                ->leftJoin('users as u', 'u.id', '=', 'v.voter_id')
                ->whereNull('u.id')
                ->count(),
            'votes_missing_election' => DB::table('votes as v')
                ->leftJoin('elections as e', 'e.id', '=', 'v.election_id')
                ->whereNull('e.id')
                ->count(),
            'duplicate_vote_keys' => $this->duplicateVoteKeyCount(),
            'candidates_missing_user' => DB::table('candidates as c')
                ->leftJoin('users as u', 'u.id', '=', 'c.user_id')
                ->whereNull('u.id')
                ->count(),
            'candidates_missing_election' => DB::table('candidates as c')
                ->leftJoin('elections as e', 'e.id', '=', 'c.election_id')
                ->whereNull('e.id')
                ->count(),
            // E2 deliberately reports raw values. Legacy zero is ambiguous and
            // lifecycle interpretation belongs to the canonical state machine.
            'candidate_accept_status_raw' => $this->acceptanceHistogram(),
            'election_candidate_status_matrix' => $this->electionCandidateStatusMatrix(),
        ];

        $hasIssues = collect([
            $report['votes_unresolved_candidate_user'],
            $report['votes_missing_candidate_user'],
            $report['votes_missing_voter'],
            $report['votes_missing_election'],
            $report['duplicate_vote_keys'],
            $report['candidates_missing_user'],
            $report['candidates_missing_election'],
            $report['candidate_accept_status_raw']['unexpected'],
        ])->contains(fn ($value) => (int) $value > 0);

        return $this->finish($report, $hasIssues);
    }

    private function duplicateVoteKeyCount(): int
    {
        $query = DB::table('votes')
            ->select('election_id', 'voter_id', 'candidate_user_id', 'position')
            ->whereNotNull('candidate_user_id')
            ->groupBy('election_id', 'voter_id', 'candidate_user_id', 'position')
            ->havingRaw('COUNT(*) > 1');

        return DB::query()->fromSub($query, 'duplicate_vote_keys')->count();
    }

    private function acceptanceHistogram(): array
    {
        $rows = DB::table('candidates')
            ->select('accept_status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('accept_status')
            ->get();

        $histogram = [
            'null' => 0,
            'raw_0_ambiguous' => 0,
            'raw_1_pending' => 0,
            'raw_2_accepted' => 0,
            'unexpected' => 0,
        ];

        foreach ($rows as $row) {
            if ($row->accept_status === null) {
                $histogram['null'] += (int) $row->aggregate;
            } elseif ((string) $row->accept_status === '0') {
                $histogram['raw_0_ambiguous'] += (int) $row->aggregate;
            } elseif ((string) $row->accept_status === '1') {
                $histogram['raw_1_pending'] += (int) $row->aggregate;
            } elseif ((string) $row->accept_status === '2') {
                $histogram['raw_2_accepted'] += (int) $row->aggregate;
            } else {
                $histogram['unexpected'] += (int) $row->aggregate;
            }
        }

        return $histogram;
    }

    private function electionCandidateStatusMatrix(): array
    {
        $rows = DB::table('elections as e')
            ->join('candidates as c', 'c.election_id', '=', 'e.id')
            ->select('e.is_closed', 'c.accept_status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('e.is_closed', 'c.accept_status')
            ->orderBy('e.is_closed')
            ->get();

        return $rows->map(fn ($row) => [
            'election_is_closed' => (int) $row->is_closed,
            'candidate_accept_status' => $row->accept_status === null ? null : (int) $row->accept_status,
            'count' => (int) $row->aggregate,
        ])->values()->all();
    }

    private function finish(array $report, bool $hasIssues): int
    {
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($report as $key => $value) {
                $this->line($key.': '.(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value));
            }
        }

        if ($hasIssues && $this->option('fail-on-issues')) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
