<?php

namespace App\Console\Commands;

use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\AccountService;
use Illuminate\Console\Command;

class NajmBaharSyncSubAccountBalances extends Command
{
    protected $signature = 'najm-bahar:sync-subaccount-balances';

    protected $description = 'Sync NajmBahar subaccount balances from their account records.';

    public function handle(AccountService $accountService): int
    {
        $systemAccount = $accountService->getSystemAccount();

        $subAccounts = SubAccount::where('account_id', $systemAccount->id)->get();

        foreach ($subAccounts as $subAccount) {
            $account = $accountService->ensureSubAccountAccount($subAccount);
            if ($subAccount->balance !== $account->balance) {
                $subAccount->balance = $account->balance;
                $subAccount->save();
            }
        }

        $this->info('Subaccount balances synced.');

        return self::SUCCESS;
    }
}
