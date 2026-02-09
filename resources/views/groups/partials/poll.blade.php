@php
    $isSpecialized = (int) ($item->real_type ?? 0) === 1;
    $delegation = $isSpecialized
        ? \App\Models\Delegation::where('poll_id', $item->id)
            ->where('user_id', auth()->id())
            ->first()
        : null;

    $ownerId = optional($item->user)->id ?? 0;
    $hue = fmod($ownerId * 137.508, 360);
    $saturation = 72;
    $lightness = 88;
    $backgroundColor = "linear-gradient(135deg, hsla({$hue}, {$saturation}%, {$lightness}%, 0.9), hsla({$hue}, {$saturation}%, 96%, 0.9))";
    $textColor = "hsl({$hue}, {$saturation}%, 25%)";

    $isOwner = ($item->created_by ?? null) == auth()->id();
    $initials = ($item->user ? mb_substr($item->user->first_name, 0, 1) . ' ' . mb_substr($item->user->last_name, 0, 1) : '؟ ؟');
    $userVote = optional($item->votes)->firstWhere('user_id', auth()->id());
    $isExpired = $item->expires_at && \Carbon\Carbon::parse($item->expires_at)->isPast();
    $isVotingDisabled = $isExpired || ($isSpecialized && $delegation);
@endphp

