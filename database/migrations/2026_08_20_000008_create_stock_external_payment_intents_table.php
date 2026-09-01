<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_external_payment_intents', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 32); // external_irr|external_usd
            $table->string('currency', 8); // IRR|USD
            $table->bigInteger('amount_minor');
            $table->string('status', 32)->default('created')->index();
            $table->string('intent_key', 191)->unique();
            $table->string('reference_type', 80);
            $table->string('reference_id', 120);
            $table->string('provider', 80)->nullable();
            $table->string('provider_intent_id', 191)->nullable()->index();
            $table->string('provider_payment_id', 191)->nullable()->index();
            $table->json('quote_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['channel', 'status']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_external_payment_intents');
    }
};
