@extends('layouts.unified')

@section('title', 'کیف پول نجم بهار')

@push('styles')
<style>
    .nb-dashboard { position: relative; overflow: hidden; }
    .nb-dashboard::before { content: ''; position: absolute; inset: -20% -10% auto auto; width: 520px; height: 520px; background: radial-gradient(circle, rgba(16, 185, 129, 0.15), transparent 60%); z-index: 0; pointer-events: none; }
    .nb-hero { position: relative; background: linear-gradient(135deg, #0f766e 0%, #10b981 55%, #60a5fa 100%); color: #ffffff; border-radius: 28px; padding: 28px 28px 26px; box-shadow: 0 18px 40px rgba(15, 118, 110, 0.25); overflow: hidden; }
    .nb-hero::after { content: ''; position: absolute; width: 240px; height: 240px; border-radius: 50%; background: rgba(255, 255, 255, 0.15); top: -80px; right: -80px; }
    .nb-chip { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: 999px; font-size: 0.85rem; font-weight: 600; background: rgba(255, 255, 255, 0.18); color: #ffffff; backdrop-filter: blur(6px); }
    .nb-card { background: #ffffff; border-radius: 24px; border: 1px solid rgba(148, 163, 184, 0.2); box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08); transition: transform 0.3s ease, box-shadow 0.3s ease; animation: nb-fade-up 0.6s ease both; }
    .nb-card:hover { transform: translateY(-4px); box-shadow: 0 18px 36px rgba(15, 23, 42, 0.14); }
    .nb-stat { background: linear-gradient(135deg, rgba(241, 245, 249, 0.9), rgba(255, 255, 255, 0.8)); border-radius: 18px; padding: 18px; border: 1px solid rgba(226, 232, 240, 0.7); transition: transform 0.25s ease, box-shadow 0.25s ease; }
    .nb-stat:hover { transform: translateY(-3px); box-shadow: 0 10px 22px rgba(15, 23, 42, 0.08); }
    .nb-metric { font-size: 1.6rem; font-weight: 800; color: #0f172a; }
    .nb-metric-accent { color: #0f766e; }
    .nb-action { background: linear-gradient(135deg, #10b981, #0ea5e9); color: #ffffff; border-radius: 999px; padding: 10px 18px; font-weight: 600; box-shadow: 0 10px 24px rgba(14, 165, 233, 0.25); transition: transform 0.25s ease, box-shadow 0.25s ease; }
    .nb-action:hover { transform: translateY(-2px); box-shadow: 0 14px 30px rgba(14, 165, 233, 0.35); }
    .nb-action-outline { border: 1px solid rgba(255, 255, 255, 0.55); background: rgba(255, 255, 255, 0.12); color: #ffffff; border-radius: 999px; padding: 10px 18px; font-weight: 600; transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease; }
    .nb-action-outline:hover { transform: translateY(-2px); background: rgba(255, 255, 255, 0.2); box-shadow: 0 12px 26px rgba(15, 118, 110, 0.25); }
    .nb-quick { display: flex; flex-direction: column; gap: 14px; padding: 18px; border-radius: 20px; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); border: 1px solid rgba(148, 163, 184, 0.2); box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08); transition: transform 0.25s ease, box-shadow 0.25s ease; }
    .nb-quick:hover { transform: translateY(-4px); box-shadow: 0 16px 34px rgba(15, 23, 42, 0.12); }
    .nb-quick-icon { width: 44px; height: 44px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; color: #ffffff; font-size: 1.1rem; }
    .nb-quick-title { font-size: 1rem; font-weight: 700; color: #0f172a; }
    @keyframes nb-fade-up { 0% { opacity: 0; transform: translateY(12px); } 100% { opacity: 1; transform: translateY(0); } }
    .nb-sidebar { position: relative; }
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
    <div class="nb-page-container nb-responsive-shell" style="max-width: var(--nb-container-max-width);">
        <section class="nb-hero">
            <x-bahar-coin variant="hero" />
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="nb-chip"><i class="fas fa-wallet"></i> کیف پول نجم بهار</div>
                    <h1 class="text-3xl md:text-4xl font-black mt-4">جزئیات کیف پول {{ $walletOwnerLabel ? $walletOwnerLabel : 'شما' }}</h1>
                    <p class="text-sm md:text-base text-emerald-50 mt-2">نمایش حساب اصلی، تراکنش‌ها و وضعیت مالی</p>
                </div>
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 flex-wrap">
                    <a href="{{ route($routePrefix . '.dashboard', $routeParams) }}" class="nb-action-outline"><i class="fas fa-arrow-right"></i> بازگشت به داشبورد</a>
                    <a href="{{ route($routePrefix . '.transfer', $routeParams) }}" class="nb-action-outline">انتقال وجه <i class="fas fa-exchange-alt"></i></a>
                    <a href="{{ route($routePrefix . '.sub-accounts.create', $routeParams) }}" class="nb-action-outline">ایجاد حساب فرعی <i class="fas fa-plus"></i></a>
                    <a href="{{ route($routePrefix . '.reports', $routeParams) }}" class="nb-action">گزارش‌های مالی <i class="fas fa-arrow-left"></i></a>
                </div>
            </div>
        </section>

        <div class="nb-responsive-layout grid grid-cols-1 lg:grid-cols-[320px_minmax(0,1fr)] gap-6 items-start mt-8">
            <div class="lg:order-1 nb-sidebar">
                @include('najm-bahar.partials.sidebar', ['routePrefix' => $routePrefix, 'routeParams' => $routeParams])
            </div>

            <main class="space-y-3 lg:order-2">
                @if(session('success'))
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6" role="alert" aria-live="polite"><i class="fas fa-check-circle ml-2" aria-hidden="true"></i>{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert" aria-live="assertive"><i class="fas fa-exclamation-circle ml-2" aria-hidden="true"></i>{{ session('error') }}</div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 md:gap-6">
                    <div class="nb-card p-6 bg-gradient-to-br from-blue-50 to-blue-100 border-blue-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-blue-200"><svg class="w-8 h-8 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg></div>
                                <div class="mr-4"><h3 class="text-sm font-semibold text-blue-700 mb-1">شماره حساب</h3><p class="text-lg font-mono text-blue-900 font-bold">{{ $account->account_number }}</p></div>
                            </div>
                            <div class="w-8 h-8 rounded-full bg-blue-200 flex items-center justify-center"><i class="fas fa-hashtag text-blue-700" aria-hidden="true"></i></div>
                        </div>
                    </div>
                    <div class="nb-card p-6 bg-gradient-to-br from-green-50 to-green-100 border-green-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-green-200"><svg class="w-8 h-8 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg></div>
                                <div class="mr-4"><h3 class="text-sm font-semibold text-green-700 mb-1">وضعیت حساب</h3><p class="text-lg font-bold text-green-900">فعال</p></div>
                            </div>
                            <div class="w-8 h-8 rounded-full bg-green-200 flex items-center justify-center"><i class="fas fa-check-circle text-green-700" aria-hidden="true"></i></div>
                        </div>
                    </div>
                </div>

                <div class="nb-card p-6 bg-gradient-to-br from-indigo-50 via-blue-50 to-cyan-50 border-indigo-200">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-indigo-200"><svg class="w-8 h-8 text-indigo-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path></svg></div>
                            <div class="mr-4"><h3 class="text-lg font-bold text-indigo-900 mb-1">موجودی کل حساب</h3><p class="nb-metric text-indigo-700">{{ \App\Helpers\BaharMoney::formatDecimalHtml($account->balance) }}</p></div>
                        </div>
                        <div class="text-xs text-indigo-600 bg-indigo-100 px-3 py-1 rounded-full font-semibold"><i class="fas fa-layer-group ml-1" aria-hidden="true"></i>مجموع کل</div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t-2 border-indigo-200 border-dashed">
                        <div class="bg-white/70 backdrop-blur-sm rounded-xl p-4 border border-green-200 hover:shadow-md transition-shadow">
                            <div class="flex items-center justify-between"><div class="flex items-center"><div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center"><svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div><div class="mr-3"><p class="text-xs text-gray-600 font-semibold mb-0.5">موجودی فعال</p><p class="text-xl font-bold text-green-600">{{ \App\Helpers\BaharMoney::formatDecimalHtml($account->balance_active ?? 0) }}</p></div></div><i class="fas fa-arrow-trend-up text-green-500 text-sm" aria-hidden="true"></i></div>
                        </div>
                        <div class="bg-white/70 backdrop-blur-sm rounded-xl p-4 border border-amber-200 hover:shadow-md transition-shadow">
                            <div class="flex items-center justify-between"><div class="flex items-center"><div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center"><svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div><div class="mr-3"><p class="text-xs text-gray-600 font-semibold mb-0.5">موجودی کمرنگ</p><p class="text-xl font-bold text-amber-600">{{ \App\Helpers\BaharMoney::formatDecimalHtml($account->balance_faded ?? 0) }}</p></div></div><i class="fas fa-arrow-trend-down text-amber-500 text-sm" aria-hidden="true"></i></div>
                        </div>
                    </div>
                </div>

                <div class="nb-card p-6 bg-gradient-to-br from-purple-50 to-pink-50 border-purple-200">
                    <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-5">
                        <div class="flex items-start flex-1">
                            <div class="p-3 rounded-full bg-purple-200"><svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg></div>
                            <div class="mr-4 flex-1">
                                <h3 class="text-lg font-bold text-purple-900 mb-1">مجموع امتیاز اعتبار و مشارکت</h3>
                                <div class="flex items-center gap-3 flex-wrap">
                                    <span class="text-3xl font-black text-purple-600">{{ number_format($totalPoints ?? 0) }}</span>
                                    <span class="text-xs px-3 py-1.5 rounded-full bg-gradient-to-br from-purple-100 to-pink-100 text-purple-700 font-bold border border-purple-200"><i class="fas fa-medal ml-1" aria-hidden="true"></i>{{ $userLevel ?? 'Bronze' }}</span>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 mt-4 text-sm">
                                    <div class="flex items-center justify-between gap-3"><span class="text-gray-600">مشارکت قابل تبدیل کسب‌شده</span><span class="font-bold text-purple-700">{{ number_format($convertibleAwardedPoints ?? 0) }}</span></div>
                                    <div class="flex items-center justify-between gap-3"><span class="text-gray-600">مصرف‌شده در تبدیل</span><span class="font-bold text-gray-700">{{ number_format($ledgerConsumedPoints ?? 0) }}</span></div>
                                    <div class="flex items-center justify-between gap-3"><span class="text-gray-600">مشارکت قابل تبدیل باقی‌مانده</span><span class="font-bold text-green-600">{{ number_format($uncashedPoints ?? 0) }}</span></div>
                                    @if(($legacyCashedPoints ?? 0) > 0)
                                        <div class="flex items-center justify-between gap-3"><span class="text-gray-600">تبدیل تاریخی ثبت‌شده</span><span class="font-semibold text-gray-500">{{ number_format($legacyCashedPoints) }}</span></div>
                                    @endif
                                    @if(($participationReversalPoints ?? 0) > 0)
                                        <div class="flex items-center justify-between gap-3"><span class="text-gray-600">اصلاح کاهنده ظرفیت مشارکت</span><span class="font-semibold text-red-600">{{ number_format($participationReversalPoints) }}</span></div>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-500 mt-3">امتیاز کل می‌تواند شامل مشارکت، قابلیت اتکا، تخصص و اعتماد مدنی باشد؛ فقط بخش «مشارکت» که هنگام صدور قابل تبدیل بوده است وارد ظرفیت تبدیل می‌شود.</p>
                            </div>
                        </div>
                        @if(($uncashedPoints ?? 0) > 0)
                            <button type="button" id="convertReputationBtn" class="px-5 py-3 bg-gradient-to-br from-purple-600 to-pink-600 text-white rounded-full font-bold text-sm shadow-lg hover:shadow-xl transition-all hover:scale-105"><i class="fas fa-coins ml-1" aria-hidden="true"></i>تبدیل مشارکت</button>
                        @endif
                    </div>
                </div>

                <div class="nb-card p-6">
                    <div class="flex justify-between items-center mb-6"><h2 class="text-xl font-bold text-gray-800">تراکنش‌های اخیر</h2><a href="{{ route($routePrefix . '.reports', $routeParams) }}" class="text-blue-600 hover:text-blue-800 font-medium">مشاهده همه</a></div>
                    @if($recentTransactions->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">تاریخ</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">نوع</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">مبلغ</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">شرح</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">وضعیت</th></tr></thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($recentTransactions as $transaction)
                                        @php
                                            $isOutgoing = in_array($transaction->from_account_id, $accountIds ?? [], true);
                                            $isIncoming = in_array($transaction->to_account_id, $accountIds ?? [], true);
                                            $isInternal = $isOutgoing && $isIncoming;
                                        @endphp
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $transaction->created_at->format('Y/m/d H:i') }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($isInternal)<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">انتقال داخلی</span>
                                                @elseif($isOutgoing)<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">برداشت</span>
                                                @else<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">واریز</span>@endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                @if($isInternal)<span class="text-blue-600">{{ \App\Helpers\BaharMoney::formatDecimalValueHtml($transaction->amount) }}</span>
                                                @elseif($isOutgoing)<span class="text-red-600">-{{ \App\Helpers\BaharMoney::formatDecimalValueHtml($transaction->amount) }}</span>
                                                @else<span class="text-green-600">+{{ \App\Helpers\BaharMoney::formatDecimalValueHtml($transaction->amount) }}</span>@endif
                                                بهار
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900">{{ $transaction->description ?? 'بدون شرح' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($transaction->status == 'completed')<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">تکمیل شده</span>
                                                @elseif($transaction->status == 'pending')<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">در انتظار</span>
                                                @else<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">ناموفق</span>@endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-8"><svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg><h3 class="mt-2 text-sm font-medium text-gray-900">هیچ تراکنشی یافت نشد</h3><p class="mt-1 text-sm text-gray-500">تراکنش‌های شما در اینجا نمایش داده خواهد شد.</p></div>
                    @endif
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <a href="{{ route($routePrefix . '.reports', $routeParams) }}" class="nb-quick"><span class="nb-quick-icon" style="background: linear-gradient(135deg, #2563eb, #60a5fa);"><i class="fas fa-chart-line"></i></span><div><p class="nb-quick-title">گزارش‌ها</p><p class="text-xs text-gray-500">مشاهده گردش مالی</p></div></a>
                    <a href="{{ route($routePrefix . '.sub-accounts.index', $routeParams) }}" class="nb-quick"><span class="nb-quick-icon" style="background: linear-gradient(135deg, #16a34a, #4ade80);"><i class="fas fa-layer-group"></i></span><div><p class="nb-quick-title">حساب‌های فرعی</p><p class="text-xs text-gray-500">مدیریت حساب‌های زیرمجموعه</p></div></a>
                    @if($isGroupWallet)
                        <a href="{{ route('groups.najm-bahar.transfer', $routeParams) }}" class="nb-quick"><span class="nb-quick-icon" style="background: linear-gradient(135deg, #0ea5e9, #38bdf8);"><i class="fas fa-exchange-alt"></i></span><div><p class="nb-quick-title">انتقال وجه</p><p class="text-xs text-gray-500">انتقال بین حساب‌های فرعی</p></div></a>
                        <a href="{{ route('groups.najm-bahar.audit-logs', $routeParams) }}" class="nb-quick"><span class="nb-quick-icon" style="background: linear-gradient(135deg, #7c3aed, #c084fc);"><i class="fas fa-clipboard-list"></i></span><div><p class="nb-quick-title">گزارش عملیات</p><p class="text-xs text-gray-500">مشاهده لاگ‌های مالی</p></div></a>
                    @else
                        <a href="{{ route('notifications.settings') }}" class="nb-quick"><span class="nb-quick-icon" style="background: linear-gradient(135deg, #7c3aed, #c084fc);"><i class="fas fa-sliders-h"></i></span><div><p class="nb-quick-title">تنظیمات</p><p class="text-xs text-gray-500">مدیریت اعلان‌ها و ترجیحات</p></div></a>
                        <a href="{{ route('user.support-chat.index') }}" class="nb-quick"><span class="nb-quick-icon" style="background: linear-gradient(135deg, #ea580c, #fbbf24);"><i class="fas fa-headset"></i></span><div><p class="nb-quick-title">پشتیبانی</p><p class="text-xs text-gray-500">پاسخ سریع به سوالات شما</p></div></a>
                    @endif
                </div>
            </main>
        </div>
    </div>
</div>

<div id="membershipFeeModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center p-4" style="z-index: var(--nb-z-modal);" role="dialog" aria-modal="true" aria-labelledby="membershipModalTitle" tabindex="-1">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden transform transition-all">
        <div class="bg-gradient-to-br from-purple-600 to-blue-600 p-6 text-white">
            <div class="flex items-center justify-between"><div class="flex items-center gap-3"><div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center"><i class="fas fa-id-card text-2xl" aria-hidden="true"></i></div><div><h3 id="membershipModalTitle" class="text-xl font-bold">پرداخت حق عضویت سالانه</h3><p class="text-sm text-purple-100">تأیید پرداخت</p></div></div><button type="button" class="text-white/80 hover:text-white transition-colors nb-focusable" onclick="closeMembershipModal()" aria-label="بستن پنجره"><i class="fas fa-times text-xl" aria-hidden="true"></i></button></div>
        </div>
        <div id="membershipModalContent" class="p-6 space-y-4"><div class="flex items-center justify-center py-8" role="status" aria-live="polite"><div class="nb-spinner" aria-label="در حال بارگذاری"></div></div></div>
    </div>
</div>

<div id="reputationConversionModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden transform transition-all">
        <div class="bg-gradient-to-br from-purple-600 to-indigo-600 p-6 text-white">
            <div class="flex items-center justify-between"><div class="flex items-center gap-3"><div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center"><i class="fas fa-coins text-2xl"></i></div><div><h3 class="text-xl font-bold">تبدیل مشارکت به پول فعال</h3><p class="text-sm text-purple-100">مصرف ظرفیت قابل تبدیل مشارکت</p></div></div><button type="button" class="text-white/80 hover:text-white transition-colors" onclick="closeReputationModal()"><i class="fas fa-times text-xl"></i></button></div>
        </div>
        <div id="reputationModalContent" class="p-6 space-y-4"><div class="flex items-center justify-center py-8"><div class="animate-spin rounded-full h-10 w-10 border-4 border-purple-200 border-t-purple-600"></div></div></div>
    </div>
</div>

@push('scripts')
<script>
function openMembershipModal() {
    NajmBahar.modal.open('membershipFeeModal');
    const content = document.getElementById('membershipModalContent');
    fetch('{{ route("najm-bahar.membership-fee.info") }}')
        .then(response => response.json())
        .then(data => {
            if (data.has_paid) {
                content.innerHTML = `
                    <div class="text-center py-6">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-check-circle text-3xl text-green-600" aria-hidden="true"></i></div>
                        <h4 class="text-lg font-bold text-gray-800 mb-2">برای سال جاری پرداخت شده</h4>
                        <p class="text-gray-600 mb-4">شما حق عضویت سالانه خود را برای سال جاری پرداخت کرده‌اید.</p>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4 space-y-2">
                            <div class="flex justify-between items-center"><span class="text-sm text-gray-600">تاریخ عضویت:</span><span class="font-bold text-green-700">${data.membership_date_formatted}</span></div>
                            <div class="flex justify-between items-center"><span class="text-sm text-gray-600">سالگرد بعدی:</span><span class="font-bold text-purple-700">${data.next_anniversary_formatted}</span></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-4"><i class="fas fa-calendar-alt ml-1" aria-hidden="true"></i>تا تاریخ سالگرد بعدی نیازی به پرداخت مجدد ندارید</p>
                    </div>
                    <button type="button" onclick="NajmBahar.modal.close('membershipFeeModal')" class="w-full py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors nb-focusable">بستن</button>`;
                return;
            }
            if (data.requires_sub_account) {
                content.innerHTML = `
                    <div class="text-center py-6">
                        <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-wallet text-3xl text-amber-600" aria-hidden="true"></i></div>
                        <h4 class="text-lg font-bold text-gray-800 mb-2">ساخت حساب فرعی ضروری است</h4>
                        <p class="text-gray-600 mb-4">برای پرداخت حق عضویت، ابتدا یک حساب فرعی بسازید و موجودی فعال را به آن منتقل کنید.</p>
                        <div class="space-y-4 text-right">
                            <div class="bg-white border border-amber-200 rounded-lg p-4">
                                <p class="text-sm text-gray-700 mb-3">ایجاد حساب فرعی در همین‌جا:</p>
                                <form action="${data.create_subaccount_store_url}" method="POST" class="space-y-3" id="createSubAccountForm">
                                    @csrf
                                    <input type="text" name="name" class="nb-input" placeholder="نام حساب فرعی (اختیاری)">
                                    <button type="submit" class="w-full nb-btn nb-btn-primary" data-loading-text="در حال ایجاد..."><i class="fas fa-plus" aria-hidden="true"></i> ایجاد حساب فرعی</button>
                                </form>
                            </div>
                            <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4"><p class="text-xs text-emerald-800">بعد از ساخت حساب فرعی، موجودی فعال خود را به حساب فرعی منتقل کنید.</p><a href="${data.transfer_url}" class="inline-flex items-center justify-center gap-2 w-full mt-3 px-4 py-2 bg-gradient-to-br from-emerald-500 to-teal-500 text-white rounded-lg font-semibold shadow-lg hover:shadow-xl transition-all nb-focusable"><i class="fas fa-exchange-alt" aria-hidden="true"></i>انتقال موجودی به حساب فرعی</a></div>
                        </div>
                    </div>
                    <button type="button" onclick="NajmBahar.modal.close('membershipFeeModal')" class="w-full mt-4 py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors nb-focusable">بستن</button>`;
                NajmBahar.form.setupLoadingState('createSubAccountForm');
                return;
            }
            if (!data.has_enough_balance) {
                content.innerHTML = `
                    <div class="text-center py-6">
                        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-exclamation-triangle text-3xl text-red-600" aria-hidden="true"></i></div>
                        <h4 class="text-lg font-bold text-gray-800 mb-2">موجودی ناکافی</h4>
                        <p class="text-gray-600 mb-4">موجودی فعال شما برای پرداخت کافی نیست.</p>
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 space-y-2">
                            <div class="flex justify-between items-center"><span class="text-sm text-gray-600">حساب فرعی مبدا:</span><span class="font-bold text-amber-700">${data.sub_account ? data.sub_account.code : '-'}</span></div>
                            <div class="flex justify-between items-center"><span class="text-sm text-gray-600">موجودی فعال شما:</span><span class="font-bold text-amber-600">${data.balance_active_formatted} بهار</span></div>
                            <div class="flex justify-between items-center"><span class="text-sm text-gray-600">مبلغ مورد نیاز:</span><span class="font-bold text-red-600">${data.total_fee_formatted} بهار</span></div>
                        </div>
                        <div class="bg-white border border-emerald-200 rounded-lg p-4 mt-4 text-right">
                            <p class="text-sm text-gray-700 mb-2">انتقال موجودی فعال به حساب فرعی (همین‌جا):</p>
                            <form action="${data.transfer_to_url || data.transfer_url}" method="POST" class="space-y-3" id="transferForm">
                                @csrf
                                <div><input type="number" id="transferAmount" name="amount" min="1" step="0.01" class="nb-input" placeholder="مبلغ بهار" required><span class="nb-help-text">حداکثر: ${data.main_active_formatted} بهار</span></div>
                                <input type="text" name="description" class="nb-input" placeholder="توضیحات (اختیاری)">
                                <button type="submit" class="w-full nb-btn nb-btn-primary" data-loading-text="در حال انتقال..."><i class="fas fa-exchange-alt" aria-hidden="true"></i> انتقال به حساب فرعی</button>
                            </form>
                        </div>
                    </div>
                    <button type="button" onclick="NajmBahar.modal.close('membershipFeeModal')" class="w-full py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors nb-focusable">بستن</button>`;
                const maxAmount = parseFloat(data.main_active_formatted.replace(/,/g, ''));
                NajmBahar.form.setupNumericValidation('transferAmount', maxAmount);
                NajmBahar.form.setupLoadingState('transferForm');
                return;
            }
            let breakdownHtml = '';
            data.breakdown.forEach(item => {
                breakdownHtml += `<div class="flex justify-between items-center py-2 border-b border-gray-100"><div><p class="font-semibold text-gray-800">${item.name}</p><p class="text-xs text-gray-500 font-mono">${item.account}</p></div><span class="font-bold text-purple-600">${item.amount_formatted} بهار</span></div>`;
            });
            content.innerHTML = `
                <div class="space-y-4">
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                        <div class="flex justify-between items-center mb-2"><span class="text-sm text-gray-700">حساب فرعی مبدا:</span><span class="font-bold text-purple-700">${data.sub_account ? data.sub_account.code : '-'}</span></div>
                        <div class="flex justify-between items-center mb-3"><span class="text-sm text-gray-700">موجودی فعال شما:</span><span class="font-bold text-green-600">${data.balance_active_formatted} بهار</span></div>
                        <div class="flex justify-between items-center"><span class="text-sm font-semibold text-gray-900">مجموع حق عضویت:</span><span class="text-xl font-black text-purple-600">${data.total_fee_formatted} بهار</span></div>
                    </div>
                    <div class="space-y-2"><h4 class="text-sm font-semibold text-gray-700 mb-2">جزئیات تقسیم‌بندی:</h4>${breakdownHtml}</div>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3"><p class="text-xs text-blue-800"><i class="fas fa-info-circle ml-1" aria-hidden="true"></i>پرداخت حق عضویت تنها از موجودی فعال شما کسر می‌شود.</p></div>
                    <form action="{{ route('najm-bahar.membership-fee.pay') }}" method="POST" class="space-y-3" id="payMembershipForm">
                        @csrf
                        <input type="hidden" name="sub_account_id" value="${data.sub_account ? data.sub_account.id : ''}">
                        <button type="submit" class="w-full py-3 nb-btn nb-btn-primary text-lg" data-loading-text="در حال پردازش..."><i class="fas fa-check-circle ml-2" aria-hidden="true"></i>تأیید و پرداخت</button>
                        <button type="button" onclick="NajmBahar.modal.close('membershipFeeModal')" class="w-full py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors nb-focusable">انصراف</button>
                    </form>
                </div>`;
            NajmBahar.form.setupLoadingState('payMembershipForm');
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = `<div class="text-center py-6"><div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-times-circle text-3xl text-red-600" aria-hidden="true"></i></div><h4 class="text-lg font-bold text-gray-800 mb-2">خطا در بار گذاری</h4><p class="text-gray-600">لطفاً دوباره تلاش کنید.</p></div><button type="button" onclick="NajmBahar.modal.close('membershipFeeModal')" class="w-full py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors nb-focusable">بستن</button>`;
        });
}

function closeMembershipModal() { NajmBahar.modal.close('membershipFeeModal'); }

function openReputationModal() {
    const modal = document.getElementById('reputationConversionModal');
    const content = document.getElementById('reputationModalContent');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    fetch('{{ route("reputation.conversion.info") }}')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                content.innerHTML = `<div class="text-center py-6"><div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-times-circle text-3xl text-red-600"></i></div><h4 class="text-lg font-bold text-gray-800 mb-2">خطا</h4><p class="text-gray-600">${data.error}</p></div><button type="button" onclick="closeReputationModal()" class="w-full py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors">بستن</button>`;
                return;
            }

            if (data.remaining_convertible_points <= 0) {
                content.innerHTML = `
                    <div class="text-center py-6">
                        <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-info-circle text-3xl text-amber-600"></i></div>
                        <h4 class="text-lg font-bold text-gray-800 mb-2">مشارکت قابل تبدیل باقی‌مانده ندارید</h4>
                        <p class="text-gray-600 mb-4">در حال حاضر ظرفیت قابل تبدیل مشارکت شما صفر است. سایر ابعاد اعتبار همچنان در امتیاز کل حفظ می‌شوند.</p>
                        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4"><div class="text-sm space-y-2">
                            <div class="flex justify-between"><span class="text-gray-600">مجموع امتیاز اعتبار و مشارکت:</span><span class="font-bold text-purple-700">${data.total_points.toLocaleString()}</span></div>
                            <div class="flex justify-between"><span class="text-gray-600">مشارکت قابل تبدیل کسب‌شده:</span><span class="font-bold text-purple-700">${data.convertible_awarded_points.toLocaleString()}</span></div>
                            <div class="flex justify-between"><span class="text-gray-600">مصرف‌شده در تبدیل:</span><span class="font-bold text-gray-600">${data.ledger_consumed_points.toLocaleString()}</span></div>
                            ${data.legacy_cashed_points > 0 ? `<div class="flex justify-between"><span class="text-gray-600">تبدیل تاریخی ثبت‌شده:</span><span class="font-bold text-gray-500">${data.legacy_cashed_points.toLocaleString()}</span></div>` : ''}
                            ${data.participation_reversal_points > 0 ? `<div class="flex justify-between"><span class="text-gray-600">اصلاح کاهنده ظرفیت:</span><span class="font-bold text-red-600">${data.participation_reversal_points.toLocaleString()}</span></div>` : ''}
                        </div></div>
                    </div>
                    <button type="button" onclick="closeReputationModal()" class="w-full py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors">بستن</button>`;
                return;
            }

            if (!data.has_enough_faded) {
                content.innerHTML = `
                    <div class="text-center py-6">
                        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-exclamation-triangle text-3xl text-red-600"></i></div>
                        <h4 class="text-lg font-bold text-gray-800 mb-2">موجودی کمرنگ ناکافی</h4>
                        <p class="text-gray-600 mb-4">برای فعال‌سازی معادل پولی این مقدار مشارکت، موجودی کمرنگ شما کافی نیست.</p>
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 space-y-2">
                            <div class="flex justify-between items-center"><span class="text-sm text-gray-600">مشارکت قابل تبدیل باقی‌مانده:</span><span class="font-bold text-purple-600">${data.remaining_convertible_points.toLocaleString()}</span></div>
                            <div class="flex justify-between items-center"><span class="text-sm text-gray-600">موجودی کمرنگ شما:</span><span class="font-bold text-amber-600">${data.balance_faded_formatted} بهار</span></div>
                        </div>
                    </div>
                    <button type="button" onclick="closeReputationModal()" class="w-full py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors">بستن</button>`;
                return;
            }

            content.innerHTML = `
                <div class="space-y-4">
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between gap-4"><span class="text-gray-600">مشارکت قابل تبدیل کسب‌شده:</span><span class="font-bold text-purple-700">${data.convertible_awarded_points.toLocaleString()}</span></div>
                            <div class="flex justify-between gap-4"><span class="text-gray-600">مصرف‌شده در تبدیل:</span><span class="font-bold text-gray-600">${data.ledger_consumed_points.toLocaleString()}</span></div>
                            <div class="flex justify-between gap-4"><span class="text-gray-600">مشارکت قابل تبدیل باقی‌مانده:</span><span class="font-bold text-green-600">${data.remaining_convertible_points.toLocaleString()}</span></div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-purple-200"><span class="text-xs text-purple-700"><i class="fas fa-exchange-alt ml-1"></i>${data.conversion_ratio_text}</span></div>
                    </div>
                    <form action="{{ route('reputation.conversion.convert') }}" method="POST" id="conversionForm" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">مقدار مشارکت برای تبدیل:</label>
                            <input type="number" name="points" id="pointsInput" min="${data.conversion_ratio}" max="${data.remaining_convertible_points}" step="${data.conversion_ratio}" value="${data.conversion_ratio}" class="w-full px-4 py-3 border-2 border-purple-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent font-bold text-lg" oninput="updateConversionPreview(${data.conversion_ratio})">
                            <p class="text-xs text-gray-500 mt-1">حداقل: ${data.conversion_ratio} | حداکثر: ${data.remaining_convertible_points.toLocaleString()}</p>
                        </div>
                        <div class="bg-gradient-to-br from-green-50 to-blue-50 border-2 border-green-200 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2"><span class="text-sm text-gray-700">فعال می‌شود:</span><i class="fas fa-arrow-down text-green-600"></i></div>
                            <div class="flex items-center justify-between"><span class="text-2xl font-black text-green-600" id="convertedAmount">1</span><span class="text-lg font-semibold text-green-700">بهار (فعال)</span></div>
                            <div class="mt-2 pt-2 border-t border-green-200 flex justify-between text-xs text-gray-600"><span>موجودی فعال فعلی:</span><span class="font-bold">${data.balance_active_formatted} بهار</span></div>
                        </div>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3"><p class="text-xs text-blue-800"><i class="fas fa-info-circle ml-1"></i>در تبدیل، فقط امتیازهای «مشارکت» که هنگام صدور قابل تبدیل بوده‌اند در دفتر مصرف دقیق ثبت می‌شوند. سابقه امتیازها حذف نمی‌شود و معادل پولی از موجودی کمرنگ شما به موجودی فعال تبدیل می‌شود.</p></div>
                        <div class="flex gap-3"><button type="submit" class="flex-1 py-3 bg-gradient-to-br from-purple-600 to-indigo-600 text-white rounded-lg font-bold text-lg shadow-lg hover:shadow-xl transition-all hover:scale-105"><i class="fas fa-check-circle ml-2"></i>تأیید و تبدیل</button><button type="button" onclick="closeReputationModal()" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors">انصراف</button></div>
                    </form>
                </div>`;
            updateConversionPreview(data.conversion_ratio);
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = `<div class="text-center py-6"><div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-times-circle text-3xl text-red-600"></i></div><h4 class="text-lg font-bold text-gray-800 mb-2">خطا در بارگذاری</h4><p class="text-gray-600">لطفاً دوباره تلاش کنید.</p></div><button type="button" onclick="closeReputationModal()" class="w-full py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors">بستن</button>`;
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
        convertedAmount.textContent = Math.floor(points / ratio).toLocaleString();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('convertReputationBtn');
    if (btn) btn.addEventListener('click', openReputationModal);
    NajmBahar.modal.setup('membershipFeeModal');
    const modal = document.getElementById('reputationConversionModal');
    if (modal) modal.addEventListener('click', function(e) { if (e.target === modal) closeReputationModal(); });
});
</script>
@endpush
@endsection
