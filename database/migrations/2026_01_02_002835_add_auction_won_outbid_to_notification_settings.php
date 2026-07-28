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
            $table->boolean('auction_won')->default(true)->after('auction_bid')->comment('اعلان برنده شدن در حراج');
            $table->boolean('auction_outbid')->default(true)->after('auction_won')->comment('اعلان پیشنهاد بالاتر از پیشنهاد کاربر');
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
            $table->dropColumn(['auction_won', 'auction_outbid']);
        });
    }
};
