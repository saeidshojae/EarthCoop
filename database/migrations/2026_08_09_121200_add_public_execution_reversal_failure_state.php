<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('governance_public_execution_reversal_requests', function (Blueprint $table) {
            $table->unsignedInteger('attempts')->default(0)->after('status');
            $table->timestamp('last_attempt_at')->nullable()->after('attempts');
            $table->timestamp('failed_at')->nullable()->after('last_attempt_at');
            $table->text('last_error')->nullable()->after('failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('governance_public_execution_reversal_requests', function (Blueprint $table) {
            $table->dropColumn(['attempts', 'last_attempt_at', 'failed_at', 'last_error']);
        });
    }
};
