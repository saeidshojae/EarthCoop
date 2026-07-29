<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

echo "📤 Uploading Second Test File:\n";
echo "=============================\n\n";

try {
    // ایجاد فایل تستی
    $testFileName = 'document_' . time() . '.txt';
    $testContent = file_get_contents('C:\Users\user\Desktop\NewEarthCoop_TestFile.txt');
    
    // ذخیره فایل
    $filePath = 'steward/knowledge/' . $testFileName;
    Storage::disk('public')->put($filePath, $testContent);
    echo "1. ✅ File stored: " . $filePath . "\n\n";
    
    // Insert in database
    DB::table('steward_knowledge_files')->insert([
        'title' => 'Document: New Earth Coop Community Guide',
        'original_filename' => $testFileName,
        'file_path' => $filePath,
        'file_type' => 'txt',
        'file_size' => strlen($testContent),
        'extracted_content' => $testContent,
        'summary' => substr($testContent, 0, 150),
        'is_active' => true,
        'search_priority' => 7,
        'uploaded_by' => 5,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "2. ✅ Database record created\n\n";
    
    // Get all files
    $files = DB::table('steward_knowledge_files')->select('id','title','search_priority')->orderBy('id', 'desc')->get();
    echo "📚 All Knowledge Files:\n";
    echo "=====================\n";
    foreach($files as $f) {
        echo "ID {$f->id}: {$f->title} (Priority: {$f->search_priority})\n";
    }
    echo "\n✅ Total: " . count($files) . " files\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
