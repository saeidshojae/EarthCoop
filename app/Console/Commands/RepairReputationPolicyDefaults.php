<?php

namespace App\Console\Commands;

use App\Models\ReputationRule;
use Illuminate\Console\Command;

class RepairReputationPolicyDefaults extends Command
{
    protected $signature = 'reputation:repair-policy-defaults
        {--dry-run : فقط تغییرات پیشنهادی را گزارش کن و داده‌ای تغییر نده}';

    protected $description = 'Repair only known stale/null reputation policy defaults without overwriting admin-authored values.';

    private const MISSING_CAP_DEFAULTS = [
        'post_created' => 50,
        'comment_created' => 20,
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $repairs = 0;

        foreach (self::MISSING_CAP_DEFAULTS as $key => $cap) {
            $query = ReputationRule::query()->where('key', $key)->whereNull('daily_cap');
            $count = $query->count();
            if ($count === 0) {
                continue;
            }

            $repairs += $count;
            $this->line(sprintf('%s: daily_cap NULL -> %d', $key, $cap));
            if (! $dryRun) {
                $query->update(['daily_cap' => $cap]);
            }
        }

        $this->info(sprintf(
            'Reputation policy repair: candidates=%d mode=%s',
            $repairs,
            $dryRun ? 'dry-run' : 'apply'
        ));

        return self::SUCCESS;
    }
}
