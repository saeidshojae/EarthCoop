<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\Runtime\NajmHodaSafeCodeOpsCanaryService;
use Illuminate\Console\Command;

class NajmHodaCodeOpsCanary extends Command
{
    protected $signature = 'najm-hoda:codeops-canary
        {--start : Start a new canary rollout}
        {--promote : Promote canary to next phase}
        {--evaluate : Evaluate current canary health}
        {--rollback : Force rollback of active canary}
        {--auto-rollback : Auto rollback on SLO breach during evaluation}
        {--status : Print current canary status}
        {--history=0 : Show latest N history rows}
        {--window=24 : Evaluation window in hours}
        {--phases= : Comma-separated canary phases, e.g. 5,25,50,100}
        {--reason= : Optional reason for operation}';

    protected $description = 'Run Safe CodeOps canary rollout with SLO-based auto rollback';

    public function __construct(
        protected NajmHodaSafeCodeOpsCanaryService $canaryService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!(bool) config('najm-hoda.enabled', true)) {
            $this->warn('Najm Hoda is disabled. CodeOps canary skipped.');
            return self::SUCCESS;
        }

        $history = (int) $this->option('history');
        if ($history > 0) {
            $rows = $this->canaryService->history($history);
            if (empty($rows)) {
                $this->line('No canary history found.');
                return self::SUCCESS;
            }

            $this->table(
                ['Timestamp', 'Type', 'Rollout', 'Phase', 'Health'],
                array_map(static function (array $row): array {
                    return [
                        (string) ($row['timestamp'] ?? '-'),
                        (string) ($row['type'] ?? '-'),
                        (string) ($row['rollout_id'] ?? '-'),
                        (string) ($row['phase_percent'] ?? '-'),
                        (string) ($row['health_status'] ?? '-'),
                    ];
                }, $rows)
            );

            return self::SUCCESS;
        }

        if ((bool) $this->option('status')) {
            return $this->printStatus();
        }

        $window = is_numeric($this->option('window')) ? (int) $this->option('window') : 24;
        $reason = $this->option('reason') !== null ? (string) $this->option('reason') : null;

        if ((bool) $this->option('start')) {
            $phases = $this->parsePhases((string) ($this->option('phases') ?? ''));
            $result = $this->canaryService->startCanary(auth()->id(), $reason, $phases, $window);
            if (!(bool) ($result['success'] ?? false)) {
                $this->warn('Start failed: ' . (string) ($result['reason'] ?? 'unknown'));
                return self::FAILURE;
            }
            $this->info('Canary rollout started.');
            return $this->printStatus();
        }

        if ((bool) $this->option('promote')) {
            $result = $this->canaryService->promote(auth()->id(), $reason);
            if (!(bool) ($result['success'] ?? false)) {
                $this->warn('Promote failed: ' . (string) ($result['reason'] ?? 'unknown'));
                if (is_array($result['health'] ?? null)) {
                    $this->line('Health status: ' . (string) data_get($result, 'health.status', 'unknown'));
                }
                return self::FAILURE;
            }
            $this->info('Canary promoted.');
            return $this->printStatus();
        }

        if ((bool) $this->option('rollback')) {
            $result = $this->canaryService->rollback(auth()->id(), $reason ?: 'manual_codeops_rollback');
            if (!(bool) ($result['success'] ?? false)) {
                $this->warn('Rollback failed: ' . (string) ($result['reason'] ?? 'unknown'));
                return self::FAILURE;
            }
            $this->info('Canary rollback executed.');
            return $this->printStatus();
        }

        if ((bool) $this->option('evaluate') || (bool) $this->option('auto-rollback')) {
            $result = $this->canaryService->evaluate((bool) $this->option('auto-rollback'), auth()->id(), $reason);
            $this->line('Health status: ' . (string) data_get($result, 'health.status', 'unknown'));
            $this->line('Active: ' . ((bool) ($result['active'] ?? false) ? 'yes' : 'no'));
            return $this->printStatus();
        }

        $this->line('No operation selected. Use --status, --start, --promote, --evaluate or --rollback.');
        return self::SUCCESS;
    }

    protected function printStatus(): int
    {
        $state = $this->canaryService->status();
        $health = is_array($state['last_health'] ?? null) ? $state['last_health'] : [];

        $this->table(['Key', 'Value'], [
            ['rollout_id', (string) ($state['rollout_id'] ?? '')],
            ['status', (string) ($state['status'] ?? 'idle')],
            ['phase_percent', (string) ($state['phase_percent'] ?? '')],
            ['window_hours', (string) ($state['window_hours'] ?? 24)],
            ['health_status', (string) ($health['status'] ?? 'unknown')],
            ['breach_count', (string) ($health['breach_count'] ?? 0)],
            ['warning_count', (string) ($health['warning_count'] ?? 0)],
            ['updated_at', (string) ($state['updated_at'] ?? '')],
        ]);

        return self::SUCCESS;
    }

    /**
     * @return array<int, int>|null
     */
    protected function parsePhases(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $rows = array_values(array_filter(array_map(
            static fn ($item): string => trim((string) $item),
            explode(',', $raw)
        ), static fn (string $item): bool => $item !== ''));

        if (empty($rows)) {
            return null;
        }

        $result = [];
        foreach ($rows as $item) {
            if (!is_numeric($item)) {
                continue;
            }
            $result[] = (int) $item;
        }

        return empty($result) ? null : $result;
    }
}

