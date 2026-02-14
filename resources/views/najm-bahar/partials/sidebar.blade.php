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
            'hasSubmenu' => true,
            'submenu' => [
                [
                    'label' => 'انتقال وجه',
                    'icon' => 'fa-exchange-alt',
                    'route' => $routePrefix . '.transfer',
                    'enabled' => true,
                ],
                [
                    'label' => 'پرداخت حق عضویت',
                    'icon' => 'fa-id-card',
                    'route' => 'najm-bahar.membership-fee.info',
                    'enabled' => true,
                    'action' => 'membership',
                ],
                [
                    'label' => 'ایجاد حساب فرعی',
                    'icon' => 'fa-plus-circle',
                    'route' => $routePrefix . '.sub-accounts.create',
                    'enabled' => true,
                ],
                [
                    'label' => 'مدیریت حساب‌های فرعی',
                    'icon' => 'fa-layer-group',
                    'route' => $routePrefix . '.sub-accounts.index',
                    'enabled' => true,
                ],
                [
                    'label' => 'سوابق تراکنش‌ها',
                    'icon' => 'fa-history',
                    'route' => $routePrefix . '.reports',
                    'enabled' => true,
                ],
            ],
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

<style>
    /* Mobile Hamburger Menu Styles */
    .mobile-menu-toggle {
        display: none;
        position: fixed;
        bottom: 1rem;
        left: 1rem;
        z-index: 999;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--nb-color-digital-gold) 0%, var(--nb-color-ocean-blue) 100%);
        border: none;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        cursor: pointer;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }
    
    .mobile-menu-toggle:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 16px rgba(0,0,0,0.25);
    }
    
    .mobile-menu-toggle i {
        color: white;
        font-size: 1.25rem;
    }
    
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 998;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .sidebar-overlay.active {
        opacity: 1;
    }
    
    @media (max-width: 1023px) {
        .mobile-menu-toggle {
            display: flex;
        }
        
        aside {
            position: fixed;
            top: 0;
            right: -100%;
            height: 100vh;
            width: 320px;
            max-width: 85vw;
            z-index: 999;
            overflow-y: auto;
            transition: right 0.3s ease;
            margin: 0;
            border-radius: 0;
        }
        
        aside.mobile-open {
            right: 0;
        }
        
        .sidebar-overlay.active {
            display: block;
        }
    }
    
    .menu-item-with-submenu {
        position: relative;
    }
    
    .submenu {
        display: none;
        position: absolute;
        right: 100%;
        top: 0;
        margin-right: 0.5rem;
        min-width: 240px;
        background: white;
        border-radius: var(--nb-radius-lg);
        box-shadow: var(--nb-shadow-xl);
        border: 1px solid var(--nb-color-neutral-200);
        padding: 0.5rem;
        z-index: 50;
        animation: slideIn 0.2s ease-out;
    }
    
    .submenu-header {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border: 1px solid #fbbf24;
        border-radius: var(--nb-radius-md);
        padding: 0.625rem 0.875rem;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .submenu-header-icon {
        width: 28px;
        height: 28px;
        border-radius: var(--nb-radius-full);
        background: white;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #f59e0b;
        font-size: 0.875rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .submenu-header-content {
        flex: 1;
    }

    .submenu-header-action {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.25rem 0.65rem;
        border-radius: 999px;
        background: #f59e0b;
        color: white;
        font-size: 0.6875rem;
        font-weight: 700;
        text-decoration: none;
        white-space: nowrap;
        box-shadow: 0 1px 3px rgba(0,0,0,0.12);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .submenu-header-action:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(0,0,0,0.18);
    }
    
    .submenu-header-label {
        font-size: 0.6875rem;
        color: #78350f;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }
    
    .submenu-header-value {
        font-size: 0.9375rem;
        font-weight: 700;
        color: #92400e;
        direction: ltr;
        text-align: right;
    }
    
    .submenu-divider {
        height: 1px;
        background: linear-gradient(to left, transparent, var(--nb-color-neutral-200), transparent);
        margin: 0.5rem 0;
    }
    
    .menu-item-with-submenu:hover .submenu {
        display: block;
    }

    .menu-item-with-submenu.open .submenu {
        display: block;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(10px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    .submenu-link {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.625rem 0.875rem;
        border-radius: var(--nb-radius-md);
        color: var(--nb-color-gentle-black);
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s ease;
        text-decoration: none;
    }
    
    .submenu-link:not(.disabled):hover {
        background: var(--nb-gradient-card);
        transform: translateX(-3px);
    }
    
    .submenu-link.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }
    
    .submenu-link i {
        width: 16px;
        text-align: center;
        color: var(--nb-color-ocean-blue);
    }
    
    .submenu-coming-soon {
        font-size: 0.625rem;
        padding: 0.125rem 0.375rem;
        border-radius: 0.25rem;
        background: #fef3c7;
        color: #92400e;
        font-weight: 600;
        margin-right: auto;
    }
    
    .sidebar-menu-link.has-submenu::after {
        content: '\f054';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        font-size: 0.75rem;
        margin-right: auto;
        color: var(--nb-color-neutral-400);
        transition: transform 0.2s ease;
    }
    
    .menu-item-with-submenu:hover .sidebar-menu-link.has-submenu::after {
        transform: rotate(-180deg);
    }

    .menu-item-with-submenu.open .sidebar-menu-link.has-submenu::after {
        transform: rotate(-180deg);
    }

    @media (max-width: 1023px) {
        .menu-item-with-submenu:hover .submenu {
            display: none;
        }

        .submenu {
            position: static;
            margin: 0.5rem 0 0 0;
            min-width: 100%;
            box-shadow: none;
        }
    }
</style>

{{-- Mobile Menu Toggle Button --}}
<button class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="منوی نجم بهار">
    <i class="fas fa-bars"></i>
</button>

{{-- Overlay for mobile --}}
<div class="sidebar-overlay" onclick="closeMobileMenu()"></div>

<aside class="bg-white rounded-2xl shadow-lg p-6 border border-gray-200" style="border-radius: var(--nb-radius-xl);" id="najm-bahar-sidebar">
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
                <li class="{{ ($item['hasSubmenu'] ?? false) ? 'menu-item-with-submenu' : '' }}">
                    @if($item['enabled'])
                        <a href="{{ route($item['route'], $routeParams) }}" 
                           class="sidebar-menu-link {{ Str::startsWith($currentRouteName, $item['route']) ? 'active' : '' }} {{ ($item['hasSubmenu'] ?? false) ? 'has-submenu' : '' }}"
                           aria-current="{{ Str::startsWith($currentRouteName, $item['route']) ? 'page' : 'false' }}">
                            <i class="fas {{ $item['icon'] }}" style="color: {{ $item['iconColor'] }};" aria-hidden="true"></i>
                            <span class="flex-grow text-right mx-3">{{ $item['label'] }}</span>
                        </a>
                        
                        @if($item['hasSubmenu'] ?? false)
                            <div class="submenu">
                                {{-- Cashable Reputation Display --}}
                                <div class="submenu-header">
                                    <div class="submenu-header-icon">
                                        <i class="fas fa-star" aria-hidden="true"></i>
                                    </div>
                                    <div class="submenu-header-content">
                                        <div class="submenu-header-label">امتیاز قابل نقد</div>
                                        <div class="submenu-header-value">
                                            @if(isset($account))
                                                {{ number_format($uncashedPoints ?? ($account->reputation->cashable ?? 0)) }} امتیاز
                                            @else
                                                --- امتیاز
                                            @endif
                                        </div>
                                    </div>
                                    <a href="{{ route('reputation.conversion.info') }}" class="submenu-header-action js-open-reputation" data-action="reputation">
                                        <i class="fas fa-coins" aria-hidden="true"></i>
                                        نقد کردن
                                    </a>
                                </div>
                                
                                <div class="submenu-divider"></div>
                                
                                @foreach($item['submenu'] as $subItem)
                                    @if($subItem['enabled'] ?? true)
                                        <a href="{{ route($subItem['route'], $routeParams) }}" 
                                           class="submenu-link {{ ($subItem['action'] ?? '') === 'membership' ? 'js-open-membership' : '' }}">
                                            <i class="fas {{ $subItem['icon'] }}" aria-hidden="true"></i>
                                            <span>{{ $subItem['label'] }}</span>
                                        </a>
                                    @else
                                        <span class="submenu-link disabled">
                                            <i class="fas {{ $subItem['icon'] }}" aria-hidden="true"></i>
                                            <span>{{ $subItem['label'] }}</span>
                                            @if($subItem['comingSoon'] ?? false)
                                                <span class="submenu-coming-soon">بزودی</span>
                                            @endif
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    @else
                        <span class="sidebar-menu-link disabled" 
                              title="این قابلیت به زودی فعال می‌شود"
                              aria-disabled="true">
                            <i class="fas {{ $item['icon'] }}" style="color: {{ $item['iconColor'] }};" aria-hidden="true"></i>
                            <span class="flex-grow text-right mx-3">{{ $item['label'] }}</span>
                            @if($item['comingSoon'] ?? false)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 font-semibold" style="font-size: var(--nb-text-xs);">بزودی</span>
                            @endif
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>
</aside>

<script>
function toggleMobileMenu() {
    const sidebar = document.getElementById('najm-bahar-sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const toggle = document.querySelector('.mobile-menu-toggle');
    const icon = toggle.querySelector('i');
    const isOpen = sidebar.classList.contains('mobile-open');
    
    if (isOpen) {
        closeMobileMenu();
        return;
    }

    sidebar.classList.add('mobile-open');
    overlay.classList.add('active');
    icon.classList.remove('fa-bars');
    icon.classList.add('fa-times');
}

function closeMobileMenu() {
    const sidebar = document.getElementById('najm-bahar-sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const toggle = document.querySelector('.mobile-menu-toggle');
    const icon = toggle.querySelector('i');
    const openSubmenus = document.querySelectorAll('.menu-item-with-submenu.open');
    
    sidebar.classList.remove('mobile-open');
    overlay.classList.remove('active');
    icon.classList.remove('fa-times');
    icon.classList.add('fa-bars');

    openSubmenus.forEach(item => {
        item.classList.remove('open');
        const submenu = item.querySelector('.submenu');
        if (submenu) {
            submenu.style.display = '';
        }
    });
}

// Close menu when clicking a link
document.addEventListener('DOMContentLoaded', function() {
    const menuLinks = document.querySelectorAll('#najm-bahar-sidebar a');
    const submenuToggles = document.querySelectorAll('.sidebar-menu-link.has-submenu');
    const membershipLinks = document.querySelectorAll('.js-open-membership');
    const reputationLinks = document.querySelectorAll('.js-open-reputation');

    submenuToggles.forEach(link => {
        link.addEventListener('click', function(event) {
            if (window.innerWidth < 1024) {
                event.preventDefault();
                event.stopPropagation();
                const parent = link.closest('.menu-item-with-submenu');
                const submenu = parent ? parent.querySelector('.submenu') : null;
                const isOpen = parent.classList.contains('open');
                document.querySelectorAll('.menu-item-with-submenu.open').forEach(item => {
                    if (item !== parent) {
                        item.classList.remove('open');
                        const itemSubmenu = item.querySelector('.submenu');
                        if (itemSubmenu) {
                            itemSubmenu.style.display = '';
                        }
                    }
                });
                parent.classList.toggle('open', !isOpen);
                if (submenu) {
                    submenu.style.display = isOpen ? 'none' : 'block';
                }
            }
        });
    });

    membershipLinks.forEach(link => {
        link.addEventListener('click', function(event) {
            if (typeof openMembershipModal === 'function') {
                event.preventDefault();
                openMembershipModal();
                if (window.innerWidth < 1024) {
                    closeMobileMenu();
                }
            }
        });
    });

    reputationLinks.forEach(link => {
        link.addEventListener('click', function(event) {
            if (typeof openReputationModal === 'function') {
                event.preventDefault();
                openReputationModal();
                if (window.innerWidth < 1024) {
                    closeMobileMenu();
                }
            }
        });
    });

    menuLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth < 1024 && !link.classList.contains('has-submenu')) {
                closeMobileMenu();
            }
        });
    });
});
</script>
