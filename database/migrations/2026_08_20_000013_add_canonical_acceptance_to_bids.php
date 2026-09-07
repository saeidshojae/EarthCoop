<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->string('acceptance_key', 191)->nullable()->unique()->after('id');
            $table->string('reservation_key', 191)->nullable()->index()->after('price_gol');
            $table->foreignId('external_payment_intent_id')->nullable()->after('reservation_key')->constrained('stock_external_payment_intents')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->dropForeign(['external_payment_intent_id']);
            $table->dropUnique(['acceptance_key']);
            $table->dropColumn(['acceptance_key','reservation_key','external_payment_intent_id']);
        });
    }
};
