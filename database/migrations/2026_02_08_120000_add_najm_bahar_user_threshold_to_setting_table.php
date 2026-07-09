<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('setting')) {
            return;
        }

        Schema::table('setting', function (Blueprint $table) {
            if (!Schema::hasColumn('setting', 'najm_bahar_user_threshold')) {
                $table->unsignedBigInteger('najm_bahar_user_threshold')->default(1111111)->after('home_content');
            }
        });

        DB::table('setting')
            ->whereNull('najm_bahar_user_threshold')
            ->update(['najm_bahar_user_threshold' => 1111111]);
    }

    public function down()
    {
        if (!Schema::hasTable('setting')) {
            return;
        }

        Schema::table('setting', function (Blueprint $table) {
            if (Schema::hasColumn('setting', 'najm_bahar_user_threshold')) {
                $table->dropColumn('najm_bahar_user_threshold');
            }
        });
    }
};
