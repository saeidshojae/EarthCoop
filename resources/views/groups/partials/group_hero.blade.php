    <section
        class="bg-white border border-emerald-100 rounded-2xl md:rounded-3xl shadow-md relative overflow-hidden group-info-card"
        data-group-hero>
        <div
            class="absolute inset-0 pointer-events-none bg-gradient-to-l from-emerald-50/50 via-transparent to-transparent">
        </div>

        <!-- نسخه خلاصه برای موبایل -->
        <button type="button" data-group-chat-action="toggle-group-hero" aria-expanded="false"
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
                    data-group-hero-chevron></i>
            </div>
        </button>

        <!-- محتوای کامل - در موبایل با expand/collapse -->
        <div class="relative z-10 px-5 py-5 collapse-content lg:hidden border-t border-emerald-100/60"
            data-group-hero-content hidden>
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
