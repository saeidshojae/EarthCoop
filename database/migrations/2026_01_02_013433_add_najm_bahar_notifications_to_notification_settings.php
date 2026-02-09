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
            // Najm Bahar notifications
            $table->boolean('najm_bahar_transaction')->default(true)->after('stock_price_changed')->comment('اعلان تراکنش نجم بهار');
            $table->boolean('najm_bahar_low_balance')->default(true)->after('najm_bahar_transaction')->comment('اعلان موجودی کم نجم بهار');
            $table->boolean('najm_bahar_large_transaction')->default(true)->after('najm_bahar_low_balance')->comment('اعلان تراکنش بزرگ نجم بهار');
            $table->boolean('najm_bahar_scheduled_transaction')->default(true)->after('najm_bahar_large_transaction')->comment('اعلان تراکنش زمان‌بندی شده نجم بهار');
            
            // Thresholds for Najm Bahar (optional, for user customization)
            $table->integer('najm_bahar_low_balance_threshold')->nullable()->after('najm_bahar_scheduled_transaction')->comment('آستانه موجودی کم نجم بهار');
            $table->integer('najm_bahar_large_transaction_threshold')->nullable()->after('najm_bahar_low_balance_threshold')->comment('آستانه تراکنش بزرگ نجم بهار');
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
                'najm_bahar_transaction',
                'najm_bahar_low_balance',
                'najm_bahar_large_transaction',
                'najm_bahar_scheduled_transaction',
                'najm_bahar_low_balance_threshold',
                'najm_bahar_large_transaction_threshold',
            ]);
        });
    }
};
