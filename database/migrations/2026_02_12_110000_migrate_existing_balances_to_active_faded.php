<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تبدیل موجودی‌های قبلی به فرمت active/faded
     * این migration موجودی‌های قبلی را که فقط در فیلد balance هستند
     * را بر اساس درصد تنظیم‌شده به active و faded تقسیم می‌کند
     */
    public function up(): void
    {
        if (!Schema::hasTable('najm_accounts')) {
            return;
        }

        $activePercentage = 30;
        if (Schema::hasTable('setting')) {
            $settings = DB::table('setting')->first();
            $activePercentage = (int) ($settings->najm_bahar_initial_active_percentage ?? 30);
        } elseif (Schema::hasTable('settings')) {
            $settings = DB::table('settings')->first();
            $activePercentage = (int) ($settings->najm_bahar_initial_active_percentage ?? 30);
        }

        // پیدا کردن حساب‌هایی که balance دارند اما active/faded صفر است
        DB::table('najm_accounts')
            ->where('balance', '>', 0)
            ->where('balance_active', 0)
            ->where('balance_faded', 0)
            ->where('type', 'user')
            ->chunkById(100, function ($accounts) use ($activePercentage) {
                foreach ($accounts as $account) {
                    $balance = (int) $account->balance;
                    
                    // محاسبه active و faded
                    $activeAmount = intval(($balance * $activePercentage) / 100);
                    $fadedAmount = $balance - $activeAmount;

                    // بروزرسانی
                    DB::table('najm_accounts')
                        ->where('id', $account->id)
                        ->update([
                            'balance_active' => $activeAmount,
                            'balance_faded' => $fadedAmount,
                        ]);

                    // Log برای کنترل
                    \Log::info("Migrated account balance", [
                        'account_number' => $account->account_number,
                        'total_balance' => $balance,
                        'active_amount' => $activeAmount,
                        'faded_amount' => $fadedAmount,
                        'percentage' => $activePercentage,
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('najm_accounts')) {
            return;
        }

        // برگشت: تمام موجودی‌های active و faded را صفر می‌کنیم
        // (balance اصلی را نگه می‌داریم)
        DB::table('najm_accounts')->update([
            'balance_active' => 0,
            'balance_faded' => 0,
        ]);
    }
};
