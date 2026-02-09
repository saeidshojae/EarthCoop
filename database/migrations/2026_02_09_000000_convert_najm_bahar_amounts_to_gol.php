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
            if (!Schema::hasColumn('setting', 'najm_bahar_amounts_in_gol')) {
                $table->boolean('najm_bahar_amounts_in_gol')->default(false)->after('najm_bahar_membership_fee_amount');
            }
        });

        $settings = DB::table('setting')->first();
        if (!$settings || !empty($settings->najm_bahar_amounts_in_gol)) {
            return;
        }

        DB::transaction(function () {
            $multiplier = 100;

            if (Schema::hasTable('najm_accounts')) {
                DB::table('najm_accounts')->update([
                    'balance' => DB::raw("balance * {$multiplier}")
                ]);
            }

            if (Schema::hasTable('najm_sub_accounts')) {
                DB::table('najm_sub_accounts')->update([
                    'balance' => DB::raw("balance * {$multiplier}")
                ]);
            }

            if (Schema::hasTable('najm_transactions')) {
                DB::table('najm_transactions')->update([
                    'amount' => DB::raw("amount * {$multiplier}")
                ]);
            }

            if (Schema::hasTable('najm_ledger_entries')) {
                DB::table('najm_ledger_entries')->update([
                    'amount' => DB::raw("amount * {$multiplier}")
                ]);
            }

            if (Schema::hasTable('najm_bahar_fees')) {
                DB::table('najm_bahar_fees')->update([
                    'fixed_amount' => DB::raw("fixed_amount * {$multiplier}"),
                    'min_amount' => DB::raw("min_amount * {$multiplier}"),
                    'max_amount' => DB::raw("max_amount * {$multiplier}")
                ]);
            }

            if (Schema::hasTable('notification_settings')) {
                if (Schema::hasColumn('notification_settings', 'najm_bahar_low_balance_threshold')) {
                    DB::table('notification_settings')->update([
                        'najm_bahar_low_balance_threshold' => DB::raw("najm_bahar_low_balance_threshold * {$multiplier}")
                    ]);
                }
                if (Schema::hasColumn('notification_settings', 'najm_bahar_large_transaction_threshold')) {
                    DB::table('notification_settings')->update([
                        'najm_bahar_large_transaction_threshold' => DB::raw("najm_bahar_large_transaction_threshold * {$multiplier}")
                    ]);
                }
            }

            $settingUpdates = [];
            if (Schema::hasColumn('setting', 'najm_bahar_initial_amount')) {
                $settingUpdates['najm_bahar_initial_amount'] = DB::raw("najm_bahar_initial_amount * {$multiplier}");
            }
            if (Schema::hasColumn('setting', 'najm_bahar_membership_fee_amount')) {
                $settingUpdates['najm_bahar_membership_fee_amount'] = DB::raw("najm_bahar_membership_fee_amount * {$multiplier}");
            }
            if (Schema::hasColumn('setting', 'najm_bahar_membership_fee_membership_amount')) {
                $settingUpdates['najm_bahar_membership_fee_membership_amount'] = DB::raw("najm_bahar_membership_fee_membership_amount * {$multiplier}");
            }
            if (Schema::hasColumn('setting', 'najm_bahar_membership_fee_insurance_amount')) {
                $settingUpdates['najm_bahar_membership_fee_insurance_amount'] = DB::raw("najm_bahar_membership_fee_insurance_amount * {$multiplier}");
            }
            if (Schema::hasColumn('setting', 'najm_bahar_membership_fee_burn_amount')) {
                $settingUpdates['najm_bahar_membership_fee_burn_amount'] = DB::raw("najm_bahar_membership_fee_burn_amount * {$multiplier}");
            }

            if (!empty($settingUpdates)) {
                DB::table('setting')->update($settingUpdates);
            }

            DB::table('setting')->update(['najm_bahar_amounts_in_gol' => true]);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('setting') || !Schema::hasColumn('setting', 'najm_bahar_amounts_in_gol')) {
            return;
        }

        $settings = DB::table('setting')->first();
        if (!$settings || empty($settings->najm_bahar_amounts_in_gol)) {
            return;
        }

        DB::transaction(function () {
            $divisor = 100;

            if (Schema::hasTable('najm_accounts')) {
                DB::table('najm_accounts')->update([
                    'balance' => DB::raw("balance / {$divisor}")
                ]);
            }

            if (Schema::hasTable('najm_sub_accounts')) {
                DB::table('najm_sub_accounts')->update([
                    'balance' => DB::raw("balance / {$divisor}")
                ]);
            }

            if (Schema::hasTable('najm_transactions')) {
                DB::table('najm_transactions')->update([
                    'amount' => DB::raw("amount / {$divisor}")
                ]);
            }

            if (Schema::hasTable('najm_ledger_entries')) {
                DB::table('najm_ledger_entries')->update([
                    'amount' => DB::raw("amount / {$divisor}")
                ]);
            }

            if (Schema::hasTable('najm_bahar_fees')) {
                DB::table('najm_bahar_fees')->update([
                    'fixed_amount' => DB::raw("fixed_amount / {$divisor}"),
                    'min_amount' => DB::raw("min_amount / {$divisor}"),
                    'max_amount' => DB::raw("max_amount / {$divisor}")
                ]);
            }

            if (Schema::hasTable('notification_settings')) {
                if (Schema::hasColumn('notification_settings', 'najm_bahar_low_balance_threshold')) {
                    DB::table('notification_settings')->update([
                        'najm_bahar_low_balance_threshold' => DB::raw("najm_bahar_low_balance_threshold / {$divisor}")
                    ]);
                }
                if (Schema::hasColumn('notification_settings', 'najm_bahar_large_transaction_threshold')) {
                    DB::table('notification_settings')->update([
                        'najm_bahar_large_transaction_threshold' => DB::raw("najm_bahar_large_transaction_threshold / {$divisor}")
                    ]);
                }
            }

            $settingUpdates = [];
            if (Schema::hasColumn('setting', 'najm_bahar_initial_amount')) {
                $settingUpdates['najm_bahar_initial_amount'] = DB::raw("najm_bahar_initial_amount / {$divisor}");
            }
            if (Schema::hasColumn('setting', 'najm_bahar_membership_fee_amount')) {
                $settingUpdates['najm_bahar_membership_fee_amount'] = DB::raw("najm_bahar_membership_fee_amount / {$divisor}");
            }
            if (Schema::hasColumn('setting', 'najm_bahar_membership_fee_membership_amount')) {
                $settingUpdates['najm_bahar_membership_fee_membership_amount'] = DB::raw("najm_bahar_membership_fee_membership_amount / {$divisor}");
            }
            if (Schema::hasColumn('setting', 'najm_bahar_membership_fee_insurance_amount')) {
                $settingUpdates['najm_bahar_membership_fee_insurance_amount'] = DB::raw("najm_bahar_membership_fee_insurance_amount / {$divisor}");
            }
            if (Schema::hasColumn('setting', 'najm_bahar_membership_fee_burn_amount')) {
                $settingUpdates['najm_bahar_membership_fee_burn_amount'] = DB::raw("najm_bahar_membership_fee_burn_amount / {$divisor}");
            }

            if (!empty($settingUpdates)) {
                DB::table('setting')->update($settingUpdates);
            }

            DB::table('setting')->update(['najm_bahar_amounts_in_gol' => false]);
        });
    }
};
