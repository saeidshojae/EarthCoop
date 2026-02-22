<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\Runtime\NajmHodaOversightConsoleService;
use Illuminate\Console\Command;

class NajmHodaOversightConsole extends Command
{
    protected $signature = 'najm-hoda:oversight-console
        {--limit=50 : Snapshot item limit}
        {--json : Print full snapshot as json}';

    protected $description = 'Build human-oversight console snapshot (approvals, controls, audit, delegation, explainability)';

    public function __construct(
        protected NajmHodaOversightConsoleService $oversightService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!(bool) config('najm-hoda.enabled', true)) {
            $this->warn('Najm Hoda is disabled. Oversight snapshot skipped.');
            return self::SUCCESS;
        }

        $limit = is_numeric($this->option('limit')) ? (int) $this->option('limit') : 50;
        $snapshot = $this->oversightService->snapshot($limit);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->line('Najm Hoda Oversight Console Snapshot');
        $this->table(['Key', 'Value'], [
            ['generated_at', (string) ($snapshot['generated_at'] ?? '')],
            ['pending_approvals', (string) data_get($snapshot, 'approvals.pending_count', 0)],
            ['overdue_approvals', (string) data_get($snapshot, 'approvals.overdue_count', 0)],
            ['active_delegations', (string) data_get($snapshot, 'delegation.active_count', 0)],
            ['failed_runs', (string) data_get($snapshot, 'audit.failed_count', 0)],
            ['autonomy_events', (string) data_get($snapshot, 'events.recent_count', 0)],
        ]);

        $recommended = (array) ($snapshot['recommended_actions'] ?? []);
        if (!empty($recommended)) {
            $rows = array_map(static function (array $item): array {
                return [
                    (string) ($item['priority'] ?? ''),
                    (string) ($item['type'] ?? ''),
                    (string) ($item['action'] ?? ''),
                    (string) ($item['reason'] ?? ''),
                ];
            }, $recommended);
            $this->table(['Priority', 'Type', 'Action', 'Reason'], $rows);
        }

        return self::SUCCESS;
    }
}

