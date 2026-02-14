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
        z-index: var(--nb-z-base);
        pointer-events: none;
    }

    /* Hero uses design tokens */
    .nb-hero {
        position: relative;
        background: var(--nb-gradient-hero);
        color: var(--nb-color-pure-white);
        border-radius: var(--nb-radius-xl);
        padding: var(--nb-space-8);
        box-shadow: var(--nb-shadow-xl);
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

    /* Improved contrast for WCAG AA */
    .nb-chip {
        display: inline-flex;
        align-items: center;
        gap: var(--nb-space-2);
        padding: var(--nb-space-2) var(--nb-space-4);
        border-radius: var(--nb-radius-full);
        font-size: var(--nb-text-sm);
        font-weight: var(--nb-font-semibold);
        background: rgba(255, 255, 255, 0.25);
        color: var(--nb-color-gentle-black);
        backdrop-filter: blur(8px);
    }

    /* Card with tokens */
    .nb-card {
        background: var(--nb-color-pure-white);
        border-radius: var(--nb-radius-lg);
        border: 1px solid var(--nb-color-gray-200);
        box-shadow: var(--nb-shadow-lg);
        transition: transform var(--nb-transition-base), box-shadow var(--nb-transition-base);
        animation: nb-fade-up var(--nb-transition-slow) ease both;
    }

    .nb-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--nb-shadow-hover);
    }

    /* Stat with tokens */
    .nb-stat {
        background: var(--nb-gradient-card);
        border-radius: var(--nb-radius-md);
        padding: var(--nb-space-5);
        border: 1px solid var(--nb-color-gray-200);
        transition: transform var(--nb-transition-fast), box-shadow var(--nb-transition-fast);
    }

    .nb-stat:hover {
        transform: translateY(-3px);
        box-shadow: var(--nb-shadow-md);
    }

    .nb-metric {
        font-size: var(--nb-text-3xl);
        font-weight: var(--nb-font-extrabold);
        color: var(--nb-color-gentle-black);
    }

    .nb-metric-accent {
        color: var(--nb-color-dark-green);
    }

    /* Action button with all states */
    .nb-action {
        background: var(--nb-gradient-action);
        color: var(--nb-color-pure-white);
        border-radius: var(--nb-radius-full);
        padding: var(--nb-space-3) var(--nb-space-5);
        font-weight: var(--nb-font-semibold);
        box-shadow: var(--nb-shadow-action);
        transition: transform var(--nb-transition-fast), box-shadow var(--nb-transition-fast);
        border: none;
        cursor: pointer;
    }

    .nb-action:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: var(--nb-shadow-action-hover);
    }

    .nb-action:active:not(:disabled) {
        transform: translateY(0);
    }

    .nb-action:focus-visible {
        outline: var(--nb-focus-ring);
        outline-offset: var(--nb-focus-offset);
    }

    .nb-action:disabled {
        opacity: var(--nb-opacity-disabled);
        cursor: not-allowed;
    }

    .nb-sidebar {
        position: relative;
    }
</style>
@endpush

