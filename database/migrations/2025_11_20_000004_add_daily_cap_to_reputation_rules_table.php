<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (Schema::hasTable('reputation_rules')) {
            Schema::table('reputation_rules', function (Blueprint $table) {
                if (!Schema::hasColumn('reputation_rules', 'daily_cap')) {
                    $table->integer('daily_cap')->nullable()->after('weight');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('reputation_rules')) {
            Schema::table('reputation_rules', function (Blueprint $table) {
                if (Schema::hasColumn('reputation_rules', 'daily_cap')) {
                    $table->dropColumn('daily_cap');
                }
            });
        }
    }
};
