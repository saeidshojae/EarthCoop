<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // بررسی اگر user موجود است
        $user = DB::table('users')->first();
        
        if (!$user) {
            echo "No users found in database, skipping test file creation.\n";
            return;
        }
        
        // ایجاد یک فایل تستی
        $testFileName = 'test_knowledge_' . time() . '.txt';
        $testContent = 'This is a test knowledge file for Steward Agent searching. It contains valuable information about the system.';
        
        // ذخیره فایل
        Storage::disk('public')->put('steward/knowledge/' . $testFileName, $testContent);
        
        // Insert یک record
        DB::table('steward_knowledge_files')->insert([
            'title' => 'Test Knowledge File - اطلاعات آزمایشی',
            'original_filename' => $testFileName,
            'file_path' => 'steward/knowledge/' . $testFileName,
            'file_type' => 'txt',
            'file_size' => strlen($testContent),
            'extracted_content' => $testContent,
            'summary' => 'Test file for Steward Agent feature - فایل آزمایشی',
            'is_active' => true,
            'search_priority' => 8,
            'uploaded_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        echo "✅ Test knowledge file created successfully!\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('steward_knowledge_files')
            ->where('original_filename', 'like', 'test_knowledge_%')
            ->delete();
    }
};
