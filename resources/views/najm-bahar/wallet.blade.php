@extends('layouts.unified')

@section('title', 'کیف پول نجم بهار')

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

    .nb-action-outline {
        border: 1px solid rgba(255, 255, 255, 0.55);
        background: rgba(255, 255, 255, 0.12);
        color: #ffffff;
        border-radius: 999px;
        padding: 10px 18px;
        font-weight: 600;
        transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
    }

    .nb-action-outline:hover {
        transform: translateY(-2px);
        background: rgba(255, 255, 255, 0.2);
        box-shadow: 0 12px 26px rgba(15, 118, 110, 0.25);
    }

    .nb-quick {
        display: flex;
        flex-direction: column;
        gap: 14px;
        padding: 18px;
        border-radius: 20px;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid rgba(148, 163, 184, 0.2);
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }

    .nb-quick:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 34px rgba(15, 23, 42, 0.12);
    }

    .nb-quick-icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 1.1rem;
    }

    .nb-quick-title {
        font-size: 1rem;
        font-weight: 700;
        color: #0f172a;
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

@php
    $routePrefix = $routePrefix ?? 'najm-bahar';
    $routeParams = $routeParams ?? [];
    $isGroupWallet = $routePrefix !== 'najm-bahar';
    $walletOwnerLabel = $walletOwnerLabel ?? null;
@endphp

@section('content')
<div class="bg-light-gray/60 py-10 md:py-12 nb-dashboard" style="background-color: var(--color-light-gray);">
    <div class="container mx-auto px-5 md:px-10 max-w-6xl relative z-10 space-y-8">
        <section class="nb-hero">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="nb-chip">
                        <i class="fas fa-wallet"></i>
                        کیف پول نجم بهار
                    </div>
                    <h1 class="text-3xl md:text-4xl font-black mt-4">
                        جزئیات کیف پول {{ $walletOwnerLabel ? $walletOwnerLabel : 'شما' }}
                    </h1>
                    <p class="text-sm md:text-base text-emerald-50 mt-2">نمایش حساب اصلی، تراکنش‌ها و وضعیت مالی</p>
                </div>
                <div class="flex items-center gap-3 flex-wrap">
                    <a href="{{ route($routePrefix . '.transfer', $routeParams) }}" class="nb-action-outline">
                        انتقال وجه
                        <i class="fas fa-exchange-alt"></i>
                    </a>
                    <a href="{{ route($routePrefix . '.sub-accounts.create', $routeParams) }}" class="nb-action-outline">
                        ایجاد حساب فرعی
                        <i class="fas fa-plus"></i>
                    </a>
                    <a href="{{ route($routePrefix . '.reports', $routeParams) }}" class="nb-action">
                        گزارش‌های مالی
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-[320px_minmax(0,1fr)] gap-6 items-start">
            <div class="lg:order-1 nb-sidebar">
                @include('najm-bahar.partials.sidebar', [
                    'routePrefix' => $routePrefix,
                    'routeParams' => $routeParams,
                ])
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

                <!-- Account Summary -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="nb-card p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                            </div>
                            <div class="mr-4">
                                <h3 class="text-lg font-semibold text-gray-800">موجودی حساب</h3>
                                <p class="nb-metric nb-metric-accent">{{ \App\Helpers\BaharMoney::formatDecimal($account->balance) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="nb-card p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <div class="mr-4">
                                <h3 class="text-lg font-semibold text-gray-800">شماره حساب</h3>
                                <p class="text-lg font-mono text-gray-600">{{ $account->account_number }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="nb-card p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                            <div class="mr-4">
                                <h3 class="text-lg font-semibold text-gray-800">وضعیت حساب</h3>
                                <p class="text-lg font-semibold text-green-600">فعال</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="nb-card p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-gray-800">تراکنش‌های اخیر</h2>
                        <a href="{{ route($routePrefix . '.reports', $routeParams) }}" class="text-blue-600 hover:text-blue-800 font-medium">
                            مشاهده همه
                        </a>
                    </div>

                    @if($recentTransactions->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">تاریخ</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">نوع</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">مبلغ</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">شرح</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">وضعیت</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($recentTransactions as $transaction)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $transaction->created_at->format('Y/m/d H:i') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($transaction->from_account_id == $account->id)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                        برداشت
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        واریز
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                @if($transaction->from_account_id == $account->id)
                                                    <span class="text-red-600">-{{ \App\Helpers\BaharMoney::formatDecimalValue($transaction->amount) }}</span>
                                                @else
                                                    <span class="text-green-600">+{{ \App\Helpers\BaharMoney::formatDecimalValue($transaction->amount) }}</span>
                                                @endif
                                                بهار
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900">
                                                {{ $transaction->description ?? 'بدون شرح' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($transaction->status == 'completed')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        تکمیل شده
                                                    </span>
                                                @elseif($transaction->status == 'pending')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                        در انتظار
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                        ناموفق
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">هیچ تراکنشی یافت نشد</h3>
                            <p class="mt-1 text-sm text-gray-500">تراکنش‌های شما در اینجا نمایش داده خواهد شد.</p>
                        </div>
                    @endif
                </div>

                <!-- Quick Actions -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <a href="{{ route($routePrefix . '.reports', $routeParams) }}" class="nb-quick">
                        <span class="nb-quick-icon" style="background: linear-gradient(135deg, #2563eb, #60a5fa);">
                            <i class="fas fa-chart-line"></i>
                        </span>
                        <div>
                            <p class="nb-quick-title">گزارش‌ها</p>
                            <p class="text-xs text-gray-500">مشاهده گردش مالی</p>
                        </div>
                    </a>

                    <a href="{{ route($routePrefix . '.sub-accounts.index', $routeParams) }}" class="nb-quick">
                        <span class="nb-quick-icon" style="background: linear-gradient(135deg, #16a34a, #4ade80);">
                            <i class="fas fa-layer-group"></i>
                        </span>
                        <div>
                            <p class="nb-quick-title">حساب‌های فرعی</p>
                            <p class="text-xs text-gray-500">مدیریت حساب‌های زیرمجموعه</p>
                        </div>
                    </a>
                    @if($isGroupWallet)
                        <a href="{{ route('groups.najm-bahar.transfer', $routeParams) }}" class="nb-quick">
                            <span class="nb-quick-icon" style="background: linear-gradient(135deg, #0ea5e9, #38bdf8);">
                                <i class="fas fa-exchange-alt"></i>
                            </span>
                            <div>
                                <p class="nb-quick-title">انتقال وجه</p>
                                <p class="text-xs text-gray-500">انتقال بین حساب‌های فرعی</p>
                            </div>
                        </a>

                        <a href="{{ route('groups.najm-bahar.audit-logs', $routeParams) }}" class="nb-quick">
                            <span class="nb-quick-icon" style="background: linear-gradient(135deg, #7c3aed, #c084fc);">
                                <i class="fas fa-clipboard-list"></i>
                            </span>
                            <div>
                                <p class="nb-quick-title">گزارش عملیات</p>
                                <p class="text-xs text-gray-500">مشاهده لاگ‌های مالی</p>
                            </div>
                        </a>
                    @else
                        <a href="{{ route('notifications.settings') }}" class="nb-quick">
                            <span class="nb-quick-icon" style="background: linear-gradient(135deg, #7c3aed, #c084fc);">
                                <i class="fas fa-sliders-h"></i>
                            </span>
                            <div>
                                <p class="nb-quick-title">تنظیمات</p>
                                <p class="text-xs text-gray-500">مدیریت اعلان‌ها و ترجیحات</p>
                            </div>
                        </a>

                        <a href="{{ route('user.support-chat.index') }}" class="nb-quick">
                            <span class="nb-quick-icon" style="background: linear-gradient(135deg, #ea580c, #fbbf24);">
                                <i class="fas fa-headset"></i>
                            </span>
                            <div>
                                <p class="nb-quick-title">پشتیبانی</p>
                                <p class="text-xs text-gray-500">پاسخ سریع به سوالات شما</p>
                            </div>
                        </a>
                    @endif
                </div>
            </main>
        </div>
    </div>
</div>
@endsection
