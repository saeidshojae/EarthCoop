@php
    use Illuminate\Support\Str;

    $group2 = $group2 ?? $group;
    $guestCount = $group->guestsCount();
    $pollCollection = $group2->polls ?? collect();
    $regularPolls = $pollCollection->filter(fn($poll) => (int)($poll->main_type ?? 1) !== 0);
    $electionPolls = $pollCollection->filter(fn($poll) => (int)($poll->main_type ?? 1) === 0);
    $userVote = $userVote ?? null;

    $blogs = \App\Models\Blog::where('group_id', $group2->id ?? 0)
        ->with(['reactions', 'comments', 'category'])
        ->latest()
        ->get();

    $userMemberList = \App\Models\GroupUser::where('group_id', $group2->id ?? 0)
        ->where('status', 1)
        ->with('user')
        ->get();

    $admins = $group2->users()
        ->withPivot(['role', 'status'])
        ->whereIn('role', [2, 3])
        ->get();

    $categories = $categories ?? collect();
    $specialities = $specialities ?? collect();
    $chatRequests = $chatRequests ?? collect();
    $managersSorted = $managersSorted ?? collect();
    $inspectorsSorted = $inspectorsSorted ?? collect();
    $managerCounts = $managerCounts ?? collect();
    $inspectorCounts = $inspectorCounts ?? collect();
    $groupSetting = $groupSetting ?? null;
    $yourRole = $yourRole ?? 0;
    $canLeadGroup = in_array((int)$yourRole, [2, 3], true);
    $isManager = (int)$yourRole === 3;
@endphp

