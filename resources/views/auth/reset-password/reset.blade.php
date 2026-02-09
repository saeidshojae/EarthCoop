@extends('layouts.unified')

@section('title', __('تغییر رمز عبور') . ' - ' . config('app.name', 'EarthCoop'))

@push('styles')
    @include('auth.partials.recovery-styles')
@endpush

@push('scripts')
    @include('auth.partials.recovery-scripts')
@endpush

@section('content')
    <div class="auth-recovery-wrapper bg-slate-50 dark:bg-slate-950">
        <span class="auth-recovery-glow"></span>

        <div class="container mx-auto px-4 md:px-10 max-w-6xl" dir="rtl">
            <div class="grid gap-10 lg:grid-cols-2 items-center">
                <div class="space-y-6 text-right">
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full auth-recovery-badge text-xs font-semibold uppercase tracking-widest">
                        <i class="fas fa-lock text-sm"></i>
                        <span>{{ __('بازنشانی امن رمز عبور') }}</span>
                    </div>

                    <h1 class="font-vazirmatn text-3xl md:text-4xl font-extrabold leading-tight text-slate-900 dark:text-white">
                        {{ __('رمز عبور جدید خود را تنظیم کنید') }}
                    </h1>

                    <p class="text-slate-600 dark:text-slate-300 leading-relaxed md:text-lg">
                        {{ __('کد تأیید ارسال‌شده به ایمیل خود را وارد کنید و سپس یک رمز عبور مطمئن جدید بسازید. پس از ذخیره رمز جدید می‌توانید دوباره وارد EarthCoop شوید.') }}
                    </p>

                    <ul class="space-y-3 text-sm md:text-base text-slate-500 dark:text-slate-300">
                        <li class="flex items-start gap-3">
                            <span class="mt-1 inline-flex h-6 w-6 items-center justify-center rounded-full bg-ocean-blue/10 text-ocean-blue">
                                <i class="fas fa-key text-xs"></i>
                            </span>
                            <span>{{ __('کد تأیید را دقیقاً همان‌طور که دریافت کرده‌اید وارد کنید. به حروف بزرگ و کوچک دقت کنید.') }}</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-1 inline-flex h-6 w-6 items-center justify-center rounded-full bg-earth-green/10 text-earth-green">
                                <i class="fas fa-shield-alt text-xs"></i>
                            </span>
                            <span>{{ __('برای امنیت بیشتر، از رمزهای یکتا و دشوار استفاده کنید و آن را با کسی به اشتراک نگذارید.') }}</span>
                        </li>
                    </ul>
                </div>

                <div class="auth-recovery-card relative overflow-hidden p-8 md:p-10">
                    <div class="absolute inset-0 bg-gradient-to-br from-white/60 via-white/20 to-transparent opacity-60 pointer-events-none dark:from-slate-900/80 dark:via-slate-900/40"></div>
                    <div class="relative space-y-8">
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900 dark:text-white mb-3">{{ __('تکمیل فرآیند بازنشانی') }}</h2>
                            <p class="text-sm md:text-base text-slate-600 dark:text-slate-300 leading-relaxed">
                                {{ __('فرم زیر را با دقت کامل کنید. پس از ذخیره، رمز جدید فعال می‌شود و می‌توانید وارد حساب خود شوید.') }}
                            </p>
                        </div>

                        @if(session('success'))
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200">
                                <i class="fas fa-check-circle me-2"></i>
                                {{ session('success') }}
                            </div>
                        @endif

                        @if(session('error'))
                            <div class="rounded-2xl border border-red-200 bg-red-50/60 px-4 py-3 text-sm font-medium text-red-600 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-200">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                {{ session('error') }}
                            </div>
                        @endif

        @if($errors->any())
                        <div class="rounded-2xl border border-red-200 bg-red-50/60 px-4 py-3 text-sm font-medium text-red-600 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-200">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            {{ __('لطفاً خطاهای زیر را بررسی و اصلاح کنید.') }}
                        </div>
        @endif

                        <form method="POST" action="{{ route('password.reset') }}" class="space-y-6">
                            @csrf
                            <input type="hidden" name="email" value="{{ $email }}">

                            <div class="space-y-2">
                                <label for="code" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">
                                    {{ __('کد تأیید') }}
                                </label>
                                <input
                                    id="code"
                                    type="text"
                                    class="auth-recovery-input w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-700 shadow-sm transition focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 @error('code') border-red-400 focus:ring-red-300/50 dark:border-red-400 @enderror"
                                    name="code"
                                    value="{{ old('code') }}"
                                    required
                                    autofocus
                                >
                                @error('code')
                                    <p class="mt-2 text-xs font-medium text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label for="password" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">
                                    {{ __('رمز عبور جدید') }}
                                </label>
                                <div class="relative">
                                    <input
                                        id="password"
                                        type="password"
                                        class="auth-recovery-input w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 pr-12 text-slate-700 shadow-sm transition focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 @error('password') border-red-400 focus:ring-red-300/50 dark:border-red-400 @enderror"
                                        name="password"
                                        required
                                        autocomplete="new-password"
                                    >
                                    <button
                                        type="button"
                                        class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700 transition"
                                        aria-label="{{ __('نمایش یا مخفی کردن رمز عبور') }}"
                                        data-password-toggle="password"
                                    >
                                        <i class="fas fa-eye text-sm" data-password-icon="show"></i>
                                        <i class="fas fa-eye-slash text-sm hidden" data-password-icon="hide"></i>
                                    </button>
                                </div>
                                @error('password')
                                    <p class="mt-2 text-xs font-medium text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label for="password_confirmation" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">
                                    {{ __('تکرار رمز عبور جدید') }}
                                </label>
                                <div class="relative">
                                    <input
                                        id="password_confirmation"
                                        type="password"
                                        class="auth-recovery-input w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 pr-12 text-slate-700 shadow-sm transition focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                        name="password_confirmation"
                                        required
                                        autocomplete="new-password"
                                    >
                                    <button
                                        type="button"
                                        class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700 transition"
                                        aria-label="{{ __('نمایش یا مخفی کردن تکرار رمز عبور') }}"
                                        data-password-toggle="password_confirmation"
                                    >
                                        <i class="fas fa-eye text-sm" data-password-icon="show"></i>
                                        <i class="fas fa-eye-slash text-sm hidden" data-password-icon="hide"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit"
                                    class="w-full inline-flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-earth-green to-emerald-500 px-6 py-3.5 text-base font-semibold text-white shadow-lg shadow-emerald-500/25 transition hover:from-emerald-500 hover:to-earth-green focus:outline-none focus:ring-4 focus:ring-emerald-400/40">
                                <i class="fas fa-sync-alt"></i>
                                <span>{{ __('ذخیره رمز جدید') }}</span>
                            </button>
                        </form>

                        <div class="pt-1 text-center text-sm text-slate-500 dark:text-slate-400">
                            {{ __('مشکلی در دریافت کد دارید؟') }}
                            <a href="{{ route('password.reset.view') }}" class="font-semibold text-earth-green hover:text-emerald-600 transition">
                                {{ __('ارسال دوباره کد تأیید') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
