@extends('layouts.admin')

@section('title', 'ویرایش اطلاعیه')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <!-- هدر صفحه -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-edit ml-2"></i>
                ویرایش اطلاعیه
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">ویرایش اطلاعیه «{{ $announcement->title }}»</p>
        </div>
        <a href="{{ route('admin.announcement.index') }}" 
           class="inline-flex items-center px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition-colors">
            <i class="fas fa-arrow-right ml-2"></i>
            بازگشت
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-check-circle ml-2"></i>
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 px-4 py-3 rounded-lg mb-6">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- فرم ویرایش اطلاعیه -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <form action="{{ route('admin.announcement.update', $announcement) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @method('PUT')
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="title" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        تیتر اطلاعیه <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           id="title" 
                           name="title" 
                           value="{{ old('title', $announcement->title) }}"
                           required
                           class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                           placeholder="تیتر اطلاعیه را وارد کنید">
                    @error('title')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="group_level" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        سطح گروه <span class="text-red-500">*</span>
                    </label>
                    <select id="group_level" 
                            name="group_level"
                            required
                            class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                        <option value="">انتخاب کنید</option>
                        <option value="global" {{ old('group_level', $announcement->group_level) == 'global' ? 'selected' : '' }}>جهانی</option>
                        <option value="continent" {{ old('group_level', $announcement->group_level) == 'continent' ? 'selected' : '' }}>قاره</option>
                        <option value="country" {{ old('group_level', $announcement->group_level) == 'country' ? 'selected' : '' }}>کشور</option>
                        <option value="province" {{ old('group_level', $announcement->group_level) == 'province' ? 'selected' : '' }}>استان</option>
                        <option value="county" {{ old('group_level', $announcement->group_level) == 'county' ? 'selected' : '' }}>شهرستان</option>
                        <option value="section" {{ old('group_level', $announcement->group_level) == 'section' ? 'selected' : '' }}>بخش</option>
                        <option value="city" {{ old('group_level', $announcement->group_level) == 'city' ? 'selected' : '' }}>شهر/روستا</option>
                        <option value="region" {{ old('group_level', $announcement->group_level) == 'region' ? 'selected' : '' }}>منطقه/دهستان</option>
                        <option value="neighborhood" {{ old('group_level', $announcement->group_level) == 'neighborhood' ? 'selected' : '' }}>محله</option>
                        <option value="street" {{ old('group_level', $announcement->group_level) == 'street' ? 'selected' : '' }}>خیابان</option>
                        <option value="alley" {{ old('group_level', $announcement->group_level) == 'alley' ? 'selected' : '' }}>کوچه</option>
                    </select>
                    @error('group_level')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="content" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    متن اطلاعیه <span class="text-red-500">*</span>
                </label>
                <textarea id="content" 
                          name="content" 
                          rows="6"
                          required
                          class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                          placeholder="متن اطلاعیه را وارد کنید">{{ old('content', $announcement->content) }}</textarea>
                @error('content')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="image" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        تصویر اطلاعیه
                    </label>
                    
                    @if($announcement->image)
                        <div class="mb-3">
                            <p class="text-sm text-slate-600 dark:text-slate-400 mb-2">تصویر فعلی:</p>
                            <img src="{{ asset($announcement->image) }}" 
                                 alt="{{ $announcement->title }}"
                                 class="w-32 h-32 object-cover rounded-lg border border-slate-300 dark:border-slate-600">
                        </div>
                    @endif
                    
                    <input type="file" 
                           id="image" 
                           name="image" 
                           accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                           class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                    @error('image')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">حداکثر حجم: 5 مگابایت | فرمت‌های مجاز: JPG, PNG, GIF, WEBP</p>
                    @if($announcement->image)
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">برای تغییر تصویر، فایل جدید انتخاب کنید</p>
                    @endif
                </div>

                <div class="flex items-center">
                    <div class="flex items-center h-5">
                        <input id="should_pin" 
                               name="should_pin" 
                               type="checkbox" 
                               value="1"
                               {{ old('should_pin', $announcement->should_pin) ? 'checked' : '' }}
                               class="w-4 h-4 text-blue-600 bg-slate-100 border-slate-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-slate-800 focus:ring-2 dark:bg-slate-700 dark:border-slate-600">
                    </div>
                    <label for="should_pin" class="mr-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                        پین شدن خودکار در گروه‌های مربوطه
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3 space-x-reverse pt-4 border-t border-slate-200 dark:border-slate-700">
                <a href="{{ route('admin.announcement.index') }}" 
                   class="inline-flex items-center px-6 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition-colors">
                    <i class="fas fa-times ml-2"></i>
                    انصراف
                </a>
                <button type="submit" 
                        class="inline-flex items-center px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-save ml-2"></i>
                    ذخیره تغییرات
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

