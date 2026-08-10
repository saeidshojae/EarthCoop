@php
    $isSpecialized = (int) ($item->real_type ?? 0) === 1;
    $delegation = $isSpecialized ? ($delegationsByPollId->get($item->id) ?? null) : null;

    $ownerId = (int) (optional($item->user)->id ?? 0);
    $hue = fmod($ownerId * 137.508, 360);
    $saturation = 72;
    $lightness = 88;
    $backgroundColor = "linear-gradient(135deg, hsla({$hue}, {$saturation}%, {$lightness}%, 0.9), hsla({$hue}, {$saturation}%, 96%, 0.9))";
    $textColor = "hsl({$hue}, {$saturation}%, 25%)";

    $isOwner = ((int) ($item->created_by ?? 0)) === (int) auth()->id();
    $initials = $item->user
        ? mb_substr($item->user->first_name ?? '', 0, 1) . ' ' . mb_substr($item->user->last_name ?? '', 0, 1)
        : '؟ ؟';

    $selectedOptionId = (int) ($userVotesByPollId[$item->id] ?? 0);
    $isExpired = $item->expires_at && \Carbon\Carbon::parse($item->expires_at)->isPast();
    $isVotingDisabled = $isExpired || ($isSpecialized && $delegation);

    $totalVotes = (int) ($pollTotals[$item->id] ?? 0);
    $optionVotes = $pollOptionVotes[$item->id] ?? [];
@endphp

