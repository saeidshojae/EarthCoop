@extends('layouts.unified')

@section('title', 'گفتگوهای خصوصی - ' . config('app.name', 'EarthCoop'))

@push('styles')
<style>
    .pm-list-shell {
        direction: rtl;
        width: 100%;
        max-width: 100%;
        margin: 0 auto;
        background: #fff;
        min-height: calc(100dvh - 7rem);
    }

    .pm-list-header {
        position: sticky;
        top: 0;
        z-index: 20;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .9rem 1rem .8rem;
        background: rgba(255, 255, 255, .96);
        border-bottom: 1px solid #e8ecea;
        backdrop-filter: blur(10px);
    }

    .pm-list-heading {
        min-width: 0;
    }

    .pm-list-title {
        margin: 0;
        color: #17211d;
        font-size: 1.15rem;
        font-weight: 800;
        line-height: 1.5;
    }

    .pm-list-subtitle {
        margin: .1rem 0 0;
        color: #718079;
        font-size: .78rem;
        line-height: 1.55;
    }

    .pm-request-link {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        min-width: 44px;
        min-height: 44px;
        padding: .55rem .75rem;
        border: 1px solid #dbe5e0;
        border-radius: 14px;
        color: #23664c;
        background: #f7faf8;
        text-decoration: none;
        font-size: .84rem;
        font-weight: 700;
    }

    .pm-conversation-list {
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .pm-conversation-row {
        display: grid;
        grid-template-columns: 54px minmax(0, 1fr) auto;
        align-items: center;
        gap: .75rem;
        min-height: 78px;
        padding: .7rem 1rem;
        border-bottom: 1px solid #edf1ef;
        color: inherit;
        background: #fff;
        text-decoration: none;
        -webkit-tap-highlight-color: transparent;
    }

    .pm-conversation-row:active {
        background: #f4f8f6;
    }

    .pm-conversation-row:focus-visible,
    .pm-request-link:focus-visible,
    .pm-empty-action:focus-visible {
        outline: 3px solid rgba(36, 112, 79, .24);
        outline-offset: -2px;
    }

    .pm-conversation-row.is-unread {
        background: #f7fbf9;
    }

    .pm-avatar-wrap {
        position: relative;
        width: 54px;
        height: 54px;
        flex: 0 0 54px;
    }

    .pm-avatar {
        display: block;
        width: 54px;
        height: 54px;
        border-radius: 50%;
        object-fit: cover;
        border: 1px solid #e0e7e3;
        background: #eef2f0;
    }

    .pm-conversation-main {
        min-width: 0;
        align-self: stretch;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .pm-conversation-name {
        overflow: hidden;
        color: #1d2924;
        font-size: .96rem;
        font-weight: 700;
        line-height: 1.45;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pm-conversation-row.is-unread .pm-conversation-name {
        font-weight: 850;
    }

    .pm-conversation-preview {
        margin-top: .18rem;
        overflow: hidden;
        color: #738078;
        font-size: .84rem;
        line-height: 1.55;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pm-conversation-row.is-unread .pm-conversation-preview {
        color: #3d5148;
        font-weight: 650;
    }

    .pm-conversation-side {
        align-self: stretch;
        display: flex;
        min-width: 54px;
        flex-direction: column;
        align-items: flex-end;
        justify-content: center;
        gap: .45rem;
    }

    .pm-conversation-time {
        color: #8a9690;
        font-size: .7rem;
        line-height: 1.3;
        white-space: nowrap;
    }

    .pm-unread-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 22px;
        height: 22px;
        padding: 0 .38rem;
        border-radius: 999px;
        background: #237a56;
        color: #fff;
        font-size: .72rem;
        font-weight: 800;
        line-height: 1;
    }

    .pm-empty {
        display: flex;
        min-height: 56vh;
        padding: 2.5rem 1.25rem;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
    }

    .pm-empty-icon {
        display: grid;
        width: 68px;
        height: 68px;
        margin-bottom: 1rem;
        place-items: center;
        border-radius: 22px;
        background: #edf7f1;
        color: #237a56;
        font-size: 1.65rem;
    }

    .pm-empty h2 {
        margin: 0;
        color: #1d2924;
        font-size: 1.05rem;
        font-weight: 800;
    }

    .pm-empty p {
        max-width: 28rem;
        margin: .65rem 0 1.25rem;
        color: #758078;
        font-size: .88rem;
        line-height: 1.9;
    }

    .pm-empty-action {
        display: inline-flex;
        min-height: 46px;
        padding: .65rem 1.15rem;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        background: #237a56;
        color: #fff;
        font-size: .9rem;
        font-weight: 750;
        text-decoration: none;
    }

    @media (min-width: 769px) {
        .pm-list-shell {
            max-width: 900px;
            min-height: auto;
            margin: 1.5rem auto 2rem;
            overflow: hidden;
            border: 1px solid #e2e9e5;
            border-radius: 22px;
            box-shadow: 0 12px 34px rgba(27, 48, 38, .07);
        }

        .pm-list-header {
            position: relative;
            padding: 1.15rem 1.4rem;
        }

        .pm-list-title {
            font-size: 1.3rem;
        }

        .pm-list-subtitle {
            font-size: .84rem;
        }

        .pm-conversation-row {
            grid-template-columns: 58px minmax(0, 1fr) auto;
            min-height: 88px;
            padding: .8rem 1.4rem;
            gap: 1rem;
            transition: background-color .16s ease;
        }

        .pm-conversation-row:hover {
            background: #f7faf8;
        }

        .pm-avatar-wrap,
        .pm-avatar {
            width: 58px;
            height: 58px;
        }

        .pm-conversation-name {
            font-size: 1rem;
        }

        .pm-conversation-preview {
            font-size: .9rem;
        }
    }
</style>
@endpush

@section('content')
<section class="pm-list-shell" data-private-messaging-list aria-labelledby="private-messaging-title">
    <header class="pm-list-header">
        <div class="pm-list-heading">
            <h1 class="pm-list-title" id="private-messaging-title">گفتگوهای خصوصی</h1>
            <p class="pm-list-subtitle">پیام‌ها و گفتگوهای مستقیم شما</p>
        </div>
        <a href="{{ route('chat-requests.index', ['section' => 'requests']) }}" class="pm-request-link" aria-label="مشاهده درخواست‌های گفتگو">
            <i class="fas fa-user-plus" aria-hidden="true"></i>
            <span class="d-none d-sm-inline ms-1">درخواست‌ها</span>
        </a>
    </header>

    @if($conversations->isEmpty())
        <div class="pm-empty">
            <div class="pm-empty-icon" aria-hidden="true"><i class="far fa-comments"></i></div>
            <h2>هنوز گفتگویی ندارید</h2>
            <p>برای شروع یک گفتگوی خصوصی، از پروفایل عضو موردنظر درخواست گفتگو بفرستید یا درخواست‌های دریافتی خود را بررسی کنید.</p>
            <a href="{{ route('chat-requests.index', ['section' => 'requests']) }}" class="pm-empty-action">مشاهده درخواست‌ها</a>
        </div>
    @else
        <ul class="pm-conversation-list" role="list">
            @foreach($conversations as $conversation)
                @php
                    $otherUser = $conversation->users->firstWhere('id', '!=', auth()->id());
                    $lastMessage = $conversation->messages->first();
                    $unreadCount = (int) ($conversation->unread_count ?? 0);
                @endphp
                <li>
                    <a href="{{ route('private-chats.show', $conversation->id) }}"
                       class="pm-conversation-row {{ $unreadCount > 0 ? 'is-unread' : '' }}"
                       aria-label="گفتگو با {{ $otherUser?->fullName() ?? 'عضو EarthCoop' }}{{ $unreadCount > 0 ? '، ' . $unreadCount . ' پیام خوانده‌نشده' : '' }}">
                        <span class="pm-avatar-wrap" aria-hidden="true">
                            <img src="{{ $otherUser && $otherUser->avatar ? asset('images/users/' . $otherUser->avatar) : asset('images/default-avatar.png') }}"
                                 alt=""
                                 class="pm-avatar">
                        </span>

                        <span class="pm-conversation-main">
                            <span class="pm-conversation-name">{{ $otherUser?->fullName() ?? 'گفتگوی خصوصی' }}</span>
                            <span class="pm-conversation-preview">
                                @if($lastMessage)
                                    @if((int) $lastMessage->sender_id === (int) auth()->id())
                                        <span>شما: </span>
                                    @endif
                                    {{ \Illuminate\Support\Str::limit($lastMessage->message, 88) }}
                                @else
                                    هنوز پیامی ردوبدل نشده است.
                                @endif
                            </span>
                        </span>

                        <span class="pm-conversation-side">
                            <span class="pm-conversation-time">
                                {{ $lastMessage ? $lastMessage->created_at->diffForHumans(null, true) : 'جدید' }}
                            </span>
                            @if($unreadCount > 0)
                                <span class="pm-unread-badge" data-unread-count="{{ $unreadCount }}" aria-hidden="true">
                                    {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                                </span>
                            @endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endsection
