<?php

$pdo = new PDO("mysql:host=127.0.0.1;dbname=newearthcoop", "root", "");

// بررسی تراکنش‌های حق عضویت
echo "=== Membership Transactions ===\n";
$result = $pdo->query("SELECT id, amount, metadata FROM najm_transactions WHERE metadata LIKE '%membership_fee%' ORDER BY created_at DESC LIMIT 10");
$rows = $result->fetchAll(PDO::FETCH_ASSOC);
echo "Total: " . count($rows) . "\n\n";

foreach ($rows as $row) {
    $meta = json_decode($row['metadata'], true);
    echo "ID: " . $row['id'] . " | Amount: " . $row['amount'] . " | BalanceType: " . ($meta['balance_type'] ?? 'NULL') . " | Split: " . ($meta['split'] ?? 'NULL') . "\n";
}

// بررسی موجودی حساب فرعی
echo "\n=== SubAccount Balances ===\n";
$result = $pdo->query("SELECT sub_account_code, balance, balance_active, balance_faded FROM najm_sub_accounts WHERE status = 1 ORDER BY created_at DESC LIMIT 5");
$rows = $result->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    echo "Code: " . $row['sub_account_code'] . " | Total: " . $row['balance'] . " | Active: " . $row['balance_active'] . " | Faded: " . $row['balance_faded'] . "\n";
}

// بررسی موجودی حساب اصلی
echo "\n=== Main Account Balances ===\n";
$result = $pdo->query("SELECT account_number, user_id, balance, balance_active, balance_faded FROM najm_accounts WHERE type = 'user' ORDER BY user_id DESC LIMIT 5");
$rows = $result->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    echo "Account: " . $row['account_number'] . " | User: " . $row['user_id'] . " | Total: " . $row['balance'] . " | Active: " . $row['balance_active'] . " | Faded: " . $row['balance_faded'] . "\n";
}

?>
