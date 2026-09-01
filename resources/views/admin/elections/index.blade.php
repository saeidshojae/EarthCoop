@extends('layouts.admin')

@section('title', 'مدیریت انتخابات')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between mb-7">
        <div>
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-300 flex items-center justify-center">
                    <i class="fas fa-vote-yea text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">مدیریت انتخابات</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">مرکز کنترل سیاست‌ها، چرخه‌ها، قراردادها، بازبینی و سلامت انتخابات سیستمی</p>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('elections.guideline') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-emerald-300 dark:border-emerald-700 text-emerald-700 dark:text-emerald-300 bg-white dark:bg-slate-800 text-sm font-semibold hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors">
                <i class="fas fa-book-open"></i>
                شیوه‌نامه انتخابات
            </a>
            <a href="{{ route('admin.group.setting.index') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 transition-colors">
                <i class="fas fa-sliders-h"></i>
                سیاست‌ها و سطوح انتخابات
            </a>
        </div>
    </div>

    @if(count($attention))
        <div class="mb-6 rounded-2xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-5">
            <div class="flex items-center gap-2 font-bold text-amber-800 dark:text-amber-200 mb-3">
                <i class="fas fa-exclamation-triangle"></i>
                نیازمند توجه
            </div>
            <ul class="space-y-2 text-sm text-amber-800 dark:text-amber-200">
                @foreach($attention as $item)
                    <li class="flex items-start gap-2"><i class="fas fa-circle text-[6px] mt-2"></i><span>{{ $item }}</span></li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-7">
        @php($cards = [
            ['label'=>'چرخه‌های باز','value'=>$stats['open_cycles'],'icon'=>'fa-play-circle'],
            ['label'=>'در حال تعیین نتیجه','value'=>$stats['settling_cycles'],'icon'=>'fa-hourglass-half'],
            ['label'=>'سیاست‌های مؤثر','value'=>$stats['active_policies'],'icon'=>'fa-layer-group'],
            ['label'=>'پرونده‌های بازبینی باز','value'=>$stats['open_reviews'],'icon'=>'fa-balance-scale'],
        ])
        @foreach($cards as $card)
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($card['value']) }}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $card['label'] }}</div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300"><i class="fas {{ $card['icon'] }}"></i></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 mb-8">
        <a href="{{ route('admin.group.setting.index') }}" class="group bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-5 hover:border-indigo-300 hover:shadow-md transition-all">
            <div class="flex items-start gap-4">
                <div class="w-11 h-11 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 flex items-center justify-center"><i class="fas fa-sliders-h"></i></div>
                <div class="flex-1"><h3 class="font-bold text-slate-900 dark:text-white">سیاست‌ها و سطوح</h3><p class="text-sm text-slate-500 mt-1 leading-6">تعداد مدیر و بازرس، حدنصاب، مدت رأی‌گیری، فاصله چرخه، مهلت پاسخ و نسخه مؤثر هر سطح.</p><div class="mt-3 text-xs font-semibold text-indigo-600">{{ $stats['group_settings'] }} تنظیم پایه · {{ $stats['future_policies'] }} نسخه آینده</div></div>
            </div>
        </a>

        <a href="{{ route('admin.elections.contracts.index') }}" class="group bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-5 hover:border-indigo-300 hover:shadow-md transition-all">
            <div class="flex items-start gap-4"><div class="w-11 h-11 rounded-xl bg-cyan-50 dark:bg-cyan-900/30 text-cyan-600 flex items-center justify-center"><i class="fas fa-file-contract"></i></div><div class="flex-1"><h3 class="font-bold text-slate-900 dark:text-white">قراردادهای مسئولیت</h3><p class="text-sm text-slate-500 mt-1 leading-6">متن نسخه‌دار و غیرقابل‌تغییر تعهدات مدیر و بازرس و شواهد پذیرش مسئولیت.</p><div class="mt-3 text-xs font-semibold text-cyan-600">{{ $stats['active_contracts'] }} قرارداد فعال</div></div></div>
        </a>

        <a href="{{ route('admin.elections.conflict-policy.index') }}" class="group bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-5 hover:border-indigo-300 hover:shadow-md transition-all">
            <div class="flex items-start gap-4"><div class="w-11 h-11 rounded-xl bg-rose-50 dark:bg-rose-900/30 text-rose-600 flex items-center justify-center"><i class="fas fa-random"></i></div><div class="flex-1"><h3 class="font-bold text-slate-900 dark:text-white">تعارض مسئولیت</h3><p class="text-sm text-slate-500 mt-1 leading-6">ماتریس نسخه‌دار سازگاری سمت‌ها در انواع و سطوح مختلف گروه و قواعد تعلیق.</p></div></div>
        </a>

        <a href="{{ route('admin.group.setting.index') }}" class="group bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-5 hover:border-indigo-300 hover:shadow-md transition-all">
            <div class="flex items-start gap-4"><div class="w-11 h-11 rounded-xl bg-violet-50 dark:bg-violet-900/30 text-violet-600 flex items-center justify-center"><i class="fas fa-chart-bar"></i></div><div class="flex-1"><h3 class="font-bold text-slate-900 dark:text-white">حریم خصوصی و گزارش‌ها</h3><p class="text-sm text-slate-500 mt-1 leading-6">آستانه گزارش، bucket زمانی، trend معنادار و تاریخچه نسخه‌های سیاست برای هر سطح.</p><div class="mt-3 text-xs text-slate-400">از ردیف هر سطح، «گزارش» یا «تاریخچه» را باز کنید.</div></div></div>
        </a>

        @if(auth()->user()?->hasPermission('elections.review.manage'))
        <a href="{{ route('admin.elections.reviews') }}" class="group bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-5 hover:border-amber-300 hover:shadow-md transition-all">
            <div class="flex items-start gap-4"><div class="w-11 h-11 rounded-xl bg-amber-50 dark:bg-amber-900/30 text-amber-600 flex items-center justify-center"><i class="fas fa-balance-scale"></i></div><div class="flex-1"><h3 class="font-bold text-slate-900 dark:text-white">بازبینی و بازشماری</h3><p class="text-sm text-slate-500 mt-1 leading-6">پرونده‌های review، stay موقت، تصمیم نهایی مستدل و audit رسیدگی.</p><div class="mt-3 text-xs font-semibold {{ $stats['open_reviews'] ? 'text-amber-600' : 'text-emerald-600' }}">{{ $stats['open_reviews'] ? $stats['open_reviews'].' مورد باز' : 'مورد باز وجود ندارد' }}</div></div></div>
        </a>
        @else
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-5 opacity-70">
            <div class="flex items-start gap-4"><div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center"><i class="fas fa-lock"></i></div><div class="flex-1"><h3 class="font-bold text-slate-900 dark:text-white">بازبینی و بازشماری</h3><p class="text-sm text-slate-500 mt-1 leading-6">نیازمند دسترسی اختصاصی مدیریت بازبینی انتخابات است.</p></div></div>
        </div>
        @endif

        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-5">
            <div class="flex items-start gap-4"><div class="w-11 h-11 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 flex items-center justify-center"><i class="fas fa-shield-alt"></i></div><div class="flex-1"><h3 class="font-bold text-slate-900 dark:text-white">سلامت و آمادگی</h3><p class="text-sm text-slate-500 mt-1 leading-6">چرخه‌ها فقط با policy و قرارداد معتبر آغاز می‌شوند و تغییر سیاست بر چرخه‌های قدیمی اثر بازگشتی ندارد.</p><div class="mt-3 text-xs font-semibold text-emerald-600">کنترل‌های fail-closed فعال</div></div></div>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden shadow-sm">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <div><h2 class="font-bold text-slate-900 dark:text-white">آخرین چرخه‌ها</h2><p class="text-xs text-slate-500 mt-1">نمای سریع از وضعیت جاری سیستم انتخابات</p></div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900 text-slate-500"><tr><th class="p-3 text-right">#</th><th class="p-3 text-right">گروه</th><th class="p-3 text-right">چرخه</th><th class="p-3 text-right">وضعیت</th><th class="p-3 text-right">شروع</th><th class="p-3 text-right">پایان رأی‌گیری</th><th class="p-3 text-right">عملیات</th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($recentCycles as $cycle)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-700/30">
                            <td class="p-3 text-slate-500">{{ $cycle->id }}</td>
                            <td class="p-3 font-semibold text-slate-800 dark:text-slate-200">{{ optional($cycle->group)->name ?: '—' }}</td>
                            <td class="p-3">{{ $cycle->cycle_number ?: 1 }}</td>
                            <td class="p-3"><span class="inline-flex px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-700 text-xs font-semibold">{{ $cycle->lifecycle_status?->value ?? $cycle->lifecycle_status ?? '—' }}</span></td>
                            <td class="p-3 text-slate-500">{{ optional($cycle->starts_at)->format('Y-m-d H:i') ?: '—' }}</td>
                            <td class="p-3 text-slate-500">{{ optional($cycle->ends_at)->format('Y-m-d H:i') ?: '—' }}</td>
                            <td class="p-3"><a href="{{ route('admin.elections.policy-override.edit', $cycle) }}" class="text-xs font-semibold text-indigo-600 hover:underline">override چرخه</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-10 text-center text-slate-400">هنوز چرخه انتخاباتی ایجاد نشده است.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
