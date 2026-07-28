<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('user_point_transactions', function (Blueprint $table) {
            $table->boolean('is_cashed')->default(false)->after('metadata');
            $table->timestamp('cashed_at')->nullable()->after('is_cashed');
            $table->bigInteger('cashed_amount_gol')->default(0)->after('cashed_at')->comment('مبلغ نقد شده به گل');
        });
    }

    public function down()
    {
        Schema::table('user_point_transactions', function (Blueprint $table) {
            $table->dropColumn(['is_cashed', 'cashed_at', 'cashed_amount_gol']);
        });
    }
};
