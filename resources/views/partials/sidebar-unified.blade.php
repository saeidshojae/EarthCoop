<!-- Tailwind & Bootstrap CSS via Vite -->

@if(auth()->check())
    @php
        $groups = auth()->user()->groups;
        $generalGroups = $groups->where('type', 'general');
        $specializedGroups = $groups->where('type', 'specialized');
        $exclusiveGroups = $groups->where('type', 'exclusive');
    @endphp

    @once
        <style>
            .unified-public-sidebar .support-submenu {
                margin-top: .5rem !important;
                margin-inline-start: 1.5rem !important;
                padding: .375rem !important;
                border-inline-start: 2px solid rgba(16, 185, 129, .2);
                border-radius: .75rem;
                background: rgba(248, 250, 252, .72);
            }
            .unified-public-sidebar .support-submenu > li {
                margin: 0 !important;
                padding: 0 !important;
            }
            .unified-public-sidebar .support-submenu-link {
                display: grid !important;
                grid-template-columns: 1.125rem minmax(0, 1fr) auto;
                align-items: center !important;
                width: 100% !important;
                min-height: 2.5rem;
                margin: 0 !important;
                padding: .625rem .75rem !important;
                column-gap: .625rem !important;
                border-radius: .625rem !important;
                color: var(--color-gentle-black) !important;
                background: transparent !important;
                font-size: .9rem !important;
                font-weight: 500 !important;
                text-align: start !important;
                transform: none !important;
            }
            .unified-public-sidebar .support-submenu-link:hover {
                color: var(--color-dark-green) !important;
                background: rgba(16, 185, 129, .1) !important;
                transform: none !important;
            }
            .unified-public-sidebar .support-submenu-link.active {
                color: var(--color-dark-green) !important;
                background: rgba(16, 185, 129, .16) !important;
                font-weight: 700 !important;
            }
            .unified-public-sidebar .support-submenu-link > i {
                width: 1.125rem;
                margin: 0 !important;
                flex: 0 0 1.125rem;
                text-align: center;
                color: var(--color-ocean-blue) !important;
            }
            .unified-public-sidebar .support-submenu-label {
                min-width: 0;
                text-align: start;
                line-height: 1.5;
            }
            .unified-public-sidebar .support-submenu-link .badge {
                margin: 0 !important;
                justify-self: end;
            }
            body.dark-mode .unified-public-sidebar .support-submenu {
                background: rgba(15, 23, 42, .5);
                border-color: rgba(52, 211, 153, .25);
            }
            body.dark-mode .unified-public-sidebar .support-submenu-link {
                color: var(--text-dark) !important;
            }
            body.dark-mode .unified-public-sidebar .support-submenu-link:hover,
            body.dark-mode .unified-public-sidebar .support-submenu-link.active {
                color: #a7f3d0 !important;
                background: rgba(16, 185, 129, .18) !important;
            }
        </style>
    @endonce

    <!-- Right Sidebar - Collapsible on mobile -->
    <aside x-data="{ open: false }" @click.away="open = false" class="home-sidebar unified-public-sidebar w-full lg:w-80 bg-white rounded-2xl shadow-lg p-0 lg:p-6 flex-shrink-0 lg:sticky lg:top-24 h-fit border border-gray-200 transition-all duration-300 hover:shadow-xl"
           style="background-color: var(--color-pure-white);">
        <button type="button" @click="open = !open" class="home-sidebar-toggle w-full text-left text-xl lg:text-2xl font-bold text-gentle-black flex items-center justify-between gap-3 px-4 py-3 lg:px-0 lg:py-3 border-gray-200" :class="open ? 'border-b' : 'lg:border-b'" style="color: var(--color-gentle-black);">
            <div class="flex items-center gap-3">
                <i class="fas fa-bars" style="color: var(--color-earth-green);"></i>
                <span>منو</span>
            </div>
            <i class="lg:hidden" :class="open ? 'fas fa-chevron-up' : 'fas fa-chevron-down'"></i>
        </button>

        <nav x-cloak :class="open ? 'block' : 'hidden lg:block'" class="home-sidebar-nav lg:block overflow-hidden transition-all duration-200 ease-out lg:border-t lg:border-gray-200">
            <ul class="space-y-2">
                <!-- Notifications -->
                <li class="sidebar-menu-item">
                    <a href="{{ route('notifications.index') }}" class="sidebar-menu-link {{ request()->routeIs('notifications.*') ? 'active' : '' }} block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                        <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                        <i class="fas fa-bell" style="color: var(--color-ocean-blue);"></i>
                        <span class="flex-grow text-right mx-3">اعلان‌ها</span>
                        @if(auth()->user()->unreadNotifications->count() > 0)
                            <span class="badge text-white text-xs px-2 py-1 rounded-full font-bold badge-pulse" style="background-color: var(--color-red-tomato);">{{ auth()->user()->unreadNotifications->count() }}</span>
                        @endif
                    </a>
                </li>

                <!-- Private Conversations -->
                @php
                    $pendingChatRequestCount = \App\Models\ChatRequest::where('receiver_id', auth()->id())
                        ->where('status', 'pending')
                        ->count();
                @endphp
                <li class="sidebar-menu-item">
                    <a href="{{ route('chat-requests.index') }}" class="sidebar-menu-link {{ request()->routeIs('chat-requests.*') ? 'active' : '' }} block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                        <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                        <i class="fas fa-comments" style="color: var(--color-ocean-blue);"></i>
                        <span class="flex-grow text-right mx-3">گفتگوهای خصوصی</span>
                        @if($pendingChatRequestCount > 0)
                            <span class="badge text-white text-xs px-2 py-1 rounded-full font-bold badge-pulse" style="background-color: var(--color-red-tomato);">{{ $pendingChatRequestCount }}</span>
                        @endif
                    </a>
                </li>

                <!-- Groups -->
                <li class="sidebar-menu-item">
                    <a href="{{ route('groups.index') }}" class="sidebar-menu-link {{ request()->routeIs('groups.index') ? 'active' : '' }} block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                        <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                        <i class="fas fa-users" style="color: var(--color-ocean-blue);"></i>
                        <span class="flex-grow text-right mx-3">{{ __('navigation.footer_my_groups') }}</span>
                        @if($groups->count() > 0)
                            <span class="badge text-xs px-2 py-1 rounded-full font-bold" style="background-color: var(--color-digital-gold); color: var(--color-pure-white);">{{ $groups->count() }}</span>
                        @endif
                    </a>
                </li>

                <!-- Collaborations -->
                <li class="sidebar-menu-item">
                    <a href="{{ route('history.index') }}" class="sidebar-menu-link {{ request()->routeIs('history.index') ? 'active' : '' }} block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                        <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                        <i class="fas fa-handshake" style="color: var(--color-digital-gold);"></i>
                        <span class="flex-grow text-right mx-3">مشارکت‌های من</span>
                    </a>
                </li>

                <!-- Elections -->
                <li class="sidebar-menu-item">
                    <a href="{{ route('history.election') }}" class="sidebar-menu-link {{ request()->routeIs('history.election') ? 'active' : '' }} block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                        <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                        <i class="fas fa-vote-yea" style="color: var(--color-earth-green);"></i>
                        <span class="flex-grow text-right mx-3">انتخابات جاری</span>
                    </a>
                </li>

                <!-- Polls -->
                <li class="sidebar-menu-item">
                    <a href="{{ route('history.poll') }}" class="sidebar-menu-link {{ request()->routeIs('history.poll') ? 'active' : '' }} block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                        <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                        <i class="fas fa-chart-pie" style="color: var(--color-ocean-blue);"></i>
                        <span class="flex-grow text-right mx-3">نظرسنجی‌های جاری</span>
                    </a>
                </li>

                <!-- Spring Account -->
                @php
                    $accountService = app(\App\Modules\NajmBahar\Services\AccountService::class);
                    $needsNajmAgreement = !$accountService->hasMainAccount(auth()->id());
                @endphp
                <li class="sidebar-menu-item">
                    <a href="{{ route('najm-bahar.dashboard') }}" class="sidebar-menu-link {{ request()->routeIs('najm-bahar.*') ? 'active' : '' }} block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group {{ $needsNajmAgreement ? 'blinking-item' : '' }}" style="color: var(--color-gentle-black);">
                        <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                        <i class="fas fa-wallet" style="color: var(--color-digital-gold);"></i>
                        <span class="flex-grow text-right mx-3">حساب مالی نجم بهار</span>
                    </a>
                </li>

                <!-- Invite Friends -->
                <li class="sidebar-menu-item">
                    <a href="{{ route('my-invation-code') }}" class="sidebar-menu-link {{ request()->routeIs('my-invation-code') ? 'active' : '' }} block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                        <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                        <i class="fas fa-user-plus" style="color: var(--color-earth-green);"></i>
                        <span class="flex-grow text-right mx-3">دعوت از دوستان</span>
                    </a>
                </li>

                <!-- Edit Profile -->
                <li class="sidebar-menu-item">
                    <a href="{{ route('profile.edit') }}" class="sidebar-menu-link {{ request()->routeIs('profile.edit*') ? 'active' : '' }} block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                        <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                        <i class="fas fa-cog" style="color: var(--color-ocean-blue);"></i>
                        <span class="flex-grow text-right mx-3">ویرایش حساب کاربری</span>
                    </a>
                </li>

                <!-- Support -->
                <li class="sidebar-menu-item" x-data="{ open: {{ request()->routeIs('support.kb.*', 'user.tickets.*', 'user.support-chat.*') ? 'true' : 'false' }} }">
                    <button @click="open = !open" class="sidebar-menu-link {{ request()->routeIs('support.kb.*', 'user.tickets.*', 'user.support-chat.*') ? 'active' : '' }} w-full block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                        <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                        <div class="flex items-center">
                            <i class="fas fa-headset" style="color: var(--color-ocean-blue);"></i>
                            <span class="flex-grow text-right mx-3">پشتیبانی</span>
                        </div>
                        <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                    </button>
                    <ul x-show="open"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 transform -translate-y-2"
                        x-transition:enter-end="opacity-100 transform translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 transform translate-y-0"
                        x-transition:leave-end="opacity-0 transform -translate-y-2"
                        class="support-submenu space-y-1">
                        <li>
                            <a href="{{ route('support.kb.index') }}" class="sidebar-menu-link support-submenu-link {{ request()->routeIs('support.kb.*') ? 'active' : '' }} relative group">
                                <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                                <i class="fas fa-book text-sm" aria-hidden="true"></i>
                                <span class="support-submenu-label">پایگاه دانش</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('user.tickets.create') }}" class="sidebar-menu-link support-submenu-link {{ request()->routeIs('user.tickets.create') ? 'active' : '' }} relative group">
                                <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                                <i class="fas fa-plus-circle text-sm" aria-hidden="true"></i>
                                <span class="support-submenu-label">ارسال تیکت</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('user.tickets.index') }}" class="sidebar-menu-link support-submenu-link {{ request()->routeIs('user.tickets.index', 'user.tickets.show') ? 'active' : '' }} relative group">
                                <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                                <i class="fas fa-ticket-alt text-sm" aria-hidden="true"></i>
                                <span class="support-submenu-label">تیکت‌ها</span>
                                @php
                                    $openTicketsCount = \App\Models\Ticket::where(function($q) {
                                        $q->where('user_id', auth()->id())
                                          ->orWhere('email', auth()->user()->email);
                                    })->whereIn('status', ['open', 'in-progress'])->count();
                                @endphp
                                @if($openTicketsCount > 0)
                                    <span class="badge text-white text-xs px-2 py-1 rounded-full font-bold" style="background-color: var(--color-red-tomato);">{{ $openTicketsCount }}</span>
                                @endif
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('user.support-chat.index') }}" class="sidebar-menu-link support-submenu-link {{ request()->routeIs('user.support-chat.*') ? 'active' : '' }} relative group">
                                <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                                <i class="fas fa-comments text-sm" aria-hidden="true"></i>
                                <span class="support-submenu-label">چت پشتیبانی</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Admin Panel (if admin) -->
                @if (auth()->user()->is_admin == 1)
                    <li class="sidebar-menu-item">
                        <a href="{{ route('admin.dashboard') }}" class="sidebar-menu-link block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                            <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                            <i class="fas fa-user-shield" style="color: #9333ea;"></i>
                            <span class="flex-grow text-right mx-3">{{ __('navigation.admin_dashboard') }}</span>
                        </a>
                    </li>
                @endif

                <!-- Logout -->
                <li class="sidebar-menu-item">
                    <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form-sidebar').submit();" class="sidebar-menu-link block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                        <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                        <i class="fas fa-sign-out-alt" style="color: var(--color-digital-gold);"></i>
                        <span class="flex-grow text-right mx-3">{{ __('navigation.logout') }}</span>
                    </a>
                    <form id="logout-form-sidebar" action="{{ route('logout') }}" method="POST" class="hidden">
                        @csrf
                    </form>
                </li>
            </ul>
        </nav>

        <div class="mt-6 pt-4 border-t border-gray-200 text-center text-sm text-gray-500 hidden lg:block">
            نسخه ۲.۱.۰ - EarthCoop
        </div>
    </aside>
@endif
