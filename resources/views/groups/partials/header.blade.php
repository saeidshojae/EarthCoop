@php
    $initials = mb_substr($group->name, 0, 1);
    $memberCount = $group->userCount();
    $guestCount = $group->guestsCount();
@endphp

<div class="relative overflow-hidden rounded-3xl bg-gradient-to-l from-emerald-600 to-emerald-400 text-white shadow-lg">
    <div class="absolute inset-0 opacity-15 bg-[radial-gradient(circle_at_top_right,_rgba(255,255,255,0.6),_transparent_55%)]"></div>
    <div class="relative px-6 py-8 lg:px-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4 lg:gap-6">
                <div class="group-avatar flex h-16 w-16 items-center justify-center rounded-full border border-white/30 bg-white/15 text-2xl font-black uppercase text-white shadow-md lg:h-20 lg:w-20">
                    @if($group->avatar)
                        <img src="{{ asset('images/groups/' . $group->avatar) }}" alt="{{ $group->name }}" class="h-full w-full rounded-full object-cover">
                    @else
                        <span>{{ $initials }}</span>
                    @endif
                </div>
                <div class="flex flex-col items-start gap-2 text-right">
                    <button type="button"
                            onclick="openGroupInfo()"
                            class="text-2xl font-black tracking-tight transition hover:text-white/80 lg:text-3xl">
                        {{ $group->name }}
                    </button>
                    <div class="flex flex-wrap items-center gap-2 text-sm lg:text-base">
                        <span class="inline-flex items-center gap-1 rounded-full bg-white/15 px-3 py-1 font-medium text-white">
                            <i class="fas fa-user-group text-xs"></i>
                            {{ $memberCount }} عضو
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-white/15 px-3 py-1 font-medium text-white/90">
                            <i class="fas fa-user-plus text-xs"></i>
                            {{ $guestCount }} میهمان
                        </span>
                        @if ($group->location_level)
                            <span class="inline-flex items-center gap-1 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-white/80 lg:text-sm">
                                <i class="fas fa-map-marker-alt text-xs"></i>
                                سطح {{ $group->location_level }}
                            </span>
                        @endif
                    </div>
                    @if ($group->description)
                        <p class="max-w-2xl text-sm text-white/85 lg:text-base">{{ Str::limit(strip_tags($group->description), 140) }}</p>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-2 lg:gap-3">
                <button class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/30 bg-white/10 text-lg text-white transition hover:bg-white/20"
                        type="button"
                        onclick="openElectionBox()"
                        title="ایجاد انتخابات">
                    <i class="fas fa-ballot"></i>
                </button>

                <button id="btn-chat-search"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/30 bg-white/10 text-lg text-white transition hover:bg-white/20"
                        type="button"
                        aria-expanded="false"
                        aria-controls="gc-search-wrap"
                        title="جستجو">
                    <i class="fas fa-magnifying-glass"></i>
                </button>

                <div class="dropdown">
                    <button class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/30 bg-white/10 text-lg text-white transition hover:bg-white/20"
                            type="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false">
                        <i class="fas fa-ellipsis-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end text-end shadow-lg">
                        @if($group->location_level != 10)
                            @if (in_array($yourRole, [1,2,3], true))
                                @if (in_array($yourRole, [2,3], true))
                                    <li><a class="dropdown-item" onclick="openGroupEdit()" href="#">ویرایش گروه</a></li>
                                    <li><a class="dropdown-item" id="addUserButton" href="#">اضافه کردن کاربر مهمان به گروه</a></li>
                                    <li><a class="dropdown-item" id="addChatRequestButton" href="#">درخواست چت به مدیران</a></li>
                                    <li><a class="dropdown-item" onclick="openElection2Box()" href="#">➕ افزودن انتخابات</a></li>
                                    <li><a class="dropdown-item" href="{{ route('groups.open', $group) }}">{{ $group->is_open == 0 ? 'فعال کردن نشست' : 'غیرفعال کردن نشست' }}</a></li>
                                @endif
                            @endif
                            <li><a class="dropdown-item text-danger" href="{{ route('groups.logout', $group->id) }}">❌ خروج از گروه</a></li>
                        @else
                            <li><a class="dropdown-item" href="#" onclick="openChatSearch()">🔍 جستجو در چت</a></li>
                            <li><a class="dropdown-item" href="#" onclick="clearChatHistory()">🗑️ پاک کردن تاریخچه چت</a></li>
                            <li><a class="dropdown-item" href="#" onclick="deleteChat()">❌ حذف چت</a></li>
                            <li><a class="dropdown-item" href="#" onclick="reportUser()">🚩 گزارش و ریپورت کاربر</a></li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>

        <div id="gc-search-wrap"
             class="mt-6 hidden overflow-hidden rounded-2xl bg-white/15 backdrop-blur-md lg:mt-8"
             hidden>
            <div class="gc-searchbar flex items-center gap-3 px-4 py-3">
                <i class="fa fa-magnifying-glass text-white/70"></i>
                <input id="gc-search-input"
                       type="text"
                       class="flex-1 bg-transparent text-sm text-white placeholder:text-white/70 focus:outline-none"
                       placeholder="جستجو در پیام‌ها، پست‌ها و نظرسنجی‌ها…"
                       autocomplete="off" />
                <button id="gc-search-clear"
                        type="button"
                        title="پاک‌کردن"
                        class="text-white/80 transition hover:text-white">
                    <i class="fa fa-xmark"></i>
                </button>
            </div>
            <div id="gc-search-dd"
                 class="gc-search-dropdown max-h-80 overflow-y-auto border-t border-white/20 bg-white/90 p-3 text-gray-800 shadow-lg backdrop-blur"
                 hidden>
                <div class="gc-search-status hidden items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                    <span class="gc-spin"></span>
                    در حال جستجو…
                </div>
                <ul class="gc-search-list space-y-2"></ul>
                <button class="gc-search-more mt-3 w-full rounded-xl border border-emerald-500 px-3 py-2 text-sm font-semibold text-emerald-600 transition hover:bg-emerald-500 hover:text-white"
                        hidden>نتایج بیشتر</button>
            </div>
        </div>
    </div>
</div>
