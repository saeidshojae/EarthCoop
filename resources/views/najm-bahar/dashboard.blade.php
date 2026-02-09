@extends('layouts.unified')

@section('title', 'داشبورد نجم بهار')

@push('styles')
<style>
    .nb-dashboard {
        position: relative;
        overflow: hidden;
    }

    .nb-dashboard::before {
        content: '';
        position: absolute;
        inset: -20% -10% auto auto;
        width: 520px;
        height: 520px;
        background: radial-gradient(circle, rgba(16, 185, 129, 0.15), transparent 60%);
        z-index: 0;
        pointer-events: none;
    }

    .nb-hero {
        position: relative;
        background: linear-gradient(135deg, #0f766e 0%, #10b981 55%, #60a5fa 100%);
        color: #ffffff;
        border-radius: 28px;
        padding: 28px 28px 26px;
        box-shadow: 0 18px 40px rgba(15, 118, 110, 0.25);
        overflow: hidden;
    }

    .nb-hero::after {
        content: '';
        position: absolute;
        width: 240px;
        height: 240px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.15);
        top: -80px;
        right: -80px;
    }

    .nb-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 999px;
        font-size: 0.85rem;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.18);
        color: #ffffff;
        backdrop-filter: blur(6px);
    }

    .nb-card {
        background: #ffffff;
        border-radius: 24px;
        border: 1px solid rgba(148, 163, 184, 0.2);
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        animation: nb-fade-up 0.6s ease both;
    }

    .nb-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 18px 36px rgba(15, 23, 42, 0.14);
    }

    .nb-stat {
        background: linear-gradient(135deg, rgba(241, 245, 249, 0.9), rgba(255, 255, 255, 0.8));
        border-radius: 18px;
        padding: 18px;
        border: 1px solid rgba(226, 232, 240, 0.7);
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }

    .nb-stat:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.08);
    }

    .nb-metric {
        font-size: 1.6rem;
        font-weight: 800;
        color: #0f172a;
    }

    .nb-metric-accent {
        color: #0f766e;
    }

    .nb-action {
        background: linear-gradient(135deg, #10b981, #0ea5e9);
        color: #ffffff;
        border-radius: 999px;
        padding: 10px 18px;
        font-weight: 600;
        box-shadow: 0 10px 24px rgba(14, 165, 233, 0.25);
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }

    .nb-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 30px rgba(14, 165, 233, 0.35);
    }

    @keyframes nb-fade-up {
        0% {
            opacity: 0;
            transform: translateY(12px);
        }
        100% {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .nb-sidebar {
        position: relative;
    }
</style>
@endpush

@section('content')
<div class="bg-light-gray/60 py-10 md:py-12 nb-dashboard" style="background-color: var(--color-light-gray);">
    <div class="container mx-auto px-5 md:px-10 max-w-6xl relative z-10 space-y-8">
        <section class="nb-hero">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="nb-chip">
                        <i class="fas fa-sparkles"></i>
                        نجم بهار
                    </div>
                    <h1 class="text-3xl md:text-4xl font-black mt-4">داشبورد مالی شما</h1>
                    <p class="text-sm md:text-base text-emerald-50 mt-2">گزارش کلی سامانه و نمای حساب شما</p>
                </div>
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="nb-chip">
                        <i class="fas fa-shield-check"></i>
                        {{ $isThresholdMet ? 'تراکنش‌ها فعال' : 'تراکنش‌ها قفل' }}
                    </span>
                    <a href="{{ route('najm-bahar.wallet') }}" class="nb-action">
                        ورود به کیف پول
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-[320px_minmax(0,1fr)] gap-6 items-start">
            <div class="lg:order-1 nb-sidebar">
                @include('najm-bahar.partials.sidebar')
            </div>

            <main class="space-y-8 lg:order-2">

                @if(session('success'))
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="nb-card p-6">
                    <div class="flex items-center justify-between flex-wrap gap-4 mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800">گزارش کلی سامانه</h2>
                            <p class="text-sm text-gray-500">تصویر کلی از وضعیت سیستم نجم بهار</p>
                        </div>
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-semibold {{ $isThresholdMet ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                            <i class="fas fa-signal"></i>
                            {{ $isThresholdMet ? 'تراکنش‌ها فعال' : 'تراکنش‌ها قفل' }}
                        </span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="nb-stat">
                            <p class="text-sm text-gray-500">تعداد کل اعضای جامعه</p>
                            <p class="nb-metric">{{ number_format($userCount) }}</p>
                        </div>
                        <div class="nb-stat">
                            <p class="text-sm text-gray-500">کل پول خلق شده</p>
                            <p class="nb-metric">{{ \App\Helpers\BaharMoney::formatDecimal($totalMinted) }}</p>
                        </div>
                        <div class="nb-stat">
                            <p class="text-sm text-gray-500">کاربران باقی‌مانده تا حدنصاب</p>
                            <p class="nb-metric">{{ number_format($remainingUsers) }}</p>
                        </div>
                        <div class="nb-stat">
                            <p class="text-sm text-gray-500">حدنصاب فعال‌سازی تراکنش</p>
                            <p class="nb-metric">{{ number_format($userThreshold) }}</p>
                        </div>
                        <div class="nb-stat">
                            <p class="text-sm text-gray-500">موجودی حساب حق عضویت</p>
                            <p class="nb-metric">{{ \App\Helpers\BaharMoney::formatDecimal($membershipBalance) }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ $membershipAccountCode }}</p>
                        </div>
                        <div class="nb-stat">
                            <p class="text-sm text-gray-500">وضعیت تراکنش‌های کاربری</p>
                            <p class="nb-metric nb-metric-accent">{{ $isThresholdMet ? 'فعال' : 'قفل' }}</p>
                            <p class="text-xs text-gray-400 mt-1">بر اساس حدنصاب سامانه</p>
                        </div>
                    </div>
                </div>

                <div class="nb-card p-6">
                    <div class="flex items-center justify-between flex-wrap gap-4 mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800">نمای کلی حساب شما</h2>
                            <p class="text-sm text-gray-500">خلاصه موجودی و فعالیت‌های مالی</p>
                        </div>
                        <a href="{{ route('najm-bahar.wallet') }}" class="nb-action">
                            مشاهده کیف پول
                            <i class="fas fa-arrow-left"></i>
                        </a>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="nb-stat">
                            <p class="text-sm text-emerald-700">موجودی حساب اصلی</p>
                            <p class="nb-metric nb-metric-accent">{{ \App\Helpers\BaharMoney::formatDecimal($account->balance) }}</p>
                        </div>
                        <div class="nb-stat">
                            <p class="text-sm text-emerald-700">شماره حساب</p>
                            <p class="text-base font-mono text-emerald-800">{{ $account->account_number }}</p>
                        </div>
                        <div class="nb-stat">
                            <p class="text-sm text-emerald-700">تعداد تراکنش‌های شما</p>
                            <p class="nb-metric nb-metric-accent">{{ number_format($userTransactionsCount) }}</p>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</div>
@endsection