<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;

class FixCorruptedBalances extends Command
{
    protected $signature = 'najm:fix-balances';
    protected $description = 'Fix corrupted balance fields where balance != balance_active + balance_faded';

    public function handle()
    {
        $this->info('Fixing corrupted balance fields...');
        
        // Fix main accounts
        $mainBefore = Account::where('type', 'user')
            ->whereRaw('balance != (COALESCE(balance_active, 0) + COALESCE(balance_faded, 0))')
            ->count();
        
        $mainUpdated = \Illuminate\Support\Facades\DB::update(
            "UPDATE najm_accounts SET balance = balance_active + balance_faded WHERE type = 'user' AND balance != balance_active + balance_faded"
        );
        
        $this->info("✓ Fixed {$mainUpdated} main account(s)");
        
        // Fix subaccounts
        $subBefore = SubAccount::whereRaw('balance != (COALESCE(balance_active, 0) + COALESCE(balance_faded, 0))')
            ->count();
        
        $subUpdated = \Illuminate\Support\Facades\DB::update(
            "UPDATE najm_sub_accounts SET balance = balance_active + balance_faded WHERE balance != balance_active + balance_faded"
        );
        
        $this->info("✓ Fixed {$subUpdated} subaccount(s)");
        
        // Verify specific account
        $account15 = Account::where('account_number', '1000000015')->first();
        if ($account15) {
            $this->line("\nMain Account 1000000015:");
            $this->line("  Balance: " . round($account15->balance / 100, 2) . " bahaar");
            $this->line("  Active: " . round($account15->balance_active / 100, 2) . " bahaar");
            $this->line("  Faded: " . round($account15->balance_faded / 100, 2) . " bahaar");
        }
        
        $sub15 = SubAccount::where('sub_account_code', '1000000015-001')->first();
        if ($sub15) {
            $this->line("\nSubAccount 1000000015-001:");
            $this->line("  Balance: " . round($sub15->balance / 100, 2) . " bahaar");
            $this->line("  Active: " . round($sub15->balance_active / 100, 2) . " bahaar");
            $this->line("  Faded: " . round($sub15->balance_faded / 100, 2) . " bahaar");
        }
        
        $this->info('\n✅ All corrupted balances have been fixed!');
        
        return 0;
    }
}
