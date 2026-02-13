<?php

$pdo = new PDO("mysql:host=127.0.0.1;dbname=newearthcoop", "root", "");

// بررسی تراکنش‌های حق عضویت
echo "=== تراکنش‌های حق عضویت ===\n";
$result = $pdo->query("
    SELECT 
        t.id, 
        t.from_account_id, 
        a.account_number,
        t.to_account_id,
        a2.account_number as to_account_number,
        t.amount,
        JSON_EXTRACT(t.metadata, '$.type') as type,
        JSON_EXTRACT(t.metadata, '$.split') as split,
        JSON_EXTRACT(t.metadata, '$.user_id') as user_id,
        JSON_EXTRACT(t.metadata, '$.balance_type') as balance_type,
        t.created_at
    FROM najm_transactions t
    LEFT JOIN najm_accounts a ON t.from_account_id = a.id
    LEFT JOIN najm_accounts a2 ON t.to_account_id = a2.id
    WHERE JSON_EXTRACT(t.metadata, '$.type') = 'membership_fee'
    ORDER BY t.created_at DESC
");

$rows = $result->fetchAll(PDO::FETCH_ASSOC);
echo "کل تراکنش‌های حق عضویت: " . count($rows) . "\n\n";

foreach ($rows as $row) {
    echo sprintf(
        "ID: %d | From: %s | To: %s | Amount: %d | Split: %s | BalanceType: %s | User: %s | Time: %s\n",
        $row['id'],
        $row['account_number'] ?? 'NULL',
        $row['to_account_number'] ?? 'NULL',
        $row['amount'],
        $row['split'] ?? 'NULL',
        $row['balance_type'] ?? 'NULL',
        $row['user_id'] ?? 'NULL',
        $row['created_at']
    );
}

// بررسی موجودی حساب‌های فرعی
echo "\n=== موجودی حساب‌های فرعی ===\n";
$result = $pdo->query("
    SELECT 
        sa.id,
        sa.sub_account_code,
        sa.balance,
        sa.balance_active,
        sa.balance_faded,
        a.account_number as parent_account
    FROM najm_sub_accounts sa
    LEFT JOIN najm_accounts a ON sa.account_id = a.id
    WHERE sa.status = 1
    ORDER BY sa.created_at DESC
    LIMIT 5
");

$rows = $result->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo sprintf(
        "Code: %s | Parent: %s | Balance: %d | Active: %d | Faded: %d\n",
        $row['sub_account_code'],
        $row['parent_account'] ?? 'NULL',
        $row['balance'],
        $row['balance_active'],
        $row['balance_faded']
    );
}

// بررسی موجودی حساب‌های اصلی
echo "\n=== موجودی حساب‌های اصلی ===\n";
$result = $pdo->query("
    SELECT 
        a.account_number,
        a.type,
        a.user_id,
        a.balance,
        a.balance_active,
        a.balance_faded
    FROM najm_accounts a
    WHERE a.type = 'user'
    ORDER BY a.user_id DESC
    LIMIT 5
");

$rows = $result->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo sprintf(
        "Account: %s | User: %s | Balance: %d | Active: %d | Faded: %d\n",
        $row['account_number'],
        $row['user_id'] ?? 'NULL',
        $row['balance'],
        $row['balance_active'],
        $row['balance_faded']
    );
}

echo "\nDone!\n";
?>
