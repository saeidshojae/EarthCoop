<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->unsignedBigInteger('base_share_price_gol')->nullable()->after('base_share_price');
            $table->unsignedBigInteger('startup_valuation_gol')->nullable()->after('startup_valuation');
        });

        Schema::table('auctions', function (Blueprint $table) {
            $table->unsignedBigInteger('base_price_gol')->nullable()->after('base_price');
            $table->unsignedBigInteger('min_bid_gol')->nullable()->after('min_bid');
            $table->unsignedBigInteger('max_bid_gol')->nullable()->after('max_bid');
        });

        Schema::table('bids', function (Blueprint $table) {
            $table->unsignedBigInteger('price_gol')->nullable()->after('price');
        });

        Schema::table('stock_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('price_gol')->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transactions', fn (Blueprint $table) => $table->dropColumn('price_gol'));
        Schema::table('bids', fn (Blueprint $table) => $table->dropColumn('price_gol'));
        Schema::table('auctions', fn (Blueprint $table) => $table->dropColumn(['base_price_gol','min_bid_gol','max_bid_gol']));
        Schema::table('stocks', fn (Blueprint $table) => $table->dropColumn(['base_share_price_gol','startup_valuation_gol']));
    }
};
