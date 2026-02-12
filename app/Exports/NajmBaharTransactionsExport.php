<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use App\Helpers\BaharMoney;

class NajmBaharTransactionsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
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
        return $this->transactions;
    }

    public function headings(): array
    {
        return [
            'تاریخ',
            'نوع تراکنش',
            'مبلغ (بهار)',
            'توضیحات',
            'وضعیت',
        ];
    }

    public function map($transaction): array
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

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E0F2FE']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'گزارش تراکنش‌ها';
    }
}

