@extends('layouts.unified')

@section('title', 'داشبورد دبیرخانه - ' . $office->name)

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
    $isCentral = $office->office_type === 'central';
@endphp

<div class="container mx-auto px-4 py-6 max-w-7xl">
    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 mb-5">{{ session('success') }}</div>
    @endif

    <div class="rounded-3xl border border-emerald-100 bg-gradient-to-l from-emerald-50 via-white to-sky-50 dark:from-gray-800 dark:via-gray-900 dark:to-gray-800 p-6 mb-6 shadow-sm">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <span class="text-xs font-semibold rounded-full bg-white/80 border px-3 py-1 text-gray-600">{{ $isCentral ? 'دفتر مرکزی EarthCoop' : 'دفتر گروه' }}</span>
                    @if($canManageOffice)
                        <span class="text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800 px-3 py-1">حالت مدیریت</span>
                    @elseif($canInspectOffice)
                        <span class="text-xs font-semibold rounded-full bg-amber-100 text-amber-800 px-3 py-1">حالت بازرسی</span>
                    @else
                        <span class="text-xs font-semibold rounded-full bg-slate-100 text-slate-700 px-3 py-1">حالت مشاهده</span>
                    @endif
                </div>
                <p class="text-sm text-gray-500">کد دفتر: <span class="font-mono">{{ $office->code }}</span></p>
                <h1 class="text-3xl font-black text-gray-900 dark:text-gray-100 mt-1">داشبورد دبیرخانه</h1>
                <h2 class="text-lg font-bold text-emerald-700 mt-2">{{ $office->name }}</h2>
                <p class="text-sm text-gray-500 mt-2 max-w-3xl">مرکز عملیات ثبت رسمی، مکاتبات، پرونده‌ها، بایگانی و پیگیری اسناد این دفتر. ثبت رسمی همچنان از چرخه کنترل‌شده پیش‌نویس → تأیید → شماره ثبت عبور می‌کند.</p>
            </div>

            <div class="flex flex-wrap gap-2 lg:max-w-xl lg:justify-end">
                <a href="{{ route('secretariat.cases.index', $office) }}" class="inline-flex items-center gap-2 rounded-xl border bg-white px-4 py-2.5 hover:bg-gray-50">
                    <i class="fa-solid fa-folder-tree text-indigo-600"></i> پرونده‌ها
                </a>
                @if($canInspectOffice)
                    <a href="{{ route('secretariat.records.create', ['office' => $office, 'record_type' => 'policy']) }}" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-white hover:bg-emerald-700">
                        <i class="fa-solid fa-file-circle-plus"></i> ثبت سند رسمی
                    </a>
                    <a href="{{ route('secretariat.correspondence.create', ['office' => $office, 'direction' => 'incoming']) }}" class="inline-flex items-center gap-2 rounded-xl border border-emerald-500 bg-white px-4 py-2.5 text-emerald-700 hover:bg-emerald-50">
                        <i class="fa-solid fa-inbox"></i> نامه وارده
                    </a>
                    <a href="{{ route('secretariat.correspondence.create', ['office' => $office, 'direction' => 'outgoing']) }}" class="inline-flex items-center gap-2 rounded-xl border border-sky-500 bg-white px-4 py-2.5 text-sky-700 hover:bg-sky-50">
                        <i class="fa-solid fa-paper-plane"></i> نامه صادره
                    </a>
                    <a href="{{ route('secretariat.correspondence.create', ['office' => $office, 'direction' => 'internal']) }}" class="inline-flex items-center gap-2 rounded-xl border bg-white px-4 py-2.5 hover:bg-gray-50">
                        <i class="fa-solid fa-right-left"></i> مکاتبه داخلی
                    </a>
                    <a href="{{ route('secretariat.cases.create', $office) }}" class="inline-flex items-center gap-2 rounded-xl border bg-white px-4 py-2.5 hover:bg-gray-50">
                        <i class="fa-solid fa-folder-plus"></i> پرونده جدید
                    </a>
                @endif
                @if($canManageOffice)
                    <a href="{{ route('secretariat.settings.edit', $office) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-400 bg-white px-4 py-2.5 text-slate-700 hover:bg-slate-50">
                        <i class="fa-solid fa-sliders"></i> تنظیمات دبیرخانه
                    </a>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
        <div class="rounded-2xl bg-white dark:bg-gray-800 shadow-sm border p-4"><div class="text-xs text-gray-500">کل رکوردهای قابل مشاهده</div><div class="text-3xl font-black mt-2">{{ $dashboard['visible_records'] }}</div></div>
        <div class="rounded-2xl bg-white dark:bg-gray-800 shadow-sm border p-4"><div class="text-xs text-gray-500">پیش‌نویس‌ها</div><div class="text-3xl font-black mt-2 text-slate-700">{{ $counts['draft'] }}</div></div>
        <div class="rounded-2xl bg-white dark:bg-gray-800 shadow-sm border p-4"><div class="text-xs text-gray-500">منتظر تأیید</div><div class="text-3xl font-black mt-2 text-amber-600">{{ $counts['pending_approval'] }}</div></div>
        <div class="rounded-2xl bg-white dark:bg-gray-800 shadow-sm border p-4"><div class="text-xs text-gray-500">ثبت رسمی</div><div class="text-3xl font-black mt-2 text-emerald-600">{{ $counts['registered'] }}</div></div>
        <div class="rounded-2xl bg-white dark:bg-gray-800 shadow-sm border p-4"><div class="text-xs text-gray-500">مکاتبات</div><div class="text-3xl font-black mt-2 text-sky-600">{{ $dashboard['correspondence'] }}</div></div>
        <div class="rounded-2xl bg-white dark:bg-gray-800 shadow-sm border p-4"><div class="text-xs text-gray-500">پرونده‌های باز</div><div class="text-3xl font-black mt-2 text-indigo-600">{{ $dashboard['open_cases'] }}</div></div>
    </div>

    @if($canInspectOffice)
        <div class="rounded-2xl border bg-white dark:bg-gray-800 p-5 mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <h3 class="font-bold text-lg">کار فوری دبیرخانه</h3>
                    @if($isCentral)
                        <p class="text-sm text-gray-500 mt-1">برای اسناد بنیادین EarthCoop از «ثبت سند رسمی» استفاده کنید و نوع سند را «سند بنیادین / سیاست» انتخاب کنید.</p>
                    @else
                        <p class="text-sm text-gray-500 mt-1">برای ثبت مصوبات، تصمیم‌ها، صورت‌جلسه‌ها و سایر امور رسمی این گروه از «ثبت سند رسمی» استفاده کنید؛ مکاتبات و پرونده‌های گروه نیز از همین داشبورد قابل پیگیری‌اند.</p>
                    @endif
                </div>
                <a href="{{ route('secretariat.records.create', ['office' => $office, 'record_type' => 'policy']) }}" class="rounded-xl bg-gray-900 text-white px-5 py-3 text-center whitespace-nowrap">شروع ثبت سند</a>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 mb-6">
        <section class="rounded-2xl bg-white dark:bg-gray-800 border shadow-sm overflow-hidden">
            <div class="p-4 border-b flex items-center justify-between"><h3 class="font-bold">آخرین اسناد و مکاتبات</h3><span class="text-xs text-gray-500">آخرین موارد مجاز</span></div>
            @forelse($recentRecords as $record)
                @php $isCorrespondence = in_array($record->record_type, ['incoming_letter', 'outgoing_letter', 'internal_correspondence'], true); @endphp
                <a href="{{ $isCorrespondence ? route('secretariat.correspondence.show', [$office, $record]) : route('secretariat.records.show', [$office, $record]) }}" class="block p-4 border-b last:border-0 hover:bg-gray-50 dark:hover:bg-gray-900/50">
                    <div class="flex items-center justify-between gap-3"><span class="font-semibold truncate">{{ $record->title }}</span><span class="text-xs rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-1 whitespace-nowrap">{{ $statusLabels[$record->status] ?? $record->status }}</span></div>
                    <div class="text-xs text-gray-500 mt-1">{{ $record->registry_number ?: 'هنوز بدون شماره ثبت' }} · {{ $record->record_type }}</div>
                </a>
            @empty
                <div class="p-8 text-center text-gray-500">هنوز سندی در این دفتر ثبت نشده است.</div>
            @endforelse
        </section>

        <section class="rounded-2xl bg-white dark:bg-gray-800 border shadow-sm overflow-hidden">
            <div class="p-4 border-b flex items-center justify-between"><h3 class="font-bold">پرونده‌های اخیر</h3><a href="{{ route('secretariat.cases.index', $office) }}" class="text-xs text-emerald-700">مشاهده همه</a></div>
            @forelse($recentCases as $case)
                <a href="{{ route('secretariat.cases.show', [$office, $case]) }}" class="block p-4 border-b last:border-0 hover:bg-gray-50 dark:hover:bg-gray-900/50">
                    <div class="flex items-center justify-between gap-3"><span class="font-semibold truncate">{{ $case->title }}</span><span class="text-xs rounded-full bg-indigo-50 text-indigo-700 px-2 py-1">{{ $case->status }}</span></div>
                    <div class="text-xs text-gray-500 mt-1">{{ $case->case_number ?: 'پرونده بدون شماره نمایشی' }}</div>
                </a>
            @empty
                <div class="p-8 text-center text-gray-500">هنوز پرونده‌ای در این دفتر وجود ندارد.</div>
            @endforelse
        </section>
    </div>

    <form method="GET" action="{{ route('secretariat.index', $office) }}" class="rounded-2xl bg-white dark:bg-gray-800 shadow-sm border p-5 mb-6">
        <div class="flex items-center justify-between gap-3 mb-4"><h3 class="font-bold">جست‌وجو در دفتر ثبت</h3><span class="text-xs text-gray-500">Policy-aware retrieval</span></div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <input type="text" name="registry_number" value="{{ request('registry_number') }}" placeholder="شماره ثبت" class="rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
            <input type="text" name="title" value="{{ request('title') }}" placeholder="عنوان یا موضوع" class="rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
            <select name="record_type" class="rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700"><option value="">همه انواع</option>@foreach($recordTypes as $type)<option value="{{ $type }}" @selected(request('record_type') === $type)>{{ $type }}</option>@endforeach</select>
            <select name="status" class="rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700"><option value="">همه وضعیت‌ها</option>@foreach($statusLabels as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
        </div>
        <div class="mt-4 flex gap-2"><button class="rounded-xl bg-gray-900 dark:bg-gray-100 dark:text-gray-900 px-5 py-2.5 text-white">جست‌وجو</button><a href="{{ route('secretariat.index', $office) }}" class="rounded-xl border px-5 py-2.5">پاک‌کردن فیلتر</a></div>
    </form>

    <div class="rounded-2xl bg-white dark:bg-gray-800 shadow-sm border overflow-hidden">
        <div class="p-4 border-b"><h3 class="font-bold">فهرست دفتر ثبت</h3></div>
        @forelse($records as $record)
            @php $isCorrespondence = in_array($record->record_type, ['incoming_letter', 'outgoing_letter', 'internal_correspondence'], true); @endphp
            <a href="{{ $isCorrespondence ? route('secretariat.correspondence.show', [$office, $record]) : route('secretariat.records.show', [$office, $record]) }}" class="block border-b last:border-b-0 p-4 hover:bg-gray-50 dark:hover:bg-gray-900/50">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div class="min-w-0"><div class="flex flex-wrap items-center gap-2 mb-1"><span class="font-bold">{{ $record->title }}</span><span class="text-xs rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-1">{{ $record->record_type }}</span>@if(in_array($record->confidentiality, ['restricted', 'confidential'], true))<span class="text-xs rounded-full bg-amber-100 text-amber-800 px-2 py-1"><i class="fa-solid fa-lock ml-1"></i>{{ $record->confidentiality }}</span>@endif</div><p class="text-sm text-gray-500 truncate">{{ $record->subject ?: $record->summary ?: 'بدون توضیح تکمیلی' }}</p></div>
                    <div class="flex items-center gap-3 shrink-0 text-sm">@if($record->registry_number)<span class="font-mono rounded-lg bg-emerald-50 text-emerald-700 px-2 py-1">{{ $record->registry_number }}</span>@endif<span class="rounded-lg bg-gray-100 dark:bg-gray-700 px-2 py-1">{{ $statusLabels[$record->status] ?? $record->status }}</span></div>
                </div>
            </a>
        @empty
            <div class="p-10 text-center text-gray-500"><i class="fa-regular fa-folder-open text-3xl mb-3"></i><p>سندی مطابق این فیلترها یافت نشد.</p></div>
        @endforelse
    </div>

    <p class="mt-3 text-xs text-gray-500">داشبورد حداکثر ۲۰۰ رکورد اخیر قابل مشاهده را برای شاخص‌های سریع ارزیابی می‌کند؛ فهرست جست‌وجو حداکثر ۱۰۰ نتیجه مجاز را نشان می‌دهد و هر نتیجه از Policy دبیرخانه عبور می‌کند.</p>
</div>
@endsection