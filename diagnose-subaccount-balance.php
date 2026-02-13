<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Http\Kernel::class)->handle(
    $request = \Illuminate\Http\Request::capture()
);

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\LedgerEntry;

// Get the latest subaccount with balance issue
$subAccount = SubAccount::latest('id')->first();

if (!$subAccount) {
    echo "No subaccount found!\n";
    exit;
}

echo "=== SubAccount Balance Issue ===\n";
echo "Account Code: {$subAccount->sub_account_code}\n";
echo "Balance (Total): {$subAccount->balance} (" . ($subAccount->balance / 100) . " bahaar)\n";
echo "Balance Active: {$subAccount->balance_active} (" . ($subAccount->balance_active / 100) . " bahaar)\n";
echo "Balance Faded: {$subAccount->balance_faded} (" . ($subAccount->balance_faded / 100) . " bahaar)\n";
echo "Calculated (active + faded): " . (($subAccount->balance_active + $subAccount->balance_faded) / 100) . " bahaar\n\n";

// Get all transactions for this subaccount
echo "=== All Transactions ===\n";
$entries = LedgerEntry::where('meta->account_code', $subAccount->sub_account_code)
    ->orWhere('account_id', function ($q) use ($subAccount) {
        $q->select('id')->from('najm_accounts')->where('account_number', $subAccount->sub_account_code);
    })
    ->orderBy('created_at')
    ->get();

$runningBalance = 0;
foreach ($entries as $entry) {
    $amount = $entry->amount;
    $runningBalance += $amount;
    $sign = $amount >= 0 ? '+' : '' ;
    echo "[{$entry->created_at}] {$sign}{$amount} gol = {$runningBalance} gol running\n";
    if ($entry->meta) {
        echo "  Meta: " . json_encode($entry->meta) . "\n";
    }
}

echo "\n=== Analysis ===\n";
echo "Expected balance (from transactions): " . ($runningBalance / 100) . " bahaar\n";
echo "Actual balance_active: " . ($subAccount->balance_active / 100) . " bahaar\n";
echo "Actual balance (total): " . ($subAccount->balance / 100) . " bahaar\n";

if ($subAccount->balance_active / 100 === 3 && $subAccount->balance / 100 !== 3) {
    echo "\n✗ ISSUE: balance_active is correct (3) but balance field shows " . ($subAccount->balance / 100) . "\n";
    echo "  The balance field needs to be recalculated!\n";
}
