@extends('layouts.unified')

@section('title', __('فراموشی رمز عبور') . ' - ' . config('app.name', 'EarthCoop'))

@push('styles')
    @include('auth.partials.recovery-styles')
@endpush

@section('content')
    <div class="auth-recovery-wrapper bg-slate-50 dark:bg-slate-950">
        <span class="auth-recovery-glow"></span>

        <div class="container mx-auto px-4 md:px-10 max-w-6xl" dir="rtl">
            <div class="grid gap-10 lg:grid-cols-2 items-center">
                <div class="space-y-6 text-right">
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full auth-recovery-badge text-xs font-semibold uppercase tracking-widest">
                        <i class="fas fa-key text-sm"></i>
                        <span>{{ __('بازیابی دسترسی سریع') }}</span>
                    </div>

                    <h1 class="font-vazirmatn text-3xl md:text-4xl font-extrabold leading-tight text-slate-900 dark:text-white">
                        {{ __('کد تأیید را برای ایمیل خود دریافت کنید') }}
                    </h1>

                    <p class="text-slate-600 dark:text-slate-300 leading-relaxed md:text-lg">
                        {{ __('برای امنیت بیشتر، پیش از تغییر رمز عبور لازم است کد تأییدی که برای شما ارسال می‌شود را وارد کنید. آدرس ایمیل خود را در فرم کنار وارد کنید تا کد را دریافت نمایید.') }}
                    </p>

                    <ul class="space-y-3 text-sm md:text-base text-slate-500 dark:text-slate-300">
                        <li class="flex items-start gap-3">
                            <span class="mt-1 inline-flex h-6 w-6 items-center justify-center rounded-full bg-ocean-blue/10 text-ocean-blue">
                                <i class="fas fa-envelope text-xs"></i>
                            </span>
                            <span>{{ __('اگر ایمیل در پوشه اصلی نبود، لطفاً پوشه هرزنامه یا تبلیغات را هم بررسی کنید.') }}</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-1 inline-flex h-6 w-6 items-center justify-center rounded-full bg-earth-green/10 text-earth-green">
                                <i class="fas fa-shield-check text-xs"></i>
                            </span>
                            <span>{{ __('کد ارسال‌شده فقط برای مدت محدودی معتبر است؛ پس از دریافت، سریعاً به مرحله بعد بروید.') }}</span>
                        </li>
                    </ul>
                </div>

                <div class="auth-recovery-card relative overflow-hidden p-8 md:p-10">
                    <div class="absolute inset-0 bg-gradient-to-br from-white/60 via-white/20 to-transparent opacity-60 pointer-events-none dark:from-slate-900/80 dark:via-slate-900/40"></div>
                    <div class="relative space-y-8">
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900 dark:text-white mb-3">{{ __('ارسال کد تأیید به ایمیل') }}</h2>
                            <p class="text-sm md:text-base text-slate-600 dark:text-slate-300 leading-relaxed">
                                {{ __('آدرس ایمیلی را که با آن ثبت‌نام کرده‌اید بنویسید تا کد تأیید برای شما ارسال شود. سپس با همان کد ادامه دهید و رمز جدید انتخاب کنید.') }}
                            </p>
                        </div>

                        @if(session('success'))
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200">
                                <i class="fas fa-check-circle me-2"></i>
                                {{ session('success') }}
                            </div>
                        @endif

                        @if($errors->any())
                            <div class="rounded-2xl border border-red-200 bg-red-50/60 px-4 py-3 text-sm font-medium text-red-600 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-200">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                {{ __('لطفاً خطاهای زیر را برطرف کنید و دوباره تلاش نمایید.') }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('password.reset.send') }}" class="space-y-6">
                            @csrf
                            <div class="space-y-2">
                                <label for="email" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">
                                    {{ __('آدرس ایمیل') }}
                                </label>
                                <input
                                    id="email"
                                    type="email"
                                    dir="ltr"
                                    class="auth-recovery-input w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-700 shadow-sm transition focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 @error('email') border-red-400 focus:ring-red-300/50 dark:border-red-400 @enderror"
                                    name="email"
                                    value="{{ old('email') }}"
                                    required
                                    autocomplete="email"
                                    autofocus
                                >
                                @error('email')
                                    <p class="mt-2 text-xs font-medium text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <button type="submit"
                                    class="w-full inline-flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-earth-green to-emerald-500 px-6 py-3.5 text-base font-semibold text-white shadow-lg shadow-emerald-500/25 transition hover:from-emerald-500 hover:to-earth-green focus:outline-none focus:ring-4 focus:ring-emerald-400/40">
                                <i class="fas fa-paper-plane"></i>
                                <span>{{ __('ارسال کد تأیید') }}</span>
                            </button>
                        </form>

                        <div class="pt-1 text-center text-sm text-slate-500 dark:text-slate-400">
                            {{ __('به کمک بیشتری نیاز دارید؟') }}
                            <a href="{{ url('/pages/contact') }}" class="font-semibold text-earth-green hover:text-emerald-600 transition">
                                {{ __('با تیم پشتیبانی EarthCoop در ارتباط باشید') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
