@extends('layouts.admin')

@section('title', 'تاریخچه سیاست انتخابات')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <div class="flex items-center justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">تاریخچه سیاست انتخابات</h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">
                {{ $setting->name() }} — تاریخچه فقط‌خواندنی نسخه‌های منتشرشده
            </p>
        </div>
        <a href="{{ route('admin.group.setting.index') }}"
           class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200">
            بازگشت به تنظیمات انتخابات
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 lg:col-span-2">
            <div class="text-sm text-slate-500 dark:text-slate-400 mb-2">نسخه مؤثر فعلی</div>
            @if($currentPolicy)
                <div class="flex flex-wrap items-center gap-3 mb-4">
                    <span class="text-xl font-bold text-slate-900 dark:text-white">نسخه {{ $currentPolicy->version }}</span>
                    <span class="px-2.5 py-1 text-xs rounded-full {{ $currentPolicy->election_status ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        {{ $currentPolicy->election_status ? 'انتخابات فعال' : 'انتخابات غیرفعال' }}
                    </span>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                    <div><span class="text-slate-500">مدیر:</span> <strong>{{ $currentPolicy->manager_count }}</strong></div>
                    <div><span class="text-slate-500">بازرس:</span> <strong>{{ $currentPolicy->inspector_count }}</strong></div>
                    <div><span class="text-slate-500">حدنصاب:</span> <strong>{{ $currentPolicy->start_threshold }}</strong></div>
                    <div><span class="text-slate-500">مدت رأی‌گیری:</span> <strong>{{ $currentPolicy->voting_duration_days }} روز</strong></div>
                    <div><span class="text-slate-500">فاصله چرخه:</span> <strong>{{ $currentPolicy->cycle_interval_months }} ماه</strong></div>
                    <div><span class="text-slate-500">مهلت پاسخ:</span> <strong>{{ $currentPolicy->response_duration_days }} روز</strong></div>
                    <div><span class="text-slate-500">حداقل نمونه گزارش:</span> <strong>{{ $currentPolicy->report_min_distinct_voters ?? 10 }} نفر</strong></div>
                    <div><span class="text-slate-500">بازه تجمیع گزارش:</span> <strong>{{ $currentPolicy->report_bucket_days ?? 7 }} روز</strong></div>
                    <div><span class="text-slate-500">آستانه روند معنادار:</span> <strong>{{ $currentPolicy->meaningful_trend_min_net_change ?? 3 }}</strong></div>
                </div>
            @else
                <div class="text-amber-700 dark:text-amber-300">در حال حاضر نسخه مؤثر منتشرشده‌ای برای این سطح وجود ندارد.</div>
            @endif
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
            <div class="text-sm text-slate-500 dark:text-slate-400 mb-2">نسخه‌های آینده</div>
            <div class="text-3xl font-bold text-slate-900 dark:text-white">{{ $futurePolicies->count() }}</div>
            <p class="text-xs text-slate-500 mt-2">نسخه‌هایی که effective_at آن‌ها هنوز نرسیده است.</p>
        </div>
    </div>

    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4 mb-6 text-sm text-blue-900 dark:text-blue-100">
        نسخه‌های منتشرشده قابل ویرایش یا حذف نیستند. هر تغییر باید به‌صورت نسخه جدید منتشر شود؛ چرخه‌های آغازشده نیز policy و قراردادهای فریز‌شده خود را حفظ می‌کنند.
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-4 py-3 text-right">نسخه</th>
                        <th class="px-4 py-3 text-right">زمان اثر</th>
                        <th class="px-4 py-3 text-right">پایان اثر</th>
                        <th class="px-4 py-3 text-right">پارامترها</th>
                        <th class="px-4 py-3 text-right">حریم گزارش</th>
                        <th class="px-4 py-3 text-right">قرارداد مدیر</th>
                        <th class="px-4 py-3 text-right">قرارداد بازرس</th>
                        <th class="px-4 py-3 text-right">دلیل تغییر</th>
                        <th class="px-4 py-3 text-right">چرخه‌های استفاده‌کننده</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($policies as $policy)
                        <tr class="{{ $currentPolicy && $currentPolicy->id === $policy->id ? 'bg-green-50/60 dark:bg-green-900/10' : '' }}">
                            <td class="px-4 py-4 whitespace-nowrap font-bold">v{{ $policy->version }}</td>
                            <td class="px-4 py-4 whitespace-nowrap">{{ optional($policy->effective_at)->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="px-4 py-4 whitespace-nowrap">{{ optional($policy->retired_at)->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="px-4 py-4 min-w-[240px]">
                                <div>مدیر {{ $policy->manager_count }} / بازرس {{ $policy->inspector_count }}</div>
                                <div>حدنصاب {{ $policy->start_threshold }} / رأی‌گیری {{ $policy->voting_duration_days }} روز</div>
                                <div>چرخه {{ $policy->cycle_interval_months }} ماه / پاسخ {{ $policy->response_duration_days }} روز</div>
                            </td>
                            <td class="px-4 py-4 min-w-[220px]">
                                <div>نمونه ≥ {{ $policy->report_min_distinct_voters ?? 10 }} نفر</div>
                                <div>تجمیع ≥ {{ $policy->report_bucket_days ?? 7 }} روز</div>
                                <div>روند معنادار ≥ {{ $policy->meaningful_trend_min_net_change ?? 3 }} رأی خالص</div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                @if($policy->managerContractVersion)
                                    #{{ $policy->managerContractVersion->id }} — v{{ $policy->managerContractVersion->version }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                @if($policy->inspectorContractVersion)
                                    #{{ $policy->inspectorContractVersion->id }} — v{{ $policy->inspectorContractVersion->version }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-4 min-w-[220px]">{{ $policy->change_reason ?: '—' }}</td>
                            <td class="px-4 py-4 text-center">{{ $policy->elections()->count() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-8 text-center text-slate-500">هنوز نسخه‌ای برای این سطح ثبت نشده است.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
