<?php
// Direct database check without Laravel to avoid any caching
$db = new mysqli('localhost', 'root', '', 'newearthcoop');

if ($db->connect_error) {
    die("Connection error: " . $db->connect_error);
}

echo "=== Checking Najm Account Balances ===\n\n";

// Get account 1000000015 (the one in the screenshot)
$result = $db->query("
    SELECT account_number, balance, balance_active, balance_faded, user_id
    FROM najm_accounts
    WHERE account_number = '1000000015'
");

if ($row = $result->fetch_assoc()) {
    echo "Main Account 1000000015:\n";
    echo "  Balance: {$row['balance']} (" . ($row['balance'] / 100) . " bahaar)\n";
    echo "  Active: {$row['balance_active']} (" . ($row['balance_active'] / 100) . " bahaar)\n";
    echo "  Faded: {$row['balance_faded']} (" . ($row['balance_faded'] / 100) . " bahaar)\n";
    echo "  User ID: {$row['user_id']}\n\n";
}

// Get subaccounts for this account
$result = $db->query("
    SELECT id, sub_account_code, balance, balance_active, balance_faded, account_id
    FROM najm_sub_accounts
    WHERE sub_account_code LIKE '1000000015-%'
");

echo "SubAccounts:\n";
while ($row = $result->fetch_assoc()) {
    echo "\n{$row['sub_account_code']}:\n";
    echo "  Balance: {$row['balance']} (" . ($row['balance'] / 100) . " bahaar)\n";
    echo "  Active: {$row['balance_active']} (" . ($row['balance_active'] / 100) . " bahaar)\n";
    echo "  Faded: {$row['balance_faded']} (" . ($row['balance_faded'] / 100) . " bahaar)\n";
    echo "  Correct: " . (($row['balance'] == $row['balance_active'] + $row['balance_faded']) ? '✓' : '✗') . "\n";
}

// Check all ledger entries for this subaccount
echo "\n\n=== Ledger Entries for 1000000015-001 ===\n";
$result = $db->query("
    SELECT amount, entry_type, created_at
    FROM najm_ledger_entries
    WHERE account_id IN (
        SELECT id FROM najm_accounts
        WHERE account_number = '1000000015-001'
    )
    ORDER BY created_at
");

$total = 0;
while ($row = $result->fetch_assoc()) {
    $total += $row['amount'];
    $sign = $row['amount'] >= 0 ? '+' : '';
    echo "[{$row['created_at']}] {$sign}{$row['amount']} gol ({$row['entry_type']}) = {$total} gol total\n";
}

echo "\nExpected balance from ledger: " . ($total / 100) . " bahaar\n";

$db->close();
