@extends('layouts.admin')

@section('title', 'ایجاد اساسنامه جدید')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <!-- هدر صفحه -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-plus-circle ml-2"></i>
                ایجاد اساسنامه جدید
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">ایجاد اساسنامه جدید برای سیستم</p>
        </div>
        <a href="{{ route('admin.rule.index') }}" 
           class="inline-flex items-center px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition-colors">
            <i class="fas fa-arrow-right ml-2"></i>
            بازگشت
        </a>
    </div>

    @if ($errors->any())
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 px-4 py-3 rounded-lg mb-6">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- فرم ایجاد اساسنامه -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <form action="{{ route('admin.rule.store') }}" method="POST" class="space-y-6">
            @csrf
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label for="title" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        عنوان اساسنامه <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           id="title" 
                           name="title" 
                           value="{{ old('title') }}"
                           required
                           class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                           placeholder="عنوان اساسنامه را وارد کنید">
                    @error('title')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label for="message" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        محتوای اساسنامه <span class="text-red-500">*</span>
                    </label>
                    <textarea id="message" 
                              name="message" 
                              rows="15"
                              required
                              class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                              placeholder="محتوای اساسنامه را وارد کنید">{{ old('message') }}</textarea>
                    @error('message')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label for="parent_id" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        والد (اختیاری)
                    </label>
                    <select id="parent_id" 
                            name="parent_id"
                            class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                        <option value="">اساسنامه اصلی (بدون والد)</option>
                        @foreach($mainTerms as $mainTerm)
                            <option value="{{ $mainTerm->id }}" {{ old('parent_id') == $mainTerm->id ? 'selected' : '' }}>
                                {{ $mainTerm->title }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        اگر می‌خواهید این اساسنامه به عنوان زیرمجموعه یک اساسنامه دیگر باشد، والد را انتخاب کنید.
                    </p>
                    @error('parent_id')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3 space-x-reverse pt-4 border-t border-slate-200 dark:border-slate-700">
                <a href="{{ route('admin.rule.index') }}" 
                   class="inline-flex items-center px-6 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition-colors">
                    <i class="fas fa-times ml-2"></i>
                    انصراف
                </a>
                <button type="submit" 
                        class="inline-flex items-center px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    <i class="fas fa-save ml-2"></i>
                    ایجاد اساسنامه
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
<script>
    CKEDITOR.replace('message', {
        filebrowserUploadUrl: "{{ route('admin.pages.upload') }}?_token={{ csrf_token() }}",
        filebrowserUploadMethod: 'form',
        language: 'fa',
        height: 400,
        extraPlugins: 'uploadimage',
        removeButtons: '',
        toolbarGroups: [
            { name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
            { name: 'paragraph', groups: [ 'list', 'indent', 'blocks', 'align' ] },
            { name: 'styles' },
            { name: 'colors' },
            { name: 'insert' },
            { name: 'tools' },
            { name: 'editing' },
            { name: 'document', groups: [ 'mode', 'document' ] },
            { name: 'clipboard', groups: [ 'clipboard', 'undo' ] },
            { name: 'links' }
        ]
    });
</script>
@endpush
@endsection

