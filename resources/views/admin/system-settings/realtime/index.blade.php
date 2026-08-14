@extends('layouts.admin')

@section('title', 'تنظیمات ارتباط بلادرنگ')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl" x-data="{
    transport: @js(old('transport', $settings->transport)),
    provider: @js(old('provider', $settings->provider)),
    useEnv: @js((bool) old('use_env_credentials', $settings->use_env_credentials)),
    enabled: @js((bool) old('enabled', $settings->enabled))
}">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
        <div>
            <a href="{{ route('admin.system-settings.index') }}" class="text-sm text-slate-500 hover:text-emerald-600">
                <i class="fas fa-arrow-right ml-1"></i> بازگشت به تنظیمات سیستمی
            </a>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white mt-2">
                <i class="fas fa-tower-broadcast text-emerald-500 ml-2"></i> تنظیمات ارتباط بلادرنگ
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">مدیریت Polling، Reverb، Soketi و Pusher بدون نیاز به ساخت مجدد فایل‌های فرانت‌اند</p>
        </div>
        <form action="{{ route('admin.system-settings.realtime.test') }}" method="POST">
            @csrf
            <button class="px-5 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold shadow">
                <i class="fas fa-plug ml-2"></i> آزمایش تنظیمات ذخیره‌شده
            </button>
        </form>
    </div>

    @if(session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800 p-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 text-red-800 p-4">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 text-red-800 p-4">
            <ul class="list-disc pr-5 space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <div class="rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-5">
            <div class="text-sm text-slate-500">حالت مؤثر</div>
            <div class="text-xl font-bold mt-2">{{ strtoupper($effective['transport']) }}</div>
        </div>
        <div class="rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-5">
            <div class="text-sm text-slate-500">ارائه‌دهنده</div>
            <div class="text-xl font-bold mt-2">{{ ucfirst($effective['provider']) }}</div>
        </div>
        <div class="rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-5">
            <div class="text-sm text-slate-500">دفتر رویدادها</div>
            <div class="text-xl font-bold mt-2 {{ $health['journal_available'] ? 'text-emerald-600' : 'text-red-600' }}">
                {{ $health['journal_available'] ? number_format($health['journal_events']) . ' رویداد' : 'در دسترس نیست' }}
            </div>
        </div>
        <div class="rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-5">
            <div class="text-sm text-slate-500">آخرین آزمایش</div>
            <div class="text-lg font-bold mt-2 {{ $settings->last_test_status === 'success' ? 'text-emerald-600' : ($settings->last_test_status === 'failed' ? 'text-red-600' : '') }}">
                {{ $settings->last_test_status === 'success' ? 'موفق' : ($settings->last_test_status === 'failed' ? 'ناموفق' : 'انجام نشده') }}
            </div>
            @if($settings->last_tested_at)<div class="text-xs text-slate-500 mt-1">{{ $settings->last_tested_at->format('Y-m-d H:i:s') }}</div>@endif
        </div>
    </div>

    <form action="{{ route('admin.system-settings.realtime.update') }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-5 md:p-7 shadow-sm">
            <h2 class="text-lg font-bold mb-5">حالت اجرا</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach([
                    'polling' => ['Polling', 'مناسب هاست اشتراکی؛ بدون نیاز به سرویس WebSocket'],
                    'auto' => ['Auto', 'WebSocket در اولویت و Polling به‌عنوان مسیر بازیابی'],
                    'websocket' => ['WebSocket', 'استفاده اجباری از ارتباط بلادرنگ؛ fallback اختیاری'],
                ] as $value => [$title, $description])
                    <label class="cursor-pointer rounded-xl border p-4 transition" :class="transport === '{{ $value }}' ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20' : 'border-slate-200 dark:border-slate-700'">
                        <input type="radio" name="transport" value="{{ $value }}" x-model="transport" class="ml-2">
                        <span class="font-bold">{{ $title }}</span>
                        <span class="block text-sm text-slate-500 mt-2">{{ $description }}</span>
                    </label>
                @endforeach
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-5">
                <label class="flex items-center gap-3 rounded-xl bg-slate-50 dark:bg-slate-900 p-4">
                    <input type="checkbox" name="enabled" value="1" x-model="enabled">
                    <span><strong class="block">فعال بودن همگام‌سازی گروه</strong><small class="text-slate-500">در حالت خاموش هیچ رویداد زنده‌ای منتشر نمی‌شود.</small></span>
                </label>
                <label class="flex items-center gap-3 rounded-xl bg-slate-50 dark:bg-slate-900 p-4">
                    <input type="checkbox" name="fallback_to_polling" value="1" {{ old('fallback_to_polling', $settings->fallback_to_polling) ? 'checked' : '' }}>
                    <span><strong class="block">Fallback به Polling</strong><small class="text-slate-500">در قطع WebSocket رویدادها از journal بازیابی شوند.</small></span>
                </label>
                <label class="block rounded-xl bg-slate-50 dark:bg-slate-900 p-4">
                    <strong class="block mb-2">فاصله Polling</strong>
                    <input type="number" name="polling_interval_ms" min="1000" max="10000" step="100" value="{{ old('polling_interval_ms', $settings->polling_interval_ms) }}" class="w-full rounded-lg border-slate-300">
                    <small class="text-slate-500">بین ۱۰۰۰ تا ۱۰۰۰۰ میلی‌ثانیه</small>
                </label>
            </div>
        </section>

        <section class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-5 md:p-7 shadow-sm" x-show="transport !== 'polling'" x-cloak>
            <h2 class="text-lg font-bold mb-5">ارائه‌دهنده و اتصال</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
                @foreach(['reverb' => 'Laravel Reverb', 'soketi' => 'Soketi', 'pusher' => 'Pusher Hosted'] as $value => $label)
                    <label class="rounded-xl border p-4 cursor-pointer" :class="provider === '{{ $value }}' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-slate-200 dark:border-slate-700'">
                        <input type="radio" name="provider" value="{{ $value }}" x-model="provider" class="ml-2"> <strong>{{ $label }}</strong>
                    </label>
                @endforeach
            </div>

            <label class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-900/20 p-4 mb-5">
                <input type="checkbox" name="use_env_credentials" value="1" x-model="useEnv">
                <span><strong class="block">استفاده از credentialهای فایل .env</strong><small class="text-slate-600">برای نگهداری secret خارج از دیتابیس پیشنهاد می‌شود.</small></span>
            </label>

            <div x-show="!useEnv" x-cloak class="space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <label class="block"><span class="font-semibold">App ID</span><input name="app_id" value="{{ old('app_id', $settings->app_id) }}" class="mt-2 w-full rounded-lg border-slate-300" autocomplete="off"></label>
                    <label class="block"><span class="font-semibold">App Key</span><input name="app_key" value="{{ old('app_key', $settings->app_key) }}" class="mt-2 w-full rounded-lg border-slate-300" autocomplete="off"></label>
                    <label class="block"><span class="font-semibold">App Secret</span><input type="password" name="app_secret" value="" class="mt-2 w-full rounded-lg border-slate-300" autocomplete="new-password" placeholder="برای حفظ مقدار فعلی خالی بگذارید"><small class="text-slate-500">مقدار به‌صورت رمزنگاری‌شده ذخیره می‌شود.</small></label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-5">
                <label class="block md:col-span-2"><span class="font-semibold">Host</span><input name="host" value="{{ old('host', $settings->host) }}" class="mt-2 w-full rounded-lg border-slate-300" placeholder="ws.example.com"><small class="text-slate-500" x-show="provider === 'pusher'">برای Pusher Hosted می‌تواند خالی باشد.</small></label>
                <label class="block"><span class="font-semibold">Port</span><input type="number" name="port" min="1" max="65535" value="{{ old('port', $settings->port ?: 443) }}" class="mt-2 w-full rounded-lg border-slate-300"></label>
                <label class="block"><span class="font-semibold">Scheme</span><select name="scheme" class="mt-2 w-full rounded-lg border-slate-300"><option value="https" {{ old('scheme', $settings->scheme) === 'https' ? 'selected' : '' }}>HTTPS / WSS</option><option value="http" {{ old('scheme', $settings->scheme) === 'http' ? 'selected' : '' }}>HTTP / WS</option></select></label>
                <label class="block"><span class="font-semibold">Cluster</span><input name="cluster" value="{{ old('cluster', $settings->cluster ?: 'mt1') }}" class="mt-2 w-full rounded-lg border-slate-300"><small class="text-slate-500">برای Pusher مهم است.</small></label>
            </div>
        </section>

        <section class="rounded-2xl bg-slate-900 text-white p-5 md:p-7">
            <h2 class="font-bold text-lg mb-3">وضعیت امنیت و بازیابی</h2>
            <ul class="space-y-2 text-sm text-slate-200">
                <li><i class="fas fa-check text-emerald-400 ml-2"></i>App Secret هیچ‌گاه به JavaScript یا صفحه عمومی ارسال نمی‌شود.</li>
                <li><i class="fas fa-check text-emerald-400 ml-2"></i>Journal مستقل از provider، رویدادهای ازدست‌رفته را با cursor بازیابی می‌کند.</li>
                <li><i class="fas {{ $health['credentials_complete'] ? 'fa-check text-emerald-400' : 'fa-triangle-exclamation text-amber-400' }} ml-2"></i>وضعیت credential فعلی: {{ $health['credentials_complete'] ? 'کامل' : 'ناقص' }}</li>
            </ul>
            @if($settings->last_test_message)<div class="mt-4 rounded-lg bg-white/10 p-3 text-sm break-words">{{ $settings->last_test_message }}</div>@endif
        </section>

        <div class="flex justify-end">
            <button type="submit" class="px-7 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-lg"><i class="fas fa-save ml-2"></i>ذخیره تنظیمات</button>
        </div>
    </form>
</div>
@endsection

@push('styles')
<style>[x-cloak]{display:none!important}</style>
@endpush
