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
            if (!Schema::hasColumn('setting', 'najm_bahar_membership_fee_account')) {
                $table->string('najm_bahar_membership_fee_account', 32)
                    ->default('0000000000-001')
                    ->after('najm_bahar_initial_amount');
            }
            if (!Schema::hasColumn('setting', 'najm_bahar_membership_fee_insurance_account')) {
                $table->string('najm_bahar_membership_fee_insurance_account', 32)
                    ->default('0000000000-002')
                    ->after('najm_bahar_membership_fee_account');
            }
            if (!Schema::hasColumn('setting', 'najm_bahar_membership_fee_burn_account')) {
                $table->string('najm_bahar_membership_fee_burn_account', 32)
                    ->default('0000000000-003')
                    ->after('najm_bahar_membership_fee_insurance_account');
            }
            if (!Schema::hasColumn('setting', 'najm_bahar_membership_fee_membership_amount')) {
                $table->unsignedBigInteger('najm_bahar_membership_fee_membership_amount')
                    ->default(6)
                    ->after('najm_bahar_membership_fee_burn_account');
            }
            if (!Schema::hasColumn('setting', 'najm_bahar_membership_fee_insurance_amount')) {
                $table->unsignedBigInteger('najm_bahar_membership_fee_insurance_amount')
                    ->default(3)
                    ->after('najm_bahar_membership_fee_membership_amount');
            }
            if (!Schema::hasColumn('setting', 'najm_bahar_membership_fee_burn_amount')) {
                $table->unsignedBigInteger('najm_bahar_membership_fee_burn_amount')
                    ->default(3)
                    ->after('najm_bahar_membership_fee_insurance_amount');
            }
        });

        DB::table('setting')
            ->whereNull('najm_bahar_membership_fee_account')
            ->update([
                'najm_bahar_membership_fee_account' => '0000000000-001',
                'najm_bahar_membership_fee_insurance_account' => '0000000000-002',
                'najm_bahar_membership_fee_burn_account' => '0000000000-003',
                'najm_bahar_membership_fee_membership_amount' => 6,
                'najm_bahar_membership_fee_insurance_amount' => 3,
                'najm_bahar_membership_fee_burn_amount' => 3,
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('setting')) {
            return;
        }

        Schema::table('setting', function (Blueprint $table) {
            $columns = [
                'najm_bahar_membership_fee_account',
                'najm_bahar_membership_fee_insurance_account',
                'najm_bahar_membership_fee_burn_account',
                'najm_bahar_membership_fee_membership_amount',
                'najm_bahar_membership_fee_insurance_amount',
                'najm_bahar_membership_fee_burn_amount',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('setting', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
