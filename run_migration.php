<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// Run migration
$exitCode = $kernel->call('migrate', ['--force' => true]);

echo "Migration exit code: $exitCode\n";

// Check database columns
use Illuminate\Support\Facades\Schema;
$kernel->bootstrap();

echo "\nChecking najm_accounts columns:\n";
$columns = Schema::getColumnListing('najm_accounts');
foreach ($columns as $col) {
    echo "- $col\n";
}

// Check sample account
echo "\nChecking sample account:\n";
$account = \App\Modules\NajmBahar\Models\Account::first();
if ($account) {
    echo "balance: " . ($account->balance ?? 'NULL') . "\n";
    echo "balance_active: " . ($account->balance_active ?? 'NULL') . "\n";
    echo "balance_faded: " . ($account->balance_faded ?? 'NULL') . "\n";
} else {
    echo "No account found\n";
}
