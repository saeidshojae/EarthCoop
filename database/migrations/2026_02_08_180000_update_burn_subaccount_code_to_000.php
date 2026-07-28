<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('najm_sub_accounts')) {
            return;
        }

        $oldCode = '0000000000-003';
        $newCode = '0000000000-000';

        $existsNew = DB::table('najm_sub_accounts')->where('sub_account_code', $newCode)->exists();
        if (! $existsNew) {
            DB::table('najm_sub_accounts')
                ->where('sub_account_code', $oldCode)
                ->update(['sub_account_code' => $newCode]);
        }

        if (Schema::hasTable('najm_accounts')) {
            $existsNewAccount = DB::table('najm_accounts')->where('account_number', $newCode)->exists();
            if (! $existsNewAccount) {
                DB::table('najm_accounts')
                    ->where('account_number', $oldCode)
                    ->update(['account_number' => $newCode]);
            }
        }

        if (Schema::hasTable('setting')) {
            DB::table('setting')
                ->where('najm_bahar_membership_fee_burn_account', $oldCode)
                ->update(['najm_bahar_membership_fee_burn_account' => $newCode]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('najm_sub_accounts')) {
            return;
        }

        $oldCode = '0000000000-003';
        $newCode = '0000000000-000';

        DB::table('najm_sub_accounts')
            ->where('sub_account_code', $newCode)
            ->update(['sub_account_code' => $oldCode]);

        if (Schema::hasTable('najm_accounts')) {
            DB::table('najm_accounts')
                ->where('account_number', $newCode)
                ->update(['account_number' => $oldCode]);
        }

        if (Schema::hasTable('setting')) {
            DB::table('setting')
                ->where('najm_bahar_membership_fee_burn_account', $newCode)
                ->update(['najm_bahar_membership_fee_burn_account' => $oldCode]);
        }
    }
};
