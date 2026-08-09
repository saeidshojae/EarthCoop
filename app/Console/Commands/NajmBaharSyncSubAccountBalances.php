<?php

namespace App\Console\Commands;

use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\AccountService;
use Illuminate\Console\Command;

class NajmBaharSyncSubAccountBalances extends Command
{
    protected $signature = 'najm-bahar:sync-subaccount-balances';

    protected $description = 'Sync legacy NajmBahar subaccount mirrors from canonical account records.';

    public function handle(AccountService $accountService): int
    {
        $systemAccount = $accountService->getSystemAccount();
        $subAccounts = SubAccount::where('account_id', $systemAccount->id)->get();

        foreach ($subAccounts as $subAccount) {
            $accountService->syncSubAccountFromAccount($subAccount);
        }

        $this->info('Subaccount mirrors synced through AccountService.');

        return self::SUCCESS;
    }
}
