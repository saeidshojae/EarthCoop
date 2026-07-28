<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // اگر ستون status وجود دارد
        if (Schema::hasColumn('users', 'status')) {
            // ابتدا یک ستون موقت برای ذخیره مقادیر string ایجاد می‌کنیم
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `users` ADD COLUMN `status_temp` VARCHAR(20) DEFAULT NULL");
            
            // تبدیل داده‌های موجود: 1 -> 'active', 0 -> 'inactive', null -> 'active'
            \Illuminate\Support\Facades\DB::statement("UPDATE `users` SET `status_temp` = CASE 
                WHEN `status` = 1 THEN 'active'
                WHEN `status` = 0 THEN 'inactive'
                WHEN `status` IS NULL THEN 'active'
                ELSE 'active'
            END");
            
            // حذف ستون قدیمی
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `users` DROP COLUMN `status`");
            
            // اضافه کردن ستون جدید به صورت enum
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `users` ADD COLUMN `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active' AFTER `national_id`");
            
            // کپی کردن داده‌ها از ستون موقت به ستون جدید
            \Illuminate\Support\Facades\DB::statement("UPDATE `users` SET `status` = `status_temp`");
            
            // حذف ستون موقت
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `users` DROP COLUMN `status_temp`");
        } else {
            // اگر وجود ندارد، آن را اضافه می‌کنیم
            Schema::table('users', function (Blueprint $table) {
                $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('national_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
