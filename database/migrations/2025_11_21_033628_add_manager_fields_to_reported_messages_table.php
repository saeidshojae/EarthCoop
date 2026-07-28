<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('reported_messages', function (Blueprint $table) {
            // اضافه کردن group_id اگر وجود ندارد
            if (!Schema::hasColumn('reported_messages', 'group_id')) {
                $table->foreignId('group_id')->nullable()->after('message_id')->constrained('groups')->onDelete('cascade');
            }
            
            // اضافه کردن description اگر وجود ندارد
            if (!Schema::hasColumn('reported_messages', 'description')) {
                $table->text('description')->nullable()->after('reason');
            }
            
            // اضافه کردن فیلدهای جدید برای مدیریت مدیران گروه
            $table->text('manager_note')->nullable()->after('admin_note');
            $table->foreignId('reviewed_by_manager')->nullable()->after('manager_note')->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_manager');
            $table->boolean('escalated_to_admin')->default(false)->after('reviewed_at');
            $table->timestamp('escalated_at')->nullable()->after('escalated_to_admin');
        });
        
        // تغییر نوع status از enum به string برای پشتیبانی از status های جدید
        // این کار باید در یک migration جداگانه انجام شود
        // فعلاً فقط فیلدهای جدید اضافه می‌شوند
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('reported_messages', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by_manager']);
            $table->dropColumn([
                'manager_note',
                'reviewed_by_manager',
                'reviewed_at',
                'escalated_to_admin',
                'escalated_at'
            ]);
        });
    }
};
