<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use App\Models\Account;

// Check columns in accounts table
echo "=== Najm Accounts Table Columns ===\n";
$columns = Schema::getColumnListing('najm_accounts');
foreach ($columns as $col) {
    echo "- $col\n";
}

// Check a sample account
echo "\n=== Sample Account ===\n";
$account = Account::first();
if ($account) {
    echo "ID: " . $account->id . "\n";
    echo "balance: " . ($account->balance ?? 'NULL') . "\n";
    echo "balance_active: " . ($account->balance_active ?? 'NULL') . "\n";
    echo "balance_faded: " . ($account->balance_faded ?? 'NULL') . "\n";
} else {
    echo "No accounts found\n";
}
