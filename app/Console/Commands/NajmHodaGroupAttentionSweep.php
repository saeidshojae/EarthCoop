<?php

namespace App\Console\Commands;

use App\Models\NajmHodaGroupAttentionSetting;
use App\Services\NajmHoda\NajmHodaGroupAttentionDeliveryService;
use App\Services\NajmHoda\NajmHodaGroupAttentionEvaluatorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NajmHodaGroupAttentionSweep extends Command
{
    protected $signature = 'najm-hoda:group-attention-sweep {--max-groups=200 : Max enabled groups to process per run}';
    protected $description = 'Evaluate Najm Hoda group action queues and proactively deliver leadership attention alerts';

    public function handle(
        NajmHodaGroupAttentionEvaluatorService $evaluator,
        NajmHodaGroupAttentionDeliveryService $delivery
    ): int {
        if (! config('najm-hoda.enabled', true)) {
            $this->warn('Najm Hoda is disabled (NAJM_HODA_ENABLED=false).');
            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('max-groups'));
        $settings = NajmHodaGroupAttentionSetting::query()
            ->with('group')
            ->where('enabled', true)
            ->orderBy('group_id')
            ->limit($limit)
            ->get();

        $processed = 0;
        $events = 0;
        $resolved = 0;
        $deliveries = 0;
        $recipients = 0;
        $failures = 0;

        foreach ($settings as $setting) {
            $group = $setting->group;
            if (! $group) {
                continue;
            }

            try {
                $evaluation = $evaluator->evaluateGroup($group);
                $deliveryResult = $delivery->deliverGroup($group);

                $processed++;
                $events += (int) ($evaluation['events'] ?? 0);
                $resolved += (int) ($evaluation['resolved'] ?? 0);
                $deliveries += (int) ($deliveryResult['sent'] ?? 0);
                $recipients += (int) ($deliveryResult['recipients'] ?? 0);
            } catch (\Throwable $e) {
                $failures++;
                Log::error('Najm Hoda group attention sweep failed for group', [
                    'group_id' => (int) $group->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info('Najm Hoda group attention sweep completed.');
        $this->line("Processed groups: {$processed}");
        $this->line("Attention events seen: {$events}");
        $this->line("Resolved events: {$resolved}");
        $this->line("Notifications sent: {$deliveries}");
        $this->line("Recipients reached: {$recipients}");
        if ($failures > 0) {
            $this->warn("Failures: {$failures}");
        }

        return self::SUCCESS;
    }
}
