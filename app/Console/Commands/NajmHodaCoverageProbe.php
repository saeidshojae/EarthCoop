<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\Runtime\NajmHodaCoverageProbeService;
use Illuminate\Console\Command;

class NajmHodaCoverageProbe extends Command
{
    protected $signature = 'najm-hoda:coverage-probe
        {--dry-run : Do not emit probe events}';

    protected $description = 'Emit low-risk runtime probe events across critical coverage families';

    public function __construct(
        protected NajmHodaCoverageProbeService $probeService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!(bool) config('najm-hoda.enabled', true)) {
            $this->warn('Najm Hoda is disabled. Coverage probe skipped.');
            return self::SUCCESS;
        }

        if (!(bool) config('najm-hoda.runtime.coverage_kpi.probe.enabled', true)) {
            $this->warn('Coverage probe is disabled by config.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $events = $this->probeService->emit($dryRun);

        $this->line('Najm Hoda Coverage Probe');
        $this->table(
            ['Domain', 'Event', 'Action'],
            array_map(static fn (array $item): array => [
                (string) $item['domain'],
                (string) $item['event'],
                (string) $item['action'],
            ], $events)
        );

        return self::SUCCESS;
    }
}
