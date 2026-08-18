<?php

namespace App\Console\Commands;

use App\Modules\NajmBahar\Services\ProductionReadinessAuditService;
use Illuminate\Console\Command;

class NajmBaharProductionReadinessAudit extends Command
{
    protected $signature = 'najm-bahar:production-readiness {--json : Emit machine-readable JSON}';

    protected $description = 'Audit Najm Bahar account invariants and completed-transaction ledger consistency.';

    public function handle(ProductionReadinessAuditService $audit): int
    {
        $result = $audit->run();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->info('Najm Bahar production-readiness audit');
            $this->line('Accounts checked: ' . $result['accounts_checked']);
            $this->line('Completed transactions checked: ' . $result['completed_transactions_checked']);
            $this->line('Account failures: ' . count($result['account_failures']));
            $this->line('Ledger failures: ' . count($result['ledger_failures']));

            if (! $result['ok']) {
                $this->error('Production readiness audit FAILED.');
            } else {
                $this->info('Production readiness audit PASSED.');
            }
        }

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
