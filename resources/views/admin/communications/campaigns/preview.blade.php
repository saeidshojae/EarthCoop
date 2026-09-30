@extends('layouts.admin')
@section('title', 'پیش‌نمایش کمپین - مرکز ارتباطات')
@section('page-title', 'پیش‌نمایش کمپین')
@section('content')
<div class="container mx-auto max-w-4xl px-4 py-6 space-y-5">
    @if($errors->any())<div class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>@endif
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="mb-4 flex items-start justify-between gap-4"><div><h1 class="text-xl font-semibold">{{ $campaign->name }}</h1><p class="text-sm text-gray-500">{{ $campaign->status }} · {{ $campaign->template?->key }}</p></div><a href="{{ route('admin.communications.campaigns.index') }}" class="text-sm underline">بازگشت</a></div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900"><div class="text-xs text-gray-500">Matched</div><div class="text-2xl font-semibold">{{ $counts['matched'] }}</div></div>
            <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900"><div class="text-xs text-gray-500">Eligible</div><div class="text-2xl font-semibold">{{ $counts['eligible'] }}</div></div>
            <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900"><div class="text-xs text-gray-500">Suppressed</div><div class="text-2xl font-semibold">{{ $counts['suppressed'] }}</div></div>
            <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900"><div class="text-xs text-gray-500">Invalid</div><div class="text-2xl font-semibold">{{ $counts['invalid'] }}</div></div>
        </div>
        <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">این صفحه فقط پیش‌نمایش مخاطبان است و هیچ ارسال واقعی ایجاد نمی‌کند.</p>
    </div>

    @if(in_array($campaign->status, ['draft', 'preview']))
        <form method="POST" action="{{ route('admin.communications.campaigns.confirm', $campaign) }}" class="rounded-xl border border-amber-200 bg-amber-50 p-5">
            @csrf
            <label class="flex items-start gap-2 text-sm text-amber-950"><input type="checkbox" name="elevated_confirmed" value="1" class="mt-1"><span>تعداد مخاطبان و پیامد ارسال گروهی را بررسی کرده‌ام. این تایید برای کمپین‌های بزرگ می‌تواند اجباری باشد.</span></label>
            <button class="mt-4 rounded-lg bg-gray-900 px-4 py-2 text-white">تایید نهایی کمپین</button>
        </form>
    @endif

    @if(in_array($campaign->status, ['running', 'scheduled']))
        <div class="flex gap-3">
            <form method="POST" action="{{ route('admin.communications.campaigns.pause', $campaign) }}">@csrf<button class="rounded-lg border border-gray-300 px-4 py-2">توقف موقت</button></form>
            <form method="POST" action="{{ route('admin.communications.campaigns.cancel', $campaign) }}">@csrf<button class="rounded-lg border border-red-300 px-4 py-2 text-red-700">لغو بخش ارسال‌نشده</button></form>
        </div>
    @endif
</div>
@endsection
