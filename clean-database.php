<?php

// اتصال مستقیم به دیتابیس MySQL
$host = '127.0.0.1';
$db = 'newearthcoop';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ اتصال به دیتابیس موفق بود.\n\n";
} catch (PDOException $e) {
    echo "✗ خطا در اتصال: " . $e->getMessage() . "\n";
    exit(1);
}

// ترتیب حذف داده‌ها (بر اساس Foreign Keys)
$tablesToClean = [
    'ticket_activities',
    'ticket_attachments',
    'ticket_tags',
    'ticket_tag',
    'ticket_comments',
    'message_reactions',
    'pinned_messages',
    'support_chat_messages',
    'group_user_settings',
    'group_user',
    'user_point_transactions',
    'chat_requests',
    'reactions',
    'elections',
    'candidates',
    'votes',
    'notification_settings',
    'messages',
    'support_chats',
    'tickets',
    'groups',
    'files',
    'user_points',
    'najm_bahar_salary_run_items',
    'najm_bahar_salary_runs',
    'najm_bahar_salary_rules',
    'najm_bahar_audit_logs',
    'najm_bahar_fees',
    'najm_bahar_agreements',
    'najm_scheduled_transactions',
    'najm_ledger_entries',
    'najm_transactions',
    'najm_sub_accounts',
    'najm_accounts',
    'user_occupational_field',
    'user_experience_field',
    'user_location',
    'user_role',
    'users',
];

echo "شروع پاک‌سازی جداول...\n";
echo str_repeat("=", 60) . "\n\n";

// دریافت لیست جداول موجود
try {
    $result = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$db' ORDER BY TABLE_NAME");
    $existingTableNames = $result->fetchAll(PDO::FETCH_COLUMN, 0);
    
    echo "تعداد جداول موجود: " . count($existingTableNames) . "\n\n";
    
} catch (Exception $e) {
    echo "✗ خطا در دریافت لیست جداول: " . $e->getMessage() . "\n";
    exit(1);
}

// غیرفعال‌کردن foreign key checks
try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0;');
    echo "✓ Foreign Key Checks غیرفعال شد.\n\n";
} catch (Exception $e) {
    echo "✗ خطا: " . $e->getMessage() . "\n";
    exit(1);
}

$cleanedCount = 0;
echo "پاک‌سازی جداول:\n";
echo str_repeat("-", 60) . "\n";

foreach ($tablesToClean as $table) {
    if (!in_array($table, $existingTableNames)) {
        continue;
    }
    
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        if ($count > 0) {
            $pdo->exec("TRUNCATE TABLE `$table`");
            printf("✓ %-40s (%4d رکورد حذف شد)\n", "'$table'", $count);
            $cleanedCount++;
        }
    } catch (Exception $e) {
        printf("✗ %-40s (خطا: %s)\n", "'$table'", $e->getMessage());
    }
}

// بازفعال‌سازی foreign key checks
try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1;');
    echo "\n✓ Foreign Key Checks بازفعال شد.\n";
} catch (Exception $e) {
    echo "\n✗ خطا: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "پاک‌سازی تکمیل شد! ($cleanedCount جدول خالی شد)\n\n";

// جداول حفاظت‌شده
$protectedTables = [
    'occupational_fields',
    'experience_fields',
    'locations',
    'continents',
    'countries',
    'regions',
    'neighborhoods',
    'streets',
    'alleies',
    'rurals',
    'villages',
    'blog_categories',
    'blog_tags',
    'blog_posts',
    'blog_post_tag',
    'blog_comments',
    'kb_categories',
    'kb_articles',
    'kb_tags',
    'kb_article_tag',
    'pages',
    'faq_questions',
    'terms',
    'email_templates',
    'Springs',
    'settings',
];

echo "جداول حفاظت‌شده (تغییری نکردند):\n";
echo str_repeat("-", 60) . "\n";

foreach ($protectedTables as $table) {
    if (in_array($table, $existingTableNames)) {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        printf("✓ %-40s (%4d رکورد)\n", "'$table'", $count);
    }
}

echo "\n✓ دیتابیس آماده است برای تست‌های جدید!\n";
