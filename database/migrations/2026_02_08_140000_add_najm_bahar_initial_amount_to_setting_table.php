<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('setting')) {
            return;
        }

        Schema::table('setting', function (Blueprint $table) {
            if (!Schema::hasColumn('setting', 'najm_bahar_initial_amount')) {
                $table->unsignedBigInteger('najm_bahar_initial_amount')->default(10000)->after('najm_bahar_user_threshold');
            }
        });

        DB::table('setting')
            ->whereNull('najm_bahar_initial_amount')
            ->update(['najm_bahar_initial_amount' => 10000]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('setting')) {
            return;
        }

        Schema::table('setting', function (Blueprint $table) {
            if (Schema::hasColumn('setting', 'najm_bahar_initial_amount')) {
                $table->dropColumn('najm_bahar_initial_amount');
            }
        });
    }
};
