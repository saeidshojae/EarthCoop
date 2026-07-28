<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            if (!Schema::hasColumn('invitations', 'status')) {
                $table->string('status', 20)->default('pending')->index()->after('code');
            } else {
                // اطمینان از اینکه مقدار پیش‌فرض pending است
                $table->string('status', 20)->default('pending')->change();
            }
            if (!Schema::hasColumn('invitations', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('status')->index();
            }
            if (!Schema::hasColumn('invitations', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
            if (!Schema::hasColumn('invitations', 'admin_note')) {
                $table->text('admin_note')->nullable()->after('reviewed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            if (Schema::hasColumn('invitations', 'admin_note')) {
                $table->dropColumn('admin_note');
            }
            if (Schema::hasColumn('invitations', 'reviewed_at')) {
                $table->dropColumn('reviewed_at');
            }
            if (Schema::hasColumn('invitations', 'reviewed_by')) {
                $table->dropColumn('reviewed_by');
            }
            // ستون status را حذف نمی‌کنیم تا داده‌های موجود حفظ شود
        });
    }
};


