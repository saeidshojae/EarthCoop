<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('governance_public_execution_payment_instructions', function (Blueprint $table) {
            $table->unsignedInteger('attempts')->default(0)->after('status');
            $table->timestamp('last_attempt_at')->nullable()->after('attempts');
            $table->timestamp('failed_at')->nullable()->after('last_attempt_at');
            $table->text('failure_reason')->nullable()->after('failed_at');
            $table->index(['status', 'attempts'], 'gov_pay_status_attempts_idx');
        });
    }

    public function down(): void
    {
        Schema::table('governance_public_execution_payment_instructions', function (Blueprint $table) {
            $table->dropIndex('gov_pay_status_attempts_idx');
            $table->dropColumn(['attempts', 'last_attempt_at', 'failed_at', 'failure_reason']);
        });
    }
};
