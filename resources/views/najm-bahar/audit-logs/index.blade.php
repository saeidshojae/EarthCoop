@extends('layouts.unified')

@section('title', 'گزارش عملیات مالی نجم بهار - ' . config('app.name', 'EarthCoop'))
@vite(['resources/css/app.css', 'resources/js/app.js'])
@push('styles')
<style>
    .logs-container {
        direction: rtl;
    }

    .filters-card,
    .logs-table {
        background: var(--color-pure-white);
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        margin-bottom: 1.5rem;
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
</style>
@endpush

@php
$routePrefix = $routePrefix ?? 'groups.najm-bahar.audit-logs';
$routeParams = $routeParams ?? [];
$reportOwnerName = $reportOwnerName ?? '';
$accountNumberDisplay = $accountNumberDisplay ?? '';
$filters = $filters ?? [];
$actionLabels = [
    'subaccount.create' => 'ایجاد حساب فرعی',
    'subaccount.transfer_to' => 'انتقال به حساب فرعی',
    'subaccount.transfer_from' => 'انتقال از حساب فرعی',
    'subaccount.deactivate' => 'غیرفعال‌سازی حساب فرعی',
    'subaccount.rename' => 'ویرایش نام حساب فرعی',
    'subaccount.close' => 'بستن حساب فرعی',
    'subaccount.activate' => 'فعال سازی حساب فرعی',
    'subaccount.transfer_between' => 'انتقال بین حساب‌های فرعی',
    'subaccount.transfer_between_scheduled' => 'انتقال زمان‌بندی‌شده بین حساب‌های فرعی',
    'subaccount.transfer_between_executed' => 'اجرای انتقال زمان‌بندی‌شده',
];
$roleLabels = [
    0 => 'ناظر',
    1 => 'فعال',
    2 => 'بازرس',
    3 => 'مدیر',
    4 => 'مهمان',
];
@endphp

@section('content')
<div class="bg-light-gray/60 py-8 md:py-10" style="background-color: var(--color-light-gray);">
    <div class=\"nb-page-container\" style=\"max-width: var(--nb-container-max-width-xl);\">
        <div class="logs-container">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-3xl md:text-4xl font-extrabold text-gentle-black mb-2">
                        <i class="fas fa-clipboard-list ml-3" style="color: var(--color-earth-green);"></i>
                        گزارش عملیات مالی
                    </h1>
                    <p class="text-slate-600 dark:text-slate-400">شفافیت و ردیابی عملیات مالی گروه</p>
                    @if($reportOwnerName)
                        <p class="text-sm text-slate-500 mt-2">{{ $reportOwnerName }}</p>
                    @endif
                    @if($accountNumberDisplay)
                        <p class="text-sm text-slate-500 mt-1">شماره حساب: {{ $accountNumberDisplay }}</p>
                    @endif
                </div>
                <div class="flex gap-2 flex-wrap">
                    @if(isset($routeParams['group']))
                        <a href="{{ route('groups.najm-bahar.reports', ['group' => $routeParams['group']]) }}"
                           class="px-4 py-2 bg-white text-slate-700 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
                            <i class="fas fa-chart-bar ml-2"></i>
                            گزارش‌های مالی
                        </a>
                    @endif
                    <a href="{{ route($routePrefix . '.export', array_merge($routeParams, request()->all())) }}"
                       class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors">
                        <i class="fas fa-file-export ml-2"></i>
                        خروجی CSV
                    </a>
                </div>
            </div>

            <div class="filters-card">
                <form method="GET" action="{{ route($routePrefix, $routeParams) }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">از تاریخ</label>
                        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                               class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-earth-green focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">تا تاریخ</label>
                        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                               class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-earth-green focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">شناسه/نام عامل</label>
                        <input type="text" name="actor" value="{{ $filters['actor'] ?? '' }}"
                               placeholder="مثال: 12 یا علی"
                               class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-earth-green focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">نوع عملیات</label>
                        <select name="action"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-earth-green focus:border-transparent">
                            <option value="">همه</option>
                            @foreach($actionLabels as $actionKey => $actionLabel)
                                <option value="{{ $actionKey }}" {{ ($filters['action'] ?? '') === $actionKey ? 'selected' : '' }}>{{ $actionLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-slate-700 mb-2">جستجو</label>
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                               placeholder="شرح، شماره حساب یا کد حساب فرعی"
                               class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-earth-green focus:border-transparent">
                    </div>
                    <div class="md:col-span-2 flex items-end gap-3">
                        <button type="submit" class="px-6 py-2 bg-earth-green text-white rounded-lg hover:bg-opacity-90 transition-colors">
                            <i class="fas fa-search ml-2"></i>
                            اعمال فیلتر
                        </button>
                        <a href="{{ route($routePrefix, $routeParams) }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                            <i class="fas fa-redo ml-2"></i>
                            پاک کردن
                        </a>
                    </div>
                </form>
            </div>

            <div class="logs-table">
                <h3 class="text-xl font-bold text-gentle-black mb-4">
                    <i class="fas fa-list ml-2" style="color: var(--color-earth-green);"></i>
                    لیست عملیات ثبت‌شده
                </h3>
                @if($logs->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>تاریخ</th>
                                    <th>عامل</th>
                                    <th>نقش</th>
                                    <th>عملیات</th>
                                    <th>حساب</th>
                                    <th>حساب فرعی</th>
                                    <th>مبلغ</th>
                                    <th>شرح</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($logs as $log)
                                    @php
                                        $actorName = $log->actor ? trim($log->actor->first_name . ' ' . $log->actor->last_name) : 'سیستم';
                                    @endphp
                                    <tr>
                                        <td>{{ \Morilog\Jalali\Jalalian::fromCarbon($log->created_at)->format('Y/m/d H:i') }}</td>
                                        <td>{{ $actorName }}</td>
                                        <td>{{ $roleLabels[$log->actor_role] ?? '-' }}</td>
                                        <td>{{ $actionLabels[$log->action] ?? $log->action }}</td>
                                        <td>{{ $log->account_number ?? '-' }}</td>
                                        <td>{{ $log->sub_account_code ? str_replace('-', '/', $log->sub_account_code) : '-' }}</td>
                                        <td>{{ $log->amount !== null ? \App\Helpers\BaharMoney::formatDecimalHtml($log->amount) : '-' }}</td>
                                        <td>{{ $log->description ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $logs->links() }}
                    </div>
                @else
                    <div class="text-center text-slate-500 py-10">
                        عملیاتی ثبت نشده است.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

