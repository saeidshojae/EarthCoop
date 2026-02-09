@extends('layouts.admin')

@section('title', 'ایجاد ایمیل سیستم جدید - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'ایجاد ایمیل سیستم جدید')

@section('content')
<div class="container mx-auto px-4 py-6 max-w-2xl">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <form action="{{ route('admin.system-emails.store') }}" method="POST">
            @csrf

            <div class="space-y-6">
                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        نام <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                        placeholder="مثال: support, contact, info">
                    <p class="mt-1 text-xs text-gray-500">نام یکتای ایمیل (بدون @ و دامنه)</p>
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        آدرس ایمیل <span class="text-red-500">*</span>
                    </label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                        placeholder="support@earthcoop.info">
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Display Name -->
                <div>
                    <label for="display_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        نام نمایشی
                    </label>
                    <input type="text" id="display_name" name="display_name" value="{{ old('display_name') }}"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                        placeholder="مثال: پشتیبانی EarthCoop">
                    <p class="mt-1 text-xs text-gray-500">نامی که در ایمیل‌های ارسالی نمایش داده می‌شود</p>
                    @error('display_name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        توضیحات
                    </label>
                    <textarea id="description" name="description" rows="3"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Is Active -->
                <div class="flex items-center">
                    <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <label for="is_active" class="mr-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        ایمیل فعال است
                    </label>
                </div>

                <!-- Is Default -->
                <div class="flex items-center">
                    <input type="checkbox" id="is_default" name="is_default" value="1" {{ old('is_default') ? 'checked' : '' }}
                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <label for="is_default" class="mr-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        تنظیم به عنوان ایمیل پیش‌فرض
                    </label>
                    <p class="text-xs text-gray-500 mr-2">(اگر فعال باشد، این ایمیل به عنوان پیش‌فرض برای ارسال ایمیل‌ها استفاده می‌شود)</p>
                </div>

                <!-- Actions -->
                <div class="flex gap-3 pt-4">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition">
                        ایجاد ایمیل
                    </button>
                    <a href="{{ route('admin.system-emails.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg transition">
                        انصراف
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

