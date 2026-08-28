<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ get_direction() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="format-detection" content="telephone=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#10b981">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon.svg') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/icon.svg') }}">
    <title>@yield('title', 'New Earth Coop')</title>

    <!-- Tailwind & Bootstrap CSS via Vite -->
    @vite(['resources/js/app.js'])

    <!-- Alpine.js -->
    <script defer src="{{ asset("vendor/alpinejs/cdn.min.js") }}"></script>

    <!-- FontAwesome -->
    <link rel="stylesheet" href="{{ asset("vendor/fontawesome/css/all.min.css") }}">

    <!-- Fonts - Same as Home Page -->
    <link rel="stylesheet" href="{{ asset('Css/fonts-local.css') }}">

    <!-- Dark Mode -->
    <link rel="stylesheet" href="{{ asset('Css/dark-mode.css') }}">
    <link rel="stylesheet" href="{{ asset('Css/dark-mode-enhanced.css') }}">
    <script src="{{ asset('js/dark-mode.js') }}"></script>

    <!-- Dark Mode Fix - پشتیبانی کامل از کلاس dark-mode -->
    <link rel="stylesheet" href="{{ asset('Css/dark-mode-fix.css') }}">

    <!-- User Dropdown Responsive -->
    <link rel="stylesheet" href="{{ asset('Css/user-dropdown-responsive.css') }}">

    <!-- Unified Styles - بر اساس طراحی Home -->
    <link rel="stylesheet" href="{{ asset('Css/unified-styles.css') }}">
    <link rel="stylesheet" href="{{ asset('Css/responsive-system.css') }}">
    <link rel="stylesheet" href="{{ asset('Css/header-mobile-polish.css') }}">

    <!-- Page Specific Styles -->
    @stack('styles')
    @yield('head-tag')

    <style>
        /* =============================== */
        /* Alpine.js cloaking              */
        /* =============================== */
        [x-cloak] {
            display: none !important;
        }

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
        /* بدنه با min-height مناسب        */
        /* =============================== */
        body {
            min-height: 100dvh !important;
            background: var(--bg-gradient-light);
            transition: background-color 0.3s ease;
            margin: 0 !important;
            padding: 0 !important;
        }

        body.dark-mode {
            background: var(--bg-gradient-dark);
        }

        /* =============================== */
        /* هدر یکپارچه - چسبیده به بالا   */
        /* =============================== */
        /* Scope header reset to the actual unified site header only. */
        header.site-header-unified {
            margin: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important; /* در صورت لزوم */
            border: none !important;
            border-radius: 0 !important;
            position: relative;
            top: 0;
        }

        /* اگر هدر دارای container است، padding افقی را نگه می‌داریم */
        header.site-header-unified .container {
            margin: 0 auto !important;
            padding: 0.5rem 1rem !important; /* padding مناسب برای محتوا */
            max-width: 1320px !important;
            width: 100% !important;
        }

        /* در موبایل padding کمتر */
        @media (max-width: 576px) {
            header.site-header-unified .container {
                padding: 0.5rem 0.75rem !important;
            }
        }

        /* =============================== */
        /* سایر استایل‌های قبلی           */
        /* =============================== */
        .container {
            width: 100%;
            margin-left: auto;
            margin-right: auto;
            padding-left: 1rem;
            padding-right: 1rem;
        }

        /* در صورت نیاز، استایل‌های دیگر از فایل‌های خارجی override نمی‌شوند */
    </style>
</head>
<body class="font-vazirmatn leading-relaxed flex flex-col"
      x-data="{
          mobileMenuOpen: false,
          userDropdownOpen: false,
          sidebarOpen: false
      }">
    @include('components.pwa-splash')
    <!-- Unified Header - بر اساس طراحی Home -->
    @include('components.header-unified', ['headerContext' => 'default'])

    <!-- Flash Messages -->
    @if(session('success'))
        <div class="container mx-auto mt-3 px-4">
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

    <!-- Main Content -->
    <main class="flex-grow">
        @yield('content')
    </main>

    <!-- Unified Footer -->
    @include('components.footer-unified', ['footerContext' => 'default'])

    <!-- Responsive shared runtime -->
    <script src="{{ asset('js/responsive-system.js') }}" defer></script>

    <!-- Scripts -->
    @stack('scripts')
    @yield('scripts')

    <!-- Najm Hoda Widget -->
    @if(config('najm-hoda.widget.enabled', true))
        @include('components.najm-hoda-widget')
    @endif

    <!-- SweetAlert Helper Functions -->
    <script>
        function showAlert(message, type = 'info') {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    text: message,
                    icon: type,
                    confirmButtonText: 'باشه',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
            } else {
                alert(message);
            }
        }
        function showSuccessAlert(message) {
            showAlert(message, 'success');
        }
        function showErrorAlert(message) {
            showAlert(message, 'error');
        }
        function showWarningAlert(message) {
            showAlert(message, 'warning');
        }
        function showInfoAlert(message) {
            showAlert(message, 'info');
        }
    </script>
</body>
</html>
