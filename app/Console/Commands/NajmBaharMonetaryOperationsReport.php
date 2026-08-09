<?php

namespace App\Console\Commands;

use App\Modules\NajmBahar\Services\MonetaryOperationsReportService;
use Illuminate\Console\Command;

class NajmBaharMonetaryOperationsReport extends Command
{
    protected $signature = 'najm-bahar:monetary-operations-report
        {--limit=100 : Maximum problem items to display}
        {--json : Emit machine-readable JSON instead of tables}';

    protected $description = 'Report failed and dead-letter Governance/Najm Bahar monetary operations requiring operator attention.';

    public function handle(MonetaryOperationsReportService $report): int
    {
        $limit = max(1, min((int) $this->option('limit'), 500));
        $summary = $report->summary();
        $items = $report->problemItems($limit);

        if ($this->option('json')) {
            $this->line(json_encode([
                'generated_at' => now()->toIso8601String(),
                'summary' => $summary,
                'items' => $items->all(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $this->info('Najm Bahar monetary operations requiring attention');
        $this->newLine();
        $this->table(
            ['Kind', 'Failed', 'Dead-letter'],
            collect($summary)->map(fn (array $counts, string $kind) => [
                $kind,
                (int) $counts['failed'],
                (int) $counts['dead_letter'],
            ])->values()->all()
        );

        if ($items->isEmpty()) {
            $this->newLine();
            $this->info('No failed or dead-letter monetary operations found.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['Kind', 'ID', 'Status', 'Attempts', 'Group', 'Reference', 'Last failure', 'Operator action', 'Error'],
            $items->map(fn (array $item) => [
                $item['kind'],
                $item['id'],
                $item['status'],
                $item['attempts'],
                $item['group_id'],
                $item['reference_id'],
                $item['last_failure_at'] ?: '-',
                $item['operator_action'],
                mb_strimwidth((string) ($item['error'] ?? ''), 0, 100, '…'),
            ])->all()
        );

        $this->newLine();
        $this->line('Recovery remains explicit; this report never retries or recovers money-moving operations by itself.');

        return self::SUCCESS;
    }
}
