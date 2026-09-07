@extends('layouts.admin')
@section('title', 'تسویه‌های خارجی - EarthCoop')
@section('content')
<div class="max-w-7xl mx-auto space-y-6" dir="rtl">
    <section class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">تسویه‌های خارجی</h1>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">کنسول عملیاتی Payment Intentها و وضعیت تطبیق درگاه؛ این صفحه هیچ پرداختی را دستی تأیید نمی‌کند.</p>
            </div>
            <span class="inline-flex self-start rounded-full bg-amber-100 text-amber-800 px-3 py-1 text-xs font-bold">فقط خواندنی</span>
        </div>
        <form method="GET" class="mt-5 grid grid-cols-1 md:grid-cols-3 gap-3">
            <select name="status" class="rounded-xl border-slate-300 dark:bg-slate-900 dark:border-slate-600">
                <option value="">همه وضعیت‌ها</option>
                @foreach(['created','pending','confirmed','failed','cancelled','refunded','reversed'] as $status)
                    <option value="{{ $status }}" @selected($selectedStatus === $status)>{{ $status }}</option>
                @endforeach
            </select>
            <select name="currency" class="rounded-xl border-slate-300 dark:bg-slate-900 dark:border-slate-600">
                <option value="">همه ارزها</option><option value="IRR" @selected($selectedCurrency === 'IRR')>IRR</option><option value="USD" @selected($selectedCurrency === 'USD')>USD</option>
            </select>
            <button class="rounded-xl bg-emerald-600 text-white px-4 py-2 font-bold">اعمال فیلتر</button>
        </form>
    </section>
    <section class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-300"><tr><th class="p-3 text-right">Intent</th><th class="p-3 text-right">وضعیت</th><th class="p-3 text-right">مبلغ</th><th class="p-3 text-right">ارز</th><th class="p-3 text-right">Provider</th><th class="p-3 text-right">مرجع</th><th class="p-3 text-right">رویدادها</th><th class="p-3"></th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse($intents as $intent)
                <tr class="text-slate-800 dark:text-slate-100"><td class="p-3 font-mono text-xs">{{ $intent->intent_key }}</td><td class="p-3">{{ $intent->status }}</td><td class="p-3">{{ number_format((int)$intent->amount_minor) }}</td><td class="p-3">{{ $intent->currency }}</td><td class="p-3">{{ $intent->provider ?: '—' }}</td><td class="p-3">{{ $intent->reference_type }} #{{ $intent->reference_id }}</td><td class="p-3">{{ $intent->reconciliations_count }}</td><td class="p-3"><a class="text-emerald-700 dark:text-emerald-400 font-bold" href="{{ route('admin.stock.external-payments.show', $intent) }}">جزئیات</a></td></tr>
            @empty<tr><td colspan="8" class="p-8 text-center text-slate-500">هنوز Payment Intent خارجی ثبت نشده است.</td></tr>@endforelse
            </tbody>
        </table></div>
        @if($intents->hasPages())<div class="p-4 border-t border-slate-200 dark:border-slate-700">{{ $intents->links() }}</div>@endif
    </section>
</div>
@endsection
