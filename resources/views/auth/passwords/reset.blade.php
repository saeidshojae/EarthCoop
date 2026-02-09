@extends('layouts.unified')

@section('title', __('Reset Password') . ' - ' . config('app.name', 'EarthCoop'))

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
                        <span>{{ __('ایجاد رمز عبور جدید') }}</span>
                    </div>

                    <h1 class="font-vazirmatn text-3xl md:text-4xl font-extrabold leading-tight text-slate-900 dark:text-white">
                        {{ __('رمز عبور خود را بازنشانی کنید') }}
                    </h1>

                    <p class="text-slate-600 dark:text-slate-300 leading-relaxed md:text-lg">
                        {{ __('رمز عبور جدیدی انتخاب کنید که امن و قابل اعتماد باشد. دو مرتبه رمز جدید را وارد کنید تا از صحت آن مطمئن شویم.') }}
                    </p>

                    <ul class="space-y-3 text-sm md:text-base text-slate-500 dark:text-slate-300">
                        <li class="flex items-start gap-3">
                            <span class="mt-1 inline-flex h-6 w-6 items-center justify-center rounded-full bg-ocean-blue/10 text-ocean-blue">
                                <i class="fas fa-wave-square text-xs"></i>
                            </span>
                            <span>{{ __('از ترکیب حروف بزرگ و کوچک، اعداد و نشانه‌ها برای امنیت بیشتر استفاده کنید.') }}</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-1 inline-flex h-6 w-6 items-center justify-center rounded-full bg-earth-green/10 text-earth-green">
                                <i class="fas fa-clock text-xs"></i>
                            </span>
                            <span>{{ __('لینک بازنشانی فقط برای مدت کوتاهی معتبر است، بنابراین همین حالا رمز خود را تغییر دهید.') }}</span>
                        </li>
                    </ul>
                </div>

                <div class="auth-recovery-card relative overflow-hidden p-8 md:p-10">
                    <div class="absolute inset-0 bg-gradient-to-br from-white/60 via-white/20 to-transparent opacity-60 pointer-events-none dark:from-slate-900/80 dark:via-slate-900/40"></div>
                    <div class="relative space-y-8">
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900 dark:text-white mb-3">{{ __('تنظیم رمز جدید') }}</h2>
                            <p class="text-sm md:text-base text-slate-600 dark:text-slate-300 leading-relaxed">
                                {{ __('اطلاعات زیر را با دقت تکمیل کنید. پس از ذخیره رمز جدید، می‌توانید وارد حساب خود شوید.') }}
                            </p>
                        </div>

                        <form method="POST" action="{{ route('password.update') }}" class="space-y-6">
                            @csrf
                            <input type="hidden" name="token" value="{{ $token }}">

                            <div class="space-y-2">
                                <label for="email" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">
                                    {{ __('آدرس ایمیل') }}
                                </label>
                                <input id="email"
                                       type="email"
                                       dir="ltr"
                                       class="auth-recovery-input w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-700 shadow-sm transition focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 @error('email') border-red-400 focus:ring-red-300/50 dark:border-red-400 @enderror"
                                       name="email"
                                       value="{{ $email ?? old('email') }}"
                                       required
                                       autocomplete="email"
                                       autofocus>
                                @error('email')
                                    <p class="mt-2 text-xs font-medium text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label for="password" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">
                                    {{ __('رمز عبور جدید') }}
                                </label>
                                <div class="relative">
                                    <input id="password"
                                           type="password"
                                           class="auth-recovery-input w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 pr-12 text-slate-700 shadow-sm transition focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 @error('password') border-red-400 focus:ring-red-300/50 dark:border-red-400 @enderror"
                                           name="password"
                                           required
                                           autocomplete="new-password">
                                    <button type="button"
                                            class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700 transition"
                                            aria-label="{{ __('نمایش یا مخفی کردن رمز عبور') }}"
                                            data-password-toggle="password">
                                        <i class="fas fa-eye text-sm" data-password-icon="show"></i>
                                        <i class="fas fa-eye-slash text-sm hidden" data-password-icon="hide"></i>
                                    </button>
                                </div>
                                @error('password')
                                    <p class="mt-2 text-xs font-medium text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label for="password-confirm" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">
                                    {{ __('تکرار رمز عبور جدید') }}
                                </label>
                                <div class="relative">
                                    <input id="password-confirm"
                                           type="password"
                                           class="auth-recovery-input w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 pr-12 text-slate-700 shadow-sm transition focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                           name="password_confirmation"
                                           required
                                           autocomplete="new-password">
                                    <button type="button"
                                            class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700 transition"
                                            aria-label="{{ __('نمایش یا مخفی کردن تکرار رمز عبور') }}"
                                            data-password-toggle="password-confirm">
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
                            {{ __('پشتیبانی لازم دارید؟') }}
                            <a href="{{ url('/pages/contact') }}" class="font-semibold text-earth-green hover:text-emerald-600 transition">
                                {{ __('با تیم ما در ارتباط باشید') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
