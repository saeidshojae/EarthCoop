@extends('layouts.admin')

@section('title', 'سیاست‌ها و سطوح انتخابات')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between mb-6">
        <div>
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 flex items-center justify-center">
                    <i class="fas fa-sliders-h"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">سیاست‌ها و سطوح انتخابات</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">تنظیم حدنصاب، کرسی‌ها، مدت رأی‌گیری و فاصله چرخه برای هر نوع و سطح گروه</p>
                </div>
            </div>
        </div>
        <a href="{{ route('admin.elections.dashboard') }}"
           class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-700 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
            <i class="fas fa-arrow-right"></i>
            بازگشت به مدیریت انتخابات
        </a>
    </div>

    @if(session('success'))
        <div class="mb-5 rounded-xl border border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800 p-4 text-sm text-green-800 dark:text-green-200">
            <i class="fas fa-check-circle ml-2"></i>{{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 p-4 text-sm text-red-800 dark:text-red-200">
            <div class="font-bold mb-2">خطا در ذخیره تنظیمات</div>
            <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 mb-6 shadow-sm">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-semibold text-slate-700 dark:text-slate-300 ml-2">نوع گروه:</span>
            @php($tabs = [
                'public' => 'عمومی',
                'experience' => 'علمی/تجربی',
                'job' => 'صنفی/شغلی',
                'age' => 'سنی',
                'gender' => 'جنسیتی',
            ])
            @foreach($tabs as $key => $label)
                <a href="{{ $key === 'public' ? route('admin.group.setting.index') : route('admin.group.setting.index', ['sort' => $key]) }}"
                   class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-lg transition-colors {{ $sort === $key ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="mb-4 rounded-xl border border-blue-200 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800 px-4 py-3 text-xs leading-6 text-blue-800 dark:text-blue-200">
        <i class="fas fa-info-circle ml-1"></i>
        «مدت رأی‌گیری» بر حسب روز است. «فاصله چرخه» بر حسب ماه است. تغییرات ذخیره‌شده نسخه جدید policy می‌سازند و بر چرخه‌های قبلی اثر بازگشتی ندارند.
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-300">
                    <tr>
                        <th class="p-3 text-right">#</th>
                        <th class="p-3 text-right min-w-[170px]">سطح گروه</th>
                        <th class="p-3 text-center">بازرس</th>
                        <th class="p-3 text-center">مدیر</th>
                        <th class="p-3 text-center">حدنصاب شروع</th>
                        <th class="p-3 text-center">مدت رأی‌گیری</th>
                        <th class="p-3 text-center">فاصله چرخه</th>
                        <th class="p-3 text-center">وضعیت</th>
                        <th class="p-3 text-center min-w-[180px]">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($groupSettings as $key => $setting)
                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-700/30">
                        <form action="{{ route('admin.group.setting.update', $setting) }}" method="POST" class="contents">
                            @csrf
                            @method('PUT')
                            <td class="p-3 text-slate-500">{{ $key + 1 }}</td>
                            <td class="p-3 font-semibold text-slate-900 dark:text-white">{{ $setting->name() }}</td>
                            <td class="p-3 text-center"><input type="number" name="inspector_count" value="{{ old('inspector_count', $setting->inspector_count) }}" min="0" required class="w-20 px-2 py-1.5 text-center border rounded-lg dark:bg-slate-700 dark:border-slate-600"></td>
                            <td class="p-3 text-center"><input type="number" name="manager_count" value="{{ old('manager_count', $setting->manager_count) }}" min="0" required class="w-20 px-2 py-1.5 text-center border rounded-lg dark:bg-slate-700 dark:border-slate-600"></td>
                            <td class="p-3 text-center"><input type="number" name="max_for_election" value="{{ old('max_for_election', $setting->max_for_election) }}" min="1" required class="w-20 px-2 py-1.5 text-center border rounded-lg dark:bg-slate-700 dark:border-slate-600"></td>
                            <td class="p-3 text-center"><div class="inline-flex items-center gap-2"><input type="number" name="election_time" value="{{ old('election_time', $setting->election_time) }}" min="1" required class="w-20 px-2 py-1.5 text-center border rounded-lg dark:bg-slate-700 dark:border-slate-600"><span class="text-xs text-slate-500">روز</span></div></td>
                            <td class="p-3 text-center"><div class="inline-flex items-center gap-2"><input type="number" name="second_election_time" value="{{ old('second_election_time', $setting->second_election_time) }}" min="0" required class="w-20 px-2 py-1.5 text-center border rounded-lg dark:bg-slate-700 dark:border-slate-600"><span class="text-xs text-slate-500">ماه</span></div></td>
                            <td class="p-3 text-center">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold {{ $setting->election_status ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $setting->election_status ? 'فعال' : 'غیرفعال' }}
                                </span>
                            </td>
                            <td class="p-3">
                                <div class="flex items-center justify-center gap-2 flex-wrap">
                                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700"><i class="fas fa-save"></i> ذخیره</button>
                                    <a href="{{ route('admin.group.setting.edit', $setting) }}" class="inline-flex items-center px-2.5 py-1.5 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold hover:bg-slate-200">{{ $setting->election_status ? 'غیرفعال' : 'فعال' }}</a>
                                    <a href="{{ route('admin.group.setting.index', ['history' => $setting->id]) }}" class="text-xs font-semibold text-blue-600 hover:underline">تاریخچه</a>
                                    <a href="{{ route('admin.group.setting.index', ['reporting' => $setting->id]) }}" class="text-xs font-semibold text-violet-600 hover:underline">گزارش</a>
                                </div>
                            </td>
                        </form>
                    </tr>
                @empty
                    <tr><td colspan="9" class="p-10 text-center text-slate-400">تنظیمی برای این نوع گروه یافت نشد.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
