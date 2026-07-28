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
            if (!Schema::hasColumn('users', 'biografie')) {
                $table->text('biografie')->nullable()->after('avatar');
            }
            if (!Schema::hasColumn('users', 'show_biografie')) {
                $table->boolean('show_biografie')->default(true)->after('biografie');
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
            if (Schema::hasColumn('users', 'biografie')) {
                $table->dropColumn('biografie');
            }
            if (Schema::hasColumn('users', 'show_biografie')) {
                $table->dropColumn('show_biografie');
            }
        });
    }
};
