<?php
// Direct database fix for corrupted balance
$db = new mysqli('localhost', 'root', '', 'newearthcoop');

if ($db->connect_error) {
    die("Connection error: " . $db->connect_error);
}

echo "=== Fixing Account 1000000015 Subaccount Balance ===\n\n";

// For account 1000000015-001, fix the balance
$result = $db->query("
    SELECT id, balance, balance_active, balance_faded
    FROM najm_sub_accounts
    WHERE sub_account_code = '1000000015-001'
    LIMIT 1
");

if ($row = $result->fetch_assoc()) {
    echo "Found subaccount 1000000015-001:\n";
    echo "  Before fix:\n";
    echo "    Balance: {$row['balance']} (" . ($row['balance'] / 100) . " bahaar)\n";
    echo "    Active: {$row['balance_active']} (" . ($row['balance_active'] / 100) . " bahaar)\n";
    echo "    Faded: {$row['balance_faded']} (" . ($row['balance_faded'] / 100) . " bahaar)\n\n";
    
    // Fix: balance should be balance_active + balance_faded
    $newBalance = $row['balance_active'] + $row['balance_faded'];
    $subAccountId = $row['id'];
    
    $db->query("UPDATE najm_sub_accounts SET balance = {$newBalance} WHERE id = {$subAccountId}");
    
    echo "  After fix:\n";
    echo "    Balance: {$newBalance} (" . ($newBalance / 100) . " bahaar)\n";
    echo "    ✓ Fixed!\n";
}

// Also fix all other corrupted balances
echo "\n\nFixing ALL corrupted subaccount balances:\n";
$db->query("
    UPDATE najm_sub_accounts
    SET balance = balance_active + balance_faded
    WHERE balance != balance_active + balance_faded
");

echo "✓ Fixed all subaccount balances\n";

// Fix main accounts too
$db->query("
    UPDATE najm_accounts
    SET balance = balance_active + balance_faded
    WHERE type = 'user' AND balance != balance_active + balance_faded
");

echo "✓ Fixed all main account balances\n\n";

// Verify the fix
$result = $db->query("
    SELECT account_number, balance, balance_active, balance_faded,
           ROUND(balance / 100, 2) as balance_bahaar
    FROM najm_accounts
    WHERE account_number = '1000000015'
    LIMIT 1
");

if ($row = $result->fetch_assoc()) {
    echo "Main Account 1000000015 after fix:\n";
    echo "  Balance: {$row['balance_bahaar']} bahaar ✓\n";
    echo "  Active: " . round($row['balance_active'] / 100, 2) . " bahaar\n";
    echo "  Faded: " . round($row['balance_faded'] / 100, 2) . " bahaar\n";
}

$result = $db->query("
    SELECT sub_account_code, balance, balance_active, balance_faded,
           ROUND(balance / 100, 2) as balance_bahaar
    FROM najm_sub_accounts
    WHERE sub_account_code = '1000000015-001'
    LIMIT 1
");

if ($row = $result->fetch_assoc()) {
    echo "\nSubAccount 1000000015-001 after fix:\n";
    echo "  Balance: {$row['balance_bahaar']} bahaar ✓\n";
    echo "  Active: " . round($row['balance_active'] / 100, 2) . " bahaar\n";
    echo "  Faded: " . round($row['balance_faded'] / 100, 2) . " bahaar\n";
}

echo "\n✅ All balances have been corrected!\n";
echo "Please refresh the page to see the updated balance.\n";

$db->close();
?>
