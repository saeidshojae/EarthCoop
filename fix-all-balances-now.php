<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Http\Kernel::class)->handle(
    $request = \Illuminate\Http\Request::capture()
);

use Illuminate\Support\Facades\DB;

echo "=== Fixing All SubAccount Balances ===\n\n";

// Fix all subaccount balances to be balance_active + balance_faded
$updated = DB::update("
    UPDATE najm_sub_accounts
    SET balance = balance_active + balance_faded
    WHERE id > 0
");

echo "✓ Updated all subaccount balances\n";

// Get the latest test to verify
$result = DB::select("
    SELECT sub_account_code, balance, balance_active, balance_faded,
           ROUND(balance / 100, 2) as balance_bahaar,
           ROUND(balance_active / 100, 2) as active_bahaar,
           ROUND(balance_faded / 100, 2) as faded_bahaar
    FROM najm_sub_accounts
    ORDER BY id DESC
    LIMIT 3
");

echo "\n=== Latest SubAccounts ===\n";
foreach ($result as $row) {
    echo "\n{$row->sub_account_code}:\n";
    echo "  Balance: {$row->balance_bahaar} bahaar\n";
    echo "  Active: {$row->active_bahaar} bahaar\n";
    echo "  Faded: {$row->faded_bahaar} bahaar\n";
}

// Also fix main accounts
echo "\n\n=== Fixing All Main Accounts ===\n";
$updated = DB::update("
    UPDATE najm_accounts
    SET balance = balance_active + balance_faded
    WHERE type = 'user'
");

echo "✓ Updated all main account balances\n";

$result = DB::select("
    SELECT account_number, balance, balance_active, balance_faded,
           ROUND(balance / 100, 2) as balance_bahaar,
           ROUND(balance_active / 100, 2) as active_bahaar,
           ROUND(balance_faded / 100, 2) as faded_bahaar
    FROM najm_accounts
    WHERE type = 'user'
    ORDER BY id DESC
    LIMIT 3
");

echo "\n=== Latest Main Accounts ===\n";
foreach ($result as $row) {
    echo "\n{$row->account_number}:\n";
    echo "  Balance: {$row->balance_bahaar} bahaar\n";
    echo "  Active: {$row->active_bahaar} bahaar\n";
    echo "  Faded: {$row->faded_bahaar} bahaar\n";
}

echo "\n✅ All balances fixed!\n";
