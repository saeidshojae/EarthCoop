@extends('layouts.chat')

@php
$lastReadMessageId = $lastReadMessageId ?? null;
@endphp

@section('title', $group->name . ' - گفت‌وگوی گروه')

@section('head-tag')

<!-- Tailwind & Bootstrap CSS via Vite -->
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
// مطمئن شو Select2 بعد از jQuery لود شده
if (typeof jQuery !== 'undefined') {
    jQuery.fn.select2.defaults.set('language', {
        noResults: function() {
            return "نتیجه‌ای یافت نشد";
        },
        searching: function() {
            return "در حال جستجو...";
        }
    });
}
</script>

<script src="{{ asset('vendor/ckeditor/ckeditor.js') }}"></script>



<!-- CSRF Token (برای Ajax) -->
<meta name="csrf-token" content="{{ csrf_token() }}">


<link rel="stylesheet" href="{{ asset('Css/group-chat.css') }}">



@include('groups.partials.chat_runtime')
<!-- کد حفظ موقعیت scroll به انتهای صفحه منتقل شد -->
@include('groups.partials.styles.base_styles')

@endsection

@section('content')
@php
$memberCount = $group->userCount();
$guestCount = $group->guestsCount();
$blogCount = \App\Models\Blog::where('group_id', $group->id)->count();
$pollCount = $group->polls()->count();
// از $yourRole که controller محاسبه کرده استفاده می‌کنیم
// دیگر نیازی به کوئری users() در blade نیست
$pivotUser = \App\Models\GroupUser::where('group_id', $group->id)->where('user_id', auth()->id())->first();
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
$checkBlockElection = \App\Models\Block::where('user_id', auth()->id())->where('position', 'election')->first();
$electionAvailable = ($election ?? null) && optional($groupSetting)->election_status == 1;
$canParticipateElection = $electionAvailable && !$checkBlockElection && optional(auth()->user())->status == 1;
@endphp
<div id="group-chat-main-container"
    class="container mx-auto max-w-7xl px-4 md:px-8 pt-0 pb-8 space-y-6 md:space-y-10 group-chat-container"
    style="direction: rtl;">
    <section
        class="bg-white border border-emerald-100 rounded-2xl md:rounded-3xl shadow-md relative overflow-hidden group-info-card"
        x-data="{ expanded: false }">
        <div
            class="absolute inset-0 pointer-events-none bg-gradient-to-l from-emerald-50/50 via-transparent to-transparent">
        </div>

        <!-- نسخه خلاصه برای موبایل -->
        <button @click="expanded = !expanded"
            class="lg:hidden w-full relative z-10 flex items-center justify-between gap-3 px-5 py-4 hover:bg-emerald-50/50 active:bg-emerald-50 transition-colors">
            <div class="flex items-center gap-4 flex-1 min-w-0">
                <div
                    class="w-14 h-14 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-xl font-black shadow-md flex-shrink-0 border border-emerald-200/60">
                    @if($group->avatar)
                    <img src="{{ asset('images/groups/' . $group->avatar) }}" alt="{{ $group->name }}"
                        class="w-full h-full object-cover rounded-2xl">
                    @else
                    {{ Str::upper(Str::substr($group->name, 0, 2)) }}
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <h1 class="text-lg font-bold text-slate-900 truncate leading-tight mb-1.5">{{ $group->name }}</h1>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <span
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-700 text-xs font-semibold">
                            <i class="fas fa-user-shield text-[10px]"></i>{{ $roleTitle }}
                        </span>
                        <span class="text-xs text-slate-500 font-medium">{{ $memberCount }} عضو</span>
                    </div>
                </div>
            </div>
            <div
                class="flex-shrink-0 w-9 h-9 flex items-center justify-center rounded-xl bg-emerald-50 hover:bg-emerald-100 active:bg-emerald-200 transition-colors ml-2">
                <i class="fas fa-chevron-down text-emerald-600 text-xs transition-transform duration-300"
                    :class="{ 'rotate-180': expanded }"></i>
            </div>
        </button>

        <!-- محتوای کامل - در موبایل با expand/collapse -->
        <div class="relative z-10 px-5 py-5 collapse-content lg:hidden border-t border-emerald-100/60" x-show="expanded"
            x-cloak style="display: none;">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-4">
                    <div
                        class="w-16 h-16 rounded-3xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-2xl font-black shadow-inner hidden lg:flex">
                        @if($group->avatar)
                        <img src="{{ asset('images/groups/' . $group->avatar) }}" alt="{{ $group->name }}"
                            class="w-full h-full object-cover rounded-3xl">
                        @else
                        {{ Str::upper(Str::substr($group->name, 0, 2)) }}
                        @endif
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center gap-3 flex-wrap">
                            <h1 class="text-xl lg:text-3xl font-black text-slate-900">{{ $group->name }}</h1>
                            <span
                                class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-100 text-emerald-600 text-sm font-semibold">
                                <i class="fas fa-user-shield"></i>{{ $roleTitle }}
                            </span>
                            <span
                                class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-sm">
                                <i class="fas fa-wave-square"></i>{{ $membershipStatusLabel }}
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-sm text-slate-600">
                            <span class="inline-flex items-center gap-2">
                                <i class="fas fa-users text-emerald-500"></i>{{ $memberCount }} عضو
                            </span>
                            @if($guestCount > 0)
                            <span class="inline-flex items-center gap-2">
                                <i class="fas fa-user-clock text-emerald-500"></i>{{ $guestCount }} مهمان
                            </span>
                            @endif
                            @if($group->location_level)
                            <span class="inline-flex items-center gap-2">
                                <i class="fas fa-map-marker-alt text-emerald-500"></i>{{ $group->location_level }}
                            </span>
                            @endif
                            <span class="inline-flex items-center gap-2">
                                <i
                                    class="fas fa-calendar-check text-emerald-500"></i>{{ verta($group->created_at)->format('Y/m/d') }}
                            </span>
                        </div>
                        @if(!empty($group->description))
                        <p class="text-sm text-slate-500 leading-relaxed max-w-2xl">
                            {{ Str::limit(strip_tags($group->description), 180) }}
                        </p>
                        @endif
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3 justify-start lg:justify-end">
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-emerald-200 text-emerald-600 hover:bg-emerald-50 transition lg:hidden"
                        data-chat-page-action="open-group-info">
                        <i class="fas fa-layer-group"></i>
                        پنل گروه
                    </button>
                    @if(($yourRole ?? 0) !== 5)
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-emerald-500 text-white shadow-sm hover:bg-emerald-600 transition"
                        data-chat-page-action="open-blog">
                        <i class="far fa-pen-to-square"></i>
                        ایجاد پست
                    </button>
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-emerald-100 text-emerald-600 hover:bg-emerald-200 transition"
                        data-chat-page-action="open-poll">
                        <i class="fas fa-chart-simple"></i>
                        ساخت نظرسنجی
                    </button>
                    @endif
                    @if($electionAvailable)
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl {{ $canParticipateElection ? 'bg-indigo-500 text-white shadow-sm hover:bg-indigo-600 transition' : 'bg-slate-100 text-slate-500 cursor-not-allowed' }}"
                        @if($canParticipateElection) data-chat-page-action="open-election" @else disabled @endif>
                        <i class="fas fa-vote-yea"></i>
                        {{ $canParticipateElection ? 'شرکت در انتخابات' : 'انتخابات فعال' }}
                    </button>
                    @endif
                    @if(in_array($yourRole ?? 0, [2,3]))
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition"
                        data-chat-page-action="open-election-admin">
                        <i class="fas fa-ballot-check text-emerald-500"></i>
                        افزودن انتخابات
                    </button>
                    @endif
                    @if(($yourRole ?? 0) == 3)
                    <button type="button" id="manage-members-btn"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-blue-200 text-blue-600 hover:bg-blue-50 transition"
                        data-chat-page-action="manage-members">
                        <i class="fas fa-users-cog"></i>
                        مدیریت اعضا
                    </button>
                    <button type="button" id="manage-reports-btn"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-orange-200 text-orange-600 hover:bg-orange-50 transition relative"
                        data-chat-page-action="manage-reports">
                        <i class="fas fa-flag"></i>
                        گزارش‌ها
                        <span id="reports-badge"
                            class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"
                            style="display: none;">0</span>
                    </button>
                    @endif
                    <button type="button" id="group-settings-btn"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-emerald-200 text-emerald-600 hover:bg-emerald-50 transition"
                        data-chat-page-action="group-settings">
                        <i class="fas fa-cog"></i>
                        تنظیمات
                    </button>
                    <a href="{{ route('groups.logout', $group->id) }}"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-red-100 text-red-500 hover:bg-red-50 transition">
                        <i class="fas fa-door-open"></i>
                        خروج از گروه
                    </a>
                </div>
            </div>
            <div class="relative z-10 mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="stat-chip">
                    <span class="stat-chip__label">پیام‌های سنجاق‌شده</span>
                    <span class="stat-chip__value">{{ $pinnedMessages->count() }}</span>
                </div>
                <div class="stat-chip">
                    <span class="stat-chip__label">پست‌ها</span>
                    <span class="stat-chip__value">{{ $blogCount }}</span>
                </div>
                <div class="stat-chip">
                    <span class="stat-chip__label">نظرسنجی‌ها</span>
                    <span class="stat-chip__value">{{ $pollCount }}</span>
                </div>
                <div class="stat-chip">
                    <span class="stat-chip__label">آخرین فعالیت</span>
                    <span class="stat-chip__value">{{ verta($group->updated_at)->formatDifference() }}</span>
                </div>
            </div>
        </div>

        <!-- نسخه دسکتاپ - همیشه باز -->
        <div class="hidden lg:block relative z-10 px-5 py-6">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-5">
                    <div
                        class="w-16 h-16 rounded-3xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-2xl font-black shadow-inner">
                        @if($group->avatar)
                        <img src="{{ asset('images/groups/' . $group->avatar) }}" alt="{{ $group->name }}"
                            class="w-full h-full object-cover rounded-3xl">
                        @else
                        {{ Str::upper(Str::substr($group->name, 0, 2)) }}
                        @endif
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center gap-3 flex-wrap">
                            <h1 class="text-2xl lg:text-3xl font-black text-slate-900">{{ $group->name }}</h1>
                            <span
                                class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-100 text-emerald-600 text-sm font-semibold">
                                <i class="fas fa-user-shield"></i>{{ $roleTitle }}
                            </span>
                            <span
                                class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-sm">
                                <i class="fas fa-wave-square"></i>{{ $membershipStatusLabel }}
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-sm text-slate-600">
                            <span class="inline-flex items-center gap-2">
                                <i class="fas fa-users text-emerald-500"></i>{{ $memberCount }} عضو
                            </span>
                            @if($guestCount > 0)
                            <span class="inline-flex items-center gap-2">
                                <i class="fas fa-user-clock text-emerald-500"></i>{{ $guestCount }} مهمان
                            </span>
                            @endif
                            @if($group->location_level)
                            <span class="inline-flex items-center gap-2">
                                <i class="fas fa-map-marker-alt text-emerald-500"></i>{{ $group->location_level }}
                            </span>
                            @endif
                            <span class="inline-flex items-center gap-2">
                                <i
                                    class="fas fa-calendar-check text-emerald-500"></i>{{ verta($group->created_at)->format('Y/m/d') }}
                            </span>
                        </div>
                        @if(!empty($group->description))
                        <p class="text-sm text-slate-500 leading-relaxed max-w-2xl">
                            {{ Str::limit(strip_tags($group->description), 180) }}
                        </p>
                        @endif
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3 justify-start lg:justify-end">
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-emerald-200 text-emerald-600 hover:bg-emerald-50 transition lg:hidden"
                        data-chat-page-action="open-group-info">
                        <i class="fas fa-layer-group"></i>
                        پنل گروه
                    </button>
                    @if(($yourRole ?? 0) !== 5)
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-emerald-500 text-white shadow-sm hover:bg-emerald-600 transition"
                        data-chat-page-action="open-blog">
                        <i class="far fa-pen-to-square"></i>
                        ایجاد پست
                    </button>
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-emerald-100 text-emerald-600 hover:bg-emerald-200 transition"
                        data-chat-page-action="open-poll">
                        <i class="fas fa-chart-simple"></i>
                        ساخت نظرسنجی
                    </button>
                    @endif
                    @if($electionAvailable)
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl {{ $canParticipateElection ? 'bg-indigo-500 text-white shadow-sm hover:bg-indigo-600 transition' : 'bg-slate-100 text-slate-500 cursor-not-allowed' }}"
                        @if($canParticipateElection) data-chat-page-action="open-election" @else disabled @endif>
                        <i class="fas fa-vote-yea"></i>
                        {{ $canParticipateElection ? 'شرکت در انتخابات' : 'انتخابات فعال' }}
                    </button>
                    @endif
                    @if(in_array($yourRole ?? 0, [2,3]))
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition"
                        data-chat-page-action="open-election-admin">
                        <i class="fas fa-ballot-check text-emerald-500"></i>
                        افزودن انتخابات
                    </button>
                    @endif
                    @if(($yourRole ?? 0) == 3)
                    <button type="button" id="manage-members-btn"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-blue-200 text-blue-600 hover:bg-blue-50 transition"
                        data-chat-page-action="manage-members">
                        <i class="fas fa-users-cog"></i>
                        مدیریت اعضا
                    </button>
                    <button type="button" id="manage-reports-btn"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-orange-200 text-orange-600 hover:bg-orange-50 transition relative"
                        data-chat-page-action="manage-reports">
                        <i class="fas fa-flag"></i>
                        گزارش‌ها
                        <span id="reports-badge"
                            class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"
                            style="display: none;">0</span>
                    </button>
                    @endif
                    <button type="button" id="group-settings-btn"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-emerald-200 text-emerald-600 hover:bg-emerald-50 transition"
                        data-chat-page-action="group-settings">
                        <i class="fas fa-cog"></i>
                        تنظیمات
                    </button>
                    <a href="{{ route('groups.logout', $group->id) }}"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-red-100 text-red-500 hover:bg-red-50 transition">
                        <i class="fas fa-door-open"></i>
                        خروج از گروه
                    </a>
                </div>
            </div>
            <div class="relative z-10 mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="stat-chip">
                    <span class="stat-chip__label">پیام‌های سنجاق‌شده</span>
                    <span class="stat-chip__value">{{ $pinnedMessages->count() }}</span>
                </div>
                <div class="stat-chip">
                    <span class="stat-chip__label">پست‌ها</span>
                    <span class="stat-chip__value">{{ $blogCount }}</span>
                </div>
                <div class="stat-chip">
                    <span class="stat-chip__label">نظرسنجی‌ها</span>
                    <span class="stat-chip__value">{{ $pollCount }}</span>
                </div>
                <div class="stat-chip">
                    <span class="stat-chip__label">آخرین فعالیت</span>
                    <span class="stat-chip__value">{{ verta($group->updated_at)->formatDifference() }}</span>
                </div>
            </div>
        </div>
    </section>

    @include('groups.modals.group_edit_form', compact('group'))
    @php use Illuminate\Support\Str; @endphp
    <div class="loading-overlay" id="global-loading">
        <div class="spinner"></div>
    </div>


    <div class="grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)] items-start">
        <div class="space-y-6">
            @if ($pinnedMessages->count() > 0)
            <div class="bg-white border border-emerald-100 rounded-3xl shadow-sm mb-6">
                <div class="pinned-messages"
                    style="border-radius: 1.5rem; overflow: hidden; background: #fff; border: none;">
                    @foreach($pinnedMessages as $pinnedMessage)
                    <div class="pin-wrapper"
                        style="position: relative; border-bottom: 1px solid #f1f5f9; padding: 4px;">
                        <a class="pin" href="#msg-{{ $pinnedMessage->message->id }}"
                            style="flex: 1; padding-left: 45px; box-shadow: none; border-radius: 0.75rem;">
                            <div>
                                <b style="color: #10b981; font-size: 0.85rem;">پیام سنجاق‌شده</b>
                                <p style="font-size: 0.9rem; color: #475569;">{!!
                                    Str::limit(strip_tags($pinnedMessage->message->message), 120, '...') !!}</p>
                            </div>
                            <i class="fas fa-thumbtack" style="font-size: 1.1rem; color: #10b981; opacity: 0.5;"></i>
                        </a>
                        @if($roleValue === 3 || $pinnedMessage->message->user_id === auth()->id())
                        <button type="button" data-chat-page-action="unpin" data-message-id="{{ $pinnedMessage->message->id }}"
                            class="unpin-btn"
                            style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); border: none; background: #f1f5f9; cursor: pointer; color: #94a3b8; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s;"
                            title="حذف از حالت سنجاق">
                            <i class="fas fa-times" style="font-size: 0.9rem; color: inherit !important;"></i>
                        </button>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

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

            @php
            $checkBlockMessage = \App\Models\Block::where('user_id', auth()->user()->id)->where('position',
            'message')->first();
            $checkBlockPost = \App\Models\Block::where('user_id', auth()->user()->id)->where('position',
            'post')->first();
            $checkBlockPoll = \App\Models\Block::where('user_id', auth()->user()->id)->where('position',
            'poll')->first();
            @endphp

            <div class="chat-composer-shell bg-white border border-emerald-100 rounded-3xl shadow-sm p-5 w-full">
                @if ($yourRole === 0 && $group->is_open == 0)
                <p class="text-red-500">
                    شما مجاز به ارسال پیام در گروه نیستید.
                </p>
                @elseif (auth()->user()->status == 0 || auth()->user()->first_name == null || auth()->user()->last_name
                == null)
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
                    <div
                        class="chat-block-message text-danger-emphasis bg-danger-subtle border border-danger-subtle rounded-4 px-3 py-3">
                        شما از جانب مدیریت برای عملیات ارسال پیام مسدود شده‌اید، جهت رفع مسدودیت با مدیریت در ارتباط
                        باشید.
                    </div>
                    @else
                    <!-- Reply Indicator Container - شبیه تلگرام -->
                    <div id="reply-indicator-container" class="telegram-reply-indicator" style="display: none;"></div>

                    <!-- Input Container -->
                    <div class="telegram-input-container">
                        <!-- Attachment Button -->
                        @if($yourRole != 5)
                        <div class="position-relative telegram-attach-btn-wrapper">
                            <button type="button" id="chatCreateToggle" class="telegram-attach-btn">
                                <i class="fas fa-paperclip"></i>
                            </button>
                            <div id="createMenu" style="display: none;" class="chat-tool-menu telegram-attach-menu">
                                @if ($checkBlockPost != null)
                                <span class="chat-tool-menu__item text-danger">شما برای عملیات ایجاد پست مسدود
                                    شده‌اید</span>
                                @else
                                <button type="button" class="chat-tool-menu__item" id="create-post-btn">
                                    <i class="far fa-edit text-success"></i>
                                    ایجاد پست
                                </button>
                                @endif

                                @if ($checkBlockPoll != null)
                                <span class="chat-tool-menu__item text-danger">شما برای عملیات ایجاد نظرسنجی مسدود
                                    شده‌اید</span>
                                @else
                                <button type="button" class="chat-tool-menu__item" id="create-poll-btn">
                                    <i class="fas fa-chart-simple text-success"></i>
                                    ایجاد نظرسنجی
                                </button>
                                @endif

                                <button type="button" id="audio-upload-trigger" class="chat-tool-menu__item">
                                    <i class="fas fa-file-audio text-success"></i>
                                    ارسال فایل صوتی
                                </button>
                            </div>
                        </div>
                        @endif

                        <!-- Text Input -->
                        <div class="telegram-input-wrapper">
                            <textarea class="telegram-textarea" name="message" placeholder="پیام خود را بنویسید..."
                                id="message_editor" rows="1"></textarea>
                        </div>

                        <!-- Send Button -->
                        <div class="telegram-action-buttons">
                            <button type="button" id="voice-record-btn" class="telegram-action-btn telegram-voice-btn"
                                title="ضبط صدا">
                                <i class="fas fa-microphone"></i>
                            </button>
                            <button type="submit" id="telegram-send-btn" class="telegram-action-btn telegram-send-btn"
                                title="ارسال">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Voice File Preview -->
                    <div id="voice-file-preview" class="voice-file-preview telegram-voice-preview"
                        style="display: none !important;">
                        <i class="fas fa-file-audio"></i>
                        <div class="voice-file-info">
                            <div id="voice-file-name"></div>
                            <small id="voice-file-size"></small>
                        </div>
                        <button type="button" class="voice-file-remove-btn" id="voice-file-remove">
                            <i class="fas fa-times"></i>
                        </button>
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

    <div id="groupInfoBackdrop" class="group-info-backdrop hidden"></div>
    <div id="categoryBlogsOverlay"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1100;"></div>

    <div id="categoryBlogsModal" style="
  display:none; position:fixed; inset:0; z-index:1110;
  align-items:center; justify-content:center;">
        <div style="
    width: min(700px, 92vw);
    max-height: 80vh;
    background:#fff; border-radius:12px; overflow:hidden;
    direction: rtl; box-shadow:0 10px 30px rgba(0,0,0,.2);
  ">
            <div
                style="display:flex; align-items:center; justify-content:space-between; padding: .8rem 1rem; background:#f6f6f6;">
                <strong id="catModalTitle" style="font-size:1rem">لیست پست‌ها</strong>
                <button id="closeCatModal"
                    style="border:none; background:transparent; font-size:1.2rem; line-height:1;">✖</button>
            </div>
            <div id="catModalBody" style="padding: .6rem 1rem; overflow:auto; max-height: calc(80vh - 52px);">
                <div id="catLoading" style="padding:1rem; text-align:center;">در حال بارگذاری...</div>
                <ul id="catList" style="list-style:none; margin:0; padding:0; display:none;"></ul>
                <div id="catEmpty" style="display:none; text-align:center; padding:1rem;">پستی در این دسته یافت نشد.
                </div>
            </div>
        </div>
    </div>
    <!-- Edit Modal -->
    <div id="editModal" class="edit-modal hidden" aria-hidden="true">
        <div class="edit-modal__backdrop"></div>
        <div class="edit-modal__panel" role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
            <div class="edit-modal__header">
                <h3 id="editModalTitle">ویرایش پیام</h3>
                <button type="button" class="edit-close" aria-label="بستن">×</button>
            </div>
            <div class="edit-modal__body">
                <textarea id="editText" rows="6" class="edit-textarea" placeholder="متن پیام..."></textarea>
            </div>
            <div class="edit-modal__footer">
                <button type="button" class="btn btn-primary save-edit">ذخیره</button>
                <button type="button" class="btn cancel-edit "
                    style='    background-color: #c24545 !important;'>لغو</button>
            </div>
        </div>
    </div>

