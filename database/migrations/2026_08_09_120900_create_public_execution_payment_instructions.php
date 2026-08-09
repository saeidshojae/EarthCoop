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
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('authorization_id');
            $table->unsignedBigInteger('execution_account_id');
            $table->unsignedBigInteger('payee_account_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->unsignedBigInteger('amount_gol');
            $table->string('status', 30)->default('pending_approval');
            $table->string('idempotency_key', 191)->unique();
            $table->text('purpose');
            $table->json('evidence')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->foreign('plan_id', 'gov_pay_plan_fk')
                ->references('id')->on('governance_public_contribution_plans')->cascadeOnDelete();
            $table->foreign('authorization_id', 'gov_pay_auth_fk')
                ->references('id')->on('governance_public_execution_authorizations')->cascadeOnDelete();
            $table->foreign('execution_account_id', 'gov_pay_exec_acc_fk')
                ->references('id')->on('najm_accounts')->restrictOnDelete();
            $table->foreign('payee_account_id', 'gov_pay_payee_acc_fk')
                ->references('id')->on('najm_accounts')->restrictOnDelete();
            $table->foreign('created_by', 'gov_pay_creator_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by', 'gov_pay_approver_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('cancelled_by', 'gov_pay_canceller_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->index(['plan_id', 'status'], 'gov_pay_plan_status_idx');
            $table->index(['payee_account_id', 'status'], 'gov_pay_payee_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance_public_execution_payment_instructions');
    }
};
