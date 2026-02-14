@extends('layouts.admin')

@section('title', 'داشبورد نجم بهار - ' . config('app.name', 'EarthCoop'))

@push('styles')
<style>
    .stats-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 1.5rem;
        transition: all 0.3s ease;
        border-top: 4px solid;
        height: 100%;
    }
    
    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    }
    
    .stats-card.primary { border-top-color: #3b82f6; }
    .stats-card.success { border-top-color: #10b981; }
    .stats-card.warning { border-top-color: #f59e0b; }
    .stats-card.info { border-top-color: #06b6d4; }
    .stats-card.purple { border-top-color: #8b5cf6; }
    .stats-card.pink { border-top-color: #ec4899; }
    
    .stats-icon {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        opacity: 0.8;
    }
    
    .stats-value {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }
    
    .stats-label {
        font-size: 0.875rem;
        color: #64748b;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <!-- هدر صفحه -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">
            <i class="fas fa-chart-line ml-2"></i>
            داشبورد نجم بهار
        </h1>
        <p class="text-slate-600 dark:text-slate-400 mt-1">نمای کلی سیستم مالی نجم بهار</p>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-8">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            <div>
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">حدنصاب کاربران برای باز شدن تراکنش‌ها</h2>
                <p class="text-slate-600 dark:text-slate-400 mt-1">
                    وضعیت فعلی: <span class="font-semibold {{ $isThresholdMet ? 'text-green-600' : 'text-amber-600' }}">
                        {{ $isThresholdMet ? 'باز' : 'قفل' }}
                    </span>
                </p>
                <div class="mt-3 text-sm text-slate-600 dark:text-slate-400">
                    کاربران فعلی: <span class="font-semibold text-slate-900 dark:text-white">{{ number_format($userCount) }}</span>
                    <span class="mx-2">/</span>
                    حدنصاب: <span class="font-semibold text-slate-900 dark:text-white">{{ number_format($userThreshold) }}</span>
                </div>
            </div>

            <form action="{{ route('admin.najm-bahar.threshold.update') }}" method="POST" class="flex flex-col sm:flex-row gap-3">
                @csrf
                <div>
                    <label class="block text-sm text-slate-600 dark:text-slate-400 mb-1" for="najm_bahar_user_threshold">حدنصاب جدید</label>
                    <input
                        id="najm_bahar_user_threshold"
                        name="najm_bahar_user_threshold"
                        type="number"
                        min="1"
                        value="{{ old('najm_bahar_user_threshold', $userThreshold) }}"
                        class="w-full sm:w-56 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                        required
                    />
                </div>
                <button type="submit" class="mt-6 sm:mt-auto px-4 py-2 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-700 transition">
                    ذخیره
                </button>
            </form>
        </div>
        @error('najm_bahar_user_threshold')
            <div class="text-red-600 text-sm mt-3">{{ $message }}</div>
        @enderror

        <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">مبلغ واریز اولیه</h2>
                    <p class="text-slate-600 dark:text-slate-400 mt-1">
                        پول خلق شده فعلی: <span class="font-semibold text-slate-900 dark:text-white">{{ \App\Helpers\BaharMoney::formatDecimalHtml($totalMinted) }}</span>
                    </p>
                    <div class="mt-3 text-sm text-slate-600 dark:text-slate-400">
                        کاربران فعلی: <span class="font-semibold text-slate-900 dark:text-white">{{ number_format($userCount) }}</span>
                        <span class="mx-2">/</span>
                        مبلغ اولیه: <span class="font-semibold text-slate-900 dark:text-white">{{ \App\Helpers\BaharMoney::formatDecimalHtml($initialAmount) }}</span>
                    </div>
                </div>
                
                <a href="{{ route('admin.najm-bahar.settings.index') }}" class="px-4 py-2 rounded-lg border border-blue-600 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition font-semibold flex items-center gap-2">
                    <i class="fas fa-cog"></i>
                    تنظیمات پیشرفته
                </a>
            </div>

            <form action="{{ route('admin.najm-bahar.initial-amount.update') }}" method="POST" class="flex flex-col sm:flex-row gap-3">
                @csrf
                <div>
                    <label class="block text-sm text-slate-600 dark:text-slate-400 mb-1" for="najm_bahar_initial_amount">مبلغ اولیه جدید (بهار.گل)</label>
                    <input
                        id="najm_bahar_initial_amount"
                        name="najm_bahar_initial_amount"
                        type="number"
                        min="1"
                        value="{{ old('najm_bahar_initial_amount', \App\Helpers\BaharMoney::formatDecimalValue($initialAmount)) }}"
                        step="0.01"
                        class="w-full sm:w-56 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                        required
                    />
                </div>
                <button type="submit" class="mt-6 sm:mt-auto px-4 py-2 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-700 transition">
                    ذخیره
                </button>
            </form>
        </div>
        @error('najm_bahar_initial_amount')
            <div class="text-red-600 text-sm mt-3">{{ $message }}</div>
        @enderror
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-8">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            <div>
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">تقسیم حق عضویت سالانه</h2>
                <p class="text-slate-600 dark:text-slate-400 mt-1">مبالغ به سه حساب سیستمی تقسیم می‌شود.</p>
                <div class="mt-3 text-sm text-slate-600 dark:text-slate-400">
                    مجموع فعلی: <span class="font-semibold text-slate-900 dark:text-white">
                        {{ \App\Helpers\BaharMoney::formatDecimalHtml($membershipSplit['membership_amount'] + $membershipSplit['insurance_amount'] + $membershipSplit['burn_amount']) }}
                    </span>
                </div>
            </div>

            <form action="{{ route('admin.najm-bahar.membership-split.update') }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-3 w-full lg:w-auto">
                @csrf
                <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm text-slate-600 dark:text-slate-400 mb-1">حساب حق عضویت</label>
                        <input
                            name="najm_bahar_membership_fee_account"
                            type="text"
                            value="{{ old('najm_bahar_membership_fee_account', $membershipSplit['membership_account']) }}"
                            class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                            required
                        />
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 dark:text-slate-400 mb-1">حساب بیمه پایه</label>
                        <input
                            name="najm_bahar_membership_fee_insurance_account"
                            type="text"
                            value="{{ old('najm_bahar_membership_fee_insurance_account', $membershipSplit['insurance_account']) }}"
                            class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                            required
                        />
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 dark:text-slate-400 mb-1">حساب امحای پول</label>
                        <input
                            name="najm_bahar_membership_fee_burn_account"
                            type="text"
                            value="{{ old('najm_bahar_membership_fee_burn_account', $membershipSplit['burn_account']) }}"
                            class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                            required
                        />
                    </div>
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm text-slate-600 dark:text-slate-400 mb-1">مبلغ حق عضویت (کل - بهار.گل)</label>
                    <input
                        name="najm_bahar_membership_fee_amount"
                        type="number"
                        min="1"
                        value="{{ old('najm_bahar_membership_fee_amount', \App\Helpers\BaharMoney::formatDecimalValue($membershipSplit['membership_fee_amount'])) }}"
                        step="0.01"
                        class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                        required
                    />
                    <p class="text-xs text-slate-500 mt-1">جمع سهم‌ها باید دقیقاً برابر این مبلغ باشد.</p>
                </div>
                <div>
                    <label class="block text-sm text-slate-600 dark:text-slate-400 mb-1">سهم حق عضویت (بهار.گل)</label>
                    <input
                        name="najm_bahar_membership_fee_membership_amount"
                        type="number"
                        min="0"
                        value="{{ old('najm_bahar_membership_fee_membership_amount', \App\Helpers\BaharMoney::formatDecimalValue($membershipSplit['membership_amount'])) }}"
                        step="0.01"
                        class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                        required
                    />
                </div>
                <div>
                    <label class="block text-sm text-slate-600 dark:text-slate-400 mb-1">سهم بیمه پایه (بهار.گل)</label>
                    <input
                        name="najm_bahar_membership_fee_insurance_amount"
                        type="number"
                        min="0"
                        value="{{ old('najm_bahar_membership_fee_insurance_amount', \App\Helpers\BaharMoney::formatDecimalValue($membershipSplit['insurance_amount'])) }}"
                        step="0.01"
                        class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                        required
                    />
                </div>
                <div>
                    <label class="block text-sm text-slate-600 dark:text-slate-400 mb-1">سهم امحا (بهار.گل)</label>
                    <input
                        name="najm_bahar_membership_fee_burn_amount"
                        type="number"
                        min="0"
                        value="{{ old('najm_bahar_membership_fee_burn_amount', \App\Helpers\BaharMoney::formatDecimalValue($membershipSplit['burn_amount'])) }}"
                        step="0.01"
                        class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                        required
                    />
                </div>
                <div class="md:col-span-3">
                    <button type="submit" class="w-full px-4 py-2 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-700 transition">
                        ذخیره تقسیم حق عضویت
                    </button>
                </div>
            </form>
        </div>
        @if(session('error'))
            <div class="text-red-600 text-sm mt-3">{{ session('error') }}</div>
        @endif
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-8">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">
            <i class="fas fa-sliders-h ml-2"></i>
            عملیات مدیریتی نجم بهار
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <a href="{{ route('admin.najm-bahar.system-accounts.index') }}" class="block p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-slate-500 hover:shadow transition">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center">
                        <i class="fas fa-landmark"></i>
                    </span>
                    <div>
                        <p class="font-semibold text-slate-900 dark:text-white">حساب‌های سیستمی</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">مدیریت حساب اصلی و حساب‌های فرعی سیستم</p>
                    </div>
                </div>
            </a>

            <a href="{{ route('admin.najm-bahar.accounts.index') }}" class="block p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-indigo-400 hover:shadow transition">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <i class="fas fa-list"></i>
                    </span>
                    <div>
                        <p class="font-semibold text-slate-900 dark:text-white">مدیریت همه حساب‌ها</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">فهرست کامل حساب‌ها و تراکنش‌ها</p>
                    </div>
                </div>
            </a>
            <a href="{{ route('admin.najm-bahar.fees.index') }}" class="block p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-emerald-400 hover:shadow transition">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <i class="fas fa-money-bill-wave"></i>
                    </span>
                    <div>
                        <p class="font-semibold text-slate-900 dark:text-white">مدیریت کارمزدها</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">تعریف، ویرایش و فعال‌سازی کارمزدها</p>
                    </div>
                </div>
            </a>

            <a href="{{ route('admin.najm-bahar.salaries.index') }}" class="block p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-rose-400 hover:shadow transition">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center">
                        <i class="fas fa-hand-holding-usd"></i>
                    </span>
                    <div>
                        <p class="font-semibold text-slate-900 dark:text-white">قوانین پرداخت</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">تعریف قوانین حقوق مدیران، بازرسان و پروژه‌ها</p>
                    </div>
                </div>
            </a>

            <a href="{{ route('admin.najm-bahar.salary-runs.index') }}" class="block p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-cyan-400 hover:shadow transition">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-lg bg-cyan-50 text-cyan-600 flex items-center justify-center">
                        <i class="fas fa-play-circle"></i>
                    </span>
                    <div>
                        <p class="font-semibold text-slate-900 dark:text-white">اجرای پرداخت‌ها</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">ایجاد و پردازش دوره‌های پرداخت حقوق</p>
                    </div>
                </div>
            </a>

            <a href="{{ route('admin.najm-bahar.index') }}" class="block p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-blue-400 hover:shadow transition">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                        <i class="fas fa-file-contract"></i>
                    </span>
                    <div>
                        <p class="font-semibold text-slate-900 dark:text-white">مدیریت توافقنامه‌ها</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">ویرایش و انتشار متن توافقنامه‌ها</p>
                    </div>
                </div>
            </a>

            <a href="{{ route('admin.najm-bahar.analytics') }}" class="block p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-purple-400 hover:shadow transition">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center">
                        <i class="fas fa-chart-pie"></i>
                    </span>
                    <div>
                        <p class="font-semibold text-slate-900 dark:text-white">گزارش‌های تحلیلی</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">تحلیل رفتار تراکنش‌ها و کاربران</p>
                    </div>
                </div>
            </a>

            <a href="{{ route('admin.najm-bahar.logs.index') }}" class="block p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-amber-400 hover:shadow transition">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                        <i class="fas fa-clipboard-list"></i>
                    </span>
                    <div>
                        <p class="font-semibold text-slate-900 dark:text-white">لاگ‌های مدیریتی</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">نمایش و فیلتر عملیات مدیران</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-8">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">
            <i class="fas fa-clipboard-list ml-2"></i>
            لاگ عملیات مدیریتی
        </h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-4 py-2 text-right text-slate-700 dark:text-slate-300">ادمین</th>
                        <th class="px-4 py-2 text-right text-slate-700 dark:text-slate-300">عملیات</th>
                        <th class="px-4 py-2 text-right text-slate-700 dark:text-slate-300">توضیح</th>
                        <th class="px-4 py-2 text-right text-slate-700 dark:text-slate-300">تاریخ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($adminLogs as $log)
                        <tr>
                            <td class="px-4 py-2">
                                {{ $log->adminUser?->fullName() ?? ('#' . $log->admin_user_id) }}
                            </td>
                            <td class="px-4 py-2">{{ $log->action }}</td>
                            <td class="px-4 py-2">{{ $log->description }}</td>
                            <td class="px-4 py-2 text-slate-600 dark:text-slate-400">
                                {{ \Morilog\Jalali\Jalalian::fromCarbon($log->created_at)->format('Y/m/d H:i') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-slate-500">لاگی ثبت نشده است</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- کارت‌های آماری -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <div class="stats-card primary">
            <div class="stats-icon text-blue-600">
                <i class="fas fa-users"></i>
            </div>
            <div class="stats-value text-blue-600">{{ number_format($stats['total_accounts']) }}</div>
            <div class="stats-label">کل حساب‌های کاربری</div>
        </div>

        <div class="stats-card success">
            <div class="stats-icon text-green-600">
                <i class="fas fa-exchange-alt"></i>
            </div>
            <div class="stats-value text-green-600">{{ number_format($stats['total_transactions']) }}</div>
            <div class="stats-label">کل تراکنش‌ها</div>
        </div>

        <div class="stats-card warning">
            <div class="stats-icon text-amber-600">
                <i class="fas fa-coins"></i>
            </div>
            <div class="stats-value text-amber-600">{{ \App\Helpers\BaharMoney::formatDecimalHtml($stats['total_balance']) }}</div>
            <div class="stats-label">موجودی حساب‌های کاربری (بهار)</div>
        </div>

        <div class="stats-card success">
            <div class="stats-icon text-green-600">
                <i class="fas fa-seedling"></i>
            </div>
            <div class="stats-value text-green-600">{{ \App\Helpers\BaharMoney::formatDecimalHtml($stats['total_minted']) }}</div>
            <div class="stats-label">کل پول خلق شده (بهار)</div>
        </div>

        <div class="stats-card info">
            <div class="stats-icon text-cyan-600">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stats-value text-cyan-600">{{ number_format($stats['total_sub_accounts']) }}</div>
            <div class="stats-label">حساب‌های فرعی فعال</div>
        </div>

        <div class="stats-card purple">
            <div class="stats-icon text-purple-600">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stats-value text-purple-600">{{ number_format($stats['active_fees']) }}</div>
            <div class="stats-label">کارمزدهای فعال</div>
        </div>

        <div class="stats-card pink">
            <div class="stats-icon text-pink-600">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stats-value text-pink-600">{{ number_format($stats['pending_scheduled']) }}</div>
            <div class="stats-label">تراکنش‌های زمان‌بندی شده</div>
        </div>
    </div>

    <!-- آمار امروز و هفته -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">
                <i class="fas fa-calendar-day ml-2"></i>
                آمار امروز
            </h3>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-slate-600 dark:text-slate-400">تعداد تراکنش‌ها:</span>
                    <span class="font-bold text-slate-900 dark:text-white">{{ number_format($todayTransactions) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-600 dark:text-slate-400">حجم تراکنش‌ها:</span>
                    <span class="font-bold text-green-600">{{ \App\Helpers\BaharMoney::formatDecimalHtml($todayVolume) }}</span>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">
                <i class="fas fa-calendar-week ml-2"></i>
                آمار هفته گذشته
            </h3>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-slate-600 dark:text-slate-400">تعداد تراکنش‌ها:</span>
                    <span class="font-bold text-slate-900 dark:text-white">{{ number_format($weekTransactions) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-600 dark:text-slate-400">حجم تراکنش‌ها:</span>
                    <span class="font-bold text-green-600">{{ \App\Helpers\BaharMoney::formatDecimalHtml($weekVolume) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- نمودار تراکنش‌های 30 روز گذشته -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-8">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">
            <i class="fas fa-chart-area ml-2"></i>
            نمودار تراکنش‌های 30 روز گذشته
        </h3>
        <canvas id="transactionsChart" height="100"></canvas>
    </div>

    <!-- تراکنش‌های اخیر و حساب‌های برتر -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- تراکنش‌های اخیر -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">
                <i class="fas fa-history ml-2"></i>
                تراکنش‌های اخیر
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-700">
                        <tr>
                            <th class="px-4 py-2 text-right text-slate-700 dark:text-slate-300">مبلغ</th>
                            <th class="px-4 py-2 text-right text-slate-700 dark:text-slate-300">نوع</th>
                            <th class="px-4 py-2 text-right text-slate-700 dark:text-slate-300">تاریخ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @forelse($recentTransactions as $transaction)
                            <tr>
                                <td class="px-4 py-2 font-bold">{{ \App\Helpers\BaharMoney::formatDecimalHtml($transaction->amount) }}</td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-1 rounded-full text-xs {{ $transaction->type === 'immediate' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' }}">
                                        {{ $transaction->type === 'immediate' ? 'فوری' : $transaction->type }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-slate-600 dark:text-slate-400">
                                    {{ \Morilog\Jalali\Jalalian::fromCarbon($transaction->created_at)->format('Y/m/d H:i') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-slate-500">هیچ تراکنشی یافت نشد</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- حساب‌های برتر -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">
                <i class="fas fa-trophy ml-2"></i>
                حساب‌های با بیشترین موجودی
            </h3>
            <div class="space-y-3">
                @forelse($topAccounts as $index => $account)
                    <div class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-700 rounded-lg">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-300 flex items-center justify-center font-bold">
                                {{ $index + 1 }}
                            </span>
                            <div>
                                <p class="font-semibold text-slate-900 dark:text-white">{{ $account->account_number }}</p>
                                @if($account->user_id)
                                    <p class="text-xs text-slate-500">کاربر #{{ $account->user_id }}</p>
                                @endif
                            </div>
                        </div>
                        <span class="font-bold text-green-600">{{ \App\Helpers\BaharMoney::formatDecimalHtml($account->balance) }}</span>
                    </div>
                @empty
                    <p class="text-center text-slate-500 py-8">هیچ حسابی یافت نشد</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- توزیع نوع تراکنش‌ها -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">
            <i class="fas fa-pie-chart ml-2"></i>
            توزیع نوع تراکنش‌ها
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($transactionTypes as $type)
                <div class="p-4 bg-slate-50 dark:bg-slate-700 rounded-lg">
                    <p class="text-sm text-slate-600 dark:text-slate-400 mb-1">
                        @if($type->type === 'immediate')
                            فوری
                        @elseif($type->type === 'scheduled')
                            زمان‌بندی شده
                        @else
                            {{ $type->type }}
                        @endif
                    </p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($type->count) }}</p>
                    <p class="text-xs text-slate-500 mt-1">حجم: {{ \App\Helpers\BaharMoney::formatDecimalHtml($type->volume) }}</p>
                </div>
            @endforeach
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // نمودار تراکنش‌های 30 روز گذشته
    const ctx = document.getElementById('transactionsChart').getContext('2d');
    const dailyData = @json($dailyTransactions);
    
    const labels = dailyData.map(item => {
        const date = new Date(item.date);
        return date.toLocaleDateString('fa-IR', { month: 'short', day: 'numeric' });
    });
    
    const counts = dailyData.map(item => item.count);
    const volumes = dailyData.map(item => item.volume);
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'تعداد تراکنش‌ها',
                    data: counts,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y',
                },
                {
                    label: 'حجم تراکنش‌ها (بهار)',
                    data: volumes,
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1',
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'تعداد'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'حجم (بهار)'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                }
            }
        }
    });
</script>
@endpush
@endsection


