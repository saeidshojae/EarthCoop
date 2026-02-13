<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Quick Account Check ===\n\n";

$account = DB::table('najm_accounts')
    ->where('account_number', '1000000014')
    ->first();

if ($account) {
    echo "Account: {$account->account_number}\n";
    echo "Balance: {$account->balance}\n";
    echo "Active: {$account->balance_active}\n";
    echo "Faded: {$account->balance_faded}\n";
    echo "Sum (active+faded): " . ($account->balance_active + $account->balance_faded) . "\n";
} else {
    echo "Account not found\n";
}
