@extends('layouts.admin')

@section('title', 'تنظیمات سیستمی')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <!-- هدر صفحه -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-cog ml-2"></i>
                تنظیمات سیستمی
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">
                مدیریت و تنظیم بخش‌های مختلف سیستم
            </p>
        </div>
    </div>

    <!-- کارت‌های آماری -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm mb-1">کدهای دعوت</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['invitation_codes']) }}</p>
                </div>
                <div class="bg-blue-400/20 rounded-full p-4">
                    <i class="fas fa-ticket-alt text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-100 text-sm mb-1">درخواست‌های در انتظار</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['invitation_requests']) }}</p>
                </div>
                <div class="bg-orange-400/20 rounded-full p-4">
                    <i class="fas fa-envelope-open-text text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm mb-1">دسته‌بندی‌ها</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['categories']) }}</p>
                </div>
                <div class="bg-purple-400/20 rounded-full p-4">
                    <i class="fas fa-folder text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm mb-1">تنظیمات گروه‌ها</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['group_settings']) }}</p>
                </div>
                <div class="bg-green-400/20 rounded-full p-4">
                    <i class="fas fa-vote-yea text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- کارت‌های دسترسی سریع -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- مدیریت کدهای دعوت -->
        <a href="{{ route('admin.invitation_codes.index') }}" 
           class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 hover:shadow-lg transition-shadow group">
            <div class="flex items-center justify-between mb-4">
                <div class="bg-blue-100 dark:bg-blue-900/30 rounded-lg p-4 group-hover:bg-blue-200 dark:group-hover:bg-blue-900/50 transition-colors">
                    <i class="fas fa-ticket-alt text-3xl text-blue-600 dark:text-blue-400"></i>
                </div>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">مدیریت کدهای دعوت</h3>
            <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">ایجاد، مشاهده و مدیریت کدهای دعوت کاربران</p>
            <div class="flex items-center text-blue-600 dark:text-blue-400 text-sm font-semibold">
                <span>مشاهده و مدیریت</span>
                <i class="fas fa-arrow-left mr-2 group-hover:mr-0 group-hover:ml-2 transition-all"></i>
            </div>
        </a>

        <!-- بررسی درخواست‌های کد دعوت -->
        <a href="{{ route('admin.invitation_codes.index', ['invation' => 1]) }}" 
           class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 hover:shadow-lg transition-shadow group">
            <div class="flex items-center justify-between mb-4">
                <div class="bg-orange-100 dark:bg-orange-900/30 rounded-lg p-4 group-hover:bg-orange-200 dark:group-hover:bg-orange-900/50 transition-colors">
                    <i class="fas fa-envelope-open-text text-3xl text-orange-600 dark:text-orange-400"></i>
                </div>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">بررسی درخواست‌های کد دعوت</h3>
            <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">مشاهده و مدیریت درخواست‌های کد دعوت کاربران</p>
            <div class="flex items-center text-orange-600 dark:text-orange-400 text-sm font-semibold">
                <span>مشاهده و مدیریت</span>
                <i class="fas fa-arrow-left mr-2 group-hover:mr-0 group-hover:ml-2 transition-all"></i>
            </div>
        </a>

        <!-- مدیریت دسته‌بندی‌ها -->
        <a href="{{ route('admin.categories.index') }}" 
           class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 hover:shadow-lg transition-shadow group">
            <div class="flex items-center justify-between mb-4">
                <div class="bg-purple-100 dark:bg-purple-900/30 rounded-lg p-4 group-hover:bg-purple-200 dark:group-hover:bg-purple-900/50 transition-colors">
                    <i class="fas fa-folder text-3xl text-purple-600 dark:text-purple-400"></i>
                </div>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">مدیریت دسته‌بندی‌ها</h3>
            <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">ایجاد، مشاهده و مدیریت دسته‌بندی‌های گروه‌ها</p>
            <div class="flex items-center text-purple-600 dark:text-purple-400 text-sm font-semibold">
                <span>مشاهده و مدیریت</span>
                <i class="fas fa-arrow-left mr-2 group-hover:mr-0 group-hover:ml-2 transition-all"></i>
            </div>
        </a>

        <!-- مدیریت فعال‌سازی‌ها -->
        <a href="{{ route('admin.activate.index') }}" 
           class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 hover:shadow-lg transition-shadow group">
            <div class="flex items-center justify-between mb-4">
                <div class="bg-green-100 dark:bg-green-900/30 rounded-lg p-4 group-hover:bg-green-200 dark:group-hover:bg-green-900/50 transition-colors">
                    <i class="fas fa-key text-3xl text-green-600 dark:text-green-400"></i>
                </div>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">مدیریت فعال‌سازی‌ها</h3>
            <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">تنظیم دسترسی‌ها و کدهای فعال‌سازی سیستم</p>
            <div class="flex items-center text-green-600 dark:text-green-400 text-sm font-semibold">
                <span>مشاهده و مدیریت</span>
                <i class="fas fa-arrow-left mr-2 group-hover:mr-0 group-hover:ml-2 transition-all"></i>
            </div>
        </a>

        <!-- تنظیمات انتخابات -->
        <a href="{{ route('admin.group.setting.index') }}" 
           class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 hover:shadow-lg transition-shadow group">
            <div class="flex items-center justify-between mb-4">
                <div class="bg-teal-100 dark:bg-teal-900/30 rounded-lg p-4 group-hover:bg-teal-200 dark:group-hover:bg-teal-900/50 transition-colors">
                    <i class="fas fa-vote-yea text-3xl text-teal-600 dark:text-teal-400"></i>
                </div>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">تنظیمات انتخابات</h3>
            <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">مدیریت سطح، بازرسان و مدیران گروه‌ها</p>
            <div class="flex items-center text-teal-600 dark:text-teal-400 text-sm font-semibold">
                <span>مشاهده و مدیریت</span>
                <i class="fas fa-arrow-left mr-2 group-hover:mr-0 group-hover:ml-2 transition-all"></i>
            </div>
        </a>
    </div>
</div>
@endsection

