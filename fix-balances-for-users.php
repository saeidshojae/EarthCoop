<?php
// Fix balances for specific main account numbers.
$targetAccounts = ['1000000015', '1000000006'];

$pdo = new PDO('mysql:host=127.0.0.1;dbname=newearthcoop', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Fixing balances for: " . implode(', ', $targetAccounts) . "\n\n";

// Fix main accounts.
$mainStmt = $pdo->prepare(
    "UPDATE najm_accounts
     SET balance = COALESCE(balance_active, 0) + COALESCE(balance_faded, 0)
     WHERE account_number = ? AND type = 'user'"
);

foreach ($targetAccounts as $accountNumber) {
    $mainStmt->execute([$accountNumber]);
}

echo "Main accounts updated.\n";

// Fix subaccounts that belong to these main accounts.
$subStmt = $pdo->prepare(
    "UPDATE najm_sub_accounts
     SET balance = COALESCE(balance_active, 0) + COALESCE(balance_faded, 0)
     WHERE sub_account_code LIKE ?"
);

foreach ($targetAccounts as $accountNumber) {
    $subStmt->execute([$accountNumber . '-%']);
}

echo "Subaccounts updated.\n\n";

// Show results.
$checkMain = $pdo->prepare(
    "SELECT account_number, balance, balance_active, balance_faded
     FROM najm_accounts
     WHERE account_number = ? AND type = 'user'"
);

$checkSub = $pdo->prepare(
    "SELECT sub_account_code, balance, balance_active, balance_faded
     FROM najm_sub_accounts
     WHERE sub_account_code LIKE ?
     ORDER BY sub_account_code"
);

foreach ($targetAccounts as $accountNumber) {
    $checkMain->execute([$accountNumber]);
    $main = $checkMain->fetch(PDO::FETCH_ASSOC);
    if ($main) {
        echo "Main {$main['account_number']}:\n";
        echo "  Balance: {$main['balance']} (" . ($main['balance'] / 100) . " bahaar)\n";
        echo "  Active: {$main['balance_active']} (" . ($main['balance_active'] / 100) . " bahaar)\n";
        echo "  Faded: {$main['balance_faded']} (" . ($main['balance_faded'] / 100) . " bahaar)\n\n";
    }

    $checkSub->execute([$accountNumber . '-%']);
    $subs = $checkSub->fetchAll(PDO::FETCH_ASSOC);
    foreach ($subs as $sub) {
        echo "Sub {$sub['sub_account_code']}:\n";
        echo "  Balance: {$sub['balance']} (" . ($sub['balance'] / 100) . " bahaar)\n";
        echo "  Active: {$sub['balance_active']} (" . ($sub['balance_active'] / 100) . " bahaar)\n";
        echo "  Faded: {$sub['balance_faded']} (" . ($sub['balance_faded'] / 100) . " bahaar)\n\n";
    }
}

echo "Done.\n";
