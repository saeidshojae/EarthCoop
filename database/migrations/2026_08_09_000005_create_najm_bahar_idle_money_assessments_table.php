<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('najm_bahar_idle_money_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('najm_accounts')->cascadeOnDelete();
            $table->foreignId('policy_version_id')->nullable()->constrained('najm_bahar_monetary_policy_versions')->nullOnDelete();
            $table->timestamp('as_of');
            $table->unsignedInteger('idle_period_days');
            $table->unsignedBigInteger('active_balance_gol');
            $table->unsignedBigInteger('exempt_balance_gol')->default(0);
            $table->unsignedBigInteger('taxable_candidate_gol')->default(0);
            $table->timestamp('last_external_active_outflow_at')->nullable();
            $table->timestamp('idle_since')->nullable();
            $table->string('status', 30)->default('not_idle');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'as_of']);
            $table->index(['status', 'as_of']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('najm_bahar_idle_money_assessments');
    }
};
