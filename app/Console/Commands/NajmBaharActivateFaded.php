<?php

namespace App\Console\Commands;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Services\MonetaryPolicyService;
use App\Modules\NajmBahar\Services\MonetaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NajmBaharActivateFaded extends Command
{
    protected $signature = 'najm-bahar:activate-faded
                            {--dry-run : نمایش عملیات بدون اعمال تغییرات}
                            {--amount= : مقدار فعال‌سازی به بهار (اختیاری - فقط برای اجرای دستی)}';

    protected $description = 'فعال‌سازی دوره‌ای موجودی کمرنگ - تبدیل مقداری از موجودی فیدد به اکتیو';

    public function handle(
        MonetaryService $monetaryService,
        MonetaryPolicyService $monetaryPolicyService
    ) {
        $policy = $monetaryPolicyService->current();
        $enabled = (bool) data_get($policy, 'parameters.auto_activation_enabled', false);

        if (!$enabled) {
            $this->warn('⚠️ فعال‌سازی خودکار غیرفعال است.');
            return Command::FAILURE;
        }

        $activationAmount = $this->option('amount') !== null
            ? $this->parseBaharToGol((string) $this->option('amount'))
            : (int) data_get($policy, 'parameters.auto_activation_amount_gol', 0);

        if ($activationAmount <= 0) {
            $this->error('❌ مقدار فعال‌سازی باید بیشتر از صفر باشد.');
            return Command::FAILURE;
        }

        $isDryRun = (bool) $this->option('dry-run');
        $period = (string) data_get($policy, 'parameters.auto_activation_period', 'monthly');
        $periodKey = $this->periodKey($period);
        $policyVersionId = $policy['version_id'];
        $policyVersion = $policy['version'];

        $this->info("🔄 شروع فعال‌سازی دوره‌ای ({$period})");
        $this->info('💰 مقدار فعال‌سازی: ' . number_format($activationAmount / 100, 2) . ' بهار');
        $this->info('📜 منبع سیاست: ' . $policy['source'] . ($policyVersion ? " / v{$policyVersion}" : ''));

        if ($isDryRun) {
            $this->warn('⚡ حالت Dry Run - تغییرات اعمال نمی‌شود');
        }

        $accounts = Account::where('type', 'user')
            ->where('balance_faded', '>', 0)
            ->get();

        $this->info('📊 تعداد حساب‌ها: ' . $accounts->count());

        $successCount = 0;
        $totalActivated = 0;
        $alreadyAppliedCount = 0;

        $progressBar = $this->output->createProgressBar($accounts->count());
        $progressBar->start();

        foreach ($accounts as $account) {
            try {
                $amountToActivate = min($activationAmount, (int) $account->balance_faded);

                if ($isDryRun) {
                    $totalActivated += $amountToActivate;
                    $successCount++;
                } else {
                    $result = $monetaryService->activateDim(
                        $account,
                        $activationAmount,
                        "فعال‌سازی خودکار دوره‌ای ({$periodKey})",
                        [
                            'type' => 'automatic_activation',
                            'user_id' => $account->user_id,
                            'period' => $period,
                            'period_key' => $periodKey,
                            'system_operation' => true,
                            'policy_version_id' => $policyVersionId,
                            'policy_version' => $policyVersion,
                        ],
                        'auto-activation-' . $periodKey . '-account-' . $account->id,
                        true
                    );

                    if ($result['applied']) {
                        $totalActivated += $result['amount'];
                        $successCount++;

                        Log::info('NajmBahar: Faded balance activated', [
                            'account_number' => $account->account_number,
                            'user_id' => $account->user_id,
                            'amount' => $result['amount'],
                            'period' => $period,
                            'period_key' => $periodKey,
                            'policy_version_id' => $policyVersionId,
                        ]);
                    } else {
                        $alreadyAppliedCount++;
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

        $this->info("✅ تعداد حساب‌های فعال‌شده: {$successCount}");
        if (!$isDryRun && $alreadyAppliedCount > 0) {
            $this->info("↩️ تعداد حساب‌های قبلاً پردازش‌شده در این دوره: {$alreadyAppliedCount}");
        }
        $this->info('💵 مجموع مبلغ فعال‌شده: ' . number_format($totalActivated / 100, 2) . ' بهار');

        if ($isDryRun) {
            $this->warn('⚠️ این فقط یک پیش‌نمایش بود. برای اعمال تغییرات، دستور را بدون --dry-run اجرا کنید.');
        } else {
            $this->info('🎉 فعال‌سازی دوره‌ای با موفقیت انجام شد!');
        }

        return Command::SUCCESS;
    }

    private function periodKey(string $period): string
    {
        return match ($period) {
            'yearly' => now()->format('Y'),
            'quarterly' => now()->format('Y') . '-Q' . (int) ceil(((int) now()->format('n')) / 3),
            default => now()->format('Y-m'),
        };
    }

    private function parseBaharToGol(string $value): int
    {
        $value = trim($value);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new \InvalidArgumentException('مقدار بهار نامعتبر است. حداکثر دو رقم اعشار مجاز است.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = str_pad($fraction, 2, '0');

        return ((int) $whole * 100) + (int) substr($fraction, 0, 2);
    }
}
