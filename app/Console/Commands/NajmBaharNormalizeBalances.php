<?php

namespace App\Console\Commands;

use App\Modules\NajmBahar\Services\BalanceNormalizationService;
use Illuminate\Console\Command;

class NajmBaharNormalizeBalances extends Command
{
    protected $signature = 'najm-bahar:normalize-balances {--apply : Apply cached-total normalization. Without this flag the command is read-only.}';

    protected $description = 'Normalize legacy Account/SubAccount balance caches to local active+dim totals.';

    public function handle(BalanceNormalizationService $service): int
    {
        $plan = $service->plan();

        $this->info('Account rows requiring normalization: ' . $plan['account_change_count']);
        $this->info('Sub-account rows requiring normalization: ' . $plan['subaccount_change_count']);

        foreach (array_slice($plan['accounts'], 0, 25) as $change) {
            $this->line(sprintf('Account %s: %d -> %d', $change['account_number'], $change['stored'], $change['expected']));
        }

        foreach (array_slice($plan['subaccounts'], 0, 25) as $change) {
            $this->line(sprintf('SubAccount %s: %d -> %d', $change['sub_account_code'], $change['stored'], $change['expected']));
        }

        if (! $this->option('apply')) {
            $this->warn('Dry-run only. Re-run with --apply to write cached totals. Active/dim buckets will never be modified.');
            return self::SUCCESS;
        }

        $service->apply();
        $this->info('Normalization applied successfully.');

        return self::SUCCESS;
    }
}
