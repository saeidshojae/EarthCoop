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
    }
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
    echo "✓ Database cleaned\n\n";

    // Create test user
    echo "=== SCENARIO: 15 Bahaar → Sub, Then 12 Bahaar Membership ===\n";
    $user = User::create([
        'name' => 'User Test',
        'email' => 'test-' . time() . '@test.com',
        'password' => bcrypt('password'),
    ]);
    echo "User: {$user->email} (ID: {$user->id})\n\n";

    // Setup
    $accountService = app(AccountService::class);
    $transactionService = app(TransactionService::class);

    // Create and fund main account with 10000 bahaar
    $main = $accountService->createMainAccountForUser($user->id, 'Main');
    $main->balance_active = 300000;  // 3000 bahaar
    $main->balance_faded = 700000;   // 7000 bahaar
    $main->balance = 1000000;        // 10000 bahaar
    $main->save();
    echo "Main Account: {$main->account_number}\n";
    echo "  Balance: " . ($main->balance / 100) . " bahaar\n\n";

    // Create subaccount
    $sub = SubAccount::create([
        'account_id' => $main->id,
        'sub_account_code' => $main->account_number . '-001',
        'name' => 'Sub',
        'balance' => 0,
        'balance_active' => 0,
        'balance_faded' => 0,
        'status' => 1,
    ]);
    $accountService->ensureSubAccountAccount($sub);

    // STEP 1: Transfer 15 bahaar from main to sub (1500 gol)
    echo "=== STEP 1: Transfer 15 bahaar Main → Sub ===\n";
    $tx1 = $transactionService->transfer(
        $main->account_number,
        $sub->sub_account_code,
        1500,  // 15 bahaar (not 22)
        'Transfer to sub',
        ['step' => 1],
        'step1',
        'active'
    );
    
    $main->refresh();
    $sub->refresh();
    echo "✓ Transfer complete\n";
    echo "  Main Balance: " . ($main->balance / 100) . " bahaar\n";
    echo "  Sub Balance: " . ($sub->balance / 100) . " bahaar\n";
    echo "  Sub Active: " . ($sub->balance_active / 100) . " bahaar\n\n";

    // STEP 2: Pay 12 bahaar membership
    echo "=== STEP 2: Pay 12 bahaar Membership ===\n";
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
            $name,
            ['type' => 'membership', 'split' => $name],
            "test2-$name",
            'active'
        );
        echo "✓ Paid $name: " . ($amount / 100) . " bahaar\n";
    }
    echo "\n";

    // Final check
    $main->refresh();
    $sub->refresh();
    
    echo "=== FINAL STATE ===\n";
    echo "Main Balance: " . ($main->balance / 100) . " bahaar (expected 9988)\n";
    echo "Main Active: " . ($main->balance_active / 100) . " bahaar (expected 2985)\n";
    echo "Sub Balance: " . ($sub->balance / 100) . " bahaar (expected 3)\n";
    echo "Sub Active: " . ($sub->balance_active / 100) . " bahaar (expected 3)\n\n";

    // Verify
    $errors = [];
    if ($main->balance / 100 != 9988) {
        $errors[] = "Main balance should be 9988, got " . ($main->balance / 100);
    }
    if ($main->balance_active / 100 != 2985) {
        $errors[] = "Main active should be 2985, got " . ($main->balance_active / 100);
    }
    if ($sub->balance / 100 != 3) {
        $errors[] = "Sub balance should be 3, got " . ($sub->balance / 100);
    }
    if ($sub->balance_active / 100 != 3) {
        $errors[] = "Sub active should be 3, got " . ($sub->balance_active / 100);
    }

    if (count($errors) === 0) {
        echo "✅ ALL CORRECT!\n";
    } else {
        echo "❌ ERRORS:\n";
        foreach ($errors as $err) {
            echo "   - $err\n";
        }
    }

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
