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
    // Clean database
    echo "=== CLEANING DATABASE ===\n";
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    foreach (['najm_accounts', 'najm_sub_accounts', 'najm_ledger_entries', 'najm_transactions'] as $table) {
        DB::statement("TRUNCATE $table");
        echo "✓ Truncated $table\n";
    }
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
    echo "\n";

    // Create test user
    echo "=== CREATING TEST USER ===\n";
    $user = User::create([
        'name' => 'Test Family',
       'email' => 'family-' . time() . '@test.com',
        'password' => bcrypt('password'),
    ]);
    echo "User ID: {$user->id}\n";
    echo "Email: {$user->email}\n\n";

    // Setup services
    $accountService = app(AccountService::class);
    $transactionService = app(TransactionService::class);

    // Create and fund main account
    echo "=== INITIALIZING MAIN ACCOUNT ===\n";
    $main = $accountService->createMainAccountForUser($user->id, 'Family Main');
    $main->balance_active = 300000;  // 3000 bahaar (active)
    $main->balance_faded = 700000;   // 7000 bahaar (faded)
    $main->balance = 1000000;        // 10000 bahaar (total)
    $main->save();
    echo "Account: {$main->account_number}\n";
    echo "Balance: " . ($main->balance / 100) . " bahaar\n";
    echo "  Active: " . ($main->balance_active / 100) . " bahaar\n";
    echo "  Faded: " . ($main->balance_faded / 100) . " bahaar\n\n";

    // Create subaccount
    echo "=== CREATING SUBACCOUNT ===\n";
    $sub = SubAccount::create([
        'account_id' => $main->id,
        'sub_account_code' => $main->account_number . '-001',
        'name' => 'Family Sub',
        'balance' => 0,
        'balance_active' => 0,
        'balance_faded' => 0,
        'status' => 1,
    ]);
    $accountService->ensureSubAccountAccount($sub);
    echo "SubAccount: {$sub->sub_account_code}\n\n";

    // STEP 1: Internal transfer Main → Sub
    echo "=== STEP 1: INTERNAL TRANSFER (Main → Sub, 22 bahaar) ===\n";
    $tx1 = $transactionService->transfer(
        $main->account_number,
        $sub->sub_account_code,
        2200,
        'Internal transfer to sub',
        ['test' => 'step1'],
        'test-step1',
        'active'
    );
    echo "✓ Transfer complete\n\n";

    // Check balances after Step 1
    $main->refresh();
    $sub->refresh();
    echo "After Step 1:\n";
    echo "Main Balance: {$main->balance} (" . ($main->balance / 100) . " bahaar)\n";
    echo "  - Expected: 1000000 (10000 bahaar) - INTERNAL, should NOT change\n";
    if ($main->balance === 1000000) {
        echo "  ✓ CORRECT\n";
    } else {
        echo "  ✗ WRONG (changed by " . ($main->balance - 1000000) . " gol)\n";
    }
    echo "Main Active: {$main->balance_active} (" . ($main->balance_active / 100) . " bahaar)\n";
    echo "Sub Balance: {$sub->balance} (" . ($sub->balance / 100) . " bahaar)\n";
    echo "Sub Active: {$sub->balance_active} (" . ($sub->balance_active / 100) . " bahaar)\n\n";

    // STEP 2: External transfers (Sub → System, 3 payments = 12 bahaar total)
    echo "=== STEP 2: EXTERNAL TRANSFERS (Sub → System, 6+3+3 bahaar) ===\n";
    
    $systemAccount = $accountService->getSystemAccount();
    $accountService->ensureDefaultSystemSubAccounts($systemAccount);
    
    $payments = [
        ['0000000000-001', 600, 'membership'],
        ['0000000000-002', 300, 'insurance'],
        ['0000000000-000', 300, 'burn'],
    ];
    
    foreach ($payments as [$target, $amount, $name]) {
        $tx = $transactionService->transfer(
            $sub->sub_account_code,
            $target,
            $amount,
            "Payment: $name",
            ['test' => 'step2', 'payment' => $name],
            "test-step2-$name",
            'active'
        );
        echo "✓ Paid $name: " . ($amount / 100) . " bahaar\n";
    }
    echo "\n";

    // Check balances after Step 2
    $main->refresh();
    $sub->refresh();
    echo "After Step 2:\n";
    echo "Main Balance: {$main->balance} (" . ($main->balance / 100) . " bahaar)\n";
    echo "  - Expected: 998800 (9988 bahaar) - EXTERNAL, should decrease by 12\n";
    if ($main->balance === 998800) {
        echo "  ✓ CORRECT\n";
    } else {
        echo "  ✗ WRONG (expected 998800, got {$main->balance})\n";
    }
    echo "Main Active: {$main->balance_active} (" . ($main->balance_active / 100) . " bahaar)\n";
    echo "Sub Balance: {$sub->balance} (" . ($sub->balance / 100) . " bahaar)\n";
    echo " - Expected: 1000 (10 bahaar) - 22 - 12 = 10\n";
    if ($sub->balance === 1000) {
        echo "  ✓ CORRECT\n";
    } else {
        echo "  ✗ WRONG (expected 1000, got {$sub->balance})\n";
    }
    echo "Sub Active: {$sub->balance_active} (" . ($sub->balance_active / 100) . " bahaar)\n\n";

    // Final check
    echo "=== FINAL RESULT ===\n";
    $familyTotal = $main->balance + $sub->balance;
    echo "Family Total (Main + Sub): {$familyTotal} (" . ($familyTotal / 100) . " bahaar)\n";
    if ($familyTotal === 999800) {
        echo "✅ SUCCESS! System is working correctly!\n";
    } else {
        echo "❌ FAILED! Expected family total 999800, got {$familyTotal}\n";
    }

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
