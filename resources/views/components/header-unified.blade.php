@php
    $headerContext = $headerContext ?? (request()->routeIs('welcome') ? 'welcome' : 'default');
    $isWelcomeHeader = $headerContext === 'welcome';
    $isAuth = auth()->check();
    $isHome = request()->routeIs('home');
    $logoTarget = $isAuth && !$isWelcomeHeader ? route('home') : route('welcome');
    $currentLocale = app()->getLocale();
    $locales = [
        'fa' => ['label' => 'فارسی', 'abbr' => 'FA'],
        'en' => ['label' => 'English', 'abbr' => 'EN'],
        'ar' => ['label' => 'العربية', 'abbr' => 'AR'],
    ];
    $tagline = __('langWelcome.tagline');
    $taglineParts = preg_split('/[؛;]+/', $tagline);

    $headerPages = collect();
    if (!$isWelcomeHeader && \Illuminate\Support\Facades\Schema::hasTable('pages')) {
        $headerPageQuery = \App\Models\Page::query()->where('is_published', 1);
        if (\Illuminate\Support\Facades\Schema::hasColumn('pages', 'show_in_header')) {
            $headerPageQuery->where('show_in_header', 1);
        }
        $headerPages = $headerPageQuery->get();
    }

    $navLinks = $isWelcomeHeader
        ? [
            ['url' => route('blog.index'), 'label' => __('navigation.blog'), 'icon' => 'fa-blog'],
            ['url' => '#about', 'label' => __('langWelcome.nav_about'), 'icon' => 'fa-info-circle'],
            ['url' => '#how-it-works', 'label' => __('langWelcome.nav_guide'), 'icon' => 'fa-question-circle'],
            ['url' => '#projects', 'label' => __('langWelcome.nav_projects'), 'icon' => 'fa-seedling'],
            ['url' => '#testimonials', 'label' => __('langWelcome.nav_stories'), 'icon' => 'fa-users'],
        ]
        : [
            ['url' => route('blog.index'), 'label' => __('navigation.blog'), 'icon' => 'fa-blog'],
            ...($isAuth ? [['url' => route('stock.book'), 'label' => __('navigation.stock_office'), 'icon' => 'fa-chart-line']] : []),
            ...$headerPages->map(fn ($page) => [
                'url' => url('/pages/' . $page->slug),
                'label' => $page->translated_title,
                'icon' => 'fa-file-alt',
            ])->all(),
        ];
@endphp

