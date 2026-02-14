<?php

namespace App\Exports;

use App\Helpers\BaharMoney;
use App\Modules\NajmBahar\Models\SubAccount;
use Carbon\Carbon;

class NajmBaharTransactionsExport
{
    protected $transactions;
    protected $account;

    public function __construct($transactions, $account)
    {
        $this->transactions = $transactions;
        $this->account = $account;
    }

    public function collection()
    {
        return $this->getRows();
    }

    public function getRows()
    {
        $rows = [$this->getHeadings()];
        
        foreach ($this->transactions as $transaction) {
            $rows[] = $this->mapTransaction($transaction);
        }
        
        return $rows;
    }

    public function getHeadings(): array
    {
        return [
            'شماره رهگیری',
            'تاریخ',
            'از حساب (نام)',
            'از حساب (شماره)',
            'به حساب (نام)',
            'به حساب (شماره)',
            'نوع تراکنش',
            'مبلغ (بهار)',
            'توضیحات',
            'وضعیت',
        ];
    }

    public function mapTransaction($transaction): array
    {
        $isIncoming = $this->account && isset($transaction->to_account_id) && $transaction->to_account_id == $this->account->id;
        
        $fromAccount = $transaction->fromAccount;
        $toAccount = $transaction->toAccount;
        
        $fromAccountName = $this->getAccountLabel($fromAccount, 'from');
        $fromAccountNumber = $fromAccount?->account_number ?? '—';
        
        $toAccountName = $this->getAccountLabel($toAccount, 'to');
        $toAccountNumber = $toAccount?->account_number ?? '—';

        return [
            $transaction->tracking_number ?? '—',
            \Morilog\Jalali\Jalalian::fromCarbon($transaction->created_at)->format('Y/m/d H:i'),
            $fromAccountName,
            $fromAccountNumber,
            $toAccountName,
            $toAccountNumber,
            $isIncoming ? 'ورودی' : 'خروجی',
            ($isIncoming ? '+' : '-') . BaharMoney::formatDecimalValue($transaction->amount),
            $transaction->description ?? 'تراکنش',
            'تکمیل شده',
        ];
    }

    private function getAccountLabel($account, $type = 'from'): string
    {
        if (!$account) {
            return 'نامشخص';
        }

        if ($account->type === 'system') {
            return $type === 'to' ? 'EarthCoop' : 'سیستم';
        }

        if ($account->type === 'subaccount') {
            $subAccount = SubAccount::where('sub_account_code', $account->account_number)->first();
            $subAccountName = $subAccount?->name ?? 'حساب فرعی';
            $mainAccount = $subAccount?->account;

            if ($mainAccount && $mainAccount->type === 'system') {
                return 'EarthCoop - ' . $subAccountName;
            }

            $user = $mainAccount?->user ?? null;
            $userName = $user ? trim($user->first_name . ' ' . $user->last_name) : 'کاربر';
            return $userName . ' - ' . $subAccountName;
        }

        $user = $account->user ?? null;
        $userName = $user ? trim($user->first_name . ' ' . $user->last_name) : 'کاربر';
        return $userName;
    }
}

