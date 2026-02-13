<?php

// اتصال مستقیم به دیتابیس MySQL
$host = '127.0.0.1';
$db = 'newearthcoop';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "✗ خطا در اتصال: " . $e->getMessage() . "\n";
    exit(1);
}

// دریافت لیست تمام جداول
$result = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$db' ORDER BY TABLE_NAME");
$allTables = $result->fetchAll(PDO::FETCH_COLUMN, 0);

echo "تمام جداول دیتابیس:\n";
echo str_repeat("=", 80) . "\n";

foreach ($allTables as $table) {
    $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    printf("%-45s %6d رکورد\n", "'$table'", $count);
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "کل جداول: " . count($allTables) . "\n";
?>
