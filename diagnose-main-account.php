<?php
// Load Laravel
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Http\Kernel::class)->handle(
    $request = \Illuminate\Http\Request::capture()
);

use App\Models\Account;
use App\Models\SubAccount;
use App\Models\LedgerEntry;

echo "=== Checking Latest User's Accounts ===\n";

// Get the latest user
$latestUser = \App\Models\User::latest('id')->first();
if (!$latestUser) {
    echo "No user found!\n";
    exit;
}

echo "User ID: {$latestUser->id}\n";
echo "User Email: {$latestUser->email}\n\n";

// Get main account
$mainAccount = Account::where('user_id', $latestUser->id)->first();
if ($mainAccount) {
    echo "=== Main Account ===\n";
    echo "ID: {$mainAccount->id}\n";
    echo "Account Number: {$mainAccount->account_number}\n";
    echo "Balance (Total): {$mainAccount->balance}\n";
    echo "Balance Active: {$mainAccount->balance_active}\n";
    echo "Balance Faded: {$mainAccount->balance_faded}\n";
    $expectedTotal = $mainAccount->balance_active + $mainAccount->balance_faded;
    echo "Expected Total (active + faded): {$expectedTotal}\n";
    if ($mainAccount->balance != $expectedTotal) {
        echo "⚠️ MISMATCH! Balance should be {$expectedTotal} not {$mainAccount->balance}\n";
    }
}

// Get subaccounts
$subAccounts = SubAccount::where('user_id', $latestUser->id)->get();
echo "\n=== SubAccounts ===\n";
foreach ($subAccounts as $sub) {
    echo "\nSubAccount ID: {$sub->id}\n";
    echo "Account Number: {$sub->account_number}\n";
    echo "Balance (Total): {$sub->balance}\n";
    echo "Balance Active: {$sub->balance_active}\n";
    echo "Balance Faded: {$sub->balance_faded}\n";
    $expectedTotal = $sub->balance_active + $sub->balance_faded;
    echo "Expected Total (active + faded): {$expectedTotal}\n";
    if ($sub->balance != $expectedTotal) {
        echo "⚠️ MISMATCH! Balance should be {$expectedTotal} not {$sub->balance}\n";
    }
}

// Get all transactions for this user
echo "\n\n=== All Ledger Entries for User {$latestUser->id} ===\n";
$entries = LedgerEntry::where('user_id', $latestUser->id)
    ->orderBy('created_at', 'desc')
    ->get();

foreach ($entries as $entry) {
    echo "[{$entry->created_at}] {$entry->from_account} → {$entry->to_account}: {$entry->amount} gol ({$entry->transaction_type})\n";
}

// Calculate what the balances SHOULD be
echo "\n\n=== Expected Balance Calculation ===\n";
echo "Initial Funding: 1,000,000 gol (10,000 bahaar)\n";
echo "Initial Active (30%): 300,000 gol (3,000 bahaar)\n";
echo "Initial Faded (70%): 700,000 gol (7,000 bahaar)\n";

// Calculate deductions
$membershipPayments = $entries->where('to_account', 'LIKE', 'system-%')->sum('amount');
echo "\nTotal External Payments: {$membershipPayments} gol\n";

$expectedActive = 300000 - $membershipPayments;
$expectedFaded = 700000;
$expectedTotal = $expectedActive + $expectedFaded;

echo "\nExpected Active: {$expectedActive} gol\n";
echo "Expected Faded: {$expectedFaded} gol\n";
echo "Expected Total: {$expectedTotal} gol\n";

if ($mainAccount) {
    echo "\nActual Main Account Total: {$mainAccount->balance} gol\n";
    if ($mainAccount->balance == 1000000) {
        echo "❌ PROBLEM: Main account balance NOT updated after external transfer!\n";
    } elseif ($mainAccount->balance == $expectedTotal) {
        echo "✅ CORRECT: Main account balance is correct!\n";
    }
}