<div class="poll-wrapper {{ $isOwner ? 'poll-wrapper--self' : '' }}" id="poll-{{ $item->id }}"
    data-feed-item="true" data-feed-type="poll" data-feed-id="{{ $item->id }}"
    data-feed-author-id="{{ $item->created_by }}" data-feed-unread="{{ !$isOwner && !$item->isReadBy((int) auth()->id()) ? '1' : '0' }}">
    <article class="poll-card {{ $isSpecialized ? 'poll-card--specialized' : 'poll-card--general' }}">
        <header class="poll-card__hero" style="background: {{ $backgroundColor }}; color: {{ $textColor }};">
            <div class="poll-card__context">
                <span class="poll-card__badge">{{ $isSpecialized ? 'نظرسنجی تخصصی' : 'نظرسنجی عمومی' }}</span>
                <span class="poll-card__meta">
                    <i class="far fa-calendar"></i> {{ verta($item->created_at)->format('Y/m/d') }}
                    <span class="poll-card__dot"></span>
                    <i class="far fa-clock"></i> {{ verta($item->expires_at)->formatDifference() }}
                    @if($isSpecialized)
                        <span class="poll-card__dot"></span>
                        <i class="fas fa-diagram-project"></i> {{ optional($item->skill)->name ?? 'بدون دسته' }}
                    @endif
                </span>
            </div>

            <div class="poll-card__owner">
                @if($item->user && $item->user->avatar)
                    <div class="poll-card__avatar poll-card__avatar--image">
                        <img src="{{ asset('/images/users/avatars/' . $item->user->avatar) }}" alt="{{ optional($item->user)->fullName() }}">
                    </div>
                @else
                    <div class="poll-card__avatar" style="background: {{ $backgroundColor }}; color: {{ $textColor }};">
                        {{ $initials }}
                    </div>
                @endif
                <div class="poll-card__owner-info">
                    <span class="poll-card__name">{{ optional($item->user)->fullName() ?? 'حساب حذف شده' }}</span>
                    <span class="poll-card__role">{{ (int) ($item->main_type ?? 0) === 0 ? 'انتخاب' : 'نظرسنجی' }}</span>
                </div>
            </div>

            <div class="action-menu" data-action-menu>
                <button type="button" class="action-menu__toggle" aria-expanded="false" aria-label="گزینه‌های نظرسنجی">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <div class="action-menu__list">
                    <button type="button" class="action-menu__item" data-chat-page-action="reply-content" data-reply-target="poll-{{ $item->id }}" data-reply-text="نظرسنجی: {{ $item->question }}">
                        <i class="fas fa-reply"></i> پاسخ
                    </button>
                    @if($isOwner)
                        <button type="button" class="action-menu__item" data-chat-page-action="edit-poll" data-poll-id="{{ $item->id }}">
                            <i class="fas fa-edit"></i> ویرایش
                        </button>
                        <button type="button" class="action-menu__item action-menu__item--danger" data-chat-page-action="delete-poll" data-poll-id="{{ $item->id }}" data-delete-url="{{ route('groups.poll.delete', [$group, $item->id]) }}">
                            <i class="fas fa-trash"></i> حذف
                        </button>
                    @else
                        <button type="button" class="action-menu__item action-menu__item--danger" data-chat-page-action="report-message" data-message-id="{{ $item->id }}">
                            <i class="fas fa-flag"></i> گزارش
                        </button>
                    @endif
                </div>
            </div>
        </header>

        <div class="poll-card__question">
            <h3 class="poll-card__title">{{ $item->question }}</h3>
            @if(!empty($item->description))
                <p class="poll-card__description">{!! nl2br(e($item->description)) !!}</p>
            @endif
        </div>

        @if($isSpecialized)
            <section class="poll-card__delegation" data-skill-id="{{ $item->skill_id }}">
                <button type="button" class="poll-card__delegation-btn" data-chat-page-action="toggle-skill-list" data-poll-id="{{ $item->id }}">
                    <i class="fas fa-user-tie"></i> مشاهده متخصصین برای تفویض رأی
                </button>
                <span class="poll-card__delegation-status">
                    {{ $delegation ? 'رأی شما به متخصص تفویض شده است.' : 'می‌توانید رأی خود را به متخصص تفویض کنید.' }}
                </span>
            </section>
            <div id="skill-list-{{ $item->id }}" class="skill-list" style="display:none;">
                <p class="text-muted">برای کاهش فشار صفحه، لیست متخصصین در بارگذاری اولیه نمایش داده نمی‌شود.</p>
            </div>
        @endif

        <div class="poll-options" {{ $isVotingDisabled ? 'data-disabled=true' : '' }}>
            @foreach($item->options as $option)
                @php
                    $ov = (int) ($optionVotes[$option->id] ?? 0);
                    $percent = $totalVotes > 0 ? (int) round(($ov / $totalVotes) * 100) : 0;
                    $isSelected = $selectedOptionId === (int) $option->id;
                @endphp
                <button type="button"
                        class="poll-option {{ $isSelected ? 'poll-option--selected voted' : '' }}"
                        data-poll-id="{{ $item->id }}"
                        data-option-id="{{ $option->id }}"
                        @if(! $isVotingDisabled) data-chat-page-action="submit-vote" @endif>
                    <span class="poll-option__label">{{ $option->text }}</span>
                    <span class="poll-option__stat">{{ $percent }}%</span>
                </button>
            @endforeach
        </div>

        <footer class="poll-card__footer content-meta-line">
            <span class="content-meta-time">{{ verta($item->created_at)->format('H:i') }}</span>
            @if($item->edited_at)
            <span class="content-edit-status" title="ویرایش شده در {{ verta($item->edited_at)->format('Y/m/d H:i:s') }}">(ویرایش شده)</span>
            @endif
            <span class="poll-card__total content-reactions-slot">تعداد رأی: {{ $totalVotes }}</span>
            <span class="poll-card__status">
                @if($isExpired)
                    مهلت رأی‌گیری تمام شده است.
                @elseif($isSpecialized && $delegation)
                    رأی شما به متخصص تفویض شده است.
                @endif
            </span>

        @php
            $readBy = null;
            if ($isOwner && isset($item->read_by)) {
                if (is_string($item->read_by)) {
                    $readBy = json_decode($item->read_by, true);
                } else {
                    $readBy = $item->read_by;
                }
            }
            $readCount = is_array($readBy) ? count($readBy) : 0;
        @endphp

        @if($isOwner)
        <div class="poll-read-receipt content-read-receipt" style="font-size: 11px; color: #6b7280;">
            @if($readCount > 0)
            <span style="color: #10b981;">
                <i class="fas fa-check-double"></i> {{ $readCount }} نفر دیده‌اند
            </span>
            @else
            <span style="color: #9ca3af;">
                <i class="fas fa-check"></i> ارسال شده
            </span>
            @endif
        </div>
        @endif
        </footer>

        <div id="edit-poll-box-{{ $item->id }}" style="display: none;" class="post-edit-form">
            <form class="poll-edit-form" action="{{ route('groups.poll.update', [$group, $item->id]) }}" method="POST">
                @csrf
                @method('PUT')
                <input type="text" name="question" value="{{ $item->question }}" class="form-control mb-2">
                <button type="submit" class="btn btn-primary btn-sm">ذخیره</button>
            </form>
        </div>
    </article>
</div>