@include('groups.partials.styles.message_edit_styles')

    @include('groups.partials.message_edit_runtime')
    @include('groups.partials.legacy_message_runtime')
    @include('groups.partials.composer_actions_runtime')
</div>
</div>
@include('groups.modals.election_form', compact('group'))
@include('groups.modals.post_form', compact('group', 'categories'))
@include('groups.partials.post_submission_runtime')
@include('groups.modals.poll_form', compact('group'))

@if($electionAvailable && isset($election) && $election)
<div id="electionVotingOverlay" class="election-voting-overlay" style="display: none;">
    <div class="election-voting-overlay__backdrop" data-chat-page-action="close-election"></div>
    @include('groups.modals.election_modal', compact('group', 'election', 'selectedVotesInspector',
    'selectedVotesManager', 'managersSorted', 'inspectorsSorted', 'managerCounts', 'inspectorCounts', 'groupSetting'))
</div>
@endif

@include('groups.partials.page_chrome_runtime')

@push('scripts')
@include('groups.partials.ckeditor_runtime')
@endpush

@include('groups.partials.styles.auxiliary_styles')
@include('groups.partials.chat_search_runtime')

@include('groups.partials.pin_runtime')

@include('groups.partials.action_menu_dismissal')

@include('groups.partials.management_modals')

</div>

<!-- مدیریت حرفه‌ای اسکرول چت: ورود اول از ابتدا، ورودهای بعدی از اولین پیام نخوانده -->
@include('groups.partials.scroll_unread_runtime')

@endsection
