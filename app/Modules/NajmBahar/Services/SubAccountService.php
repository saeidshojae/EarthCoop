<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\AccountNumberService;
use Illuminate\Support\Facades\DB;

class SubAccountService
{
    /**
     * ایجاد حساب فرعی برای یک حساب اصلی
     */
    public function createSubAccount(int $accountId, string $name = null): SubAccount
    {
        $account = Account::findOrFail($accountId);
        
        // پیدا کردن آخرین شماره حساب فرعی
        $lastSubAccount = SubAccount::where('account_id', $accountId)
            ->orderBy('sub_account_code', 'desc')
            ->first();
        
        // تولید شماره حساب فرعی جدید
        $subAccountCode = $this->generateSubAccountCode($account->account_number, $lastSubAccount);
        
        return SubAccount::create([
            'account_id' => $accountId,
            'sub_account_code' => $subAccountCode,
            'name' => $name ?? 'حساب فرعی ' . $subAccountCode,
            'balance' => 0,
            'status' => 1,
        ]);
    }

    /**
     * تولید شماره حساب فرعی
     */
    private function generateSubAccountCode(string $accountNumber, ?SubAccount $lastSubAccount = null): string
    {
        if ($lastSubAccount) {
            // استخراج شماره آخرین حساب فرعی
            $parts = explode('-', $lastSubAccount->sub_account_code);
            $lastNumber = isset($parts[1]) ? intval($parts[1]) : 0;
            $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '001';
        }
        
        return $accountNumber . '-' . $newNumber;
    }

    /**
     * دریافت تمام حساب‌های فرعی یک حساب اصلی
     */
    public function getSubAccountsForAccount(int $accountId): \Illuminate\Database\Eloquent\Collection
    {
        return SubAccount::where('account_id', $accountId)
            ->where('status', 1)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * دریافت تمام حساب‌های فرعی (فعال و غیرفعال)
     */
    public function getAllSubAccountsForAccount(int $accountId): \Illuminate\Database\Eloquent\Collection
    {
        return SubAccount::where('account_id', $accountId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * انتقال وجه از حساب اصلی به حساب فرعی
     */
    public function transferToSubAccount(int $accountId, int $subAccountId, int $amount, string $description = null): void
    {
        DB::transaction(function () use ($accountId, $subAccountId, $amount, $description) {
            $account = Account::lockForUpdate()->findOrFail($accountId);
            $subAccount = SubAccount::lockForUpdate()->findOrFail($subAccountId);
            
            if ($subAccount->account_id !== $accountId) {
                throw new \RuntimeException('SubAccount does not belong to this account');
            }

            if ($subAccount->status !== 1) {
                throw new \RuntimeException('SubAccount is inactive');
            }
            
            if ($account->balance < $amount) {
                throw new \RuntimeException('Insufficient funds');
            }
            
            $account->balance -= $amount;
            $account->save();
            
            $subAccount->balance += $amount;
            $subAccount->save();
        });
    }

    /**
     * انتقال وجه از حساب فرعی به حساب اصلی
     */
    public function transferFromSubAccount(int $subAccountId, int $accountId, int $amount, string $description = null): void
    {
        DB::transaction(function () use ($subAccountId, $accountId, $amount, $description) {
            $subAccount = SubAccount::lockForUpdate()->findOrFail($subAccountId);
            $account = Account::lockForUpdate()->findOrFail($accountId);
            
            if ($subAccount->account_id !== $accountId) {
                throw new \RuntimeException('SubAccount does not belong to this account');
            }

            if ($subAccount->status !== 1) {
                throw new \RuntimeException('SubAccount is inactive');
            }
            
            if ($subAccount->balance < $amount) {
                throw new \RuntimeException('Insufficient funds in sub-account');
            }
            
            $subAccount->balance -= $amount;
            $subAccount->save();
            
            $account->balance += $amount;
            $account->save();
        });
    }

    /**
     * انتقال وجه بین حساب‌های فرعی
     */
    public function transferBetweenSubAccounts(int $fromSubAccountId, int $toSubAccountId, int $amount, string $description = null): void
    {
        DB::transaction(function () use ($fromSubAccountId, $toSubAccountId, $amount, $description) {
            $fromSubAccount = SubAccount::lockForUpdate()->findOrFail($fromSubAccountId);
            $toSubAccount = SubAccount::lockForUpdate()->findOrFail($toSubAccountId);

            if ($fromSubAccount->id === $toSubAccount->id) {
                throw new \RuntimeException('Source and destination sub-accounts are the same');
            }

            if ($fromSubAccount->status !== 1 || $toSubAccount->status !== 1) {
                throw new \RuntimeException('Sub-account is inactive');
            }

            if ($fromSubAccount->balance < $amount) {
                throw new \RuntimeException('Insufficient funds in sub-account');
            }

            $fromSubAccount->balance -= $amount;
            $fromSubAccount->save();

            $toSubAccount->balance += $amount;
            $toSubAccount->save();
        });
    }

    /**
     * غیرفعال کردن حساب فرعی
     */
    public function deactivateSubAccount(int $subAccountId): void
    {
        $subAccount = SubAccount::findOrFail($subAccountId);
        
        $subAccount->status = 0;
        $subAccount->save();
    }

    /**
     * فعال سازی مجدد حساب فرعی
     */
    public function activateSubAccount(int $subAccountId): void
    {
        $subAccount = SubAccount::findOrFail($subAccountId);
        $subAccount->status = 1;
        $subAccount->save();
    }
}

