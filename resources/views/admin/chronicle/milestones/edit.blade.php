@extends('layouts.admin')

@section('title', 'ویرایش رویداد گاه‌شمار')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">ویرایش رویداد گاه‌شمار</h1>
                <p class="text-slate-600 dark:text-slate-400 mt-1">{{ $milestone->translatedTitle('fa') }}</p>
            </div>
            <a href="{{ route('admin.chronicle.milestones.index') }}" class="px-4 py-2 rounded-lg bg-slate-600 text-white">بازگشت</a>
        </div>

        @if($errors->any())
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.chronicle.milestones.update', $milestone) }}"
              class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">تاریخ رویداد</label>
                <x-temporal.date-input name="occurred_on" :value="old('occurred_on', $milestone->occurred_on)" class="w-full rounded-lg border-slate-300 dark:bg-slate-700 dark:border-slate-600 dark:text-white" required />
            </div>

            @php
                $titles = $milestone->title_translations ?? [];
                $descriptions = $milestone->description_translations ?? [];
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">عنوان فارسی</label>
                    <input name="title_fa" required maxlength="255" value="{{ old('title_fa', $titles['fa'] ?? '') }}"
                           class="w-full rounded-lg border-slate-300 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">عنوان انگلیسی</label>
                    <input name="title_en" maxlength="255" dir="ltr" value="{{ old('title_en', $titles['en'] ?? '') }}"
                           class="w-full rounded-lg border-slate-300 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">عنوان عربی</label>
                    <input name="title_ar" maxlength="255" value="{{ old('title_ar', $titles['ar'] ?? '') }}"
                           class="w-full rounded-lg border-slate-300 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4">
                <textarea name="description_fa" rows="4" maxlength="5000" placeholder="شرح فارسی"
                          class="w-full rounded-lg border-slate-300 dark:bg-slate-700 dark:border-slate-600 dark:text-white">{{ old('description_fa', $descriptions['fa'] ?? '') }}</textarea>
                <textarea name="description_en" rows="4" maxlength="5000" dir="ltr" placeholder="English description"
                          class="w-full rounded-lg border-slate-300 dark:bg-slate-700 dark:border-slate-600 dark:text-white">{{ old('description_en', $descriptions['en'] ?? '') }}</textarea>
                <textarea name="description_ar" rows="4" maxlength="5000" placeholder="الوصف بالعربية"
                          class="w-full rounded-lg border-slate-300 dark:bg-slate-700 dark:border-slate-600 dark:text-white">{{ old('description_ar', $descriptions['ar'] ?? '') }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">ترتیب</label>
                    <input type="number" min="0" max="65535" name="sort_order" value="{{ old('sort_order', $milestone->sort_order) }}"
                           class="w-full rounded-lg border-slate-300 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                </div>
                <label class="flex items-center gap-2 pb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $milestone->is_published)) class="rounded">
                    منتشر شود
                </label>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
                <a href="{{ route('admin.chronicle.milestones.index') }}" class="px-5 py-2 rounded-lg bg-slate-600 text-white">انصراف</a>
                <button class="px-5 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">ذخیره تغییرات</button>
            </div>
        </form>
    </div>
</div>
@endsection
