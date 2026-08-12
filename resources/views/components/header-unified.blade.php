@php
    $headerContext = $headerContext ?? (request()->routeIs('welcome') ? 'welcome' : 'default');
    $isWelcomeHeader = $headerContext === 'welcome';
    $isAuth = auth()->check();
    $isHome = request()->routeIs('home');
    $previousUrl = url()->previous();
    $backUrl = $previousUrl === url()->current() ? route('home') : $previousUrl;
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
        .site-header-menu-panel { top: 100%; inset-inline: 0; }
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
        header.site-header-unified .site-header-mobile-actions.is-authenticated {
            gap: 16px !important;
        }
        @media (max-width: 768px) {
            header.site-header-unified {
                box-sizing: border-box;
                height: 77px !important;
                min-height: 77px !important;
                padding: 16px 24px !important;
                overflow: visible !important;
            }
            header.site-header-unified .site-header-row { height: 45px; }
        }
        @media (min-width: 769px) and (max-width: 1535px) {
            header.site-header-unified {
                box-sizing: border-box;
                height: 84px !important;
                min-height: 84px !important;
                padding: 19.5px 32px !important;
                overflow: visible !important;
            }
            header.site-header-unified .site-header-row { height: 45px; }
        }
        .site-header-hamburger {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: stretch;
            width: 30px;
            height: 19px;
        }
        .site-header-hamburger span {
            display: block; width: 30px; height: 3px; min-height: 3px; border-radius: 999px;
            background: var(--color-gentle-black); transition: transform .25s ease, opacity .2s ease;
        }
        .site-header-hamburger.is-open span:nth-child(1) { transform: translateY(8px) rotate(45deg); }
        .site-header-hamburger.is-open span:nth-child(2) { opacity: 0; }
        .site-header-hamburger.is-open span:nth-child(3) { transform: translateY(-8px) rotate(-45deg); }
        .site-header-link { white-space: nowrap; }
    </style>
@endonce

