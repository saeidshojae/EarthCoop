<section
    class="bg-white border border-emerald-100 rounded-2xl md:rounded-3xl shadow-md relative overflow-hidden group-info-card"
    data-group-hero>
    <div class="absolute inset-0 pointer-events-none bg-gradient-to-l from-emerald-50/50 via-transparent to-transparent"></div>

    {{-- Mobile: compact sticky identity row. The existing toggle contract is preserved. --}}
    <button type="button"
        data-group-chat-action="toggle-group-hero"
        aria-expanded="false"
        class="lg:hidden w-full relative z-10 flex items-center justify-between gap-3 px-5 py-4 hover:bg-emerald-50/50 active:bg-emerald-50 transition-colors">
        <div class="flex items-center gap-4 flex-1 min-w-0">
            <div class="group-hero__avatar group-hero__avatar--mobile w-14 h-14 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-xl font-black shadow-md flex-shrink-0 border border-emerald-200/60">
                @if($group->avatar)
                    <img src="{{ asset('images/groups/' . $group->avatar) }}" alt="{{ $group->name }}" class="w-full h-full object-cover rounded-2xl">
                @else
                    {{ Str::upper(Str::substr($group->name, 0, 2)) }}
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-lg font-bold text-slate-900 truncate leading-tight mb-1.5">{{ $group->name }}</h1>
                <div class="flex items-center gap-2.5 flex-wrap">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-700 text-xs font-semibold">
                        <i class="fas fa-user-shield text-[10px]"></i>{{ $roleTitle }}
                    </span>
                    <span class="text-xs text-slate-500 font-medium">{{ $memberCount }} عضو</span>
                </div>
            </div>
        </div>
        <div class="flex-shrink-0 w-9 h-9 flex items-center justify-center rounded-xl bg-emerald-50 hover:bg-emerald-100 active:bg-emerald-200 transition-colors ml-2">
            <i class="fas fa-chevron-down text-emerald-600 text-xs transition-transform duration-300" data-group-hero-chevron></i>
        </div>
    </button>

    {{-- Mobile expanded context: identity details + only the three high-value entry points. --}}
    <div class="relative z-10 px-5 py-4 collapse-content lg:hidden border-t border-emerald-100/60"
        data-group-hero-content hidden>
        <div class="space-y-4">
            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600">
                    <i class="fas fa-wave-square"></i>{{ $membershipStatusLabel }}
                </span>
                @if($guestCount > 0)
                    <span class="inline-flex items-center gap-1.5"><i class="fas fa-user-clock text-emerald-500"></i>{{ $guestCount }} مهمان</span>
                @endif
                @if($group->location_level)
                    <span class="inline-flex items-center gap-1.5"><i class="fas fa-map-marker-alt text-emerald-500"></i>{{ $group->location_level }}</span>
                @endif
            </div>

            @if(!empty($group->description))
                <p class="text-xs text-slate-500 leading-7 m-0">{{ Str::limit(strip_tags($group->description), 150) }}</p>
            @endif

            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                <button type="button"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl bg-emerald-600 text-white shadow-sm hover:bg-emerald-700 transition"
                    data-chat-page-action="open-group-info">
                    <i class="fas fa-layer-group"></i><span>پنل گروه</span>
                </button>
                @if(($yourRole ?? 0) !== 5)
                    <button type="button"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl border border-emerald-200 text-emerald-700 bg-white hover:bg-emerald-50 transition"
                        data-chat-page-action="open-blog">
                        <i class="far fa-pen-to-square"></i><span>ایجاد پست</span>
                    </button>
                    <button type="button"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl border border-emerald-200 text-emerald-700 bg-white hover:bg-emerald-50 transition"
                        data-chat-page-action="open-poll">
                        <i class="fas fa-chart-simple"></i><span>نظرسنجی</span>
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Desktop: compact app-style hero. Deep management now lives in Control Center. --}}
    <div class="group-hero__desktop hidden lg:flex relative z-10 items-center justify-between gap-5 px-6 py-4">
        <div class="flex items-center gap-4 min-w-0">
            <div class="group-hero__avatar w-16 h-16 rounded-3xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-2xl font-black shadow-inner">
                @if($group->avatar)
                    <img src="{{ asset('images/groups/' . $group->avatar) }}" alt="{{ $group->name }}" class="w-full h-full object-cover rounded-3xl">
                @else
                    {{ Str::upper(Str::substr($group->name, 0, 2)) }}
                @endif
            </div>
            <div class="min-w-0">
                <div class="flex items-center gap-2.5 flex-wrap">
                    <h1 class="text-xl xl:text-2xl font-black text-slate-900 leading-tight">{{ $group->name }}</h1>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold">
                        <i class="fas fa-user-shield"></i>{{ $roleTitle }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 text-xs">
                        <i class="fas fa-wave-square"></i>{{ $membershipStatusLabel }}
                    </span>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                    <span class="inline-flex items-center gap-1.5"><i class="fas fa-users text-emerald-500"></i>{{ $memberCount }} عضو</span>
                    @if($guestCount > 0)
                        <span class="inline-flex items-center gap-1.5"><i class="fas fa-user-clock text-emerald-500"></i>{{ $guestCount }} مهمان</span>
                    @endif
                    @if($group->location_level)
                        <span class="inline-flex items-center gap-1.5"><i class="fas fa-map-marker-alt text-emerald-500"></i>{{ $group->location_level }}</span>
                    @endif
                    <span class="inline-flex items-center gap-1.5"><i class="fas fa-clock text-emerald-500"></i>{{ verta($group->updated_at)->formatDifference() }}</span>
                </div>
            </div>
        </div>

        <div class="group-hero__desktop-actions flex items-center gap-2 flex-shrink-0">
            <button type="button"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-emerald-600 text-white shadow-sm hover:bg-emerald-700 transition"
                data-chat-page-action="open-group-info">
                <i class="fas fa-layer-group"></i><span>پنل گروه</span>
            </button>
            @if(($yourRole ?? 0) !== 5)
                <button type="button"
                    class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-2xl border border-emerald-200 text-emerald-700 bg-white hover:bg-emerald-50 transition"
                    data-chat-page-action="open-blog" title="ایجاد پست">
                    <i class="far fa-pen-to-square"></i><span class="hidden xl:inline">ایجاد پست</span>
                </button>
                <button type="button"
                    class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-2xl border border-emerald-200 text-emerald-700 bg-white hover:bg-emerald-50 transition"
                    data-chat-page-action="open-poll" title="ساخت نظرسنجی">
                    <i class="fas fa-chart-simple"></i><span class="hidden xl:inline">نظرسنجی</span>
                </button>
            @endif
        </div>
    </div>
</section>
