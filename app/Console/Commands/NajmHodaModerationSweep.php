<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\NajmHodaGroupAssistantService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NajmHodaModerationSweep extends Command
{
    protected $signature = 'najm-hoda:moderation-sweep {--max-groups=100 : Max groups to process per run}';
    protected $description = 'Run scheduled Najm Hoda group chat cleanup for irrelevant messages';

    public function handle(NajmHodaGroupAssistantService $assistant): int
    {
        if (!config('najm-hoda.enabled', true)) {
            Log::info('NajmHoda command skipped because system is disabled', [
                'command' => 'najm-hoda:moderation-sweep',
            ]);
            $this->warn('Najm Hoda is disabled (NAJM_HODA_ENABLED=false).');
            return self::SUCCESS;
        }

        $maxGroups = max(1, (int) $this->option('max-groups'));
        $result = $assistant->runScheduledModerationSweep($maxGroups);

        $this->info('Najm Hoda moderation sweep completed.');
        $this->line('Processed groups: ' . (int) ($result['processed_groups'] ?? 0));
        $this->line('Cleaned messages: ' . (int) ($result['cleaned_messages'] ?? 0));

        return self::SUCCESS;
    }
}
