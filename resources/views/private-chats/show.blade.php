@extends('layouts.unified')

@section('title', 'گفتگوی خصوصی - ' . config('app.name', 'EarthCoop'))

@push('styles')
<style>
    .pm-chat-page {
        --pm-chat-border: #e2e9e5;
        --pm-chat-muted: #738078;
        --pm-chat-green: #237a56;
        --pm-chat-green-dark: #1b6646;
        --pm-chat-bg: #f4f7f5;
        direction: rtl;
        width: 100%;
        min-height: calc(100dvh - 5rem);
        margin: 0;
        background: var(--pm-chat-bg);
    }

    .pm-chat-shell {
        position: relative;
        display: grid;
        grid-template-rows: auto minmax(0, 1fr) auto;
        width: 100%;
        min-height: calc(100dvh - 5rem);
        overflow: hidden;
        background: #fff;
    }

    .pm-chat-header {
        position: sticky;
        top: 0;
        z-index: 30;
        display: grid;
        grid-template-columns: 44px minmax(0, 1fr) 44px;
        align-items: center;
        gap: .65rem;
        min-height: 64px;
        padding: .55rem .7rem;
        border-bottom: 1px solid var(--pm-chat-border);
        background: rgba(255, 255, 255, .97);
        backdrop-filter: blur(12px);
    }

    .pm-chat-back,
    .pm-chat-more {
        display: inline-flex;
        width: 44px;
        height: 44px;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 14px;
        background: transparent;
        color: #4f5f56;
        text-decoration: none;
    }

    .pm-chat-more {
        visibility: hidden;
    }

    .pm-chat-person {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: .65rem;
    }

    .pm-chat-avatar {
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        border: 1px solid #dfe7e2;
        border-radius: 50%;
        object-fit: cover;
        background: #edf2ef;
    }

    .pm-chat-person-text {
        min-width: 0;
    }

    .pm-chat-name {
        display: block;
        overflow: hidden;
        color: #1d2924;
        font-size: .96rem;
        font-weight: 800;
        line-height: 1.45;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pm-chat-context {
        display: block;
        margin-top: .08rem;
        color: #87938c;
        font-size: .68rem;
        line-height: 1.4;
    }

    .pm-chat-status-message {
        margin: .55rem .7rem 0;
        padding: .6rem .75rem;
        border: 1px solid #d6ebdf;
        border-radius: 12px;
        background: #eef8f2;
        color: #2a674b;
        font-size: .8rem;
    }

    .pm-chat-timeline-wrap {
        position: relative;
        min-height: 0;
        overflow: hidden;
        background: var(--pm-chat-bg);
    }

    .pm-chat-older {
        position: absolute;
        top: .55rem;
        left: 50%;
        z-index: 12;
        transform: translateX(-50%);
    }

    .pm-chat-older-btn {
        min-height: 38px;
        padding: .45rem .8rem;
        border: 1px solid #dfe7e2;
        border-radius: 999px;
        background: rgba(255, 255, 255, .94);
        color: #526159;
        font-family: inherit;
        font-size: .72rem;
        font-weight: 700;
        box-shadow: 0 4px 14px rgba(31, 52, 42, .07);
    }

    .pm-chat-messages {
        height: 100%;
        min-height: 0;
        overflow-y: auto;
        overscroll-behavior: contain;
        padding: 1.1rem .7rem 1.5rem;
        scroll-behavior: smooth;
        scrollbar-gutter: stable;
    }

    .pm-message {
        position: relative;
        display: flex;
        width: 100%;
        margin: .22rem 0;
        align-items: flex-end;
        gap: .4rem;
    }

    .pm-message.sent {
        justify-content: flex-start;
    }

    .pm-message.received {
        justify-content: flex-end;
    }

    .pm-message-avatar {
        width: 28px;
        height: 28px;
        flex: 0 0 28px;
        margin-bottom: .2rem;
        border-radius: 50%;
        object-fit: cover;
        background: #e6ece8;
    }

    .pm-message-body {
        display: flex;
        max-width: min(84%, 34rem);
        min-width: 0;
        flex-direction: column;
    }

    .pm-message.sent .pm-message-body {
        align-items: flex-start;
    }

    .pm-message.received .pm-message-body {
        align-items: flex-end;
    }

    .pm-message-sender {
        margin: 0 .42rem .18rem;
        color: #7c8982;
        font-size: .67rem;
        line-height: 1.3;
    }

    .pm-message-bubble {
        max-width: 100%;
        padding: .58rem .76rem .48rem;
        border-radius: 17px;
        overflow-wrap: anywhere;
        white-space: pre-wrap;
        font-size: .91rem;
        line-height: 1.8;
        box-shadow: 0 1px 2px rgba(28, 45, 37, .04);
    }

    .pm-message.sent .pm-message-bubble {
        border-bottom-right-radius: 6px;
        background: #dff3e7;
        color: #173c2c;
    }

    .pm-message.received .pm-message-bubble {
        border: 1px solid #e3e9e6;
        border-bottom-left-radius: 6px;
        background: #fff;
        color: #25312b;
    }

    .pm-message-meta-row {
        display: flex;
        min-height: 24px;
        margin: .15rem .25rem 0;
        align-items: center;
        gap: .35rem;
        color: #87938c;
        font-size: .64rem;
        line-height: 1;
    }

    .pm-message-time {
        white-space: nowrap;
    }

    .pm-read-receipt {
        display: inline-flex;
        min-width: 21px;
        align-items: center;
        justify-content: center;
        color: #8a9690;
        font-size: .72rem;
        letter-spacing: -3px;
        direction: ltr;
    }

    .pm-read-receipt.is-read {
        color: var(--pm-chat-green);
    }

    .pm-message-tools {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: .1rem;
    }

    .pm-message-tool {
        display: inline-flex;
        width: 30px;
        height: 30px;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 9px;
        background: transparent;
        color: #7c8982;
        font-size: .75rem;
        cursor: pointer;
    }

    .pm-reaction-trigger-wrap {
        position: relative;
    }

    .reaction-picker {
        position: absolute;
        bottom: calc(100% + 5px);
        right: 0;
        z-index: 50;
        display: none;
        width: max-content;
        max-width: calc(100vw - 2rem);
        padding: .3rem;
        gap: .1rem;
        border: 1px solid #e0e7e3;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 8px 24px rgba(30, 49, 40, .13);
    }

    .reaction-picker.show {
        display: flex;
    }

    .reaction-picker-btn {
        display: inline-flex;
        width: 36px;
        height: 36px;
        padding: 0;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 10px;
        background: transparent;
        font-size: 1.08rem;
        cursor: pointer;
    }

    .message-reactions-summary {
        display: flex;
        max-width: 100%;
        margin: -.12rem .35rem 0;
        flex-wrap: wrap;
        gap: .2rem;
        z-index: 2;
    }

    .message-reaction-summary-chip {
        display: inline-flex;
        min-height: 24px;
        padding: .16rem .42rem;
        align-items: center;
        gap: .22rem;
        border: 1px solid #dfe7e2;
        border-radius: 999px;
        background: #fff;
        color: #5f6e66;
        font-size: .68rem;
        cursor: pointer;
    }

    .pm-chat-empty {
        display: flex;
        min-height: 100%;
        padding: 4rem 1.25rem;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #8a9690;
        text-align: center;
    }

    .pm-chat-empty i {
        margin-bottom: .8rem;
        color: #9fb9ab;
        font-size: 2rem;
    }

    .pm-new-message {
        position: absolute;
        right: 50%;
        bottom: .8rem;
        z-index: 15;
        display: none;
        min-height: 38px;
        padding: .45rem .75rem;
        transform: translateX(50%);
        align-items: center;
        gap: .35rem;
        border: 0;
        border-radius: 999px;
        background: var(--pm-chat-green);
        color: #fff;
        font-family: inherit;
        font-size: .75rem;
        box-shadow: 0 5px 16px rgba(35, 122, 86, .24);
    }

    .pm-new-message.active {
        display: inline-flex;
    }

    .pm-typing {
        position: absolute;
        right: .8rem;
        bottom: .5rem;
        z-index: 14;
        display: none;
        padding: .32rem .58rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, .93);
        color: #78857e;
        font-size: .68rem;
        box-shadow: 0 2px 8px rgba(28, 45, 37, .05);
    }

    .pm-typing.active {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
    }

    .pm-chat-composer {
        position: sticky;
        bottom: 0;
        z-index: 30;
        padding: .5rem .55rem calc(.5rem + env(safe-area-inset-bottom));
        border-top: 1px solid var(--pm-chat-border);
        background: rgba(255, 255, 255, .98);
        backdrop-filter: blur(12px);
    }

    .pm-composer-form {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 46px;
        align-items: end;
        gap: .45rem;
    }

    .pm-message-input {
        display: block;
        width: 100%;
        min-height: 46px;
        max-height: 132px;
        padding: .7rem .82rem;
        overflow-y: auto;
        border: 1px solid #dde5e1;
        border-radius: 16px;
        outline: 0;
        background: #f7f9f8;
        color: #26322c;
        font: inherit;
        font-size: .9rem;
        line-height: 1.55;
        resize: none;
    }

    .pm-message-input:focus {
        border-color: #aacdbb;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(35, 122, 86, .09);
    }

    .pm-send-btn {
        display: inline-flex;
        width: 46px;
        height: 46px;
        padding: 0;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 15px;
        background: var(--pm-chat-green);
        color: #fff;
        cursor: pointer;
    }

    .pm-send-btn:disabled {
        opacity: .5;
        cursor: wait;
    }

    .pm-chat-back:focus-visible,
    .pm-message-tool:focus-visible,
    .reaction-picker-btn:focus-visible,
    .message-reaction-summary-chip:focus-visible,
    .pm-chat-older-btn:focus-visible,
    .pm-new-message:focus-visible,
    .pm-send-btn:focus-visible {
        outline: 3px solid rgba(35, 122, 86, .24);
        outline-offset: 2px;
    }

    .report-modal-overlay {
        position: fixed;
        inset: 0;
        z-index: 1100;
        display: none;
        padding: 1rem;
        align-items: flex-end;
        justify-content: center;
        background: rgba(18, 29, 24, .52);
    }

    .report-modal-overlay.show {
        display: flex;
    }

    .report-modal {
        width: 100%;
        max-width: 32rem;
        max-height: min(82dvh, 42rem);
        padding: 1rem;
        overflow-y: auto;
        border-radius: 22px 22px 16px 16px;
        background: #fff;
        box-shadow: 0 22px 60px rgba(15, 28, 21, .2);
    }

    .report-modal h3 {
        margin: 0;
        color: #233029;
        font-size: 1rem;
        font-weight: 800;
    }

    .report-modal-close {
        display: inline-flex;
        width: 40px;
        height: 40px;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 12px;
        background: #f3f6f4;
        color: #5d6b64;
    }

    .report-reason-option {
        display: flex;
        min-height: 46px;
        margin-bottom: .45rem;
        padding: .65rem .75rem;
        align-items: center;
        gap: .55rem;
        border: 1px solid #e2e8e5;
        border-radius: 13px;
        color: #45534b;
        cursor: pointer;
    }

    .report-reason-option.selected {
        border-color: #b7d6c6;
        background: #eef7f2;
    }

    .report-description {
        width: 100%;
        min-height: 86px;
        padding: .65rem .75rem;
        border: 1px solid #dfe6e2;
        border-radius: 13px;
        font: inherit;
        font-size: .85rem;
        resize: vertical;
    }

    .report-actions {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: .5rem;
        margin-top: .8rem;
    }

    .report-action {
        min-height: 44px;
        padding: .55rem .8rem;
        border: 0;
        border-radius: 12px;
        font-family: inherit;
        font-size: .82rem;
        font-weight: 750;
    }

    .report-action--primary { background: var(--pm-chat-green); color: #fff; }
    .report-action--secondary { background: #f1f4f2; color: #56645d; }

    @media (min-width: 769px) {
        .pm-chat-page {
            min-height: auto;
            padding: 1.25rem 1rem 2rem;
        }

        .pm-chat-shell {
            max-width: 920px;
            height: min(78dvh, 820px);
            min-height: 620px;
            margin: 0 auto;
            border: 1px solid var(--pm-chat-border);
            border-radius: 24px;
            box-shadow: 0 18px 48px rgba(26, 47, 37, .09);
        }

        .pm-chat-header {
            position: relative;
            padding-inline: 1rem;
        }

        .pm-chat-messages {
            padding: 1.4rem 1.25rem 1.7rem;
        }

        .pm-message-body {
            max-width: min(66%, 36rem);
        }

        .pm-message-tools {
            opacity: .35;
            transition: opacity .15s ease;
        }

        .pm-message:hover .pm-message-tools,
        .pm-message:focus-within .pm-message-tools {
            opacity: 1;
        }

        .pm-chat-composer {
            position: relative;
            padding: .65rem .8rem;
        }

        .report-modal-overlay {
            align-items: center;
        }

        .report-modal {
            border-radius: 20px;
        }
    }
</style>
@endpush

@section('content')
@php
    $otherUser = $conversation->users->firstWhere('id', '!=', auth()->id());
@endphp
<section class="pm-chat-page" data-private-conversation data-conversation-id="{{ $conversation->id }}">
    <div class="pm-chat-shell">
        <header class="pm-chat-header" data-conversation-header>
            <a href="{{ route('chat-requests.index', ['section' => 'conversations']) }}" class="pm-chat-back" aria-label="بازگشت به گفتگوها">
                <i class="fas fa-arrow-right" aria-hidden="true"></i>
            </a>

            <div class="pm-chat-person">
                <img src="{{ $otherUser && $otherUser->avatar ? asset('images/users/' . $otherUser->avatar) : asset('images/default-avatar.png') }}"
                     alt=""
                     class="pm-chat-avatar">
                <div class="pm-chat-person-text">
                    <span class="pm-chat-name">{{ $otherUser?->fullName() ?? 'گفتگوی خصوصی' }}</span>
                    <span class="pm-chat-context">گفتگوی خصوصی</span>
                </div>
            </div>

            <button type="button" class="pm-chat-more" tabindex="-1" aria-hidden="true"><i class="fas fa-ellipsis-v"></i></button>
        </header>

        @if(session('status'))
            <div class="pm-chat-status-message" role="status">{{ session('status') }}</div>
        @endif

        <div class="pm-chat-timeline-wrap">
            @if(!empty($hasMoreMessages) && $hasMoreMessages)
                <div class="pm-chat-older">
                    <button type="button" class="pm-chat-older-btn" id="load-older-messages-btn">پیام‌های قدیمی‌تر</button>
                </div>
            @endif

            <div class="pm-chat-messages" id="chat-messages" aria-live="polite" aria-label="پیام‌های گفتگو">
                @forelse($conversation->messages as $message)
                    @php
                        $isSent = (int) $message->sender_id === (int) auth()->id();
                        $reactionSummary = $message->reactions->groupBy('reaction_type')->map(function($group) {
                            return [
                                'count' => $group->count(),
                                'users' => $group->map(fn($reaction) => $reaction->user ? $reaction->user->fullName() : '')
                                    ->filter()->unique()->values()->toArray(),
                            ];
                        });
                    @endphp
                    <div class="pm-message {{ $isSent ? 'sent' : 'received' }}"
                         data-message-id="{{ $message->id }}"
                         data-created-at="{{ $message->created_at->timestamp }}">
                        @if(!$isSent)
                            <img src="{{ $message->sender->avatar ? asset('images/users/' . $message->sender->avatar) : asset('images/default-avatar.png') }}"
                                 alt=""
                                 class="pm-message-avatar">
                        @endif

                        <div class="pm-message-body">
                            @if(!$isSent)
                                <div class="pm-message-sender">{{ $message->sender->fullName() }}</div>
                            @endif

                            <div class="pm-message-bubble">{{ $message->message }}</div>

                            <div class="message-reactions-summary" data-message-reactions="{{ $message->id }}">
                                @foreach($reactionSummary as $reactionType => $data)
                                    @if($data['count'] > 0)
                                        <button type="button" class="message-reaction-summary-chip reaction-chip"
                                                data-message-id="{{ $message->id }}"
                                                data-reaction="{{ $reactionType }}"
                                                title="{{ implode(', ', $data['users']) }}">
                                            <span>{{ $reactionType }}</span>
                                            <span>{{ $data['count'] }}</span>
                                        </button>
                                    @endif
                                @endforeach
                            </div>

                            <div class="pm-message-meta-row">
                                <time class="pm-message-time" datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('H:i') }}</time>

                                @if($isSent)
                                    <span class="pm-read-receipt {{ $message->read_at ? 'is-read' : '' }}"
                                          data-read-receipt
                                          data-message-id="{{ $message->id }}"
                                          aria-label="{{ $message->read_at ? 'خوانده شده' : 'ارسال شده' }}">
                                        {{ $message->read_at ? '✓✓' : '✓' }}
                                    </span>
                                @endif

                                <span class="pm-message-tools">
                                    <span class="pm-reaction-trigger-wrap">
                                        <button type="button" class="pm-message-tool reaction-trigger-btn" data-message-id="{{ $message->id }}" aria-label="واکنش به پیام">
                                            <i class="far fa-smile" aria-hidden="true"></i>
                                        </button>
                                        <span class="reaction-picker" data-picker-for="{{ $message->id }}">
                                            @foreach(['👍', '❤️', '😂', '😮', '😢', '🔥', '👎'] as $reaction)
                                                <button type="button" class="reaction-picker-btn" data-message-id="{{ $message->id }}" data-reaction="{{ $reaction }}" aria-label="واکنش {{ $reaction }}">{{ $reaction }}</button>
                                            @endforeach
                                        </span>
                                    </span>
                                    @if(!$isSent)
                                        <button type="button" class="pm-message-tool report-btn"
                                                data-message-id="{{ $message->id }}"
                                                data-message-sender="{{ $message->sender_id }}"
                                                aria-label="گزارش پیام">
                                            <i class="far fa-flag" aria-hidden="true"></i>
                                        </button>
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="pm-chat-empty">
                        <i class="far fa-comments" aria-hidden="true"></i>
                        <div>هنوز پیامی ردوبدل نشده است.</div>
                        <small>می‌توانید اولین پیام را بفرستید.</small>
                    </div>
                @endforelse
            </div>

            <button type="button" class="pm-new-message" id="new-message-indicator">
                <i class="fas fa-arrow-down" aria-hidden="true"></i>
                <span><span id="new-message-count">0</span> پیام جدید</span>
            </button>

            <div class="pm-typing" id="typing-indicator" aria-live="polite">
                <span>در حال نوشتن</span><span aria-hidden="true">…</span>
            </div>
        </div>

        <footer class="pm-chat-composer" data-conversation-composer>
            <form id="chat-form" class="pm-composer-form" action="{{ route('private-chats.send', $conversation->id) }}" method="POST">
                @csrf
                <textarea name="message"
                          id="message-input"
                          class="pm-message-input"
                          rows="1"
                          maxlength="5000"
                          autocomplete="off"
                          aria-label="متن پیام"
                          placeholder="پیام بنویسید…"
                          required>{{ old('message') }}</textarea>
                <button type="submit" class="pm-send-btn" id="send-btn" aria-label="ارسال پیام">
                    <i class="fas fa-paper-plane" aria-hidden="true"></i>
                </button>
            </form>
            @error('message')
                <div class="text-danger small mt-1" role="alert">{{ $message }}</div>
            @enderror
        </footer>
    </div>

    <div class="report-modal-overlay" id="report-modal" role="dialog" aria-modal="true" aria-labelledby="report-modal-title">
        <div class="report-modal">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 id="report-modal-title">گزارش پیام</h3>
                <button type="button" class="report-modal-close" data-close-report aria-label="بستن">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>

            <form id="report-form">
                @csrf
                <input type="hidden" name="message_id" id="report-message-id">
                <p class="text-muted small mb-3">دلیل گزارش را انتخاب کنید:</p>

                <div class="report-reasons">
                    <label class="report-reason-option"><input type="radio" name="reason" value="spam" required> اسپم و تبلیغات</label>
                    <label class="report-reason-option"><input type="radio" name="reason" value="harassment"> آزار و اذیت</label>
                    <label class="report-reason-option"><input type="radio" name="reason" value="inappropriate_content"> محتوای نامناسب</label>
                    <label class="report-reason-option"><input type="radio" name="reason" value="abuse"> توهین</label>
                    <label class="report-reason-option"><input type="radio" name="reason" value="other"> سایر</label>
                </div>

                <textarea name="description" class="report-description" maxlength="1000" placeholder="توضیحات بیشتر (اختیاری)"></textarea>

                <div class="report-actions">
                    <button type="submit" class="report-action report-action--primary" id="report-submit-btn">ارسال گزارش</button>
                    <button type="button" class="report-action report-action--secondary" data-close-report>انصراف</button>
                </div>
            </form>

            <div id="report-success" class="text-center py-3" style="display:none" role="status">
                <i class="fas fa-check-circle text-success fa-2x" aria-hidden="true"></i>
                <p class="mt-2 mb-0 text-success">گزارش ثبت شد و توسط مدیریت بررسی خواهد شد.</p>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatMessages = document.getElementById('chat-messages');
    const chatForm = document.getElementById('chat-form');
    const messageInput = document.getElementById('message-input');
    const sendBtn = document.getElementById('send-btn');
    const typingIndicator = document.getElementById('typing-indicator');
    const newMessageIndicator = document.getElementById('new-message-indicator');
    const newMessageCount = document.getElementById('new-message-count');
    const loadOlderMessagesBtn = document.getElementById('load-older-messages-btn');
    const reportModal = document.getElementById('report-modal');
    const reportForm = document.getElementById('report-form');

    const conversationId = {{ $conversation->id }};
    const currentUserId = {{ auth()->id() }};
    const messagesUrl = @json(route('private-chats.messages', $conversation->id));
    const sendUrl = @json(route('private-chats.send', $conversation->id));
    const reactionUrl = @json(route('messages.reactions.store'));
    const reportUrl = @json(route('private-chats.report'));
    const usersAssetBase = @json(asset('images/users'));
    const defaultAvatar = @json(asset('images/default-avatar.png'));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    let lastMessageId = {{ $conversation->messages->max('id') ?? 0 }};
    let newMessagesCountValue = 0;
    let isAtBottom = true;
    let pollingInterval = null;
    let typingTimeout = null;
    let loadingOlderMessages = false;
    let echoChannel = null;

    if (!chatMessages || !chatForm || !messageInput || !sendBtn) {
        return;
    }

    function scrollToBottom(behavior = 'auto') {
        chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior });
        newMessageIndicator?.classList.remove('active');
        newMessagesCountValue = 0;
        if (newMessageCount) newMessageCount.textContent = '0';
    }

    requestAnimationFrame(() => scrollToBottom('auto'));

    chatMessages.addEventListener('scroll', function() {
        const threshold = 100;
        isAtBottom = (chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight) < threshold;
        if (isAtBottom && newMessagesCountValue > 0) {
            newMessageIndicator?.classList.remove('active');
            newMessagesCountValue = 0;
            if (newMessageCount) newMessageCount.textContent = '0';
        }
    });

    newMessageIndicator?.addEventListener('click', () => scrollToBottom('smooth'));

    chatForm.addEventListener('submit', function(event) {
        event.preventDefault();
        const message = messageInput.value.trim();
        if (!message) return;

        sendBtn.disabled = true;
        fetch(sendUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ message })
        })
        .then(async response => {
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Send failed');
            return data;
        })
        .then(data => {
            if (!data.success) return;
            addMessageToChat(data.message, true);
            lastMessageId = Math.max(lastMessageId, Number(data.message.id) || 0);
            messageInput.value = '';
            resizeComposer();
            scrollToBottom('smooth');
        })
        .catch(error => {
            console.error('Error sending private message:', error);
            alert('خطا در ارسال پیام. لطفاً دوباره تلاش کنید.');
        })
        .finally(() => {
            sendBtn.disabled = false;
            messageInput.focus();
        });
    });

    function receiptHtml(messageData, isSent) {
        if (!isSent) return '';
        const isRead = Boolean(messageData.is_read || messageData.read_at);
        return `<span class="pm-read-receipt ${isRead ? 'is-read' : ''}" data-read-receipt data-message-id="${messageData.id}" aria-label="${isRead ? 'خوانده شده' : 'ارسال شده'}">${isRead ? '✓✓' : '✓'}</span>`;
    }

    function addMessageToChat(messageData, isSent, prepend = false) {
        const emptyChat = chatMessages.querySelector('.pm-chat-empty');
        if (emptyChat) emptyChat.remove();

        const existing = chatMessages.querySelector(`[data-message-id="${messageData.id}"]`);
        if (existing) {
            if (messageData.reaction_summary) updateReactionUI(messageData.id, messageData.reaction_summary);
            if (isSent && (messageData.is_read || messageData.read_at)) markReceiptRead(messageData.id);
            return;
        }

        const messageEl = document.createElement('div');
        messageEl.className = `pm-message ${isSent ? 'sent' : 'received'}`;
        messageEl.dataset.messageId = messageData.id;
        messageEl.dataset.createdAt = String(new Date(messageData.created_at).getTime() / 1000);

        const avatarUrl = messageData.sender.avatar ? `${usersAssetBase}/${messageData.sender.avatar}` : defaultAvatar;
        const reactions = buildReactionSummaryHtml(messageData.reaction_summary || {}, messageData.id);
        const reportButton = !isSent ? `<button type="button" class="pm-message-tool report-btn" data-message-id="${messageData.id}" data-message-sender="${messageData.sender.id}" aria-label="گزارش پیام"><i class="far fa-flag" aria-hidden="true"></i></button>` : '';

        messageEl.innerHTML = `
            ${!isSent ? `<img src="${avatarUrl}" alt="" class="pm-message-avatar">` : ''}
            <div class="pm-message-body">
                ${!isSent ? `<div class="pm-message-sender">${escapeHtml(messageData.sender.name)}</div>` : ''}
                <div class="pm-message-bubble">${escapeHtml(messageData.message)}</div>
                ${reactions}
                <div class="pm-message-meta-row">
                    <span class="pm-message-time">${formatTime(messageData.created_at)}</span>
                    ${receiptHtml(messageData, isSent)}
                    <span class="pm-message-tools">
                        <span class="pm-reaction-trigger-wrap">
                            <button type="button" class="pm-message-tool reaction-trigger-btn" data-message-id="${messageData.id}" aria-label="واکنش به پیام"><i class="far fa-smile" aria-hidden="true"></i></button>
                            <span class="reaction-picker" data-picker-for="${messageData.id}">
                                ${['👍','❤️','😂','😮','😢','🔥','👎'].map(reaction => `<button type="button" class="reaction-picker-btn" data-message-id="${messageData.id}" data-reaction="${reaction}" aria-label="واکنش ${reaction}">${reaction}</button>`).join('')}
                            </span>
                        </span>
                        ${reportButton}
                    </span>
                </div>
            </div>`;

        if (prepend) {
            chatMessages.insertBefore(messageEl, chatMessages.firstChild);
        } else {
            chatMessages.appendChild(messageEl);
        }

        if (!prepend && isAtBottom) {
            scrollToBottom('smooth');
        } else if (!prepend) {
            newMessagesCountValue++;
            if (newMessageCount) newMessageCount.textContent = String(newMessagesCountValue);
            newMessageIndicator?.classList.add('active');
        }
    }

    function buildReactionSummaryHtml(reactions, messageId) {
        const entries = Object.entries(reactions || {});
        return `<div class="message-reactions-summary" data-message-reactions="${messageId}">${entries.map(([type, data]) => `
            <button type="button" class="message-reaction-summary-chip reaction-chip" data-message-id="${messageId}" data-reaction="${type}" title="${escapeHtml((data.users || []).join(', '))}">
                <span>${type}</span><span>${data.count}</span>
            </button>`).join('')}</div>`;
    }

    function subscribeToPrivateChat() {
        if (!window.Echo) {
            startPolling();
            return;
        }

        try {
            echoChannel = window.Echo.private(`private-chat.${conversationId}`);
            echoChannel.listen('.private-message.created', function(event) {
                const msg = event.message;
                if (!msg || !msg.id) return;
                addMessageToChat(msg, Number(msg.sender.id) === Number(currentUserId));
                lastMessageId = Math.max(lastMessageId, Number(msg.id) || 0);
            });

            echoChannel.listen('.private-message.reactions.updated', function(event) {
                if (event?.message_id) updateReactionUI(event.message_id, event.reactions || {});
            });

            echoChannel.listenForWhisper('typing', function(payload) {
                if (!payload || Number(payload.user_id) === Number(currentUserId) || document.hidden) return;
                typingIndicator?.classList.add('active');
                clearTimeout(typingTimeout);
                typingTimeout = setTimeout(() => typingIndicator?.classList.remove('active'), 1400);
            });

            if (pollingInterval) {
                clearInterval(pollingInterval);
                pollingInterval = null;
            }
        } catch (error) {
            console.warn('Echo subscription failed, falling back to polling.', error);
            startPolling();
        }
    }

    function fetchNewMessages() {
        if (document.hidden) return;
        fetch(`${messagesUrl}?after_id=${lastMessageId}`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        })
        .then(response => response.json())
        .then(data => {
            (data.messages || []).forEach(msg => {
                lastMessageId = Math.max(lastMessageId, Number(msg.id) || 0);
                addMessageToChat(msg, Number(msg.sender.id) === Number(currentUserId));
            });
        })
        .catch(error => console.error('Error polling private messages:', error));
    }

    function startPolling() {
        if (pollingInterval) return;
        pollingInterval = setInterval(function() {
            if (window.Echo && echoChannel) return;
            fetchNewMessages();
        }, 3000);
    }

    if (window.Echo) subscribeToPrivateChat(); else startPolling();

    document.addEventListener('visibilitychange', function() {
        if (!document.hidden && (!window.Echo || !echoChannel)) fetchNewMessages();
    });

    loadOlderMessagesBtn?.addEventListener('click', loadOlderMessages);

    function loadOlderMessages() {
        if (loadingOlderMessages) return;
        const firstMessage = chatMessages.querySelector('.pm-message');
        if (!firstMessage?.dataset.messageId) return;

        loadingOlderMessages = true;
        loadOlderMessagesBtn.disabled = true;
        const oldHeight = chatMessages.scrollHeight;

        fetch(`${messagesUrl}?before_id=${firstMessage.dataset.messageId}&limit=50`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        })
        .then(response => response.json())
        .then(data => {
            const messages = data.messages || [];
            [...messages].reverse().forEach(msg => addMessageToChat(msg, Number(msg.sender.id) === Number(currentUserId), true));
            chatMessages.scrollTop += chatMessages.scrollHeight - oldHeight;
            if (!data.has_more && loadOlderMessagesBtn) loadOlderMessagesBtn.style.display = 'none';
        })
        .catch(error => console.error('Error loading older private messages:', error))
        .finally(() => {
            loadingOlderMessages = false;
            if (loadOlderMessagesBtn) loadOlderMessagesBtn.disabled = false;
        });
    }

    function resizeComposer() {
        messageInput.style.height = 'auto';
        messageInput.style.height = `${Math.min(messageInput.scrollHeight, 132)}px`;
    }

    messageInput.addEventListener('input', function() {
        resizeComposer();
        // Only the remote participant should see a typing indicator.
        if (window.Echo && echoChannel) {
            echoChannel.whisper('typing', { user_id: currentUserId });
        }
    });

    messageInput.addEventListener('keydown', function(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            chatForm.requestSubmit();
        }
    });

    function formatTime(timestamp) {
        const date = new Date(timestamp);
        return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function markReceiptRead(messageId) {
        const receipt = document.querySelector(`[data-read-receipt][data-message-id="${messageId}"]`);
        if (!receipt) return;
        receipt.classList.add('is-read');
        receipt.textContent = '✓✓';
        receipt.setAttribute('aria-label', 'خوانده شده');
    }

    document.addEventListener('click', function(event) {
        const trigger = event.target.closest('.reaction-trigger-btn');
        if (trigger) {
            event.stopPropagation();
            const picker = document.querySelector(`.reaction-picker[data-picker-for="${trigger.dataset.messageId}"]`);
            document.querySelectorAll('.reaction-picker.show').forEach(item => {
                if (item !== picker) item.classList.remove('show');
            });
            picker?.classList.toggle('show');
            return;
        }

        const reactionButton = event.target.closest('.reaction-picker-btn, .reaction-chip');
        if (reactionButton) {
            event.stopPropagation();
            toggleReaction(reactionButton.dataset.messageId, reactionButton.dataset.reaction);
            document.querySelectorAll('.reaction-picker.show').forEach(item => item.classList.remove('show'));
            return;
        }

        if (!event.target.closest('.reaction-picker')) {
            document.querySelectorAll('.reaction-picker.show').forEach(item => item.classList.remove('show'));
        }
    });

    function toggleReaction(messageId, reactionType) {
        fetch(reactionUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ message_id: messageId, reaction_type: reactionType })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) updateReactionUI(messageId, data.reactions || {});
        })
        .catch(error => console.error('Error toggling reaction:', error));
    }

    function updateReactionUI(messageId, reactions) {
        const container = document.querySelector(`.message-reactions-summary[data-message-reactions="${messageId}"]`);
        if (!container) return;
        container.outerHTML = buildReactionSummaryHtml(reactions || {}, messageId);
    }

    let currentReportMessageId = null;

    document.addEventListener('click', function(event) {
        const reportButton = event.target.closest('.report-btn');
        if (reportButton) {
            event.stopPropagation();
            if (Number(reportButton.dataset.messageSender) === Number(currentUserId)) return;
            currentReportMessageId = reportButton.dataset.messageId;
            document.getElementById('report-message-id').value = currentReportMessageId;
            reportForm.style.display = 'block';
            document.getElementById('report-success').style.display = 'none';
            reportForm.reset();
            document.querySelectorAll('.report-reason-option').forEach(option => option.classList.remove('selected'));
            reportModal.classList.add('show');
            reportModal.querySelector('input[name="reason"]')?.focus();
            return;
        }

        if (event.target.closest('[data-close-report]')) closeReportModal();
    });

    function closeReportModal() {
        reportModal?.classList.remove('show');
    }

    reportModal?.addEventListener('click', function(event) {
        if (event.target === reportModal) closeReportModal();
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && reportModal?.classList.contains('show')) closeReportModal();
    });

    document.addEventListener('change', function(event) {
        if (!event.target.matches('.report-reason-option input[type="radio"]')) return;
        document.querySelectorAll('.report-reason-option').forEach(option => option.classList.remove('selected'));
        event.target.closest('.report-reason-option')?.classList.add('selected');
    });

    reportForm?.addEventListener('submit', function(event) {
        event.preventDefault();
        const reason = reportForm.querySelector('input[name="reason"]:checked');
        const description = reportForm.querySelector('textarea[name="description"]')?.value || '';
        if (!reason || !currentReportMessageId) return;

        const submitButton = document.getElementById('report-submit-btn');
        submitButton.disabled = true;

        fetch(reportUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ message_id: currentReportMessageId, reason: reason.value, description })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) throw new Error(data.error || 'Report failed');
            reportForm.style.display = 'none';
            document.getElementById('report-success').style.display = 'block';
            setTimeout(closeReportModal, 1700);
        })
        .catch(error => {
            console.error('Error reporting private message:', error);
            alert('خطا در ارسال گزارش. لطفاً دوباره تلاش کنید.');
        })
        .finally(() => { submitButton.disabled = false; });
    });

    window.addEventListener('beforeunload', function() {
        if (pollingInterval) clearInterval(pollingInterval);
        if (window.Echo && echoChannel && typeof window.Echo.leave === 'function') {
            window.Echo.leave(`private-chat.${conversationId}`);
        }
    });
});
</script>
@endpush
