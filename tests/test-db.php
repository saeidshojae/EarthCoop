<?php
// Test database connection and query
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Testing Database Connection:\n";
echo "============================\n\n";

try {
    // Test connection
    DB::connection()->getPdo();
    echo "✅ Database connection OK\n\n";
    
    // Check if table exists
    $tables = DB::select("SHOW TABLES FROM `newearthcoop` LIKE 'steward_knowledge_files'");
    if (count($tables) > 0) {
        echo "✅ Table 'steward_knowledge_files' exists\n\n";
    } else {
        echo "❌ Table 'steward_knowledge_files' NOT FOUND\n\n";
        exit(1);
    }
    
    // Count records
    $count = DB::table('steward_knowledge_files')->count();
    echo "📊 Total records: {$count}\n\n";
    
    // Show all records
    if ($count > 0) {
        $files = DB::table('steward_knowledge_files')->select(['id', 'title', 'file_type', 'is_active', 'created_at'])->get();
        echo "📋 Files:\n";
        foreach ($files as $file) {
            echo "  - ID: {$file->id}, Title: {$file->title}, Type: {$file->file_type}, Active: {$file->is_active}\n";
        }
    } else {
        echo "⚠️  No files in database\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
