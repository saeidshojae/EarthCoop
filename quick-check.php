<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=newearthcoop', 'root', '');

// Get last subaccount
$stmt = $pdo->query("
    SELECT id, sub_account_code, balance, balance_active, balance_faded
    FROM najm_sub_accounts
    ORDER BY id DESC
    LIMIT 1
");

$sub = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sub) {
    echo "No subaccount found\n";
    exit;
}

echo "=== SubAccount Status ===\n";
echo "Code: {$sub['sub_account_code']}\n";
echo "Balance (total field): {$sub['balance']} gol (" . ($sub['balance'] / 100) . " bahaar)\n";
echo "Balance Active: {$sub['balance_active']} gol (" . ($sub['balance_active'] / 100) . " bahaar)\n";
echo "Balance Faded: {$sub['balance_faded']} gol (" . ($sub['balance_faded'] / 100) . " bahaar)\n";
echo "Sum (active + faded): " . ($sub['balance_active'] + $sub['balance_faded']) . " gol (" . (($sub['balance_active'] + $sub['balance_faded']) / 100) . " bahaar)\n\n";

if ($sub['balance'] != ($sub['balance_active'] + $sub['balance_faded'])) {
    echo "❌ MISMATCH DETECTED!\n";
    echo "   balance should equal active + faded\n";
    echo "   balance = " . ($sub['balance'] / 100) . " but active + faded = " . (($sub['balance_active'] + $sub['balance_faded']) / 100) . "\n";
} else {
    echo "✓ Balance fields are in sync\n";
}
