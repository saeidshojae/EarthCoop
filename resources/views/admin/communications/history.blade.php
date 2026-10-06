@extends('layouts.admin')

@section('title', 'تاریخچه تحویل - مرکز ارتباطات')
@section('page-title', 'تاریخچه تحویل ارتباطات')
@section('page-description', 'جست‌وجو و مشاهده وضعیت گیرندگان و تحویل پیام‌ها')

@section('content')
@php($labels = \App\Support\Communication\CommunicationAdminLabels::class)
<div class="container mx-auto px-4 py-6 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="text-2xl font-bold text-gray-900 dark:text-white">تاریخچه تحویل</h1><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">نمای فقط‌خواندنی گیرندگان و آخرین وضعیت تحویل هر پیام.</p></div>
        <div class="flex flex-wrap gap-2 text-sm"><a href="{{ route('admin.communications.index') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-gray-700 dark:border-gray-600 dark:text-gray-200">داشبورد</a><a href="{{ route('admin.communications.failures') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-gray-700 dark:border-gray-600 dark:text-gray-200">خطاها</a></div>
    </div>

    <form method="GET" action="{{ route('admin.communications.history') }}" class="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 md:grid-cols-3">
        <div>
            <label for="status" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">وضعیت تحویل</label>
            <select id="status" name="status" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                <option value="">همه وضعیت‌ها</option>
                @foreach($statuses as $availableStatus)<option value="{{ $availableStatus }}" @selected($status === $availableStatus)>{{ $labels::deliveryStatus($availableStatus) }}</option>@endforeach
            </select>
        </div>
        <div><label for="email" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">ایمیل گیرنده</label><input id="email" name="email" value="{{ $email }}" type="search" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white" placeholder="name@example.com"></div>
        <div class="flex items-end gap-2"><button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-white">اعمال فیلتر</button><a href="{{ route('admin.communications.history') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-gray-700 dark:border-gray-600 dark:text-gray-200">پاک کردن</a></div>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900/50"><tr class="text-right text-xs font-semibold text-gray-500 dark:text-gray-400"><th class="px-4 py-3">گیرنده</th><th class="px-4 py-3">وضعیت تحویل</th><th class="px-4 py-3">قالب پیام</th><th class="px-4 py-3">زمان ورود به صف</th><th class="px-4 py-3">آخرین ارسال / خطا</th></tr></thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($recipients as $recipient)
                    <tr class="text-sm text-gray-700 dark:text-gray-200">
                        <td class="px-4 py-3 font-medium">{{ $recipient->email }}</td>
                        <td class="px-4 py-3"><span class="rounded-full bg-gray-100 px-2 py-1 text-xs dark:bg-gray-700">{{ $labels::deliveryStatus($recipient->status) }}</span></td>
                        <td class="px-4 py-3"><div>{{ $recipient->templateVersion?->template?->name ?? '—' }}</div>@if($recipient->templateVersion?->template?->key)<div class="mt-1 font-mono text-xs text-gray-500">{{ $recipient->templateVersion->template->key }}</div>@endif</td>
                        <td class="px-4 py-3">@if($recipient->queued_at)<x-temporal.date-time :value="$recipient->queued_at" />@else — @endif</td>
                        <td class="px-4 py-3">@if($recipient->sent_at ?? $recipient->failed_at)<x-temporal.date-time :value="$recipient->sent_at ?? $recipient->failed_at" />@else — @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">موردی مطابق فیلترها پیدا نشد.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        @if($recipients->hasPages())<div class="border-t border-gray-200 px-4 py-3 dark:border-gray-700">{{ $recipients->links() }}</div>@endif
    </div>
</div>
@endsection
