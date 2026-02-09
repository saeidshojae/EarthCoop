@extends('layouts.admin')

@section('title', 'مدیریت فعال‌سازی‌ها')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <!-- هدر صفحه -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-key ml-2"></i>
                مدیریت فعال‌سازی‌ها
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">
                تنظیم و مدیریت سیستم‌های فعال‌سازی و احراز هویت
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

    <form action="{{ route('admin.activate.update') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- تنظیمات کد دعوت -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="bg-blue-100 dark:bg-blue-900/30 rounded-lg p-3">
                        <i class="fas fa-ticket-alt text-2xl text-blue-600 dark:text-blue-400"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">سیستم کد دعوت</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-400">تنظیمات مربوط به کدهای دعوت کاربران</p>
                    </div>
                </div>

                <div class="space-y-6">
                    <!-- فعال/غیرفعال کردن کد دعوت -->
                    <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-700/50 rounded-lg">
                        <div class="flex-1">
                            <label for="invation_status" class="block text-sm font-semibold text-slate-900 dark:text-white mb-1">
                                فعال‌سازی سیستم کد دعوت
                            </label>
                            <p class="text-xs text-slate-600 dark:text-slate-400">
                                در صورت فعال بودن، کاربران جدید باید کد دعوت معتبر داشته باشند
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer group">
                            <input type="checkbox" 
                                   name="invation_status" 
                                   id="invation_status"
                                   value="1"
                                   class="sr-only peer"
                                   {{ old('invation_status', $setting->invation_status ?? 0) == 1 ? 'checked' : '' }}>
                            <div class="w-14 h-7 bg-slate-300 dark:bg-slate-600 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer peer-checked:bg-blue-600 dark:peer-checked:bg-blue-500 transition-colors duration-200 ease-in-out shadow-inner">
                                <div class="absolute top-0.5 right-0.5 bg-white dark:bg-slate-200 border-2 border-slate-300 dark:border-slate-500 rounded-full h-6 w-6 transition-all duration-200 ease-in-out peer-checked:right-auto peer-checked:left-0.5 peer-checked:border-blue-600 dark:peer-checked:border-blue-400 shadow-md"></div>
                            </div>
                        </label>
                    </div>

                    <!-- زمان انقضای کد دعوت -->
                    <div>
                        <label for="expire_invation_time" class="block text-sm font-semibold text-slate-900 dark:text-white mb-2">
                            زمان انقضای کد دعوت (ساعت)
                            <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="number" 
                                   name="expire_invation_time" 
                                   id="expire_invation_time"
                                   value="{{ old('expire_invation_time', $setting->expire_invation_time ?? 72) }}"
                                   min="1"
                                   max="8760"
                                   required
                                   class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">
                            مدت زمان اعتبار کد دعوت پس از ایجاد (پیش‌فرض: 72 ساعت)
                        </p>
                    </div>

                    <!-- تعداد کد دعوت -->
                    <div>
                        <label for="count_invation" class="block text-sm font-semibold text-slate-900 dark:text-white mb-2">
                            تعداد کد دعوت مجاز
                        </label>
                        <div class="relative">
                            <input type="number" 
                                   name="count_invation" 
                                   id="count_invation"
                                   value="{{ old('count_invation', $setting->count_invation ?? 0) }}"
                                   min="0"
                                   class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                                <i class="fas fa-hashtag"></i>
                            </div>
                        </div>
                        <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">
                            تعداد کد دعوتی که هر کاربر می‌تواند ایجاد کند (0 = نامحدود)
                        </p>
                    </div>
                </div>
            </div>

            <!-- تنظیمات کد اثر انگشت -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="bg-purple-100 dark:bg-purple-900/30 rounded-lg p-3">
                        <i class="fas fa-fingerprint text-2xl text-purple-600 dark:text-purple-400"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">سیستم کد اثر انگشت</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-400">تنظیمات مربوط به احراز هویت با اثر انگشت</p>
                    </div>
                </div>

                <div class="space-y-6">
                    <!-- فعال/غیرفعال کردن کد اثر انگشت -->
                    <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-700/50 rounded-lg">
                        <div class="flex-1">
                            <label for="finger_status" class="block text-sm font-semibold text-slate-900 dark:text-white mb-1">
                                فعال‌سازی سیستم کد اثر انگشت
                            </label>
                            <p class="text-xs text-slate-600 dark:text-slate-400">
                                در صورت فعال بودن، کاربران باید کد اثر انگشت معتبر داشته باشند
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer group">
                            <input type="checkbox" 
                                   name="finger_status" 
                                   id="finger_status"
                                   value="1"
                                   class="sr-only peer"
                                   {{ old('finger_status', $setting->finger_status ?? 0) == 1 ? 'checked' : '' }}>
                            <div class="w-14 h-7 bg-slate-300 dark:bg-slate-600 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 dark:peer-focus:ring-purple-800 rounded-full peer peer-checked:bg-purple-600 dark:peer-checked:bg-purple-500 transition-colors duration-200 ease-in-out shadow-inner">
                                <div class="absolute top-0.5 right-0.5 bg-white dark:bg-slate-200 border-2 border-slate-300 dark:border-slate-500 rounded-full h-6 w-6 transition-all duration-200 ease-in-out peer-checked:right-auto peer-checked:left-0.5 peer-checked:border-purple-600 dark:peer-checked:border-purple-400 shadow-md"></div>
                            </div>
                        </label>
                    </div>

                    <!-- اطلاعات اضافی -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-info-circle text-blue-600 dark:text-blue-400 mt-0.5"></i>
                            <div class="flex-1">
                                <p class="text-sm text-blue-800 dark:text-blue-200 font-semibold mb-1">نکته مهم</p>
                                <p class="text-xs text-blue-700 dark:text-blue-300">
                                    با فعال‌سازی سیستم کد اثر انگشت، تمام کاربران باید در زمان ثبت‌نام کد اثر انگشت معتبر ارائه دهند. 
                                    این سیستم برای افزایش امنیت و جلوگیری از ثبت‌نام تکراری استفاده می‌شود.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- دکمه ذخیره -->
        <div class="mt-6 flex items-center justify-end gap-3">
            <a href="{{ route('admin.system-settings.index') }}" 
               class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-700 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-400 transition-colors">
                <i class="fas fa-times ml-2"></i>
                انصراف
            </a>
            <button type="submit" class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                <i class="fas fa-save ml-2"></i>
                ذخیره تغییرات
            </button>
        </div>
    </form>
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