<div id="groupInfoPanel"
     class="group-info-panel"
     role="dialog"
     aria-modal="true"
     aria-hidden="true"
     aria-labelledby="groupControlCenterTitle">
    <div class="group-info-panel__inner">
        <header class="control-center-header">
            <button type="button" id="exitNavbar" class="panel-close-btn" data-chat-page-action="close-group-info" aria-label="بستن پنل گروه">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>

            <div class="panel-hero__avatar" aria-hidden="true">
                @if($group->avatar)
                    <img src="{{ asset('images/groups/' . $group->avatar) }}" alt="">
                @else
                    <span>{{ Str::substr($group->name, 0, 2) }}</span>
                @endif
            </div>

            <div class="control-center-header__copy">
                <p class="control-center-eyebrow">مرکز کنترل گروه</p>
                <h2 id="groupControlCenterTitle" class="panel-hero__title">{{ $group->name }}</h2>
                <p class="panel-hero__subtitle">
                    {{ $group->userCount() }} عضو
                    @if($guestCount > 0)
                        <span aria-hidden="true">·</span> {{ $guestCount }} مهمان
                    @endif
                    @if($group->location_level)
                        <span aria-hidden="true">·</span> {{ $group->location_level }}
                    @endif
                </p>
            </div>
        </header>

        <div class="panel-metrics" aria-label="خلاصه وضعیت گروه">
            <div class="panel-metrics__item">
                <span class="panel-metrics__label">پست‌ها</span>
                <span class="panel-metrics__value">{{ $blogs->count() }}</span>
            </div>
            <div class="panel-metrics__item">
                <span class="panel-metrics__label">نظرسنجی‌ها</span>
                <span class="panel-metrics__value">{{ $regularPolls->count() }}</span>
            </div>
            <div class="panel-metrics__item">
                <span class="panel-metrics__label">انتخابات</span>
                <span class="panel-metrics__value">{{ $electionPolls->count() }}</span>
            </div>
            <div class="panel-metrics__item">
                <span class="panel-metrics__label">نقش شما</span>
                <span class="panel-metrics__value panel-metrics__value--text">
                    {{ match((int)$yourRole) { 0 => 'ناظر', 1 => 'فعال', 2 => 'بازرس', 3 => 'مدیر', 4 => 'مهمان', 5 => 'فعال ۲', default => 'عضو' } }}
                </span>
            </div>
        </div>

        <nav class="panel-tabs control-center-tabs" role="tablist" aria-label="بخش‌های پنل گروه">
            <button type="button" class="tab active" data-tab="content" data-control-center-tab="content" role="tab" aria-selected="true" aria-controls="content">
                <i class="far fa-message" aria-hidden="true"></i><span>محتوا</span>
            </button>
            <button type="button" class="tab" data-tab="members" data-control-center-tab="members" role="tab" aria-selected="false" aria-controls="members">
                <i class="fas fa-users" aria-hidden="true"></i><span>اعضا</span>
            </button>
            <button type="button" class="tab" data-tab="governance" data-control-center-tab="governance" role="tab" aria-selected="false" aria-controls="governance">
                <i class="fas fa-scale-balanced" aria-hidden="true"></i><span>حکمرانی</span>
            </button>
            <button type="button" class="tab" data-tab="tools" data-control-center-tab="tools" role="tab" aria-selected="false" aria-controls="tools">
                <i class="fas fa-grid-2" aria-hidden="true"></i><span>ابزارها</span>
            </button>
        </nav>

        <div class="panel-tab-contents control-center-body">
            <section class="tab-content active" id="content" role="tabpanel" aria-label="محتوا">
                <div class="control-center-section-heading">
                    <div>
                        <h3>محتوا و مشارکت</h3>
                        <p>پست‌ها، نظرسنجی‌ها و ابزارهای تولید محتوا</p>
                    </div>
                    @if((int)$yourRole !== 5)
                        <div class="control-center-actions control-center-actions--compact">
                            <button type="button" class="panel-action-btn panel-action-btn--primary" data-chat-page-action="open-blog">
                                <i class="far fa-pen-to-square"></i><span>ایجاد پست</span>
                            </button>
                            <button type="button" class="panel-action-btn" data-chat-page-action="open-poll">
                                <i class="fas fa-chart-simple"></i><span>ساخت نظرسنجی</span>
                            </button>
                        </div>
                    @endif
                </div>

                <div class="panel-search panel-search--single">
                    <div class="panel-search__input w-100">
                        <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" id="contentSearch" data-control-center-search="content" class="form-control" placeholder="جستجو در عنوان و متن پست‌ها و نظرسنجی‌ها..." autocomplete="off">
                    </div>
                </div>
                <div id="contentSearchEmpty" class="empty-state" hidden>موردی با این عبارت پیدا نشد.</div>

                <div class="control-center-subsection">
                    <div class="control-center-subsection__title">
                        <h4>پست‌ها</h4><span>{{ $blogs->count() }}</span>
                    </div>
                    <div class="post-filters mb-3" aria-label="فیلتر پست‌ها">
                        <button type="button" class="post-filter-btn active" data-filter="all">همه</button>
                        <button type="button" class="post-filter-btn" data-filter="most-likes">بیشترین لایک</button>
                        <button type="button" class="post-filter-btn" data-filter="most-dislikes">بیشترین دیسلایک</button>
                        <button type="button" class="post-filter-btn" data-filter="most-comments">بیشترین نظر</button>
                        @foreach($categories as $cat)
                            @if($blogs->contains(fn($b) => $b->category_id == $cat->id))
                                <button type="button" class="post-filter-btn" data-filter="category-{{ $cat->id }}">{{ $cat->name }}</button>
                            @endif
                        @endforeach
                    </div>

                    <div id="posts-container" class="control-center-list">
                        @forelse ($blogs as $item)
                            @php
                                $type = $item->file_type ? explode('/', $item->file_type)[0] : null;
                                $likesCount = $item->likes()->count();
                                $dislikesCount = $item->dislikes()->count();
                                $commentsCount = $item->comments()->count();
                                $categoryId = $item->category_id ?? null;
                            @endphp
                            <article class="post-card"
                                     data-control-center-content-item
                                     data-control-center-search-text="{{ Str::lower(strip_tags(($item->title ?? '') . ' ' . ($item->content ?? ''))) }}"
                                     data-post-id="{{ $item->id }}"
                                     data-likes="{{ $likesCount }}"
                                     data-dislikes="{{ $dislikesCount }}"
                                     data-comments="{{ $commentsCount }}"
                                     data-category-id="{{ $categoryId }}"
                                     data-created-at="{{ $item->created_at->timestamp }}">
                                @if($item->img)
                                    <div class="post-card__media">
                                        @if($type === 'image')
                                            <img src="{{ $item->media_url }}" alt="{{ $item->title }}">
                                        @elseif($type === 'video')
                                            <video controls><source src="{{ $item->media_url }}" type="{{ $item->file_type }}"></video>
                                        @elseif($type === 'audio')
                                            <audio controls><source src="{{ $item->media_url }}" type="{{ $item->file_type }}"></audio>
                                        @endif
                                    </div>
                                @endif
                                <div class="post-card__body">
                                    <h3 class="post-card__title">{{ $item->title }}</h3>
                                    <p class="post-card__excerpt">{!! Str::limit(strip_tags($item->content), 200, '…') !!}</p>
                                    <div class="post-card__footer">
                                        <div class="post-card__stats">
                                            <span><i class="fas fa-thumbs-up"></i>{{ $likesCount }}</span>
                                            <span><i class="fas fa-thumbs-down"></i>{{ $dislikesCount }}</span>
                                            <span><i class="fas fa-comments"></i>{{ $commentsCount }}</span>
                                            @if($item->category)<span><i class="fas fa-tag"></i>{{ $item->category->name }}</span>@endif
                                        </div>
                                        <a href="{{ route('groups.comment', $item) }}" class="post-card__link">مشاهده نظرات</a>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="empty-state">هنوز پستی در این گروه ثبت نشده است.</div>
                        @endforelse
                    </div>
                </div>

                <div class="control-center-subsection">
                    <div class="control-center-subsection__title">
                        <h4>نظرسنجی‌ها</h4><span>{{ $regularPolls->count() }}</span>
                    </div>
                    <div class="control-center-list control-center-list--polls">
                        @forelse ($regularPolls as $item)
                            <div data-control-center-content-item data-control-center-search-text="{{ Str::lower(strip_tags(($item->title ?? '') . ' ' . ($item->question ?? '') . ' ' . ($item->description ?? ''))) }}">
                                @include('groups.partials.poll', ['item' => $item, 'userVote' => $userVote])
                            </div>
                        @empty
                            <div class="empty-state">نظرسنجی فعالی وجود ندارد.</div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="tab-content" id="members" role="tabpanel" aria-label="اعضا">
                <div class="control-center-section-heading">
                    <div>
                        <h3>اعضا و ساختار انسانی</h3>
                        <p>اعضا، مدیران، بازرسان و ارتباطات مدیریتی</p>
                    </div>
                    @if($group->location_level != 10 && $canLeadGroup)
                        <div class="control-center-actions control-center-actions--compact">
                            <button type="button" class="panel-action-btn" id="addUserButton">
                                <i class="fas fa-user-plus"></i><span>افزودن مهمان</span>
                            </button>
                            <button type="button" class="panel-action-btn" id="addChatRequestButton">
                                <i class="fas fa-comments"></i><span>ارتباط مدیران</span>
                            </button>
                        </div>
                    @endif
                </div>

                <div class="panel-search panel-search--single">
                    <div class="panel-search__input w-100">
                        <i class="fas fa-user"></i>
                        <input id="membersSearch" data-control-center-search="members" type="search" class="form-control" placeholder="جستجوی نام، نقش یا ایمیل..." autocomplete="off">
                    </div>
                </div>
                <div class="members-count text-muted mb-3" id="membersCount"></div>
                <div id="membersSearchEmpty" class="empty-state" hidden>عضوی با این مشخصات پیدا نشد.</div>

                @if($isManager)
                    <div class="control-center-actions control-center-actions--compact mb-3">
                        <button type="button" class="panel-action-btn" data-chat-page-action="manage-members">
                            <i class="fas fa-users-cog"></i><span>مدیریت اعضا</span>
                        </button>
                    </div>
                @endif

                <div class="control-center-subsection">
                    <div class="control-center-subsection__title"><h4>اعضای گروه</h4><span>{{ $userMemberList->count() }}</span></div>
                    <ul id="membersList" class="member-list">
                        @foreach ($userMemberList as $member)
                            @php
                                $person = $member->user;
                                $full = trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? '')) ?: '—';
                                $email = $person->email ?? '';
                                $initial = Str::upper(Str::substr($email ?: $full, 0, 1));
                                $pivotRole = isset($member->role) ? (int)$member->role : null;
                                if (in_array($pivotRole, [2,3,4,5], true)) {
                                    $finalRole = $pivotRole;
                                } else {
                                    $locationLevel = strtolower(trim((string)($group2->location_level ?? '')));
                                    $finalRole = in_array($locationLevel, ['neighborhood','street','alley'], true) ? 1 : 0;
                                }
                                $memberRoleLabel = match($finalRole) { 0=>'ناظر',1=>'فعال',2=>'بازرس',3=>'مدیر',4=>'مهمان',5=>'فعال ۲',default=>'عضو' };
                                $profileUrl = $person?->id ? route('profile.member.show', $person->id) : '#';
                                $isOnline = method_exists($person, 'isOnline') ? (bool)$person->isOnline() : false;
                            @endphp
                            <li class="member-item control-center-member-item"
                                data-name="{{ $full }}" data-role="{{ $memberRoleLabel }}" data-email="{{ $email }}">
                                <div class="member-avatar"><span>{{ $initial }}</span><span class="member-status {{ $isOnline ? 'online' : 'offline' }}"></span></div>
                                <div class="member-info">
                                    <a href="{{ $profileUrl }}" class="member-name">{{ $full }}</a>
                                    <div class="member-meta"><span class="member-role-label">{{ $memberRoleLabel }}</span>@if($email)<span class="member-email">{{ $email }}</span>@endif</div>
                                </div>
                                @if($isManager && in_array((int)($member->role ?? -1), [0,1], true) && $person?->id)
                                    <select id="role-hours-{{ $person->id }}" class="member-role-hours" aria-label="مدت تغییر نقش">
                                        @for($hour = 1; $hour <= 24; $hour++)<option value="{{ $hour }}">{{ $hour }} ساعت</option>@endfor
                                    </select>
                                    <button type="button" class="member-change-role" data-chat-feature-action="toggle-member-role" data-member-id="{{ $person->id }}" data-member-role="{{ (int)$member->role }}">تغییر نقش</button>
                                @endif
                                @if($canLeadGroup && in_array((int)($member->role ?? -1), [1,4,5], true) && $person?->id)
                                    <form method="POST" action="{{ route('groups.session-permissions.toggle', [$group2, $person]) }}" class="m-0">
                                        @csrf
                                        <button type="submit" class="member-session-permission {{ $member->session_write_allowed ? 'is-allowed' : '' }}" title="مجوز مشارکت هنگام غیرفعال بودن نشست">
                                            <i class="fas {{ $member->session_write_allowed ? 'fa-user-check' : 'fa-user-lock' }}"></i>
                                        </button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="control-center-subsection">
                    <div class="control-center-subsection__title"><h4>مدیران و بازرسان</h4><span>{{ $admins->count() }}</span></div>
                    <ul class="admin-list">
                        @foreach ($admins as $admin)
                            @php
                                $adminRole = match((int)$admin->pivot->role) { 2 => 'بازرس', 3 => 'مدیر', default => 'عضو' };
                                $adminName = trim(($admin->first_name ?? '') . ' ' . ($admin->last_name ?? '')) ?: ($admin->email ?? '—');
                                $adminEmail = $admin->email ?? '';
                                $onlineState = method_exists($admin, 'isOnline') ? (bool)$admin->isOnline() : false;
                            @endphp
                            <li class="admin-item control-center-member-item" data-name="{{ $adminName }}" data-role="{{ $adminRole }}" data-email="{{ $adminEmail }}">
                                <div class="admin-avatar {{ $onlineState ? 'online' : 'offline' }}"><span>{{ Str::upper(Str::substr($adminEmail ?: $adminName, 0, 1)) }}</span></div>
                                <div class="admin-info"><a href="{{ route('profile.member.show', $admin) }}" class="admin-name">{{ $adminName }}</a><span class="admin-role">{{ $adminRole }}</span></div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

            <section class="tab-content" id="governance" role="tabpanel" aria-label="حکمرانی">
                <div class="control-center-section-heading">
                    <div><h3>حکمرانی و مدیریت</h3><p>انتخابات، نشست، تنظیمات و ابزارهای مدیریتی گروه</p></div>
                </div>

                <div class="control-center-action-grid">
                    @if(($election ?? null) && optional($groupSetting)->election_status == 1)
                        <button type="button" class="control-center-tool-card" @if($canParticipateElection ?? false) data-chat-page-action="open-election" @else disabled @endif>
                            <i class="fas fa-vote-yea"></i><strong>انتخابات جاری</strong><span>{{ ($canParticipateElection ?? false) ? 'مشاهده و مشارکت' : 'در حال برگزاری' }}</span>
                        </button>
                    @endif
                    @if($canLeadGroup)
                        <button type="button" class="control-center-tool-card" data-chat-page-action="open-election-admin">
                            <i class="fas fa-ballot-check"></i><strong>افزودن انتخابات</strong><span>مدیریت فرایند انتخاباتی</span>
                        </button>
                    @endif
                    @if($group->location_level != 10 && $canLeadGroup)
                        <button type="button" class="control-center-tool-card" data-session-toggle data-session-open="{{ $group->is_open ? '1' : '0' }}">
                            <i class="fas {{ $group->is_open ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i><strong>نشست گروه</strong><span>{{ $group->is_open ? 'غیرفعال کردن نشست' : 'فعال کردن نشست' }}</span>
                        </button>
                        <button type="button" class="control-center-tool-card" data-session-admin-open>
                            <i class="fas fa-hand-paper"></i><strong>مشارکت نشست</strong><span>درخواست‌ها و مجوزها</span>
                            <b id="sessionParticipationBadge" class="control-center-badge" @if(empty($pendingSessionParticipationCount)) hidden @endif>{{ (int)($pendingSessionParticipationCount ?? 0) }}</b>
                        </button>
                        <button type="button" class="control-center-tool-card" data-chat-page-action="open-group-edit">
                            <i class="fas fa-pen-to-square"></i><strong>ویرایش گروه</strong><span>اطلاعات و مشخصات گروه</span>
                        </button>
                    @endif
                    <button type="button" class="control-center-tool-card" data-chat-page-action="group-settings">
                        <i class="fas fa-cog"></i><strong>تنظیمات گروه</strong><span>گزینه‌های مدیریتی و رفتاری</span>
                    </button>
                    @if($isManager)
                        <button type="button" class="control-center-tool-card" data-chat-page-action="manage-reports">
                            <i class="fas fa-flag"></i><strong>گزارش‌ها</strong><span>رسیدگی به گزارش‌های گروه</span><b id="reports-badge" class="control-center-badge" style="display:none">0</b>
                        </button>
                    @endif
                    @if($canLeadGroup)
                        <a class="control-center-tool-card control-center-tool-card--featured" href="{{ route('groups.najm-hoda.panel', $group) }}">
                            <i class="fas fa-brain"></i><strong>نجم هدا</strong><span>مدیریت هوشمند و دستیار اجرایی گروه</span>
                        </a>
                    @endif
                </div>

                <div class="control-center-subsection">
                    <div class="control-center-subsection__title"><h4>انتخابات گروه</h4><span>{{ $electionPolls->count() }}</span></div>
                    <div class="control-center-list">
                        @forelse($electionPolls as $item)
                            <div data-governance-search-text="{{ Str::lower(strip_tags(($item->title ?? '') . ' ' . ($item->question ?? '') . ' ' . ($item->description ?? ''))) }}">
                                @include('groups.partials.poll', ['item' => $item, 'userVote' => $userVote])
                            </div>
                        @empty
                            <div class="empty-state">انتخاباتی برای نمایش وجود ندارد.</div>
                        @endforelse
                    </div>
                </div>

                @if($isManager)
                    <div class="control-center-subsection">
                        <div class="control-center-subsection__title"><h4>آمار عملیاتی</h4><button type="button" class="control-center-refresh" id="loadGroupStatsButton">به‌روزرسانی</button></div>
                        <div id="stats-loading" class="empty-state" style="display:none">در حال بارگذاری آمار...</div>
                        <div id="stats-error" class="empty-state empty-state--danger" style="display:none"><span id="stats-error-text"></span></div>
                        <div id="stats-content" class="stats-container"></div>
                    </div>
                @endif
            </section>

            <section class="tab-content" id="tools" role="tabpanel" aria-label="ابزارها">
                <div class="control-center-section-heading">
                    <div><h3>ابزارها و سامانه‌های گروه</h3><p>داشبورد، دبیرخانه، امور مالی و جابه‌جایی میان گروه‌ها</p></div>
                </div>

                <div class="control-center-action-grid control-center-action-grid--tools">
                    <a class="control-center-tool-card" href="{{ route('groups.show', $group) }}">
                        <i class="fas fa-chart-line"></i><strong>داشبورد و گزارش‌های گروه</strong><span>شاخص‌ها، وضعیت و نمای مدیریتی</span>
                    </a>
                    <a class="control-center-tool-card" href="{{ route('secretariat.group', $group) }}">
                        <i class="fas fa-box-archive"></i><strong>دبیرخانه گروه</strong><span>اسناد، مکاتبات و پرونده‌های مجاز</span>
                    </a>
                    <a class="control-center-tool-card control-center-tool-card--finance" href="{{ route('groups.najm-bahar.dashboard', $group) }}">
                        <i class="fas fa-wallet"></i><strong>حساب و امور مالی گروه — نجم بهار</strong><span>کیف پول، انتقال، حساب‌های فرعی و سوابق</span>
                    </a>
                </div>

                <div class="control-center-subsection">
                    <div class="control-center-subsection__title"><h4>گروه‌های من</h4><span>جابه‌جایی سریع</span></div>
                    <div class="panel-search">
                        <select class="form-select" id="searchType" aria-label="نوع جستجو">
                            <option value="name">جستجو در نام گروه</option>
                            <option value="content">جستجو در محتوا</option>
                        </select>
                        <div class="panel-search__input">
                            <i class="fas fa-magnifying-glass"></i>
                            <input type="search" id="groupSearch" class="form-control" placeholder="جستجوی گروه..." autocomplete="off">
                        </div>
                    </div>
                    <div id="groupsList" class="groups-list space-y-3">
                        @foreach(auth()->user()->groups()->orderBy('last_activity_at', 'desc')->get() as $relatedGroup)
                            @php
                                $currentUser = auth()->id();
                                $pivot = \App\Models\GroupUser::where('group_id', $relatedGroup->id)->where('user_id', $currentUser)->first();
                                $locationApproved = true;
                                if ($relatedGroup->address_id !== null) {
                                    $level = $relatedGroup->location_level;
                                    if (!in_array($level, ['continent','country','province','county','section','city'], true)) {
                                        $modelMap = ['region'=>\App\Models\Region::class,'village'=>\App\Models\Village::class,'rural'=>\App\Models\Rural::class,'neighborhood'=>\App\Models\Neighborhood::class,'street'=>\App\Models\Street::class,'alley'=>\App\Models\Alley::class];
                                        $model = $modelMap[$level] ?? null;
                                        $instance = $model ? $model::find($relatedGroup->address_id) : null;
                                        if ($instance && (int)$instance->status === 0) $locationApproved = false;
                                    }
                                }
                                $specialtyApproved = !(($relatedGroup->specialty && (int)$relatedGroup->specialty->status === 0) || ($relatedGroup->experience && (int)$relatedGroup->experience->status === 0));
                                $relatedRole = match((int)($pivot->role ?? 0)) { 0=>'ناظر',1=>'فعال',2=>'بازرس',3=>'مدیر',4=>'مهمان',5=>'فعال ۲',default=>'عضو' };
                            @endphp
                            @if($pivot)
                                <div class="group-item" data-level="{{ $relatedGroup->location_level }}" data-group-id="{{ $relatedGroup->id }}">
                                    <div class="group-avatar">@if($relatedGroup->avatar)<img src="{{ asset('images/groups/' . $relatedGroup->avatar) }}" alt="">@else<span>{{ Str::substr($relatedGroup->name, 0, 2) }}</span>@endif</div>
                                    <div class="group-info">
                                        <div class="group-name">
                                            @if($locationApproved && $specialtyApproved && (int)$pivot->status === 1)
                                                <a href="{{ route('groups.chat', $relatedGroup) }}">{{ $relatedGroup->name }}</a>
                                            @else
                                                <span class="text-muted">{{ $relatedGroup->name }} (در انتظار تأیید)</span>
                                            @endif
                                        </div>
                                        <div class="group-meta">{{ $relatedRole }} · {{ $relatedGroup->userCount() }} عضو</div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </section>
        </div>

        <footer class="control-center-footer">
            <a href="{{ route('groups.logout', $group->id) }}" class="panel-action-btn panel-action-btn--danger">
                <i class="fas fa-door-open"></i><span>خروج از گروه</span>
            </a>
        </footer>
    </div>