<header x-data="{ headerMenuOpen: false, localeOpen: false }"
        @keydown.escape.window="headerMenuOpen = false; localeOpen = false"
        class="site-header-unified relative bg-pure-white shadow-md py-4 px-4 sm:px-6 2xl:px-8 sticky top-0 z-50 font-vazirmatn"
        style="background-color: var(--color-pure-white);"
        data-header-context="{{ $headerContext }}">
    <div class="site-header-row container mx-auto flex items-center justify-between gap-3 flex-nowrap">
        <div class="flex items-center gap-2 sm:gap-3 min-w-0 flex-shrink-0">
            @if(!$isWelcomeHeader && !$isHome)
                <a href="{{ $backUrl }}" class="flex-shrink-0 text-gray-600 hover:text-earth-green transition p-1" aria-label="بازگشت">
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
                <a href="{{ route('invite') }}" class="bg-digital-gold text-white px-6 py-2 rounded-full shadow-md transition font-medium">{{ __('langWelcome.btn_invite') }}</a>
            @elseif($isAuth)
                <a href="{{ route('support.kb.index') }}" class="inline-flex w-10 h-10 items-center justify-center rounded-full border border-gray-200 bg-white shadow-sm" title="پایگاه دانش"><i class="fas fa-circle-question text-ocean-blue"></i></a>
                @include('components.user-dropdown-unified')
            @else
                <a href="{{ route('login') }}" class="bg-earth-green text-white px-5 py-2 rounded-full shadow-md font-medium">{{ __('navigation.login') }}</a>
                <a href="{{ request()->routeIs('terms') ? '#terms-acceptance' : route('terms') . '#terms-acceptance' }}" class="bg-ocean-blue text-white px-5 py-2 rounded-full shadow-md font-medium">{{ __('navigation.register') }}</a>
            @endif
        </div>

        <div class="site-header-mobile-actions {{ !$isWelcomeHeader && $isAuth ? 'is-authenticated' : '' }} 2xl:hidden flex items-center gap-2 flex-shrink-0">
            @if(!$isWelcomeHeader && $isAuth)
                @include('components.user-dropdown-unified')
            @endif
            <button type="button" @click="headerMenuOpen = !headerMenuOpen" :class="{ 'is-open': headerMenuOpen }"
                    class="site-header-hamburger flex flex-col justify-between w-[30px] h-[19px] focus:outline-none"
                    aria-controls="site-header-mobile-menu" :aria-expanded="headerMenuOpen" aria-label="{{ __('navigation.open_menu') }}">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>

    <div id="site-header-mobile-menu" x-show="headerMenuOpen" x-cloak @click.outside="headerMenuOpen = false"
         x-transition class="site-header-menu-panel absolute 2xl:hidden bg-pure-white shadow-xl border-t border-gray-200 px-4 py-4">
        <nav class="container mx-auto flex flex-col items-center gap-2 text-gentle-black">
            @foreach($navLinks as $link)
                <a href="{{ $link['url'] }}" @click="headerMenuOpen = false" class="flex w-full items-center justify-center gap-2 rounded-lg py-2.5 font-medium hover:bg-light-gray hover:text-earth-green transition">
                    <i class="fas {{ $link['icon'] }} text-earth-green" aria-hidden="true"></i><span>{{ $link['label'] }}</span>
                </a>
            @endforeach
            @if(!$isWelcomeHeader && $isAuth)
                <a href="{{ route('support.kb.index') }}" class="flex w-full items-center justify-center gap-2 rounded-lg py-2.5 hover:bg-light-gray"><i class="fas fa-circle-question text-ocean-blue"></i><span>پایگاه دانش</span></a>
            @endif
            <div class="w-full border-t border-gray-200 mt-1 pt-3 flex flex-col items-center gap-3">
                <div class="theme-toggle" onclick="toggleTheme()" role="button" tabindex="0" aria-label="{{ __('navigation.theme_toggle') }}">
                    <span class="theme-toggle-icon sun">☀️</span><span class="theme-toggle-icon moon">🌙</span><div class="theme-toggle-slider"></div>
                </div>
                @if($isWelcomeHeader)
                    <button type="button" onclick="openModal()" class="w-full max-w-sm bg-earth-green text-white px-5 py-2.5 rounded-full shadow-md font-medium">{{ __('langWelcome.btn_join') }}</button>
                    <a href="{{ route('invite') }}" class="w-full max-w-sm bg-digital-gold text-white px-5 py-2.5 rounded-full shadow-md text-center font-medium">{{ __('langWelcome.btn_invite') }}</a>
                    <a href="{{ route('login') }}" class="w-full max-w-sm bg-ocean-blue text-white px-5 py-2.5 rounded-full shadow-md text-center font-medium">{{ __('langWelcome.btn_login') }}</a>
                @elseif(!$isAuth)
                    <a href="{{ route('login') }}" class="w-full max-w-sm bg-earth-green text-white px-5 py-2.5 rounded-full text-center font-medium">{{ __('navigation.login') }}</a>
                    <a href="{{ request()->routeIs('terms') ? '#terms-acceptance' : route('terms') . '#terms-acceptance' }}" class="w-full max-w-sm bg-ocean-blue text-white px-5 py-2.5 rounded-full text-center font-medium">{{ __('navigation.register') }}</a>
                @endif
                <div class="flex flex-wrap justify-center gap-2">
                    @foreach($locales as $code => $meta)
                        <a href="{{ route('locale.change', $code) }}" class="px-3 py-1 rounded-full text-sm {{ $currentLocale === $code ? 'bg-earth-green text-white' : 'bg-light-gray text-gentle-black' }}">{{ $meta['abbr'] }} <span class="mr-1">{{ $meta['label'] }}</span></a>
                    @endforeach
                </div>
            </div>
        </nav>
    </div>
</header>
