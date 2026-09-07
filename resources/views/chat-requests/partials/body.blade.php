<style>
    .pm-hub {
        direction: rtl;
        width: 100%;
        max-width: 100%;
        margin: 0 auto;
        background: #fff;
    }

    .pm-hub-tabs,
    .pm-box-tabs {
        display: grid;
        gap: .35rem;
        padding: .45rem;
        margin: 0;
        list-style: none;
        background: #f2f6f4;
        border-radius: 15px;
    }

    .pm-hub-tabs {
        grid-template-columns: 1fr 1fr;
        margin-bottom: .8rem;
    }

    .pm-box-tabs {
        grid-template-columns: 1fr 1fr;
        margin-bottom: .65rem;
    }

    .pm-tab-link {
        display: flex;
        min-height: 44px;
        padding: .55rem .7rem;
        align-items: center;
        justify-content: center;
        gap: .4rem;
        border-radius: 11px;
        color: #637169;
        font-size: .88rem;
        font-weight: 750;
        text-align: center;
        text-decoration: none;
    }

    .pm-tab-link.is-active {
        background: #fff;
        color: #215f48;
        box-shadow: 0 2px 8px rgba(25, 55, 42, .07);
    }

    .pm-tab-count {
        display: inline-flex;
        min-width: 20px;
        height: 20px;
        padding: 0 .3rem;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        background: #e2ece7;
        color: #456055;
        font-size: .69rem;
    }

    .pm-status-strip {
        display: flex;
        gap: .4rem;
        padding: .15rem 0 .75rem;
        overflow-x: auto;
        scrollbar-width: none;
    }

    .pm-status-strip::-webkit-scrollbar { display: none; }

    .pm-status-chip {
        display: inline-flex;
        min-height: 38px;
        padding: .48rem .75rem;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        border: 1px solid #e0e7e3;
        border-radius: 999px;
        background: #fff;
        color: #66746c;
        font-size: .78rem;
        font-weight: 700;
        text-decoration: none;
    }

    .pm-status-chip.is-active {
        border-color: #bfd5ca;
        background: #edf7f1;
        color: #206045;
    }

    .pm-request-list,
    .pm-hub-conversation-list {
        display: flex;
        flex-direction: column;
        gap: .65rem;
    }

    .pm-request-card {
        padding: .9rem;
        border: 1px solid #e3eae6;
        border-radius: 16px;
        background: #fff;
    }

    .pm-request-person {
        display: grid;
        grid-template-columns: 46px minmax(0, 1fr) auto;
        gap: .7rem;
        align-items: center;
    }

    .pm-request-avatar {
        display: block;
        width: 46px;
        height: 46px;
        border: 1px solid #e2e8e5;
        border-radius: 50%;
        object-fit: cover;
        background: #eef2f0;
    }

    .pm-request-identity {
        min-width: 0;
    }

    .pm-request-name {
        display: block;
        overflow: hidden;
        color: #1e2a25;
        font-size: .94rem;
        font-weight: 800;
        line-height: 1.4;
        text-decoration: none;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pm-request-time {
        display: block;
        margin-top: .15rem;
        color: #8b9690;
        font-size: .7rem;
    }

    .pm-request-status {
        display: inline-flex;
        min-height: 28px;
        padding: .3rem .55rem;
        align-items: center;
        border-radius: 999px;
        background: #f1f4f2;
        color: #627068;
        font-size: .69rem;
        font-weight: 750;
        white-space: nowrap;
    }

    .pm-request-status--pending { background: #fff7df; color: #785a15; }
    .pm-request-status--accepted { background: #eaf7ef; color: #236044; }
    .pm-request-status--rejected { background: #f5f1f1; color: #705b5b; }

    .pm-request-message {
        margin: .75rem 0 0;
        padding: .72rem .78rem;
        border-radius: 12px;
        background: #f7f9f8;
        color: #435149;
        font-size: .84rem;
        line-height: 1.85;
        overflow-wrap: anywhere;
    }

    .pm-request-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .55rem;
        margin-top: .8rem;
    }

    .pm-request-actions form { margin: 0; }

    .pm-action {
        display: inline-flex;
        width: 100%;
        min-height: 44px;
        padding: .58rem .8rem;
        align-items: center;
        justify-content: center;
        gap: .4rem;
        border: 1px solid transparent;
        border-radius: 12px;
        font-family: inherit;
        font-size: .84rem;
        font-weight: 750;
        text-decoration: none;
        cursor: pointer;
    }

    .pm-action--primary { background: #237a56; color: #fff; }
    .pm-action--secondary { border-color: #dfe6e2; background: #fff; color: #53635a; }
    .pm-action--full { grid-column: 1 / -1; }

    .pm-empty-state {
        padding: 3rem 1rem;
        color: #7a8780;
        text-align: center;
        font-size: .88rem;
        line-height: 1.9;
    }

    .pm-hub-conversation {
        display: grid;
        grid-template-columns: 50px minmax(0, 1fr) auto;
        gap: .75rem;
        min-height: 72px;
        padding: .72rem .8rem;
        align-items: center;
        border: 1px solid #e7ece9;
        border-radius: 15px;
        color: inherit;
        text-decoration: none;
    }

    .pm-hub-conversation.is-unread { background: #f6fbf8; }
    .pm-hub-conversation img { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; }
    .pm-hub-conversation-main { min-width: 0; }
    .pm-hub-conversation-name { display: block; overflow: hidden; font-size: .92rem; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
    .pm-hub-conversation-preview { display: block; margin-top: .15rem; overflow: hidden; color: #78837d; font-size: .78rem; text-overflow: ellipsis; white-space: nowrap; }
    .pm-hub-conversation-side { display: flex; flex-direction: column; align-items: flex-end; gap: .4rem; color: #8c9791; font-size: .68rem; white-space: nowrap; }
    .pm-hub-unread { display: inline-flex; min-width: 21px; height: 21px; padding: 0 .32rem; align-items: center; justify-content: center; border-radius: 999px; background: #237a56; color: #fff; font-weight: 800; }

    .pm-tab-link:focus-visible,
    .pm-status-chip:focus-visible,
    .pm-request-name:focus-visible,
    .pm-action:focus-visible,
    .pm-hub-conversation:focus-visible {
        outline: 3px solid rgba(35, 122, 86, .23);
        outline-offset: 2px;
    }

    @media (min-width: 769px) {
        .pm-hub {
            max-width: 900px;
            padding: 1rem;
            border: 1px solid #e4ebe7;
            border-radius: 22px;
            box-shadow: 0 12px 32px rgba(27, 48, 38, .06);
        }

        .pm-request-list,
        .pm-hub-conversation-list {
            gap: .8rem;
        }

        .pm-request-card {
            padding: 1.05rem 1.15rem;
        }

        .pm-request-actions {
            display: flex;
            justify-content: flex-end;
        }

        .pm-request-actions form { width: auto; }
        .pm-action { width: auto; min-width: 110px; }
        .pm-action--full { width: auto; }

        .pm-hub-conversation {
            min-height: 82px;
            padding: .85rem 1rem;
            transition: background-color .16s ease, border-color .16s ease;
        }

        .pm-hub-conversation:hover { background: #f7faf8; border-color: #dce7e1; }
    }
</style>

<div class="pm-hub" data-private-messaging-shell>
    <nav aria-label="بخش‌های گفتگوهای خصوصی">
        <ul class="pm-hub-tabs">
            <li>
                <a class="pm-tab-link js-chat-tab-link {{ $section === 'conversations' ? 'is-active' : '' }}"
                   href="{{ route('chat-requests.index', ['section' => 'conversations']) }}">
                    <i class="far fa-comments" aria-hidden="true"></i>
                    <span>گفتگوها</span>
                </a>
            </li>
            <li>
                <a class="pm-tab-link js-chat-tab-link {{ $section === 'requests' ? 'is-active' : '' }}"
                   href="{{ route('chat-requests.index', ['section' => 'requests', 'box' => $box ?? 'received', 'status' => $status]) }}">
                    <i class="far fa-envelope" aria-hidden="true"></i>
                    <span>درخواست‌ها</span>
                    @if(($counts['pending'] ?? 0) > 0)
                        <span class="pm-tab-count" aria-label="{{ $counts['pending'] }} درخواست در انتظار">{{ $counts['pending'] > 99 ? '99+' : $counts['pending'] }}</span>
                    @endif
                </a>
            </li>
        </ul>
    </nav>

    @if($section === 'requests')
        @php
            $activeBox = $box ?? 'received';
            $activeItems = $activeBox === 'sent' ? $sent : $received;
            $activeCounts = $requestCounts[$activeBox] ?? [];
            $statusLabels = [
                'all' => 'همه',
                'pending' => 'در انتظار',
                'accepted' => 'پذیرفته‌شده',
                'rejected' => 'ردشده',
            ];
        @endphp

        <nav aria-label="نوع درخواست گفتگو">
            <ul class="pm-box-tabs">
                <li>
                    <a class="pm-tab-link js-chat-tab-link {{ $activeBox === 'received' ? 'is-active' : '' }}"
                       href="{{ route('chat-requests.index', ['section' => 'requests', 'box' => 'received', 'status' => $status]) }}">
                        دریافتی
                        @if(($requestCounts['received']['pending'] ?? 0) > 0)
                            <span class="pm-tab-count">{{ $requestCounts['received']['pending'] }}</span>
                        @endif
                    </a>
                </li>
                <li>
                    <a class="pm-tab-link js-chat-tab-link {{ $activeBox === 'sent' ? 'is-active' : '' }}"
                       href="{{ route('chat-requests.index', ['section' => 'requests', 'box' => 'sent', 'status' => $status]) }}">
                        ارسالی
                    </a>
                </li>
            </ul>
        </nav>

        <nav class="pm-status-strip" aria-label="فیلتر وضعیت درخواست">
            @foreach($statusLabels as $statusKey => $statusLabel)
                <a class="pm-status-chip js-chat-tab-link {{ $status === $statusKey ? 'is-active' : '' }}"
                   href="{{ route('chat-requests.index', ['section' => 'requests', 'box' => $activeBox, 'status' => $statusKey]) }}">
                    {{ $statusLabel }}
                    @if(isset($activeCounts[$statusKey]))
                        <span class="ms-1">{{ $activeCounts[$statusKey] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div id="chat-panel-content-requests" class="pm-request-list">
            @forelse($activeItems as $requestItem)
                @php
                    $person = $activeBox === 'sent' ? $requestItem->receiver : $requestItem->sender;
                    $statusClass = 'pm-request-status--' . $requestItem->status;
                    $statusText = $statusLabels[$requestItem->status] ?? $requestItem->status;
                @endphp
                <article class="pm-request-card">
                    <div class="pm-request-person">
                        <img src="{{ $person && $person->avatar ? asset('images/users/' . $person->avatar) : asset('images/default-avatar.png') }}"
                             alt=""
                             class="pm-request-avatar">
                        <div class="pm-request-identity">
                            @if($person)
                                <a class="pm-request-name" href="{{ route('profile.member.show', $person->id) }}">{{ $person->fullName() }}</a>
                            @else
                                <span class="pm-request-name">عضو EarthCoop</span>
                            @endif
                            <time class="pm-request-time" datetime="{{ $requestItem->created_at?->toIso8601String() }}">
                                {{ $requestItem->created_at?->diffForHumans() }}
                            </time>
                        </div>
                        <span class="pm-request-status {{ $statusClass }}">{{ $statusText }}</span>
                    </div>

                    @if($requestItem->message)
                        <p class="pm-request-message">{{ $requestItem->message }}</p>
                    @endif

                    @if($activeBox === 'received' && $requestItem->status === 'pending')
                        <div class="pm-request-actions">
                            <form action="{{ route('chat-requests.accept', $requestItem->id) }}" method="POST">
                                @csrf
                                <button class="pm-action pm-action--primary" type="submit">
                                    <i class="fas fa-check" aria-hidden="true"></i> پذیرفتن
                                </button>
                            </form>
                            <form action="{{ route('chat-requests.reject', $requestItem->id) }}" method="POST">
                                @csrf
                                <button class="pm-action pm-action--secondary" type="submit">رد کردن</button>
                            </form>
                        </div>
                    @elseif($requestItem->status === 'accepted' && $requestItem->private_conversation_id)
                        <div class="pm-request-actions">
                            <a class="pm-action pm-action--primary pm-action--full" href="{{ route('private-chats.show', $requestItem->private_conversation_id) }}">
                                ادامه گفتگو
                            </a>
                        </div>
                    @endif
                </article>
            @empty
                <div class="pm-empty-state">
                    @if($activeBox === 'received')
                        در این بخش درخواست دریافتی‌ای ندارید.
                    @else
                        در این بخش درخواست ارسالی‌ای ندارید.
                    @endif
                </div>
            @endforelse
        </div>
    @else
        <div id="chat-panel-content-conversations" class="pm-hub-conversation-list">
            @forelse($conversations as $conversation)
                @php
                    $otherUser = $conversation->users->firstWhere('id', '!=', auth()->id());
                    $lastMessage = $conversation->messages->first();
                    $unreadCount = (int) ($conversation->unread_count ?? 0);
                @endphp
                <a href="{{ route('private-chats.show', $conversation->id) }}"
                   class="pm-hub-conversation {{ $unreadCount > 0 ? 'is-unread' : '' }}">
                    <img src="{{ $otherUser && $otherUser->avatar ? asset('images/users/' . $otherUser->avatar) : asset('images/default-avatar.png') }}" alt="">
                    <span class="pm-hub-conversation-main">
                        <span class="pm-hub-conversation-name">{{ $otherUser?->fullName() ?? 'گفتگوی خصوصی' }}</span>
                        <span class="pm-hub-conversation-preview">
                            {{ $lastMessage ? \Illuminate\Support\Str::limit($lastMessage->message, 88) : 'هنوز پیامی ردوبدل نشده است.' }}
                        </span>
                    </span>
                    <span class="pm-hub-conversation-side">
                        <span>{{ $lastMessage ? $lastMessage->created_at->diffForHumans(null, true) : 'جدید' }}</span>
                        @if($unreadCount > 0)
                            <span class="pm-hub-unread" aria-label="{{ $unreadCount }} پیام خوانده‌نشده">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                        @endif
                    </span>
                </a>
            @empty
                <div class="pm-empty-state">
                    هنوز گفتگوی خصوصی‌ای ندارید. برای شروع، از پروفایل یک عضو درخواست گفتگو بفرستید.
                </div>
            @endforelse
        </div>
    @endif
</div>
