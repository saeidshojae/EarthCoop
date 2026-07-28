<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ get_direction() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'پنل مدیریت - ' . config('app.name', 'EarthCoop'))</title>
    <!-- Vite CSS & JS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <!-- Alpine.js -->
    <script defer src="{{ asset("vendor/alpinejs/cdn.min.js") }}"></script>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="{{ asset("vendor/fontawesome/css/all.min.css") }}">
    <!-- Fonts - Same as Unified Layout -->
    <link rel="stylesheet" href="{{ asset('Css/fonts-local.css') }}">
    <!-- Dark Mode -->
    <link rel="stylesheet" href="{{ asset('Css/dark-mode.css') }}">
    <script src="{{ asset('js/dark-mode.js') }}"></script>
    <!-- Dark Mode Fix - پشتیبانی کامل از کلاس dark-mode -->
    <link rel="stylesheet" href="{{ asset('Css/dark-mode-fix.css') }}">
    <!-- Unified Styles -->
    <link rel="stylesheet" href="{{ asset('Css/unified-styles.css') }}">
    <!-- Admin Specific Styles -->
    <link rel="stylesheet" href="{{ asset('Css/admin-styles.css') }}">
    <!-- Najm Hoda Dark Mode & Responsive Styles -->
    <link rel="stylesheet" href="{{ asset('Css/najm-hoda-dark-mode.css') }}">
    <!-- SweetAlert2 -->
    <script src="{{ asset("vendor/sweetalert2/sweetalert2.all.min.js") }}"></script>
    <!-- Page Specific Styles -->
    @stack('styles')
    @yield('head-tag')
    <style>
        /* Admin Layout Styles */
        :root {
            --admin-sidebar-width: 280px;
            --admin-header-height: 70px;
        }
        body {
            font-family: 'Vazirmatn', 'Poppins', sans-serif;
            direction: rtl;
        }
        .admin-sidebar {
            width: var(--admin-sidebar-width);
            height: 100vh;
            position: fixed;
            right: 0;
            top: 0;
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        .admin-sidebar::-webkit-scrollbar {
            width: 6px;
        }
        .admin-sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }
        .admin-sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }
        .admin-main-content {
            margin-right: var(--admin-sidebar-width);
            min-height: 100vh;
            background: #f8fafc;
            transition: margin-right 0.3s ease;
        }
        @media (prefers-color-scheme: dark) {
            .admin-main-content {
                background: #0f172a;
            }
        }
        .admin-header {
            height: var(--admin-header-height);
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1rem;
            position: sticky;
            top: 0;
            z-index: 100;
            gap: 1rem;
        }
        @media (min-width: 768px) {
            .admin-header {
                padding: 0 2rem;
            }
        }
        @media (prefers-color-scheme: dark) {
            .admin-header {
                background: #1e293b;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
                border-bottom: 1px solid #334155;
            }
        }
        .admin-content-wrapper {
            padding: 1rem;
        }
        @media (min-width: 768px) {
            .admin-content-wrapper {
                padding: 2rem;
            }
        }
        @media (max-width: 768px) {
            .admin-sidebar {
                width: 100%;
                max-width: 300px;
                box-shadow: -2px 0 10px rgba(0, 0, 0, 0.3);
                transform: translateX(100%);
            }
            .admin-sidebar[x-show="sidebarOpen"] {
                transform: translateX(0);
            }
            .admin-main-content {
                margin-right: 0;
            }
        }
        /* Overlay for mobile sidebar */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            cursor: pointer;
        }
        /* Alpine.js cloaking */
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>
<body class="font-vazirmatn"
      x-data="{
          sidebarOpen: false,
          userDropdownOpen: false,
          isDesktop: false
      }"
      x-init="
          // Check if desktop on load
          isDesktop = window.innerWidth > 768;
          sidebarOpen = isDesktop;
          // Handle window resize
          window.addEventListener('resize', function() {
              isDesktop = window.innerWidth > 768;
              if (isDesktop) {
                  sidebarOpen = true;
              }
          });
      ">
    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay"
         x-show="sidebarOpen && !isDesktop"
         @click="sidebarOpen = false"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-cloak
         style="display: none;"></div>
    <!-- Admin Sidebar -->
    @include('admin.partials.sidebar')
    <!-- Admin Main Content -->
    <div class="admin-main-content">
        <!-- Admin Header -->
        @include('admin.partials.header')
        <!-- Flash Messages -->
        @if(session('success'))
            <div class="mx-4 mt-4">
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            </div>
        @endif
        @if(session('error'))
            <div class="mx-4 mt-4">
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            </div>
        @endif
        @if(session('warning'))
            <div class="mx-4 mt-4">
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded-lg relative" role="alert">
                    <span class="block sm:inline">{{ session('warning') }}</span>
                </div>
            </div>
        @endif
        @if(session('info'))
            <div class="mx-4 mt-4">
                <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded-lg relative" role="alert">
                    <span class="block sm:inline">{{ session('info') }}</span>
                </div>
            </div>
        @endif
        <!-- Main Content -->
        <div class="admin-content-wrapper">
            @yield('content')
        </div>
    </div>
    <!-- Scripts -->
    @stack('scripts')
    @yield('scripts')
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