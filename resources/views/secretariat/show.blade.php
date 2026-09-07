@extends('layouts.unified')

@section('title', $record->title . ' - دبیرخانه')

@section('content')
@php
    $statusLabels = [
        'draft' => 'پیش‌نویس', 'pending_approval' => 'منتظر تأیید', 'registered' => 'ثبت‌شده',
        'active' => 'فعال', 'closed' => 'مختومه', 'archived' => 'بایگانی', 'rejected' => 'ردشده',
        'cancelled' => 'لغوشده', 'superseded' => 'جایگزین‌شده', 'voided' => 'باطل‌شده',
    ];
@endphp

<div class="container mx-auto px-4 py-6 max-w-7xl">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between mb-6">
        <div class="min-w-0">
            <a href="{{ route('secretariat.index', $office) }}" class="text-sm text-emerald-700 hover:underline">
                <i class="fa-solid fa-arrow-right ml-1"></i> بازگشت به دبیرخانه
            </a>
            <div class="flex flex-wrap items-center gap-2 mt-3 mb-2">
                <h1 class="text-2xl font-bold">{{ $record->title }}</h1>
                <span class="text-xs rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-1">{{ $record->record_type }}</span>
                <span class="text-xs rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-1">{{ $statusLabels[$record->status] ?? $record->status }}</span>
                @if(in_array($record->confidentiality, ['restricted', 'confidential'], true))
                    <span class="text-xs rounded-full bg-amber-100 text-amber-800 px-2 py-1"><i class="fa-solid fa-lock ml-1"></i>{{ $record->confidentiality }}</span>
                @endif
            </div>
            <p class="text-sm text-gray-500">{{ $record->subject ?: 'بدون موضوع مستقل' }}</p>
            @if($record->registry_number)
                <div class="mt-3 inline-flex items-center gap-2 rounded-xl bg-emerald-50 text-emerald-800 px-3 py-2">
                    <span class="text-xs">شماره ثبت</span>
                    <strong class="font-mono">{{ $record->registry_number }}</strong>
                </div>
            @endif
        </div>

        <div class="flex flex-wrap gap-2">
            @can('submitForApproval', $record)
                <form method="POST" action="{{ route('secretariat.records.submit', [$office, $record]) }}">@csrf
                    <button class="rounded-xl bg-amber-500 hover:bg-amber-600 text-white px-4 py-2.5">ارسال برای تأیید</button>
                </form>
            @endcan
            @can('register', $record)
                <form method="POST" action="{{ route('secretariat.records.register', [$office, $record]) }}">@csrf
                    <button class="rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5">تأیید و ثبت رسمی</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <section class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm p-6">
                <h2 class="font-bold text-lg mb-4">نسخه جاری</h2>
                @if($record->currentVersion)
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <div class="text-sm text-gray-500">نسخه {{ $record->currentVersion->version_number }}</div>
                        @if($record->currentVersion->is_official)
                            <span class="text-xs rounded-full bg-emerald-100 text-emerald-800 px-2 py-1">نسخه رسمی</span>
                        @else
                            <span class="text-xs rounded-full bg-gray-100 px-2 py-1">غیررسمی</span>
                        @endif
                    </div>
                    @if($record->summary)<p class="mb-4 text-gray-600 dark:text-gray-300">{{ $record->summary }}</p>@endif
                    <div class="whitespace-pre-wrap leading-8 text-gray-900 dark:text-gray-100">{{ $record->currentVersion->body ?: 'متنی برای این نسخه ثبت نشده است.' }}</div>
                @endif
            </section>

            <section class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-bold text-lg">نسخه‌ها و پیوست‌ها</h2>
                    <span class="text-sm text-gray-500">{{ $record->versions->count() }} نسخه</span>
                </div>
                <div class="space-y-4">
                    @foreach($record->versions as $version)
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                                <div>
                                    <strong>نسخه {{ $version->version_number }}</strong>
                                    <span class="text-xs text-gray-500 mr-2">{{ $version->change_reason }}</span>
                                </div>
                                <div class="text-xs text-gray-500">{{ optional($version->created_at)->format('Y-m-d H:i') }}</div>
                            </div>
                            @if($version->attachments->isNotEmpty())
                                <div class="space-y-2">
                                    @foreach($version->attachments as $attachment)
                                        <a href="{{ route('secretariat.attachments.download', [$office, $record, $attachment]) }}"
                                           class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 dark:bg-gray-900 px-3 py-2 hover:bg-gray-100">
                                            <span class="truncate"><i class="fa-solid fa-paperclip ml-2"></i>{{ $attachment->original_name }}</span>
                                            <span class="text-xs text-gray-500 shrink-0">{{ number_format($attachment->file_size / 1024, 1) }} KB</span>
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-xs text-gray-400">بدون پیوست</div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @can('update', $record)
                    <form method="POST" enctype="multipart/form-data" action="{{ route('secretariat.attachments.store', [$office, $record]) }}"
                          class="mt-5 rounded-xl bg-gray-50 dark:bg-gray-900 p-4 flex flex-col sm:flex-row gap-3 sm:items-end">
                        @csrf
                        <div class="flex-1">
                            <label class="block text-sm font-medium mb-2">افزودن پیوست به نسخه جاری</label>
                            <input type="file" name="attachment" required class="w-full rounded-lg border border-gray-300 p-2 dark:bg-gray-800 dark:border-gray-700">
                        </div>
                        <button class="rounded-xl bg-gray-900 dark:bg-gray-100 dark:text-gray-900 text-white px-4 py-2.5">ثبت پیوست</button>
                    </form>
                @endcan
            </section>

            <section class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm p-6">
                <h2 class="font-bold text-lg mb-4">روابط ثبتی</h2>
                <div class="space-y-2 mb-5">
                    @foreach($record->outgoingRelations as $relation)
                        <a href="{{ route('secretariat.records.show', [$office, $relation->targetRecord]) }}" class="block rounded-xl border border-gray-200 dark:border-gray-700 p-3 hover:bg-gray-50">
                            <span class="text-xs text-emerald-700 ml-2">{{ $relation->relation_type }} →</span>
                            <strong>{{ $relation->targetRecord->title }}</strong>
                        </a>
                    @endforeach
                    @foreach($record->incomingRelations as $relation)
                        <a href="{{ route('secretariat.records.show', [$office, $relation->sourceRecord]) }}" class="block rounded-xl border border-gray-200 dark:border-gray-700 p-3 hover:bg-gray-50">
                            <span class="text-xs text-blue-700 ml-2">← {{ $relation->relation_type }}</span>
                            <strong>{{ $relation->sourceRecord->title }}</strong>
                        </a>
                    @endforeach
                    @if($record->outgoingRelations->isEmpty() && $record->incomingRelations->isEmpty())
                        <p class="text-sm text-gray-500">هنوز رابطه‌ای ثبت نشده است.</p>
                    @endif
                </div>

                @can('transition', $record)
                    @if($linkableRecords->isNotEmpty())
                        <form method="POST" action="{{ route('secretariat.relations.store', [$office, $record]) }}" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            @csrf
                            <select name="relation_type" required class="rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                                @foreach($relationTypes as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach
                            </select>
                            <select name="target_record_id" required class="rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700 md:col-span-1">
                                @foreach($linkableRecords as $candidate)
                                    <option value="{{ $candidate->id }}">{{ $candidate->registry_number ?: '#' . $candidate->id }} — {{ $candidate->title }}</option>
                                @endforeach
                            </select>
                            <button class="rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5">افزودن رابطه</button>
                        </form>
                    @endif
                @endcan
            </section>
        </div>

        <aside class="space-y-6">
            <section class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm p-5">
                <h2 class="font-bold mb-4">مشخصات ثبتی</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">دفتر</dt><dd>{{ $office->code }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">جهت</dt><dd>{{ $record->direction }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">محرمانگی</dt><dd>{{ $record->confidentiality }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">ثبت</dt><dd>{{ optional($record->registered_at)->format('Y-m-d H:i') ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">نسخه جاری</dt><dd>{{ optional($record->currentVersion)->version_number ?: '—' }}</dd></div>
                </dl>
            </section>

            <section class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-bold">Timeline ممیزی</h2>
                    <span class="text-xs text-gray-500">{{ $record->auditEvents->count() }}</span>
                </div>
                <div class="space-y-4 max-h-[700px] overflow-y-auto pl-1">
                    @foreach($record->auditEvents->sortByDesc('event_at') as $event)
                        <div class="relative pr-5 border-r border-gray-200 dark:border-gray-700">
                            <span class="absolute -right-1.5 top-1.5 w-3 h-3 rounded-full bg-emerald-500"></span>
                            <div class="text-sm font-medium">{{ $event->event_type }}</div>
                            <div class="text-xs text-gray-500 mt-1">
                                {{ trim((string) optional($event->actor)->first_name . ' ' . (string) optional($event->actor)->last_name) ?: optional($event->actor)->email ?: 'سیستم' }}
                            </div>
                            <div class="text-xs text-gray-400 mt-1">{{ optional($event->event_at)->format('Y-m-d H:i:s') }}</div>
                        </div>
                    @endforeach
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection
