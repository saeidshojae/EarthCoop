@extends('layouts.admin')

@section('title', 'دسته‌بندی‌های پایگاه دانش')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-layer-group ml-2"></i>
                دسته‌بندی‌های پایگاه دانش
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">ساختار موضوعی برای مقالات پشتیبانی</p>
        </div>
        <a href="{{ route('admin.kb.articles.index') }}"
           class="inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700">
            <i class="fas fa-arrow-right ml-2"></i>
            بازگشت به مقالات
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-6 flex items-center gap-3 text-sm text-green-800 dark:text-green-200">
            <i class="fas fa-check-circle"></i>
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-6">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">لیست دسته‌بندی‌ها</h2>
            <div class="space-y-4">
                @forelse($categories as $category)
                    <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ $category->name }}</h3>
                                <p class="text-sm text-slate-500">{{ $category->description }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('admin.kb.categories.destroy', $category) }}" onsubmit="return confirm('حذف دسته‌بندی و زیرمجموعه‌ها؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="px-3 py-1 text-xs text-white bg-red-600 rounded-lg">حذف</button>
                                </form>
                            </div>
                        </div>
                        @if($category->children->count())
                            <div class="pl-4 border-r border-slate-200 dark:border-slate-700 mt-3">
                                @foreach($category->children as $child)
                                    <div class="flex items-center justify-between py-2">
                                        <div>
                                            <p class="font-semibold text-slate-800 dark:text-slate-200">{{ $child->name }}</p>
                                            <p class="text-xs text-slate-500">{{ $child->description }}</p>
                                        </div>
                                        <form method="POST" action="{{ route('admin.kb.categories.destroy', $child) }}" onsubmit="return confirm('حذف زیر دسته؟');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="px-3 py-1 text-xs text-white bg-red-600 rounded-lg">حذف</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">دسته‌ای ثبت نشده است.</p>
                @endforelse
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-6">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">ایجاد دسته‌بندی جدید</h2>
            <form method="POST" action="{{ route('admin.kb.categories.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">عنوان</label>
                    <input type="text" name="name" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">والد</label>
                    <select name="parent_id" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                        <option value="">بدون والد</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">آیکون (کلاس FontAwesome)</label>
                    <input type="text" name="icon" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white" placeholder="fas fa-life-ring">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">توضیحات</label>
                    <textarea name="description" rows="3" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">ترتیب نمایش</label>
                    <input type="number" name="sort_order" value="0" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="is_active" class="rounded border-slate-300 dark:border-slate-600" checked>
                    فعال باشد
                </label>
                <button class="w-full inline-flex justify-center items-center px-4 py-2 text-white bg-green-600 rounded-lg hover:bg-green-700 font-semibold">
                    ذخیره دسته‌بندی
                </button>
            </form>
        </div>
    </div>
</div>
@endsection




