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
        Schema::table('group_user', function (Blueprint $table) {
            // اضافه کردن ستون status
            if (!Schema::hasColumn('group_user', 'status')) {
                $table->tinyInteger('status')->default(1)->after('role');
            }
            // اضافه کردن ستون expired
            if (!Schema::hasColumn('group_user', 'expired')) {
                $table->timestamp('expired')->nullable()->after('status');
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
        Schema::table('group_user', function (Blueprint $table) {
            //
        });
    }
};
