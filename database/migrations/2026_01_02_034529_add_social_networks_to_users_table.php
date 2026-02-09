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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'social_networks')) {
                $table->text('social_networks')->nullable()->after('biografie');
            }
            if (!Schema::hasColumn('users', 'show_social_networks')) {
                $table->boolean('show_social_networks')->default(true)->after('social_networks');
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
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'social_networks')) {
                $table->dropColumn('social_networks');
            }
            if (Schema::hasColumn('users', 'show_social_networks')) {
                $table->dropColumn('show_social_networks');
            }
        });
    }
};
