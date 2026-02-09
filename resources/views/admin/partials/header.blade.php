{{-- Admin Header --}}
<header class="admin-header">
    <!-- Mobile Menu Button -->
    <button @click="sidebarOpen = !sidebarOpen" 
            x-show="!isDesktop"
            class="md:hidden text-gray-700 dark:text-gray-300 focus:outline-none flex-shrink-0"
            style="display: none;">
        <i class="fas fa-bars text-2xl" x-show="!sidebarOpen"></i>
        <i class="fas fa-times text-2xl" x-show="sidebarOpen" x-transition style="display: none;"></i>
    </button>
    
    <!-- Page Title -->
    <div class="flex-1 mr-4 md:mr-0 min-w-0">
        <h1 class="text-base sm:text-lg md:text-2xl font-bold text-gray-800 dark:text-gray-100 truncate">@yield('page-title', 'داشبورد مدیریت')</h1>
        <p class="text-xs md:text-sm text-gray-500 dark:text-gray-400 mt-1 hidden sm:block">@yield('page-description', 'مدیریت سیستم EarthCoop')</p>
    </div>
    
    <!-- Header Actions -->
    <div class="flex items-center space-x-4 space-x-reverse">
        <!-- Dark Mode Toggle -->
        <div class="theme-toggle" onclick="toggleTheme()" title="تغییر تم">
            <span class="theme-toggle-icon sun">☀️</span>
            <span class="theme-toggle-icon moon">🌙</span>
            <div class="theme-toggle-slider"></div>
        </div>
        
        <!-- User Dropdown -->
        <div class="relative" x-data="{ open: false }">
            <button @click="open = !open" 
                    class="flex items-center space-x-2 space-x-reverse px-2 md:px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                <div class="w-8 h-8 bg-gradient-to-br from-green-400 to-blue-500 rounded-full flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-user text-white text-sm"></i>
                </div>
                <span class="text-gray-700 dark:text-gray-200 font-medium hidden md:block">{{ Auth::user()->fullName() }}</span>
                <i class="fas fa-chevron-down text-gray-500 dark:text-gray-400 text-sm hidden md:block"></i>
            </button>
            
            <div x-show="open" 
                 @click.away="open = false"
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="transform opacity-0 scale-95"
                 x-transition:enter-end="transform opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="transform opacity-100 scale-100"
                 x-transition:leave-end="transform opacity-0 scale-95"
                 class="absolute left-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg dark:shadow-xl py-2 z-50 border border-gray-200 dark:border-gray-700"
                 style="display: none;">
                <a href="{{ route('profile.show') }}" 
                   class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-user ml-2"></i>
                    پروفایل
                </a>
                <a href="{{ route('home') }}" 
                   class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-home ml-2"></i>
                    بازگشت به سایت
                </a>
                <hr class="my-2 border-gray-200 dark:border-gray-700">
                <a href="{{ route('logout') }}" 
                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                   class="block px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                    <i class="fas fa-sign-out-alt ml-2"></i>
                    خروج
                </a>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                    @csrf
                </form>
            </div>
        </div>
    </div>
</header>

