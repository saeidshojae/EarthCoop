<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\NajmBahar\Models\Account;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

echo "=== Checking Existing Accounts ===\n\n";

// Get all user accounts
$accounts = Account::where('type', 'user')->get();
echo "Total user accounts: " . $accounts->count() . "\n\n";

// Find accounts with balance but no active/faded split
$needsMigration = $accounts->filter(function($account) {
    return $account->balance > 0 && ($account->balance_active == 0 && $account->balance_faded == 0);
});

echo "Accounts needing migration: " . $needsMigration->count() . "\n";

if ($needsMigration->count() > 0) {
    echo "\n=== Sample accounts needing migration: ===\n";
    foreach ($needsMigration->take(5) as $account) {
        echo "Account #{$account->account_number}: balance={$account->balance}, active={$account->balance_active}, faded={$account->balance_faded}\n";
    }
    
    echo "\n=== Migration Options ===\n";
    echo "1. Convert all balance to FADED (default behavior)\n";
    echo "2. Split using current setting percentage\n";
    echo "3. Keep as-is (balance remains, active/faded stay 0)\n";
    
    echo "\nRecommendation: Option 2 - Split using setting percentage\n";
    
    // Get current setting
    $settings = Setting::first();
    $percentage = $settings->najm_bahar_initial_active_percentage ?? 30;
    echo "Current active percentage: {$percentage}%\n";
}
