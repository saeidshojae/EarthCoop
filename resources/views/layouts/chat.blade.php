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

    <script defer src="{{ asset('vendor/alpinejs/cdn.min.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Css/fonts-local.css') }}">
    <link rel="stylesheet" href="{{ asset('Css/dark-mode.css') }}">
    <link rel="stylesheet" href="{{ asset('Css/dark-mode-enhanced.css') }}">
    <script src="{{ asset('js/dark-mode.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('Css/dark-mode-fix.css') }}">
    <link rel="stylesheet" href="{{ asset('Css/user-dropdown-responsive.css') }}">
    <link rel="stylesheet" href="{{ asset('Css/unified-styles.css') }}">
    <link rel="stylesheet" href="{{ asset('Css/header-mobile-polish.css') }}">

    @stack('styles')
    @yield('head-tag')

    {{-- GroupChatConfig is published by the page head section. Keep Vite after it
         so the Group Chat page entry cannot race its server-rendered context. --}}
    @vite(['resources/js/app.js'])

    <style>
        [x-cloak] { display: none !important; }

        html,
        body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            overflow-x: clip !important;
            overflow-y: visible !important;
        }

        body.chat-layout {
            margin: 0 !important;
            padding: 0 !important;
            min-height: 100dvh !important;
            display: flex !important;
            flex-direction: column !important;
            -webkit-overflow-scrolling: touch !important;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8f5e9 100%) !important;
            font-family: var(--font-vazirmatn), system-ui, sans-serif !important;
            line-height: 1.5 !important;
            --chat-site-header-height: 60px;
            --chat-site-header-offset: 0px;
        }

        body.dark-mode.chat-layout {
            background: var(--bg-gradient-dark) !important;
        }

        /* Chat is an app-like surface: the shared EarthCoop header exists, but
           starts outside the viewport and reserves no space until explicitly
           revealed by the chat-only gesture controller. */
        body.chat-layout header.site-header-unified[data-header-context="chat"] {
            transform: translateY(-100%) !important;
            opacity: 0;
            pointer-events: none;
            transition: transform .25s cubic-bezier(.4, 0, .2, 1), opacity .2s ease !important;
            will-change: transform, opacity;
        }

        body.chat-layout header.site-header-unified[data-header-context="chat"].chat-site-header-visible {
            transform: translateY(0) !important;
            opacity: 1;
            pointer-events: auto;
        }

        body.chat-layout > .site-header-spacer {
            height: 0 !important;
            min-height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .chat-content-wrapper {
            margin: 0 !important;
            padding: 0 !important;
            padding-top: 0 !important;
            flex: 1 0 auto !important;
            width: 100% !important;
            max-width: 100% !important;
            display: block !important;
        }

        .chat-content-wrapper > * {
            margin-top: 0 !important;
        }

        @media (prefers-reduced-motion: reduce) {
            body.chat-layout header.site-header-unified[data-header-context="chat"] {
                transition: none !important;
            }
        }
    </style>
</head>

<body class="font-vazirmatn leading-relaxed flex flex-col chat-layout">
    @include('components.pwa-splash')
    @include('components.header-unified', ['headerContext' => 'chat'])

    @if(session('success'))
        <div class="container mx-auto mt-3 px-4 group-chat-flash" data-group-chat-flash>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        </div>
    @endif
    @if(session('error'))
        <div class="container mx-auto mt-3 px-4 group-chat-flash" data-group-chat-flash>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        </div>
    @endif
    @if(session('warning'))
        <div class="container mx-auto mt-3 px-4 group-chat-flash" data-group-chat-flash>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('warning') }}</span>
            </div>
        </div>
    @endif
    @if(session('info'))
        <div class="container mx-auto mt-3 px-4 group-chat-flash" data-group-chat-flash>
            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('info') }}</span>
            </div>
        </div>
    @endif

    <div class="chat-content-wrapper flex-grow">
        <main>
            @yield('content')
        </main>
    </div>

    @stack('scripts')
    @yield('scripts')

    @if(config('najm-hoda.widget.enabled', true))
        @include('components.najm-hoda-widget')
    @endif

    <script>
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
        function showErrorAlert(message) { showAlert(message, 'error'); }
        function showWarningAlert(message) { showAlert(message, 'warning'); }
        function showInfoAlert(message) { showAlert(message, 'info'); }
    </script>
</body>
</html>
