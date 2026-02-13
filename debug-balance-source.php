<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Http\Kernel::class)->handle(
    $request = \Illuminate\Http\Request::capture()
);

use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;

echo "=== بررسی عمیق حساب فرعی 1000000006-001 ===\n\n";

// Get the subaccount
$subAccount = SubAccount::where('sub_account_code', '1000000006-001')->first();

if (!$subAccount) {
    echo "❌ حساب فرعی پیدا نشد!\n";
    exit(1);
}

echo "شماره حساب: {$subAccount->sub_account_code}\n";
echo "ID: {$subAccount->id}\n";
echo "Account ID (Foreign Key): {$subAccount->account_id}\n\n";

echo "=== موجودی ثبت‌شده در jadwal ===\n";
echo "balance: {$subAccount->balance} (" . ($subAccount->balance / 100) . " بهار)\n";
echo "balance_active: {$subAccount->balance_active} (" . ($subAccount->balance_active / 100) . " بهار)\n";
echo "balance_faded: {$subAccount->balance_faded} (" . ($subAccount->balance_faded / 100) . " بهار)\n";
echo "مجموع active+faded: " . ($subAccount->balance_active + $subAccount->balance_faded) . " (" . (($subAccount->balance_active + $subAccount->balance_faded) / 100) . " بهار)\n\n";

// Check the Account record for this subaccount code
$accountRecord = Account::where('account_number', '1000000006-001')->first();
if ($accountRecord) {
    echo "=== Account Record for 1000000006-001 ===\n";
    echo "ID: {$accountRecord->id}\n";
    echo "Type: {$accountRecord->type}\n";
    echo "User ID: {$accountRecord->user_id}\n";
    echo "balance: {$accountRecord->balance} (" . ($accountRecord->balance / 100) . " بهار)\n";
    echo "balance_active: {$accountRecord->balance_active} (" . ($accountRecord->balance_active / 100) . " بهار)\n";
    echo "balance_faded: {$accountRecord->balance_faded} (" . ($accountRecord->balance_faded / 100) . " بهار)\n\n";
}

// Get ALL ledger entries for this subaccount
echo "=== تمام تراکنش‌های دفتر برای این حساب فرعی ===\n";
$entries = LedgerEntry::where('account_id', $accountRecord->id ?? null)
    ->orderBy('created_at')
    ->get();

if ($entries->count() == 0) {
    echo "❌ هیچ تراکنشی برای حساب فرعی پیدا نشد!\n\n";
} else {
    echo "تعداد تراکنش: {$entries->count()}\n\n";
    $runningBalance = 0;
    foreach ($entries as $entry) {
        $runningBalance += $entry->amount;
        $sign = $entry->amount >= 0 ? '+' : '';
        echo "[{$entry->created_at}] {$sign}{$entry->amount} gol ({$entry->entry_type})\n";
        echo "  موجودی تراکمی: {$runningBalance} (" . ($runningBalance / 100) . " بهار)\n";
        if ($entry->meta) {
            echo "  متا: " . json_encode($entry->meta) . "\n";
        }
        echo "\n";
    }
    
    echo "📊 خلاصه:\n";
    echo "موجودی نهایی از تراکنش‌ها: {$runningBalance} (" . ($runningBalance / 100) . " بهار)\n";
    echo "موجودی ثبت‌شده در SubAccount: {$subAccount->balance} (" . ($subAccount->balance / 100) . " بهار)\n";
    
    if ($runningBalance === $subAccount->balance) {
        echo "✅ تطابق دارند!\n";
    } else {
        echo "❌ تطابق ندارند! تفاوت: " . ($subAccount->balance - $runningBalance) . " gol\n";
    }
}

// Check if SubAccount balance is being calculated by some formula
echo "\n\n=== بررسی نحوه محاسبه موجودی در UI ===\n";
echo "بررسی کنیم که موجودی از کجا محاسبه می‌شود:\n";
echo "1. balance_active: {$subAccount->balance_active}\n";
echo "2. balance_faded: {$subAccount->balance_faded}\n";
echo "3. balance: {$subAccount->balance}\n";
echo "4. balance_active + balance_faded: " . ($subAccount->balance_active + $subAccount->balance_faded) . "\n";

// Check if there's a model accessor or mutator
echo "\n=== بررسی Accessors/Mutators در Model ===\n";
echo "Model SubAccount آیا دارای accessor برای balance است?\n";
$reflection = new ReflectionClass($subAccount);
$methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
$accessors = [];
foreach ($methods as $method) {
    if (strpos($method->getName(), 'get') === 0 && strpos($method->getName(), 'Attribute') !== false) {
        $accessors[] = $method->getName();
    }
}
if (count($accessors) > 0) {
    echo "✓ Accessors یافت شد: " . implode(', ', $accessors) . "\n";
} else {
    echo "✗ هیچ accessor پیدا نشد\n";
}

echo "\n";
