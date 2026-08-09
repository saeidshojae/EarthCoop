<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governance_public_execution_payment_instructions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('governance_public_contribution_plans')->cascadeOnDelete();
            $table->foreignId('authorization_id')->constrained('governance_public_execution_authorizations')->cascadeOnDelete();
            $table->foreignId('execution_account_id')->constrained('najm_bahar_accounts')->restrictOnDelete();
            $table->foreignId('payee_account_id')->constrained('najm_bahar_accounts')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('amount_gol');
            $table->string('status', 30)->default('pending');
            $table->string('idempotency_key', 191)->unique();
            $table->text('purpose');
            $table->json('evidence')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'status']);
            $table->index(['payee_account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance_public_execution_payment_instructions');
    }
};
