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
            if (!Schema::hasColumn('notification_settings', 'auction_won')) {
                $table->boolean('auction_won')->default(true)->after('auction_bid')->comment('اعلان برنده شدن در حراج');
            }
            if (!Schema::hasColumn('notification_settings', 'auction_outbid')) {
                $table->boolean('auction_outbid')->default(true)->after('auction_won')->comment('اعلان پیشنهاد بالاتر از پیشنهاد کاربر');
            }
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
            $toDrop = [];
            if (Schema::hasColumn('notification_settings', 'auction_won')) {
                $toDrop[] = 'auction_won';
            }
            if (Schema::hasColumn('notification_settings', 'auction_outbid')) {
                $toDrop[] = 'auction_outbid';
            }

            if (!empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });
    }
};
