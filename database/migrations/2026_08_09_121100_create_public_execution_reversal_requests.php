<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governance_public_execution_reversal_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_instruction_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->unsignedBigInteger('amount_gol');
            $table->string('status', 30)->default('pending_approval');
            $table->string('idempotency_key', 191);
            $table->text('reason');
            $table->json('evidence')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->foreign('payment_instruction_id', 'gov_rev_payment_fk')
                ->references('id')->on('governance_public_execution_payment_instructions')->cascadeOnDelete();
            $table->foreign('created_by', 'gov_rev_creator_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by', 'gov_rev_approver_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('cancelled_by', 'gov_rev_canceller_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->unique('idempotency_key', 'gov_rev_idem_uq');
            $table->index(['payment_instruction_id', 'status'], 'gov_rev_payment_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance_public_execution_reversal_requests');
    }
};
