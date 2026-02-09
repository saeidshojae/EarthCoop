<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            if (!Schema::hasColumn('invitations', 'status')) {
                $table->string('status')->default('pending')->index()->after('code');
            } else {
                // ensure index exists conceptually (skip if already)
            }
            if (!Schema::hasColumn('invitations', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('invitations', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('reviewed_at');
                $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('invitations', 'admin_note')) {
                $table->text('admin_note')->nullable()->after('reviewed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            if (Schema::hasColumn('invitations', 'admin_note')) {
                $table->dropColumn('admin_note');
            }
            if (Schema::hasColumn('invitations', 'reviewed_by')) {
                $table->dropForeign(['reviewed_by']);
                $table->dropColumn('reviewed_by');
            }
            if (Schema::hasColumn('invitations', 'reviewed_at')) {
                $table->dropColumn('reviewed_at');
            }
            // keep status column if existed before; otherwise drop
            // Attempt safe drop only if default migration didn't have it
            // $table->dropColumn('status'); // skipped intentionally
        });
    }
};


