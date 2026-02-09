@extends('layouts.unified')

@section('title', __('Verify Your Email Address') . ' - ' . config('app.name', 'EarthCoop'))

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
                        <i class="fas fa-shield-alt text-sm"></i>
                        <span>{{ __('امنیت حساب شما') }}</span>
                    </div>

                    <h1 class="font-vazirmatn text-3xl md:text-4xl font-extrabold leading-tight text-slate-900 dark:text-white">
                        {{ __('تأیید ایمیل شما برای تکمیل فرایند لازم است') }}
                    </h1>

                    <p class="text-slate-600 dark:text-slate-300 leading-relaxed md:text-lg">
                        {{ __('قبل از ادامه، لطفاً صندوق ایمیل خود را بررسی کنید و لینک تأیید ارسال‌شده را باز کنید. این کار به ما کمک می‌کند تا از امنیت حساب شما اطمینان حاصل کنیم.') }}
                    </p>

                    <div class="space-y-3 text-sm md:text-base">
                        <div class="flex items-start gap-3 text-slate-500 dark:text-slate-300">
                            <span class="mt-1 inline-flex h-6 w-6 items-center justify-center rounded-full bg-earth-green/10 text-earth-green">
                                <i class="fas fa-envelope-open-text text-xs"></i>
                            </span>
                            <span>{{ __('اگر ایمیل را پیدا نکردید، فولدر هرزنامه (Spam) یا تبلیغات را هم بررسی کنید.') }}</span>
                        </div>
                        <div class="flex items-start gap-3 text-slate-500 dark:text-slate-300">
                            <span class="mt-1 inline-flex h-6 w-6 items-center justify-center rounded-full bg-ocean-blue/10 text-ocean-blue">
                                <i class="fas fa-redo text-xs"></i>
                            </span>
                            <span>{{ __('در صورت نیاز می‌توانید پس از چند دقیقه درخواست ارسال مجدد لینک را بدهید.') }}</span>
                        </div>
                    </div>
                </div>

                <div class="auth-recovery-card relative overflow-hidden p-8 md:p-10">
                    <div class="absolute inset-0 bg-gradient-to-br from-white/60 via-white/20 to-transparent opacity-60 pointer-events-none dark:from-slate-900/80 dark:via-slate-900/40"></div>
                    <div class="relative space-y-7">
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900 dark:text-white mb-3">{{ __('پوشه ایمیل خود را بررسی کنید') }}</h2>
                            <p class="text-sm md:text-base text-slate-600 dark:text-slate-300 leading-relaxed">
                                {{ __('اگر هنوز ایمیلی دریافت نکرده‌اید، می‌توانید با کلیک بر روی دکمه زیر، یک لینک تأیید جدید دریافت کنید.') }}
                            </p>
                        </div>

                        @if (session('resent'))
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200">
                                <i class="fas fa-check-circle me-2"></i>
                                {{ __('A fresh verification link has been sent to your email address.') }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('verification.resend') }}" class="space-y-4">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-earth-green to-emerald-500 px-6 py-3.5 text-base font-semibold text-white shadow-lg shadow-emerald-500/25 transition hover:from-emerald-500 hover:to-earth-green focus:outline-none focus:ring-4 focus:ring-emerald-400/40">
                                <i class="fas fa-paper-plane"></i>
                                <span>{{ __('ارسال مجدد لینک تأیید') }}</span>
                            </button>
                        </form>

                        <div class="pt-2 text-center text-sm text-slate-500 dark:text-slate-400">
                            {{ __('در صورت بروز مشکل') }}
                            <a href="{{ url('/pages/contact') }}" class="font-semibold text-earth-green hover:text-emerald-600 transition">
                                {{ __('با پشتیبانی EarthCoop در تماس باشید') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
