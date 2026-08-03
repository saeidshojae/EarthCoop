<!DOCTYPE html>
<!-- ========================================================== -->
<!-- لایوت عمومی سایت (قبل از بازطراحی)                         -->
<!-- ========================================================== -->
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ get_direction() }}">
<head>
    <!-- ========================================================== -->
    <!-- متا تگ‌های پایه                                             -->
    <!-- ========================================================== -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- ========================================================== -->
    <!-- PWA و تنظیمات اپلیکیشن                                     -->
    <!-- ========================================================== -->
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#4c7caf">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon.svg') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/icon.svg') }}">

    <!-- ========================================================== -->
    <!-- عنوان صفحه (قابل بازنویسی توسط هر صفحه)                     -->
    <!-- ========================================================== -->
    <title>@yield('title', config('app.name', 'Laravel'))</title>

    <!-- ========================================================== -->
    <!-- فونت‌های محلی (Vazir)                                      -->
    <!-- ========================================================== -->
    <link rel="stylesheet" href="{{ asset('css/fonts-local.css') }}">

    <!-- ========================================================== -->
    <!-- وابستگی‌های اصلی (Vite برای CSS و JS)                     -->
    <!-- ========================================================== -->
    @vite(['resources/js/app.js'])

    <!-- ========================================================== -->
    <!-- Font Awesome (نسخه محلی)                                   -->
    <!-- ========================================================== -->
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">

    <!-- ========================================================== -->
    <!-- سیستم طراحی یکپارچه (Design System)                        -->
    <!-- ========================================================== -->
    <link rel="stylesheet" href="{{ asset('Css/design-system.css') }}">

    <!-- ========================================================== -->
    <!-- حالت تاریک (Dark Mode)                                     -->
    <!-- ========================================================== -->
    <link rel="stylesheet" href="{{ asset('Css/dark-mode.css') }}">
    <script src="{{ asset('js/dark-mode.js') }}"></script>

    <!-- ========================================================== -->
    <!-- SweetAlert2 (محلی)                                         -->
    <!-- ========================================================== -->
    <script src="{{ asset('vendor/sweetalert/sweetalert2.min.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('vendor/sweetalert/sweetalert2.min.css') }}">

    <!-- ========================================================== -->
    <!-- تگ‌های اضافی سربرگ (از هر صفحه)                           -->
    <!-- ========================================================== -->
    @yield('head-tag')

    <!-- ========================================================== -->
    <!-- استایل‌های درون‌خطی (تنظیمات عمومی + رفع فاصله هدر)        -->
    <!-- ========================================================== -->
    <style>
        /* =============================== */
        /* ریست کامل حاشیه‌های پیش‌فرض     */
        /* =============================== */
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            overflow-x: hidden !important;
        }

        /* =============================== */
        /* تنظیم بدنه برای چسبیدن به بالا   */
        /* =============================== */
        body {
            font-family: 'Vazirmatn', 'Poppins', sans-serif;
            background: var(--bg-gradient-light);
            transition: background-color 0.3s ease;
            margin: 0 !important;
            padding: 0 !important;
        }

        body.dark-mode {
            background: var(--bg-gradient-dark);
        }

        /* =============================== */
        /* نوار ناوبری - چسبیده به بالا     */
        /* =============================== */
        .navbar {
            background-color: var(--navbar-light) !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s ease;
            margin: 0 !important; /* حذف فاصله خارجی */
            padding: 0.5rem 0 !important; /* حفظ padding داخلی برای زیبایی */
            border: none !important;
            border-radius: 0 !important;
        }

        body.dark-mode .navbar {
            background-color: var(--navbar-dark) !important;
        }

        /* =============================== */
        /* استایل‌های باقی‌مانده (بدون تغییر) */
        /* =============================== */
        .modal-content {
            direction: rtl;
        }

        .modal-content .btn-close {
            margin: 0;
        }

        .modal-content .btn {
            width: 100%;
        }

        /* رنگ‌های اصلی - استفاده از متغیرهای سیستم طراحی */
        .bg-primary {
            background: var(--color-primary) !important;
        }

        .alert-info {
            border: 1px solid var(--color-primary-light);
            color: var(--color-primary-light);
            background-color: transparent;
        }

        .btn {
            background: var(--color-primary-light);
            color: #ffffff !important;
            border: none;
            width: 100%;
            margin: .2rem;
        }

        .remove-selection {
            padding: 0 .4rem !important;
        }

        .navbar-nav {
            direction: rtl;
            padding: 0;
        }

        .table-dark th {
            background: #46c77a19;
            color: #333;
        }

        #box-widget-icon {
            width: 45px !important;
            height: 45px !important;
        }

        /* تنظیمات واکنش‌گرا برای موبایل */
        @media only screen and (max-width: 990px) {
            .main-section {
                padding: .5rem;
                margin-top: .5rem;
            }
            .col-md-10 {
                padding: 0;
            }
            .col-md-8 {
                padding: 0;
            }
        }

        .swal2-html-container {
            direction: rtl;
        }

        #navbarDropdown {
            text-align: right;
        }

        .dropdown-item {
            text-align: right;
        }

        /* =============================== */
        /* اصلاح container داخل navbar     */
        /* =============================== */
        .navbar .container {
            margin: 0 auto !important;
            padding: 0 1rem !important; /* فاصله افقی برای محتوا */
            width: 100% !important;
            max-width: 1320px !important; /* مطابق با container-fluid در Bootstrap */
        }

        /* در موبایل padding کمتر */
        @media (max-width: 576px) {
            .navbar .container {
                padding: 0 0.75rem !important;
            }
        }
    </style>
