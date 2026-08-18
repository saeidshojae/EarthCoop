<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('najm_sub_accounts')
            || ! Schema::hasColumn('najm_sub_accounts', 'balance_active')
            || ! Schema::hasColumn('najm_sub_accounts', 'balance_faded')
            || ! Schema::hasTable('najm_accounts')) {
            return;
        }

        DB::table('najm_sub_accounts')
            ->where('balance', '>', 0)
            ->where('balance_active', 0)
            ->where('balance_faded', 0)
            ->orderBy('id')
            ->chunkById(100, function ($subAccounts) {
                foreach ($subAccounts as $subAccount) {
                    $total = (int) $subAccount->balance;
                    $mirror = DB::table('najm_accounts')
                        ->where('account_number', $subAccount->sub_account_code)
                        ->where('type', 'subaccount')
                        ->first();

                    $active = 0;
                    $dim = $total;

                    if ($mirror) {
                        $mirrorActive = (int) ($mirror->balance_active ?? 0);
                        $mirrorDim = (int) ($mirror->balance_faded ?? 0);
                        $mirrorStateTotal = $mirrorActive + $mirrorDim;

                        // Prefer an already-classified mirror only when it fully
                        // accounts for the legacy child total. Never invent or
                        // rescale a partially inconsistent monetary state.
                        if ($mirrorStateTotal === $total && $mirrorStateTotal > 0) {
                            $active = $mirrorActive;
                            $dim = $mirrorDim;
                        }
                    }

                    DB::table('najm_sub_accounts')
                        ->where('id', $subAccount->id)
                        ->update([
                            'balance_active' => $active,
                            'balance_faded' => $dim,
                        ]);

                    // A completely unbucketed matching mirror is the same
                    // legacy condition. Classify it conservatively as Dim so
                    // the child and mirror become canonical without changing
                    // either stored total.
                    if ($mirror
                        && (int) ($mirror->balance ?? 0) === $total
                        && (int) ($mirror->balance_active ?? 0) === 0
                        && (int) ($mirror->balance_faded ?? 0) === 0) {
                        DB::table('najm_accounts')
                            ->where('id', $mirror->id)
                            ->update([
                                'balance_active' => 0,
                                'balance_faded' => $total,
                            ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Data classification is intentionally irreversible. Rolling this back
        // would erase provenance and could recreate an ambiguous legacy state.
    }
};
