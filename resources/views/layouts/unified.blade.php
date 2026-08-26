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

    @vite(['resources/js/app.js'])
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

    <style>
        [x-cloak] { display: none !important; }

        html, body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            overflow-x: hidden !important;
        }

        body {
            min-height: 100dvh !important;
            background: var(--bg-gradient-light);
            transition: background-color 0.3s ease;
            margin: 0 !important;
            padding: 0 !important;
        }

        body.dark-mode { background: var(--bg-gradient-dark); }

        header.site-header-unified {
            margin: 0 !important;
            border: none !important;
            border-radius: 0 !important;
            top: 0;
        }

        header.site-header-unified .container {
            margin: 0 auto !important;
            max-width: 1320px !important;
            width: 100% !important;
        }

        .container {
            width: 100%;
            margin-left: auto;
            margin-right: auto;
            padding-left: 1rem;
            padding-right: 1rem;
        }
    </style>
</head>
<body class="font-vazirmatn leading-relaxed flex flex-col"
      x-data="{ mobileMenuOpen: false, userDropdownOpen: false, sidebarOpen: false }">
    @include('components.pwa-splash')
    @include('components.header-unified', ['headerContext' => 'default'])

    @if(session('success'))
        <div class="container mx-auto mt-3 px-4"><div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert"><span class="block sm:inline">{{ session('success') }}</span></div></div>
    @endif
    @if(session('error'))
        <div class="container mx-auto mt-3 px-4"><div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert"><span class="block sm:inline">{{ session('error') }}</span></div></div>
    @endif
    @if(session('warning'))
        <div class="container mx-auto mt-3 px-4"><div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative" role="alert"><span class="block sm:inline">{{ session('warning') }}</span></div></div>
    @endif
    @if(session('info'))
        <div class="container mx-auto mt-3 px-4"><div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative" role="alert"><span class="block sm:inline">{{ session('info') }}</span></div></div>
    @endif

    <main class="flex-grow">@yield('content')</main>

    @include('components.footer-unified', ['footerContext' => 'default'])

    @stack('scripts')
    @yield('scripts')

    @if(config('najm-hoda.widget.enabled', true))
        @include('components.najm-hoda-widget')
    @endif

    <script>
        function showAlert(message, type = 'info') {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ text: message, icon: type, confirmButtonText: 'باشه', customClass: { confirmButton: 'btn btn-primary' } });
            } else {
                alert(message);
            }
        }
        function showSuccessAlert(message) { showAlert(message, 'success'); }
        function showErrorAlert(message) { showAlert(message, 'error'); }
        function showWarningAlert(message) { showAlert(message, 'warning'); }
        function showInfoAlert(message) { showAlert(message, 'info'); }
    </script>
</body>
</html>
