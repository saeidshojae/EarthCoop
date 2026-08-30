@extends('layouts.admin')
@section('title', 'جزئیات تسویه خارجی - EarthCoop')
@section('content')
<div class="max-w-6xl mx-auto space-y-6" dir="rtl">
    <section class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
        <div class="flex items-start justify-between gap-4"><div><h1 class="text-2xl font-bold text-slate-900 dark:text-white">جزئیات تسویه خارجی</h1><p class="mt-2 font-mono text-xs text-slate-500">{{ $intent->intent_key }}</p></div><span class="rounded-full bg-amber-100 text-amber-800 px-3 py-1 text-xs font-bold">فقط خواندنی</span></div>
        <div class="mt-6 grid grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
            <div class="rounded-xl bg-slate-50 dark:bg-slate-900 p-4"><div class="text-slate-500">وضعیت</div><strong>{{ $intent->status }}</strong></div>
            <div class="rounded-xl bg-slate-50 dark:bg-slate-900 p-4"><div class="text-slate-500">مبلغ</div><strong>{{ number_format((int)$intent->amount_minor) }} {{ $intent->currency }}</strong></div>
            <div class="rounded-xl bg-slate-50 dark:bg-slate-900 p-4"><div class="text-slate-500">Provider</div><strong>{{ $intent->provider ?: '—' }}</strong></div>
            <div class="rounded-xl bg-slate-50 dark:bg-slate-900 p-4"><div class="text-slate-500">مرجع</div><strong>{{ $intent->reference_type }} #{{ $intent->reference_id }}</strong></div>
        </div>
        <div class="mt-4 rounded-xl border border-blue-200 bg-blue-50 dark:bg-slate-900 p-4 text-sm text-blue-900 dark:text-blue-200">تغییر وضعیت پرداخت فقط از رویداد معتبر و قابل‌اثبات provider و سرویس reconciliation انجام می‌شود. این کنسول عمداً عملیات تأیید دستی پرداخت ندارد.</div>
    </section>
    <section class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="p-5 border-b border-slate-200 dark:border-slate-700"><h2 class="text-lg font-bold text-slate-900 dark:text-white">تاریخچه تطبیق</h2><p class="mt-1 text-xs text-slate-500">رویدادها append-only هستند و برای ممیزی حفظ می‌شوند.</p></div>
        <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 dark:bg-slate-900"><tr><th class="p-3 text-right">زمان</th><th class="p-3 text-right">نوع رویداد</th><th class="p-3 text-right">نتیجه</th><th class="p-3 text-right">Provider Event</th><th class="p-3 text-right">Provider Payment</th><th class="p-3 text-right">مبلغ</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-700">
        @forelse($intent->reconciliations as $event)<tr><td class="p-3">{{ optional($event->occurred_at)->format('Y-m-d H:i:s') ?: $event->created_at }}</td><td class="p-3">{{ $event->event_type }}</td><td class="p-3">{{ $event->result_status }}</td><td class="p-3 font-mono text-xs">{{ $event->provider_event_id ?: '—' }}</td><td class="p-3 font-mono text-xs">{{ $event->provider_payment_id ?: '—' }}</td><td class="p-3">{{ number_format((int)$event->amount_minor) }} {{ $event->currency }}</td></tr>
        @empty<tr><td colspan="6" class="p-8 text-center text-slate-500">هنوز رویداد reconciliation ثبت نشده است.</td></tr>@endforelse
        </tbody></table></div>
    </section>
    <a href="{{ route('admin.stock.external-payments.index') }}" class="inline-flex text-emerald-700 dark:text-emerald-400 font-bold">بازگشت به تسویه‌های خارجی</a>
</div>
@endsection
