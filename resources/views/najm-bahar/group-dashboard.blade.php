@extends('layouts.unified')

@section('title', 'داشبورد نجم بهار گروه')

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
</style>
@endpush

@section('content')
<div class="bg-light-gray/60 py-10 md:py-12 nb-dashboard" style="background-color: var(--color-light-gray);">
    <div class="container mx-auto px-5 md:px-10 max-w-6xl relative z-10 space-y-8">
        <section class="nb-hero">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="nb-chip">
                        <i class="fas fa-layer-group"></i>
                        نجم بهار گروه
                    </div>
                    <h1 class="text-3xl md:text-4xl font-black mt-4">داشبورد مالی {{ $group->name }}</h1>
                    <p class="text-sm md:text-base text-emerald-50 mt-2">مدیریت کیف پول و انتقال‌های مالی گروه</p>
                </div>
                <div class="flex items-center gap-3 flex-wrap">
                    <a href="{{ route('groups.najm-bahar.wallet', $group) }}" class="nb-action">
                        ورود به کیف پول گروه
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-[320px_minmax(0,1fr)] gap-6 items-start">
            <div class="lg:order-1">
                @include('najm-bahar.partials.sidebar', [
                    'routePrefix' => 'groups.najm-bahar',
                    'routeParams' => ['group' => $group->id],
                ])
            </div>

            <main class="space-y-8 lg:order-2">
                <div class="nb-card p-6">
                    <div class="flex items-center justify-between flex-wrap gap-4 mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800">نمای کلی حساب گروه</h2>
                            <p class="text-sm text-gray-500">خلاصه موجودی و عملیات مالی</p>
                        </div>
                        <a href="{{ route('groups.najm-bahar.transfer', $group) }}" class="nb-action">
                            انتقال وجه گروه
                            <i class="fas fa-arrow-left"></i>
                        </a>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="nb-stat">
                            <p class="text-sm text-emerald-700">موجودی حساب اصلی</p>
                            <p class="nb-metric nb-metric-accent">{{ \App\Helpers\BaharMoney::formatDecimal($account->balance) }}</p>
                        </div>
                        <div class="nb-stat">
                            <p class="text-sm text-emerald-700">شماره حساب گروه</p>
                            <p class="text-base font-mono text-emerald-800">{{ $account->account_number }}</p>
                        </div>
                        <div class="nb-stat">
                            <p class="text-sm text-emerald-700">تعداد تراکنش‌ها</p>
                            <p class="nb-metric nb-metric-accent">{{ number_format($transactionCount) }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="nb-card p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">حساب‌های فرعی گروه</h3>
                        <p class="text-sm text-gray-500 mb-4">تعداد حساب‌های فرعی فعال: {{ number_format($subAccountCount) }}</p>
                        <a href="{{ route('groups.najm-bahar.sub-accounts.index', $group) }}" class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition">
                            <i class="fas fa-wallet ml-2"></i>
                            مدیریت حساب‌های فرعی
                        </a>
                    </div>
                    <div class="nb-card p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">گزارش‌های مالی گروه</h3>
                        <p class="text-sm text-gray-500 mb-4">دسترسی سریع به گزارش‌ها و گردش مالی</p>
                        <a href="{{ route('groups.najm-bahar.reports', $group) }}" class="inline-flex items-center px-4 py-2 bg-white text-emerald-700 border border-emerald-200 rounded-lg hover:bg-emerald-50 transition">
                            <i class="fas fa-chart-bar ml-2"></i>
                            مشاهده گزارش‌ها
                        </a>
                    </div>
                </div>
            </main>
        </div>
    </div>
</div>
@endsection
