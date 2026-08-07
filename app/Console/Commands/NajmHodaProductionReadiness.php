<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\Runtime\NajmHodaProductionReadinessService;
use Illuminate\Console\Command;

class NajmHodaProductionReadiness extends Command
{
    protected $signature = 'najm-hoda:production-readiness
        {--window=24 : Analysis window in hours}
        {--json : Print raw JSON report}
        {--strict : Return non-zero exit code unless decision is GO}';

    protected $description = 'Evaluate Najm Hoda production readiness and return a GO / CONDITIONAL GO / NO-GO decision';

    public function __construct(
        protected NajmHodaProductionReadinessService $readinessService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!(bool) config('najm-hoda.enabled', true)) {
            $this->error('Najm Hoda is disabled.');
            return self::FAILURE;
        }

        $window = is_numeric($this->option('window')) ? (int) $this->option('window') : 24;
        $window = max(1, min(720, $window));
        $report = $this->readinessService->review($window);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $decision = strtoupper(str_replace('_', ' ', (string) ($report['decision'] ?? 'no_go')));
            $this->line('Decision: ' . $decision);
            $this->line('Window: ' . $window . 'h');
            $this->line('Blockers: ' . (int) ($report['blocker_count'] ?? 0));
            $this->line('Warnings: ' . (int) ($report['warning_count'] ?? 0));

            $rows = [];
            foreach ((array) ($report['checks'] ?? []) as $name => $check) {
                if (!is_array($check)) {
                    continue;
                }
                $rows[] = [
                    (string) $name,
                    strtoupper((string) ($check['status'] ?? 'unknown')),
                    implode(', ', array_map('strval', (array) ($check['issues'] ?? []))),
                ];
            }

            if (!empty($rows)) {
                $this->table(['Check', 'Status', 'Issues'], $rows);
            }

            $hash = (string) data_get($report, 'evidence.integrity_hash', '');
            if ($hash !== '') {
                $this->line('Evidence hash: ' . $hash);
            }
        }

        $decision = (string) ($report['decision'] ?? 'no_go');
        if ((bool) $this->option('strict')) {
            return $decision === 'go' ? self::SUCCESS : self::FAILURE;
        }

        return $decision === 'no_go' ? self::FAILURE : self::SUCCESS;
    }
}
