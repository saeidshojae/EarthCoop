<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Http\Kernel::class)->handle(
    $request = \Illuminate\Http\Request::capture()
);

use App\Modules\NajmBahar\Models\Account;

echo "=== System Accounts Check ===\n";

$systemAccounts = Account::where('type', 'system')->get();
echo "Total System Accounts: " . $systemAccounts->count() . "\n\n";

foreach ($systemAccounts as $acc) {
    echo "Account: {$acc->account_number}\n";
    echo "  Type: {$acc->type}\n";
    echo "  Name: {$acc->name}\n";
    echo "  Balance: {$acc->balance}\n";
    echo "  Balance Active: {$acc->balance_active}\n\n";
}

echo "\n=== Checking if System SubAccounts Exist ===\n";
$sysSubs = Account::where('account_number', 'LIKE', '0000000000%')->get();
echo "Total System Sub-like accounts: " . $sysSubs->count() . "\n\n";

foreach ($sysSubs as $acc) {
    echo "Account: {$acc->account_number}\n";
    echo "  Type: {$acc->type}\n";
    echo "  ID: {$acc->id}\n";
    echo "  Balance: {$acc->balance}\n\n";
}
