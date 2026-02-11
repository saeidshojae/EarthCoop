<?php
// Direct database test - برای تست بدون احراز هویت
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';

// Force it to accept the connection
$app->bootstrapWith([
    \Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
    \Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
]);

$app->make('config');
$db = $app->make('db');

echo "Testing Direct Database Insert:\n";
echo "===============================\n\n";

try {
    // بررسی اینکه table موجود است
    $exists = $db->getSchemaBuilder()->hasTable('steward_knowledge_files');
    echo "1. Table exists: " . ($exists ? "✅ YES" : "❌ NO") . "\n\n";
    
    if ($exists) {
        // Insert a test record
        echo "2. Inserting test record...\n";
        $result = $db->table('steward_knowledge_files')->insert([
            'title' => 'Test File من',
            'original_filename' => 'test_' . time() . '.txt',
            'file_path' => 'steward/knowledge/test_' . time() . '.txt',
            'file_type' => 'txt',
            'file_size' => 1024,
            'extracted_content' => 'This is a test file content.',
            'summary' => 'Test summary',
            'is_active' => true,
            'search_priority' => 8,
            'uploaded_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        echo "   ✅ Insert result: " . ($result ? "SUCCESS" : "FAILED") . "\n\n";
        
        // Query back
        echo "3. Querying records...\n";
        $count = $db->table('steward_knowledge_files')->count();
        echo "   Total records: {$count}\n\n";
        
        $files = $db->table('steward_knowledge_files')->latest()->limit(3)->get();
        echo "4. Latest files:\n";
        foreach ($files as $f) {
            echo "   - ID {$f->id}: {$f->title} (Priority: {$f->search_priority})\n";
        }
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\nDetails:\n";
    echo $e->getTraceAsString() . "\n";
}
