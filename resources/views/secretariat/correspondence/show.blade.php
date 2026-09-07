@extends('layouts.unified')

@section('title', $record->title . ' - مکاتبات دبیرخانه')

@section('content')
@php
    $detail = $record->correspondenceDetail;
    $statusLabels = [
        'draft' => 'پیش‌نویس', 'pending_approval' => 'منتظر تأیید', 'registered' => 'ثبت‌شده',
        'active' => 'فعال', 'closed' => 'مختومه', 'archived' => 'بایگانی', 'cancelled' => 'لغوشده',
    ];
    $directionLabels = ['incoming' => 'وارده', 'outgoing' => 'صادره', 'internal' => 'داخلی'];
    $dispatchStatusLabels = [
        'pending' => 'منتظر ارسال', 'sent' => 'ارسال‌شده', 'received' => 'دریافت‌شده',
        'acknowledged' => 'تأیید دریافت', 'completed' => 'تکمیل‌شده', 'failed' => 'ناموفق', 'cancelled' => 'لغوشده',
    ];
@endphp

<div class="container mx-auto px-4 py-6 max-w-7xl">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between mb-6">
        <div>
            <a href="{{ route('secretariat.index', $office) }}" class="text-sm text-emerald-700 hover:underline">
                <i class="fa-solid fa-arrow-right ml-1"></i> بازگشت به دبیرخانه
            </a>
            <div class="flex flex-wrap items-center gap-2 mt-3">
                <h1 class="text-2xl font-bold">{{ $record->title }}</h1>
                <span class="text-xs rounded-full bg-sky-100 text-sky-800 px-2 py-1">{{ $directionLabels[$record->direction] ?? $record->direction }}</span>
                <span class="text-xs rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-1">{{ $statusLabels[$record->status] ?? $record->status }}</span>
                @if(in_array($record->confidentiality, ['restricted', 'confidential'], true))
                    <span class="text-xs rounded-full bg-amber-100 text-amber-800 px-2 py-1"><i class="fa-solid fa-lock ml-1"></i>{{ $record->confidentiality }}</span>
                @endif
            </div>
            <p class="text-sm text-gray-500 mt-2">{{ $record->subject ?: 'بدون موضوع مستقل' }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($record->registry_number)
                <span class="rounded-xl bg-emerald-50 text-emerald-800 px-3 py-2 font-mono">{{ $record->registry_number }}</span>
            @endif
            <a href="{{ route('secretariat.records.show', [$office, $record]) }}" class="rounded-xl border px-4 py-2">رکورد ثبتی کامل</a>
            @can('submitForApproval', $record)
                <form method="POST" action="{{ route('secretariat.records.submit', [$office, $record]) }}">@csrf
                    <button class="rounded-xl bg-amber-500 text-white px-4 py-2">ارسال برای تأیید</button>
                </form>
            @endcan
            @can('register', $record)
                <form method="POST" action="{{ route('secretariat.records.register', [$office, $record]) }}">@csrf
                    <button class="rounded-xl bg-emerald-600 text-white px-4 py-2">تأیید و ثبت رسمی</button>
                </form>
            @endcan
        </div>
    </div>

    @if(session('success'))
        <div class="mb-5 rounded-xl bg-emerald-50 text-emerald-800 p-4">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-5 rounded-xl bg-red-50 text-red-800 p-4"><ul class="list-disc pr-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <section class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm p-6">
                <h2 class="font-bold text-lg mb-4">متن و مشخصات مکاتبه</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm mb-5">
                    <div class="rounded-xl bg-gray-50 dark:bg-gray-900 p-3"><dt class="text-gray-500">کانال</dt><dd class="font-medium mt-1">{{ $detail?->channel ?: '—' }}</dd></div>
                    <div class="rounded-xl bg-gray-50 dark:bg-gray-900 p-3"><dt class="text-gray-500">مرجع بیرونی</dt><dd class="font-medium mt-1">{{ $detail?->external_reference_number ?: '—' }}</dd></div>
                    <div class="rounded-xl bg-gray-50 dark:bg-gray-900 p-3"><dt class="text-gray-500">دریافت</dt><dd class="font-medium mt-1">{{ optional($detail?->received_at)->format('Y-m-d H:i') ?: '—' }}</dd></div>
                    <div class="rounded-xl bg-gray-50 dark:bg-gray-900 p-3"><dt class="text-gray-500">ارسال ثبت‌شده</dt><dd class="font-medium mt-1">{{ optional($detail?->sent_at)->format('Y-m-d H:i') ?: '—' }}</dd></div>
                </dl>
                @if($record->summary)<p class="mb-4 text-gray-600 dark:text-gray-300">{{ $record->summary }}</p>@endif
                <div class="whitespace-pre-wrap leading-8">{{ $record->currentVersion?->body ?: 'متنی ثبت نشده است.' }}</div>

                @if($record->attachments->isNotEmpty())
                    <div class="mt-5 border-t pt-4 space-y-2">
                        @foreach($record->attachments as $attachment)
                            <a href="{{ route('secretariat.attachments.download', [$office, $record, $attachment]) }}" class="block rounded-lg bg-gray-50 dark:bg-gray-900 p-3 hover:bg-gray-100">
                                <i class="fa-solid fa-paperclip ml-2"></i>{{ $attachment->original_name }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm p-6">
                <h2 class="font-bold text-lg mb-4">طرف‌های مکاتبه</h2>
                <div class="space-y-3">
                    @foreach($record->parties as $party)
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div>
                                <span class="text-xs rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-1 ml-2">{{ $party->role }}</span>
                                <strong>{{ $party->display_name }}</strong>
                                @if($party->organization_name)<span class="text-sm text-gray-500 mr-2">{{ $party->organization_name }}</span>@endif
                            </div>
                            <div class="text-sm text-gray-500">{{ $party->email ?: $party->phone ?: $party->party_type }}</div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-bold text-lg">گردش و ابلاغ</h2>
                    <span class="text-sm text-gray-500">{{ $record->dispatches->count() }} مورد</span>
                </div>

                <div class="space-y-4">
                    @forelse($record->dispatches as $dispatch)
                        @php
                            $targetName = $dispatch->targetParty?->display_name;
                            if (!$targetName && $dispatch->targetUser) {
                                $targetName = trim((string) $dispatch->targetUser->first_name . ' ' . (string) $dispatch->targetUser->last_name) ?: $dispatch->targetUser->email;
                            }
                        @endphp
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <strong>{{ $dispatch->dispatch_type }}</strong>
                                    <span class="text-xs text-gray-500 mr-2">{{ $dispatch->channel }} → {{ $targetName ?: '—' }}</span>
                                </div>
                                <span class="text-xs rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-1">{{ $dispatchStatusLabels[$dispatch->status] ?? $dispatch->status }}</span>
                            </div>
                            @if($dispatch->instructions)<p class="text-sm mt-3 text-gray-600 dark:text-gray-300">{{ $dispatch->instructions }}</p>@endif
                            <div class="text-xs text-gray-400 mt-3 flex flex-wrap gap-3">
                                <span>ارسال: {{ optional($dispatch->dispatched_at)->format('Y-m-d H:i') ?: '—' }}</span>
                                <span>دریافت: {{ optional($dispatch->received_at)->format('Y-m-d H:i') ?: '—' }}</span>
                                <span>تکمیل: {{ optional($dispatch->completed_at)->format('Y-m-d H:i') ?: '—' }}</span>
                            </div>
                            @can('transition', $record)
                                @if(!empty($nextDispatchStatuses[$dispatch->status] ?? []))
                                    <div class="flex flex-wrap gap-2 mt-4">
                                        @foreach($nextDispatchStatuses[$dispatch->status] as $nextStatus)
                                            <form method="POST" action="{{ route('secretariat.dispatches.transition', [$office, $record, $dispatch]) }}">@csrf
                                                <input type="hidden" name="status" value="{{ $nextStatus }}">
                                                <button class="rounded-lg border px-3 py-1.5 text-sm hover:bg-gray-50">→ {{ $dispatchStatusLabels[$nextStatus] ?? $nextStatus }}</button>
                                            </form>
                                        @endforeach
                                    </div>
                                @endif
                            @endcan
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">هنوز گردش یا ابلاغی برای این سند ثبت نشده است.</p>
                    @endforelse
                </div>

                @can('transition', $record)
                    @if($record->registry_number && in_array($record->status, ['registered', 'active', 'closed'], true))
                        <form method="POST" action="{{ route('secretariat.dispatches.store', [$office, $record]) }}" class="mt-6 rounded-xl bg-gray-50 dark:bg-gray-900 p-4 space-y-3">
                            @csrf
                            <h3 class="font-bold">گردش جدید</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <select name="dispatch_type" required class="rounded-xl border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                                    <option value="referral">ارجاع</option><option value="notification">ابلاغ</option><option value="delivery">تحویل</option><option value="return">بازگشت</option>
                                </select>
                                <select name="channel" required class="rounded-xl border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                                    <option value="internal">internal</option><option value="email">email</option><option value="physical">physical</option><option value="api">api</option><option value="other">other</option>
                                </select>
                                <select name="target_user_id" class="rounded-xl border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                                    <option value="">مقصد داخلی — انتخاب کاربر</option>
                                    @foreach($dispatchUsers as $member)
                                        @php $name = trim((string) $member->first_name . ' ' . (string) $member->last_name); @endphp
                                        <option value="{{ $member->id }}">{{ $name ?: $member->email ?: ('#' . $member->id) }}</option>
                                    @endforeach
                                </select>
                                <select name="target_party_id" class="rounded-xl border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                                    <option value="">مقصد بیرونی — انتخاب طرف مکاتبه</option>
                                    @foreach($record->parties as $party)<option value="{{ $party->id }}">{{ $party->role }} — {{ $party->display_name }}</option>@endforeach
                                </select>
                            </div>
                            <input type="text" name="external_reference_number" placeholder="شماره رهگیری/مرجع حمل اختیاری" class="w-full rounded-xl border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                            <textarea name="instructions" rows="3" placeholder="دستور یا توضیح گردش" class="w-full rounded-xl border-gray-300 dark:bg-gray-800 dark:border-gray-700"></textarea>
                            <p class="text-xs text-gray-500">برای کانال internal فقط مقصد داخلی را انتخاب کنید؛ برای email/physical/api/other فقط یک طرف ثبت‌شدهٔ همین سند را.</p>
                            <button class="rounded-xl bg-indigo-600 text-white px-4 py-2.5">ثبت گردش</button>
                        </form>
                    @endif
                @endcan
            </section>
        </div>

        <aside class="space-y-6">
            <section class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm p-5">
                <h2 class="font-bold mb-4">شناسه‌های رسمی</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">دفتر</dt><dd>{{ $office->code }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">شماره ثبت</dt><dd class="font-mono">{{ $record->registry_number ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">مرجع بیرونی</dt><dd>{{ $detail?->external_reference_number ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">نسخه</dt><dd>{{ $record->currentVersion?->version_number ?: '—' }}</dd></div>
                </dl>
            </section>

            <section class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm p-5">
                <h2 class="font-bold mb-4">روابط</h2>
                <div class="space-y-2">
                    @foreach($record->outgoingRelations as $relation)
                        <a href="{{ route('secretariat.records.show', [$office, $relation->targetRecord]) }}" class="block rounded-lg border p-3 text-sm">
                            {{ $relation->relation_type }} → {{ $relation->targetRecord->title }}
                        </a>
                    @endforeach
                    @foreach($record->incomingRelations as $relation)
                        <a href="{{ route('secretariat.records.show', [$office, $relation->sourceRecord]) }}" class="block rounded-lg border p-3 text-sm">
                            ← {{ $relation->relation_type }} — {{ $relation->sourceRecord->title }}
                        </a>
                    @endforeach
                    @if($record->outgoingRelations->isEmpty() && $record->incomingRelations->isEmpty())<p class="text-sm text-gray-500">بدون رابطه</p>@endif
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection
