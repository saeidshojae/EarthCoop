<?php

namespace App\Observers;

use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Models\Account;
use App\Models\User;
use App\Services\NotificationService;
use App\Helpers\BaharMoney;

class NajmBaharTransactionObserver
{
    /**
     * آستانه‌های پیش‌فرض
     */
    private const LOW_BALANCE_THRESHOLD = 100000; // 1000 بهار
    private const LARGE_TRANSACTION_THRESHOLD = 10000000; // 100,000 بهار
    
    /**
     * دریافت تنظیمات اعلان‌های کاربر از NotificationSetting اصلی
     */
    private function getNotificationSettings(int $userId): array
    {
        $settings = \App\Models\NotificationSetting::forUser($userId);
        
        // خواندن thresholdها از NotificationSetting اصلی
        $lowBalanceThreshold = $settings->najm_bahar_low_balance_threshold ?? self::LOW_BALANCE_THRESHOLD;
        $largeTransactionThreshold = $settings->najm_bahar_large_transaction_threshold ?? self::LARGE_TRANSACTION_THRESHOLD;
        
        return [
            'low_balance_threshold' => $lowBalanceThreshold,
            'large_transaction_threshold' => $largeTransactionThreshold,
            'settings' => $settings,
        ];
    }

    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        // فقط برای تراکنش‌های تکمیل شده
        if ($transaction->status !== 'completed') {
            return;
        }

        // ارسال اعلان به حساب مبدا (اگر کاربر باشد)
        if ($transaction->from_account_id) {
            $fromAccount = Account::find($transaction->from_account_id);
            if ($fromAccount && $fromAccount->user_id) {
                $user = User::find($fromAccount->user_id);
                if ($user) {
                    $this->notifyUser($user, $fromAccount, $transaction, 'out');
                }
            }
        }

        // ارسال اعلان به حساب مقصد (اگر کاربر باشد)
        if ($transaction->to_account_id) {
            $toAccount = Account::find($transaction->to_account_id);
            if ($toAccount && $toAccount->user_id) {
                $user = User::find($toAccount->user_id);
                if ($user) {
                    $this->notifyUser($user, $toAccount, $transaction, 'in');
                }
            }
        }
    }

    /**
     * ارسال اعلان به کاربر
     */
    private function notifyUser(User $user, Account $account, Transaction $transaction, string $type): void
    {
        $settingsData = $this->getNotificationSettings($user->id);
        $settings = $settingsData['settings'];
        $lowBalanceThreshold = $settingsData['low_balance_threshold'];
        $largeTransactionThreshold = $settingsData['large_transaction_threshold'];
        
        $isIncoming = $type === 'in';

        // 1. اعلان تراکنش عادی
        if ($settings->najm_bahar_transaction) {
            $title = $isIncoming ? 'دریافت وجه' : 'ارسال وجه';
            $message = $isIncoming
                ? "مبلغ " . BaharMoney::format($transaction->amount) . " به حساب شما واریز شد."
                : "مبلغ " . BaharMoney::format($transaction->amount) . " از حساب شما کسر شد.";
            
            if ($transaction->description) {
                $message .= " توضیحات: " . $transaction->description;
            }
            
            if ($account->balance !== null) {
                $message .= " موجودی فعلی: " . BaharMoney::format($account->balance);
            }

            app(NotificationService::class)->notifyUser(
                $user->id,
                $title,
                $message,
                route('najm-bahar.dashboard'),
                'najm-bahar.transaction',
                [
                    'transaction_id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'type' => $type,
                    'balance' => $account->balance,
                ]
            );
        }

        // 2. بررسی موجودی کم
        if ($settings->najm_bahar_low_balance && intval($account->balance) < $lowBalanceThreshold) {
            $title = 'هشدار موجودی کم';
            $message = "موجودی حساب شما (" . BaharMoney::format($account->balance) . ") کمتر از آستانه تعیین شده (" . BaharMoney::format($lowBalanceThreshold) . ") است.";
            
            app(NotificationService::class)->notifyUser(
                $user->id,
                $title,
                $message,
                route('najm-bahar.dashboard'),
                'najm-bahar.low-balance',
                [
                    'balance' => $account->balance,
                    'threshold' => $lowBalanceThreshold,
                ]
            );
        }

        // 3. بررسی تراکنش بزرگ
        if ($settings->najm_bahar_large_transaction && $transaction->amount >= $largeTransactionThreshold) {
            $title = $isIncoming 
                ? 'هشدار: تراکنش بزرگ (ورودی)' 
                : 'هشدار: تراکنش بزرگ (خروجی)';
            
            $message = "یک تراکنش " . ($isIncoming ? "ورودی" : "خروجی") . " بزرگ به مبلغ " . BaharMoney::format($transaction->amount) . " انجام شد.";
            
            if ($transaction->description) {
                $message .= " توضیحات: " . $transaction->description;
            }
            
            $message .= " (آستانه: " . BaharMoney::format($largeTransactionThreshold) . ")";

            app(NotificationService::class)->notifyUser(
                $user->id,
                $title,
                $message,
                route('najm-bahar.dashboard'),
                'najm-bahar.large-transaction',
                [
                    'transaction_id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'type' => $type,
                    'threshold' => $largeTransactionThreshold,
                ]
            );
        }
    }
}
