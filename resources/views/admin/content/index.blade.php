@extends('layouts.admin')

@section('title', 'مدیریت محتوا')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <!-- هدر صفحه -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-file-alt ml-2"></i>
                مدیریت محتوا
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">مدیریت تمام محتوای سایت</p>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-check-circle ml-2"></i>
            {{ session('success') }}
        </div>
    @endif

    <!-- کارت‌های آماری -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm mb-1">اطلاعیه‌ها</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['announcements'] ?? 0) }}</p>
                </div>
                <div class="bg-blue-400/20 rounded-full p-4">
                    <i class="fas fa-bullhorn text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm mb-1">صفحات استاتیک</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['pages'] ?? 0) }}</p>
                </div>
                <div class="bg-green-400/20 rounded-full p-4">
                    <i class="fas fa-file-alt text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm mb-1">اساسنامه</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['rules'] ?? 0) }}</p>
                </div>
                <div class="bg-purple-400/20 rounded-full p-4">
                    <i class="fas fa-gavel text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-100 text-sm mb-1">اسلایدرها</p>
                    <p class="text-3xl font-bold">{{ number_format(($stats['welcome_sliders'] ?? 0) + ($stats['home_sliders'] ?? 0)) }}</p>
                </div>
                <div class="bg-orange-400/20 rounded-full p-4">
                    <i class="fas fa-images text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- بخش‌های مدیریت محتوا -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
        <!-- مدیریت اطلاعیه‌ها -->
        <a href="{{ route('admin.announcement.index') }}" 
           class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 hover:shadow-lg transition-shadow group">
            <div class="flex items-center justify-between mb-4">
                <div class="bg-blue-100 dark:bg-blue-900/30 rounded-lg p-4 group-hover:bg-blue-200 dark:group-hover:bg-blue-900/50 transition-colors">
                    <i class="fas fa-bullhorn text-3xl text-blue-600 dark:text-blue-400"></i>
                </div>
                <span class="text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($stats['announcements'] ?? 0) }}</span>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">مدیریت اطلاعیه‌ها</h3>
            <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">ایجاد و مدیریت اطلاعیه‌های عمومی سایت</p>
            <div class="flex items-center text-blue-600 dark:text-blue-400 text-sm font-semibold">
                <span>مشاهده و مدیریت</span>
                <i class="fas fa-arrow-left mr-2 group-hover:mr-0 group-hover:ml-2 transition-all"></i>
            </div>
        </a>

        <!-- صفحات استاتیک -->
        <a href="{{ route('admin.pages.index') }}" 
           class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 hover:shadow-lg transition-shadow group">
            <div class="flex items-center justify-between mb-4">
                <div class="bg-green-100 dark:bg-green-900/30 rounded-lg p-4 group-hover:bg-green-200 dark:group-hover:bg-green-900/50 transition-colors">
                    <i class="fas fa-file-alt text-3xl text-green-600 dark:text-green-400"></i>
                </div>
                <span class="text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($stats['pages'] ?? 0) }}</span>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">صفحات استاتیک</h3>
            <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">مدیریت صفحات استاتیک سایت</p>
            <div class="flex items-center text-green-600 dark:text-green-400 text-sm font-semibold">
                <span>مشاهده و مدیریت</span>
                <i class="fas fa-arrow-left mr-2 group-hover:mr-0 group-hover:ml-2 transition-all"></i>
            </div>
        </a>

        <!-- اساسنامه -->
        <a href="{{ route('admin.rule.index') }}" 
           class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 hover:shadow-lg transition-shadow group">
            <div class="flex items-center justify-between mb-4">
                <div class="bg-purple-100 dark:bg-purple-900/30 rounded-lg p-4 group-hover:bg-purple-200 dark:group-hover:bg-purple-900/50 transition-colors">
                    <i class="fas fa-gavel text-3xl text-purple-600 dark:text-purple-400"></i>
                </div>
                <span class="text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($stats['rules'] ?? 0) }}</span>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">اساسنامه</h3>
            <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">مدیریت اساسنامه و قوانین</p>
            <div class="flex items-center text-purple-600 dark:text-purple-400 text-sm font-semibold">
                <span>مشاهده و مدیریت</span>
                <i class="fas fa-arrow-left mr-2 group-hover:mr-0 group-hover:ml-2 transition-all"></i>
            </div>
        </a>

        <!-- صفحه خوش‌آمد -->
        <a href="{{ route('admin.welcome-page') }}" 
           class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 hover:shadow-lg transition-shadow group">
            <div class="flex items-center justify-between mb-4">
                <div class="bg-cyan-100 dark:bg-cyan-900/30 rounded-lg p-4 group-hover:bg-cyan-200 dark:group-hover:bg-cyan-900/50 transition-colors">
                    <i class="fas fa-hand-sparkles text-3xl text-cyan-600 dark:text-cyan-400"></i>
                </div>
                <div class="text-right">
                    <span class="text-2xl font-bold text-slate-900 dark:text-white block">{{ number_format($stats['welcome_sliders'] ?? 0) }}</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">اسلایدر</span>
                </div>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">صفحه خوش‌آمد</h3>
            <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">مدیریت صفحه خوش‌آمد و اسلایدرهای آن</p>
            <div class="flex items-center text-cyan-600 dark:text-cyan-400 text-sm font-semibold">
                <span>مشاهده و مدیریت</span>
                <i class="fas fa-arrow-left mr-2 group-hover:mr-0 group-hover:ml-2 transition-all"></i>
            </div>
        </a>

        <!-- صفحه هوم -->
        <a href="{{ route('admin.welcome-page') }}?home=1" 
           class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 hover:shadow-lg transition-shadow group">
            <div class="flex items-center justify-between mb-4">
                <div class="bg-indigo-100 dark:bg-indigo-900/30 rounded-lg p-4 group-hover:bg-indigo-200 dark:group-hover:bg-indigo-900/50 transition-colors">
                    <i class="fas fa-home text-3xl text-indigo-600 dark:text-indigo-400"></i>
                </div>
                <div class="text-right">
                    <span class="text-2xl font-bold text-slate-900 dark:text-white block">{{ number_format($stats['home_sliders'] ?? 0) }}</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">اسلایدر</span>
                </div>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">صفحه هوم</h3>
            <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">مدیریت صفحه اصلی و اسلایدرهای آن</p>
            <div class="flex items-center text-indigo-600 dark:text-indigo-400 text-sm font-semibold">
                <span>مشاهده و مدیریت</span>
                <i class="fas fa-arrow-left mr-2 group-hover:mr-0 group-hover:ml-2 transition-all"></i>
            </div>
        </a>

        <!-- نجم بهار -->
        <a href="{{ route('admin.najm-bahar.index') }}" 
           class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 hover:shadow-lg transition-shadow group">
            <div class="flex items-center justify-between mb-4">
                <div class="bg-pink-100 dark:bg-pink-900/30 rounded-lg p-4 group-hover:bg-pink-200 dark:group-hover:bg-pink-900/50 transition-colors">
                    <i class="fas fa-file-contract text-3xl text-pink-600 dark:text-pink-400"></i>
                </div>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">توافقنامه‌های نجم بهار</h3>
            <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">مدیریت توافقنامه‌های نجم بهار</p>
            <div class="flex items-center text-pink-600 dark:text-pink-400 text-sm font-semibold">
                <span>مشاهده و مدیریت</span>
                <i class="fas fa-arrow-left mr-2 group-hover:mr-0 group-hover:ml-2 transition-all"></i>
            </div>
        </a>
    </div>

    <!-- آخرین فعالیت‌ها -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- آخرین اطلاعیه‌ها -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                    <i class="fas fa-bullhorn ml-2"></i>
                    آخرین اطلاعیه‌ها
                </h3>
                <a href="{{ route('admin.announcement.index') }}" 
                   class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                    مشاهده همه
                </a>
            </div>
            <div class="space-y-3">
                @forelse($recentAnnouncements as $announcement)
                <div class="flex items-start justify-between p-3 bg-slate-50 dark:bg-slate-900 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-slate-900 dark:text-white mb-1">
                            {{ Str::limit($announcement->title, 40) }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            @php
                                try {
                                    $date = isset($announcement->created_at) ? \Carbon\Carbon::parse($announcement->created_at) : null;
                                    echo $date ? \Morilog\Jalali\Jalalian::fromCarbon($date)->format('Y/m/d') : '-';
                                } catch (\Exception $e) {
                                    echo '-';
                                }
                            @endphp
                        </p>
                    </div>
                </div>
                @empty
                <p class="text-sm text-slate-500 dark:text-slate-400 text-center py-4">اطلاعیه‌ای یافت نشد</p>
                @endforelse
            </div>
        </div>

        <!-- آخرین صفحات -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                    <i class="fas fa-file-alt ml-2"></i>
                    آخرین صفحات
                </h3>
                <a href="{{ route('admin.pages.index') }}" 
                   class="text-sm text-green-600 dark:text-green-400 hover:underline">
                    مشاهده همه
                </a>
            </div>
            <div class="space-y-3">
                @forelse($recentPages as $page)
                <div class="flex items-start justify-between p-3 bg-slate-50 dark:bg-slate-900 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-slate-900 dark:text-white mb-1">
                            {{ Str::limit($page->title, 40) }}
                        </p>
                        <div class="flex items-center gap-2 mt-1">
                            @if($page->is_published)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    منتشر شده
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                                    پیش‌نویس
                                </span>
                            @endif
                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                @php
                                    try {
                                        $date = isset($page->created_at) ? \Carbon\Carbon::parse($page->created_at) : null;
                                        echo $date ? \Morilog\Jalali\Jalalian::fromCarbon($date)->format('Y/m/d') : '-';
                                    } catch (\Exception $e) {
                                        echo '-';
                                    }
                                @endphp
                            </span>
                        </div>
                    </div>
                </div>
                @empty
                <p class="text-sm text-slate-500 dark:text-slate-400 text-center py-4">صفحه‌ای یافت نشد</p>
                @endforelse
            </div>
        </div>

        <!-- آخرین اساسنامه‌ها -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                    <i class="fas fa-gavel ml-2"></i>
                    آخرین اساسنامه‌ها
                </h3>
                <a href="{{ route('admin.rule.index') }}" 
                   class="text-sm text-purple-600 dark:text-purple-400 hover:underline">
                    مشاهده همه
                </a>
            </div>
            <div class="space-y-3">
                @forelse($recentRules as $rule)
                <div class="flex items-start justify-between p-3 bg-slate-50 dark:bg-slate-900 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-slate-900 dark:text-white mb-1">
                            {{ Str::limit($rule->title ?? 'بدون عنوان', 40) }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            @php
                                try {
                                    $date = isset($rule->created_at) ? \Carbon\Carbon::parse($rule->created_at) : null;
                                    echo $date ? \Morilog\Jalali\Jalalian::fromCarbon($date)->format('Y/m/d') : '-';
                                } catch (\Exception $e) {
                                    echo '-';
                                }
                            @endphp
                        </p>
                    </div>
                </div>
                @empty
                <p class="text-sm text-slate-500 dark:text-slate-400 text-center py-4">اساسنامه‌ای یافت نشد</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

