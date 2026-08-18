<?php

namespace App\Console\Commands;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\AccountBalanceService;
use Illuminate\Console\Command;

class NajmBaharPlanBalanceNormalization extends Command
{
    protected $signature = 'najm-bahar:plan-balance-normalization {--only-changes : Hide rows already using canonical local totals}';

    protected $description = 'Read-only plan for normalizing Account.balance to canonical local totals';

    public function handle(AccountBalanceService $balances): int
    {
        $rows = [];
        $changed = 0;

        Account::query()->orderBy('id')->chunkById(500, function ($accounts) use ($balances, &$rows, &$changed) {
            foreach ($accounts as $account) {
                $expected = $balances->expectedStoredLocalTotal($account);
                $stored = (int) ($account->balance ?? 0);
                $delta = $expected - $stored;

                if ($delta !== 0) {
                    $changed++;
                }

                if ($this->option('only-changes') && $delta === 0) {
                    continue;
                }

                $rows[] = [
                    $account->id,
                    $account->account_number,
                    $account->type,
                    $stored,
                    $expected,
                    $delta,
                ];
            }
        });

        $subDrift = 0;
        SubAccount::query()->orderBy('id')->chunkById(500, function ($subs) use (&$subDrift) {
            foreach ($subs as $sub) {
                $expected = (int) ($sub->balance_active ?? 0) + (int) ($sub->balance_faded ?? 0);
                $mirror = Account::where('account_number', $sub->sub_account_code)->first();

                if ((int) ($sub->balance ?? 0) !== $expected
                    || ! $mirror
                    || (int) ($mirror->balance ?? 0) !== $expected
                    || (int) ($mirror->balance_active ?? 0) !== (int) ($sub->balance_active ?? 0)
                    || (int) ($mirror->balance_faded ?? 0) !== (int) ($sub->balance_faded ?? 0)) {
                    $subDrift++;
                }
            }
        });

        $this->table(['ID', 'Account', 'Type', 'Stored balance', 'Canonical local', 'Delta'], $rows);
        $this->info("{$changed} account row(s) would change if local-balance normalization is later applied.");
        $this->info("{$subDrift} sub-account mirror row(s) require synchronization review.");
        $this->comment('Read-only: this command never writes balances. An apply command will only be introduced after all aggregate reads are migrated.');

        return self::SUCCESS;
    }
}
