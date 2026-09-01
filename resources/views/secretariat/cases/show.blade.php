@extends('layouts.unified')

@section('title', $case->title . ' - پرونده دبیرخانه')

@section('content')
<div class="container mx-auto px-4 py-6 max-w-7xl">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between mb-6">
        <div>
            <a href="{{ route('secretariat.cases.index', $office) }}" class="text-sm text-emerald-700 hover:underline">بازگشت به پرونده‌ها</a>
            <div class="flex flex-wrap gap-2 items-center mt-3">
                <h1 class="text-2xl font-bold">{{ $case->title }}</h1>
                <span class="font-mono text-xs rounded-lg bg-emerald-50 text-emerald-700 px-2 py-1">{{ $case->case_number }}</span>
                <span class="text-xs rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-1">{{ $case->status }}</span>
                <span class="text-xs rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-1">{{ $case->confidentiality }}</span>
            </div>
            @if($case->summary)<p class="mt-3 text-gray-600 dark:text-gray-300 max-w-4xl">{{ $case->summary }}</p>@endif
        </div>

        @can('manage', $case)
            <form method="POST" action="{{ route('secretariat.cases.transition', [$office, $case]) }}" class="flex gap-2 items-center">
                @csrf
                <select name="status" required class="rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                    @foreach(['open', 'on_hold', 'closed', 'archived'] as $status)
                        <option value="{{ $status }}" @selected($case->status === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                <button class="rounded-xl bg-gray-900 dark:bg-gray-100 dark:text-gray-900 text-white px-4 py-2.5">تغییر وضعیت</button>
            </form>
        @endcan
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <section class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm p-6">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h2 class="font-bold text-lg">رکوردهای قابل مشاهده در این پرونده</h2>
                    <span class="text-sm text-gray-500">{{ $visibleRecords->count() }}</span>
                </div>
                <div class="space-y-3">
                    @forelse($visibleRecords as $record)
                        <a href="{{ route('secretariat.records.show', [$record->office, $record]) }}" class="block rounded-xl border border-gray-200 dark:border-gray-700 p-4 hover:bg-gray-50 dark:hover:bg-gray-900/50">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <strong>{{ $record->title }}</strong>
                                        @if($record->pivot->link_type === 'cross_office_reference')
                                            <span class="text-xs rounded-full bg-indigo-100 text-indigo-800 px-2 py-1">ارجاع از دفتر {{ $record->office->code }}</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">{{ $record->record_type }} · {{ $record->pivot->role }}</div>
                                </div>
                                <span class="font-mono text-xs text-emerald-700">{{ $record->registry_number }}</span>
                            </div>
                        </a>
                    @empty
                        <p class="text-sm text-gray-500">هنوز رکوردی که اجازه مشاهده‌اش را داشته باشید در پرونده نیست.</p>
                    @endforelse
                </div>

                @can('manage', $case)
                    @if($case->status !== 'archived' && $linkableRecords->isNotEmpty())
                        <form method="POST" action="{{ route('secretariat.cases.records.store', [$office, $case]) }}" class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-3 rounded-xl bg-gray-50 dark:bg-gray-900 p-4">
                            @csrf
                            <div class="md:col-span-3 font-medium">افزودن رکورد همین دفتر</div>
                            <select name="record_id" required class="rounded-xl border-gray-300 dark:bg-gray-800 dark:border-gray-700 md:col-span-2">
                                @foreach($linkableRecords as $record)
                                    <option value="{{ $record->id }}">{{ $record->registry_number }} — {{ $record->title }}</option>
                                @endforeach
                            </select>
                            <select name="role" required class="rounded-xl border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                                @foreach($roles as $role)<option value="{{ $role }}">{{ $role }}</option>@endforeach
                            </select>
                            <button class="md:col-span-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5">افزودن رکورد رسمی به پرونده</button>
                        </form>
                    @endif

                    @if($case->status !== 'archived' && $referenceOffices->isNotEmpty())
                        <form method="POST" action="{{ route('secretariat.cases.references.store', [$office, $case]) }}" class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-3 rounded-xl border border-indigo-100 bg-indigo-50/50 dark:bg-indigo-950/20 dark:border-indigo-900 p-4">
                            @csrf
                            <div class="md:col-span-4">
                                <div class="font-medium">ارجاع رکورد رسمی از دفتر دیگر</div>
                                <p class="text-xs text-gray-500 mt-1">هیچ کپی‌ای ساخته نمی‌شود؛ سند در دفتر مبدأ منبع حقیقت می‌ماند.</p>
                            </div>
                            <select name="source_office_id" required class="rounded-xl border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                                @foreach($referenceOffices as $candidateOffice)
                                    <option value="{{ $candidateOffice->id }}">{{ $candidateOffice->code }} — {{ $candidateOffice->name }}</option>
                                @endforeach
                            </select>
                            <input name="registry_number" required maxlength="255" placeholder="شماره ثبت دقیق" class="rounded-xl border-gray-300 dark:bg-gray-800 dark:border-gray-700 md:col-span-2">
                            <select name="role" required class="rounded-xl border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                                @foreach($roles as $role)<option value="{{ $role }}">{{ $role }}</option>@endforeach
                            </select>
                            <button class="md:col-span-4 rounded-xl bg-indigo-700 hover:bg-indigo-800 text-white px-4 py-2.5">ثبت ارجاع بین‌دفتری</button>
                        </form>
                    @endif
                @endcan
            </section>
        </div>

        <aside class="space-y-6">
            <section class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm p-5">
                <h2 class="font-bold mb-4">مشخصات پرونده</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">دفتر</dt><dd>{{ $office->code }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">ایجاد</dt><dd>{{ optional($case->created_at)->format('Y-m-d H:i') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">سازنده</dt><dd>{{ optional($case->createdBy)->email ?: ('#' . $case->created_by) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">اختتام</dt><dd>{{ optional($case->closed_at)->format('Y-m-d H:i') ?: '—' }}</dd></div>
                </dl>
            </section>
            <section class="rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-100 dark:border-amber-900 p-5 text-sm leading-7">
                این پرونده container حقیقت نیست؛ رکوردهای رسمی در Office خودشان منبع حقیقت باقی می‌مانند و نمایش هر عضو پرونده همچنان از Policy همان Record عبور می‌کند.
            </section>
        </aside>
    </div>
</div>
@endsection
