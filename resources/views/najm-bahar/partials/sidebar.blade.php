@php
    $routePrefix = $routePrefix ?? 'najm-bahar';
    $routeParams = $routeParams ?? [];
@endphp
<aside class="bg-white rounded-2xl shadow-lg p-6 border border-gray-200">
    <div class="pb-4 mb-4 border-b border-gray-200">
        <h2 class="text-xl font-bold text-gentle-black flex items-center gap-2" style="color: var(--color-gentle-black);">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl" style="background: rgba(16, 185, 129, 0.15);">
                <i class="fas fa-compass" style="color: var(--color-earth-green);"></i>
            </span>
            <span>منو نجم بهار</span>
        </h2>
        <p class="text-xs text-gray-500 mt-2">مسیرهای سریع مدیریت مالی</p>
    </div>
    <nav>
        <ul class="space-y-2">
            <li>
                <a href="{{ route($routePrefix . '.wallet', $routeParams) }}" class="sidebar-menu-link block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                    <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                    <i class="fas fa-wallet" style="color: var(--color-digital-gold);"></i>
                    <span class="flex-grow text-right mx-3">کیف پول</span>
                </a>
            </li>
            <li>
                <a href="{{ route($routePrefix . '.transfer', $routeParams) }}" class="sidebar-menu-link block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                    <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                    <i class="fas fa-exchange-alt" style="color: var(--color-ocean-blue);"></i>
                    <span class="flex-grow text-right mx-3">انتقال وجه</span>
                </a>
            </li>
            <li>
                <a href="#" class="sidebar-menu-link block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                    <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                    <i class="fas fa-coins" style="color: var(--color-ocean-blue);"></i>
                    <span class="flex-grow text-right mx-3">کیف دارایی</span>
                </a>
            </li>
            <li>
                <a href="#" class="sidebar-menu-link block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                    <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                    <i class="fas fa-chart-line" style="color: var(--color-dark-green);"></i>
                    <span class="flex-grow text-right mx-3">سرمایه گذاری</span>
                </a>
            </li>
            <li>
                <a href="#" class="sidebar-menu-link block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                    <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                    <i class="fas fa-project-diagram" style="color: var(--color-accent-peach);"></i>
                    <span class="flex-grow text-right mx-3">تعریف یک پروژه</span>
                </a>
            </li>
            <li>
                <a href="#" class="sidebar-menu-link block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                    <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                    <i class="fas fa-building" style="color: var(--color-ocean-blue);"></i>
                    <span class="flex-grow text-right mx-3">تاسیس یک شرکت</span>
                </a>
            </li>
            <li>
                <a href="#" class="sidebar-menu-link block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                    <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                    <i class="fas fa-store" style="color: var(--color-digital-gold);"></i>
                    <span class="flex-grow text-right mx-3">ایجاد یک فروشگاه</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>

