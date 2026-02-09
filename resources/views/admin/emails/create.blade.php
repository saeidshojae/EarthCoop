@extends('layouts.admin')

@section('title', 'ایجاد قالب ایمیل جدید - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'ایجاد قالب ایمیل جدید')

@section('content')
<div class="container mx-auto px-4 py-6 max-w-4xl">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <form action="{{ route('admin.emails.store') }}" method="POST">
            @csrf

            <div class="space-y-6">
                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        نام قالب <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Subject -->
                <div>
                    <label for="subject" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        موضوع ایمیل <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="subject" name="subject" value="{{ old('subject') }}" required
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                        placeholder="مثال: خوش آمدید @{{name}}">
                    <p class="mt-1 text-xs text-gray-500">می‌توانید از متغیرها استفاده کنید: @{{name}}, @{{email}}, etc.</p>
                    @error('subject')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Category -->
                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        دسته‌بندی
                    </label>
                    <input type="text" id="category" name="category" value="{{ old('category') }}"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                        placeholder="مثال: invitation, notification">
                    @error('category')
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

                <!-- Body -->
                <div>
                    <label for="body" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        محتوای ایمیل (HTML) <span class="text-red-500">*</span>
                    </label>
                    <textarea id="body" name="body" rows="15" required
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white font-mono text-sm">{{ old('body') }}</textarea>
                    <p class="mt-1 text-xs text-gray-500">می‌توانید از HTML و متغیرها استفاده کنید: @{{name}}, @{{email}}, etc.</p>
                    @error('body')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Is Active -->
                <div class="flex items-center">
                    <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <label for="is_active" class="mr-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        قالب فعال است
                    </label>
                </div>

                <!-- Actions -->
                <div class="flex gap-3 pt-4">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition">
                        ایجاد قالب
                    </button>
                    <a href="{{ route('admin.emails.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg transition">
                        انصراف
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

