<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\NajmHodaGroupAttentionSetting;
use App\Services\NajmHoda\NajmHodaGroupAttentionEvaluatorService;
use Illuminate\Console\Command;

class NajmHodaEvaluateGroupAttention extends Command
{
    protected $signature = 'najm-hoda:evaluate-group-attention {--group=}';
    protected $description = 'Evaluate enabled group action queues and persist deduplicated Najm Hoda attention events';

    public function handle(NajmHodaGroupAttentionEvaluatorService $evaluator): int
    {
        $groupId = (int) ($this->option('group') ?: 0);

        $query = NajmHodaGroupAttentionSetting::query()->where('enabled', true);
        if ($groupId > 0) {
            $query->where('group_id', $groupId);
        }

        $settings = $query->get(['group_id']);
        $totals = ['groups' => 0, 'evaluated' => 0, 'events' => 0, 'resolved' => 0];

        foreach ($settings as $setting) {
            $group = Group::query()->find($setting->group_id);
            if (! $group) continue;
            $result = $evaluator->evaluateGroup($group);
            $totals['groups']++;
            $totals['evaluated'] += $result['evaluated'];
            $totals['events'] += $result['events'];
            $totals['resolved'] += $result['resolved'];
        }

        $this->info(sprintf(
            'Najm Hoda attention evaluation: groups=%d items=%d active_events=%d resolved=%d',
            $totals['groups'], $totals['evaluated'], $totals['events'], $totals['resolved']
        ));

        return self::SUCCESS;
    }
}
