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
            if (!Schema::hasColumn('setting', 'najm_bahar_membership_fee_amount')) {
                $table->unsignedInteger('najm_bahar_membership_fee_amount')->default(12)->after('najm_bahar_membership_fee_burn_account');
            }
        });

        DB::table('setting')
            ->whereNull('najm_bahar_membership_fee_amount')
            ->update(['najm_bahar_membership_fee_amount' => 12]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('setting')) {
            return;
        }

        Schema::table('setting', function (Blueprint $table) {
            if (Schema::hasColumn('setting', 'najm_bahar_membership_fee_amount')) {
                $table->dropColumn('najm_bahar_membership_fee_amount');
            }
        });
    }
};
