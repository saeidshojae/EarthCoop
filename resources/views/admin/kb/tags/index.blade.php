@extends('layouts.admin')

@section('title', 'تگ‌های پایگاه دانش')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-tags ml-2"></i>
                مدیریت تگ‌ها
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">برچسب‌های مقالات پایگاه دانش</p>
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
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">لیست تگ‌ها</h2>
            <div class="space-y-3">
                @forelse($tags as $tag)
                    <form method="POST" action="{{ route('admin.kb.tags.update', $tag) }}" class="border border-slate-200 dark:border-slate-700 rounded-lg p-4 flex flex-col gap-3">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="text-xs font-semibold text-slate-500">عنوان</label>
                                <input type="text" name="name" value="{{ $tag->name }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-500">رنگ (HEX)</label>
                                <input type="text" name="color" value="{{ $tag->color }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white" placeholder="#0ea5e9">
                            </div>
                            <div class="flex items-center">
                                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                    <input type="checkbox" name="is_active" class="rounded border-slate-300 dark:border-slate-600" @checked($tag->is_active)>
                                    فعال
                                </label>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <button class="inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700">
                                ذخیره
                            </button>
                            <form method="POST" action="{{ route('admin.kb.tags.destroy', $tag) }}" onsubmit="return confirm('حذف تگ؟');">
                                @csrf
                                @method('DELETE')
                                <button class="inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700">
                                    حذف
                                </button>
                            </form>
                        </div>
                    </form>
                @empty
                    <p class="text-sm text-slate-500">تگی ثبت نشده است.</p>
                @endforelse
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-6">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">ایجاد تگ جدید</h2>
            <form method="POST" action="{{ route('admin.kb.tags.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">عنوان</label>
                    <input type="text" name="name" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">رنگ (HEX)</label>
                    <input type="text" name="color" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white" placeholder="#0ea5e9">
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="is_active" class="rounded border-slate-300 dark:border-slate-600" checked>
                    فعال باشد
                </label>
                <button class="w-full inline-flex justify-center items-center px-4 py-2 text-white bg-green-600 rounded-lg hover:bg-green-700 font-semibold">
                    ذخیره تگ
                </button>
            </form>
        </div>
    </div>
</div>
@endsection




