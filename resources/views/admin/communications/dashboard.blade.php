@extends('layouts.admin')

@section('title', 'مرکز ارتباطات - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'مرکز ارتباطات')
@section('page-description', 'نمای وضعیت صف، تحویل و قواعد ارتباطی')

@section('content')
<div class="container mx-auto px-4 py-6 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">مرکز ارتباطات</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">نمای مدیریتیِ فقط‌خواندنی برای مشاهده سلامت صف و وضعیت تحویل پیام‌ها.</p>
        </div>
        <div class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('admin.communications.index') }}" class="rounded-lg bg-gray-900 px-3 py-2 text-white">داشبورد</a>
            <a href="{{ route('admin.communications.history') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-gray-700 dark:border-gray-600 dark:text-gray-200">تاریخچه تحویل</a>
            <a href="{{ route('admin.communications.failures') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-gray-700 dark:border-gray-600 dark:text-gray-200">خطاها و تلاش مجدد</a>
        </div>
    </div>

    @php
        $cards = [
            ['label' => 'در صف', 'value' => $metrics['queued'], 'icon' => 'fa-clock'],
            ['label' => 'ارسال‌شده', 'value' => $metrics['sent'], 'icon' => 'fa-check-circle'],
            ['label' => 'در حال تلاش مجدد', 'value' => $metrics['retrying'], 'icon' => 'fa-redo'],
            ['label' => 'ناموفق دائمی', 'value' => $metrics['failed'], 'icon' => 'fa-exclamation-triangle'],
            ['label' => 'قواعد فعال', 'value' => $metrics['active_rules'], 'icon' => 'fa-bolt'],
            ['label' => 'زمان‌بندی‌های سررسیدشده', 'value' => $metrics['due_runs'], 'icon' => 'fa-calendar-check'],
            ['label' => 'زمان‌بندی‌های پیش‌رو', 'value' => $metrics['upcoming_runs'], 'icon' => 'fa-calendar-alt'],
        ];
    @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($cards as $card)
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <div><p class="text-sm text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p><p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($card['value']) }}</p></div>
                    <i class="fas {{ $card['icon'] }} text-xl text-gray-400"></i>
                </div>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-200">
        سلامت صف در این صفحه فقط بر پایهٔ سوابق ثبت‌شده در پایگاه داده گزارش می‌شود؛ وضعیت بیرونی پردازشگر صف یا سرویس ارسال ایمیل از روی حدس نمایش داده نمی‌شود.
    </div>
</div>
@endsection
