<?php

namespace App\Exports;

use App\Helpers\BaharMoney;
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
            'تاریخ',
            'نوع تراکنش',
            'مبلغ (بهار)',
            'توضیحات',
            'وضعیت',
        ];
    }

    public function mapTransaction($transaction): array
    {
        $isIncoming = $this->account && isset($transaction->to_account_id) && $transaction->to_account_id == $this->account->id;

        return [
            \Morilog\Jalali\Jalalian::fromCarbon($transaction->created_at)->format('Y/m/d H:i'),
            $isIncoming ? 'ورودی' : 'خروجی',
            ($isIncoming ? '+' : '-') . BaharMoney::formatDecimalValue($transaction->amount),
            $transaction->description ?? 'تراکنش',
            'تکمیل شده',
        ];
    }
}

