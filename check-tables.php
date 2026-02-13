<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=newearthcoop", "root", "");
$result = $pdo->query("SELECT TABLE_NAME, TABLE_ROWS FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'newearthcoop' AND TABLE_ROWS > 0 ORDER BY TABLE_ROWS DESC");
$tables = $result->fetchAll();
foreach ($tables as $row) {
    echo $row['TABLE_NAME'] . ": " . $row['TABLE_ROWS'] . "\n";
}
?>