</head>

<body class="bg-light font-vazirmatn">
    <!-- ========================================================== -->
    <!-- کانتینر اصلی برنامه (app2)                                  -->
    <!-- ========================================================== -->
    <div id="app2">
        <!-- ========================================================== -->
        <!-- نوار ناوبری (Navbar)                                        -->
        <!-- ========================================================== -->
        <nav class="navbar navbar-expand-md navbar-light shadow-sm">
            <div class="container">
                <!-- لوگو و دکمه بازگشت -->
                <div style="display: flex; flex-direction: column;">
                    <a href="{{ url()->previous() == url()->current() ? route('home') : url()->previous() }}" style="margin-right: .5rem; text-decoration: none">
                        <i class="fa fa-arrow-left" style="color: #fff; font-size: 1rem"></i>
                    </a>
                    <a class="navbar-brand fw-bold" href="{{ url('/') }}">
                        <img src="{{ asset('images/logo.png') }}" style="width: 8rem">
                    </a>
                </div>

                <!-- دکمه منوی همبرگری (موبایل) -->
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                        data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                        aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <!-- محتوای منو -->
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <!-- منوی سمت چپ (خالی) -->
                    <ul class="navbar-nav me-auto">
                        {{-- لینک‌های اضافی در صورت نیاز --}}
                    </ul>

                    <!-- منوی سمت راست (تنظیمات، زبان، احراز هویت، لینک‌ها) -->
                    <ul class="navbar-nav ms-auto">
                        <!-- دکمه تغییر تم (روشن/تاریک) -->
                        <li class="nav-item d-flex align-items-center">
                            <div class="theme-toggle me-3" onclick="toggleTheme()" title="{{ __('navigation.theme_toggle') }}" style="cursor: pointer;">
                                <span class="theme-toggle-icon sun">☀️</span>
                                <span class="theme-toggle-icon moon">🌙</span>
                                <div class="theme-toggle-slider"></div>
                            </div>
                        </li>

                        <!-- انتخابگر زبان -->
                        @php
                            $locales = [
                                'fa' => ['label' => 'فارسی', 'flag' => '🇮🇷'],
                                'en' => ['label' => 'English', 'flag' => '🇬🇧'],
                                'ar' => ['label' => 'العربية', 'flag' => '🇸🇦'],
                            ];
                        @endphp
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-white fw-semibold d-flex align-items-center gap-1" href="#" id="languageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('navigation.language_picker') }}">
                                <span>{{ $locales[app()->getLocale()]['flag'] ?? '🌐' }}</span>
                                <span class="d-none d-lg-inline">{{ $locales[app()->getLocale()]['label'] ?? strtoupper(app()->getLocale()) }}</span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="languageDropdown">
                                @foreach($locales as $code => $meta)
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center @if(app()->getLocale() === $code) active fw-bold @endif" href="{{ route('locale.change', $code) }}">
                                            <span class="me-2">{{ $meta['flag'] }}</span>
                                            <span>{{ $meta['label'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </li>

                        <!-- بخش احراز هویت (Guest / Auth) -->
                        @guest
                            @if (Route::has('login'))
                                <li class="nav-item">
                                    <a class="nav-link text-white fw-semibold" href="{{ route('login') }}">
                                        {{ __('navigation.login') }}
                                    </a>
                                </li>
                            @endif
                            @if (Route::has('register'))
                                <li class="nav-item">
                                    <a class="nav-link text-white fw-semibold" href="{{ route('register') }}">
                                        {{ __('navigation.register') }}
                                    </a>
                                </li>
                            @endif
                        @else
                            <li class="nav-item {{ request()->is('groups/chat/*') ? '' : 'dropdown' }}">
                                <a id="navbarDropdown" class="nav-link dropdown-toggle fw-bold text-white" href="#" role="button"
                                   data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    {{ Auth::user()->fullName() }}
                                </a>
                                <div class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="navbarDropdown">
                                    <!-- پروفایل کاربر -->
                                    <a class="dropdown-item fw-semibold" href="{{ route('profile.show') }}">
                                        {{ __('navigation.profile') }}
                                    </a>
                                    <div class="dropdown-divider"></div>

                                    <!-- بخش دفتر سهام -->
                                    <h6 class="dropdown-header text-primary">{{ __('navigation.stock_office_section') }}</h6>
                                    <a class="dropdown-item fw-semibold" href="{{ route('auction.index') }}">
                                        <i class="fas fa-gavel me-2"></i>{{ __('navigation.auctions') }}
                                    </a>
                                    <a class="dropdown-item fw-semibold" href="{{ route('stock.book') }}">
                                        <i class="fas fa-book me-2"></i>دفتر سهام
                                    </a>
                                    <a class="dropdown-item fw-semibold" href="{{ route('wallet.index') }}">
                                        <i class="fas fa-wallet me-2"></i>{{ __('navigation.wallet') }}
                                    </a>
                                    <a class="dropdown-item fw-semibold" href="{{ route('holding.index') }}">
                                        <i class="fas fa-chart-line me-2"></i>{{ __('navigation.holdings') }}
                                    </a>
                                    <div class="dropdown-divider"></div>

                                    <!-- اسناد و توافقات -->
                                    <a class="dropdown-item fw-semibold" href="{{ route('terms') }}">
                                        {{ __('navigation.charter') }}
                                    </a>
                                    <a class="dropdown-item fw-semibold" href="{{ route('najm-bahar.agreement') }}">
                                        {{ __('navigation.financial_agreement') }}
                                    </a>

                                    <!-- بخش مدیریت (فقط ادمین) -->
                                    @if (auth()->check() && auth()->user()->is_admin == 1)
                                        <div class="dropdown-divider"></div>
                                        <h6 class="dropdown-header text-primary">{{ __('navigation.admin_section') }}</h6>
                                        <a class="dropdown-item fw-semibold" href="{{ route('admin.dashboard') }}">
                                            <i class="bi bi-house-door me-2"></i>{{ __('navigation.admin_dashboard') }}
                                        </a>
                                        <a class="dropdown-item fw-semibold" href="{{ route('admin.blog.dashboard') }}">
                                            <i class="fas fa-blog me-2"></i>{{ __('navigation.admin_blog') }}
                                        </a>
                                    @endif

                                    <!-- خروج -->
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger fw-semibold" href="#"
                                       onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                        {{ __('navigation.logout') }}
                                    </a>
                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </div>
                            </li>
                        @endguest

                        <!-- لینک‌های عمومی (همیشه نمایش داده می‌شوند) -->
                        @if(auth()->check())
                            <li class="nav-item"><a class="nav-link text-white fw-semibold" href="{{ route('blog.index') }}">
                                <i class="fas fa-blog me-1"></i>{{ __('navigation.blog') }}
                            </a></li>
                            <li class="nav-item"><a class="nav-link text-white fw-semibold" href="{{ route('auction.index') }}">
                                <i class="fas fa-gavel me-1"></i>{{ __('navigation.auctions') }}
                            </a></li>
                            <li class="nav-item"><a class="nav-link text-white fw-semibold" href="{{ route('stock.book') }}">
                                <i class="fas fa-book me-1"></i>دفتر سهام
                            </a></li>
                        @else
                            <li class="nav-item"><a class="nav-link text-white fw-semibold" href="{{ route('blog.index') }}">
                                <i class="fas fa-blog me-1"></i>{{ __('navigation.blog') }}
                            </a></li>
                        @endif

                        <!-- صفحات استاتیک (از مدل Page) -->
                        @foreach(\App\Models\Page::where('is_published', 1)->get() as $page)
                            <li class="nav-item"><a class="nav-link text-white fw-semibold" href="{{ url('/pages/' . $page->slug) }}">{{ $page->title }}</a></li>
                        @endforeach

                        <!-- پنل مدیریت (فقط ادمین) -->
                        @if (auth()->check() && auth()->user()->is_admin == 1)
                            <li class="nav-item"><a class="nav-link text-white fw-semibold" href="{{ route('admin.dashboard') }}">
                                <i class="bi bi-house-door"></i>{{ __('navigation.admin_portal') }}</a>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>
        </nav>

        <!-- ========================================================== -->
        <!-- محتوای اصلی (هر صفحه)                                      -->
        <!-- ========================================================== -->
        <main>
            <div class="container main-section">
                @yield('content')
            </div>
        </main>
    </div>

    <!-- ========================================================== -->
    <!-- اسکریپت‌های اختصاصی هر صفحه                                 -->
    <!-- ========================================================== -->
    @yield('scripts')
    @stack('scripts')

    <!-- ========================================================== -->
    <!-- پاک کردن localStorage در صورت نیاز (از سشن)                -->
    <!-- ========================================================== -->
    @if(session()->has('clearLocalStorage'))
        <script>
            localStorage.clear(); // پاک کردن localStorage
        </script>
    @endif

    <!-- ========================================================== -->
    <!-- ویجت چت گوفتینو (غیرفعال موقت)                             -->
    <!-- ========================================================== -->
    <!-- Goftino Chat Widget - Disabled for offline mode -->
    <!-- Re-enable when internet is available by uncommenting below -->
    <!-- <script type="text/javascript">
        !function(){var i="yT1vfw",a=window,d=document;function g(){var g=d.createElement("script"),s="https://www.goftino.com/widget/"+i,l=localStorage.getItem("goftino_"+i);g.async=!0,g.src=l?s+"?o="+l:s;d.getElementsByTagName("head")[0].appendChild(g);};"complete"===d.readyState?g():a.attachEvent?a.attachEvent("onload",g):a.addEventListener("load",g,!1);}();
    </script> -->

    <!-- ========================================================== -->
    <!-- اسکریپت‌های عمومی (alert, تنظیمات ویجت)                    -->
    <!-- ========================================================== -->
    <script>
        // تنظیم ویجت گوفتینو برای موبایل
        if(window.innerWidth<769){
            window.addEventListener('goftino_ready', function () {
                Goftino.setWidget({
                    marginRight: (window.innerWidth - 70),
                    marginBottom: 10
                });
            });
        }

        // توابع کمکی برای نمایش اعلان‌ها (با پشتیبانی از SweetAlert)
        function showAlert(message, type = 'info') {
            if(typeof Swal === 'undefined') {
                alert(message);
                return;
            }
            Swal.fire({
                text: message,
                icon: type,
                confirmButtonText: 'باشه',
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            });
        }

        function showSuccessAlert(message) { showAlert(message, 'success'); }
        function showErrorAlert(message)   { showAlert(message, 'error');   }
        function showWarningAlert(message) { showAlert(message, 'warning'); }
        function showInfoAlert(message)    { showAlert(message, 'info');    }
    </script>

    <!-- ========================================================== -->
    <!-- ویجت نجم‌هدا (دستیار هوشمند)                               -->
    <!-- ========================================================== -->
    @if(config('najm-hoda.widget.enabled', true))
        @include('components.najm-hoda-widget')
    @endif
</body>
</html>