<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('secretariat_dispatches', function (Blueprint $table) {
            $table->boolean('expects_response')->default(false)->after('external_reference_number');
            $table->timestamp('due_at')->nullable()->after('expects_response');
            $table->timestamp('follow_up_at')->nullable()->after('due_at');
            $table->index(['status', 'due_at'], 'secretariat_dispatches_status_due_idx');
            $table->index(['status', 'follow_up_at'], 'secretariat_dispatches_status_follow_up_idx');
            $table->index(['expects_response', 'status'], 'secretariat_dispatches_response_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('secretariat_dispatches', function (Blueprint $table) {
            $table->dropIndex('secretariat_dispatches_status_due_idx');
            $table->dropIndex('secretariat_dispatches_status_follow_up_idx');
            $table->dropIndex('secretariat_dispatches_response_status_idx');
            $table->dropColumn(['expects_response', 'due_at', 'follow_up_at']);
        });
    }
};
