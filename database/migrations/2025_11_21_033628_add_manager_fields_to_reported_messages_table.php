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
        if (!Schema::hasTable('reported_messages')) {
            return;
        }

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
            if (!Schema::hasColumn('reported_messages', 'manager_note')) {
                $table->text('manager_note')->nullable()->after('admin_note');
            }
            if (!Schema::hasColumn('reported_messages', 'reviewed_by_manager')) {
                $table->foreignId('reviewed_by_manager')->nullable()->after('manager_note')->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('reported_messages', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_manager');
            }
            if (!Schema::hasColumn('reported_messages', 'escalated_to_admin')) {
                $table->boolean('escalated_to_admin')->default(false)->after('reviewed_at');
            }
            if (!Schema::hasColumn('reported_messages', 'escalated_at')) {
                $table->timestamp('escalated_at')->nullable()->after('escalated_to_admin');
            }
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
        if (!Schema::hasTable('reported_messages')) {
            return;
        }

        Schema::table('reported_messages', function (Blueprint $table) {
            if (Schema::hasColumn('reported_messages', 'reviewed_by_manager')) {
                try {
                    $table->dropForeign(['reviewed_by_manager']);
                } catch (\Throwable $e) {
                    // Ignore missing foreign key.
                }
            }

            $toDrop = [];
            foreach (['manager_note', 'reviewed_by_manager', 'reviewed_at', 'escalated_to_admin', 'escalated_at'] as $column) {
                if (Schema::hasColumn('reported_messages', $column)) {
                    $toDrop[] = $column;
                }
            }

            if (!empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });
    }
};
