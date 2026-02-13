<?php
echo "Starting test...\n";

$pdo = new PDO('mysql:host=127.0.0.1;dbname=newearthcoop', 'root', '');

// Clean
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');

echo "Checking accounts...\n";
$stmt = $pdo->query("SELECT account_number, balance, balance_active, balance_faded FROM najm_accounts ORDER BY created_at DESC LIMIT 1");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Account: {$row['account_number']}\n";
    echo "  Balance: {$row['balance']} (" . ($row['balance'] / 100) . " bahaar)\n";
    echo "  Active: {$row['balance_active']} (" . ($row['balance_active'] / 100) . " bahaar)\n";
    echo "  Faded: {$row['balance_faded']} (" . ($row['balance_faded'] / 100) . " bahaar)\n\n";
}

echo "Checking subaccounts...\n";
$stmt = $pdo->query("SELECT sub_account_code, balance, balance_active, balance_faded FROM najm_sub_accounts ORDER BY created_at DESC LIMIT 3");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "SubAccount: {$row['sub_account_code']}\n";
    echo "  Balance: {$row['balance']} (" . ($row['balance'] / 100) . " bahaar)\n";
    echo "  Active: {$row['balance_active']} (" . ($row['balance_active'] / 100) . " bahaar)\n";
    echo "  Faded: {$row['balance_faded']} (" . ($row['balance_faded'] / 100) . " bahaar)\n\n";
}

echo "Done!\n";
