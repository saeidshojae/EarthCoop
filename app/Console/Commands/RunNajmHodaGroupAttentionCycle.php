<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\NajmHodaGroupAttentionSetting;
use App\Services\NajmHoda\NajmHodaGroupAttentionDeliveryService;
use App\Services\NajmHoda\NajmHodaGroupAttentionEvaluatorService;
use Illuminate\Console\Command;

class RunNajmHodaGroupAttentionCycle extends Command
{
    protected $signature = 'najm-hoda:group-attention-cycle {--group= : Evaluate and deliver only one group ID}';

    protected $description = 'Evaluate enabled Najm Hoda group attention policies and deliver due managerial digests';

    public function handle(
        NajmHodaGroupAttentionEvaluatorService $evaluator,
        NajmHodaGroupAttentionDeliveryService $delivery
    ): int {
        $groupId = (int) ($this->option('group') ?: 0);

        $settings = NajmHodaGroupAttentionSetting::query()
            ->where('enabled', true)
            ->when($groupId > 0, fn ($query) => $query->where('group_id', $groupId))
            ->with('group')
            ->get();

        if ($groupId > 0 && $settings->isEmpty()) {
            $this->warn("Najm Hoda proactive attention is not enabled for group {$groupId}.");
            return self::SUCCESS;
        }

        $totals = [
            'groups' => 0,
            'items' => 0,
            'active_events' => 0,
            'resolved' => 0,
            'digests' => 0,
            'recipients' => 0,
        ];

        foreach ($settings as $setting) {
            /** @var Group|null $group */
            $group = $setting->group;
            if (! $group) {
                continue;
            }

            $evaluation = $evaluator->evaluateGroup($group);
            $delivered = $delivery->deliverGroup($group);

            $totals['groups']++;
            $totals['items'] += (int) $evaluation['evaluated'];
            $totals['active_events'] += (int) $evaluation['events'];
            $totals['resolved'] += (int) $evaluation['resolved'];
            $totals['digests'] += (int) $delivered['sent'];
            $totals['recipients'] += (int) $delivered['recipients'];
        }

        $this->info(sprintf(
            'Najm Hoda attention cycle: groups=%d items=%d active_events=%d resolved=%d digests=%d recipients=%d',
            $totals['groups'],
            $totals['items'],
            $totals['active_events'],
            $totals['resolved'],
            $totals['digests'],
            $totals['recipients']
        ));

        return self::SUCCESS;
    }
}
