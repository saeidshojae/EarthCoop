@extends('layouts.chat')

@php
$lastReadMessageId = $lastReadMessageId ?? null;
@endphp

@section('title', $group->name . ' - گفت‌وگوی گروه')

@section('head-tag')

<!-- Bootstrap, jQuery and Select2 are bundled by Vite; Font Awesome is local in the layout. -->

<!-- CSRF Token (برای Ajax) -->
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="group-chat-id" content="{{ $group->id }}">
<meta name="group-chat-auth-user-id" content="{{ auth()->id() }}">

<link rel="stylesheet" href="{{ asset('Css/group-chat.css') }}?v={{ filemtime(public_path('Css/group-chat.css')) }}">

@include('groups.partials.chat_runtime')
<!-- کد حفظ موقعیت scroll به انتهای صفحه منتقل شد -->
@include('groups.partials.styles.base_styles')

@endsection

@section('content')
@php
$memberCount = $memberCount ?? 0;
$guestCount = $guestCount ?? 0;
$blogCount = $blogCount ?? 0;
$pollCount = $pollCount ?? 0;
$pivotUser = $pivotUser ?? null;
$checkBlockElection = $checkBlockElection ?? null;
$checkBlockMessage = $checkBlockMessage ?? null;
$checkBlockPost = $checkBlockPost ?? null;
$checkBlockPoll = $checkBlockPoll ?? null;
$roleValue = $yourRole;

