<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Setting;

echo "=== Converting Existing Balances to Active/Faded ===\n\n";

// Get setting
$settings = Setting::first();
$activePercentage = (int) ($settings->najm_bahar_initial_active_percentage ?? 30);
echo "Active percentage: {$activePercentage}%\n\n";

// Find accounts needing conversion
$accounts = DB::table('najm_accounts')
    ->where('balance', '>', 0)
    ->where('balance_active', 0)
    ->where('balance_faded', 0)
    ->where('type', 'user')
    ->get();

echo "Accounts to convert: " . count($accounts) . "\n\n";

if (count($accounts) > 0) {
    foreach ($accounts as $account) {
        $balance = (int) $account->balance;
        
        // Calculate active and faded
        $activeAmount = intval(($balance * $activePercentage) / 100);
        $fadedAmount = $balance - $activeAmount;

        // Update
        DB::table('najm_accounts')
            ->where('id', $account->id)
            ->update([
                'balance_active' => $activeAmount,
                'balance_faded' => $fadedAmount,
            ]);

        echo "✓ Account {$account->account_number}:\n";
        echo "  Total: {$balance}\n";
        echo "  Active: {$activeAmount} ({$activePercentage}%)\n";
        echo "  Faded: {$fadedAmount}\n\n";
    }
    
    echo "\n✅ Conversion complete!\n";
} else {
    echo "No accounts need conversion.\n";
}
