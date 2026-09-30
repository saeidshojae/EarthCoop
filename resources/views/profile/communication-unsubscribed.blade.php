@extends('layouts.unified')
@section('title', 'لغو دریافت ایمیل - ' . config('app.name', 'EarthCoop'))
@section('content')
<div class="container mx-auto max-w-2xl px-4 py-10">
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <h1 class="text-xl font-semibold">لغو دریافت انجام شد</h1>
        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">دریافت ایمیل اختیاری «{{ $topicKey }}» برای این حساب خاموش شد.</p>
        <p class="mt-2 text-xs text-gray-500">پیام‌های ضروری امنیتی و دسترسی با این لینک قابل غیرفعال‌سازی نیستند.</p>
    </div>
</div>
@endsection
