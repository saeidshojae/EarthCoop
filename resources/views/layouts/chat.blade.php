<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ get_direction() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="format-detection" content="telephone=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'New Earth Coop')</title>

    <script defer src="{{ asset("vendor/alpinejs/cdn.min.js") }}"></script>
    <link rel="stylesheet" href="{{ asset("vendor/fontawesome/css/all.min.css") }}">

    <link rel="stylesheet" href="{{ asset('Css/fonts-local.css') }}">
    <link rel="stylesheet" href="{{ asset('Css/dark-mode.css') }}">
    <link rel="stylesheet" href="{{ asset('Css/dark-mode-enhanced.css') }}">
    <script src="{{ asset('js/dark-mode.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('Css/dark-mode-fix.css') }}">
    <link rel="stylesheet" href="{{ asset('Css/user-dropdown-responsive.css') }}">
    <link rel="stylesheet" href="{{ asset('Css/unified-styles.css') }}">

    @stack('styles')
    @yield('head-tag')

    {{-- The group page publishes GroupChatConfig/groupId in head-tag. Load the
         module only afterwards so its bootstrap cannot race that context. --}}
    @vite(['resources/js/app.js'])

    <style>
        /* =============================================================== */
        /* ۱. ریست کامل html و body (با اولویت بالا)                     */
        /* =============================================================== */
        html,
        body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            overflow-x: hidden !important;
            background: transparent !important;
        }

        /* =============================================================== */
        /* ۲. بدنه صفحات چت                                                */
        /* =============================================================== */
        body.chat-layout {
            margin: 0 !important;
            padding: 0 !important;
            min-height: 100dvh !important;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8f5e9 100%) !important;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch !important;
            display: flex !important;
            flex-direction: column !important;
            font-family: var(--font-vazirmatn), system-ui, sans-serif !important;
            line-height: 1.5 !important;
            /* padding-top برای جبران ارتفاع هدر (اختیاری) */
            padding-top: 0 !important;
        }

        /* =============================================================== */
        /* ۳. هدر با position: fixed (کاملاً چسبیده به بالا)              */
        /* =============================================================== */
        .chat-mini-header {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            z-index: 50 !important;
            margin: 0 !important;
            padding: 0 !important;
            background-color: var(--color-pure-white) !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1),
                        opacity 0.4s ease,
                        box-shadow 0.3s ease !important;
            transform: translateY(0) !important;
            opacity: 1 !important;
            will-change: transform, opacity !important;
            width: 100% !important;
            max-width: 100% !important;
            border: none !important;
            outline: none !important;
            /* padding داخلی را روی container می‌دهیم */
            padding: 0 !important;
        }

        .chat-mini-header .container {
            margin: 0 auto !important;
            padding: 0.75rem 1rem !important; /* معادل py-3 px-4 */
            width: 100% !important;
            max-width: 1280px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 1rem !important;
        }

        @media (max-width: 640px) {
            .chat-mini-header .container {
                padding: 0.75rem 0.75rem !important;
            }
        }

        .chat-mini-header.header-hidden {
            transform: translateY(-100%) !important;
            opacity: 0 !important;
            box-shadow: none !important;
            pointer-events: none !important;
        }

        /* =============================================================== */
        /* ۴. عنصر رصدکننده (با ارتفاع صفر)                               */
        /* =============================================================== */
        #header-observer {
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            position: relative !important;
            z-index: -1 !important;
            pointer-events: none !important;
            background: transparent !important;
            border: none !important;
            flex-shrink: 0 !important;
            display: block !important;
        }

        /* =============================================================== */
        /* ۵. محتوای اصلی - با padding-top برای جبران ارتفاع هدر          */
        /* =============================================================== */
        .chat-content-wrapper {
            margin: 0 !important;
            padding: 0 !important;
            padding-top: 72px !important; /* ارتفاع تقریبی هدر (با padding) */
            flex: 1 0 auto !important;
            width: 100% !important;
            max-width: 100% !important;
            display: block !important;
        }

        /* در موبایل، padding-top را کاهش می‌دهیم (اختیاری) */
        @media (max-width: 768px) {
            .chat-content-wrapper {
                padding-top: 64px !important; /* ارتفاع کمتر در موبایل */
            }
        }

        .chat-content-wrapper > * {
            margin: 0 !important;
            padding: 0 !important;
        }

        /* =============================================================== */
        /* ۶. استایل‌های منو و dropdown (بدون تغییر)                     */
        /* =============================================================== */
        .chat-menu-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9998;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .chat-menu-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .chat-menu-sidebar {
            position: fixed;
            top: 0;
            right: 0;
            width: 320px;
            max-width: 85vw;
            height: 100vh;
            background: var(--color-pure-white);
            box-shadow: -4px 0 20px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            overflow-y: auto;
            direction: rtl;
        }
        .chat-menu-sidebar.active {
            transform: translateX(0);
        }
        @media (max-width: 768px) {
            .chat-menu-sidebar { width: 280px; }
        }

        body.chat-layout .relative > div.chat-user-dropdown,
        body.chat-layout .relative[x-data*="userDropdownOpen"] > div[x-show],
        body.chat-layout .chat-user-dropdown,
        .chat-layout .relative .chat-user-dropdown,
        .chat-layout .chat-user-dropdown {
            right: 0 !important;
            left: auto !important;
            transform-origin: top right !important;
        }
        @media (max-width: 768px) {
            body.chat-layout .relative > div.chat-user-dropdown,
            body.chat-layout .relative[x-data*="userDropdownOpen"] > div[x-show],
            body.chat-layout .chat-user-dropdown,
            .chat-layout .relative .chat-user-dropdown,
            .chat-layout .chat-user-dropdown {
                max-width: calc(100vw - 1rem) !important;
                width: 18rem !important;
                min-width: 16rem !important;
                right: 0.5rem !important;
                left: auto !important;
            }
        }
        body.chat-layout .user-dropdown-btn,
        body.chat-layout .relative button.user-dropdown-btn {
            background-color: var(--color-earth-green) !important;
            color: var(--color-pure-white) !important;
        }
        body.chat-layout .user-dropdown-btn:hover,
        body.chat-layout .relative button.user-dropdown-btn:hover {
            background-color: var(--color-dark-green) !important;
        }
        body.chat-layout .user-dropdown-btn:active,
        body.chat-layout .user-dropdown-btn:focus,
        body.chat-layout .relative button.user-dropdown-btn:active,
        body.chat-layout .relative button.user-dropdown-btn:focus {
            background-color: var(--color-earth-green) !important;
            outline: none !important;
        }
        body.chat-layout .user-dropdown-btn[style*="background-color"] {
            background-color: var(--color-earth-green) !important;
        }
        @media (min-width: 768px) {
            body.chat-layout .relative > div.chat-user-dropdown,
            body.chat-layout .relative[x-data*="userDropdownOpen"] > div[x-show],
            body.chat-layout .chat-user-dropdown,
            .chat-layout .relative .chat-user-dropdown,
            .chat-layout .chat-user-dropdown {
                right: 0 !important;
                left: auto !important;
            }
        }

        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="font-vazirmatn leading-relaxed flex flex-col chat-layout"
      x-data="{ mobileMenuOpen: false, chatMenuOpen: false }">

    <!-- هدر با position: fixed -->
    <header class="chat-mini-header" id="chat-header">
        <div class="container">
            <div class="flex items-center gap-3 flex-shrink-0">
                <a href="{{ url()->previous() == url()->current() ? route('home') : url()->previous() }}"
                   class="flex items-center justify-center w-10 h-10 rounded-full hover:bg-gray-100 transition-colors text-gray-600 hover:text-green-600"
                   title="بازگشت">
                    <i class="fa fa-arrow-right text-xl"></i>
                </a>
                <a href="{{ route('home') }}" class="flex items-center gap-2 hover:opacity-80 transition-opacity">
                    <svg width="32" height="32" class="brand-logo-animated" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2Z" fill="#10b981" opacity="0.8"/>
                        <path d="M12 2C10.5 4 8 6 8 9C8 12 12 14 12 14C12 14 16 12 16 9C16 6 13.5 4 12 2ZM12 14C12 14 10 16 10 18C10 20 12 22 12 22" fill="#047857"/>
                    </svg>
                    <span class="text-lg font-extrabold text-gentle-black hidden sm:inline" style="color: var(--color-gentle-black);">
                        EarthCoop
                    </span>
                </a>
            </div>
            <div class="flex items-center gap-3 flex-shrink-0">
                <button @click="chatMenuOpen = !chatMenuOpen"
                        class="flex items-center justify-center w-10 h-10 rounded-full hover:bg-gray-100 transition-colors text-gray-600 hover:text-green-600"
                        title="منو">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                @auth
                    @include('components.user-dropdown-unified')
                @else
                    <a href="{{ route('login') }}"
                       class="bg-earth-green text-pure-white px-4 py-2 rounded-full shadow-md hover:bg-dark-green transition duration-300 font-medium text-sm">
                        {{ __('navigation.login') }}
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <!-- عنصر رصدکننده (observer) -->
    <div id="header-observer"></div>

    <!-- منوی کناری -->
    <div class="chat-menu-overlay"
         :class="{ 'active': chatMenuOpen }"
         @click="chatMenuOpen = false"
         x-cloak></div>
    <aside class="chat-menu-sidebar"
           :class="{ 'active': chatMenuOpen }"
           x-cloak>
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gentle-black" style="color: var(--color-gentle-black);">منو</h2>
                <button @click="chatMenuOpen = false"
                        class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <nav class="space-y-2">
                <a href="{{ route('home') }}"
                   @click="chatMenuOpen = false"
                   class="flex items-center gap-3 px-4 py-3 rounded-xl text-gentle-black transition duration-200 hover:bg-light-gray"
                   style="color: var(--color-gentle-black);">
                    <i class="fas fa-home" style="color: var(--color-earth-green);"></i>
                    <span>خانه</span>
                </a>
                @auth
                    <a href="{{ route('blog.index') }}"
                       @click="chatMenuOpen = false"
                       class="flex items-center gap-3 px-4 py-3 rounded-xl text-gentle-black transition duration-200 hover:bg-light-gray"
                       style="color: var(--color-gentle-black);">
                        <i class="fas fa-blog" style="color: var(--color-ocean-blue);"></i>
                        <span>{{ __('navigation.blog') }}</span>
                    </a>
                    <a href="{{ route('stock.book') }}"
                       @click="chatMenuOpen = false"
                       class="flex items-center gap-3 px-4 py-3 rounded-xl text-gentle-black transition duration-200 hover:bg-light-gray"
                       style="color: var(--color-gentle-black);">
                        <i class="fas fa-chart-line" style="color: var(--color-earth-green);"></i>
                        <span>{{ __('navigation.stock_office') }}</span>
                    </a>
                    <a href="{{ route('groups.index') }}"
                       @click="chatMenuOpen = false"
                       class="flex items-center gap-3 px-4 py-3 rounded-xl text-gentle-black transition duration-200 hover:bg-light-gray"
                       style="color: var(--color-gentle-black);">
                        <i class="fas fa-users" style="color: var(--color-ocean-blue);"></i>
                        <span>{{ __('navigation.footer_my_groups') }}</span>
                    </a>
                    <a href="{{ route('notifications.index') }}"
                       @click="chatMenuOpen = false"
                       class="flex items-center gap-3 px-4 py-3 rounded-xl text-gentle-black transition duration-200 hover:bg-light-gray relative"
                       style="color: var(--color-gentle-black);">
                        <i class="fas fa-bell" style="color: var(--color-ocean-blue);"></i>
                        <span>اعلان‌ها</span>
                        @if(auth()->user()->unreadNotifications->count() > 0)
                            <span class="absolute right-2 top-2 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center">
                                {{ auth()->user()->unreadNotifications->count() }}
                            </span>
                        @endif
                    </a>
                    <a href="{{ route('profile.edit') }}"
                       @click="chatMenuOpen = false"
                       class="flex items-center gap-3 px-4 py-3 rounded-xl text-gentle-black transition duration-200 hover:bg-light-gray"
                       style="color: var(--color-gentle-black);">
                        <i class="fas fa-cog" style="color: var(--color-ocean-blue);"></i>
                        <span>ویرایش حساب کاربری</span>
                    </a>
                    @if (auth()->user()->is_admin == 1)
                        <a href="{{ route('admin.dashboard') }}"
                           @click="chatMenuOpen = false"
                           class="flex items-center gap-3 px-4 py-3 rounded-xl text-gentle-black transition duration-200 hover:bg-light-gray"
                           style="color: var(--color-gentle-black);">
                            <i class="fas fa-user-shield" style="color: #9333ea;"></i>
                            <span>{{ __('navigation.admin_dashboard') }}</span>
                        </a>
                    @endif
                    <hr class="my-4 border-gray-200">
                    <a href="#"
                       onclick="event.preventDefault(); document.getElementById('logout-form-chat').submit();"
                       @click="chatMenuOpen = false"
                       class="flex items-center gap-3 px-4 py-3 rounded-xl text-red-600 transition duration-200 hover:bg-red-50">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>{{ __('navigation.logout') }}</span>
                    </a>
                    <form id="logout-form-chat" action="{{ route('logout') }}" method="POST" class="hidden">
                        @csrf
                    </form>
                @else
                    <a href="{{ route('login') }}"
                       @click="chatMenuOpen = false"
                       class="flex items-center gap-3 px-4 py-3 rounded-xl text-gentle-black transition duration-200 hover:bg-light-gray"
                       style="color: var(--color-gentle-black);">
                        <i class="fas fa-sign-in-alt" style="color: var(--color-earth-green);"></i>
                        <span>{{ __('navigation.login') }}</span>
                    </a>
                @endauth
            </nav>
        </div>
    </aside>

    <!-- پیام‌های فلش -->
    @if(session('success'))
        <div class="container mx-auto mt-3 px-4 group-chat-flash" data-group-chat-flash>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        </div>
    @endif
    @if(session('error'))
        <div class="container mx-auto mt-3 px-4">
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        </div>
    @endif
    @if(session('warning'))
        <div class="container mx-auto mt-3 px-4">
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('warning') }}</span>
            </div>
        </div>
    @endif
    @if(session('info'))
        <div class="container mx-auto mt-3 px-4">
            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('info') }}</span>
            </div>
        </div>
    @endif

    <!-- محتوای اصلی -->
    <div class="chat-content-wrapper flex-grow">
        <main>
            @yield('content')
        </main>
    </div>

    <!-- اسکریپت‌ها -->
    @stack('scripts')
    @yield('scripts')
    @if(config('najm-hoda.widget.enabled', true))
        @include('components.najm-hoda-widget')
    @endif

    <script>
        // =============================================================
        // توابع اعلان (Alert)
        // =============================================================
        function showAlert(message, type = 'info') {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    text: message,
                    icon: type,
                    confirmButtonText: 'باشه',
                    customClass: { confirmButton: 'btn btn-primary' }
                });
            } else {
                alert(message);
            }
        }

        function showSuccessAlert(message) { showAlert(message, 'success'); }
        function showErrorAlert(message)   { showAlert(message, 'error');   }
        function showWarningAlert(message) { showAlert(message, 'warning'); }
        function showInfoAlert(message)    { showAlert(message, 'info');    }

        // =============================================================
        // بستن منو با Escape
        // =============================================================
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const sidebar = document.querySelector('.chat-menu-sidebar');
                const overlay = document.querySelector('.chat-menu-overlay');
                if (sidebar && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                }
            }
        });

        // =============================================================
        // کنترل هدر محوشونده با Intersection Observer
        // =============================================================
        document.addEventListener('DOMContentLoaded', function() {
            const header = document.getElementById('chat-header');
            const observerElement = document.getElementById('header-observer');

            if (!header || !observerElement) {
                console.warn('⚠️ هدر یا عنصر observer یافت نشد!');
                return;
            }

            if ('IntersectionObserver' in window) {
                const intersectionObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            header.classList.remove('header-hidden');
                        } else {
                            header.classList.add('header-hidden');
                        }
                    });
                }, {
                    rootMargin: '0px 0px 0px 0px',
                    threshold: 0.1
                });

                intersectionObserver.observe(observerElement);

                if (window.scrollY === 0) {
                    header.classList.remove('header-hidden');
                }
            } else {
                // Fallback برای مرورگرهای قدیمی
                let lastScrollTop = 0;
                window.addEventListener('scroll', function() {
                    const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
                    if (currentScroll <= 0) {
                        header.classList.remove('header-hidden');
                    } else if (currentScroll > 50) {
                        header.classList.add('header-hidden');
                    } else {
                        header.classList.remove('header-hidden');
                    }
                    lastScrollTop = currentScroll;
                }, { passive: true });
            }

            window.addEventListener('pageshow', function(event) {
                if (event.persisted) {
                    const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
                    if (currentScroll <= 0) {
                        header.classList.remove('header-hidden');
                    }
                }
            });

            document.addEventListener('mouseenter', function(e) {
                if (e.clientY < 50 && window.innerWidth > 1024) {
                    header.classList.remove('header-hidden');
                }
            });

            console.log('✅ کنترل هدر محوشونده با موفقیت راه‌اندازی شد.');
        });
    </script>
</body>
</html>
