@extends('layouts.unified')

@section('title', 'سند جدید - ' . $office->name)

@section('content')
<div class="container mx-auto px-4 py-6 max-w-4xl">
    <div class="mb-6">
        <a href="{{ route('secretariat.index', $office) }}" class="text-sm text-emerald-700 hover:underline">
            <i class="fa-solid fa-arrow-right ml-1"></i> بازگشت به دبیرخانه
        </a>
        <h1 class="text-2xl font-bold mt-3">ثبت پیش‌نویس جدید</h1>
        <p class="text-sm text-gray-500 mt-1">{{ $office->name }} — فایل در صورت انتخاب به نسخه جاری همین سند متصل می‌شود.</p>
    </div>

    @if($errors->any())
        <div class="rounded-xl bg-red-50 border border-red-200 text-red-800 p-4 mb-5">
            <ul class="list-disc pr-5 space-y-1 text-sm">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" enctype="multipart/form-data" action="{{ route('secretariat.records.store', $office) }}"
          class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm p-6 space-y-5">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-2">نوع سند</label>
                <select name="record_type" required class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                    @foreach($recordTypes as $type)
                        <option value="{{ $type }}" @selected(old('record_type') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-2">جهت</label>
                <select name="direction" required class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                    @foreach($directions as $direction)
                        <option value="{{ $direction }}" @selected(old('direction', 'none') === $direction)>{{ $direction }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium mb-2">عنوان</label>
            <input name="title" value="{{ old('title') }}" required maxlength="500"
                   class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
        </div>

        <div>
            <label class="block text-sm font-medium mb-2">موضوع</label>
            <input name="subject" value="{{ old('subject') }}" maxlength="1000"
                   class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
        </div>

        <div>
            <label class="block text-sm font-medium mb-2">خلاصه</label>
            <textarea name="summary" rows="3" maxlength="5000"
                      class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">{{ old('summary') }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-medium mb-2">متن سند</label>
            <textarea name="body" rows="9"
                      class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">{{ old('body') }}</textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-2">سطح محرمانگی</label>
                <select name="confidentiality" required class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                    @foreach($confidentialities as $level)
                        <option value="{{ $level }}" @selected(old('confidentiality', $office->default_confidentiality) === $level)>{{ $level }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">restricted/confidential بدون ACL صریح برای کاربران عادی نمایش داده نمی‌شوند.</p>
            </div>
            <div>
                <label class="block text-sm font-medium mb-2">پیوست اولیه (اختیاری)</label>
                <input type="file" name="attachment" class="w-full rounded-xl border border-gray-300 p-2 dark:bg-gray-900 dark:border-gray-700">
                <p class="text-xs text-gray-500 mt-1">حداکثر ۲۰ مگابایت در این UI. checksum SHA-256 در زمان ثبت محاسبه می‌شود.</p>
            </div>
        </div>

        <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end pt-3">
            <a href="{{ route('secretariat.index', $office) }}" class="rounded-xl border px-5 py-3 text-center">انصراف</a>
            <button class="rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3">
                ایجاد پیش‌نویس
            </button>
        </div>
    </form>
</div>
@endsection
