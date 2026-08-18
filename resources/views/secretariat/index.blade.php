@extends('layouts.unified')

@section('title', 'دبیرخانه - ' . $office->name)

@section('content')
@php
    $statusLabels = [
        'draft' => 'پیش‌نویس',
        'pending_approval' => 'منتظر تأیید',
        'registered' => 'ثبت‌شده',
        'active' => 'فعال',
        'closed' => 'مختومه',
        'archived' => 'بایگانی',
        'rejected' => 'ردشده',
        'cancelled' => 'لغوشده',
        'superseded' => 'جایگزین‌شده',
        'voided' => 'باطل‌شده',
    ];
@endphp

<div class="container mx-auto px-4 py-6 max-w-7xl">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <p class="text-sm text-gray-500 mb-1">دفتر ثبت {{ $office->code }}</p>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $office->name }}</h1>
            <p class="text-sm text-gray-500 mt-2">ثبت رسمی، نسخه‌ها، پیوست‌ها و روابط اسناد این دفتر</p>
        </div>
        @can('inspect', $office)
            <a href="{{ route('secretariat.records.create', $office) }}"
               class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-white hover:bg-emerald-700">
                <i class="fa-solid fa-plus"></i>
                سند جدید
            </a>
        @endcan
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="rounded-2xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 p-5">
            <div class="text-sm text-gray-500">پیش‌نویس‌های قابل مشاهده</div>
            <div class="text-3xl font-bold mt-2">{{ $counts['draft'] }}</div>
        </div>
        <div class="rounded-2xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 p-5">
            <div class="text-sm text-gray-500">منتظر تأیید</div>
            <div class="text-3xl font-bold mt-2">{{ $counts['pending_approval'] }}</div>
        </div>
        <div class="rounded-2xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 p-5">
            <div class="text-sm text-gray-500">رکوردهای رسمی</div>
            <div class="text-3xl font-bold mt-2">{{ $counts['registered'] }}</div>
        </div>
    </div>

    <form method="GET" action="{{ route('secretariat.index', $office) }}"
          class="rounded-2xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 p-5 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <input type="text" name="registry_number" value="{{ request('registry_number') }}"
                   placeholder="شماره ثبت"
                   class="rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
            <input type="text" name="title" value="{{ request('title') }}"
                   placeholder="عنوان یا موضوع"
                   class="rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
            <select name="record_type" class="rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                <option value="">همه انواع</option>
                @foreach($recordTypes as $type)
                    <option value="{{ $type }}" @selected(request('record_type') === $type)>{{ $type }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                <option value="">همه وضعیت‌ها</option>
                @foreach($statusLabels as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ request('date_from') }}"
                   class="rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
            <input type="date" name="date_to" value="{{ request('date_to') }}"
                   class="rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
        </div>
        <div class="mt-4 flex gap-2">
            <button class="rounded-xl bg-gray-900 dark:bg-gray-100 dark:text-gray-900 px-5 py-2.5 text-white">جست‌وجو</button>
            <a href="{{ route('secretariat.index', $office) }}" class="rounded-xl border px-5 py-2.5">پاک‌کردن فیلتر</a>
        </div>
    </form>

    <div class="rounded-2xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        @forelse($records as $record)
            <a href="{{ route('secretariat.records.show', [$office, $record]) }}"
               class="block border-b last:border-b-0 border-gray-100 dark:border-gray-700 p-4 hover:bg-gray-50 dark:hover:bg-gray-900/50">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <span class="font-bold text-gray-900 dark:text-gray-100">{{ $record->title }}</span>
                            <span class="text-xs rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-1">{{ $record->record_type }}</span>
                            @if(in_array($record->confidentiality, ['restricted', 'confidential'], true))
                                <span class="text-xs rounded-full bg-amber-100 text-amber-800 px-2 py-1">
                                    <i class="fa-solid fa-lock ml-1"></i>{{ $record->confidentiality }}
                                </span>
                            @endif
                        </div>
                        <p class="text-sm text-gray-500 truncate">{{ $record->subject ?: $record->summary ?: 'بدون توضیح تکمیلی' }}</p>
                    </div>
                    <div class="flex items-center gap-3 shrink-0 text-sm">
                        @if($record->registry_number)
                            <span class="font-mono rounded-lg bg-emerald-50 text-emerald-700 px-2 py-1">{{ $record->registry_number }}</span>
                        @endif
                        <span class="rounded-lg bg-gray-100 dark:bg-gray-700 px-2 py-1">{{ $statusLabels[$record->status] ?? $record->status }}</span>
                    </div>
                </div>
            </a>
        @empty
            <div class="p-10 text-center text-gray-500">
                <i class="fa-regular fa-folder-open text-3xl mb-3"></i>
                <p>سندی مطابق این فیلترها یافت نشد.</p>
            </div>
        @endforelse
    </div>

    <p class="mt-3 text-xs text-gray-500">این جست‌وجوی سریع S2 حداکثر ۱۰۰ نتیجه مجاز را نمایش می‌دهد و هر نتیجه قبل از نمایش از Policy دبیرخانه عبور می‌کند.</p>
</div>
@endsection
