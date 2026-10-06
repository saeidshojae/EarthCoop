<?php

namespace App\Console\Commands;

use App\Services\Communication\CommunicationScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class CommunicationProcessDueRules extends Command
{
    protected $signature = 'communications:process-due';

    protected $description = 'Queue due Communication Center rule runs.';

    public function handle(CommunicationScheduleService $service): int
    {
        $processed = $service->processDue(CarbonImmutable::now('UTC'));
        $this->info("Processed {$processed} due communication rule(s).");

        return self::SUCCESS;
    }
}
