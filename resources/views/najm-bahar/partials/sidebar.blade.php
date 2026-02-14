@php
    $routePrefix = $routePrefix ?? 'najm-bahar';
    $routeParams = $routeParams ?? [];
    $currentRouteName = Route::currentRouteName();
    
    // Define menu items with their routes and enabled status
    $menuItems = [
        [
            'label' => 'کیف پول',
            'icon' => 'fa-wallet',
            'iconColor' => 'var(--nb-color-digital-gold)',
            'route' => $routePrefix . '.wallet',
            'enabled' => true,
        ],
        [
            'label' => 'انتقال وجه',
            'icon' => 'fa-exchange-alt',
            'iconColor' => 'var(--nb-color-ocean-blue)',
            'route' => $routePrefix . '.transfer',
            'enabled' => true,
        ],
        [
            'label' => 'کیف دارایی',
            'icon' => 'fa-coins',
            'iconColor' => 'var(--nb-color-ocean-blue)',
            'route' => null,
            'enabled' => false,
            'comingSoon' => true,
        ],
        [
            'label' => 'سرمایه گذاری',
            'icon' => 'fa-chart-line',
            'iconColor' => 'var(--nb-color-dark-green)',
            'route' => null,
            'enabled' => false,
            'comingSoon' => true,
        ],
        [
            'label' => 'تعریف یک پروژه',
            'icon' => 'fa-project-diagram',
            'iconColor' => 'var(--nb-color-accent-peach)',
            'route' => null,
            'enabled' => false,
            'comingSoon' => true,
        ],
        [
            'label' => 'تاسیس یک شرکت',
            'icon' => 'fa-building',
            'iconColor' => 'var(--nb-color-ocean-blue)',
            'route' => null,
            'enabled' => false,
            'comingSoon' => true,
        ],
        [
            'label' => 'ایجاد یک فروشگاه',
            'icon' => 'fa-store',
            'iconColor' => 'var(--nb-color-digital-gold)',
            'route' => null,
            'enabled' => false,
            'comingSoon' => true,
        ],
    ];
@endphp
<aside class="bg-white rounded-2xl shadow-lg p-6 border border-gray-200" style="border-radius: var(--nb-radius-xl);">
    <div class="pb-4 mb-4 border-b border-gray-200">
        <h2 class="text-xl font-bold text-gentle-black flex items-center gap-2" style="color: var(--color-gentle-black);">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl" style="background: rgba(16, 185, 129, 0.15); border-radius: var(--nb-radius-md);">
                <i class="fas fa-compass" style="color: var(--color-earth-green);" aria-hidden="true"></i>
            </span>
            <span>منو نجم بهار</span>
        </h2>
        <p class="text-xs text-gray-500 mt-2">مسیرهای سریع مدیریت مالی</p>
    </div>
    <nav aria-label="منوی اصلی نجم بهار">
        <ul class="space-y-2">
            @foreach($menuItems as $item)
                <li>
                    @if($item['enabled'])
                        <a href="{{ route($item['route'], $routeParams) }}" 
                           class="sidebar-menu-link {{ Str::startsWith($currentRouteName, $item['route']) ? 'active' : '' }}"
                           aria-current="{{ Str::startsWith($currentRouteName, $item['route']) ? 'page' : 'false' }}">
                            <i class="fas {{ $item['icon'] }}" style="color: {{ $item['iconColor'] }};" aria-hidden="true"></i>
                            <span class="flex-grow text-right mx-3">{{ $item['label'] }}</span>
                        </a>
                    @else
                        <span class="sidebar-menu-link disabled" 
                              title="این قابلیت به زودی فعال می‌شود"
                              aria-disabled="true">
                            <i class="fas {{ $item['icon'] }}" style="color: {{ $item['iconColor'] }};" aria-hidden="true"></i>
                            <span class="flex-grow text-right mx-3">{{ $item['label'] }}</span>
                            @if($item['comingSoon'] ?? false)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 font-semibold" style="font-size: var(--nb-text-xs);">قریباً</span>
                            @endif
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>
</aside>

