<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=newearthcoop', 'root', '');

echo "=== Current Database State ===\n\n";

// Get latest test user's accounts
$stmt = $pdo->query("
    SELECT a.account_number, a.balance, a.balance_active, a.balance_faded, u.email
    FROM najm_accounts a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.type = 'user'
    ORDER BY a.id DESC
    LIMIT 3
");

echo "Main Accounts:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "\n{$row['account_number']} - User: {$row['email']}\n";
    echo "  Balance: {$row['balance']} (" . ($row['balance'] / 100) . " bahaar)\n";
    echo "  Active: {$row['balance_active']} (" . ($row['balance_active'] / 100) . " bahaar)\n";
    echo "  Faded: {$row['balance_faded']} (" . ($row['balance_faded'] / 100) . " bahaar)\n";
    echo "  Correct: " . (($row['balance'] == $row['balance_active'] + $row['balance_faded']) ? '✓' : '✗') . "\n";
}

echo "\n\nSubAccounts:\n";
$stmt = $pdo->query("
    SELECT sub_account_code, balance, balance_active, balance_faded
    FROM najm_sub_accounts
    ORDER BY id DESC
    LIMIT 5
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "\n{$row['sub_account_code']}\n";
    echo "  Balance: {$row['balance']} (" . ($row['balance'] / 100) . " bahaar)\n";
    echo "  Active: {$row['balance_active']} (" . ($row['balance_active'] / 100) . " bahaar)\n";
    echo "  Faded: {$row['balance_faded']} (" . ($row['balance_faded'] / 100) . " bahaar)\n";
    echo "  Correct: " . (($row['balance'] == $row['balance_active'] + $row['balance_faded']) ? '✓' : '✗') . "\n";
}
