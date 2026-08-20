<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->unsignedBigInteger('seller_user_id')->nullable()->index()->after('stock_id');
            $table->string('seller_holding_reservation_key',191)->nullable()->unique()->after('seller_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropUnique(['seller_holding_reservation_key']);
            $table->dropColumn(['seller_user_id','seller_holding_reservation_key']);
        });
    }
};