</div>

<div id="userSearchModal" class="panel-modal" style="display:none">
    <div class="panel-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="guestModalTitle">
        <button type="button" class="panel-modal__close" data-chat-page-action="cancel-add-guests" aria-label="بستن">×</button>
        <h3 class="panel-modal__title" id="guestModalTitle">افزودن کاربر مهمان</h3>
        <div class="panel-modal__body">
            <div class="panel-search__input mb-3"><i class="fas fa-user-search"></i><input type="search" id="searchUsers" class="form-control" placeholder="کد کاربری، نام، ایمیل یا شماره تماس..." autocomplete="off"></div>
            <ul id="searchUserResults" class="panel-modal__list" style="display:none"></ul>
            <div class="guest-duration-row">
                <input type="number" id="hoursUser" class="form-control" min="1" placeholder="مدت حضور (ساعت)">
                <button type="button" class="btn btn-success" id="addUsersToGroup">افزودن به گروه</button>
            </div>
        </div>
    </div>
</div>

<div id="chatRequestModal" class="panel-modal" style="display:none">
    <div class="panel-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="managerChatModalTitle">
        <button type="button" class="panel-modal__close" data-chat-page-action="cancel-manager-chat" aria-label="بستن">×</button>
        <h3 class="panel-modal__title" id="managerChatModalTitle">ارتباط با مدیران گروه‌های دیگر</h3>
        <div class="panel-modal__body">
            <div class="manager-chat-tabs" role="tablist" aria-label="درخواست‌های چت مدیران">
                <button type="button" class="manager-chat-tab active" data-manager-chat-tab="outgoing" role="tab" aria-selected="true"><i class="fas fa-paper-plane"></i><span>ارسال به مدیران</span></button>
                <button type="button" class="manager-chat-tab" data-manager-chat-tab="incoming" role="tab" aria-selected="false"><i class="fas fa-inbox"></i><span>درخواست‌های دریافتی</span>@if($chatRequests->isNotEmpty())<b>{{ $chatRequests->count() }}</b>@endif</button>
            </div>
            <section class="manager-chat-pane active" data-manager-chat-pane="outgoing">
                <div class="panel-search__input mb-3"><i class="fas fa-search"></i><input type="search" id="searchManagers" class="form-control" placeholder="جستجوی مدیر یا گروه..." autocomplete="off"></div>
                <ul id="managerList" class="panel-modal__list">
                    @php $managers = \App\Models\GroupUser::query()->where('role', 3)->with(['user','group'])->get(); @endphp
                    @foreach($managers as $manager)
                        @if(auth()->id() !== $manager->user_id)
                            <li class="manager-item" data-manager-search-text="{{ mb_strtolower(trim($manager->user->first_name . ' ' . $manager->user->last_name . ' ' . $manager->group->name)) }}">
                                <div class="manager-request-card__identity">
                                    <div class="manager-request-card__avatar">@if($manager->user->avatar)<img src="{{ asset('storage/' . ltrim($manager->user->avatar, '/')) }}" alt="">@else<span>{{ mb_substr($manager->user->first_name ?? '',0,1) }}{{ mb_substr($manager->user->last_name ?? '',0,1) }}</span>@endif</div>
                                    <div class="manager-request-card__person"><strong>{{ $manager->user->first_name }} {{ $manager->user->last_name }}</strong><span><i class="fas fa-layer-group"></i>{{ $manager->group->name }}</span></div>
                                </div>
                                @include('chat_request', ['user'=>$manager->user,'request_to_group'=>$manager->group_id,'manager_card'=>true])
                            </li>
                        @endif
                    @endforeach
                </ul>
            </section>
            <section class="manager-chat-pane" data-manager-chat-pane="incoming" hidden>@include('chat_request', ['user'=>auth()->user(),'manager_inbox'=>true])</section>
        </div>
    </div>
