<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ get_direction() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'پنل مدیریت - ' . config('app.name', 'EarthCoop'))</title>
    @vite(['resources/js/app.js'])
    <script defer src="{{ asset("vendor/alpinejs/cdn.min.js") }}"></script>
    <link rel="stylesheet" href="{{ asset("vendor/fontawesome/css/all.min.css") }}">
    <link rel="stylesheet" href="{{ asset('Css/fonts-local.css') }}">
    <link rel="stylesheet" href="{{ asset('Css/dark-mode.css') }}">
    <script src="{{ asset('js/dark-mode.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('Css/dark-mode-fix.css') }}">
    <link rel="stylesheet" href="{{ asset('Css/unified-styles.css') }}">
    <link rel="stylesheet" href="{{ asset('Css/admin-styles.css') }}">
    <link rel="stylesheet" href="{{ asset('Css/najm-hoda-dark-mode.css') }}">
    <script src="{{ asset("vendor/sweetalert2/sweetalert2.all.min.js") }}"></script>
    @stack('styles')
    @yield('head-tag')
    <style>
        :root { --admin-sidebar-width: 280px; --admin-header-height: 70px; }
        body { font-family: 'Vazirmatn', 'Poppins', sans-serif; direction: rtl; }
        .admin-sidebar { width: var(--admin-sidebar-width); height: 100vh; position: fixed; right: 0; top: 0; background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%); box-shadow: 2px 0 10px rgba(0,0,0,.1); overflow-y: auto; z-index: 1000; transition: transform .3s ease; }
        .admin-sidebar::-webkit-scrollbar { width: 6px; }
        .admin-sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,.1); }
        .admin-sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.3); border-radius: 3px; }
        .admin-main-content { margin-right: var(--admin-sidebar-width); min-height: 100vh; background: #f8fafc; transition: margin-right .3s ease; }
        @media (prefers-color-scheme: dark) { .admin-main-content { background: #0f172a; } }
        .admin-header { height: var(--admin-header-height); background: white; box-shadow: 0 2px 10px rgba(0,0,0,.05); display: flex; align-items: center; justify-content: space-between; padding: 0 1rem; position: sticky; top: 0; z-index: 100; gap: 1rem; }
        @media (min-width:768px) { .admin-header { padding: 0 2rem; } }
        @media (prefers-color-scheme: dark) { .admin-header { background:#1e293b; box-shadow:0 2px 10px rgba(0,0,0,.3); border-bottom:1px solid #334155; } }
        .admin-content-wrapper { padding: 1rem; }
        @media (min-width:768px) { .admin-content-wrapper { padding: 2rem; } }
        @media (max-width:768px) {
            .admin-sidebar { width:100%; max-width:300px; box-shadow:-2px 0 10px rgba(0,0,0,.3); transform:translateX(100%); }
            .admin-sidebar[x-show="sidebarOpen"] { transform:translateX(0); }
            .admin-main-content { margin-right:0; }
        }
        .sidebar-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.5); z-index:999; cursor:pointer; }
        [x-cloak] { display:none !important; }
    </style>
</head>
<body class="font-vazirmatn"
      x-data="{ sidebarOpen:false, userDropdownOpen:false, isDesktop:false }"
      x-init="isDesktop = window.innerWidth > 768; sidebarOpen = isDesktop; window.addEventListener('resize', function(){ isDesktop = window.innerWidth > 768; if (isDesktop) sidebarOpen = true; });">
    <div class="sidebar-overlay" x-show="sidebarOpen && !isDesktop" @click="sidebarOpen=false" x-transition x-cloak style="display:none;"></div>
    @include('admin.partials.sidebar')
    <div class="admin-main-content">
        @include('admin.partials.header')
        @if(session('success'))<div class="mx-4 mt-4"><div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg"><span class="block sm:inline">{{ session('success') }}</span></div></div>@endif
        @if(session('error'))<div class="mx-4 mt-4"><div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg"><span class="block sm:inline">{{ session('error') }}</span></div></div>@endif
        @if(session('warning'))<div class="mx-4 mt-4"><div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded-lg"><span class="block sm:inline">{{ session('warning') }}</span></div></div>@endif
        @if(session('info'))<div class="mx-4 mt-4"><div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded-lg"><span class="block sm:inline">{{ session('info') }}</span></div></div>@endif

        @php
            $isElectionAdminPage = request()->routeIs('admin.elections.*') || request()->routeIs('admin.group.setting.*');
            $isElectionDashboard = request()->routeIs('admin.elections.dashboard');
            $isPlainPolicyIndex = request()->routeIs('admin.group.setting.index') && !request()->hasAny(['history', 'reporting']);
        @endphp
        @if($isElectionAdminPage && !$isElectionDashboard && !$isPlainPolicyIndex)
            <div class="px-4 md:px-8 pt-4">
                <a href="{{ route('admin.elections.dashboard') }}" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                    <i class="fas fa-arrow-right"></i>
                    بازگشت به مدیریت انتخابات
                </a>
            </div>
        @endif

        <div class="admin-content-wrapper">@yield('content')</div>
    </div>
    @stack('scripts')
    @yield('scripts')
    <script>
        function showAlert(message, type='info') { if (typeof Swal !== 'undefined') { Swal.fire({text:message,icon:type,confirmButtonText:'باشه',customClass:{confirmButton:'btn btn-primary'}}); } else { alert(message); } }
        function showSuccessAlert(message){ showAlert(message,'success'); }
        function showErrorAlert(message){ showAlert(message,'error'); }
        function showWarningAlert(message){ showAlert(message,'warning'); }
        function showInfoAlert(message){ showAlert(message,'info'); }
    </script>
</body>
</html>