@section('content')
<div class="bg-light-gray/60 py-10 md:py-12 nb-dashboard" style="background-color: var(--color-light-gray);">
    <div class="nb-container relative" style="z-index: var(--nb-z-base); max-width: var(--nb-container-max-width);">
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

        <div class="grid grid-cols-1 lg:grid-cols-[320px_minmax(0,1fr)] gap-6 items-start mt-6">
            <div class="lg:order-1 nb-sidebar">
                @include('najm-bahar.partials.sidebar')
            </div>

            <main class="space-y-4 lg:order-2">

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

                <!-- نمای کلی حساب شما - first -->
                <div class="nb-card p-5 mt-6">
                    <div class="flex items-center justify-between flex-wrap gap-4 mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800">
                                <i class="fas fa-wallet ml-2" style="color: var(--nb-color-earth-green);" aria-hidden="true"></i>
                                نمای کلی حساب شما
                            </h2>
                            <p class="text-sm text-gray-500">خلاصه موجودی و فعالیت‌های مالی</p>
                        </div>
                        <div class="flex flex-col sm:flex-row items-center gap-3">
                            <button type="button" id="membershipFeeBtn" class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-br from-purple-600 to-blue-600 text-white rounded-full font-semibold text-sm shadow-lg hover:shadow-xl transition-all hover:scale-105">
                                <i class="fas fa-id-card"></i>
                                پرداخت حق عضویت سالانه
                            </button>
                            <a href="{{ route('najm-bahar.wallet') }}" class="nb-action">
                                مشاهده کیف پول
                                <i class="fas fa-arrow-left"></i>
                            </a>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" style="gap: var(--nb-space-4);">
                        <!-- Balance -->
                        <div class="nb-stat bg-gradient-to-br from-emerald-50 to-emerald-100 border-emerald-200">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm text-emerald-700 font-semibold">موجودی حساب اصلی</p>
                                <div class="w-8 h-8 rounded-full bg-emerald-200 flex items-center justify-center">
                                    <i class="fas fa-coins text-emerald-700" aria-hidden="true"></i>
                                </div>
                            </div>
                            <p class="nb-metric nb-metric-accent">{{ \App\Helpers\BaharMoney::formatDecimalHtml($account->balance) }}</p>
                        </div>
                        <!-- Account Number -->
                        <div class="nb-stat bg-gradient-to-br from-blue-50 to-blue-100 border-blue-200">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm text-blue-700 font-semibold">شماره حساب</p>
                                <div class="w-8 h-8 rounded-full bg-blue-200 flex items-center justify-center">
                                    <i class="fas fa-hashtag text-blue-700" aria-hidden="true"></i>
                                </div>
                            </div>
                            <p class="text-base font-mono text-blue-800">{{ $account->account_number }}</p>
                        </div>
                        <!-- Transactions Count -->
                        <div class="nb-stat bg-gradient-to-br from-purple-50 to-purple-100 border-purple-200">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm text-purple-700 font-semibold">تعداد تراکنش‌های شما</p>
                                <div class="w-8 h-8 rounded-full bg-purple-200 flex items-center justify-center">
                                    <i class="fas fa-exchange-alt text-purple-700" aria-hidden="true"></i>
                                </div>
                            </div>
                            <p class="nb-metric nb-metric-accent text-purple-700">{{ number_format($userTransactionsCount) }}</p>
                        </div>
                    </div>
                </div>

                <!-- گزارش کلی سامانه - second -->
                <div class="nb-card p-5">
                    <div class="flex items-center justify-between flex-wrap gap-4 mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800">
                                <i class="fas fa-chart-pie ml-2" style="color: var(--nb-color-ocean-blue);" aria-hidden="true"></i>
                                گزارش کلی سامانه
                            </h2>
                            <p class="text-sm text-gray-500">تصویر کلی از وضعیت سیستم نجم بهار</p>
                        </div>
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-semibold {{ $isThresholdMet ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                            <i class="fas fa-signal"></i>
                            {{ $isThresholdMet ? 'تراکنش‌ها فعال' : 'تراکنش‌ها قفل' }}
                        </span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" style="gap: var(--nb-space-4);">
                        <!-- 1. تعداد کل اعضای جامعه -->
                        <div class="nb-stat bg-gradient-to-br from-cyan-50 to-cyan-100 border-cyan-200">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm text-cyan-700 font-semibold">تعداد کل اعضای جامعه</p>
                                <div class="w-8 h-8 rounded-full bg-cyan-200 flex items-center justify-center">
                                    <i class="fas fa-users text-cyan-700" aria-hidden="true"></i>
                                </div>
                            </div>
                            <p class="nb-metric text-cyan-700">{{ number_format($userCount) }}</p>
                        </div>
                        <!-- 2. کل پول خلق شده -->
                        <div class="nb-stat bg-gradient-to-br from-green-50 to-green-100 border-green-200">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm text-green-700 font-semibold">کل پول خلق شده</p>
                                <div class="w-8 h-8 rounded-full bg-green-200 flex items-center justify-center">
                                    <i class="fas fa-gem text-green-700" aria-hidden="true"></i>
                                </div>
                            </div>
                            <p class="nb-metric text-green-700">{{ \App\Helpers\BaharMoney::formatDecimalHtml($totalMinted) }}</p>
                        </div>
                        <!-- 3. موجودی حساب حق عضویت EarthCoop -->
                        <div class="nb-stat bg-gradient-to-br from-yellow-50 to-yellow-100 border-yellow-200">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm text-yellow-700 font-semibold">موجودی حساب حق عضویت</p>
                                <div class="w-8 h-8 rounded-full bg-yellow-200 flex items-center justify-center">
                                    <i class="fas fa-receipt text-yellow-700" aria-hidden="true"></i>
                                </div>
                            </div>
                            <p class="nb-metric text-yellow-700">{{ \App\Helpers\BaharMoney::formatDecimalHtml($membershipBalance) }}</p>
                            <p class="text-xs text-yellow-600 mt-1 font-mono">{{ $membershipAccountCode }}</p>
                        </div>
                        <!-- 4. وضعیت تراکنش‌های کاربری -->
                        <div class="nb-stat bg-gradient-to-br {{ $isThresholdMet ? 'from-green-50 to-green-100 border-green-200' : 'from-amber-50 to-amber-100 border-amber-200' }}">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm {{ $isThresholdMet ? 'text-green-700' : 'text-amber-700' }} font-semibold">وضعیت تراکنش‌های کاربری</p>
                                <div class="w-8 h-8 rounded-full {{ $isThresholdMet ? 'bg-green-200' : 'bg-amber-200' }} flex items-center justify-center">
                                    <i class="fas {{ $isThresholdMet ? 'fa-check-circle text-green-700' : 'fa-lock text-amber-700' }}" aria-hidden="true"></i>
                                </div>
                            </div>
                            <p class="nb-metric {{ $isThresholdMet ? 'text-green-700' : 'text-amber-700' }}">{{ $isThresholdMet ? 'فعال' : 'قفل' }}</p>
                            <p class="text-xs {{ $isThresholdMet ? 'text-green-600' : 'text-amber-600' }} mt-1">بر اساس حدنصاب سامانه</p>
                        </div>
                        <!-- 5. حدنصاب فعال‌سازی تراکنش -->
                        <div class="nb-stat bg-gradient-to-br from-indigo-50 to-indigo-100 border-indigo-200">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm text-indigo-700 font-semibold">حدنصاب فعال‌سازی</p>
                                <div class="w-8 h-8 rounded-full bg-indigo-200 flex items-center justify-center">
                                    <i class="fas fa-chart-line text-indigo-700" aria-hidden="true"></i>
                                </div>
                            </div>
                            <p class="nb-metric text-indigo-700">{{ number_format($userThreshold) }}</p>
                            <p class="text-xs text-indigo-600 mt-1">عضو پایه</p>
                        </div>
                        <!-- 6. کاربران باقی‌مانده تا حدنصاب -->
                        <div class="nb-stat bg-gradient-to-br from-rose-50 to-rose-100 border-rose-200">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm text-rose-700 font-semibold">کاربران باقی‌مانده</p>
                                <div class="w-8 h-8 rounded-full bg-rose-200 flex items-center justify-center">
                                    <i class="fas fa-hourglass-end text-rose-700" aria-hidden="true"></i>
                                </div>
                            </div>
                            <p class="nb-metric text-rose-700">{{ number_format($remainingUsers) }}</p>
                            <p class="text-xs text-rose-600 mt-1">تا حدنصاب</p>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</div>

<!-- مدال پرداخت حق عضویت -->
<div id="membershipFeeModal" 
     class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center p-4"
     style="z-index: var(--nb-z-modal);"
     role="dialog" 
     aria-modal="true" 
     aria-labelledby="membershipModalTitle"
     tabindex="-1">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden transform transition-all">
        <div class="bg-gradient-to-br from-purple-600 to-blue-600 p-6 text-white">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-id-card text-2xl" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h3 id="membershipModalTitle" class="text-xl font-bold">پرداخت حق عضویت سالانه</h3>
                        <p class="text-sm text-purple-100">تأیید پرداخت</p>
                    </div>
                </div>
                <button type="button" 
                        class="text-white/80 hover:text-white transition-colors nb-focusable" 
                        onclick="closeMembershipModal()"
                        aria-label="بستن پنجره">
                    <i class="fas fa-times text-xl" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div id="membershipModalContent" class="p-6 space-y-4">
            <!-- محتوا از API بارگذاری می‌شود -->
            <div class="flex items-center justify-center py-8" role="status" aria-live="polite">
                <div class="nb-spinner" aria-label="در حال بارگذاری"></div>
            </div>
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
                        <i class="fas fa-coins text-2xl" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold">تبدیل امتیاز به پول فعال</h3>
                        <p class="text-sm text-purple-100">نقد کردن امتیازات</p>
                    </div>
                </div>
                <button type="button" class="text-white/80 hover:text-white transition-colors" onclick="closeReputationModal()">
                    <i class="fas fa-times text-xl" aria-hidden="true"></i>
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
function openMembershipModal() {
    NajmBahar.modal.open('membershipFeeModal');
    
    const content = document.getElementById('membershipModalContent');
    
    // بارگذاری اطلاعات از API
    fetch('{{ route("najm-bahar.membership-fee.info") }}')
        .then(response => response.json())
        .then(data => {
            if (data.has_paid) {
                content.innerHTML = `
                    <div class="text-center py-6">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-check-circle text-3xl text-green-600" aria-hidden="true"></i>
                        </div>
                        <h4 class="text-lg font-bold text-gray-800 mb-2">برای سال جاری پرداخت شده</h4>
                        <p class="text-gray-600 mb-4">شما حق عضویت سالانه خود را برای سال جاری پرداخت کرده‌اید.</p>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4 space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">تاریخ عضویت:</span>
                                <span class="font-bold text-green-700">${data.membership_date_formatted}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">سالگرد بعدی:</span>
                                <span class="font-bold text-purple-700">${data.next_anniversary_formatted}</span>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-4">
                            <i class="fas fa-calendar-alt ml-1" aria-hidden="true"></i>
                            تا تاریخ سالگرد بعدی نیازی به پرداخت مجدد ندارید
                        </p>
                    </div>
                    <button type="button" onclick="NajmBahar.modal.close('membershipFeeModal')" class="w-full py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors nb-focusable">
                        بستن
                    </button>
                `;
                return;
            }

            if (data.requires_sub_account) {
                content.innerHTML = `
                    <div class="text-center py-6">
                        <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-wallet text-3xl text-amber-600" aria-hidden="true"></i>
                        </div>
                        <h4 class="text-lg font-bold text-gray-800 mb-2">ساخت حساب فرعی ضروری است</h4>
                        <p class="text-gray-600 mb-4">برای پرداخت حق عضویت، ابتدا یک حساب فرعی بسازید و موجودی فعال را به آن منتقل کنید.</p>
                        <div class="space-y-4 text-right">
                            <div class="bg-white border border-amber-200 rounded-lg p-4">
                                <p class="text-sm text-gray-700 mb-3">ایجاد حساب فرعی در همین‌جا:</p>
                                <form action="${data.create_subaccount_store_url}" method="POST" class="space-y-3" id="createSubAccountForm">
                                    @csrf
                                    <input type="text" name="name" class="nb-input" placeholder="نام حساب فرعی (اختیاری)">
                                    <button type="submit" class="w-full nb-btn nb-btn-primary" data-loading-text="در حال ایجاد...">
                                        <i class="fas fa-plus" aria-hidden="true"></i>
                                        ایجاد حساب فرعی
                                    </button>
                                </form>
                            </div>
                            <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4">
                                <p class="text-xs text-emerald-800">
                                    بعد از ساخت حساب فرعی، موجودی فعال خود را به حساب فرعی منتقل کنید.
                                </p>
                                <a href="${data.transfer_url}" class="inline-flex items-center justify-center gap-2 w-full mt-3 px-4 py-2 bg-gradient-to-br from-emerald-500 to-teal-500 text-white rounded-lg font-semibold shadow-lg hover:shadow-xl transition-all nb-focusable">
                                    <i class="fas fa-exchange-alt" aria-hidden="true"></i>
                                    انتقال موجودی به حساب فرعی
                                </a>
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="NajmBahar.modal.close('membershipFeeModal')" class="w-full mt-4 py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors nb-focusable">
                        بستن
                    </button>
                `;
                // Setup loading state for create form
                NajmBahar.form.setupLoadingState('createSubAccountForm');
                return;
            }

            if (!data.has_enough_balance) {
                content.innerHTML = `
                    <div class="text-center py-6">
                        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl text-red-600" aria-hidden="true"></i>
                        </div>
                        <h4 class="text-lg font-bold text-gray-800 mb-2">موجودی ناکافی</h4>
                        <p class="text-gray-600 mb-4">موجودی فعال شما برای پرداخت کافی نیست.</p>
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">حساب فرعی مبدا:</span>
                                <span class="font-bold text-amber-700">${data.sub_account ? data.sub_account.code : '-'}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">موجودی فعال شما:</span>
                                <span class="font-bold text-amber-600">${data.balance_active_formatted} بهار</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">مبلغ مورد نیاز:</span>
                                <span class="font-bold text-red-600">${data.total_fee_formatted} بهار</span>
                            </div>
                        </div>
                        <div class="bg-white border border-emerald-200 rounded-lg p-4 mt-4 text-right">
                            <p class="text-sm text-gray-700 mb-2">انتقال موجودی فعال به حساب فرعی (همین‌جا):</p>
                            <form action="${data.transfer_to_url || data.transfer_url}" method="POST" class="space-y-3" id="transferForm">
                                @csrf
                                <div>
                                    <input type="number" id="transferAmount" name="amount" min="1" step="0.01" class="nb-input" placeholder="مبلغ بهار" required>
                                    <span class="nb-help-text">حداکثر: ${data.main_active_formatted} بهار</span>
                                </div>
                                <input type="text" name="description" class="nb-input" placeholder="توضیحات (اختیاری)">
                                <button type="submit" class="w-full nb-btn nb-btn-primary" data-loading-text="در حال انتقال...">
                                    <i class="fas fa-exchange-alt" aria-hidden="true"></i>
                                    انتقال به حساب فرعی
                                </button>
                            </form>
                        </div>
                    </div>
                    <button type="button" onclick="NajmBahar.modal.close('membershipFeeModal')" class="w-full py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors nb-focusable">
                        بستن
                    </button>
                `;
                // Setup validation and loading
                const maxAmount = parseFloat(data.main_active_formatted.replace(/,/g, ''));
                NajmBahar.form.setupNumericValidation('transferAmount', maxAmount);
                NajmBahar.form.setupLoadingState('transferForm');
                return;
            }

            // نمایش تقسیم‌بندی و دکمه پرداخت
            let breakdownHtml = '';
            data.breakdown.forEach(item => {
                breakdownHtml += `
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <div>
                            <p class="font-semibold text-gray-800">${item.name}</p>
                            <p class="text-xs text-gray-500 font-mono">${item.account}</p>
                        </div>
                        <span class="font-bold text-purple-600">${item.amount_formatted} بهار</span>
                    </div>
                `;
            });

            content.innerHTML = `
                <div class="space-y-4">
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm text-gray-700">حساب فرعی مبدا:</span>
                            <span class="font-bold text-purple-700">${data.sub_account ? data.sub_account.code : '-'}</span>
                        </div>
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-sm text-gray-700">موجودی فعال شما:</span>
                            <span class="font-bold text-green-600">${data.balance_active_formatted} بهار</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-semibold text-gray-900">مجموع حق عضویت:</span>
                            <span class="text-xl font-black text-purple-600">${data.total_fee_formatted} بهار</span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">جزئیات تقسیم‌بندی:</h4>
                        ${breakdownHtml}
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <p class="text-xs text-blue-800">
                            <i class="fas fa-info-circle ml-1" aria-hidden="true"></i>
                            پرداخت حق عضویت تنها از موجودی فعال شما کسر می‌شود.
                        </p>
                    </div>

                    <form action="{{ route('najm-bahar.membership-fee.pay') }}" method="POST" class="space-y-3" id="payMembershipForm">
                        @csrf
                        <input type="hidden" name="sub_account_id" value="${data.sub_account ? data.sub_account.id : ''}">
                        <button type="submit" class="w-full py-3 nb-btn nb-btn-primary text-lg" data-loading-text="در حال پردازش...">
                            <i class="fas fa-check-circle ml-2" aria-hidden="true"></i>
                            تأیید و پرداخت
                        </button>
                        <button type="button" onclick="NajmBahar.modal.close('membershipFeeModal')" class="w-full py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors nb-focusable">
                            انصراف
                        </button>
                    </form>
                </div>
            `;
            // Setup loading state
            NajmBahar.form.setupLoadingState('payMembershipForm');
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = `
                <div class="text-center py-6">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-times-circle text-3xl text-red-600" aria-hidden="true"></i>
                    </div>
                    <h4 class="text-lg font-bold text-gray-800 mb-2">خطا در بار گذاری</h4>
                    <p class="text-gray-600">لطفاً دوباره تلاش کنید.</p>
                </div>
                <button type="button" onclick="NajmBahar.modal.close('membershipFeeModal')" class="w-full py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors nb-focusable">
                    بستن
                </button>
            `;
        });
}

function closeMembershipModal() {
    NajmBahar.modal.close('membershipFeeModal');
}

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

// Setup modal on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('membershipFeeBtn');
    if (btn) {
        btn.addEventListener('click', openMembershipModal);
        btn.setAttribute('data-trigger-element', 'membershipFeeBtn');
    }

    // Setup modal accessibility
    NajmBahar.modal.setup('membershipFeeModal');

    const reputationModal = document.getElementById('reputationConversionModal');
    if (reputationModal) {
        reputationModal.addEventListener('click', function(e) {
            if (e.target === reputationModal) {
                closeReputationModal();
            }
        });
    }
});
</script>
@endpush
@endsection
