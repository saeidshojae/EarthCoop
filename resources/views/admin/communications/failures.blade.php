@extends('layouts.admin')

@section('title', 'خطاهای تحویل - مرکز ارتباطات')
@section('page-title', 'خطاها و تلاش مجدد')
@section('page-description', 'مشاهده جزئیات خطا و سابقه تلاش‌های تحویل')

@section('content')
@php($labels = \App\Support\Communication\CommunicationAdminLabels::class)
<div class="container mx-auto px-4 py-6 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="text-2xl font-bold text-gray-900 dark:text-white">خطاها و تلاش مجدد</h1><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">نمای فقط‌خواندنی وضعیت تلاش مجدد و شکست‌های ثبت‌شده در تحویل پیام‌ها.</p></div>
        <div class="flex flex-wrap gap-2 text-sm"><a href="{{ route('admin.communications.index') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-gray-700 dark:border-gray-600 dark:text-gray-200">داشبورد</a><a href="{{ route('admin.communications.history') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-gray-700 dark:border-gray-600 dark:text-gray-200">تاریخچه تحویل</a></div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900/50"><tr class="text-right text-xs font-semibold text-gray-500 dark:text-gray-400"><th class="px-4 py-3">گیرنده</th><th class="px-4 py-3">وضعیت تحویل</th><th class="px-4 py-3">تعداد تلاش‌ها</th><th class="px-4 py-3">ماهیت خطا</th><th class="px-4 py-3">کد فنی خطا</th><th class="px-4 py-3">پیام خطا</th></tr></thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($recipients as $recipient)
                    @php($latestAttempt = $recipient->attempts->sortByDesc('attempt_number')->first())
                    <tr class="align-top text-sm text-gray-700 dark:text-gray-200">
                        <td class="px-4 py-3 font-medium">{{ $recipient->email }}</td>
                        <td class="px-4 py-3">{{ $labels::deliveryStatus($recipient->status) }}</td>
                        <td class="px-4 py-3">{{ $recipient->attempts->count() }} تلاش</td>
                        <td class="px-4 py-3">{{ $labels::failureClass($latestAttempt?->failure_class) }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $latestAttempt?->failure_code ?? '—' }}</td>
                        <td class="max-w-md px-4 py-3">{{ $latestAttempt?->failure_message ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">در حال حاضر خطا یا تلاش مجددی ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        @if($recipients->hasPages())<div class="border-t border-gray-200 px-4 py-3 dark:border-gray-700">{{ $recipients->links() }}</div>@endif
    </div>
</div>
@endsection
