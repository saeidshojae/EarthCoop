<?php

$pdo = new PDO("mysql:host=127.0.0.1;dbname=newearthcoop", "root", "");

// یافتن کاربر
$user = $pdo->query("SELECT id, email, created_at FROM users WHERE email = 'jomhouri.sherakati@gmail.com'")->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    echo "کاربر یافت نشد\n";
    exit(1);
}
$userId = $user['id'];
echo "کاربر: $userId (" . $user['email'] . ")\n\n";

// موجودی حسابی
echo "=== Main Account ===\n";
$main = $pdo->query("SELECT * FROM najm_accounts WHERE user_id = $userId AND type = 'user'")->fetch(PDO::FETCH_ASSOC);
if ($main) {
    echo "Account: " . $main['account_number'] . "\n";
    echo "Balance: " . $main['balance'] . " | Active: " . $main['balance_active'] . " | Faded: " . $main['balance_faded'] . "\n";
} else {
    echo "No main account found\n";
}

// حسابهای فرعی
echo "\n=== SubAccounts ===\n";
$subs = $pdo->query("
    SELECT sa.id, sa.sub_account_code, sa.balance, sa.balance_active, sa.balance_faded, a.id as account_id
    FROM najm_sub_accounts sa
    LEFT JOIN najm_accounts a ON sa.account_id = a.id
    WHERE a.user_id = $userId AND sa.status = 1
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($subs as $sub) {
    echo "SubAccount: " . $sub['sub_account_code'] . "\n";
    echo "  Balance: " . $sub['balance'] . " | Active: " . $sub['balance_active'] . " | Faded: " . $sub['balance_faded'] . "\n";
}

// تراکنش‌های کاربر
echo "\n=== User Transactions ===\n";
$txs = $pdo->query("
    SELECT 
        t.id,
        t.amount,
        a1.account_number as from_acc,
        a2.account_number as to_acc,
        JSON_EXTRACT(t.metadata, '$.type') as type,
        JSON_EXTRACT(t.metadata, '$.split') as split,
        JSON_EXTRACT(t.metadata, '$.balance_type') as balance_type,
        t.created_at
    FROM najm_transactions t
    LEFT JOIN najm_accounts a1 ON t.from_account_id = a1.id
    LEFT JOIN najm_accounts a2 ON t.to_account_id = a2.id
    WHERE 
        (a1.user_id = $userId OR a2.user_id = $userId) OR
        (JSON_EXTRACT(t.metadata, '$.user_id') = $userId)
    ORDER BY t.created_at DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($txs as $tx) {
    echo "TX: " . $tx['id'] . " | Amount: " . $tx['amount'] . " | From: " . $tx['from_acc'] . " | To: " . $tx['to_acc'] . " | Type: " . $tx['type'] . " | BalanceType: " . $tx['balance_type'] . " | Split: " . $tx['split'] . " | Time: " . $tx['created_at'] . "\n";
}

// مجموع تراکنش‌های حق عضویت کاربر
echo "\n=== Membership Fee Calculations ===\n";
$initialFunding = $pdo->query("
    SELECT COALESCE(SUM(amount), 0) as total
    FROM najm_transactions
    WHERE JSON_EXTRACT(metadata, '$.type') = 'initial_funding'
    AND JSON_EXTRACT(metadata, '$.user_id') = $userId
")->fetch(PDO::FETCH_ASSOC)['total'];

$membershipTxs = $pdo->query("
    SELECT amount, JSON_EXTRACT(metadata, '$.split') as split
    FROM najm_transactions
    WHERE JSON_EXTRACT(metadata, '$.type') = 'membership_fee'
    AND JSON_EXTRACT(metadata, '$.user_id') = $userId
")->fetchAll(PDO::FETCH_ASSOC);

echo "Initial Funding: $initialFunding\n";
echo "Membership Transactions:\n";
$totalMembership = 0;
foreach ($membershipTxs as $tx) {
    echo "  Split: " . $tx['split'] . " | Amount: " . $tx['amount'] . "\n";
    $totalMembership += $tx['amount'];
}
echo "Total Membership Paid: $totalMembership\n";

// محاسبه موجودی آخری
$currentTotal = 0;
if ($main) {
    $currentTotal += intval($main['balance_active'] ?? 0) + intval($main['balance_faded'] ?? 0);
}
foreach ($subs as $sub) {
    $currentTotal += intval($sub['balance_active'] ?? 0) + intval($sub['balance_faded'] ?? 0);
}

echo "\nCurrent Total Balance: $currentTotal\n";
echo "Expected: 10000 - 12 = 9988\n";
echo "Difference: " . ($currentTotal - 9988) . "\n";

?>
