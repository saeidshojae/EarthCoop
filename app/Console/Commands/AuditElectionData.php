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

        $requiredColumns = [
            ['votes', 'candidate_user_id'],
            ['votes', 'position'],
            ['candidates', 'acceptance_status'],
            ['elections', 'lifecycle_status'],
        ];

        foreach ($requiredColumns as [$table, $column]) {
            if (! Schema::hasColumn($table, $column)) {
                return $this->finish([
                    'schema_ready' => false,
                    'error' => "{$table}.{$column} is missing; run migrations first.",
                ], true);
            }
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
            'duplicate_candidate_memberships' => $this->duplicateCandidateMembershipCount(),
            'elections_missing_canonical_lifecycle' => DB::table('elections')
                ->whereNull('lifecycle_status')
                ->count(),
            'candidate_accept_status_raw' => $this->acceptanceHistogram(),
            'candidate_acceptance_status_canonical' => $this->canonicalAcceptanceHistogram(),
            'election_lifecycle_status_canonical' => $this->canonicalLifecycleHistogram(),
            'election_candidate_status_matrix' => $this->electionCandidateStatusMatrix(),
        ];

        $constraintBlockers = [
            'vote_candidate_fk' => $report['votes_unresolved_candidate_user'] + $report['votes_missing_candidate_user'],
            'vote_voter_fk' => $report['votes_missing_voter'],
            'vote_election_fk' => $report['votes_missing_election'],
            'candidate_user_fk' => $report['candidates_missing_user'],
            'candidate_election_fk' => $report['candidates_missing_election'],
            'unique_vote_key' => $report['duplicate_vote_keys'],
            'unique_candidate_membership' => $report['duplicate_candidate_memberships'],
        ];
        $report['constraint_blockers'] = $constraintBlockers;
        $report['hard_constraints_ready'] = collect($constraintBlockers)
            ->every(fn ($value) => (int) $value === 0);

        $hasIssues = ! $report['hard_constraints_ready']
            || $report['elections_missing_canonical_lifecycle'] > 0
            || $report['candidate_accept_status_raw']['unexpected'] > 0
            || $report['candidate_acceptance_status_canonical']['unexpected'] > 0
            || $report['election_lifecycle_status_canonical']['unexpected'] > 0;

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

    private function duplicateCandidateMembershipCount(): int
    {
        $query = DB::table('candidates')
            ->select('election_id', 'user_id')
            ->groupBy('election_id', 'user_id')
            ->havingRaw('COUNT(*) > 1');

        return DB::query()->fromSub($query, 'duplicate_candidate_memberships')->count();
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
            'text_pending' => 0,
            'text_accepted' => 0,
            'text_declined' => 0,
            'text_expired' => 0,
            'unexpected' => 0,
        ];

        foreach ($rows as $row) {
            $raw = $row->accept_status === null ? null : (string) $row->accept_status;
            $bucket = match ($raw) {
                null => 'null',
                '0' => 'raw_0_ambiguous',
                '1' => 'raw_1_pending',
                '2' => 'raw_2_accepted',
                'pending' => 'text_pending',
                'accepted' => 'text_accepted',
                'declined' => 'text_declined',
                'expired' => 'text_expired',
                default => 'unexpected',
            };
            $histogram[$bucket] += (int) $row->aggregate;
        }

        return $histogram;
    }

    private function canonicalAcceptanceHistogram(): array
    {
        $allowed = ['pending', 'accepted', 'declined', 'expired'];
        $rows = DB::table('candidates')
            ->select('acceptance_status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('acceptance_status')
            ->get();

        $histogram = ['null' => 0, 'pending' => 0, 'accepted' => 0, 'declined' => 0, 'expired' => 0, 'unexpected' => 0];
        foreach ($rows as $row) {
            $raw = $row->acceptance_status === null ? null : (string) $row->acceptance_status;
            $bucket = $raw === null ? 'null' : (in_array($raw, $allowed, true) ? $raw : 'unexpected');
            $histogram[$bucket] += (int) $row->aggregate;
        }

        return $histogram;
    }

    private function canonicalLifecycleHistogram(): array
    {
        $allowed = ['scheduled', 'open', 'closed', 'tallying', 'awaiting_acceptance', 'appointing', 'filled', 'exhausted', 'cancelled'];
        $rows = DB::table('elections')
            ->select('lifecycle_status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('lifecycle_status')
            ->get();

        $histogram = array_fill_keys(array_merge(['null'], $allowed, ['unexpected']), 0);
        foreach ($rows as $row) {
            $raw = $row->lifecycle_status === null ? null : (string) $row->lifecycle_status;
            $bucket = $raw === null ? 'null' : (in_array($raw, $allowed, true) ? $raw : 'unexpected');
            $histogram[$bucket] += (int) $row->aggregate;
        }

        return $histogram;
    }

    private function electionCandidateStatusMatrix(): array
    {
        $rows = DB::table('elections as e')
            ->join('candidates as c', 'c.election_id', '=', 'e.id')
            ->select('e.is_closed', 'e.lifecycle_status', 'c.accept_status', 'c.acceptance_status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('e.is_closed', 'e.lifecycle_status', 'c.accept_status', 'c.acceptance_status')
            ->orderBy('e.is_closed')
            ->get();

        return $rows->map(fn ($row) => [
            'election_is_closed' => (int) $row->is_closed,
            'election_lifecycle_status' => $row->lifecycle_status,
            'candidate_accept_status_raw' => $row->accept_status,
            'candidate_acceptance_status' => $row->acceptance_status,
            'count' => (int) $row->aggregate,
        ])->values()->all();
    }

    private function finish(array $report, bool $hasIssues): int
    {
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($report as $key => $value) {
                $this->line($key.': '.(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (is_bool($value) ? ($value ? 'true' : 'false') : (string) $value)));
            }
        }

        if ($hasIssues && $this->option('fail-on-issues')) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
