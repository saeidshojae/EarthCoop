<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            // Auction notifications
            $table->boolean('auction_lost')->default(true)->after('auction_outbid')->comment('اعلان رد شدن پیشنهاد');
            $table->boolean('auction_cancelled')->default(true)->after('auction_lost')->comment('اعلان لغو پیشنهاد');
            $table->boolean('auction_reminder')->default(true)->after('auction_cancelled')->comment('یادآوری نزدیک شدن به پایان حراج');
            
            // Wallet notifications
            $table->boolean('wallet_settled')->default(true)->after('auction_reminder')->comment('اعلان تسویه کیف پول');
            $table->boolean('wallet_released')->default(true)->after('wallet_settled')->comment('اعلان آزادسازی مبلغ');
            $table->boolean('wallet_held')->default(true)->after('wallet_released')->comment('اعلان مسدودسازی مبلغ');
            $table->boolean('wallet_credited')->default(true)->after('wallet_held')->comment('اعلان شارژ کیف پول توسط ادمین');
            $table->boolean('wallet_debited')->default(true)->after('wallet_credited')->comment('اعلان کسر از کیف پول توسط ادمین');
            
            // Shares notifications
            $table->boolean('shares_received')->default(true)->after('wallet_debited')->comment('اعلان دریافت سهام');
            $table->boolean('shares_gifted')->default(true)->after('shares_received')->comment('اعلان هدیه سهام');
            
            // Stock notifications
            $table->boolean('stock_price_changed')->default(true)->after('shares_gifted')->comment('اعلان تغییر قیمت سهام');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->dropColumn([
                'auction_lost',
                'auction_cancelled',
                'auction_reminder',
                'wallet_settled',
                'wallet_released',
                'wallet_held',
                'wallet_credited',
                'wallet_debited',
                'shares_received',
                'shares_gifted',
                'stock_price_changed',
            ]);
        });
    }
};
