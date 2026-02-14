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
    <div class="nb-page-container" style="max-width: var(--nb-container-max-width);">
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
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6" role="alert" aria-live="polite">
                        <i class="fas fa-check-circle ml-2" aria-hidden="true"></i>
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert" aria-live="assertive">
                        <i class="fas fa-exclamation-circle ml-2" aria-hidden="true"></i>
                        {{ session('error') }}
                    </div>
                @endif

                <!-- Account Summary -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
                    <div class="nb-card p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                            </div>
                            <div class="mr-4">
                                <h3 class="text-lg font-semibold text-gray-800">موجودی کل</h3>
                                <p class="nb-metric nb-metric-accent">{{ \App\Helpers\BaharMoney::formatDecimalHtml($account->balance) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="nb-card p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="mr-4">
                                <h3 class="text-lg font-semibold text-gray-800">موجودی فعال</h3>
                                <p class="nb-metric" style="color: #10b981;">{{ \App\Helpers\BaharMoney::formatDecimalHtml($account->balance_active ?? 0) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="nb-card p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="mr-4">
                                <h3 class="text-lg font-semibold text-gray-800">موجودی کمرنگ</h3>
                                <p class="nb-metric" style="color: #f59e0b;">{{ \App\Helpers\BaharMoney::formatDecimalHtml($account->balance_faded ?? 0) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="nb-card p-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                    </svg>
                                </div>
                                <div class="mr-4">
                                    <h3 class="text-lg font-semibold text-gray-800">امتیازات</h3>
                                    <div class="flex items-center gap-3">
                                        <span class="nb-metric" style="color: #8b5cf6;">{{ number_format($totalPoints ?? 0) }}</span>
                                        <span class="text-xs px-2 py-1 rounded-full bg-purple-100 text-purple-700 font-semibold">{{ $userLevel ?? 'Bronze' }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 mt-2 text-xs">
                                        <span class="text-green-600 font-bold">{{ number_format($uncashedPoints ?? 0) }}</span>
                                        <span class="text-gray-500">پررنگ</span>
                                        <span class="text-gray-300">|</span>
                                        <span class="text-gray-400 font-semibold">{{ number_format($cashedPoints ?? 0) }}</span>
                                        <span class="text-gray-500">کمرنگ</span>
                                    </div>
                                </div>
                            </div>
                            @if(($uncashedPoints ?? 0) > 0)
                                <button type="button" id="convertReputationBtn" class="px-4 py-2 bg-gradient-to-br from-purple-600 to-indigo-600 text-white rounded-full font-semibold text-sm shadow-lg hover:shadow-xl transition-all hover:scale-105">
                                    <i class="fas fa-coins ml-1"></i>
                                    نقد کردن
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Additional Info Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 md:gap-6">
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
                                        @php
                                            $isOutgoing = in_array($transaction->from_account_id, $accountIds ?? [], true);
                                            $isIncoming = in_array($transaction->to_account_id, $accountIds ?? [], true);
                                            $isInternal = $isOutgoing && $isIncoming;
                                        @endphp
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $transaction->created_at->format('Y/m/d H:i') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($isInternal)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        انتقال داخلی
                                                    </span>
                                                @elseif($isOutgoing)
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
                                                @if($isInternal)
                                                    <span class="text-blue-600">{{ \App\Helpers\BaharMoney::formatDecimalValueHtml($transaction->amount) }}</span>
                                                @elseif($isOutgoing)
                                                    <span class="text-red-600">-{{ \App\Helpers\BaharMoney::formatDecimalValueHtml($transaction->amount) }}</span>
                                                @else
                                                    <span class="text-green-600">+{{ \App\Helpers\BaharMoney::formatDecimalValueHtml($transaction->amount) }}</span>
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

<!-- مدال نقد کردن امتیازات -->
<div id="reputationConversionModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden transform transition-all">
        <div class="bg-gradient-to-br from-purple-600 to-indigo-600 p-6 text-white">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-coins text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold">تبدیل امتیاز به پول فعال</h3>
                        <p class="text-sm text-purple-100">نقد کردن امتیازات</p>
                    </div>
                </div>
                <button type="button" class="text-white/80 hover:text-white transition-colors" onclick="closeReputationModal()">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>

        <div id="reputationModalContent" class="p-6 space-y-4">
            <!-- محتوا از API بارگذاری می‌شود -->
            <div class="flex items-center justify-center py-8">
                <div class="animate-spin rounded-full h-10 w-10 border-4 border-purple-200 border-t-purple-600"></div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openReputationModal() {
    const modal = document.getElementById('reputationConversionModal');
    const content = document.getElementById('reputationModalContent');
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // بارگذاری اطلاعات از API
    fetch('{{ route("reputation.conversion.info") }}')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                content.innerHTML = `
                    <div class="text-center py-6">
                        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-times-circle text-3xl text-red-600"></i>
                        </div>
                        <h4 class="text-lg font-bold text-gray-800 mb-2">خطا</h4>
                        <p class="text-gray-600">${data.error}</p>
                    </div>
                    <button type="button" onclick="closeReputationModal()" class="w-full py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                        بستن
                    </button>
                `;
                return;
            }

            if (data.uncashed_points <= 0) {
                content.innerHTML = `
                    <div class="text-center py-6">
                        <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-info-circle text-3xl text-amber-600"></i>
                        </div>
                        <h4 class="text-lg font-bold text-gray-800 mb-2">امتیاز قابل نقد ندارید</h4>
                        <p class="text-gray-600 mb-4">تمام امتیازات شما قبلاً نقد شده است.</p>
                        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                            <div class="text-sm space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">مجموع امتیازات:</span>
                                    <span class="font-bold text-purple-700">${data.total_points.toLocaleString()}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">نقد شده:</span>
                                    <span class="font-bold text-gray-500">${data.cashed_points.toLocaleString()}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="closeReputationModal()" class="w-full py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                        بستن
                    </button>
                `;
                return;
            }

            if (!data.has_enough_faded) {
                content.innerHTML = `
                    <div class="text-center py-6">
                        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl text-red-600"></i>
                        </div>
                        <h4 class="text-lg font-bold text-gray-800 mb-2">موجودی کمرنگ ناکافی</h4>
                        <p class="text-gray-600 mb-4">برای تبدیل امتیازات به پول فعال، موجودی کمرنگ شما کافی نیست.</p>
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">امتیازات قابل نقد:</span>
                                <span class="font-bold text-purple-600">${data.uncashed_points.toLocaleString()}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">موجودی کمرنگ شما:</span>
                                <span class="font-bold text-amber-600">${data.balance_faded_formatted} بهار</span>
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="closeReputationModal()" class="w-full py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                        بستن
                    </button>
                `;
                return;
            }

            // نمایش فرم تبدیل
            const maxConvertibleGol = Math.floor(data.uncashed_points / data.conversion_ratio);
            
            content.innerHTML = `
                <div class="space-y-4">
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-600">امتیاز پررنگ:</span>
                                <p class="font-bold text-purple-700 text-lg">${data.uncashed_points.toLocaleString()}</p>
                            </div>
                            <div>
                                <span class="text-gray-600">امتیاز کمرنگ:</span>
                                <p class="font-bold text-gray-500 text-lg">${data.cashed_points.toLocaleString()}</p>
                            </div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-purple-200">
                            <span class="text-xs text-purple-700">
                                <i class="fas fa-exchange-alt ml-1"></i>
                                ${data.conversion_ratio_text}
                            </span>
                        </div>
                    </div>

                    <form action="{{ route('reputation.conversion.convert') }}" method="POST" id="conversionForm" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">مقدار امتیاز برای تبدیل:</label>
                            <input type="number" name="points" id="pointsInput" min="${data.conversion_ratio}" max="${data.uncashed_points}" step="${data.conversion_ratio}" value="${data.conversion_ratio}" 
                                class="w-full px-4 py-3 border-2 border-purple-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent font-bold text-lg"
                                oninput="updateConversionPreview(${data.conversion_ratio})">
                            <p class="text-xs text-gray-500 mt-1">حداقل: ${data.conversion_ratio} | حداکثر: ${data.uncashed_points.toLocaleString()}</p>
                        </div>

                        <div class="bg-gradient-to-br from-green-50 to-blue-50 border-2 border-green-200 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-gray-700">دریافت می‌کنید:</span>
                                <i class="fas fa-arrow-down text-green-600"></i>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-2xl font-black text-green-600" id="convertedAmount">1</span>
                                <span class="text-lg font-semibold text-green-700">بهار (فعال)</span>
                            </div>
                            <div class="mt-2 pt-2 border-t border-green-200 flex justify-between text-xs text-gray-600">
                                <span>موجودی فعال فعلی:</span>
                                <span class="font-bold">${data.balance_active_formatted} بهار</span>
                            </div>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <p class="text-xs text-blue-800">
                                <i class="fas fa-info-circle ml-1"></i>
                                امتیازات نقد شده کمرنگ می‌شوند اما از حساب شما حذف نمی‌شوند. معادل ارزش امتیاز از موجودی کمرنگ شما کسر و به موجودی فعال اضافه می‌شود.
                            </p>
                        </div>

                        <div class="flex gap-3">
                            <button type="submit" class="flex-1 py-3 bg-gradient-to-br from-purple-600 to-indigo-600 text-white rounded-lg font-bold text-lg shadow-lg hover:shadow-xl transition-all hover:scale-105">
                                <i class="fas fa-check-circle ml-2"></i>
                                تأیید و تبدیل
                            </button>
                            <button type="button" onclick="closeReputationModal()" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                                انصراف
                            </button>
                        </div>
                    </form>
                </div>
            `;

            // Set initial preview
            updateConversionPreview(data.conversion_ratio);
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = `
                <div class="text-center py-6">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-times-circle text-3xl text-red-600"></i>
                    </div>
                    <h4 class="text-lg font-bold text-gray-800 mb-2">خطا در بارگذاری</h4>
                    <p class="text-gray-600">لطفاً دوباره تلاش کنید.</p>
                </div>
                <button type="button" onclick="closeReputationModal()" class="w-full py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                    بستن
                </button>
            `;
        });
}

function closeReputationModal() {
    const modal = document.getElementById('reputationConversionModal');
    modal.classList.add('hidden');
    document.body.style.overflow = '';
}

function updateConversionPreview(ratio) {
    const pointsInput = document.getElementById('pointsInput');
    const convertedAmount = document.getElementById('convertedAmount');
    
    if (pointsInput && convertedAmount) {
        const points = parseInt(pointsInput.value) || 0;
        const bahar = Math.floor(points / ratio);
        convertedAmount.textContent = bahar.toLocaleString();
    }
}

// اتصال دکمه به تابع
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('convertReputationBtn');
    if (btn) {
        btn.addEventListener('click', openReputationModal);
    }

    // بستن مدال با کلیک روی پس‌زمینه
    const modal = document.getElementById('reputationConversionModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeReputationModal();
            }
        });
    }
});
</script>
@endpush

@endsection

