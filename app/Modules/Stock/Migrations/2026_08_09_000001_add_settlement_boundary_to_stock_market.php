<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            // Intentionally nullable for legacy rows. External settlement must
            // fail closed until an issuer is explicitly classified.
            $table->string('issuer_type', 32)->nullable()->after('id');
            $table->unsignedBigInteger('issuer_id')->nullable()->after('issuer_type');
            $table->index(['issuer_type', 'issuer_id'], 'stocks_issuer_lookup');
        });

        Schema::table('auctions', function (Blueprint $table) {
            // No permissive defaults: legacy auctions must be explicitly
            // classified before entering the canonical settlement path.
            $table->string('market_type', 16)->nullable()->after('stock_id');
            $table->string('supply_source', 16)->nullable()->after('market_type');
            $table->string('settlement_channel', 32)->nullable()->after('supply_source');
            $table->string('quote_unit', 16)->default('bahar')->after('settlement_channel');
            $table->index(
                ['market_type', 'supply_source', 'settlement_channel'],
                'auctions_settlement_boundary'
            );
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropIndex('auctions_settlement_boundary');
            $table->dropColumn([
                'market_type',
                'supply_source',
                'settlement_channel',
                'quote_unit',
            ]);
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropIndex('stocks_issuer_lookup');
            $table->dropColumn(['issuer_type', 'issuer_id']);
        });
    }
};