<div class="poll-wrapper {{ $isOwner ? 'poll-wrapper--self' : '' }}" id="poll-{{ $item->id }}">
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
                    <span class="poll-card__role">{{ $item->main_type == 0 ? 'انتخاب' : 'نظرسنجی' }}</span>
                </div>
            </div>

            <div class="action-menu" data-action-menu>
                <button type="button" class="action-menu__toggle" aria-expanded="false" aria-label="گزینه‌های نظرسنجی">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <div class="action-menu__list">
                    <button type="button" class="action-menu__item" onclick="replyToMessage('poll-{{ $item->id }}', '', 'نظرسنجی: {!! $item->question !!}')">
                        <i class="fas fa-reply"></i>
                        پاسخ
                    </button>

                    @if($isOwner)
                        <button type="button" class="action-menu__item" onclick="showEditPollBox({{ $item->id }})">
                            <i class="fas fa-edit"></i>
                            ویرایش
                        </button>
                        <button type="button" class="action-menu__item action-menu__item--danger" onclick="if(confirm('آیا مطمئن هستید که می‌خواهید این نظرسنجی را حذف کنید؟')){ window.location.href='{{ route('groups.poll.delete', [$group, $item->id]) }}'; }">
                            <i class="fas fa-trash"></i>
                            حذف
                        </button>
                    @else
                        <button type="button" class="action-menu__item action-menu__item--danger" onclick="reportMessage({{ $item->id }})">
                            <i class="fas fa-flag"></i>
                            گزارش
                        </button>
                    @endif
                </div>
            </div>
        </header>

        <div class="poll-card__question">
            <h3 class="poll-card__title">{{ $item->question }}</h3>
            @if($item->description)
                <p class="poll-card__description">{!! nl2br(e($item->description)) !!}</p>
            @endif
        </div>

        @if($isSpecialized)
            <section class="poll-card__delegation" data-skill-id="{{ $item->skill_id }}">
                <button type="button" class="poll-card__delegation-btn" onclick="toggleSkillList({{ $item->id }})">
                    <i class="fas fa-user-tie"></i>
                    مشاهده متخصصین برای تفویض رأی
                </button>
                <span class="poll-card__delegation-status">
                    {{ $delegation ? 'رأی شما به متخصص تفویض شده است.' : 'می‌توانید رأی خود را به متخصص تفویض کنید.' }}
                </span>
            </section>
        @endif

        @php
            // فقط رای‌های کاربرانی که status=1 دارند (عضو فعال) شمرده می‌شوند
            $activeMemberIds = \App\Models\GroupUser::where('group_id', $group->id)
                ->where('status', 1)
                ->pluck('user_id')
                ->toArray();
            
            $totalVotes = \App\Models\PollVote::where('poll_id', $item->id)
                ->whereIn('user_id', $activeMemberIds)
                ->count();
            if ($isSpecialized) {
                $delegationCount = \App\Models\Delegation::where('poll_id', $item->id)->count();
                $totalVotes += $delegationCount;
            }
        @endphp

        <div class="poll-options" {{ $isVotingDisabled ? 'data-disabled=true' : '' }}>
            @foreach($item->options as $option)
                @php
                    $optionVotes = \App\Models\PollVote::where('poll_id', $item->id)
                        ->where('option_id', $option->id)
                        ->whereIn('user_id', $activeMemberIds)
                        ->count();
                    if ($isSpecialized) {
                        $expertDelegations = \App\Models\Delegation::where('poll_id', $item->id)->where('expert_id', $option->user_id)->count();
                        $optionVotes += $expertDelegations;
                    }
                    $percent = $totalVotes ? round(($optionVotes / $totalVotes) * 100) : 0;
                    $isSelected = optional($userVote)->poll_option_id === $option->id;
                @endphp
                <button type="button"
                        class="poll-option {{ $isSelected ? 'poll-option--selected voted' : '' }}"
                        data-poll-id="{{ $item->id }}"
                        data-option-id="{{ $option->id }}"
                        @if(! $isVotingDisabled) onclick="submitVote(this)" @endif>
                    <span class="poll-option__label">{{ $option->text }}</span>
                    <span class="poll-option__stat">{{ $percent }}%</span>
                </button>
            @endforeach
        </div>

        <footer class="poll-card__footer">
            <span class="poll-card__total">تعداد رأی: {{ $totalVotes }}</span>
            <span class="poll-card__status">
                @if($isExpired)
                    مهلت رأی‌گیری تمام شده است.
                @elseif($isSpecialized && $delegation)
                    رأی شما به متخصص تفویض شده است.
                @endif
            </span>
        </footer>

        @if ($isSpecialized)
            @php
                $memberIds = $group->users()->pluck('users.id');
                $specialGroupIds = \App\Models\Group::where('experience_id', $item->skill_id)->pluck('groups.id');
                $specialityUsers = \App\Models\GroupUser::whereIn('user_id', $memberIds)
                    ->whereIn('group_id', $specialGroupIds)
                    ->with('user')
                    ->get()
                    ->unique('user_id');
            @endphp
            <div id="skill-list-{{ $item->id }}" class="skill-list">
                <h3>لیست متخصصین این دسته</h3>
                <table class="skill-list__table">
                    <thead>
                        <tr>
                            <th>نام</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($specialityUsers as $user)
                            @php
                                $specialUser = $user->user;
                                $checkDelegation = \App\Models\Delegation::where('poll_id', $item->id)
                                    ->where('expert_id', optional($specialUser)->id)
                                    ->where('user_id', auth()->id())
                                    ->first();
                            @endphp
                            @if ($specialUser && $specialUser->id !== auth()->id())
                                <tr>
                                    <td><a href="{{ route('profile.member.show', $specialUser) }}">{{ $specialUser->fullName() }}</a></td>
                                    <td class="{{ $checkDelegation ? 'text-success' : 'text-danger' }}">
                                        {{ $checkDelegation ? 'تفویض شده' : 'تفویض نشده' }}
                                    </td>
                                    <td>
                                        <a href="{{ route('groups.delegation', [$item->id, $specialUser->id]) }}" class="skill-list__action">
                                            {{ $checkDelegation ? 'برداشتن تفویض' : 'تفویض به این کاربر' }}
                                        </a>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div id="edit-poll-box-{{ $item->id }}" style="display: none;" class="post-edit-form">
            <form action="{{ route('groups.poll.update', [$group, $item->id]) }}" method="POST">
                @csrf
                @method('PUT')
                <input type="text" name="question" value="{{ $item->question }}" class="form-control mb-2">
                <button type="submit" class="btn btn-primary btn-sm">ذخیره</button>
            </form>
        </div>
    </article>
</div>
