<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Http\Kernel::class)->handle(
    $request = \Illuminate\Http\Request::capture()
);

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\TransactionService;
use Illuminate\Support\Facades\DB;

try {
    // Clean Najm tables
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    DB::statement('TRUNCATE najm_accounts');
    DB::statement('TRUNCATE najm_sub_accounts');
    DB::statement('TRUNCATE najm_ledger_entries');
    DB::statement('TRUNCATE najm_transactions');
    DB::statement('SET FOREIGN_KEY_CHECKS=1');

    echo "✓ Cleaned Najm tables\n\n";

    // Create test user
    $testUser = User::create([
        'name' => 'Test User Family Account',
        'email' => 'test-family-' . time() . '@example.com',
        'password' => bcrypt('password'),
    ]);

    echo "=== Created Test User ===\n";
    echo "User ID: {$testUser->id}\n";
    echo "Email: {$testUser->email}\n\n";

    // Initialize accounts
    $accountService = app(AccountService::class);
    $transactionService = app(TransactionService::class);

    echo "Creating main account...\n";
    $mainAccount = $accountService->createMainAccountForUser($testUser->id, 'Test Family Main Account');
    echo "Main account created: {$mainAccount->account_number}\n";

    // Initialize main account with 10,000 bahaar (1,000,000 gol)
    // 30% active (3,000 bahaar = 300,000 gol), 70% faded (7,000 bahaar = 700,000 gol)
    $mainAccount->balance_active = 300000;  // 3,000 bahaar
    $mainAccount->balance_faded = 700000;   // 7,000 bahaar
    $mainAccount->balance = 1000000;        // 10,000 bahaar
    $mainAccount->save();

    echo "✓ Main Account Initialized\n";
    echo "  Total: " . ($mainAccount->balance / 100) . " bahaar\n";
    echo "  Active: " . ($mainAccount->balance_active / 100) . " bahaar\n";
    echo "  Faded: " . ($mainAccount->balance_faded / 100) . " bahaar\n\n";

    // Create first subaccount
    echo "Creating subaccount...\n";
    $subAccount1 = SubAccount::create([
        'account_id' => $mainAccount->id,
        'sub_account_code' => $mainAccount->account_number . '-001',
        'name' => 'Family Sub Account 1',
        'balance_active' => 0,
        'balance_faded' => 0,
        'balance' => 0,
        'status' => 1,
    ]);
    
    $accountService->ensureSubAccountAccount($subAccount1);
    echo "✓ SubAccount Created: {$subAccount1->sub_account_code}\n\n";

    // Transfer 2,200 gol (22 bahaar) from main to subaccount
    echo "=== STEP 1: Internal Transfer (Main → SubAccount) ===\n";
    echo "Amount: 22 bahaar\n";
    echo "Expected: Main stays 10000, SubAccount becomes 22\n\n";

    $tx1 = $transactionService->transfer(
        $mainAccount->account_number,
        $subAccount1->sub_account_code,
        2200,
        'Transfer to subaccount',
        ['type' => 'test_internal'],
        null,
        'active'
    );
    echo "✓ Transfer complete\n\n";

    // Refresh
    $mainAccount->refresh();
    $subAccount1->refresh();

    echo "After Step 1:\n";
    echo "Main Total: " . ($mainAccount->balance / 100) . " bahaar (expected 10000)\n";
    echo "SubAccount Active: " . ($subAccount1->balance_active / 100) . " bahaar (expected 22)\n";
    echo "SubAccount Total: " . ($subAccount1->balance / 100) . " bahaar (expected 22)\n\n";

    // Now pay membership fee
    echo "=== STEP 2: External Transfer (SubAccount → System) ===\n";
    echo "Amount: 12 bahaar (6+3+3 split)\n";
    echo "Expected: Main becomes 9988, SubAccount becomes 10\n\n";

    // Get system account
    $systemAccount = $accountService->getSystemAccount();
    $accountService->ensureDefaultSystemSubAccounts($systemAccount);
    echo "System account initialized\n\n";

    // Make the three payments
    $payments = [
        ['0000000000-001', 600, 'membership'],
        ['0000000000-002', 300, 'insurance'],
        ['0000000000-000', 300, 'burn'],
    ];

    foreach ($payments as [$targetCode, $amount, $name]) {
        $tx = $transactionService->transfer(
            $subAccount1->sub_account_code,
            $targetCode,
            $amount,
            "Membership fee - $name",
            ['type' => 'membership_fee', 'split' => $name],
            'test-membership-' . $name,
            'active'
        );
        echo "✓ Paid {$name}: " . ($amount / 100) . " bahaar\n";
    }

    echo "\n";

    // Refresh all
    $mainAccount->refresh();
    $subAccount1->refresh();

    echo "After Step 2:\n";
    echo "Main Total: " . ($mainAccount->balance / 100) . " bahaar (expected 9988)\n";
    echo "Main Active: " . ($mainAccount->balance_active / 100) . " bahaar (expected 2988)\n";
    echo "SubAccount Active: " . ($subAccount1->balance_active / 100) . " bahaar (expected 10)\n";
    echo "SubAccount Total: " . ($subAccount1->balance / 100) . " bahaar (expected 10)\n\n";

    // Verify
    echo "=== RESULT ===\n";
    if ($mainAccount->balance / 100 === 9988 && $subAccount1->balance / 100 === 10) {
        echo "✅ SUCCESS! All balances are correct!\n";
    } else {
        echo "❌ FAILURE! Balances are still incorrect.\n";
        echo "   Main should be 9988, is " . ($mainAccount->balance / 100) . "\n";
        echo "   SubAccount should be 10, is " . ($subAccount1->balance / 100) . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