@once
    <style>
        [x-cloak] { display: none !important; }

        @keyframes brand-logo-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        .brand-logo-animated {
            animation: brand-logo-float 3s infinite ease-in-out;
            transform-origin: center;
            will-change: transform;
        }

        html[dir="rtl"] .site-header-row,
        html[dir="ltr"] .site-header-row { flex-direction: row-reverse; }
        html[dir="rtl"] .site-header-row > * { direction: rtl; }
        html[dir="ltr"] .site-header-row > * { direction: ltr; }

        header.site-header-unified .site-header-row .site-header-logo {
            width: 45px !important;
            height: 45px !important;
            min-width: 45px !important;
            max-width: 45px !important;
            max-height: 45px !important;
        }

        header.site-header-unified {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            width: 100% !important;
            z-index: 1000 !important;
        }

        .site-header-spacer { display: block; height: 60px; flex-shrink: 0; }
        .site-header-link { white-space: nowrap; }

        .site-header-mobile-bar {
            display: grid;
            grid-template-columns: 40px minmax(0, 1fr) 40px;
            align-items: center;
            width: 100%;
            min-width: 0;
            gap: 8px;
        }

        .site-header-mobile-brand {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 0;
            justify-self: center;
        }

        .site-header-mobile-brand svg {
            width: 31px;
            height: 31px;
            flex: 0 0 31px;
        }

        .site-header-mobile-brand span {
            font-size: 17px;
            line-height: 1;
            font-weight: 800;
            white-space: nowrap;
            color: var(--color-gentle-black);
        }

        .site-header-mobile-account-slot { justify-self: start; }
        html[dir="rtl"] .site-header-mobile-account-slot { justify-self: end; }
        .site-header-mobile-menu-slot { justify-self: end; }
        html[dir="rtl"] .site-header-mobile-menu-slot { justify-self: start; }

        header.site-header-unified[data-auth-state="guest"] .site-header-mobile-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        html[dir="rtl"] header.site-header-unified[data-auth-state="guest"] .site-header-mobile-bar {
            direction: rtl;
        }

        html[dir="ltr"] header.site-header-unified[data-auth-state="guest"] .site-header-mobile-bar {
            direction: ltr;
        }

        header.site-header-unified[data-auth-state="guest"] .site-header-mobile-account-slot {
            display: none !important;
        }

        header.site-header-unified[data-auth-state="guest"] .site-header-mobile-brand,
        header.site-header-unified[data-auth-state="guest"] .site-header-mobile-menu-slot {
            justify-self: auto;
            margin: 0;
        }

        .site-header-hamburger {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: stretch;
            width: 25px;
            height: 18px;
            padding: 0;
        }
        .site-header-hamburger span {
            display: block;
            width: 25px;
            height: 2.5px;
            min-height: 2.5px;
            border-radius: 999px;
            background: var(--color-gentle-black);
            transition: transform .25s ease, opacity .2s ease;
        }
        .site-header-hamburger.is-open span:nth-child(1) { transform: translateY(7.5px) rotate(45deg); }
        .site-header-hamburger.is-open span:nth-child(2) { opacity: 0; }
        .site-header-hamburger.is-open span:nth-child(3) { transform: translateY(-7.5px) rotate(-45deg); }

        .mobile-navigation-drawer {
            position: fixed;
            inset: 0;
            z-index: 1150;
            direction: rtl;
        }
        .mobile-navigation-drawer__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, .46);
            backdrop-filter: blur(2px);
        }
        .mobile-navigation-drawer__panel {
            position: absolute;
            top: 0;
            right: 0;
            width: min(88vw, 370px);
            height: 100dvh;
            display: flex;
            flex-direction: column;
            background: var(--color-pure-white);
            box-shadow: -18px 0 50px rgba(15, 23, 42, .22);
            overflow: hidden;
        }
        html[dir="ltr"] .mobile-navigation-drawer__panel {
            right: auto;
            left: 0;
            box-shadow: 18px 0 50px rgba(15, 23, 42, .22);
        }
        .mobile-navigation-drawer__header,
        .mobile-navigation-drawer__footer {
            flex: 0 0 auto;
            padding: 14px 16px;
            border-color: #e5e7eb;
            background: var(--color-pure-white);
        }
        .mobile-navigation-drawer__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border-bottom-width: 1px;
        }
        .mobile-navigation-drawer__footer { border-top-width: 1px; }
        .mobile-navigation-drawer__body {
            flex: 1 1 auto;
            overflow-y: auto;
            padding: 12px;
            overscroll-behavior: contain;
        }
        .mobile-navigation-close {
            width: 36px;
            height: 36px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #475569;
            background: #f8fafc;
        }
        .navigation-section {
            margin-bottom: 8px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            background: #fff;
        }
        .navigation-section__toggle {
            width: 100%;
            min-height: 48px;
            padding: 0 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            color: var(--color-gentle-black);
            font-size: 14px;
            font-weight: 800;
            text-align: right;
        }
        .navigation-section__toggle > span {
            display: inline-flex;
            align-items: center;
            gap: 9px;
        }
        .navigation-section__toggle > span > i { color: var(--color-earth-green); width: 18px; text-align: center; }
        .navigation-section__toggle > i { font-size: 11px; color: #64748b; transition: transform .2s ease; }
        .navigation-section__links {
            padding: 4px 8px 9px;
            border-top: 1px solid #f1f5f9;
        }
        .navigation-link {
            min-height: 42px;
            display: grid;
            grid-template-columns: 22px minmax(0, 1fr) auto;
            align-items: center;
            gap: 9px;
            padding: 8px 10px;
            border-radius: 10px;
            color: var(--color-gentle-black);
            font-size: 13.5px;
            text-decoration: none;
        }
        .navigation-link:hover,
        .navigation-link:focus-visible { background: rgba(16, 185, 129, .08); color: var(--color-dark-green); }
        .navigation-link > i { width: 20px; text-align: center; color: var(--color-ocean-blue); }
        .navigation-badge {
            min-width: 22px;
            height: 22px;
            padding: 0 6px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 800;
            color: #fff;
            background: var(--color-digital-gold);
        }
        .navigation-badge--alert { background: var(--color-red-tomato); }

        @media (max-width: 1535px) {
            header.site-header-unified .site-header-mobile-bar {
                align-items: center;
                align-content: center;
            }

            header.site-header-unified .site-header-mobile-brand,
            header.site-header-unified .site-header-mobile-menu-slot,
            header.site-header-unified .site-header-mobile-account-slot {
                align-self: center;
            }
        }

        @media (max-width: 1023px) {
            header.site-header-unified {
                box-sizing: border-box;
                height: 60px !important;
                min-height: 60px !important;
                padding: 0 14px !important;
                overflow: visible !important;
            }
            header.site-header-unified .site-header-mobile-menu-slot:has(.site-header-mobile-back) {
                gap: 10px !important;
                width: 84px !important;
            }
            .site-header-spacer { height: 60px; }
            .unified-public-sidebar { display: none !important; }
        }

        @media (min-width: 1024px) and (max-width: 1535px) {
            header.site-header-unified {
                box-sizing: border-box;
                height: 84px !important;
                min-height: 84px !important;
                padding: 19.5px 32px !important;
                overflow: visible !important;
            }
            header.site-header-unified .site-header-row { height: 45px; }
            header.site-header-unified .site-header-mobile-menu-slot:has(.site-header-mobile-back) {
                gap: 12px !important;
                width: 90px !important;
            }
            .site-header-spacer { height: 84px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .brand-logo-animated { animation: none !important; }
        }
    </style>
@endonce

<header x-data="{ headerMenuOpen: false, localeOpen: false }"
        @keydown.escape.window="headerMenuOpen = false; localeOpen = false"
        class="site-header-unified bg-pure-white shadow-md font-vazirmatn"
        style="background-color: var(--color-pure-white);"
        data-header-context="{{ $headerContext }}"
        data-auth-state="{{ $isAuth ? 'authenticated' : 'guest' }}">

    <div class="site-header-row container mx-auto hidden 2xl:flex items-center justify-between gap-3 flex-nowrap">
        <div class="flex items-center gap-2 sm:gap-3 min-w-0 flex-shrink-0">
            @if(!$isWelcomeHeader && !$isHome)
                <a href="{{ route('home') }}"
                   data-earthcoop-history-back="true"
                   class="flex-shrink-0 text-gray-600 hover:text-earth-green transition p-1"
                   aria-label="بازگشت"
                   title="بازگشت">
                    <i class="fas fa-arrow-left text-lg" aria-hidden="true"></i>
                </a>
            @endif
            <a href="{{ $logoTarget }}" class="flex items-center gap-3 hover:opacity-80 transition min-w-0">
                <svg width="45" height="45" class="site-header-logo brand-logo-animated flex-shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2Z" fill="#10b981" opacity="0.8"/>
                    <path d="M12 2C10.5 4 8 6 8 9C8 12 12 14 12 14C12 14 16 12 16 9C16 6 13.5 4 12 2ZM12 14C12 14 10 16 10 18C10 20 12 22 12 22" fill="#047857"/>
                </svg>
                <span class="text-2xl 2xl:text-3xl font-extrabold text-gentle-black whitespace-nowrap">EarthCoop</span>
                @if($isWelcomeHeader)
                    <span class="hidden 2xl:flex flex-col border-r-2 border-gray-200 pr-4 mr-1 text-sm text-gray-500 leading-tight whitespace-nowrap">
                        <span>{{ $taglineParts[0] ?? $tagline }}</span>
                        <span>{{ $taglineParts[1] ?? '' }}</span>
                    </span>
                @endif
            </a>
        </div>

        <nav class="hidden 2xl:flex items-center justify-center gap-5 flex-1 min-w-0 text-gentle-black">
            @foreach($navLinks as $link)
                <a href="{{ $link['url'] }}" class="site-header-link relative flex items-center gap-2 pb-1 font-medium hover:text-earth-green transition group">
                    <i class="fas {{ $link['icon'] }} text-earth-green" aria-hidden="true"></i>
                    <span>{{ $link['label'] }}</span>
                    <span class="absolute bottom-0 right-0 w-0 h-0.5 bg-earth-green group-hover:w-full transition-all duration-300"></span>
                </a>
            @endforeach
        </nav>

        <div class="hidden 2xl:flex items-center gap-3 flex-shrink-0">
            <div class="theme-toggle" onclick="toggleTheme()" title="{{ __('navigation.theme_toggle') }}" role="button" tabindex="0" aria-label="{{ __('navigation.theme_toggle') }}">
                <span class="theme-toggle-icon sun">☀️</span><span class="theme-toggle-icon moon">🌙</span><div class="theme-toggle-slider"></div>
            </div>
            <div class="relative" @click.outside="localeOpen = false">
                <button type="button" @click="localeOpen = !localeOpen" class="flex items-center gap-2 rounded-full border border-gray-200 bg-light-gray px-3 py-1 shadow-sm" :aria-expanded="localeOpen">
                    <span class="text-xs font-semibold">{{ $locales[$currentLocale]['abbr'] ?? strtoupper($currentLocale) }}</span>
                    <i class="fas fa-chevron-down text-xs" aria-hidden="true"></i>
                </button>
                <div x-show="localeOpen" x-cloak class="absolute left-0 mt-2 w-32 rounded-lg border border-gray-200 bg-white py-2 shadow-lg z-50">
                    @foreach($locales as $code => $meta)
                        @if($currentLocale !== $code)
                            <a href="{{ route('locale.change', $code) }}" class="flex items-center gap-2 px-3 py-2 text-xs text-gentle-black hover:bg-light-gray"><strong>{{ $meta['abbr'] }}</strong><span>{{ $meta['label'] }}</span></a>
                        @endif
                    @endforeach
                </div>
            </div>

            @if($isWelcomeHeader)
                <button type="button" onclick="openModal()" class="bg-earth-green text-white px-6 py-2 rounded-full shadow-md hover:bg-dark-green transition font-medium">{{ __('langWelcome.btn_join') }}</button>
                <a href="{{ route('login') }}" class="bg-ocean-blue text-white px-6 py-2 rounded-full shadow-md hover:bg-dark-blue transition font-medium">{{ __('langWelcome.btn_login') }}</a>
                <a href="{{ route('invite') }}" class="bg-digital-gold text-white px-6 py-2 rounded-full shadow-md transition font-medium">درخواست کد دعوت</a>
            @elseif($isAuth)
                <a href="{{ route('support.kb.index') }}" class="inline-flex w-10 h-10 items-center justify-center rounded-full border border-gray-200 bg-white shadow-sm" title="پایگاه دانش"><i class="fas fa-circle-question text-ocean-blue"></i></a>
                @include('components.user-dropdown-unified')
            @else
                <a href="{{ route('login') }}" class="bg-earth-green text-white px-5 py-2 rounded-full shadow-md font-medium">{{ __('navigation.login') }}</a>
                <a href="{{ request()->routeIs('terms') ? '#terms-acceptance' : route('terms') . '#terms-acceptance' }}" class="bg-ocean-blue text-white px-5 py-2 rounded-full shadow-md font-medium">{{ __('navigation.register') }}</a>
            @endif
        </div>
    </div>

    <div class="site-header-mobile-bar 2xl:hidden">
        <div class="site-header-mobile-account-slot">
            @if(!$isWelcomeHeader && $isAuth)
                @include('components.mobile-account-menu')
            @else
                <span class="block h-9 w-9" aria-hidden="true"></span>
            @endif
        </div>

        <a href="{{ $logoTarget }}" class="site-header-mobile-brand" aria-label="EarthCoop">
            <svg class="brand-logo-animated" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2Z" fill="#10b981" opacity="0.8"/>
                <path d="M12 2C10.5 4 8 6 8 9C8 12 12 14 12 14C12 14 16 12 16 9C16 6 13.5 4 12 2ZM12 14C12 14 10 16 10 18C10 20 12 22 12 22" fill="#047857"/>
            </svg>
            <span>EarthCoop</span>
        </a>

        <div class="site-header-mobile-menu-slot">
            @if(!$isWelcomeHeader && !$isHome)
                <a href="{{ route('home') }}"
                   data-earthcoop-history-back="true"
                   class="site-header-mobile-back"
                   aria-label="بازگشت"
                   title="بازگشت">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                </a>
            @endif

            <button type="button"
                    @click="headerMenuOpen = !headerMenuOpen"
                    :class="{ 'is-open': headerMenuOpen }"
                    class="site-header-hamburger focus:outline-none"
                    aria-controls="site-header-mobile-menu"
                    :aria-expanded="headerMenuOpen"
                    aria-label="{{ __('navigation.open_menu') }}">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>

    <div id="site-header-mobile-menu"
         x-show="headerMenuOpen"
         x-cloak
         x-transition.opacity
         class="2xl:hidden">
        @include('components.mobile-navigation-drawer', [
            'isWelcomeHeader' => $isWelcomeHeader,
            'isAuth' => $isAuth,
            'navLinks' => $navLinks,
            'locales' => $locales,
            'currentLocale' => $currentLocale,
        ])
    </div>
</header>
<div class="site-header-spacer" aria-hidden="true"></div>