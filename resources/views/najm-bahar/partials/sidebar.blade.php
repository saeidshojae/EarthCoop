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
            'label' => 'سرمایه‌گذاری',
            'icon' => 'fa-chart-line',
            'iconColor' => 'var(--nb-color-dark-green)',
            'route' => 'najm-bahar.investments.index',
            'enabled' => true,
            'hasSubmenu' => true,
            'submenu' => [
                [
                    'label' => 'فرصت‌های سرمایه‌گذاری',
                    'icon' => 'fa-rocket',
                    'route' => 'najm-bahar.investments.index',
                    'enabled' => true,
                ],
                [
                    'label' => 'سرمایه‌گذاری‌های من',
                    'icon' => 'fa-briefcase',
                    'route' => 'najm-bahar.investments.my-investments',
                    'enabled' => true,
                ],
            ],
        ],
        [
            'label' => 'پروژه‌ها',
            'icon' => 'fa-project-diagram',
            'iconColor' => 'var(--nb-color-accent-peach)',
            'route' => 'najm-bahar.projects.index',
            'enabled' => true,
            'hasSubmenu' => true,
            'submenu' => [
                [
                    'label' => 'پروژه‌های من',
                    'icon' => 'fa-folder',
                    'route' => 'najm-bahar.projects.index',
                    'enabled' => true,
                ],
                [
                    'label' => 'ایجاد پروژه جدید',
                    'icon' => 'fa-plus-square',
                    'route' => 'najm-bahar.projects.create',
                    'enabled' => true,
                ],
            ],
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
    .najm-bahar-sidebar-toggle { display: none; }
    .najm-bahar-sidebar-body { display: block; }

    @media (max-width: 1023px) {
        .nb-dashboard {
            padding-top: 1rem !important;
            padding-bottom: 1.5rem !important;
        }
        .nb-responsive-shell {
            display: flex;
            flex-direction: column;
            gap: .875rem;
        }
        .nb-responsive-shell > .nb-hero {
            order: 2;
            margin: 0 !important;
            padding: 1.5rem !important;
        }
        .nb-responsive-shell > .nb-responsive-layout {
            display: contents;
        }
        .nb-responsive-layout > .nb-sidebar {
            order: 1;
        }
        .nb-responsive-layout > main {
            order: 3;
            margin-top: .125rem;
        }
        .nb-responsive-shell > .nb-hero h1 {
            margin-top: .75rem !important;
            font-size: 1.75rem !important;
            line-height: 1.35;
        }
        .nb-responsive-shell > .nb-hero p {
            margin-top: .375rem !important;
        }
        .nb-responsive-shell > .nb-hero > div {
            gap: .875rem !important;
        }
        #najm-bahar-sidebar {
            position: relative;
            inset: auto;
            width: 100%;
            max-width: none;
            height: auto;
            margin: 0;
            padding: 0 !important;
            overflow: visible;
            border-radius: 1rem !important;
            box-shadow: 0 10px 25px rgba(15, 23, 42, .1);
        }
        .najm-bahar-sidebar-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            min-height: 3.5rem;
            padding: .75rem 1rem;
            border: 0;
            border-radius: 1rem;
            background: transparent;
            color: var(--color-gentle-black);
            font-size: 1.25rem;
            font-weight: 700;
            cursor: pointer;
        }
        .najm-bahar-sidebar-toggle-label {
            display: flex;
            align-items: center;
            gap: .75rem;
        }
        .najm-bahar-sidebar-toggle-chevron { transition: transform .25s ease; }
        #najm-bahar-sidebar.mobile-open .najm-bahar-sidebar-toggle {
            border-bottom: 1px solid #e2e8f0;
            border-radius: 1rem 1rem 0 0;
        }
        #najm-bahar-sidebar.mobile-open .najm-bahar-sidebar-toggle-chevron { transform: rotate(180deg); }
        .najm-bahar-sidebar-body {
            display: none;
            padding: .75rem;
        }
        #najm-bahar-sidebar.mobile-open .najm-bahar-sidebar-body {
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

<aside class="bg-white rounded-2xl shadow-lg p-6 border border-gray-200" style="border-radius: var(--nb-radius-xl);" id="najm-bahar-sidebar">
    <button type="button" class="najm-bahar-sidebar-toggle" onclick="toggleNajmBaharSidebar()" aria-controls="najm-bahar-sidebar-body" aria-expanded="false">
        <span class="najm-bahar-sidebar-toggle-label"><i class="fas fa-bars" style="color: var(--color-earth-green);"></i><span>منو نجم بهار</span></span>
        <i class="fas fa-chevron-down najm-bahar-sidebar-toggle-chevron" aria-hidden="true"></i>
    </button>
    <div class="najm-bahar-sidebar-body" id="najm-bahar-sidebar-body">
    <div class="pb-4 mb-4 border-b border-gray-200 hidden lg:block">
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
    </div>
</aside>

<script>
    function toggleNajmBaharSidebar() {
        const sidebar = document.getElementById('najm-bahar-sidebar');
        const toggle = sidebar ? sidebar.querySelector('.najm-bahar-sidebar-toggle') : null;
        if (!sidebar || !toggle) return;
        const isOpen = sidebar.classList.contains('mobile-open');
        sidebar.classList.toggle('mobile-open', !isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
    }

    function closeMobileMenu() {
        const sidebar = document.getElementById('najm-bahar-sidebar');
        const toggle = sidebar ? sidebar.querySelector('.najm-bahar-sidebar-toggle') : null;
        const openSubmenus = document.querySelectorAll('.menu-item-with-submenu.open');

        if (sidebar) sidebar.classList.remove('mobile-open');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');

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
