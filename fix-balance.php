<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=newearthcoop', 'root', '');
$pdo->exec('UPDATE najm_sub_accounts SET balance = balance_active + balance_faded WHERE id > 0');
$pdo->exec('UPDATE najm_accounts SET balance = COALESCE(balance_active, 0) + COALESCE(balance_faded, 0) WHERE id > 0');
echo "✓ Balances fixed.\n";
?>
