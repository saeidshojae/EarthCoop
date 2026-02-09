@extends('layouts.admin')

@section('title', 'ایجاد صفحه جدید')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <!-- هدر صفحه -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-plus-circle ml-2"></i>
                ایجاد صفحه جدید
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">ایجاد یک صفحه استاتیک جدید</p>
        </div>
        <a href="{{ route('admin.pages.index') }}" 
           class="inline-flex items-center px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition-colors">
            <i class="fas fa-arrow-right ml-2"></i>
            بازگشت به لیست
        </a>
    </div>

    @if ($errors->any())
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 px-4 py-3 rounded-lg mb-6">
            <div class="flex items-center mb-2">
                <i class="fas fa-exclamation-circle ml-2"></i>
                <strong>خطاهای اعتبارسنجی:</strong>
            </div>
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- فرم ایجاد صفحه -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <form action="{{ route('admin.pages.store') }}" method="POST" class="space-y-6">
            @csrf

            <!-- عنوان و قالب -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="title" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        عنوان (پیش‌فرض - فارسی) <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           id="title" 
                           name="title" 
                           value="{{ old('title') }}" 
                           required
                           class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                           placeholder="عنوان صفحه را وارد کنید">
                    @error('title')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="template" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        قالب صفحه
                    </label>
                    <select id="template" 
                            name="template"
                            class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                        <option value="default" {{ old('template', 'default') == 'default' ? 'selected' : '' }}>پیش‌فرض (Default)</option>
                        <option value="about" {{ old('template') == 'about' ? 'selected' : '' }}>درباره ارثکوپ (About)</option>
                        <option value="help" {{ old('template') == 'help' ? 'selected' : '' }}>راهنمای استفاده (Help)</option>
                        <option value="cooperation" {{ old('template') == 'cooperation' ? 'selected' : '' }}>فرصت همکاری (Cooperation)</option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        انتخاب قالب برای نمایش صفحه. صفحات خاص می‌توانند طراحی اختصاصی داشته باشند.
                    </p>
                    @error('template')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- ترجمه‌های عنوان -->
            <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-6 border border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
                    <i class="fas fa-language ml-2"></i>
                    ترجمه‌های عنوان
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="title_fa" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            عنوان فارسی
                        </label>
                        <input type="text" 
                               id="title_fa" 
                               name="title_fa" 
                               value="{{ old('title_fa') }}"
                               class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                               placeholder="عنوان به فارسی">
                    </div>
                    <div>
                        <label for="title_en" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            عنوان انگلیسی
                        </label>
                        <input type="text" 
                               id="title_en" 
                               name="title_en" 
                               value="{{ old('title_en') }}"
                               class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                               placeholder="English Title">
                    </div>
                    <div>
                        <label for="title_ar" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            عنوان عربی
                        </label>
                        <input type="text" 
                               id="title_ar" 
                               name="title_ar" 
                               value="{{ old('title_ar') }}"
                               class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                               placeholder="العنوان بالعربية">
                    </div>
                </div>
            </div>

            <!-- محتوا -->
            <div>
                <label for="content" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    محتوا (پیش‌فرض - فارسی) <span class="text-red-500">*</span>
                </label>
                <textarea id="editor" 
                          name="content" 
                          rows="10"
                          required
                          class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                          placeholder="محتوا صفحه را وارد کنید">{{ old('content') }}</textarea>
                @error('content')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <!-- ترجمه‌های محتوا -->
            <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-6 border border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
                    <i class="fas fa-language ml-2"></i>
                    ترجمه‌های محتوا
                </h3>
                <div class="space-y-4">
                    <div>
                        <label for="content_fa" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            محتوا فارسی
                        </label>
                        <textarea id="editor_fa" 
                                  name="content_fa" 
                                  rows="5"
                                  class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                                  placeholder="محتوا به فارسی">{{ old('content_fa') }}</textarea>
                    </div>
                    <div>
                        <label for="content_en" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            محتوا انگلیسی
                        </label>
                        <textarea id="editor_en" 
                                  name="content_en" 
                                  rows="5"
                                  class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                                  placeholder="English Content">{{ old('content_en') }}</textarea>
                    </div>
                    <div>
                        <label for="content_ar" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            محتوا عربی
                        </label>
                        <textarea id="editor_ar" 
                                  name="content_ar" 
                                  rows="5"
                                  class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                                  placeholder="المحتوى بالعربية">{{ old('content_ar') }}</textarea>
                    </div>
                </div>
            </div>

            <!-- SEO -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="meta_title" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        عنوان متا (SEO)
                    </label>
                    <input type="text" 
                           id="meta_title" 
                           name="meta_title" 
                           value="{{ old('meta_title') }}"
                           class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                           placeholder="عنوان متا برای SEO">
                    @error('meta_title')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="meta_description" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        توضیحات متا (SEO)
                    </label>
                    <textarea id="meta_description" 
                              name="meta_description" 
                              rows="3"
                              class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                              placeholder="توضیحات متا برای SEO">{{ old('meta_description') }}</textarea>
                    @error('meta_description')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- تنظیمات -->
            <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-6 border border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
                    <i class="fas fa-cog ml-2"></i>
                    تنظیمات
                </h3>
                <div class="space-y-4">
                    <div class="flex items-center">
                        <input type="checkbox" 
                               id="is_published" 
                               name="is_published" 
                               value="1"
                               {{ old('is_published') ? 'checked' : '' }}
                               class="w-4 h-4 text-blue-600 bg-slate-100 border-slate-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-slate-800 focus:ring-2 dark:bg-slate-700 dark:border-slate-600">
                        <label for="is_published" class="mr-2 text-sm font-semibold text-slate-700 dark:text-slate-300">
                            انتشار صفحه
                        </label>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" 
                               id="show_in_header" 
                               name="show_in_header" 
                               value="1"
                               {{ old('show_in_header') ? 'checked' : '' }}
                               class="w-4 h-4 text-blue-600 bg-slate-100 border-slate-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-slate-800 focus:ring-2 dark:bg-slate-700 dark:border-slate-600">
                        <label for="show_in_header" class="mr-2 text-sm font-semibold text-slate-700 dark:text-slate-300">
                            نمایش در هدر
                        </label>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        اگر فعال باشد، این صفحه در منوی هدر نمایش داده می‌شود.
                    </p>
                </div>
            </div>

            <!-- دکمه‌های عملیات -->
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-200 dark:border-slate-700">
                <a href="{{ route('admin.pages.index') }}" 
                   class="inline-flex items-center px-6 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition-colors">
                    <i class="fas fa-times ml-2"></i>
                    انصراف
                </a>
                <button type="submit" 
                        class="inline-flex items-center px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-save ml-2"></i>
                    ایجاد صفحه
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
<script>
    var editorConfig = {
        filebrowserUploadUrl: "{{ route('admin.pages.upload') }}?_token={{ csrf_token() }}",
        filebrowserUploadMethod: 'form',
        height: 300,
        removeButtons: 'Save,NewPage,Preview,Print,Templates,Cut,Copy,Paste,PasteText,PasteFromWord,Find,Replace,SelectAll,Scayt,Form,Checkbox,Radio,TextField,Textarea,Select,Button,ImageButton,HiddenField,Strike,Subscript,Superscript,CopyFormatting,RemoveFormat,Outdent,Indent,CreateDiv,Blockquote,BidiLtr,BidiRtl,Language,Anchor,Flash,Table,HorizontalRule,Smiley,SpecialChar,PageBreak,Iframe,Maximize,ShowBlocks,About',
        toolbarGroups: [
            { name: 'document', groups: [ 'mode', 'document', 'doctools' ] },
            { name: 'clipboard', groups: [ 'clipboard', 'undo' ] },
            { name: 'editing', groups: [ 'find', 'selection', 'spellchecker', 'editing' ] },
            { name: 'forms', groups: [ 'forms' ] },
            '/',
            { name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
            { name: 'paragraph', groups: [ 'list', 'indent', 'blocks', 'align', 'bidi', 'paragraph' ] },
            { name: 'links', groups: [ 'links' ] },
            { name: 'insert', groups: [ 'insert' ] },
            '/',
            { name: 'styles', groups: [ 'styles' ] },
            { name: 'colors', groups: [ 'colors' ] },
            { name: 'tools', groups: [ 'tools' ] },
            { name: 'others', groups: [ 'others' ] }
        ],
        extraPlugins: 'uploadimage',
        uploadUrl: "{{ route('admin.pages.upload') }}?_token={{ csrf_token() }}"
    };

    CKEDITOR.replace('editor', Object.assign({}, editorConfig, { language: 'fa', height: 400 }));
    CKEDITOR.replace('editor_fa', Object.assign({}, editorConfig, { language: 'fa' }));
    CKEDITOR.replace('editor_en', Object.assign({}, editorConfig, { language: 'en' }));
    CKEDITOR.replace('editor_ar', Object.assign({}, editorConfig, { language: 'ar' }));
</script>
@endpush
@endsection
