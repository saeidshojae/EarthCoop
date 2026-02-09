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

        if (Schema::hasTable('najm_sub_accounts')) {
            DB::table('najm_sub_accounts')->where('sub_account_code', $oldCode)->delete();
        }

        if (Schema::hasTable('najm_accounts')) {
            DB::table('najm_accounts')->where('account_number', $oldCode)->delete();
        }
    }

    public function down(): void
    {
        // No automatic restore for removed account.
    }
};
