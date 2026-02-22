<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\Runtime\NajmHodaCoverageHeartbeatService;
use Illuminate\Console\Command;

class NajmHodaCoverageHeartbeat extends Command
{
    protected $signature = 'najm-hoda:coverage-heartbeat
        {--dry-run : Do not emit heartbeat events}';

    protected $description = 'Emit non-probe runtime heartbeat events for critical coverage families';

    public function __construct(
        protected NajmHodaCoverageHeartbeatService $heartbeatService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!(bool) config('najm-hoda.enabled', true)) {
            $this->warn('Najm Hoda is disabled. Coverage heartbeat skipped.');
            return self::SUCCESS;
        }

        if (!(bool) config('najm-hoda.runtime.coverage_kpi.heartbeat.enabled', true)) {
            $this->warn('Coverage heartbeat is disabled by config.');
            return self::SUCCESS;
        }

        $rows = $this->heartbeatService->emit((bool) $this->option('dry-run'));
        $this->line('Najm Hoda Coverage Heartbeat');
        $this->table(
            ['Domain', 'Event', 'Action'],
            array_map(static fn (array $item): array => [
                (string) $item['domain'],
                (string) $item['event'],
                (string) $item['action'],
            ], $rows)
        );

        return self::SUCCESS;
    }
}

