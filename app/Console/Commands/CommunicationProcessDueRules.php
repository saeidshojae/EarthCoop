<?php

namespace App\Console\Commands;

use App\Services\Communication\CommunicationScheduleService;
use Illuminate\Console\Command;

class CommunicationProcessDueRules extends Command
{
    protected $signature = 'communications:process-due';

    protected $description = 'Queue due Communication Center rule runs.';

    public function handle(CommunicationScheduleService $service): int
    {
        $processed = $service->processDue(now());
        $this->info("Processed {$processed} due communication rule(s).");

        return self::SUCCESS;
    }
}
