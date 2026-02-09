@extends('layouts.admin')

@section('title', 'مقالات پایگاه دانش')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-book ml-2"></i>
                مدیریت مقالات پایگاه دانش
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">
                ایجاد و مدیریت مقالات آموزشی برای پشتیبانی سریع‌تر کاربران
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.kb.categories.index') }}"
               class="inline-flex items-center px-4 py-2 text-sm font-semibold text-blue-700 bg-blue-100 rounded-lg hover:bg-blue-200">
                <i class="fas fa-layer-group ml-2"></i>
                مدیریت دسته‌بندی
            </a>
            <a href="{{ route('admin.kb.tags.index') }}"
               class="inline-flex items-center px-4 py-2 text-sm font-semibold text-purple-700 bg-purple-100 rounded-lg hover:bg-purple-200">
                <i class="fas fa-tags ml-2"></i>
                مدیریت تگ‌ها
            </a>
            <a href="{{ route('admin.kb.articles.create') }}"
               class="inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700">
                <i class="fas fa-plus ml-2"></i>
                مقاله جدید
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-6 flex items-center gap-3 text-sm text-green-800 dark:text-green-200">
            <i class="fas fa-check-circle"></i>
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="text-sm font-semibold text-slate-600 dark:text-slate-300 mb-1 block">جستجو</label>
                <input type="text" name="search" value="{{ request('search') }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white" placeholder="عنوان یا توضیح...">
            </div>
            <div>
                <label class="text-sm font-semibold text-slate-600 dark:text-slate-300 mb-1 block">دسته‌بندی</label>
                <select name="category_id" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                    <option value="">همه</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-semibold text-slate-600 dark:text-slate-300 mb-1 block">وضعیت</label>
                <select name="status" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                    <option value="">همه</option>
                    <option value="draft" @selected(request('status') === 'draft')>پیش‌نویس</option>
                    <option value="published" @selected(request('status') === 'published')>منتشر شده</option>
                    <option value="archived" @selected(request('status') === 'archived')>آرشیو</option>
                </select>
            </div>
            <div class="flex items-end">
                <button class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-filter ml-2"></i>
                    اعمال فیلتر
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase">عنوان</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase">دسته</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase">وضعیت</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase">بازدید</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase">تاریخ انتشار</th>
                        <th class="px-6 py-3 text-xs font-medium text-slate-500 uppercase text-center">عملیات</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($articles as $article)
                        <tr>
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-slate-900 dark:text-white">{{ $article->title }}</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $article->excerpt }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-700 dark:text-slate-300">
                                {{ $article->category?->name ?? '---' }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold
                                    @if($article->status === 'published') bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200
                                    @elseif($article->status === 'archived') bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200
                                    @else bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200 @endif">
                                    {{ __('status.' . $article->status) ?? $article->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-700 dark:text-slate-300 text-center">
                                {{ number_format($article->view_count) }}
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-700 dark:text-slate-300">
                                {{ optional($article->published_at)->format('Y/m/d H:i') ?? '---' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-700 dark:text-slate-300">
                                <div class="flex items-center gap-2 justify-center">
                                    <a href="{{ route('admin.kb.articles.edit', $article) }}"
                                       class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                                        <i class="fas fa-edit ml-1"></i>
                                        ویرایش
                                    </a>
                                    <form action="{{ route('admin.kb.articles.toggle', $article) }}" method="POST">
                                        @csrf
                                        <button class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                                            <i class="fas fa-toggle-on ml-1"></i>
                                            تغییر وضعیت
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.kb.articles.destroy', $article) }}" method="POST" onsubmit="return confirm('آیا از حذف مقاله مطمئن هستید؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700">
                                            <i class="fas fa-trash ml-1"></i>
                                            حذف
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-slate-500">
                                هیچ مقاله‌ای ثبت نشده است.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4">
            {{ $articles->links() }}
        </div>
    </div>
</div>
@endsection




