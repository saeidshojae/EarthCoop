@extends('layouts.unified')

@section('title', 'ثبت سند رسمی - ' . $office->name)

@section('content')
@php
    $typeLabels = [
        'incoming_letter' => 'نامه وارده',
        'outgoing_letter' => 'نامه صادره',
        'internal_correspondence' => 'مکاتبه داخلی',
        'meeting_minute' => 'صورتجلسه',
        'resolution' => 'مصوبه',
        'formal_decision' => 'تصمیم رسمی',
        'contract' => 'قرارداد',
        'memorandum_of_understanding' => 'تفاهم‌نامه',
        'agreement' => 'توافق‌نامه',
        'policy' => 'سند بنیادین / سیاست',
        'directive' => 'دستورالعمل',
        'official_report' => 'گزارش رسمی',
        'notice' => 'اطلاعیه رسمی',
        'official_note' => 'یادداشت رسمی',
        'financial_record' => 'سند مالی',
        'execution_record' => 'سند اجرایی',
        'election_record' => 'سند انتخاباتی',
        'case_record' => 'سند پرونده',
        'other' => 'سایر',
    ];
    $directionLabels = ['incoming' => 'وارده', 'outgoing' => 'صادره', 'internal' => 'داخلی', 'none' => 'بدون جهت'];
    $selectedType = old('record_type', request('record_type', 'policy'));
@endphp

<div class="container mx-auto px-4 py-6 max-w-4xl">
    <div class="mb-6">
        <a href="{{ route('secretariat.index', $office) }}" class="text-sm text-emerald-700 hover:underline">
            <i class="fa-solid fa-arrow-right ml-1"></i> بازگشت به داشبورد دبیرخانه
        </a>
        <h1 class="text-2xl font-bold mt-3">ثبت سند رسمی جدید</h1>
        <p class="text-sm text-gray-500 mt-1">{{ $office->name }} — در این مرحله سند به‌صورت پیش‌نویس ایجاد می‌شود؛ سپس باید برای تأیید ارسال و پس از تأیید با شماره رسمی ثبت شود.</p>
    </div>

    <div class="rounded-2xl border border-amber-200 bg-amber-50 text-amber-900 p-4 mb-5 text-sm">
        <strong>برای اسناد بنیادین EarthCoop:</strong> نوع «سند بنیادین / سیاست» را انتخاب کنید، عنوان و متن canonical را وارد یا فایل اصلی را پیوست کنید. ثبت اولیه به معنی نافذشدن خودکار نیست؛ lifecycle رسمی دبیرخانه حفظ می‌شود.
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
                        <option value="{{ $type }}" @selected($selectedType === $type)>{{ $typeLabels[$type] ?? $type }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-2">جهت</label>
                <select name="direction" required class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                    @foreach($directions as $direction)
                        <option value="{{ $direction }}" @selected(old('direction', 'none') === $direction)>{{ $directionLabels[$direction] ?? $direction }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium mb-2">عنوان رسمی سند</label>
            <input name="title" value="{{ old('title') }}" required maxlength="500" placeholder="مثلاً: اساسنامه بین‌المللی سامانه شراکتی EarthCoop"
                   class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
        </div>

        <div>
            <label class="block text-sm font-medium mb-2">موضوع</label>
            <input name="subject" value="{{ old('subject') }}" maxlength="1000" placeholder="موضوع یا دامنه حقوقی/سازمانی سند"
                   class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
        </div>

        <div>
            <label class="block text-sm font-medium mb-2">خلاصه</label>
            <textarea name="summary" rows="3" maxlength="5000" placeholder="شرح کوتاه برای بازیابی و ارجاع مدیریتی"
                      class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">{{ old('summary') }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-medium mb-2">متن canonical سند</label>
            <textarea name="body" rows="12" placeholder="متن کامل سند را اینجا درج کنید؛ اگر فایل اصلی مبناست، می‌توانید متن خلاصه/مرجع را وارد و فایل را نیز پیوست کنید."
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
                <p class="text-xs text-gray-500 mt-1">برای اسناد بنیادین قابل انتشار معمولاً public مناسب است؛ restricted/confidential نیازمند ACL صریح است.</p>
            </div>
            <div>
                <label class="block text-sm font-medium mb-2">فایل اصلی یا پیوست اولیه (اختیاری)</label>
                <input type="file" name="attachment" class="w-full rounded-xl border border-gray-300 p-2 dark:bg-gray-900 dark:border-gray-700">
                <p class="text-xs text-gray-500 mt-1">حداکثر ۲۰ مگابایت؛ checksum SHA-256 هنگام ثبت محاسبه می‌شود.</p>
            </div>
        </div>

        <div class="rounded-xl bg-slate-50 dark:bg-gray-900/50 border p-4 text-sm text-gray-600 dark:text-gray-300">
            <strong>مرحله بعد:</strong> پس از ایجاد پیش‌نویس، صفحه خود سند باز می‌شود. از همان صفحه آن را برای تأیید ارسال می‌کنید و مدیر مجاز پس از بررسی، «ثبت رسمی» را انجام می‌دهد تا شماره ثبت canonical صادر شود.
        </div>

        <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end pt-3">
            <a href="{{ route('secretariat.index', $office) }}" class="rounded-xl border px-5 py-3 text-center">انصراف</a>
            <button class="rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3">
                ایجاد پیش‌نویس سند
            </button>
        </div>
    </form>
</div>
@endsection
