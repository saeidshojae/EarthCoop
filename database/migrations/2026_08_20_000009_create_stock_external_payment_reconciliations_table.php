<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_external_payment_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_intent_id')->constrained('stock_external_payment_intents')->restrictOnDelete();
            $table->string('event_key', 191)->unique();
            $table->string('provider', 80)->nullable();
            $table->string('provider_event_id', 191)->nullable()->index();
            $table->string('provider_payment_id', 191)->nullable()->index();
            $table->string('event_type', 64);
            $table->string('currency', 8);
            $table->bigInteger('amount_minor');
            $table->string('result_status', 32);
            $table->json('provider_payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['payment_intent_id', 'result_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_external_payment_reconciliations');
    }
};