</div>

@push('styles')
<style>
    .group-info-panel { width:100%; background:#fff; border:1px solid rgba(15,118,110,.12); border-radius:28px; box-shadow:0 30px 80px -45px rgba(15,23,42,.35); }
    .group-info-panel__inner { position:relative; padding:1.2rem 1.35rem 1rem; display:flex; flex-direction:column; gap:1rem; }
    .control-center-header { display:grid; grid-template-columns:auto minmax(0,1fr); align-items:center; gap:.9rem; padding-inline:3rem .25rem; min-height:72px; }
    .control-center-header__copy { min-width:0; }
    .control-center-eyebrow { margin:0 0 .15rem; color:#10b981; font-size:.72rem; font-weight:800; }
    .panel-close-btn { position:absolute; top:1.2rem; left:1.2rem; width:36px; height:36px; border-radius:50%; border:1px solid #dbe7e4; background:#f8fafc; color:#334155; display:flex; align-items:center; justify-content:center; z-index:4; }
    .panel-hero__avatar { width:54px; height:54px; border-radius:18px; overflow:hidden; display:grid; place-items:center; background:linear-gradient(145deg,#dbeafe,#d1fae5); color:#047857; font-weight:900; font-size:1.05rem; }
    .panel-hero__avatar img { width:100%; height:100%; object-fit:cover; }
    .panel-hero__title { margin:0; font-size:1.05rem; line-height:1.55; color:#0f4c3a; font-weight:900; overflow-wrap:anywhere; }
    .panel-hero__subtitle { margin:.15rem 0 0; color:#64748b; font-size:.75rem; }
    .panel-metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.55rem; }
    .panel-metrics__item { min-width:0; background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:.55rem .65rem; text-align:center; }
    .panel-metrics__label { display:block; color:#64748b; font-size:.67rem; }
    .panel-metrics__value { display:block; margin-top:.18rem; color:#0f766e; font-size:.95rem; font-weight:900; }
    .panel-metrics__value--text { font-size:.78rem; }
    .control-center-tabs { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.4rem; padding:.35rem; border-radius:16px; background:#f1f5f9; overflow:visible; }
    .panel-tabs .tab { min-width:0; border:0; background:transparent; color:#64748b; border-radius:12px; padding:.62rem .45rem; display:flex; align-items:center; justify-content:center; gap:.4rem; font-size:.78rem; font-weight:800; cursor:pointer; white-space:nowrap; }
    .panel-tabs .tab.active { background:#fff; color:#047857; box-shadow:0 8px 20px -18px rgba(15,23,42,.7); }
    .control-center-body { min-height:0; }
    .tab-content { display:none; padding:.2rem; background:transparent; border:0; box-shadow:none; }
    .tab-content.active { display:block; }
    .control-center-section-heading { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:1rem; }
    .control-center-section-heading h3 { margin:0; color:#0f172a; font-size:1rem; font-weight:900; }
    .control-center-section-heading p { margin:.25rem 0 0; color:#64748b; font-size:.75rem; line-height:1.7; }
    .control-center-actions { display:flex; flex-wrap:wrap; gap:.55rem; }
    .control-center-actions--compact { justify-content:flex-end; }
    .panel-action-btn { display:inline-flex; align-items:center; justify-content:center; gap:.45rem; min-height:40px; padding:.55rem .75rem; border-radius:12px; border:1px solid #d1fae5; background:#ecfdf5; color:#047857; font-size:.76rem; font-weight:800; text-decoration:none; }
    .panel-action-btn--primary { background:#10b981; color:#fff; border-color:#10b981; }
    .panel-action-btn--danger { background:#fff1f2; color:#be123c; border-color:#fecdd3; }
    .panel-search { display:flex; gap:.55rem; margin-bottom:1rem; }
    .panel-search--single { display:block; }
    .panel-search__input { display:flex; align-items:center; gap:.45rem; min-width:0; flex:1; border:1px solid #dbe3ea; border-radius:13px; padding:.48rem .65rem; background:#fff; }
    .panel-search__input input { width:100%; min-width:0; border:0; outline:0; background:transparent; font-size:.78rem; }
    .panel-search .form-select { width:min(190px,42%); border-radius:13px; border-color:#dbe3ea; font-size:.75rem; }
    .control-center-subsection { margin-top:1rem; padding-top:.85rem; border-top:1px solid #eef2f7; }
    .control-center-subsection__title { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:.7rem; }
    .control-center-subsection__title h4 { margin:0; color:#0f4c3a; font-size:.86rem; font-weight:900; }
    .control-center-subsection__title span { color:#94a3b8; font-size:.7rem; }
    .control-center-list { display:grid; gap:.7rem; }
    .empty-state { text-align:center; padding:1rem; border-radius:14px; background:#f8fafc; color:#64748b; font-size:.78rem; }
    .empty-state--danger { background:#fff1f2; color:#be123c; }
    .post-filters { display:flex; flex-wrap:wrap; gap:.4rem; }
    .post-filter-btn { border:1px solid #e2e8f0; background:#fff; color:#475569; border-radius:999px; padding:.38rem .65rem; font-size:.7rem; font-weight:700; }
    .post-filter-btn.active { background:#0f766e; border-color:#0f766e; color:#fff; }
    .post-card { display:grid; gap:.7rem; padding:.8rem; border:1px solid #e2e8f0; border-radius:16px; background:#fff; }
    .post-card__media img,.post-card__media video { width:100%; max-height:220px; object-fit:cover; border-radius:12px; }
    .post-card__title { margin:0; font-size:.88rem; font-weight:900; color:#0f172a; }
    .post-card__excerpt { margin:.35rem 0 0; font-size:.76rem; color:#475569; line-height:1.8; }
    .post-card__footer { display:flex; align-items:center; justify-content:space-between; gap:.7rem; flex-wrap:wrap; }
    .post-card__stats { display:flex; flex-wrap:wrap; gap:.65rem; color:#64748b; font-size:.7rem; }
    .post-card__stats span { display:inline-flex; align-items:center; gap:.25rem; }
    .post-card__link { color:#0d9488; font-size:.72rem; font-weight:800; text-decoration:none; }
    .member-list,.admin-list { list-style:none; padding:0; margin:0; display:grid; gap:.55rem; }
    .member-item,.admin-item { display:flex; align-items:center; gap:.65rem; padding:.65rem .7rem; border:1px solid #e2e8f0; border-radius:14px; background:#fff; min-width:0; }
    .member-avatar,.admin-avatar { position:relative; width:38px; height:38px; flex:0 0 38px; border-radius:12px; display:grid; place-items:center; background:#ecfdf5; color:#047857; font-weight:900; }
    .member-status { position:absolute; width:10px; height:10px; border-radius:50%; bottom:-2px; right:-2px; border:2px solid #fff; background:#94a3b8; }
    .member-status.online { background:#22c55e; }
    .member-info,.admin-info { min-width:0; flex:1; display:grid; gap:.1rem; }
    .member-name,.admin-name { color:#0f4c3a; font-size:.78rem; font-weight:900; text-decoration:none; overflow-wrap:anywhere; }
    .member-meta { display:flex; gap:.5rem; flex-wrap:wrap; color:#64748b; font-size:.68rem; }
    .admin-role { color:#64748b; font-size:.68rem; }
    .member-role-hours { max-width:90px; padding:.3rem; border:1px solid #cbd5e1; border-radius:8px; font-size:.68rem; }
    .member-change-role,.member-session-permission { border:0; background:#ecfdf5; color:#047857; border-radius:9px; padding:.35rem .5rem; font-size:.68rem; font-weight:800; }
    .control-center-action-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.65rem; }
    .control-center-action-grid--tools { grid-template-columns:repeat(3,minmax(0,1fr)); }
    .control-center-tool-card { position:relative; min-width:0; min-height:96px; display:flex; flex-direction:column; align-items:flex-start; gap:.3rem; padding:.85rem; border:1px solid #e2e8f0; border-radius:16px; background:#fff; color:#334155; text-decoration:none; text-align:right; cursor:pointer; }
    .control-center-tool-card > i { color:#10b981; font-size:1rem; }
    .control-center-tool-card strong { color:#0f172a; font-size:.8rem; font-weight:900; }
    .control-center-tool-card span { color:#64748b; font-size:.68rem; line-height:1.65; }
    .control-center-tool-card--featured { background:linear-gradient(135deg,#ecfdf5,#eff6ff); border-color:#a7f3d0; }
    .control-center-tool-card--finance { background:linear-gradient(135deg,#fffbeb,#ecfdf5); border-color:#fde68a; }
    .control-center-badge { position:absolute; top:.5rem; left:.5rem; min-width:20px; height:20px; display:grid; place-items:center; padding:0 .3rem; border-radius:999px; background:#ef4444; color:#fff; font-size:.65rem; }
    .control-center-refresh { border:0; background:#ecfdf5; color:#047857; border-radius:9px; padding:.35rem .55rem; font-size:.68rem; font-weight:800; }
    .stats-container { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.55rem; }
    .control-center-stat { padding:.7rem; border:1px solid #e2e8f0; border-radius:13px; background:#f8fafc; }
    .control-center-stat span { display:block; color:#64748b; font-size:.65rem; }
    .control-center-stat strong { display:block; margin-top:.18rem; color:#0f4c3a; font-size:1rem; }
    .groups-list { display:grid; gap:.5rem; }
    .group-item { display:flex; align-items:center; gap:.65rem; padding:.65rem; border:1px solid #e2e8f0; border-radius:14px; background:#fff; }
    .group-avatar { width:38px; height:38px; flex:0 0 38px; border-radius:12px; overflow:hidden; display:grid; place-items:center; background:#ecfdf5; color:#047857; font-weight:900; }
    .group-avatar img { width:100%; height:100%; object-fit:cover; }
    .group-info { min-width:0; }
    .group-name a { color:#0f4c3a; font-size:.78rem; font-weight:900; text-decoration:none; }
    .group-meta { color:#64748b; font-size:.67rem; }
    .control-center-footer { display:flex; justify-content:flex-end; padding-top:.7rem; border-top:1px solid #eef2f7; }
    .panel-modal { position:fixed; inset:0; z-index:1400; display:flex; align-items:center; justify-content:center; padding:1rem; background:rgba(15,23,42,.42); }
    .panel-modal__dialog { position:relative; width:min(620px,100%); max-height:min(820px,calc(100dvh - 2rem)); display:flex; flex-direction:column; overflow:hidden; background:#fff; border-radius:22px; padding:1.2rem; box-shadow:0 30px 80px -35px rgba(15,23,42,.6); }
    #chatRequestModal .panel-modal__dialog { width:min(780px,96vw); }
    .panel-modal__body { min-height:0; overflow-y:auto; }
    .panel-modal__close { position:absolute; top:.8rem; left:.8rem; width:32px; height:32px; border:0; border-radius:50%; background:#f1f5f9; color:#475569; }
    .panel-modal__title { margin:0 0 1rem; color:#0f4c3a; font-size:1rem; font-weight:900; padding-left:2.5rem; }
    .panel-modal__list { list-style:none; margin:0; padding:0; display:grid; gap:.55rem; max-height:55dvh; overflow-y:auto; }
    .panel-modal__list-item { padding:.65rem; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; cursor:pointer; }
    .guest-duration-row { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:.55rem; margin-top:.8rem; }
    .manager-chat-tabs { display:grid; grid-template-columns:1fr 1fr; gap:.4rem; padding:.3rem; margin-bottom:.8rem; border-radius:14px; background:#f1f5f9; }
    .manager-chat-tab { min-height:40px; border:0; border-radius:10px; background:transparent; color:#64748b; font-size:.74rem; font-weight:800; }
    .manager-chat-tab.active { background:#fff; color:#047857; }
    .manager-chat-pane.active { display:flex; flex-direction:column; min-height:0; }
    .manager-item { display:grid; grid-template-columns:minmax(180px,.8fr) minmax(300px,1.4fr); gap:1rem; align-items:center; padding:.75rem; border:1px solid #e2e8f0; border-radius:14px; }
    .manager-request-card__identity { display:flex; align-items:center; gap:.65rem; min-width:0; }
    .manager-request-card__avatar { width:42px; height:42px; flex:0 0 42px; border-radius:12px; overflow:hidden; display:grid; place-items:center; background:#ecfdf5; color:#047857; font-weight:900; }
    .manager-request-card__avatar img { width:100%; height:100%; object-fit:cover; }
    .manager-request-card__person { min-width:0; display:grid; gap:.2rem; }
    .manager-request-card__person strong { font-size:.76rem; overflow-wrap:anywhere; }
    .manager-request-card__person span { color:#64748b; font-size:.67rem; }
    .manager-request-form__submit { min-height:2.5rem; border:0; border-radius:11px; background:#7c3aed; color:#fff; font-size:.72rem; font-weight:800; }
    @media (max-width: 767px) {
        .group-info-panel__inner { padding:.75rem .85rem max(1rem,env(safe-area-inset-bottom)); }
        .control-center-header { grid-template-columns:auto minmax(0,1fr); padding-inline:2.65rem 0; min-height:60px; }
        .panel-hero__avatar { width:46px; height:46px; border-radius:15px; }
        .panel-hero__title { font-size:.88rem; }
        .panel-metrics { grid-template-columns:repeat(4,minmax(0,1fr)); gap:.35rem; }
        .panel-metrics__item { padding:.42rem .28rem; border-radius:11px; }
        .panel-metrics__label { font-size:.58rem; }
        .panel-metrics__value { font-size:.78rem; }
        .panel-tabs .tab { padding:.55rem .2rem; font-size:.68rem; gap:.25rem; }
        .panel-tabs .tab i { display:none; }
        .control-center-section-heading { display:block; }
        .control-center-actions--compact { justify-content:flex-start; margin-top:.7rem; }
        .control-center-action-grid,.control-center-action-grid--tools { grid-template-columns:1fr 1fr; }
        .control-center-tool-card { min-height:88px; padding:.7rem; }
        .panel-search { flex-direction:column; }
        .panel-search .form-select { width:100%; }
        .member-item,.admin-item { flex-wrap:wrap; }
        .member-role-hours { margin-right:auto; }
        .stats-container { grid-template-columns:1fr 1fr; }
        #chatRequestModal { padding:.55rem; }
        #chatRequestModal .panel-modal__dialog { width:100%; max-height:calc(100dvh - 1.1rem); padding:.9rem; }
        .manager-item { grid-template-columns: 1fr; gap:.7rem; }
        .guest-duration-row { grid-template-columns:1fr; }
    }
</style>
@endpush

@push('scripts')
<script type="module">
    const groupInfoLifecycle = window.GroupChatLifecycle;
    if (!groupInfoLifecycle) throw new Error('GroupChatLifecycle is required by Group Control Center');

    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            groupInfoLifecycle.clearTimeout(timeout);
            timeout = groupInfoLifecycle.timeout(() => func(...args), wait);
        };
    }

    const normalize = value => String(value ?? '').trim().toLocaleLowerCase('fa');

    groupInfoLifecycle.on(document, 'DOMContentLoaded', function() {
        const contentSearch = document.getElementById('contentSearch');
        if (contentSearch) {
            const items = Array.from(document.querySelectorAll('[data-control-center-content-item]'));
            const empty = document.getElementById('contentSearchEmpty');
            groupInfoLifecycle.on(contentSearch, 'input', debounce(() => {
                const query = normalize(contentSearch.value);
                let shown = 0;
                items.forEach(item => {
                    const haystack = normalize(item.dataset.controlCenterSearchText || item.textContent);
                    const hit = !query || haystack.includes(query);
                    item.hidden = !hit;
                    if (hit) shown++;
                });
                if (empty) empty.hidden = shown !== 0 || !query;
            }, 140));
        }

        const membersSearch = document.getElementById('membersSearch');
        if (membersSearch) {
            const memberItems = Array.from(document.querySelectorAll('.control-center-member-item'));
            const membersCount = document.getElementById('membersCount');
            const empty = document.getElementById('membersSearchEmpty');
            const update = () => {
                const query = normalize(membersSearch.value);
                let shown = 0;
                memberItems.forEach(item => {
                    const haystack = normalize(`${item.dataset.name || ''} ${item.dataset.role || ''} ${item.dataset.email || ''}`);
                    const hit = !query || haystack.includes(query);
                    item.hidden = !hit;
                    if (hit) shown++;
                });
                if (membersCount) membersCount.textContent = `نمایش ${shown} از ${memberItems.length}`;
                if (empty) empty.hidden = shown !== 0 || !query;
            };
            update();
            groupInfoLifecycle.on(membersSearch, 'input', debounce(update, 140));
        }

        const groupSearch = document.getElementById('groupSearch');
        const searchType = document.getElementById('searchType');
        if (groupSearch && searchType) {
            const performSearch = debounce(function() {
                const searchText = normalize(groupSearch.value);
                const type = searchType.value || 'name';
                const groupsList = document.getElementById('groupsList');
                if (!groupsList) return;
                if (searchText.length < 2) {
                    groupsList.querySelectorAll('.group-item').forEach(item => item.hidden = false);
                    return;
                }
                groupsList.innerHTML = '<div class="empty-state">در حال جستجو…</div>';
                fetch(`/api/groups/search?q=${encodeURIComponent(searchText)}&type=${encodeURIComponent(type)}`, { headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin' })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.groups?.length) { groupsList.innerHTML = '<div class="empty-state">نتیجه‌ای یافت نشد</div>'; return; }
                        groupsList.innerHTML = '';
                        data.groups.forEach(group => {
                            const card = document.createElement('div');
                            card.className = 'group-item';
                            card.dataset.groupId = group.id;
                            card.innerHTML = `<div class="group-avatar">${group.avatar ? `<img src="${group.avatar}" alt="">` : `<span>${String(group.name || '').substring(0,2)}</span>`}</div><div class="group-info"><div class="group-name">${group.is_approved ? `<a href="/groups/chat/${group.id}">${group.name}</a>` : `<span class="text-muted">${group.name} (در انتظار تأیید)</span>`}</div><div class="group-meta">${group.role || ''} · ${group.members_count || 0} عضو</div></div>`;
                            groupsList.appendChild(card);
                        });
                    })
                    .catch(() => { groupsList.innerHTML = '<div class="empty-state empty-state--danger">خطا در بازیابی نتایج.</div>'; });
            }, 350);
            groupInfoLifecycle.on(groupSearch, 'input', performSearch);
            groupInfoLifecycle.on(searchType, 'change', performSearch);
        }

        const managerSearch = document.getElementById('searchManagers');
        if (managerSearch) {
            groupInfoLifecycle.on(managerSearch, 'input', debounce(() => {
                const query = normalize(managerSearch.value);
                document.querySelectorAll('.manager-item').forEach(item => { item.hidden = !normalize(item.dataset.managerSearchText).includes(query); });
            }, 140));
        }
    });

    const cancelAddGuests = () => { const modal = document.getElementById('userSearchModal'); if (modal) modal.style.display = 'none'; };
    const cancelManagerChat = () => { const modal = document.getElementById('chatRequestModal'); if (modal) modal.style.display = 'none'; };
    const registerGroupInfoActions = () => {
        if (!window.GroupChat?.actions) return false;
        window.GroupChat.actions.register('cancel-add-guests', () => (cancelAddGuests(), true));
        window.GroupChat.actions.register('cancel-manager-chat', () => (cancelManagerChat(), true));
        return true;
    };
    if (!registerGroupInfoActions()) groupInfoLifecycle.on(document, 'group-chat:ready', registerGroupInfoActions, { once:true });

    const addUserButton = document.getElementById('addUserButton');
    if (addUserButton) groupInfoLifecycle.on(addUserButton, 'click', () => { document.getElementById('userSearchModal').style.display = 'flex'; });
    const addChatRequestButton = document.getElementById('addChatRequestButton');
    if (addChatRequestButton) groupInfoLifecycle.on(addChatRequestButton, 'click', () => { document.getElementById('chatRequestModal').style.display = 'flex'; });

    document.querySelectorAll('[data-manager-chat-tab]').forEach(tab => {
        groupInfoLifecycle.on(tab, 'click', () => {
            const selected = tab.dataset.managerChatTab;
            document.querySelectorAll('[data-manager-chat-tab]').forEach(candidate => {
                const active = candidate === tab;
                candidate.classList.toggle('active', active);
                candidate.setAttribute('aria-selected', String(active));
            });
            document.querySelectorAll('[data-manager-chat-pane]').forEach(pane => {
                const active = pane.dataset.managerChatPane === selected;
                pane.classList.toggle('active', active);
                pane.hidden = !active;
            });
        });
    });

    groupInfoLifecycle.on(document, 'DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchUsers');
        const resultBox = document.getElementById('searchUserResults');
        let selectedUserId = null;
        if (!searchInput || !resultBox) return;
        groupInfoLifecycle.on(searchInput, 'input', debounce(function() {
            const query = searchInput.value.trim();
            if (query.length < 2) { resultBox.style.display='none'; resultBox.innerHTML=''; selectedUserId=null; return; }
            fetch(`/users/search?q=${encodeURIComponent(query)}`).then(res => res.json()).then(users => {
                resultBox.innerHTML='';
                if (!users.length) { resultBox.innerHTML='<li class="panel-modal__list-item text-muted">کاربری یافت نشد</li>'; resultBox.style.display='grid'; return; }
                users.forEach(user => {
                    const li = document.createElement('li');
                    li.className='panel-modal__list-item';
                    li.textContent=`${user.first_name ?? ''} ${user.last_name ?? ''} (${user.email ?? ''})`;
                    groupInfoLifecycle.on(li,'click',()=>{ searchInput.value=user.email ?? ''; selectedUserId=user.id; resultBox.style.display='none'; });
                    resultBox.appendChild(li);
                });
                resultBox.style.display='grid';
            });
        },220));
        const addUsersToGroup = document.getElementById('addUsersToGroup');
        if (addUsersToGroup) groupInfoLifecycle.on(addUsersToGroup,'click',function(){
            const hours=document.getElementById('hoursUser').value;
            if(!selectedUserId || !hours){ window.GroupChatFeedback?.toast('لطفاً کاربر و مدت حضور را مشخص کنید.',{type:'error'}); return; }
            fetch('/groups/add-user',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},body:JSON.stringify({user_id:selectedUserId,group_id:{{ $group->id }},hours})})
                .then(res=>res.json()).then(()=>{ window.GroupChatFeedback?.toast('کاربر با موفقیت اضافه شد',{type:'success'}); selectedUserId=null; searchInput.value=''; document.getElementById('hoursUser').value=''; cancelAddGuests(); });
        });
    });

    function applyPostFilter(filterType) {
        const postsContainer = document.getElementById('posts-container');
        if (!postsContainer) return;
        const postsArray = Array.from(postsContainer.querySelectorAll('.post-card'));
        let result = postsArray;
        const number = (card,key) => Number(card.dataset[key] || 0);
        if (filterType === 'most-likes') result = postsArray.sort((a,b)=>number(b,'likes')-number(a,'likes') || number(b,'createdAt')-number(a,'createdAt'));
        else if (filterType === 'most-dislikes') result = postsArray.sort((a,b)=>number(b,'dislikes')-number(a,'dislikes') || number(b,'createdAt')-number(a,'createdAt'));
        else if (filterType === 'most-comments') result = postsArray.sort((a,b)=>number(b,'comments')-number(a,'comments') || number(b,'createdAt')-number(a,'createdAt'));
        else if (filterType.startsWith('category-')) { const id=filterType.replace('category-',''); result=postsArray.filter(card=>card.dataset.categoryId===id).sort((a,b)=>number(b,'createdAt')-number(a,'createdAt')); }
        else result = postsArray.sort((a,b)=>number(b,'createdAt')-number(a,'createdAt'));
        postsArray.forEach(card => card.hidden = filterType.startsWith('category-') && !result.includes(card));
        result.forEach(card => { card.hidden=false; postsContainer.appendChild(card); });
        document.querySelectorAll('.post-filter-btn').forEach(button=>button.classList.toggle('active',button.dataset.filter===filterType));
    }
    groupInfoLifecycle.on(document,'click',event=>{ const button=event.target.closest('.post-filter-btn'); if(button) applyPostFilter(button.dataset.filter || 'all'); });

    function loadGroupStats() {
        const loading=document.getElementById('stats-loading');
        const error=document.getElementById('stats-error');
        const errorText=document.getElementById('stats-error-text');
        const container=document.getElementById('stats-content');
        if(!container) return;
        if(loading) loading.style.display='block'; if(error) error.style.display='none'; container.innerHTML='';
        fetch(`/groups/{{ $group->id }}/stats`,{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}})
            .then(response=>response.json()).then(data=>{
                if(loading) loading.style.display='none';
                if(data.status!=='success') throw new Error(data.message || 'خطا در بارگذاری آمار');
                const stats=data.stats || {};
                const cards=[['اعضا',stats.members?.total],['پیام‌ها',stats.messages?.total],['پست‌ها',stats.posts?.total],['نظرسنجی‌ها',stats.polls?.total],['انتخابات',stats.elections?.total],['گزارش‌های باز',stats.reports?.pending]];
                container.innerHTML=cards.map(([label,value])=>`<div class="control-center-stat"><span>${label}</span><strong>${value ?? 0}</strong></div>`).join('');
            }).catch(exception=>{ if(loading) loading.style.display='none'; if(errorText) errorText.textContent=exception.message; if(error) error.style.display='block'; });
    }
    const statsButton=document.getElementById('loadGroupStatsButton');
    if(statsButton) groupInfoLifecycle.on(statsButton,'click',loadGroupStats);

    @if(isset($_GET['filter']))
        groupInfoLifecycle.on(document,'DOMContentLoaded',function(){ document.querySelector('[data-tab="content"]')?.click(); applyPostFilter(@json((string)$_GET['filter'])); });
    @endif

    window.GroupInfoPanel = Object.freeze({ loadStats: loadGroupStats });
</script>
@endpush
