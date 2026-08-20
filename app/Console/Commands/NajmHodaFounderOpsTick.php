<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\FounderOps\FounderAutonomyTickService;
use Illuminate\Console\Command;

class NajmHodaFounderOpsTick extends Command
{
    protected $signature = 'najm-hoda:founder-ops-tick
        {--hours=24 : Founder attention window}
        {--limit=12 : Maximum attention items}
        {--plan-only : Never execute delegated-ready handlers}';

    protected $description = 'Run Founder Operations autonomous management tick';

    public function __construct(protected FounderAutonomyTickService $ticks)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $hours = max(1, min((int) $this->option('hours'), 168));
        $limit = max(1, min((int) $this->option('limit'), 50));
        $result = $this->ticks->run($hours, $limit, ! (bool) $this->option('plan-only'));

        $summary = (array) ($result['summary'] ?? []);
        $this->line(sprintf(
            'Founder Ops tick: planned=%d executed=%d planned_only=%d blocked=%d',
            (int) ($summary['planned'] ?? 0),
            (int) ($summary['executed'] ?? 0),
            (int) ($summary['planned_only'] ?? 0),
            (int) ($summary['blocked'] ?? 0)
        ));

        return self::SUCCESS;
    }
}
