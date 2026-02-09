@extends('layouts.admin')

@section('title', 'تنظیمات انتخابات')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <!-- هدر صفحه -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-vote-yea ml-2"></i>
                تنظیمات انتخابات
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">
                مدیریت تنظیمات انتخابات برای سطوح مختلف گروه‌ها
            </p>
        </div>
        <a href="{{ route('admin.system-settings.index') }}" 
           class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-700 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-400 transition-colors">
            <i class="fas fa-arrow-right ml-2"></i>
            بازگشت به تنظیمات سیستمی
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-6 flex items-center gap-3">
            <i class="fas fa-check-circle text-green-600 dark:text-green-400"></i>
            <span class="text-green-800 dark:text-green-200">{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-6">
            <div class="flex items-center gap-3 mb-2">
                <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400"></i>
                <span class="text-red-800 dark:text-red-200 font-semibold">خطا در اعتبارسنجی</span>
            </div>
            <ul class="list-disc list-inside text-red-700 dark:text-red-300 text-sm space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- فیلترها -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 mb-6">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-semibold text-slate-700 dark:text-slate-300 ml-2">فیلتر بر اساس نوع:</span>
            <a href="{{ route('admin.group.setting.index') }}" 
               class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-lg transition-colors {{ !$sort || $sort == 'total' ? 'bg-blue-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' }}">
                عمومی
            </a>
            <a href="{{ route('admin.group.setting.index', ['sort' => 'experience']) }}" 
               class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-lg transition-colors {{ $sort == 'experience' ? 'bg-blue-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' }}">
                علمی/تجربی
            </a>
            <a href="{{ route('admin.group.setting.index', ['sort' => 'job']) }}" 
               class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-lg transition-colors {{ $sort == 'job' ? 'bg-blue-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' }}">
                صنفی/شغلی
            </a>
            <a href="{{ route('admin.group.setting.index', ['sort' => 'age']) }}" 
               class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-lg transition-colors {{ $sort == 'age' ? 'bg-blue-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' }}">
                سنی
            </a>
            <a href="{{ route('admin.group.setting.index', ['sort' => 'gender']) }}" 
               class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-lg transition-colors {{ $sort == 'gender' ? 'bg-blue-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' }}">
                جنسیتی
            </a>
        </div>
    </div>

    <!-- جدول تنظیمات -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">#</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">سطح گروه</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">تعداد بازرسان</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">تعداد مدیران</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">حداقل برای شروع</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">زمان انتخابات</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">زمان ثانویه</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">وضعیت</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">عملیات</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($groupSettings as $key => $setting)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                            <form action="{{ route('admin.group.setting.update', $setting) }}" method="POST" class="contents">
                                @csrf
                                @method('PUT')

                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-white">
                                    {{ $key + 1 }}
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-slate-900 dark:text-white">
                                        {{ $setting->name() }}
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="number" 
                                           name="inspector_count" 
                                           value="{{ old('inspector_count', $setting->inspector_count) }}"
                                           min="0"
                                           required
                                           class="w-20 px-3 py-1.5 text-sm text-center border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="number" 
                                           name="manager_count" 
                                           value="{{ old('manager_count', $setting->manager_count) }}"
                                           min="0"
                                           required
                                           class="w-20 px-3 py-1.5 text-sm text-center border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="number" 
                                           name="max_for_election" 
                                           value="{{ old('max_for_election', $setting->max_for_election) }}"
                                           min="1"
                                           required
                                           class="w-20 px-3 py-1.5 text-sm text-center border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <input type="number" 
                                               name="election_time" 
                                               value="{{ old('election_time', $setting->election_time) }}"
                                               min="1"
                                               required
                                               class="w-20 px-3 py-1.5 text-sm text-center border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                                        <span class="text-xs text-slate-600 dark:text-slate-400">روز</span>
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <input type="number" 
                                               name="second_election_time" 
                                               value="{{ old('second_election_time', $setting->second_election_time) }}"
                                               min="1"
                                               required
                                               class="w-20 px-3 py-1.5 text-sm text-center border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                                        <span class="text-xs text-slate-600 dark:text-slate-400">روز</span>
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($setting->election_status == 1)
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200">
                                            <i class="fas fa-check-circle ml-1"></i>
                                            فعال
                                        </span>
                                    @else
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-200">
                                            <i class="fas fa-times-circle ml-1"></i>
                                            غیرفعال
                                        </span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <div class="flex items-center gap-2">
                                        <button type="submit" 
                                                class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                            <i class="fas fa-save ml-1"></i>
                                            به‌روزرسانی
                                        </button>
                                        <a href="{{ route('admin.group.setting.edit', $setting->id) }}" 
                                           class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-semibold {{ $setting->election_status == 1 ? 'text-orange-700 bg-orange-100 hover:bg-orange-200 dark:bg-orange-900/30 dark:text-orange-300 dark:hover:bg-orange-900/50' : 'text-green-700 bg-green-100 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-300 dark:hover:bg-green-900/50' }} rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors">
                                            <i class="fas {{ $setting->election_status == 1 ? 'fa-toggle-off' : 'fa-toggle-on' }} ml-1"></i>
                                            {{ $setting->election_status == 1 ? 'غیرفعال' : 'فعال' }}
                                        </a>
                                    </div>
                                </td>
                            </form>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                                <i class="fas fa-inbox text-3xl mb-2 block"></i>
                                تنظیماتی یافت نشد
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // نمایش پیام موفقیت با انیمیشن
    @if(session('success'))
        setTimeout(() => {
            const alert = document.querySelector('.bg-green-50');
            if (alert) {
                alert.style.transition = 'opacity 0.5s ease-out';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 5000);
    @endif
</script>
@endpush
@endsection

