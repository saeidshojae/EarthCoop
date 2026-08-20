<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_settlement_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('allocation_key', 191)->unique();
            $table->foreignId('auction_id')->constrained('auctions')->restrictOnDelete();
            $table->foreignId('bid_id')->nullable()->constrained('bids')->restrictOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->foreignId('stock_id')->constrained('stocks')->restrictOnDelete();
            $table->string('settlement_channel', 32);
            $table->unsignedBigInteger('quantity');
            $table->unsignedBigInteger('price_gol');
            $table->unsignedBigInteger('total_gol');
            $table->string('state', 40)->default('prepared')->index();
            $table->string('money_state', 32)->default('pending')->index();
            $table->string('asset_state', 32)->default('pending')->index();
            $table->string('reservation_key', 191)->nullable()->index();
            $table->string('payee_account_number', 64)->nullable();
            $table->string('money_settlement_key', 191)->nullable()->unique();
            $table->foreignId('external_payment_intent_id')->nullable()->constrained('stock_external_payment_intents')->restrictOnDelete();
            $table->foreignId('holding_transaction_id')->nullable()->constrained('holding_transactions')->restrictOnDelete();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('reconciliation_required_at')->nullable();
            $table->timestamps();

            $table->index(['auction_id','state']);
            $table->index(['stock_id','state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_settlement_allocations');
    }
};
