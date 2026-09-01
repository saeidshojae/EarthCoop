@extends('layouts.admin')

@section('title', 'سیاست حریم گزارش انتخابات')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <div class="flex items-center justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">سیاست حریم گزارش انتخابات</h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">
                {{ $setting->name() }} — تنظیم حداقل نمونه و بازه تجمیع گزارش‌های محبوبیت و رضایت
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.group.setting.index', ['history' => $setting->id]) }}"
               class="px-4 py-2 text-sm font-semibold rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200">
                تاریخچه سیاست
            </a>
            <a href="{{ route('admin.group.setting.index') }}"
               class="px-4 py-2 text-sm font-semibold rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200">
                بازگشت
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 text-green-800">{{ session('success') }}</div>
    @endif

    @if(isset($errors) && $errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 dark:bg-blue-900/20 p-4 text-sm text-blue-900 dark:text-blue-100">
        پیش‌فرض E0: حداقل ۱۰ رأی‌دهنده متمایز و بازه تجمیع ۷ روز. زیر این آستانه، گزارش فقط آمار کلی غیرقابل‌انتساب را نمایش می‌دهد و breakdownهای روند، حفظ رأی و موضوعات بازخورد سرکوب می‌شوند.
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5">
            <div class="text-sm text-slate-500">نسخه مؤثر فعلی</div>
            <div class="mt-2 text-2xl font-bold">{{ $currentPolicy ? 'v'.$currentPolicy->version : '—' }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5">
            <div class="text-sm text-slate-500">حداقل رأی‌دهنده متمایز</div>
            <div class="mt-2 text-2xl font-bold">{{ $currentPolicy?->report_min_distinct_voters ?? $setting->election_report_min_distinct_voters ?? 10 }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5">
            <div class="text-sm text-slate-500">حداقل بازه تجمیع</div>
            <div class="mt-2 text-2xl font-bold">{{ $currentPolicy?->report_bucket_days ?? $setting->election_report_bucket_days ?? 7 }} روز</div>
        </div>
    </div>

    <form action="{{ route('admin.group.setting.update', $setting) }}" method="POST"
          class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-6 space-y-6">
        @csrf
        @method('PUT')

        {{-- Preserve the rest of the election policy while publishing a new reporting-policy version. --}}
        <input type="hidden" name="manager_count" value="{{ $currentPolicy?->manager_count ?? $setting->manager_count }}">
        <input type="hidden" name="inspector_count" value="{{ $currentPolicy?->inspector_count ?? $setting->inspector_count }}">
        <input type="hidden" name="election_time" value="{{ $currentPolicy?->voting_duration_days ?? $setting->election_time }}">
        <input type="hidden" name="max_for_election" value="{{ $currentPolicy?->start_threshold ?? $setting->max_for_election }}">
        <input type="hidden" name="second_election_time" value="{{ $currentPolicy?->cycle_interval_months ?? $setting->second_election_time }}">
        <input type="hidden" name="response_duration_days" value="{{ $currentPolicy?->response_duration_days ?? 7 }}">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <label class="block">
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">حداقل رأی‌دهنده متمایز</span>
                <input type="number" min="2" name="election_report_min_distinct_voters"
                       value="{{ old('election_report_min_distinct_voters', $currentPolicy?->report_min_distinct_voters ?? $setting->election_report_min_distinct_voters ?? 10) }}"
                       class="mt-2 w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700" required>
                <small class="text-slate-500">زیر این تعداد، breakdownهای قابل استنتاج نمایش داده نمی‌شوند.</small>
            </label>

            <label class="block">
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">بازه تجمیع گزارش (روز)</span>
                <input type="number" min="1" name="election_report_bucket_days"
                       value="{{ old('election_report_bucket_days', $currentPolicy?->report_bucket_days ?? $setting->election_report_bucket_days ?? 7) }}"
                       class="mt-2 w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700" required>
                <small class="text-slate-500">گزارش‌های ریزتر از این بازه suppress می‌شوند.</small>
            </label>

            <label class="block">
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">حداقل تغییر خالص معنادار</span>
                <input type="number" min="1" name="election_meaningful_trend_min_net_change"
                       value="{{ old('election_meaningful_trend_min_net_change', $currentPolicy?->meaningful_trend_min_net_change ?? $setting->election_meaningful_trend_min_net_change ?? 3) }}"
                       class="mt-2 w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700" required>
                <small class="text-slate-500">اعلان فقط پس از عبور از این threshold و شروط حریم خصوصی ساخته می‌شود.</small>
            </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <label class="block">
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">زمان اثر نسخه جدید</span>
                <input type="datetime-local" name="effective_at" value="{{ old('effective_at') }}"
                       class="mt-2 w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700">
                <small class="text-slate-500">خالی = اثر فوری. زمان آینده، policy را بدون تغییر زودهنگام mirror زمان‌بندی می‌کند.</small>
            </label>

            <label class="block">
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">دلیل تغییر</span>
                <input type="text" maxlength="500" name="change_reason" value="{{ old('change_reason') }}"
                       placeholder="مثلاً افزایش حفاظت در گروه‌های کم‌جمعیت"
                       class="mt-2 w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700">
            </label>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold">
                انتشار نسخه جدید سیاست گزارش
            </button>
        </div>
    </form>
</div>
@endsection
