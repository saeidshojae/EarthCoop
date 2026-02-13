<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Modules\NajmBahar\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NajmBaharActivateFaded extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'najm-bahar:activate-faded 
                            {--dry-run : نمایش عملیات بدون اعمال تغییرات}
                            {--amount= : مقدار فعال‌سازی (اختیاری - از تنظیمات خوانده می‌شود)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'فعال‌سازی دوره‌ای موجودی کمرنگ - تبدیل مقداری از موجودی فیدد به اکتیو';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $settings = Setting::first();
        
        // بررسی فعال بودن قابلیت
        if (!$settings || !$settings->najm_bahar_auto_activation_enabled) {
            $this->warn('⚠️ فعال‌سازی خودکار غیرفعال است.');
            $this->info('💡 برای فعال‌سازی به: تنظیمات نجم بهار > فعال‌سازی خودکار دوره‌ای');
            return Command::FAILURE;
        }

        // دریافت مقدار فعال‌سازی
        $activationAmount = $this->option('amount') 
            ? (int) ($this->option('amount') * 100) // تبدیل به گل
            : (int) ($settings->najm_bahar_auto_activation_amount ?? 0);

        if ($activationAmount <= 0) {
            $this->error('❌ مقدار فعال‌سازی باید بیشتر از صفر باشد.');
            return Command::FAILURE;
        }

        $isDryRun = $this->option('dry-run');
        $period = $settings->najm_bahar_auto_activation_period ?? 'monthly';

        $this->info("🔄 شروع فعال‌سازی دوره‌ای ({$period})");
        $this->info("💰 مقدار فعال‌سازی: " . number_format($activationAmount / 100, 2) . " بهار");
        
        if ($isDryRun) {
            $this->warn('⚡ حالت Dry Run - تغییرات اعمال نمی‌شود');
        }

        // دریافت حساب‌های کاربران با موجودی کمرنگ
        $accounts = Account::where('type', 'user')
            ->where('balance_faded', '>', 0)
            ->get();

        $this->info("📊 تعداد حساب‌ها: " . $accounts->count());

        $successCount = 0;
        $totalActivated = 0;

        $progressBar = $this->output->createProgressBar($accounts->count());
        $progressBar->start();

        foreach ($accounts as $account) {
            try {
                if ($isDryRun) {
                    // حالت نمایش
                    $amountToActivate = min($activationAmount, $account->balance_faded);
                    $totalActivated += $amountToActivate;
                    $successCount++;
                } else {
                    // اعمال تغییرات
                    $activated = $this->activateFadedBalance($account, $activationAmount);
                    if ($activated > 0) {
                        $totalActivated += $activated;
                        $successCount++;
                        
                        // ثبت لاگ
                        Log::info('NajmBahar: Faded balance activated', [
                            'account_number' => $account->account_number,
                            'user_id' => $account->user_id,
                            'amount' => $activated,
                            'period' => $period,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('NajmBahar: Failed to activate faded balance', [
                    'account_number' => $account->account_number,
                    'error' => $e->getMessage(),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // نمایش نتایج
        $this->info("✅ تعداد حساب‌های پردازش شده: {$successCount}");
        $this->info("💵 مجموع مبلغ فعال‌شده: " . number_format($totalActivated / 100, 2) . " بهار");

        if ($isDryRun) {
            $this->warn('⚠️ این فقط یک پیش‌نمایش بود. برای اعمال تغییرات، دستور را بدون --dry-run اجرا کنید.');
        } else {
            $this->info('🎉 فعال‌سازی دوره‌ای با موفقیت انجام شد!');
        }

        return Command::SUCCESS;
    }

    /**
     * تبدیل موجودی کمرنگ به فعال
     *
     * @param Account $account
     * @param int $amount مقدار به واحد گل
     * @return int مقدار فعال‌شده
     */
    private function activateFadedBalance(Account $account, int $amount): int
    {
        return DB::transaction(function () use ($account, $amount) {
            $account = Account::where('id', $account->id)->lockForUpdate()->first();

            if ($account->balance_faded <= 0) {
                return 0;
            }

            // محاسبه مقدار قابل فعال‌سازی
            $amountToActivate = min($amount, $account->balance_faded);

            // بروزرسانی موجودی‌ها
            $account->balance_faded -= $amountToActivate;
            $account->balance_active += $amountToActivate;
            // balance کل تغییر نمی‌کند
            $account->save();

            return $amountToActivate;
        });
    }
}
