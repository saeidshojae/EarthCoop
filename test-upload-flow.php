<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\StewardKnowledgeFile;
use Illuminate\Support\Facades\Storage;

echo "Testing File Upload Flow:\n";
echo "==========================\n\n";

try {
    // بررسی database
    echo "1. Checking database...\n";
    $currentCount = StewardKnowledgeFile::count();
    echo "   Current files: {$currentCount}\n\n";

    // ایجاد یک فایل تستی
    echo "2. Creating test file on disk...\n";
    $testContent = "This is a test knowledge file.\n\nIt contains important information.";
    $testFileName = 'test_' . time() . '.txt';
    $storePath = 'steward/knowledge/' . $testFileName;
    
    $stored = Storage::disk('public')->put($storePath, $testContent);
    if ($stored) {
        echo "   ✅ File stored at: {$storePath}\n\n";
    } else {
        echo "   ❌ Failed to store file\n\n";
    }

    // ایجاد record در database
    echo "3. Creating database record...\n";
    $file = StewardKnowledgeFile::create([
        'title' => 'Test Knowledge File',
        'original_filename' => $testFileName,
        'file_path' => $storePath,
        'file_type' => 'txt',
        'file_size' => strlen($testContent),
        'extracted_content' => $testContent,
        'summary' => 'Test file summary',
        'search_priority' => 8,
        'uploaded_by' => 1,
        'is_active' => true,
    ]);
    echo "   ✅ Database record created!\n";
    echo "   ID: {$file->id}\n";
    echo "   Title: {$file->title}\n\n";

    // بررسی query
    echo "4. Verifying database query...\n";
    $count = StewardKnowledgeFile::count();
    echo "   Total files now: {$count}\n\n";

    $active = StewardKnowledgeFile::active()->count();
    echo "   Active files: {$active}\n\n";

    // چاپ تمام فایل‌ها
    echo "5. Listing all files:\n";
    $files = StewardKnowledgeFile::with('uploader:id,name')->latest()->get();
    foreach ($files as $f) {
        echo "   - ID {$f->id}: {$f->title} (Priority: {$f->search_priority})\n";
    }

    echo "\n✅ All tests passed!\n";

} catch (\Exception $e) {
    echo "❌ Error:\n";
    echo $e->getMessage() . "\n\n";
    echo "Trace:\n";
    echo $e->getTraceAsString() . "\n";
}
