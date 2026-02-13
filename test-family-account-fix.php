<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Http\Kernel::class)->handle(
    $request = \Illuminate\Http\Request::capture()
);

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\SubAccountService;
use App\Modules\NajmBahar\Services\TransactionService;
use Illuminate\Support\Facades\DB;

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
$subAccountService = app(SubAccountService::class);
$transactionService = app(TransactionService::class);

$mainAccount = $accountService->createMainAccountForUser($testUser->id, 'Test Family Main Account');

// Initialize main account with 10,000 bahaar (1,000,000 gol)
// 30% active (3,000 bahaar = 300,000 gol), 70% faded (7,000 bahaar = 700,000 gol)
$mainAccount->balance_active = 300000;  // 3,000 bahaar
$mainAccount->balance_faded = 700000;   // 7,000 bahaar
$mainAccount->balance = 1000000;        // 10,000 bahaar
$mainAccount->save();

echo "=== Main Account Initialized ===\n";
echo "Account Number: {$mainAccount->account_number}\n";
echo "Total Balance: " . ($mainAccount->balance / 100) . " bahaar\n";
echo "Active Balance: " . ($mainAccount->balance_active / 100) . " bahaar\n";
echo "Faded Balance: " . ($mainAccount->balance_faded / 100) . " bahaar\n\n";

// Create first subaccount
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

echo "=== Created SubAccount ===\n";
echo "SubAccount Code: {$subAccount1->sub_account_code}\n";
echo "Initial Balance: 0\n\n";

// Transfer 2,200 gol (22 bahaar) from main to subaccount
echo "=== Transfer 1: Main Account → SubAccount ===\n";
echo "Amount: 22 bahaar\n";
echo "Type: Internal transfer (should NOT affect main total)\n";

try {
    $tx1 = $transactionService->transfer(
        $mainAccount->account_number,
        $subAccount1->sub_account_code,
        2200,  // 22 bahaar in gol
        'Transfer to subaccount',
        ['type' => 'test_internal_transfer'],
        null,
        'active'
    );
    echo "✓ Transfer successful\n\n";
} catch (\Exception $e) {
    echo "✗ Transfer failed: {$e->getMessage()}\n\n";
    exit(1);
}

// Refresh balances
$mainAccount->refresh();
$subAccount1->refresh();

echo "After Transfer 1:\n";
echo "Main Account Active: " . ($mainAccount->balance_active / 100) . " bahaar\n";
echo "Main Account Total: " . ($mainAccount->balance / 100) . " bahaar\n";
echo "SubAccount Active: " . ($subAccount1->balance_active / 100) . " bahaar\n";
echo "SubAccount Total: " . ($subAccount1->balance / 100) . " bahaar\n";

// Verify internal transfer didn't affect main total
$expectedMainTotal = 10000;  // Should still be 10,000
$expectedSubActive = 22;     // Should be 22
if ($mainAccount->balance / 100 !== $expectedMainTotal) {
    echo "❌ ERROR: Main total changed! Expected {$expectedMainTotal}, got " . ($mainAccount->balance / 100) . "\n";
}
if ($subAccount1->balance_active / 100 !== $expectedSubActive) {
    echo "❌ ERROR: SubAccount active balance wrong! Expected {$expectedSubActive}, got " . ($subAccount1->balance_active / 100) . "\n";
}
echo "\n";

// Now pay membership fee (12 bahaar = 1200 gol split into 3: 600 + 300 + 300)
echo "=== Transfer 2: SubAccount → System (Membership Payment) ===\n";
echo "Amount: 12 bahaar total (6 membership + 3 insurance + 3 burn)\n";
echo "Type: External to system (SHOULD affect main total)\n\n";

// Get or create system account
$systemAccount = $accountService->getSystemAccount();
$accountService->ensureDefaultSystemSubAccounts($systemAccount);

// Make the three membership payments
$payments = [
    ['0000000000-001', 600, 'membership'],
    ['0000000000-002', 300, 'insurance'],
    ['0000000000-000', 300, 'burn'],
];

foreach ($payments as [$targetCode, $amount, $name]) {
    try {
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
    } catch (\Exception $e) {
        echo "✗ Payment failed for $name: {$e->getMessage()}\n";
        exit(1);
    }
}

echo "\n";

// Refresh all balances
$mainAccount->refresh();
$subAccount1->refresh();

echo "After Transfer 2 (Membership Payment):\n";
echo "Main Account Active: " . ($mainAccount->balance_active / 100) . " bahaar\n";
echo "Main Account Total: " . ($mainAccount->balance / 100) . " bahaar\n";
echo "SubAccount Active: " . ($subAccount1->balance_active / 100) . " bahaar\n";
echo "SubAccount Total: " . ($subAccount1->balance / 100) . " bahaar\n\n";

// Verify the balances
$expectedMainActive = 3000 - 12;  // 2988 bahaar
$expectedMainTotal = 10000 - 12;  // 9988 bahaar
$expectedSubActive = 22 - 12;     // 10 bahaar
$expectedSubTotal = 22 - 12;      // 10 bahaar

echo "=== VERIFICATION ===\n";
$allCorrect = true;

if ($mainAccount->balance_active / 100 === $expectedMainActive) {
    echo "✓ Main Active Balance: " . ($mainAccount->balance_active / 100) . " bahaar (correct)\n";
} else {
    echo "❌ Main Active Balance: " . ($mainAccount->balance_active / 100) . " bahaar (expected {$expectedMainActive})\n";
    $allCorrect = false;
}

if ($mainAccount->balance / 100 === $expectedMainTotal) {
    echo "✓ Main Total Balance: " . ($mainAccount->balance / 100) . " bahaar (correct)\n";
} else {
    echo "❌ Main Total Balance: " . ($mainAccount->balance / 100) . " bahaar (expected {$expectedMainTotal})\n";
    $allCorrect = false;
}

if ($subAccount1->balance_active / 100 === $expectedSubActive) {
    echo "✓ SubAccount Active: " . ($subAccount1->balance_active / 100) . " bahaar (correct)\n";
} else {
    echo "❌ SubAccount Active: " . ($subAccount1->balance_active / 100) . " bahaar (expected {$expectedSubActive})\n";
    $allCorrect = false;
}

if ($subAccount1->balance / 100 === $expectedSubTotal) {
    echo "✓ SubAccount Total: " . ($subAccount1->balance / 100) . " bahaar (correct)\n";
} else {
    echo "❌ SubAccount Total: " . ($subAccount1->balance / 100) . " bahaar (expected {$expectedSubTotal})\n";
    $allCorrect = false;
}

echo "\n";

if ($allCorrect) {
    echo "🎉 ALL TESTS PASSED!\n";
    echo "The main account is correctly tracking external transfers while ignoring internal ones!\n";
} else {
    echo "❌ Some tests failed. Check the balances above.\n";
    exit(1);
}
