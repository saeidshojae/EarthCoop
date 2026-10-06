<?php

namespace App\Console\Commands;

use App\Services\Communication\CommunicationCampaignService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class CommunicationProcessDueCampaigns extends Command
{
    protected $signature = 'communications:process-due-campaigns {--limit=100}';

    protected $description = 'Promote due scheduled communication campaigns to running state.';

    public function handle(CommunicationCampaignService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $processed = $service->processDueCampaigns(CarbonImmutable::now('UTC'), $limit);

        $this->info("Processed {$processed} due communication campaign(s).");

        return self::SUCCESS;
    }
}