$roleTitle = match($roleValue) {
0 => 'ناظر',
1 => 'فعال',
2 => 'بازرس',
3 => 'مدیر',
4 => 'مهمان',
5 => 'فعال ۲',
default => 'عضو'
};
$membershipStatusLabel = (int)($pivotUser?->status ?? 0) === 1 ? 'فعال' : 'غیرفعال';
$electionAvailable = ($election ?? null) && optional($groupSetting)->election_status == 1;
$canParticipateElection = $electionAvailable && !$checkBlockElection && (int)($pivotUser?->status ?? 0) === 1;
@endphp
<div id="group-chat-main-container"
    class="container mx-auto max-w-7xl px-4 md:px-8 pt-0 pb-8 space-y-6 md:space-y-10 group-chat-container"
    style="direction: rtl;">
    @include('groups.partials.group_hero')

    @include('groups.modals.group_edit_form', compact('group'))
    @include('groups.modals.session_schedule', compact('group'))
    @php use Illuminate\Support\Str; @endphp
    <div class="loading-overlay" id="global-loading">
        <div class="spinner"></div>
    </div>

    <div class="grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)] items-start">
        <div class="space-y-6">
            @include('groups.partials.pin_navigator')

            <div class="chat-wrapper">
                <div class="chat-body" id="chat-box">
                    @foreach($combined as $item)
                    @include('groups.partials.' . $item->type, compact('item', 'group', 'userVote', 'postGroupUsersMap'))
                    @endforeach
                </div>
            </div>

            <button id="scroll-toggle-btn" class="chat-scroll-btn">
                <i class="fas fa-arrow-up"></i>
            </button>

            <div class="chat-composer-shell @cannot('participate', $group) chat-composer-shell--restricted @endcannot bg-white border border-emerald-100 rounded-3xl shadow-sm p-5 w-full">
                @cannot('participate', $group)
                <div class="chat-session-closed" role="status">
                    <i class="fas fa-lock" aria-hidden="true"></i>
                    <div>
                        @if((int) $yourRole === 0)
                        <strong>حالت مشاهده برای نقش ناظر</strong>
                        <p>شما می‌توانید فعالیت اعضای فعال را مشاهده کنید، اما امکان ارسال پیام، پست، نظر، رأی یا واکنش ندارید.</p>
                        @else
                        <strong>نشست در حالت محدود قرار دارد</strong>
                        <p>تا فعال‌شدن دوباره نشست، فقط مدیران، بازرسان و اعضای دارای مجوز می‌توانند پیام، پست، نظر یا رأی ثبت کنند.</p>
                        <button type="button" class="session-request-trigger" data-session-request-open>
                            <i class="fas fa-hand-paper"></i> درخواست مشارکت
                        </button>
                        @endif
                    </div>
                </div>
                @elseif (auth()->user()->status == 0 || auth()->user()->first_name == null || auth()->user()->last_name == null)
                <p class="text-amber-600">
                    به دلیل کامل نبودن اطلاعات کاربری امکان ارسال پیام را ندارید، از
                    <a href='{{ route('profile.edit') }}' class="text-emerald-600 underline">این قسمت</a>
                    اقدام به وارد کردن اطلاعات کنید.
                </p>
                @else
                <form id="chatForm" class="chat-input telegram-style-input" method="POST"
                    action="{{ route('groups.messages.store') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="group_id" value="{{ $group->id }}">
                    <input type="hidden" name="parent_id" id="parent_id" value="">
                    <input type="file" name="voice_message" id="voice-file-input" accept="audio/*" class="d-none">

                    @if ($checkBlockMessage != null)
                    <div class="chat-block-message text-danger-emphasis bg-danger-subtle border border-danger-subtle rounded-4 px-3 py-3">
                        شما از جانب مدیریت برای عملیات ارسال پیام مسدود شده‌اید، جهت رفع مسدودیت با مدیریت در ارتباط باشید.
                    </div>
                    @else
                    <div id="reply-indicator-container" class="telegram-reply-indicator" style="display: none;"></div>
                    <div class="telegram-input-container">
                        @if($yourRole != 5)
                        <div class="position-relative telegram-attach-btn-wrapper">
                            <button type="button" id="chatCreateToggle" class="telegram-attach-btn"><i class="fas fa-paperclip"></i></button>
                            <div id="createMenu" style="display: none;" class="chat-tool-menu telegram-attach-menu">
                                @if ($checkBlockPost != null)
                                <span class="chat-tool-menu__item text-danger">شما برای عملیات ایجاد پست مسدود شده‌اید</span>
                                @else
                                <button type="button" class="chat-tool-menu__item" id="create-post-btn"><i class="far fa-edit text-success"></i> ایجاد پست</button>
                                @endif
                                @if ($checkBlockPoll != null)
                                <span class="chat-tool-menu__item text-danger">شما برای عملیات ایجاد نظرسنجی مسدود شده‌اید</span>
                                @else
                                <button type="button" class="chat-tool-menu__item" id="create-poll-btn"><i class="fas fa-chart-simple text-success"></i> ایجاد نظرسنجی</button>
                                @endif
                                <button type="button" id="audio-upload-trigger" class="chat-tool-menu__item"><i class="fas fa-file-audio text-success"></i> ارسال فایل صوتی</button>
                            </div>
                        </div>
                        @endif
                        <div class="telegram-input-wrapper">
                            <textarea class="telegram-textarea" name="message" placeholder="پیام خود را بنویسید..." id="message_editor" rows="1"></textarea>
                        </div>
                        <div class="telegram-action-buttons">
                            <button type="button" id="voice-record-btn" class="telegram-action-btn telegram-voice-btn" title="ضبط صدا"><i class="fas fa-microphone"></i></button>
                            <button type="submit" id="telegram-send-btn" class="telegram-action-btn telegram-send-btn" title="ارسال"><i class="fas fa-paper-plane"></i></button>
                        </div>
                    </div>
                    <div id="voice-file-preview" class="voice-file-preview telegram-voice-preview" style="display: none !important;">
                        <i class="fas fa-file-audio"></i>
                        <div class="voice-file-info"><div id="voice-file-name"></div><small id="voice-file-size"></small></div>
                        <button type="button" class="voice-file-remove-btn" id="voice-file-remove"><i class="fas fa-times"></i></button>
                    </div>
                    @endif
                </form>
                @endif
            </div>
        </div>

        <aside class="space-y-6 lg:pl-2">
            @include('groups.partials.group_info_panel', compact('group'))
        </aside>
    </div>

    @include('groups.partials.group_control_center_shell')
    @include('groups.partials.group_control_center_polish')
    @include('groups.partials.election_surface_bridge')

    <div id="groupInfoBackdrop" class="group-info-backdrop hidden"></div>
    <div id="categoryBlogsOverlay" class="category-browser__overlay"></div>
    <div id="categoryBlogsModal" class="category-browser" role="dialog" aria-modal="true" aria-labelledby="catModalTitle" aria-hidden="true">
        <div class="category-browser__panel">
            <div class="category-browser__header">
                <strong id="catModalTitle">لیست پست‌ها</strong>
                <button type="button" id="closeCatModal" class="category-browser__close" aria-label="بستن">×</button>
            </div>
            <div id="catModalBody" class="category-browser__body">
                <div id="catLoading" class="category-browser__status">در حال بارگذاری...</div>
                <ul id="catList" class="category-browser__list"></ul>
                <div id="catEmpty" class="category-browser__status">پستی در این دسته یافت نشد.</div>
            </div>
        </div>
    </div>
    <div id="editModal" class="edit-modal hidden" aria-hidden="true">
        <div class="edit-modal__backdrop"></div>
        <div class="edit-modal__panel" role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
            <div class="edit-modal__header"><h3 id="editModalTitle">ویرایش پیام</h3><button type="button" class="edit-close" aria-label="بستن">×</button></div>
            <div class="edit-modal__body"><textarea id="editText" rows="6" class="edit-textarea" placeholder="متن پیام..."></textarea></div>
            <div class="edit-modal__footer">
                <button type="button" class="btn btn-primary save-edit">ذخیره</button>
                <button type="button" class="btn cancel-edit" style='background-color: #c24545 !important;'>لغو</button>
            </div>
        </div>
    </div>

@include('groups.partials.styles.message_edit_styles')
@include('groups.partials.message_edit_runtime')
</div>
</div>
@include('groups.modals.election_form', compact('group'))
@include('groups.modals.post_form', compact('group', 'categories'))
@include('groups.modals.poll_form', compact('group'))

@if($electionAvailable && isset($election) && $election)
<div id="electionVotingOverlay" class="election-voting-overlay" style="display: none;">
    <div class="election-voting-overlay__backdrop" data-chat-page-action="close-election"></div>
    @include('groups.modals.election_modal', compact('group', 'election', 'selectedVotesInspector', 'selectedVotesManager', 'managersSorted', 'inspectorsSorted', 'managerCounts', 'inspectorCounts', 'groupSetting'))
</div>
@endif

@include('groups.partials.page_chrome_runtime')

@push('scripts')
@include('groups.partials.ckeditor_runtime')
@endpush

@include('groups.partials.styles.auxiliary_styles')
@include('groups.partials.chat_search_runtime')
@include('groups.partials.management_modals')

</div>

@include('groups.partials.scroll_unread_runtime')

@endsection
