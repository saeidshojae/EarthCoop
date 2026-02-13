<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=newearthcoop', 'root', '');

echo "Latest subaccounts:\n";
$stmt = $pdo->query("SELECT id, sub_account_code, balance, balance_active, balance_faded FROM najm_sub_accounts ORDER BY id DESC LIMIT 5");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "\nID {$row['id']}: {$row['sub_account_code']}\n";
    echo "  Balance: {$row['balance']} (" . ($row['balance'] / 100) . " bahaar)\n";
    echo "  Active: {$row['balance_active']} (" . ($row['balance_active'] / 100) . " bahaar)\n";
    echo "  Faded: {$row['balance_faded']} (" . ($row['balance_faded'] / 100) . " bahaar)\n";
}
