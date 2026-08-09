<?php

namespace App\Console\Commands;

use App\Modules\NajmBahar\Services\AccountInvariantService;
use Illuminate\Console\Command;

class NajmBaharAuditBalances extends Command
{
    protected $signature = 'najm-bahar:audit-balances {--only-problems : Show only inconsistent or drifted accounts}';

    protected $description = 'Read-only audit of Najm Bahar balance semantics and sub-account mirror consistency';

    public function handle(AccountInvariantService $auditor): int
    {
        $reports = $auditor->auditAllMainAccounts();
        $onlyProblems = (bool) $this->option('only-problems');

        $rows = [];
        $problemCount = 0;

        foreach ($reports as $report) {
            if (! $report['is_clean']) {
                $problemCount++;
            }

            if ($onlyProblems && $report['is_clean']) {
                continue;
            }

            $rows[] = [
                $report['account_id'],
                $report['account_number'],
                $report['type'],
                $report['balance_semantics'],
                $report['stored_balance'],
                $report['own_total'],
                $report['child_total'],
                $report['aggregate_total'],
                count($report['mirror_drift']),
            ];
        }

        $this->table([
            'ID',
            'Account',
            'Type',
            'Semantics',
            'Stored',
            'Own',
            'Children',
            'Aggregate',
            'Mirror drift',
        ], $rows);

        $this->info("Audited {$reports->count()} main accounts. {$problemCount} account(s) require normalization review.");
        $this->comment('This command is read-only and never changes balances.');

        return self::SUCCESS;
    }
}
