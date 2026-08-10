<div class="message-row {{ $item->user_id === auth()->id() ? 'you' : 'other' }}" data-message-id="{{ $item->id }}"

    data-feed-item="true" data-feed-type="message" data-feed-id="{{ $item->id }}"

    data-feed-author-id="{{ $item->user_id }}" data-feed-unread="{{ (int) $item->user_id !== (int) auth()->id() && !$item->isReadBy((int) auth()->id()) ? '1' : '0' }}"

    id="msg-{{ $item->id }}">

    @php

    $sender = $item->user ?? optional($group->users)->firstWhere('id', $item->user_id);

    $isMine = $item->user_id === auth()->id();

    $first = $sender->first_name ?? '';

    $last = $sender->last_name ?? '';

    $initials = trim(($first ? mb_substr($first, 0, 1) : '') . ' ' . ($last ? mb_substr($last, 0, 1) : '')) ?: '؟';

    $senderName = trim($first . ' ' . $last);

    $rawContent = $item->content ?? '';

    @endphp

    {{-- آواتار --}}

    @if(!$isMine)

    <a href="{{ route('profile.member.show', $item->user_id) }}" class="avatar-link"

        aria-label="نمایه {{ $senderName ?: 'کاربر' }}">

        @if($sender && $sender->avatar)

        <span class="avatar avatar--image">

            <img src="{{ asset('/images/users/avatars/' . $sender->avatar) }}" alt="{{ $senderName }}">

        </span>

        @else

        <span class="avatar">

            <span>{{ $initials }}</span>

        </span>

        @endif

    </a>

    @endif

    <div class="message-bubble {{ $isMine ? 'you' : 'other' }} {{ ((isset($item->voice_message_url) && $item->voice_message_url) || (isset($item->voice_message) && $item->voice_message)) ? 'message-bubble--voice' : '' }}" data-message-id="{{ $item->id }}"

        data-user-id="{{ $item->user_id }}" data-edit-url="{{ route('groups.messages.edit', $item->id) }}"

        data-delete-url="{{ route('groups.messages.delete', $item->id) }}"

        data-report-url="{{ route('messages.report', $item->id) }}"

        data-content-raw="{{ e(strip_tags($item->content ?? '')) }}">

        <div class="message-head">

            @if($isMine)

            {{-- برای پیام‌های خود کاربر: سه نقطه در سمت چپ، نام در سمت راست --}}

            <div class="action-menu message-action" data-action-menu>

                <button type="button" class="action-menu__toggle" aria-expanded="false" aria-label="گزینه‌های پیام">

                    <i class="fas fa-ellipsis-v"></i>

                </button>

                <div class="action-menu__list">

                    <button type="button"

                        data-legacy-chat-action="reply" data-message-id="{{ $item->id }}"

                        class="action-menu__item btn-rep">

                        <i class="fas fa-reply"></i>

                        پاسخ

                    </button>

                    <button type="button" class="action-menu__item btn-reaction">

                        <i class="fas fa-smile"></i>

                        واکنش

                    </button>

                    @if(in_array(($roleValue ?? 0), [2, 3]))
                    <button type="button" class="action-menu__item btn-pin" data-legacy-chat-action="pin" data-message-id="{{ $item->id }}">

                        <i class="fas fa-thumbtack"></i>

                        سنجاق کردن

                    </button>
                    @endif

                    <button type="button" class="action-menu__item btn-edit">

                        <i class="fas fa-edit"></i>

                        ویرایش

                    </button>

                    <button type="button" class="action-menu__item action-menu__item--danger btn-delete"
                        data-group-chat-action="delete-message" data-message-id="{{ $item->id }}">

                        <i class="fas fa-trash"></i>

                        حذف

                    </button>

                    @php

                    $createdAt = verta($item->created_at);

                    $isEdited = false;

                    if (isset($item->edited)) {

                    $isEdited = (bool)$item->edited;

                    } elseif (isset($item->is_edited)) {

                    $isEdited = (bool)$item->is_edited;

                    } elseif ($item->updated_at && $item->updated_at != $item->created_at) {

                    $isEdited = true;

                    }

                    $updatedAt = $isEdited && $item->updated_at ? verta($item->updated_at) : null;

                    @endphp

                    <div class="menu-meta-time">

                        <div class="menu-meta-time__item">

                            <i class="fas fa-paper-plane"

                                style="font-size: 0.7rem; opacity: 0.6; margin-left: 4px;"></i>

                            <span class="menu-meta-time__label">ارسال شده:</span>

                            <span class="menu-meta-time__value">{{ $createdAt->format('Y/m/d') }} در

                                {{ $createdAt->format('H:i:s') }}</span>

                        </div>

                        @if($isEdited && $updatedAt)

                        <div class="menu-meta-time__item"

                            style="margin-top: 6px; padding-top: 6px; border-top: 1px solid rgba(0,0,0,0.08);">

                            <i class="fas fa-edit" style="font-size: 0.7rem; opacity: 0.6; margin-left: 4px;"></i>

                            <span class="menu-meta-time__label">ویرایش شده:</span>

                            <span class="menu-meta-time__value">{{ $updatedAt->format('Y/m/d') }} در

                                {{ $updatedAt->format('H:i:s') }}</span>

                        </div>

                        @endif

                    </div>

                </div>

            </div>

            <div class="message-head__info">

                <span class="message-sender message-sender--self">شما</span>

            </div>

            @else

            {{-- برای پیام‌های دیگران: نام کاربر در سمت چپ، سه نقطه در سمت راست --}}

            <div class="message-head__info">

                @if(!$item->user)

                <span class="message-sender message-sender--deleted">حساب حذف شده</span>

                @elseif($sender)

                <a href="{{ route('profile.member.show', $item->user_id) }}" class="message-sender"

                    >

                    {{ $senderName }}

                </a>

                @endif

            </div>

            <div class="action-menu message-action" data-action-menu>

                <button type="button" class="action-menu__toggle" aria-expanded="false" aria-label="گزینه‌های پیام">

                    <i class="fas fa-ellipsis-v"></i>

                </button>

                <div class="action-menu__list">

                    <button type="button"

                        data-legacy-chat-action="reply" data-message-id="{{ $item->id }}"

                        class="action-menu__item btn-rep">

                        <i class="fas fa-reply"></i>

                        پاسخ

                    </button>

                    <button type="button" class="action-menu__item btn-reaction">

                        <i class="fas fa-smile"></i>

                        واکنش

                    </button>

                    @if(in_array(($roleValue ?? 0), [2, 3]))

                    <button type="button" class="action-menu__item btn-pin" data-legacy-chat-action="pin" data-message-id="{{ $item->id }}">

                        <i class="fas fa-thumbtack"></i>

                        سنجاق کردن

                    </button>

                    @endif

                    <button type="button" class="action-menu__item btn-report"
                        data-group-chat-action="report-message" data-message-id="{{ $item->id }}">

                        <i class="fas fa-flag"></i>

                        گزارش

                    </button>

                    @php

                    $createdAt = verta($item->created_at);

                    $isEdited = false;

                    if (isset($item->edited)) {

                    $isEdited = (bool)$item->edited;

                    } elseif (isset($item->is_edited)) {

                    $isEdited = (bool)$item->is_edited;

                    } elseif ($item->updated_at && $item->updated_at != $item->created_at) {

                    $isEdited = true;

                    }

                    $updatedAt = $isEdited && $item->updated_at ? verta($item->updated_at) : null;

                    @endphp

                    <div class="menu-meta-time">

                        <div class="menu-meta-time__item">

                            <i class="fas fa-paper-plane"

                                style="font-size: 0.7rem; opacity: 0.6; margin-left: 4px;"></i>

                            <span class="menu-meta-time__label">ارسال شده:</span>

                            <span class="menu-meta-time__value">{{ $createdAt->format('Y/m/d') }} در

                                {{ $createdAt->format('H:i:s') }}</span>

                        </div>

                        @if($isEdited && $updatedAt)

                        <div class="menu-meta-time__item"

                            style="margin-top: 6px; padding-top: 6px; border-top: 1px solid rgba(0,0,0,0.08);">

                            <i class="fas fa-edit" style="font-size: 0.7rem; opacity: 0.6; margin-left: 4px;"></i>

                            <span class="menu-meta-time__label">ویرایش شده:</span>

                            <span class="menu-meta-time__value">{{ $updatedAt->format('Y/m/d') }} در

                                {{ $updatedAt->format('H:i:s') }}</span>

                        </div>

                        @endif

                    </div>

                </div>

            </div>

            @endif

        </div>

        {{-- پیش‌نمایش پاسخ --}}

        @if ($item->parent_id)

        @php

        $pid = $item->parent_id;

        $replySender = '';

        $replyText = '';

        $link = null;

        if (\Illuminate\Support\Str::startsWith($pid, 'poll-')) {

        $id = (int) \Illuminate\Support\Str::after($pid, 'poll-');

        $parent = \App\Models\Poll::with('user')->find($id);

        $replySender = trim((optional(optional($parent)->user)->first_name ?? '') . ' ' .

        (optional(optional($parent)->user)->last_name ?? ''));

        $replyText = optional($parent)->title ?? optional($parent)->question ?? 'Poll';

        $link = $parent ? '#poll-' . $id : null;

        } elseif (\Illuminate\Support\Str::startsWith($pid, 'post-')) {

        $id = (int) \Illuminate\Support\Str::after($pid, 'post-');

        $parent = \App\Models\Blog::with('user')->find($id);

        $replySender = trim((optional(optional($parent)->user)->first_name ?? '') . ' ' .

        (optional(optional($parent)->user)->last_name ?? ''));

        $replyText = optional($parent)->title ?? 'Post';

        $link = $parent ? '#blog-' . $id : null;

        } else {

        $parent = $item->parent ?? null;

        $replySender = trim((optional(optional($parent)->user)->first_name ?? '') . ' ' .

        (optional(optional($parent)->user)->last_name ?? ''));

        $replyText = optional($parent)->message ?? '';

        $link = $parent ? '#msg-' . $parent->id : null;

        }

        $replyText = \Illuminate\Support\Str::limit(trim((string) $replyText), 80);

        @endphp

        <div class="reply-preview">

            @if($replySender)

            <div class="reply-sender">{{ $replySender }}</div>

            @endif

            <div class="reply-text">

                @if($link)

                <a href="{{ $link }}">{{ $replyText }}</a>

                @else

                {{ $replyText }}

                @endif

            </div>

        </div>

        @endif

        {{-- متن پیام --}}

        <p class="message-content">{!! $item->content ?? '' !!}</p>

        {{-- Voice Message --}}

        @if((isset($item->voice_message_url) && $item->voice_message_url) || (isset($item->voice_message) && $item->voice_message))

        @php

        // Prefer the dedicated stream endpoint when available.

        $voicePath = $item->voice_message_url ?? $item->voice_message;

        if (str_starts_with($voicePath, 'http')) {

        $voiceUrl = $voicePath;

        } elseif (str_starts_with($voicePath, '/messages/')) {

        $voiceUrl = $voicePath;

        } else {

        $normalizedVoicePath = ltrim($voicePath, '/');

        if (str_starts_with($normalizedVoicePath, 'storage/')) {

        $voiceUrl = '/' . $normalizedVoicePath;

        } else {

        $voiceUrl = '/storage/' . $normalizedVoicePath;

        }

        }

        $voiceType = isset($item->file_type) ? $item->file_type : 'audio/webm';

        // Fallback for different audio types

        if (str_contains($voiceType, 'webm')) {

        $voiceType = 'audio/webm';

        } elseif (str_contains($voiceType, 'ogg')) {

        $voiceType = 'audio/ogg';

        } elseif (str_contains($voiceType, 'wav')) {

        $voiceType = 'audio/wav';

        } elseif (str_contains($voiceType, 'mp3') || str_contains($voiceType, 'mpeg')) {

        $voiceType = 'audio/mpeg';

        } else {

        $voiceType = 'audio/webm'; // Default

        }

        @endphp

        <div class="voice-message-container" style="

                margin-top: 12px;

                padding: 12px;

                background: {{ $isMine ? '#e3f2fd' : '#f5f5f5' }};

                border-radius: 12px;

                border: 1px solid {{ $isMine ? '#90caf9' : '#e0e0e0' }};

                direction: ltr;

            ">

            <div style="display: flex; align-items: center; gap: 12px;">

                <div style="

                        width: 40px;

                        height: 40px;

                        border-radius: 50%;

                        background: {{ $isMine ? '#2196f3' : '#757575' }};

                        display: flex;

                        align-items: center;

                        justify-content: center;

                        color: white;

                        flex-shrink: 0;

                    ">

                    <i class="fas fa-microphone" style="font-size: 18px;"></i>

                </div>

                <div class="voice-message-content" style="flex: 1; min-width: 220px; width: 100%;">

                    <div style="font-size: 12px; color: #666; margin-bottom: 4px;">

                        <i class="fas fa-headphones"></i> پیام صوتی

                    </div>

                    <audio class="voice-player" controls style="width: 100%;" preload="metadata" src="{{ $voiceUrl }}" type="{{ $voiceType }}">

                        مرورگر شما از پخش صدا پشتیبانی نمی‌کند.

                    </audio>

                </div>

            </div>

        </div>

        @endif

        {{-- ردیف پایین پیام: زمان، واکنش‌ها و وضعیت ارسال --}}

        @php

        $createdAt = verta($item->created_at);

        $isEdited = false;

        if (isset($item->edited)) {

        $isEdited = (bool)$item->edited;

        } elseif (isset($item->is_edited)) {

        $isEdited = (bool)$item->is_edited;

        } elseif ($item->updated_at && $item->updated_at != $item->created_at) {

        $isEdited = true;

        }

        $updatedAt = $isEdited && $item->updated_at ? verta($item->updated_at) : null;

        $timeStr = $createdAt->format('H:i');

        $reactions = [];

        if (isset($item->reactions) && $item->reactions) {

        if (is_string($item->reactions)) {

        $reactions = json_decode($item->reactions, true) ?? [];

        } elseif (is_object($item->reactions) && method_exists($item->reactions, 'groupBy')) {

        $reactions = $item->reactions->groupBy('reaction_type')

        ->map(function($group) {

        return [

        'type' => $group->first()->reaction_type,

        'count' => $group->count()

        ];

        })

        ->values()

        ->toArray();

        } elseif (is_array($item->reactions)) {

        $reactions = $item->reactions;

        }

        }

        $emojis = [
        'like' => '👍',
        'love' => '❤️',
        'laugh' => '😂',
        'wow' => '😮',
        'sad' => '😢',
        'angry' => '😠',
        '👍' => '👍',
        '❤️' => '❤️',
        '😂' => '😂',
        '😮' => '😮',
        '😢' => '😢',
        '🔥' => '🔥',
        '👎' => '👎'
        ];

        $readBy = null;

        if ($isMine && isset($item->read_by)) {

        if (is_string($item->read_by)) {

        $readBy = json_decode($item->read_by, true);

        } else {

        $readBy = $item->read_by;

        }

        }

        $readCount = is_array($readBy) ? count($readBy) : 0;

        @endphp

        <div class="message-timestamp"

            style="display: flex !important; align-items: center; gap: 6px; margin-top: 4px; flex-wrap: wrap; margin-left: -10px !important; margin-right: -10px !important; padding-left: 10px !important; padding-right: 10px !important; justify-content: space-between !important; float: none !important; text-align: left !important; direction: ltr !important;">

            {{-- سمت چپ: ارسال شده (منتها الیه چپ) --}}

            @if($isMine)

            <div class="read-receipt"

                style="font-size: 10px; text-align: left; direction: ltr; margin-right: auto; margin-left: 0;">

                @if($readCount > 0)

                <span style="color: #10b981;">

                    <i class="fas fa-check-double"></i> {{ $readCount }} نفر خوانده‌اند

                </span>

                @else

                <span style="color: #9ca3af;">

                    <i class="fas fa-check"></i> ارسال شده

                </span>

                @endif

            </div>

            @endif

            {{-- وسط: واکنش‌ها --}}

            <div style="display: flex; align-items: center; gap: 4px; flex: 1; justify-content: center;">

                @if(!empty($reactions))

                <div class="message-reactions" style="display: flex; gap: 4px; flex-wrap: wrap;">

                    @foreach($reactions as $reaction)
                    @php
                    $reactionType = $reaction['type']
                    ?? $reaction['reaction_type']
                    ?? (is_object($reaction) ? ($reaction->type ?? $reaction->reaction_type ?? '') : '');
                    $reactionCount = $reaction['count']
                    ?? (is_object($reaction) ? ($reaction->count ?? 0) : 0);
                    @endphp

                    <span class="reaction-badge" style="

                                background: #f0f0f0;

                                padding: 2px 6px;

                                border-radius: 12px;

                                font-size: 12px;

                                cursor: pointer;

                            "

                        data-legacy-chat-action="reaction" data-message-id="{{ $item->id }}" data-reaction-type="{{ $reactionType }}">

                        {{ $emojis[$reactionType] ?? ($reactionType ?: '👍') }}

                        {{ $reactionCount }}

                    </span>

                    @endforeach

                </div>

                @endif

            </div>

            {{-- سمت راست: زمان --}}

            <div style="display: flex; align-items: center; gap: 4px; margin-left: auto;">

                <span class="message-time">{{ $timeStr }}</span>

                @if($isEdited && $updatedAt)

                <span class="message-edited" title="ویرایش شده در {{ $updatedAt->format('Y/m/d H:i:s') }}">

                    <i class="fas fa-edit"></i>

                </span>

                @endif

            </div>

        </div>

        {{-- Thread Reply Count --}}

        @php

        $replyCount = isset($item->reply_count) ? (int)$item->reply_count : 0;

        @endphp

        @if($replyCount > 0)

        <div class="thread-info" style="margin-top: 8px; font-size: 12px; color: #6b7280; cursor: pointer;"

            data-chat-page-action="show-thread" data-message-id="{{ $item->id }}">

            <i class="fas fa-comments"></i> {{ $replyCount }} پاسخ

        </div>

        @endif

    </div>

</div>

