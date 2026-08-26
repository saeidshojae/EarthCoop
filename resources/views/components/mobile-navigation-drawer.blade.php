@php
    $mobileNavUser = auth()->user();
    $mobileNavGroups = $mobileNavUser?->groups ?? collect();
    $mobileUnreadNotifications = $mobileNavUser?->unreadNotifications?->count() ?? 0;
    $mobilePendingChatRequests = $mobileNavUser
        ? \App\Models\ChatRequest::where('receiver_id', $mobileNavUser->id)->where('status', 'pending')->count()
        : 0;
    $mobileCurrentGroup = request()->route('group');
    if (! $mobileCurrentGroup instanceof \App\Models\Group) {
        $mobileCurrentGroup = null;
    }
@endphp

<div class="mobile-navigation-drawer" x-data="{ openSection: 'primary' }">
    <div class="mobile-navigation-drawer__backdrop" @click="headerMenuOpen = false" aria-hidden="true"></div>

    <aside class="mobile-navigation-drawer__panel" role="dialog" aria-modal="true" aria-label="ناوبری EarthCoop">
        <div class="mobile-navigation-drawer__header">
            <div class="flex items-center gap-3 min-w-0">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 text-earth-green">
                    <i class="fas fa-globe text-lg" aria-hidden="true"></i>
                </span>
                <div class="min-w-0">
                    <div class="font-extrabold text-gentle-black">EarthCoop</div>
                    <div class="text-xs text-gray-500">دسترسی سریع و خدمات</div>
                </div>
            </div>
            <button type="button" @click="headerMenuOpen = false" class="mobile-navigation-close" aria-label="بستن منو">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>

        <div class="mobile-navigation-drawer__body">
            @if($isWelcomeHeader)
                <div class="navigation-section">
                    <div class="navigation-section__links">
                        @foreach($navLinks as $link)
                            <a href="{{ $link['url'] }}" @click="headerMenuOpen = false" class="navigation-link">
                                <i class="fas {{ $link['icon'] }}" aria-hidden="true"></i>
                                <span>{{ $link['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @elseif($isAuth)
                <section class="navigation-section">
                    <button type="button" class="navigation-section__toggle" @click="openSection = openSection === 'primary' ? null : 'primary'">
                        <span><i class="fas fa-compass" aria-hidden="true"></i> اصلی</span>
                        <i class="fas fa-chevron-down" :class="{ 'rotate-180': openSection === 'primary' }" aria-hidden="true"></i>
                    </button>
                    <div x-show="openSection === 'primary'" x-transition class="navigation-section__links">
                        <a href="{{ route('home') }}" class="navigation-link"><i class="fas fa-home"></i><span>خانه</span></a>
                        <a href="{{ route('groups.index') }}" class="navigation-link"><i class="fas fa-users"></i><span>{{ __('navigation.footer_my_groups') }}</span><span class="navigation-badge">{{ $mobileNavGroups->count() }}</span></a>
                        <a href="{{ route('notifications.index') }}" class="navigation-link"><i class="fas fa-bell"></i><span>اعلان‌ها</span>@if($mobileUnreadNotifications > 0)<span class="navigation-badge navigation-badge--alert">{{ $mobileUnreadNotifications }}</span>@endif</a>
                        <a href="{{ route('chat-requests.index') }}" class="navigation-link"><i class="fas fa-comment-dots"></i><span>درخواست‌های چت</span>@if($mobilePendingChatRequests > 0)<span class="navigation-badge navigation-badge--alert">{{ $mobilePendingChatRequests }}</span>@endif</a>
                    </div>
                </section>

                <section class="navigation-section">
                    <button type="button" class="navigation-section__toggle" @click="openSection = openSection === 'participation' ? null : 'participation'">
                        <span><i class="fas fa-people-arrows" aria-hidden="true"></i> مشارکت</span>
                        <i class="fas fa-chevron-down" :class="{ 'rotate-180': openSection === 'participation' }"></i>
                    </button>
                    <div x-show="openSection === 'participation'" x-transition class="navigation-section__links">
                        <a href="{{ route('history.index') }}" class="navigation-link"><i class="fas fa-handshake"></i><span>مشارکت‌های من</span></a>
                        <a href="{{ route('history.election') }}" class="navigation-link"><i class="fas fa-vote-yea"></i><span>انتخابات جاری</span></a>
                        <a href="{{ route('history.poll') }}" class="navigation-link"><i class="fas fa-chart-pie"></i><span>نظرسنجی‌های جاری</span></a>
                    </div>
                </section>

                <section class="navigation-section">
                    <button type="button" class="navigation-section__toggle" @click="openSection = openSection === 'economy' ? null : 'economy'">
                        <span><i class="fas fa-coins" aria-hidden="true"></i> اقتصاد</span>
                        <i class="fas fa-chevron-down" :class="{ 'rotate-180': openSection === 'economy' }"></i>
                    </button>
                    <div x-show="openSection === 'economy'" x-transition class="navigation-section__links">
                        <a href="{{ route('najm-bahar.dashboard') }}" class="navigation-link"><i class="fas fa-wallet"></i><span>حساب مالی نجم بهار</span></a>
                        <a href="{{ route('stock.book') }}" class="navigation-link"><i class="fas fa-chart-line"></i><span>{{ __('navigation.stock_office') }}</span></a>
                        <a href="{{ route('auction.index') }}" class="navigation-link"><i class="fas fa-gavel"></i><span>{{ __('navigation.auctions') }}</span></a>
                        <a href="{{ route('wallet.index') }}" class="navigation-link"><i class="fas fa-wallet"></i><span>{{ __('navigation.wallet') }}</span></a>
                        <a href="{{ route('holding.index') }}" class="navigation-link"><i class="fas fa-layer-group"></i><span>{{ __('navigation.holdings') }}</span></a>
                    </div>
                </section>

                <section class="navigation-section">
                    <button type="button" class="navigation-section__toggle" @click="openSection = openSection === 'organization' ? null : 'organization'">
                        <span><i class="fas fa-sitemap" aria-hidden="true"></i> سازمان و ارتباطات</span>
                        <i class="fas fa-chevron-down" :class="{ 'rotate-180': openSection === 'organization' }"></i>
                    </button>
                    <div x-show="openSection === 'organization'" x-transition class="navigation-section__links">
                        <a href="{{ route('secretariat.directory') }}" class="navigation-link"><i class="fas fa-box-archive"></i><span>دبیرخانه‌های من</span></a>
                        @if($mobileCurrentGroup)
                            <a href="{{ route('secretariat.group', $mobileCurrentGroup) }}" class="navigation-link"><i class="fas fa-people-group"></i><span>دبیرخانه گروه</span></a>
                        @endif
                        <a href="{{ route('my-invation-code') }}" class="navigation-link"><i class="fas fa-user-plus"></i><span>دعوت از دوستان</span></a>
                    </div>
                </section>

                <section class="navigation-section">
                    <button type="button" class="navigation-section__toggle" @click="openSection = openSection === 'support' ? null : 'support'">
                        <span><i class="fas fa-headset" aria-hidden="true"></i> راهنما و پشتیبانی</span>
                        <i class="fas fa-chevron-down" :class="{ 'rotate-180': openSection === 'support' }"></i>
                    </button>
                    <div x-show="openSection === 'support'" x-transition class="navigation-section__links">
                        <a href="{{ route('support.kb.index') }}" class="navigation-link"><i class="fas fa-book"></i><span>پایگاه دانش</span></a>
                        <a href="{{ route('user.tickets.create') }}" class="navigation-link"><i class="fas fa-plus-circle"></i><span>ارسال تیکت</span></a>
                        <a href="{{ route('user.tickets.index') }}" class="navigation-link"><i class="fas fa-ticket-alt"></i><span>تیکت‌های من</span></a>
                        <a href="{{ route('user.support-chat.index') }}" class="navigation-link"><i class="fas fa-comments"></i><span>چت پشتیبانی</span></a>
                    </div>
                </section>

                <section class="navigation-section">
                    <button type="button" class="navigation-section__toggle" @click="openSection = openSection === 'explore' ? null : 'explore'">
                        <span><i class="fas fa-earth-americas" aria-hidden="true"></i> کاوش EarthCoop</span>
                        <i class="fas fa-chevron-down" :class="{ 'rotate-180': openSection === 'explore' }"></i>
                    </button>
                    <div x-show="openSection === 'explore'" x-transition class="navigation-section__links">
                        @foreach($navLinks as $link)
                            <a href="{{ $link['url'] }}" class="navigation-link"><i class="fas {{ $link['icon'] }}"></i><span>{{ $link['label'] }}</span></a>
                        @endforeach
                        <a href="{{ route('terms') }}" class="navigation-link"><i class="fas fa-file-alt"></i><span>{{ __('navigation.charter') }}</span></a>
                        <a href="{{ route('najm-bahar.agreement') }}" class="navigation-link"><i class="fas fa-file-contract"></i><span>{{ __('navigation.financial_agreement') }}</span></a>
                    </div>
                </section>

                @if($mobileNavUser?->is_admin == 1)
                    <section class="navigation-section">
                        <button type="button" class="navigation-section__toggle" @click="openSection = openSection === 'admin' ? null : 'admin'">
                            <span><i class="fas fa-user-shield" aria-hidden="true"></i> مدیریت</span>
                            <i class="fas fa-chevron-down" :class="{ 'rotate-180': openSection === 'admin' }"></i>
                        </button>
                        <div x-show="openSection === 'admin'" x-transition class="navigation-section__links">
                            <a href="{{ route('admin.dashboard') }}" class="navigation-link"><i class="fas fa-gauge-high"></i><span>{{ __('navigation.admin_dashboard') }}</span></a>
                            <a href="{{ route('secretariat.central') }}" class="navigation-link"><i class="fas fa-building-columns"></i><span>دبیرخانه مرکزی</span></a>
                            <a href="{{ route('admin.blog.dashboard') }}" class="navigation-link"><i class="fas fa-blog"></i><span>{{ __('navigation.admin_blog') }}</span></a>
                        </div>
                    </section>
                @endif
            @else
                <div class="navigation-section">
                    <div class="navigation-section__links">
                        @foreach($navLinks as $link)
                            <a href="{{ $link['url'] }}" class="navigation-link"><i class="fas {{ $link['icon'] }}"></i><span>{{ $link['label'] }}</span></a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="mobile-navigation-drawer__footer">
            <div class="flex items-center justify-between gap-3">
                <div class="theme-toggle" onclick="toggleTheme()" role="button" tabindex="0" aria-label="{{ __('navigation.theme_toggle') }}">
                    <span class="theme-toggle-icon sun">☀️</span><span class="theme-toggle-icon moon">🌙</span><div class="theme-toggle-slider"></div>
                </div>
                <div class="flex flex-wrap justify-end gap-1.5">
                    @foreach($locales as $code => $meta)
                        <a href="{{ route('locale.change', $code) }}" class="rounded-full px-2.5 py-1 text-xs {{ $currentLocale === $code ? 'bg-earth-green text-white' : 'bg-light-gray text-gentle-black' }}">{{ $meta['abbr'] }}</a>
                    @endforeach
                </div>
            </div>
        </div>
    </aside>
</div>
