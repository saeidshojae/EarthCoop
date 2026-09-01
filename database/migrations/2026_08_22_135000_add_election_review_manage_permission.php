<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['slug' => 'elections.review.manage'],
            [
                'name' => 'مدیریت بازبینی انتخابات',
                'description' => 'اجازه تعلیق موقت و صدور تصمیم مستدل در بازبینی فرایند انتخابات.',
                'module' => 'elections',
                'order' => 10,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('permissions')->where('slug', 'elections.review.manage')->delete();
    }
};
