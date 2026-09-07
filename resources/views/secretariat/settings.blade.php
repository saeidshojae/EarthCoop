@extends('layouts.unified')

@section('title', 'تنظیمات دبیرخانه - ' . $office->name)

@section('content')
@php
    $policy = $office->numbering_policy ?? [];
    $format = old('numbering_format', $policy['format'] ?? '{OFFICE}/{YEAR}/{FAMILY}/{SEQ}');
    $width = old('sequence_width', $policy['sequence_width'] ?? 6);
@endphp

<div class="container mx-auto px-4 py-6 max-w-4xl">
    <div class="mb-6">
        <a href="{{ route('secretariat.index', $office) }}" class="text-sm text-emerald-700 hover:underline">
            <i class="fa-solid fa-arrow-right ml-1"></i> بازگشت به داشبورد دبیرخانه
        </a>
        <h1 class="text-2xl font-bold mt-3">تنظیمات دبیرخانه</h1>
        <p class="text-sm text-gray-500 mt-1">{{ $office->name }} — نوع و مالکیت دفتر از این صفحه قابل تغییر نیست و canonical باقی می‌ماند.</p>
    </div>

    @if($errors->any())
        <div class="rounded-xl bg-red-50 border border-red-200 text-red-800 p-4 mb-5">
            <ul class="list-disc pr-5 space-y-1 text-sm">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('secretariat.settings.update', $office) }}"
          class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm p-6 space-y-6">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium mb-2">نام نمایشی دفتر</label>
            <input name="name" value="{{ old('name', $office->name) }}" required maxlength="255"
                   class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
            <p class="text-xs text-gray-500 mt-1">کد ثابت دفتر: <span class="font-mono">{{ $office->code }}</span></p>
        </div>

        <div>
            <label class="block text-sm font-medium mb-2">سطح محرمانگی پیش‌فرض اسناد جدید</label>
            <select name="default_confidentiality" required class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                @foreach($confidentialities as $level)
                    <option value="{{ $level }}" @selected(old('default_confidentiality', $office->default_confidentiality) === $level)>{{ $level }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-500 mt-1">هر سند هنگام ایجاد همچنان می‌تواند سطح متفاوتی داشته باشد؛ restricted/confidential تابع ACL صریح است.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-2">قالب شماره ثبت</label>
                <input name="numbering_format" value="{{ $format }}" required maxlength="160" dir="ltr"
                       class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700 font-mono">
                <p class="text-xs text-gray-500 mt-1">توکن‌های متعارف: {OFFICE}، {YEAR}، {FAMILY} و الزاماً {SEQ}.</p>
            </div>
            <div>
                <label class="block text-sm font-medium mb-2">طول شماره ترتیبی</label>
                <input type="number" name="sequence_width" value="{{ $width }}" min="1" max="12" required
                       class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
            </div>
        </div>

        <div class="rounded-xl bg-slate-50 dark:bg-gray-900/50 border p-4 text-sm text-gray-600 dark:text-gray-300">
            <strong>محدوده این تنظیمات:</strong> فقط نام نمایشی، محرمانگی پیش‌فرض و سیاست شماره‌گذاری تغییر می‌کند. نوع دفتر، scope گروه/مرکزی و مالکیت canonical قابل ویرایش نیست.
        </div>

        <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end">
            <a href="{{ route('secretariat.index', $office) }}" class="rounded-xl border px-5 py-3 text-center">انصراف</a>
            <button class="rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3">ذخیره تنظیمات</button>
        </div>
    </form>
</div>
@endsection
