@extends('layouts.unified')

@section('title', 'گزارش‌های مالی نجم بهار - ' . config('app.name', 'EarthCoop'))
<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
@push('styles')
<style>
    .reports-container {
        direction: rtl;
    }

    .reports-hero {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.12), rgba(59, 130, 246, 0.08));
        border: 1px solid #e2e8f0;
        border-radius: 1.5rem;
        padding: 1.75rem;
    }

    .hero-title {
        font-size: 2.25rem;
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .hero-subtitle {
        font-size: 0.95rem;
        color: #64748b;
    }

    .hero-meta {
        font-size: 0.85rem;
        color: #94a3b8;
    }
    
    .summary-card {
        background: var(--color-pure-white);
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
    }

    .summary-card::after {
        content: '';
        display: block;
        height: 3px;
        margin-top: 1.25rem;
        background: linear-gradient(90deg, rgba(16, 185, 129, 0.7), rgba(59, 130, 246, 0.7));
        border-radius: 999px;
        opacity: 0.4;
    }
    
    .summary-card-value {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }
    
    .summary-card-label {
        font-size: 0.9rem;
        color: #64748b;
    }
    
    .filters-card {
        background: var(--color-pure-white);
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        margin-bottom: 1.5rem;
    }
    
    .transactions-table {
        background: var(--color-pure-white);
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .table thead {
        background-color: #f8fafc;
    }
    
    .table th {
        padding: 1rem;
        text-align: right;
        font-weight: 600;
        color: var(--color-gentle-black);
        border-bottom: 2px solid #e2e8f0;
    }
    
    .table td {
        padding: 1rem;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .table tbody tr:hover {
        background-color: #f8fafc;
    }

    .account-toggle summary {
        cursor: pointer;
        list-style: none;
    }

    .account-toggle summary::-webkit-details-marker {
        display: none;
    }

    .account-number {
        font-size: 0.875rem;
        font-weight: 700;
        color: #0f172a;
    }

    .account-label {
        font-size: 0.75rem;
        color: #64748b;
        margin-top: 0.25rem;
    }
    
    .transaction-type-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.25rem 0.75rem;
        border-radius: 999px;
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .transaction-type-in {
        background-color: rgba(16, 185, 129, 0.1);
        color: var(--color-earth-green);
    }
    
    .transaction-type-out {
        background-color: rgba(239, 68, 68, 0.1);
        color: var(--color-red-tomato);
    }
    
    .export-buttons {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        align-items: center;
    }

    .action-button {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 1rem;
        border-radius: 0.9rem;
        font-weight: 600;
        font-size: 0.9rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .action-button:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
    }

    .back-button {
        background: #0f172a;
        color: #ffffff;
    }

    .ghost-button {
        background: #ffffff;
        color: #0f172a;
        border: 1px solid #e2e8f0;
    }
</style>
@endpush

@php
$routePrefix = $routePrefix ?? 'najm-bahar.reports';
$routeParams = $routeParams ?? [];
$reportOwnerName = $reportOwnerName ?? '';
$accountNumberDisplay = $accountNumberDisplay ?? '';
$groupId = $routeParams['group'] ?? null;
@endphp

@section('content')
<div class="bg-light-gray/60 py-8 md:py-10" style="background-color: var(--color-light-gray);">
    <div class="container mx-auto px-4 md:px-6 max-w-7xl">
        <div class="reports-container">
            <!-- Header -->
            <div class="reports-hero mb-6">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="hero-title text-gentle-black mb-2">
                            <i class="fas fa-chart-bar ml-3" style="color: var(--color-earth-green);"></i>
                            گزارش‌های مالی
                        </div>
                        <p class="hero-subtitle">مشاهده و تحلیل تراکنش‌های مالی</p>
                        @if($reportOwnerName)
                            <p class="hero-meta mt-3">{{ $reportOwnerName }}</p>
                        @endif
                        @if($accountNumberDisplay)
                            <p class="hero-meta mt-1">شماره حساب: {{ $accountNumberDisplay }}</p>
                        @endif
                    </div>
                    <div class="export-buttons">
                        @if($groupId)
                            <a href="{{ route('groups.show', ['group' => $groupId]) }}" class="action-button back-button">
                                <i class="fas fa-arrow-right"></i>
                                بازگشت به گروه
                            </a>
                        @endif
                        @if($routePrefix === 'groups.najm-bahar.leader-reports' && $groupId)
                            <a href="{{ route('groups.najm-bahar.reports', ['group' => $groupId]) }}" class="action-button ghost-button">
                                <i class="fas fa-layer-group"></i>
                                گزارش گروه
                            </a>
                        @endif
                        @if($routePrefix === 'groups.najm-bahar.reports')
                            <a href="{{ route('groups.najm-bahar.leader-reports.list', ['group' => $groupId]) }}" class="action-button ghost-button">
                                <i class="fas fa-user-shield"></i>
                                گزارش حساب مدیران
                            </a>
                            <a href="{{ route('groups.najm-bahar.audit-logs', $routeParams) }}" class="action-button ghost-button">
                                <i class="fas fa-clipboard-list"></i>
                                گزارش عملیات مالی
                            </a>
                        @endif
                        <a href="{{ route($routePrefix . '.export-pdf', array_merge($routeParams, request()->all())) }}" 
                           class="action-button" style="background:#ef4444;color:#fff;">
                            <i class="fas fa-file-pdf"></i>
                            Export PDF
                        </a>
                        <a href="{{ route($routePrefix . '.export-excel', array_merge($routeParams, request()->all())) }}" 
                           class="action-button" style="background:#22c55e;color:#fff;">
                            <i class="fas fa-file-excel"></i>
                            Export Excel
                        </a>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="summary-card">
                    <div class="summary-card-value" style="color: var(--color-earth-green);">
                        {{ \App\Helpers\BaharMoney::formatDecimalHtml($summary['totalIn']) }}
                    </div>
                    <div class="summary-card-label">مجموع ورودی</div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-card-value" style="color: var(--color-red-tomato);">
                        {{ \App\Helpers\BaharMoney::formatDecimalHtml($summary['totalOut']) }}
                    </div>
                    <div class="summary-card-label">مجموع خروجی</div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-card-value" style="color: var(--color-ocean-blue);">
                        {{ \App\Helpers\BaharMoney::formatDecimalHtml($summary['net']) }}
                    </div>
                    <div class="summary-card-label">خالص</div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-card-value" style="color: var(--color-digital-gold);">
                        {{ number_format($summary['count']) }}
                    </div>
                    <div class="summary-card-label">تعداد تراکنش‌ها</div>
                </div>
            </div>

            @if($routePrefix === 'groups.najm-bahar.leader-reports')
                <div class="filters-card">
                    <div class="flex items-center justify-between gap-4 flex-wrap">
                        <div>
                            <h3 class="text-lg font-bold text-gentle-black mb-1">
                                <i class="fas fa-user-check ml-2" style="color: var(--color-earth-green);"></i>
                                گزارش حساب شخصی مدیر یا بازرس
                            </h3>
                            <p class="text-sm text-slate-500">نمایش شفاف تراکنش‌های مالی در دوره مسئولیت</p>
                            <p class="text-xs text-slate-400 mt-2">بازه پیش‌فرض گزارش: 3 ماه اخیر</p>
                            <p class="text-xs text-slate-400 mt-1">محدوده دسترسی: از سه ماه قبل از شروع مسئولیت تا سه ماه بعد از پایان مسئولیت</p>
                            <p class="text-xs text-emerald-700 mt-2">این گزارش بر اساس تعهد شفافیت مدیران و بازرسان در دوره مسئولیت ارائه می‌شود.</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Filters -->
            <div class="filters-card">
                <form method="GET" action="{{ route($routePrefix, $routeParams) }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">از تاریخ</label>
                        <input type="date" 
                               name="date_from" 
                               value="{{ $dateFrom }}"
                               class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-earth-green focus:border-transparent dark:bg-slate-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">تا تاریخ</label>
                        <input type="date" 
                               name="date_to" 
                               value="{{ $dateTo }}"
                               class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-earth-green focus:border-transparent dark:bg-slate-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">نوع تراکنش</label>
                        <select name="type" 
                                class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-earth-green focus:border-transparent dark:bg-slate-700 dark:text-white">
                            <option value="all" {{ $type == 'all' ? 'selected' : '' }}>همه</option>
                            <option value="in" {{ $type == 'in' ? 'selected' : '' }}>ورودی</option>
                            <option value="out" {{ $type == 'out' ? 'selected' : '' }}>خروجی</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">جستجو</label>
                        <input type="text" 
                               name="search" 
                               value="{{ $search }}"
                               placeholder="جستجو در توضیحات..."
                               class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-earth-green focus:border-transparent dark:bg-slate-700 dark:text-white">
                    </div>
                    
                    <div class="md:col-span-4 flex items-center gap-4">
                        <button type="submit" 
                                class="px-6 py-2 bg-earth-green text-white rounded-lg hover:bg-opacity-90 transition-colors">
                            <i class="fas fa-search ml-2"></i>
                            اعمال فیلتر
                        </button>
                                <a href="{{ route($routePrefix, $routeParams) }}" 
                           class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                            <i class="fas fa-redo ml-2"></i>
                            پاک کردن فیلترها
                        </a>
                    </div>
                </form>
            </div>

            <!-- Transactions Table -->
            <div class="transactions-table">
                <h3 class="text-xl font-bold text-gentle-black mb-4">
                    <i class="fas fa-list ml-2" style="color: var(--color-earth-green);"></i>
                    لیست تراکنش‌ها
                </h3>
                
                @if($transactions->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>شماره رهگیری</th>
                                    <th>تاریخ</th>
                                    <th>از حساب</th>
                                    <th>به حساب</th>
                                    <th>نوع</th>
                                    <th>مبلغ</th>
                                    <th>توضیحات</th>
                                    <th>وضعیت</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transactions as $transaction)
                                    @php
                                            $isIncoming = isset($transaction->to_account_id) && $account && $transaction->to_account_id == $account->id;
                                            $fromAccount = $transaction->fromAccount;
                                            $toAccount = $transaction->toAccount;
                                            
                                            $getAccountLabel = function($acc, $type) {
                                                if (!$acc) return '—';
                                                if ($acc->type === 'system') return $type === 'to' ? 'EarthCoop' : 'سیستم';

                                                if ($acc->type === 'subaccount') {
                                                    $subAcc = \App\Modules\NajmBahar\Models\SubAccount::where('sub_account_code', $acc->account_number)->first();
                                                    $subName = $subAcc?->name ?? 'حساب فرعی';
                                                    $mainAccount = $subAcc?->account;

                                                    if ($mainAccount && $mainAccount->type === 'system') {
                                                        return 'EarthCoop - ' . $subName;
                                                    }

                                                    $user = $mainAccount?->user;
                                                    $userName = $user ? trim($user->first_name . ' ' . $user->last_name) : 'کاربر';
                                                    return $userName . ' - ' . $subName;
                                                }

                                                $user = $acc->user;
                                                $userName = $user ? trim($user->first_name . ' ' . $user->last_name) : 'کاربر';
                                                return $userName;
                                            };
                                    @endphp
                                    <tr>
                                        <td>
                                            <code class="text-xs bg-slate-100 dark:bg-slate-700 px-2 py-1 rounded">
                                                {{ $transaction->tracking_number ?? '—' }}
                                            </code>
                                        </td>
                                        <td>
                                            {{ \Morilog\Jalali\Jalalian::fromCarbon($transaction->created_at)->format('Y/m/d H:i') }}
                                        </td>
                                        <td>
                                            @php
                                                $fromLabel = $getAccountLabel($fromAccount, 'from');
                                                $fromNumber = $fromAccount?->account_number;
                                                $fromNumberDisplay = $fromNumber ?? '—';
                                            @endphp
                                            <details class="account-toggle" {{ $fromNumber ? '' : 'open' }}>
                                                <summary class="account-number" title="{{ $fromLabel }}">{{ $fromNumberDisplay }}</summary>
                                                <div class="account-label">{{ $fromLabel }}</div>
                                            </details>
                                        </td>
                                        <td>
                                            @php
                                                $toLabel = $getAccountLabel($toAccount, 'to');
                                                $toNumber = $toAccount?->account_number;
                                                $toNumberDisplay = $toNumber ?? '—';
                                            @endphp
                                            <details class="account-toggle" {{ $toNumber ? '' : 'open' }}>
                                                <summary class="account-number" title="{{ $toLabel }}">{{ $toNumberDisplay }}</summary>
                                                <div class="account-label">{{ $toLabel }}</div>
                                            </details>
                                        </td>
                                        <td>
                                            <span class="transaction-type-badge {{ $isIncoming ? 'transaction-type-in' : 'transaction-type-out' }}">
                                                <i class="fas {{ $isIncoming ? 'fa-arrow-down' : 'fa-arrow-up' }}"></i>
                                                {{ $isIncoming ? 'ورودی' : 'خروجی' }}
                                            </span>
                                        </td>
                                        <td class="font-bold {{ $isIncoming ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $isIncoming ? '+' : '-' }}{{ \App\Helpers\BaharMoney::formatDecimalValueHtml($transaction->amount) }}
                                        </td>
                                        <td>{{ $transaction->description ?? 'تراکنش' }}</td>
                                        <td>
                                            <span class="px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                                تکمیل شده
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="mt-4">
                        {{ $transactions->links() }}
                    </div>
                @else
                    <div class="text-center py-12 text-slate-500">
                        <i class="fas fa-inbox text-5xl mb-4 opacity-50"></i>
                        <p class="text-lg">هیچ تراکنشی یافت نشد</p>
                        <p class="text-sm mt-2">لطفاً فیلترها را تغییر دهید</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection


