@extends('layouts.admin')

@section('title', 'تراکنش‌های حساب - ' . config('app.name', 'EarthCoop'))

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">
            <i class="fas fa-exchange-alt ml-2"></i>
            تراکنش‌های حساب {{ $account->account_number }}
        </h1>
        <p class="text-slate-600 dark:text-slate-400 mt-1">لیست تراکنش‌های مرتبط با این حساب</p>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-4 py-2 text-right text-slate-700 dark:text-slate-300">مبلغ</th>
                        <th class="px-4 py-2 text-right text-slate-700 dark:text-slate-300">مبدا</th>
                        <th class="px-4 py-2 text-right text-slate-700 dark:text-slate-300">مقصد</th>
                        <th class="px-4 py-2 text-right text-slate-700 dark:text-slate-300">نوع</th>
                        <th class="px-4 py-2 text-right text-slate-700 dark:text-slate-300">توضیح</th>
                        <th class="px-4 py-2 text-right text-slate-700 dark:text-slate-300">تاریخ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($transactions as $transaction)
                        <tr>
                            <td class="px-4 py-2 font-semibold">{{ \App\Helpers\BaharMoney::formatDecimalHtml($transaction->amount) }}</td>
                            <td class="px-4 py-2">{{ $transaction->fromAccount->account_number ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $transaction->toAccount->account_number ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $transaction->type }}</td>
                            <td class="px-4 py-2">{{ $transaction->description }}</td>
                            <td class="px-4 py-2 text-slate-600 dark:text-slate-400">
                                {{ \Morilog\Jalali\Jalalian::fromCarbon($transaction->created_at)->format('Y/m/d H:i') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">تراکنشی یافت نشد</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $transactions->links() }}
        </div>
    </div>
</div>
@endsection

