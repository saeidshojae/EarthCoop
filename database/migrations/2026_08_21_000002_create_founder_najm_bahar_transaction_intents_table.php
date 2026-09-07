<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('founder_najm_bahar_transaction_intents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_account_id');
            $table->unsignedBigInteger('to_account_id');
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->bigInteger('amount');
            $table->string('balance_type', 16)->default('active');
            $table->string('transaction_type', 64)->nullable();
            $table->string('intent_key', 191)->unique();
            $table->string('idempotency_key', 191)->unique();
            $table->string('status', 32)->default('draft')->index();
            $table->string('description', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->foreign('from_account_id', 'fnb_tx_intent_from_fk')->references('id')->on('najm_accounts')->restrictOnDelete();
            $table->foreign('to_account_id', 'fnb_tx_intent_to_fk')->references('id')->on('najm_accounts')->restrictOnDelete();
            $table->foreign('requested_by_user_id', 'fnb_tx_intent_requester_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by_user_id', 'fnb_tx_intent_approver_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('transaction_id', 'fnb_tx_intent_transaction_fk')->references('id')->on('najm_transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('founder_najm_bahar_transaction_intents');
    }
};
