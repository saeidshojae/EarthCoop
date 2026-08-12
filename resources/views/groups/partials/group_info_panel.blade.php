@php
    use Illuminate\Support\Str;

    $group2 = $group2 ?? $group;
    $guestCount = $group->guestsCount();
    $pollCollection = $group2->polls ?? collect();
    $userVote = $userVote ?? null;

    // دریافت همه پست‌ها با eager loading
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
@endphp

<div id="groupInfoPanel" class="group-info-panel">
    <div class="group-info-panel__inner">
        <button type="button" id="exitNavbar" class="panel-close-btn" data-chat-page-action="close-group-info">
            <i class="fas fa-times"></i>
        </button>

        <!-- ====== هدر پنل ====== -->
        <div class="panel-hero">
            <div class="panel-hero__avatar">
                @if($group->avatar)
                    <img src="{{ asset('images/groups/' . $group->avatar) }}" alt="{{ $group->name }}">
                @else
                    <span>{{ Str::substr($group->name, 0, 2) }}</span>
                @endif
            </div>

            <div class="panel-hero__content">
                <h3 data-chat-page-action="open-group-info" class="panel-hero__title">{{ $group->name }}</h3>
                <p class="panel-hero__subtitle">
                    {{ $group->userCount() }} عضو
                    @if($guestCount > 0)
                        <span class="mx-2 text-emerald-900/70">·</span>
                        {{ $guestCount }} میهمان
                    @endif
                </p>
                @if($group->description)
                    <p class="panel-hero__description">
                        {{ Str::limit(strip_tags($group->description), 140) }}
                    </p>
                @endif
            </div>
        </div>

        <!-- ====== متریک‌ها ====== -->
        <div class="panel-metrics">
            <div class="panel-metrics__item">
                <span class="panel-metrics__label">سطح گروه</span>
                <span class="panel-metrics__value">{{ $group->location_level ?? '—' }}</span>
            </div>
            <div class="panel-metrics__item">
                <span class="panel-metrics__label">نظرسنجی فعال</span>
                <span class="panel-metrics__value">{{ $pollCollection->count() }}</span>
            </div>
            <div class="panel-metrics__item">
                <span class="panel-metrics__label">پست‌ها</span>
                <span class="panel-metrics__value">{{ $blogs->count() }}</span>
            </div>
            @if($groupSetting)
                <div class="panel-metrics__item">
                    <span class="panel-metrics__label">مدیران مورد نیاز</span>
                    <span class="panel-metrics__value">{{ $groupSetting->manager_count ?? '—' }}</span>
                </div>
            @endif
        </div>

        <!-- ====== دکمه‌های اقدام ====== -->
        <div class="panel-actions">
            @if($group->location_level != 10 && in_array($yourRole, [2,3]))
                <button type="button" class="panel-action-btn" data-chat-page-action="open-group-edit">
                    <i class="fas fa-pen-to-square"></i>
                    <span>ویرایش گروه</span>
                </button>
                <button type="button" class="panel-action-btn" id="addUserButton">
                    <i class="fas fa-user-plus"></i>
                    <span>افزودن کاربر مهمان</span>
                </button>
                <button type="button" class="panel-action-btn" id="addChatRequestButton">
                    <i class="fas fa-comments"></i>
                    <span>درخواست چت مدیران</span>
                </button>
                <button type="button" class="panel-action-btn" data-chat-page-action="open-election-admin">
                    <i class="fas fa-ballot-check"></i>
                    <span>افزودن انتخابات</span>
                </button>
                <a href="{{ route('groups.open', $group) }}" class="panel-action-btn">
                    <i class="fas fa-toggle-on"></i>
                    <span>{{ $group->is_open == 0 ? 'فعال کردن نشست' : 'غیرفعال کردن نشست' }}</span>
                </a>
            @endif
            <a href="{{ route('groups.logout', $group->id) }}" class="panel-action-btn panel-action-btn--danger">
                <i class="fas fa-door-open"></i>
                <span>خروج از گروه</span>
            </a>
        </div>

        <!-- ====== تب‌ها ====== -->
        <div class="panel-tabs">
            <button class="tab active" data-tab="group">گروه‌ها</button>
            <button class="tab" data-tab="members">اعضا</button>
            <button class="tab" data-tab="admins">مدیران</button>
            <button class="tab" data-tab="post">پست‌ها</button>
            <button class="tab" data-tab="poll">نظرسنجی</button>
            <button class="tab" data-tab="election">انتخابات</button>
            @if($yourRole == 3)
                <button class="tab" data-tab="stats">آمار و گزارش‌گیری</button>
            @endif
        </div>

        <!-- ====== محتوای تب‌ها ====== -->
        <div class="panel-tab-contents">

            <!-- ====== تب: گروه‌ها ====== -->
            <div class="tab-content active" id="group">
                <div class="panel-search">
                    <select class="form-select" id="searchType">
                        <option value="name">جستجو در نام گروه</option>
                        <option value="content">جستجو در محتوا</option>
                    </select>
                    <div class="panel-search__input">
                        <i class="fas fa-magnifying-glass"></i>
                        <input type="text" id="groupSearch" class="form-control" placeholder="جستجوی گروه..." autocomplete="off">
                    </div>
                </div>

                <div id="groupsList" class="groups-list space-y-3">
                    @foreach (auth()->user()->groups()->orderBy('last_activity_at', 'desc')->get() as $relatedGroup)
                        @php
                            $currentUser = auth()->id();
                            $pivot = \App\Models\GroupUser::where('group_id', $relatedGroup->id)
                                ->where('user_id', $currentUser)
                                ->first();

                            // بررسی تأیید موقعیت مکانی
                            $locationApproved = true;
                            if ($relatedGroup->address_id !== null) {
                                $level = $relatedGroup->location_level;
                                if (!in_array($level, ['continent', 'country', 'province', 'county', 'section', 'city'])) {
                                    $modelMap = [
                                        'region' => \App\Models\Region::class,
                                        'village' => \App\Models\Village::class,
                                        'rural' => \App\Models\Rural::class,
                                        'neighborhood' => \App\Models\Neighborhood::class,
                                        'street' => \App\Models\Street::class,
                                        'alley' => \App\Models\Alley::class,
                                    ];
                                    $model = $modelMap[$level] ?? null;
                                    if ($model) {
                                        $instance = $model::find($relatedGroup->address_id);
                                        if ($instance && $instance->status == 0) {
                                            $locationApproved = false;
                                        }
                                    }
                                }
                            }

                            // بررسی تأیید تخصص
                            $specialtyApproved = true;
                            if (($relatedGroup->specialty && $relatedGroup->specialty->status == 0) ||
                                ($relatedGroup->experience && $relatedGroup->experience->status == 0)) {
                                $specialtyApproved = false;
                            }
                        @endphp

                        @if($pivot)
                            @php
                                // تعیین نقش بر اساس location_level
                                $locationLevel = strtolower(trim((string)($relatedGroup->location_level ?? '')));
                                if (empty($locationLevel)) {
                                    $pivotRole = isset($pivot->role) ? (int) $pivot->role : 0;
                                } else {
                                    if (in_array($locationLevel, ['neighborhood', 'street', 'alley'], true)) {
                                        $pivotRole = 1; // عضو فعال
                                    } else {
                                        $pivotRole = 0; // ناظر
                                    }
                                }

                                $memberRole = match($pivotRole) {
                                    0 => 'ناظر',
                                    1 => 'فعال',
                                    2 => 'بازرس',
                                    3 => 'مدیر',
                                    4 => 'مهمان',
                                    5 => 'فعال ۲',
                                    default => 'عضو'
                                };
                            @endphp

                            <div class="group-item" data-level="{{ $relatedGroup->location_level }}" data-group-id="{{ $relatedGroup->id }}">
                                <div class="group-avatar">
                                    @if($relatedGroup->avatar)
                                        <img src="{{ asset('images/groups/' . $relatedGroup->avatar) }}" alt="{{ $relatedGroup->name }}">
                                    @else
                                        <div class="default-avatar">{{ Str::substr($relatedGroup->name, 0, 2) }}</div>
                                    @endif
                                </div>
                                <div class="group-info">
                                    <div class="group-main-info">
                                        <div class="group-name">
                                            @if($locationApproved && $specialtyApproved && $pivot->status == 1)
                                                <a href="{{ route('groups.chat', $relatedGroup) }}">{{ $relatedGroup->name }}</a>
                                            @else
                                                <span class="text-muted">{{ $relatedGroup->name }} (در انتظار تأیید)</span>
                                            @endif
                                        </div>
                                        <div class="group-members-count">{{ $relatedGroup->userCount() }} عضو</div>
                                    </div>
                                    <div class="group-secondary-info">
                                        <div class="member-role">
                                            @if($pivot->status == 1)
                                                <span>{{ $memberRole }}</span>
                                            @else
                                                <span>خارج شده <a class="text-primary" href="{{ route('groups.relogout', $relatedGroup) }}">بازگردانی</a></span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="group-meta text-muted">
                                        آخرین فعالیت: {{ verta($relatedGroup->updated_at)->format('Y/m/d H:i') }}
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            <!-- ====== تب: اعضا ====== -->
            <div class="tab-content" id="members">
                <div class="panel-search">
                    <div class="panel-search__input w-100">
                        <i class="fas fa-user"></i>
                        <input id="membersSearch" type="text" class="form-control" placeholder="جستجوی عضو (نام، نقش، ایمیل)..." autocomplete="off">
                    </div>
                </div>
                <div class="members-count text-muted mb-3" id="membersCount"></div>

                <ul id="membersList" class="member-list">
                    @foreach ($userMemberList as $member)
                        @php
                            $person = $member->user;
                            $full = trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? '')) ?: '—';
                            $email = $person->email ?? '';
                            $initial = Str::upper(Str::substr($email ?: $full, 0, 1));

                            // تعیین نقش
                            $pivotRole = isset($member->role) ? (int)$member->role : null;

                            if (in_array($pivotRole, [2, 3, 4, 5], true)) {
                                $finalRole = $pivotRole;
                            } else {
                                $locationLevel = strtolower(trim((string)($group2->location_level ?? '')));
                                if (in_array($locationLevel, ['neighborhood', 'street', 'alley'], true)) {
                                    $finalRole = 1;
                                } else {
                                    $finalRole = 0;
                                }
                            }

                            $memberRoleLabel = match($finalRole) {
                                0 => 'ناظر',
                                1 => 'فعال',
                                2 => 'بازرس',
                                3 => 'مدیر',
                                4 => 'مهمان',
                                5 => 'فعال ۲',
                                default => 'نقش ناشناخته'
                            };

                            $expiredHuman = null;
                            if (!empty($member->expired)) {
                                try { $expiredHuman = \Carbon\Carbon::parse($member->expired)->diffForHumans(); } catch (\Exception $e) {}
                            }

                            $profileUrl = $person?->id ? route('profile.member.show', $person->id) : '#';
                            $isOnline = method_exists($person, 'isOnline') ? (bool)$person->isOnline() : false;
                        @endphp

                        <li class="member-item"
                            data-name="{{ $full }}"
                            data-role="{{ $memberRoleLabel }}"
                            data-email="{{ $email }}">
                            <div class="member-avatar">
                                <span>{{ $initial }}</span>
                                <span class="member-status {{ $isOnline ? 'online' : 'offline' }}"></span>
                            </div>
                            <div class="member-info">
                                <a href="{{ $profileUrl }}" class="member-name">{{ $full }}</a>
                                <div class="member-meta">
                                    <span class="member-role-label">{{ $memberRoleLabel }}</span>
                                    @if($expiredHuman)
                                        <span class="member-expired">· {{ $expiredHuman }}</span>
                                    @endif
                                </div>
                            </div>
                            @if($yourRole == 3 && in_array((int)($member->role ?? -1), [0,1], true) && $person?->id)
                                <a href='{{ route('change-user-role', [$person->id, $group2->id]) }}' class="member-change-role">
                                    تغییر نقش
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>

            <!-- ====== تب: مدیران ====== -->
            <div class="tab-content" id="admins">
                <ul class="admin-list">
                    @foreach ($admins as $admin)
                        @php
                            $memberRole = match($admin->pivot->role) {
                                2 => 'بازرس',
                                3 => 'مدیر',
                                default => 'عضو'
                            };
                            $onlineState = method_exists($admin, 'isOnline') ? (bool)$admin->isOnline() : false;
                        @endphp
                        <li class="admin-item">
                            <div class="admin-avatar {{ $onlineState ? 'online' : 'offline' }}">
                                <span>{{ Str::upper(Str::substr($admin->email, 0, 1)) }}</span>
                            </div>
                            <div class="admin-info">
                                <a href='{{ route('profile.member.show', $admin) }}' class="admin-name">
                                    {{ $admin->first_name }} {{ $admin->last_name }}
                                </a>
                                <span class="admin-role">{{ $memberRole }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <!-- ====== تب: پست‌ها ====== -->
            <div class="tab-content" id="post">
                <div class="post-filters mb-3">
                    <button type="button" class="post-filter-btn active" data-filter="all">همه</button>
                    <button type="button" class="post-filter-btn" data-filter="most-likes">بیشترین لایک</button>
                    <button type="button" class="post-filter-btn" data-filter="most-dislikes">بیشترین دیسلایک</button>
                    <button type="button" class="post-filter-btn" data-filter="most-comments">بیشترین نظر</button>
                    @foreach($categories as $cat)
                        @if($blogs->contains(fn($b) => $b->category_id == $cat->id))
                            <button type="button" class="post-filter-btn" data-filter="category-{{ $cat->id }}">دسته {{ $cat->name }}</button>
                        @endif
                    @endforeach
                </div>

                <div id="posts-container">
                    @forelse ($blogs as $item)
                        @php
                            $type = $item->file_type ? explode('/', $item->file_type)[0] : null;
                            $likesCount = $item->likes()->count();
                            $dislikesCount = $item->dislikes()->count();
                            $commentsCount = $item->comments()->count();
                            $categoryId = $item->category_id ?? null;
                        @endphp

                        <article class="post-card"
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
                                        <video controls>
                                            <source src="{{ $item->media_url }}" type="{{ $item->file_type }}">
                                        </video>
                                    @elseif($type === 'audio')
                                        <audio controls>
                                            <source src="{{ $item->media_url }}" type="{{ $item->file_type }}">
                                        </audio>
                                    @endif
                                </div>
                            @endif

                            <div class="post-card__body">
                                <h3 class="post-card__title">{{ $item->title }}</h3>
                                <p class="post-card__excerpt">{!! Str::limit(strip_tags($item->content), 200, '…') !!}</p>
                                <div class="post-card__footer">
                                    <div class="post-card__stats">
                                        <span><i class="fas fa-thumbs-up" style="color: #10b981;"></i> {{ $likesCount }}</span>
                                        <span><i class="fas fa-thumbs-down" style="color: #ef4444;"></i> {{ $dislikesCount }}</span>
                                        <span><i class="fas fa-comments" style="color: #3b82f6;"></i> {{ $commentsCount }}</span>
                                        @if($item->category)
                                            <span><i class="fas fa-tag" style="color: #8b5cf6;"></i> {{ $item->category->name }}</span>
                                        @endif
                                    </div>
                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <span class="time">{{ verta($item->created_at)->format('Y/m/d H:i') }}</span>
                                        <a href="{{ route('groups.comment', $item) }}" class="post-card__link">
                                            مشاهده نظرات
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="empty-state">هنوز پستی در این گروه ثبت نشده است.</div>
                    @endforelse
                </div>
            </div>

            <!-- ====== تب: نظرسنجی ====== -->
            <div class="tab-content" id="poll">
                @forelse ($pollCollection as $item)
                    @include('groups.partials.poll', ['item' => $item, 'userVote' => $userVote])
                @empty
                    <div class="empty-state">نظرسنجی فعالی وجود ندارد.</div>
                @endforelse
            </div>

            <!-- ====== تب: انتخابات ====== -->
            <div class="tab-content" id="election">
                @php
                    $electionPolls = $pollCollection->where('main_type', 0);
                @endphp
                @forelse ($electionPolls as $item)
                    @include('groups.partials.poll', ['item' => $item, 'userVote' => $userVote])
                @empty
                    <div class="empty-state">انتخاباتی برای نمایش وجود ندارد.</div>
                @endforelse
            </div>

            <!-- ====== تب: آمار ====== -->
            @if($yourRole == 3)
                <div class="tab-content" id="stats">
                    <div id="stats-loading" class="text-center py-8" style="display: none;">
                        <i class="fas fa-spinner fa-spin text-2xl text-blue-500"></i>
                        <p class="mt-2 text-slate-600">در حال بارگذاری آمار...</p>
                    </div>
                    <div id="stats-error" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-4" style="display: none;">
                        <i class="fas fa-exclamation-circle ml-2"></i>
                        <span id="stats-error-text"></span>
                    </div>
                    <div id="stats-content" class="stats-container"></div>
                </div>
            @endif

        </div><!-- /panel-tab-contents -->
    </div>
</div>

<!-- ====== مودال اضافه کردن کاربر مهمان ====== -->
<div id="userSearchModal" class="panel-modal" style="display: none;">
    <div class="panel-modal__dialog">
        <button type="button" class="panel-modal__close" data-chat-page-action="cancel-add-guests">×</button>
        <h3 class="panel-modal__title">اضافه کردن کاربر مهمان</h3>
        <div class="panel-modal__body">
            <div class="panel-search__input mb-3">
                <i class="fas fa-user-search"></i>
                <input type="text" id="searchUsers" class="form-control" placeholder="کد کاربری، نام، ایمیل یا شماره تماس کاربر..." autocomplete="off">
            </div>
            <ul id="searchUserResults" class="panel-modal__list" style="display:none;"></ul>

            <div class="row gx-2 align-items-center mt-3">
                <div class="col-12 col-sm-6">
                    <input type="number" id="hoursUser" class="form-control" placeholder="مدت حضور (ساعت)">
                </div>
                <div class="col-12 col-sm-6 d-flex gap-2 mt-2 mt-sm-0">
                    <button type="button" class="btn btn-success flex-fill" id="addUsersToGroup">افزودن به گروه</button>
                    <button type="button" class="btn btn-outline-secondary flex-fill" data-chat-page-action="cancel-add-guests">انصراف</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ====== مودال درخواست چت مدیران ====== -->
<div id="chatRequestModal" class="panel-modal" style="display: none;">
    <div class="panel-modal__dialog">
        <button type="button" class="panel-modal__close" data-chat-page-action="cancel-manager-chat">×</button>
        <h3 class="panel-modal__title">درخواست چت با مدیران دیگر گروه‌ها</h3>
        <div class="panel-modal__body">
            <div class="manager-chat-tabs" role="tablist" aria-label="درخواست‌های چت مدیران">
                <button type="button" class="manager-chat-tab active" data-manager-chat-tab="outgoing" role="tab" aria-selected="true">
                    <i class="fas fa-paper-plane"></i><span>ارسال به مدیران</span>
                </button>
                <button type="button" class="manager-chat-tab" data-manager-chat-tab="incoming" role="tab" aria-selected="false">
                    <i class="fas fa-inbox"></i><span>درخواست‌های دریافتی</span>
                    @if($chatRequests->isNotEmpty())<b>{{ $chatRequests->count() }}</b>@endif
                </button>
            </div>
            <section class="manager-chat-pane active" data-manager-chat-pane="outgoing">
                <div class="panel-search__input mb-3">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchManagers" class="form-control" placeholder="جستجوی مدیر یا گروه..." autocomplete="off">
                </div>
                <ul id="managerList" class="panel-modal__list">
                @php
                    $managers = \App\Models\GroupUser::query()->where('role', 3)->with(['user', 'group'])->get();
                @endphp
                @foreach ($managers as $manager)
                    @if (auth()->id() !== $manager->user_id)
                        <li class="manager-item" data-manager-search-text="{{ mb_strtolower(trim($manager->user->first_name . ' ' . $manager->user->last_name . ' ' . $manager->group->name)) }}">
                            <div class="manager-request-card__identity">
                                <div class="manager-request-card__avatar">
                                    @if($manager->user->avatar)
                                        <img src="{{ asset('storage/' . ltrim($manager->user->avatar, '/')) }}" alt="">
                                    @else
                                        <span>{{ mb_substr($manager->user->first_name ?? '', 0, 1) }}{{ mb_substr($manager->user->last_name ?? '', 0, 1) }}</span>
                                    @endif
                                </div>
                                <div class="manager-request-card__person">
                                    <strong>{{ $manager->user->first_name }} {{ $manager->user->last_name }}</strong>
                                    <span><i class="fas fa-layer-group"></i>{{ $manager->group->name }}</span>
                                </div>
                            </div>
                            @include('chat_request', ['user' => $manager->user, 'request_to_group' => $manager->group_id, 'manager_card' => true])
                        </li>
                    @endif
                @endforeach
                </ul>
            </section>
            <section class="manager-chat-pane" data-manager-chat-pane="incoming" hidden>
                @include('chat_request', ['user' => auth()->user(), 'manager_inbox' => true])
            </section>
        </div>
    </div>
</div>

@push('styles')
<style>
    .group-info-panel {
        width: 100%;
        background: linear-gradient(135deg, #f9fbfd 0%, #ffffff 45%, #f1f5f9 100%);
        border-radius: 26px;
        border: 1px solid rgba(15, 118, 110, 0.12);
        box-shadow: 0 30px 80px -45px rgba(15, 23, 42, 0.25);
    }

    @media (min-width: 1200px) {
        .group-info-panel {
            position: sticky;
            top: 0;
            max-height: calc(100vh - 4rem);
            overflow-y: auto;
        }
        .panel-close-btn {
            display: none;
        }
    }

    @media (max-width: 1199px) {
        .group-info-panel {
            position: fixed;
            top: 0;
            right: -100%;
            max-width: 360px;
            height: 100vh;
            border-radius: 0;
            z-index: 1000;
            transition: right .35s ease;
            overflow-y: auto;
        }
        .group-info-panel.is-open {
            right: 0;
        }
    }

    .group-info-panel__inner {
        position: relative;
        padding: 1.5rem 1.75rem 2.5rem;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    .panel-close-btn {
        position: absolute;
        top: 1.2rem;
        left: 1.2rem;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 1px solid rgba(15, 118, 110, 0.15);
        background: rgba(255, 255, 255, 0.85);
        color: #0f4c3a;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 10px 25px -15px rgba(15, 118, 110, 0.5);
    }

    .panel-hero {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 1rem;
        padding-top: .75rem;
    }

    .panel-hero__avatar {
        width: 96px;
        height: 96px;
        border-radius: 30px;
        background: linear-gradient(145deg, rgba(59, 130, 246, 0.35), rgba(16, 185, 129, 0.32));
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: #0f172a;
        box-shadow: 0 18px 40px -22px rgba(16, 185, 129, 0.6);
        overflow: hidden;
    }

    .panel-hero__avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .panel-hero__title {
        font-size: 1.35rem;
        font-weight: 800;
        color: #0f4c3a;
        cursor: pointer;
    }

    .panel-hero__subtitle {
        font-size: .95rem;
        color: #0f766e;
    }

    .panel-hero__description {
        font-size: .85rem;
        color: #0f3d32;
        line-height: 1.8;
    }

    .panel-metrics {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .75rem;
    }

    .panel-metrics__item {
        background: rgba(255, 255, 255, 0.85);
        border-radius: 18px;
        padding: .75rem;
        border: 1px solid rgba(148, 163, 184, 0.18);
        box-shadow: inset 0 8px 24px -18px rgba(15, 118, 110, 0.4);
        text-align: center;
    }

    .panel-metrics__label {
        display: block;
        font-size: .75rem;
        color: #475569;
    }

    .panel-metrics__value {
        display: block;
        font-size: 1.1rem;
        font-weight: 700;
        color: #0f4c3a;
        margin-top: .35rem;
    }

    .panel-actions {
        display: flex;
        flex-direction: column;
        gap: .6rem;
    }

    .panel-action-btn {
        display: inline-flex;
        align-items: center;
        gap: .65rem;
        justify-content: center;
        padding: .65rem .9rem;
        border-radius: 16px;
        background: rgba(240, 253, 244, 0.85);
        border: 1px solid rgba(16, 185, 129, 0.2);
        color: #047857;
        font-weight: 600;
        text-decoration: none;
        transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
    }

    .panel-action-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 16px 40px -24px rgba(16, 185, 129, 0.45);
        background: rgba(16, 185, 129, 0.12);
    }

    .panel-action-btn--danger {
        background: rgba(254, 242, 242, 0.9);
        border-color: rgba(248, 113, 113, 0.25);
        color: #b91c1c;
    }

    .panel-tabs {
        display: flex;
        overflow-x: auto;
        gap: .6rem;
        padding-top: .5rem;
    }

    .panel-tabs .tab {
        border: none;
        background: rgba(241, 245, 249, 0.8);
        padding: .55rem 1.1rem;
        border-radius: 14px;
        font-size: .85rem;
        color: #0f4c3a;
        font-weight: 600;
        white-space: nowrap;
        cursor: pointer;
    }

    .panel-tabs .tab.active {
        background: linear-gradient(135deg, #10b981, #0f766e);
        color: #fff;
        box-shadow: 0 12px 24px -18px rgba(15, 118, 110, 0.65);
    }

    .panel-tab-contents {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .tab-content {
        display: none;
        background: rgba(255, 255, 255, 0.9);
        border-radius: 18px;
        padding: 1.1rem;
        border: 1px solid rgba(226, 232, 240, 0.6);
        box-shadow: 0 10px 30px -32px rgba(15, 23, 42, 0.6);
    }

    .tab-content.active {
        display: block;
    }

    .panel-search {
        display: flex;
        gap: .75rem;
        margin-bottom: 1rem;
    }

    .panel-search__input {
        display: flex;
        align-items: center;
        gap: .5rem;
        border: 1px solid rgba(148, 163, 184, 0.35);
        border-radius: 14px;
        padding: .5rem .75rem;
        flex: 1;
        background: rgba(248, 250, 252, 0.9);
    }

    .panel-search__input input {
        border: none;
        outline: none;
        background: transparent;
        font-size: .85rem;
        width: 100%;
    }

    .groups-list .group-item {
        display: flex;
        gap: .9rem;
        align-items: center;
        padding: .8rem .9rem;
        border-radius: 16px;
        background: rgba(249, 250, 251, 0.92);
        border: 1px solid rgba(148, 163, 184, 0.25);
        transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
    }

    .groups-list .group-item:hover {
        transform: translateY(-2px);
        background: rgba(255, 255, 255, 0.98);
        box-shadow: 0 16px 32px -28px rgba(15, 23, 42, 0.5);
    }

    .group-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: linear-gradient(135deg, rgba(96, 165, 250, 0.35), rgba(16, 185, 129, 0.28));
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        color: #0f4c3a;
        flex-shrink: 0;
    }

    .group-avatar img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }

    .group-info .group-name a {
        color: #0f4c3a;
        font-weight: 700;
        text-decoration: none;
    }

    .group-info .group-name span.text-muted {
        color: #64748b;
        font-weight: 600;
    }

    .member-list, .admin-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: .65rem;
    }

    .member-item, .admin-item {
        display: flex;
        align-items: center;
        gap: .8rem;
        padding: .7rem .9rem;
        border-radius: 16px;
        border: 1px solid rgba(148, 163, 184, 0.25);
        background: rgba(248, 250, 252, 0.92);
    }

    .member-avatar, .admin-avatar {
        position: relative;
        width: 38px;
        height: 38px;
        border-radius: 12px;
        background: linear-gradient(145deg, rgba(125, 211, 252, 0.3), rgba(125, 211, 252, 0.08));
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        color: #0369a1;
        flex-shrink: 0;
    }

    .member-avatar .member-status {
        position: absolute;
        bottom: -2px;
        right: -2px;
        width: 12px;
        height: 12px;
        border-radius: 999px;
        border: 2px solid #fff;
    }

    .member-status.online {
        background: #22c55e;
    }

    .member-status.offline {
        background: #94a3b8;
    }

    .member-name, .admin-name {
        font-weight: 700;
        color: #0f4c3a;
        text-decoration: none;
    }

    .member-change-role {
        margin-right: auto;
        font-size: .78rem;
        font-weight: 600;
        color: #047857;
        text-decoration: none;
    }

    .post-card {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        border: 1px solid rgba(148, 163, 184, 0.25);
        border-radius: 18px;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.95);
        box-shadow: 0 14px 32px -28px rgba(15, 23, 42, 0.55);
        margin-bottom: 1rem;
        transition: opacity 0.3s ease, transform 0.3s ease;
    }

    .post-card.filter-hidden {
        display: none !important;
    }

    .post-card__media img, .post-card__media video {
        width: 100%;
        border-radius: 14px;
    }

    .post-card__title {
        font-size: 1rem;
        font-weight: 700;
        color: #0f4c3a;
    }

    .post-card__excerpt {
        font-size: .9rem;
        color: #334155;
        line-height: 1.8;
    }

    .post-card__footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.5rem;
        color: #64748b;
    }

    .post-card__stats {
        display: flex;
        gap: 1rem;
        font-size: 0.85rem;
        color: #64748b;
        flex-wrap: wrap;
    }

    .post-card__stats span {
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .post-card__link {
        color: #0d9488;
        font-weight: 600;
        text-decoration: none;
    }

    .empty-state {
        text-align: center;
        padding: 1.25rem;
        font-size: .9rem;
        color: #64748b;
        background: rgba(240, 253, 244, 0.6);
        border-radius: 16px;
    }

    .post-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid rgba(148, 163, 184, 0.2);
    }

    .post-filter-btn {
        padding: 0.5rem 1rem;
        border-radius: 12px;
        border: 1px solid rgba(148, 163, 184, 0.3);
        background: rgba(248, 250, 252, 0.9);
        color: #0f4c3a;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .post-filter-btn:hover {
        background: rgba(240, 253, 244, 0.8);
        border-color: rgba(16, 185, 129, 0.4);
        transform: translateY(-2px);
    }

    .post-filter-btn.active {
        background: linear-gradient(135deg, #10b981, #0f766e);
        color: #fff;
        border-color: transparent;
        box-shadow: 0 4px 12px -4px rgba(15, 118, 110, 0.4);
    }

    .panel-modal {
        position: fixed;
        inset: 0;
        z-index: 1200;
        background: rgba(15, 23, 42, 0.35);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
    }

    .panel-modal__dialog {
        background: #fff;
        border-radius: 24px;
        width: min(560px, 92vw);
        padding: 1.5rem;
        position: relative;
        box-shadow: 0 35px 70px -30px rgba(15, 23, 42, 0.45);
    }

    #chatRequestModal .panel-modal__dialog {
        width: min(760px, 94vw);
        max-height: min(820px, calc(100vh - 3rem));
        display: flex;
        flex-direction: column;
    }

    #chatRequestModal .panel-modal__body {
        min-height: 0;
        display: flex;
        flex-direction: column;
    }

    #chatRequestModal .panel-modal__list {
        max-height: min(610px, calc(100vh - 12rem));
        padding: .15rem .1rem .4rem;
    }

    .panel-modal__close {
        position: absolute;
        top: 1rem;
        left: 1rem;
        border: none;
        background: rgba(241, 245, 249, 0.8);
        width: 30px;
        height: 30px;
        border-radius: 999px;
        font-size: 1.1rem;
        line-height: 1;
        color: #334155;
        cursor: pointer;
    }

    .panel-modal__title {
        font-size: 1.05rem;
        font-weight: 700;
        margin-bottom: 1rem;
        color: #0f4c3a;
    }

    .panel-modal__list {
        display: flex;
        flex-direction: column;
        gap: .6rem;
        list-style: none;
        padding: 0;
        margin: 0;
        max-height: 320px;
        overflow-y: auto;
    }

    .panel-modal__list-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: .75rem 1rem;
        border-radius: 16px;
        border: 1px solid rgba(148, 163, 184, 0.25);
        background: rgba(248, 250, 252, 0.92);
        cursor: pointer;
    }

    .manager-item {
        display: grid;
        grid-template-columns: minmax(180px, .8fr) minmax(300px, 1.4fr);
        align-items: center;
        gap: 1.15rem;
        padding: 1rem;
        border: 1px solid rgba(148, 163, 184, .25);
        border-radius: 20px;
        background: linear-gradient(135deg, rgba(248, 250, 252, .98), rgba(240, 253, 250, .72));
        box-shadow: 0 14px 30px -28px rgba(15, 23, 42, .55);
        transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
    }

    .manager-item:hover {
        border-color: rgba(16, 185, 129, .4);
        box-shadow: 0 20px 38px -30px rgba(5, 150, 105, .65);
        transform: translateY(-1px);
    }

    .manager-request-card__identity {
        display: flex;
        align-items: center;
        gap: .8rem;
        min-width: 0;
    }

    .manager-request-card__avatar {
        flex: 0 0 3.25rem;
        width: 3.25rem;
        height: 3.25rem;
        border-radius: 17px;
        overflow: hidden;
        display: grid;
        place-items: center;
        color: #047857;
        font-weight: 800;
        background: linear-gradient(145deg, #d1fae5, #ccfbf1);
        border: 1px solid rgba(16, 185, 129, .24);
    }

    .manager-request-card__avatar img { width: 100%; height: 100%; object-fit: cover; }
    .manager-request-card__person { min-width: 0; display: grid; gap: .35rem; }
    .manager-request-card__person strong { color: #0f172a; font-size: .95rem; overflow-wrap: anywhere; }
    .manager-request-card__person span { display: inline-flex; align-items: center; gap: .35rem; color: #64748b; font-size: .78rem; }
    .manager-request-card__person i { color: #10b981; }
    .manager-request-card__action { margin: 0; width: 100%; }
    .manager-request-form { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: end; gap: .75rem; width: 100%; }
    .manager-request-form__field { display: grid; gap: .35rem; min-width: 0; }
    .manager-request-form__field label { color: #475569; font-size: .78rem; font-weight: 700; }
    .manager-request-form__field textarea.form-control { min-height: 4.25rem; max-height: 8rem; resize: vertical; border-radius: 13px; border-color: #cbd5e1; padding: .65rem .75rem; font-size: .82rem; line-height: 1.65; background: #fff; }
    .manager-request-form__field textarea.form-control:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, .12); }
    .manager-request-form__submit { min-height: 2.75rem; display: inline-flex; align-items: center; justify-content: center; gap: .45rem; border: 0; border-radius: 13px; padding: .65rem .9rem; background: linear-gradient(135deg, #7c3aed, #5b21b6); color: #fff; font-size: .8rem; font-weight: 800; white-space: nowrap; box-shadow: 0 12px 22px -15px rgba(91, 33, 182, .8); transition: transform .2s ease, box-shadow .2s ease; }
    .manager-request-form__submit:hover { transform: translateY(-1px); box-shadow: 0 16px 26px -15px rgba(91, 33, 182, .9); }

    .manager-chat-tabs { display: grid; grid-template-columns: 1fr 1fr; gap: .45rem; padding: .3rem; margin-bottom: .9rem; border-radius: 16px; background: #f1f5f9; }
    .manager-chat-tab { display: flex; align-items: center; justify-content: center; gap: .45rem; min-height: 2.75rem; border: 0; border-radius: 12px; background: transparent; color: #64748b; font-size: .82rem; font-weight: 800; transition: .2s ease; }
    .manager-chat-tab.active { background: #fff; color: #047857; box-shadow: 0 8px 20px -17px rgba(15, 23, 42, .7); }
    .manager-chat-tab b { display: grid; place-items: center; min-width: 1.3rem; height: 1.3rem; padding: 0 .3rem; border-radius: 999px; background: #ef4444; color: #fff; font-size: .68rem; }
    .manager-chat-pane { min-height: 0; }
    .manager-chat-pane.active { display: flex; flex-direction: column; min-height: 0; }
    .manager-inbox__list { display: grid; gap: .7rem; max-height: min(580px, calc(100vh - 14rem)); overflow-y: auto; padding: .1rem; }
    .manager-inbox__item { padding: 1rem; border: 1px solid rgba(148, 163, 184, .25); border-radius: 18px; background: linear-gradient(135deg, #fff, #f8fafc); }
    .manager-inbox__item > label { display: inline-flex; margin-bottom: .6rem; padding: .25rem .55rem; border-radius: 999px; background: #ecfdf5; color: #047857; font-size: .72rem; font-weight: 800; }
    .manager-inbox__layout { display: grid; grid-template-columns: minmax(130px, .55fr) minmax(180px, 1fr) auto; align-items: center; gap: 1rem; }
    .manager-inbox__sender h6 { margin: 0 0 .25rem; color: #0f172a; font-weight: 800; }
    .manager-inbox__sender small { color: #94a3b8; }
    .manager-inbox__message span { display: block; margin-bottom: .25rem; color: #64748b; font-size: .72rem; font-weight: 700; }
    .manager-inbox__message p { margin: 0; color: #334155; line-height: 1.75; overflow-wrap: anywhere; }
    .manager-inbox__actions { display: flex; align-items: center; gap: .45rem; }
    .manager-inbox__actions form { margin: 0; }
    .manager-inbox__empty { min-height: 230px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .55rem; text-align: center; color: #64748b; padding: 2rem; border: 1px dashed #cbd5e1; border-radius: 20px; background: #f8fafc; }
    .manager-inbox__empty i { font-size: 2rem; color: #94a3b8; }
    .manager-inbox__empty strong { color: #334155; }
    .manager-inbox__empty span { max-width: 340px; font-size: .8rem; line-height: 1.8; }

    @media (max-width: 767px) {
        #exitNavbar {
            display: block;
        }

        #chatRequestModal { padding: .75rem; }
        #chatRequestModal .panel-modal__dialog { width: 100%; max-height: calc(100dvh - 1.5rem); padding: 1.1rem; border-radius: 20px; }
        #chatRequestModal .panel-modal__title { padding-left: 2.3rem; font-size: .98rem; }
        #chatRequestModal .panel-modal__list { max-height: calc(100dvh - 11rem); }
        .manager-item { grid-template-columns: 1fr; gap: .85rem; padding: .85rem; }
        .manager-request-form { grid-template-columns: 1fr; }
        .manager-request-form__submit { width: 100%; }
        .manager-chat-tab { font-size: .74rem; }
        .manager-inbox__layout { grid-template-columns: 1fr; gap: .7rem; }
        .manager-inbox__actions { width: 100%; }
        .manager-inbox__actions form { flex: 1; }
        .manager-inbox__actions .btn { width: 100%; }
    }
</style>
@endpush

@push('scripts')
<script type="module">
    const groupInfoLifecycle = window.GroupChatLifecycle;
    // ============================================================
    // Debounce Helper
    // ============================================================
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                groupInfoLifecycle.clearTimeout(timeout);
                func(...args);
            };
            groupInfoLifecycle.clearTimeout(timeout);
            timeout = groupInfoLifecycle.timeout(later, wait);
        };
    }

    // ============================================================
    // جستجوی گروه‌ها
    // ============================================================
    groupInfoLifecycle.on(document, 'DOMContentLoaded', function() {
        const searchInput = document.getElementById('groupSearch');
        const searchType = document.getElementById('searchType');

        if (searchInput && searchType) {
            const performSearch = debounce(function() {
                const searchText = (searchInput.value || '').toLowerCase();
                const type = searchType.value || 'name';
                const groupsList = document.getElementById('groupsList');

                if (!groupsList) return;

                if (searchText.length < 2) {
                    groupsList.querySelectorAll('.group-item').forEach(item => item.style.display = '');
                    return;
                }

                groupsList.innerHTML = '<div class="empty-state">در حال جستجو…</div>';

                fetch(`/api/groups/search?q=${encodeURIComponent(searchText)}&type=${type}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.groups?.length) {
                        groupsList.innerHTML = '<div class="empty-state">نتیجه‌ای یافت نشد</div>';
                        return;
                    }

                    groupsList.innerHTML = '';
                    data.groups.forEach(group => {
                        const card = document.createElement('div');
                        card.className = 'group-item';
                        card.dataset.groupId = group.id;
                        card.dataset.level = group.location_level;
                        card.innerHTML = `
                            <div class="group-avatar">
                                ${group.avatar
                                    ? `<img src="${group.avatar}" alt="${group.name}">`
                                    : `<div class="default-avatar">${group.name.substring(0, 2)}</div>`
                                }
                            </div>
                            <div class="group-info">
                                <div class="group-main-info">
                                    <div class="group-name">
                                        ${group.is_approved
                                            ? `<a href="/groups/chat/${group.id}">${group.name}</a>`
                                            : `<span class="text-muted">${group.name} (در انتظار تأیید)</span>`
                                        }
                                    </div>
                                    <div class="group-members-count">${group.members_count} عضو</div>
                                </div>
                                <div class="group-secondary-info">
                                    <div class="member-role">
                                        ${group.status === 1
                                            ? `<span>${group.role}</span>`
                                            : `<span>خارج شده <a href="/groups/${group.id}/relogout" class="text-primary">بازگردانی</a></span>`
                                        }
                                    </div>
                                </div>
                            </div>
                        `;
                        groupsList.appendChild(card);
                    });
                })
                .catch(() => {
                    groupsList.innerHTML = '<div class="empty-state text-danger">خطا در بازیابی نتایج.</div>';
                });
            }, 500);

            groupInfoLifecycle.on(searchInput, 'input', performSearch);
            groupInfoLifecycle.on(searchType, 'change', performSearch);
        }
    });

    // ============================================================
    // مدیریت تب‌ها
    // ============================================================
    /* Tab ownership moved to resources/js/group-chat/tabs.js.

            // بارگذاری آمار در صورت کلیک روی تب stats
    */

    // ============================================================
    // جستجوی اعضا
    // ============================================================
    groupInfoLifecycle.on(document, 'DOMContentLoaded', function() {
        const membersSearch = document.getElementById('membersSearch');
        if (membersSearch) {
            const memberItems = Array.from(document.querySelectorAll('.member-item'));
            const membersCount = document.getElementById('membersCount');

            const updateCount = (shown, total) => {
                if (membersCount) {
                    membersCount.textContent = `نمایش ${shown} از ${total}`;
                }
            };

            updateCount(memberItems.length, memberItems.length);

            groupInfoLifecycle.on(membersSearch, 'input', debounce(() => {
                const query = (membersSearch.value || '').trim().toLowerCase();
                let shown = 0;
                memberItems.forEach(li => {
                    const name = (li.dataset.name || '').toLowerCase();
                    const role = (li.dataset.role || '').toLowerCase();
                    const email = (li.dataset.email || '').toLowerCase();
                    const hit = !query || name.includes(query) || role.includes(query) || email.includes(query);
                    li.style.display = hit ? '' : 'none';
                    if (hit) shown++;
                });
                updateCount(shown, memberItems.length);
            }, 200));
        }
    });

    // ============================================================
    // جستجوی مدیران
    // ============================================================
    groupInfoLifecycle.on(document, 'DOMContentLoaded', function() {
        const managerSearch = document.getElementById('searchManagers');
        if (managerSearch) {
            groupInfoLifecycle.on(managerSearch, 'input', debounce(function() {
                const query = (managerSearch.value || '').toLowerCase();
                document.querySelectorAll('.manager-item').forEach(item => {
                    const text = item.dataset.managerSearchText || '';
                    item.style.display = text.includes(query) ? 'flex' : 'none';
                });
            }, 200));
        }
    });

    // ============================================================
    // مدیریت مودال‌ها
    // ============================================================
    const cancelAddGuests = function() {
        document.getElementById('userSearchModal').style.display = 'none';
    };

    const cancelManagerChat = function() {
        document.getElementById('chatRequestModal').style.display = 'none';
    };
    window.GroupChat.actions.register('cancel-add-guests', () => (cancelAddGuests(), true));
    window.GroupChat.actions.register('cancel-manager-chat', () => (cancelManagerChat(), true));

    const addUserButton = document.getElementById('addUserButton');
    if (addUserButton) groupInfoLifecycle.on(addUserButton, 'click', function() {
        document.getElementById('userSearchModal').style.display = 'flex';
    });

    const addChatRequestButton = document.getElementById('addChatRequestButton');
    if (addChatRequestButton) groupInfoLifecycle.on(addChatRequestButton, 'click', function() {
        document.getElementById('chatRequestModal').style.display = 'flex';
    });

    document.querySelectorAll('[data-manager-chat-tab]').forEach(tab => {
        groupInfoLifecycle.on(tab, 'click', function() {
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

    // ============================================================
    // جستجوی کاربران برای اضافه کردن به گروه
    // ============================================================
    groupInfoLifecycle.on(document, 'DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchUsers');
        const resultBox = document.getElementById('searchUserResults');
        let selectedUserId = null;

        if (searchInput && resultBox) {
            groupInfoLifecycle.on(searchInput, 'input', debounce(function() {
                const query = (searchInput.value || '').trim();
                if (query.length < 2) {
                    resultBox.style.display = 'none';
                    resultBox.innerHTML = '';
                    selectedUserId = null;
                    return;
                }

                fetch(`/users/search?q=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(users => {
                        resultBox.innerHTML = '';
                        if (users.length) {
                            users.forEach(user => {
                                const li = document.createElement('li');
                                li.className = 'panel-modal__list-item';
                                li.textContent = `${user.first_name ?? ''} ${user.last_name ?? ''} (${user.email ?? ''})`;
                                groupInfoLifecycle.on(li, 'click', () => {
                                    searchInput.value = user.email ?? '';
                                    selectedUserId = user.id;
                                    resultBox.style.display = 'none';
                                    resultBox.innerHTML = '';
                                });
                                resultBox.appendChild(li);
                            });
                            resultBox.style.display = 'flex';
                            resultBox.style.flexDirection = 'column';
                        } else {
                            resultBox.innerHTML = '<li class="panel-modal__list-item text-muted">کاربری یافت نشد</li>';
                            resultBox.style.display = 'flex';
                        }
                    });
            }, 250));

            groupInfoLifecycle.on(document, 'click', (e) => {
                if (!searchInput.contains(e.target) && !resultBox.contains(e.target)) {
                    resultBox.style.display = 'none';
                }
            });

            const addUsersToGroup = document.getElementById('addUsersToGroup');
            if (addUsersToGroup) groupInfoLifecycle.on(addUsersToGroup, 'click', function() {
                const hours = document.getElementById('hoursUser').value;
                if (!selectedUserId || !hours) {
                    window.GroupChatFeedback?.toast('لطفاً کاربر را انتخاب و مدت ساعت را وارد کنید.', { type: 'error' });
                    return;
                }

                fetch('/groups/add-user', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({
                        user_id: selectedUserId,
                        group_id: {{ $group->id }},
                        hours: hours
                    })
                })
                .then(res => res.json())
                .then(() => {
                    window.GroupChatFeedback?.toast('کاربر با موفقیت اضافه شد', { type: 'success' });
                    selectedUserId = null;
                    searchInput.value = '';
                    document.getElementById('hoursUser').value = '';
                    cancelAddGuests();
                });
            });
        }
    });

    // ============================================================
    // فیلتر پست‌ها
    // ============================================================
    function applyPostFilter(filterType) {
        const postsContainer = document.getElementById('posts-container');
        if (!postsContainer) return;

        const postCards = document.querySelectorAll('#posts-container .post-card');
        if (!postCards.length) return;

        const postsArray = Array.from(postCards);
        let filteredAndSorted = postsArray;

        if (filterType === 'all') {
            filteredAndSorted = postsArray.sort((a, b) => {
                const dateA = parseInt(a.dataset.createdAt || 0);
                const dateB = parseInt(b.dataset.createdAt || 0);
                return dateB - dateA;
            });
        } else if (filterType === 'most-likes') {
            filteredAndSorted = postsArray.sort((a, b) => {
                const likesA = parseInt(a.dataset.likes || 0);
                const likesB = parseInt(b.dataset.likes || 0);
                if (likesB !== likesA) return likesB - likesA;
                const dateA = parseInt(a.dataset.createdAt || 0);
                const dateB = parseInt(b.dataset.createdAt || 0);
                return dateB - dateA;
            });
        } else if (filterType === 'most-dislikes') {
            filteredAndSorted = postsArray.sort((a, b) => {
                const dislikesA = parseInt(a.dataset.dislikes || 0);
                const dislikesB = parseInt(b.dataset.dislikes || 0);
                if (dislikesB !== dislikesA) return dislikesB - dislikesA;
                const dateA = parseInt(a.dataset.createdAt || 0);
                const dateB = parseInt(b.dataset.createdAt || 0);
                return dateB - dateA;
            });
        } else if (filterType === 'most-comments') {
            filteredAndSorted = postsArray.sort((a, b) => {
                const commentsA = parseInt(a.dataset.comments || 0);
                const commentsB = parseInt(b.dataset.comments || 0);
                if (commentsB !== commentsA) return commentsB - commentsA;
                const dateA = parseInt(a.dataset.createdAt || 0);
                const dateB = parseInt(b.dataset.createdAt || 0);
                return dateB - dateA;
            });
        } else if (filterType.startsWith('category-')) {
            const categoryId = filterType.replace('category-', '');
            filteredAndSorted = postsArray.filter(card => {
                return card.dataset.categoryId === categoryId;
            }).sort((a, b) => {
                const dateA = parseInt(a.dataset.createdAt || 0);
                const dateB = parseInt(b.dataset.createdAt || 0);
                return dateB - dateA;
            });
        }

        postsContainer.innerHTML = '';
        filteredAndSorted.forEach(card => {
            card.style.display = '';
            postsContainer.appendChild(card);
        });

        document.querySelectorAll('.post-filter-btn').forEach(b => b.classList.remove('active'));
        const activeBtn = document.querySelector('.post-filter-btn[data-filter="' + filterType + '"]');
        if (activeBtn) activeBtn.classList.add('active');

        if (filteredAndSorted.length === 0 && filterType !== 'all') {
            let emptyMessage = postsContainer.querySelector('.empty-state-filter');
            if (!emptyMessage) {
                emptyMessage = document.createElement('div');
                emptyMessage.className = 'empty-state-filter empty-state';
                emptyMessage.textContent = 'پستی در این فیلتر یافت نشد';
                postsContainer.appendChild(emptyMessage);
            }
            emptyMessage.style.display = 'block';
        } else {
            const emptyMessage = postsContainer.querySelector('.empty-state-filter');
            if (emptyMessage) emptyMessage.style.display = 'none';
        }
    }

    // Event delegation برای فیلترهای پست
    groupInfoLifecycle.on(document, 'click', function(e) {
        const filterBtn = e.target.closest('.post-filter-btn');
        if (!filterBtn) return;
        e.preventDefault();
        e.stopPropagation();
        const filterType = filterBtn.dataset.filter;
        if (filterType) applyPostFilter(filterType);
    });

    // ============================================================
    // بارگذاری آمار گروه
    // ============================================================
    function loadGroupStats() {
        const loadingEl = document.getElementById('stats-loading');
        const errorEl = document.getElementById('stats-error');
        const errorTextEl = document.getElementById('stats-error-text');
        const statsContentEl = document.getElementById('stats-content');

        if (!loadingEl || !errorEl || !errorTextEl || !statsContentEl) {
            console.error('Stats elements not found');
            return;
        }

        loadingEl.style.display = 'block';
        errorEl.style.display = 'none';
        statsContentEl.innerHTML = '';

        const groupId = @json($group->id ?? 0);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        fetch(`/groups/${groupId}/stats`, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            loadingEl.style.display = 'none';
            if (data.status === 'success') {
                displayStats(data.stats);
            } else {
                errorTextEl.textContent = data.message || 'خطا در بارگذاری آمار';
                errorEl.style.display = 'block';
            }
        })
        .catch(error => {
            loadingEl.style.display = 'none';
            errorTextEl.textContent = 'خطا در ارتباط با سرور';
            errorEl.style.display = 'block';
            console.error('Error loading stats:', error);
        });
    }

    function displayStats(stats) {
        const statsContentEl = document.getElementById('stats-content');
        if (!statsContentEl) return;

        statsContentEl.innerHTML = `
            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 2rem;">

                <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; border-radius: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                        <h4 style="margin: 0; font-size: 1rem; font-weight: 600;">اعضای گروه</h4>
                        <i class="fas fa-users" style="font-size: 1.5rem; opacity: 0.8;"></i>
                    </div>
                    <div style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem;">${stats.members.total}</div>
                    <div style="font-size: 0.85rem; opacity: 0.9;">
                        فعال: ${stats.members.active} | ناظر: ${stats.members.observer} | مدیر: ${stats.members.manager}
                    </div>
                </div>

                <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 1.5rem; border-radius: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                        <h4 style="margin: 0; font-size: 1rem; font-weight: 600;">پیام‌ها</h4>
                        <i class="fas fa-comments" style="font-size: 1.5rem; opacity: 0.8;"></i>
                    </div>
                    <div style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem;">${stats.messages.total}</div>
                    <div style="font-size: 0.85rem; opacity: 0.9;">
                        امروز: ${stats.messages.today} | این هفته: ${stats.messages.this_week} | این ماه: ${stats.messages.this_month}
                    </div>
                </div>

                <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 1.5rem; border-radius: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                        <h4 style="margin: 0; font-size: 1rem; font-weight: 600;">پست‌ها</h4>
                        <i class="fas fa-file-alt" style="font-size: 1.5rem; opacity: 0.8;"></i>
                    </div>
                    <div style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem;">${stats.posts.total}</div>
                    <div style="font-size: 0.85rem; opacity: 0.9;">
                        این ماه: ${stats.posts.this_month} | با تصویر: ${stats.posts.with_images}
                    </div>
                </div>

                <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; padding: 1.5rem; border-radius: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                        <h4 style="margin: 0; font-size: 1rem; font-weight: 600;">نظرسنجی‌ها</h4>
                        <i class="fas fa-chart-pie" style="font-size: 1.5rem; opacity: 0.8;"></i>
                    </div>
                    <div style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem;">${stats.polls.total}</div>
                    <div style="font-size: 0.85rem; opacity: 0.9;">
                        فعال: ${stats.polls.active} | منقضی شده: ${stats.polls.expired}
                    </div>
                </div>

                <div class="stat-card" style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); color: white; padding: 1.5rem; border-radius: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                        <h4 style="margin: 0; font-size: 1rem; font-weight: 600;">انتخابات</h4>
                        <i class="fas fa-ballot-check" style="font-size: 1.5rem; opacity: 0.8;"></i>
                    </div>
                    <div style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem;">${stats.elections.total}</div>
                    <div style="font-size: 0.85rem; opacity: 0.9;">
                        فعال: ${stats.elections.active} | بسته شده: ${stats.elections.closed}
                    </div>
                </div>

                <div class="stat-card" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); color: white; padding: 1.5rem; border-radius: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                        <h4 style="margin: 0; font-size: 1rem; font-weight: 600;">گزارش‌ها</h4>
                        <i class="fas fa-flag" style="font-size: 1.5rem; opacity: 0.8;"></i>
                    </div>
                    <div style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem;">${stats.reports.pending + stats.reports.resolved + stats.reports.escalated}</div>
                    <div style="font-size: 0.85rem; opacity: 0.9;">
                        در انتظار: ${stats.reports.pending} | حل شده: ${stats.reports.resolved} | ارجاع شده: ${stats.reports.escalated}
                    </div>
                </div>

            </div>

            <div class="most-active-members" style="background: white; padding: 1.5rem; border-radius: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h4 style="margin: 0 0 1rem 0; font-size: 1.1rem; font-weight: 700; color: #0f172a;">
                    <i class="fas fa-fire ml-2" style="color: #f59e0b;"></i>
                    فعال‌ترین اعضا
                </h4>
                ${stats.most_active_members.length > 0 ? `
                    <div class="members-list" style="display: flex; flex-direction: column; gap: 0.75rem;">
                        ${stats.most_active_members.map((member, index) => `
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: #f8fafc; border-radius: 12px;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <span style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.85rem;">
                                        ${index + 1}
                                    </span>
                                    <span style="font-weight: 600; color: #0f172a;">${member.name}</span>
                                </div>
                                <span style="color: #64748b; font-size: 0.9rem;">
                                    <i class="fas fa-comment ml-1"></i>
                                    ${member.message_count} پیام
                                </span>
                            </div>
                        `).join('')}
                    </div>
                ` : '<p style="color: #64748b; text-align: center; padding: 2rem;">هنوز پیامی ارسال نشده است.</p>'}
            </div>
        `;
    }

    // ============================================================
    // باز کردن تب پست در صورت وجود فیلتر در URL
    // ============================================================
    @if (isset($_GET['filter']))
        groupInfoLifecycle.on(document, 'DOMContentLoaded', function() {
            const groupPanel = document.getElementById('groupInfoPanel');
            const backdrop = document.getElementById('group-info-backdrop');
            if (groupPanel) groupPanel.classList.add('is-open');
            if (backdrop) {
                backdrop.classList.remove('hidden');
                backdrop.classList.add('group-info-backdrop--visible');
            }
            const postTab = document.querySelector('[data-tab="post"]');
            postTab?.click();
        });
    @endif
    window.GroupInfoPanel = Object.freeze({ loadStats: loadGroupStats });
</script>
@endpush
