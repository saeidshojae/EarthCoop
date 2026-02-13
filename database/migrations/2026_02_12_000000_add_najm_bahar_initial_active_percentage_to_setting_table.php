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
            if (!Schema::hasColumn('setting', 'najm_bahar_initial_active_percentage')) {
                $table->unsignedTinyInteger('najm_bahar_initial_active_percentage')
                    ->default(30)
                    ->after('najm_bahar_initial_amount')
                    ->comment('درصد مقدار اولیه که بصورت اکتیو واریز شود (0-100)');
            }
        });

        DB::table('setting')
            ->whereNull('najm_bahar_initial_active_percentage')
            ->update(['najm_bahar_initial_active_percentage' => 30]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('setting')) {
            return;
        }

        Schema::table('setting', function (Blueprint $table) {
            if (Schema::hasColumn('setting', 'najm_bahar_initial_active_percentage')) {
                $table->dropColumn('najm_bahar_initial_active_percentage');
            }
        });
    }
};
