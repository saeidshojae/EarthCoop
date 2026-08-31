(() => {
    const pathname = window.location.pathname;
    const isDashboard = pathname === '/najm-bahar/dashboard';
    const isPersonalWallet = pathname === '/najm-bahar/wallet';
    if (!isDashboard && !isPersonalWallet) return;

    const mobileQuery = window.matchMedia('(max-width: 1023px)');
    const dashboard = document.querySelector('.nb-dashboard');
    const sidebarHost = document.querySelector('.nb-sidebar');
    const sidebar = document.getElementById('najm-bahar-sidebar');
    const main = dashboard?.querySelector('main');

    if (!dashboard || !sidebarHost || !sidebar || !main) return;

    const style = document.createElement('style');
    style.setAttribute('data-nb-mobile-nav-style', 'true');
    style.textContent = `
        [data-nb-mobile-nav-trigger] {
            background: linear-gradient(135deg, #fff7cc 0%, #f6c453 45%, #d99a16 100%);
            border-color: rgba(180, 121, 12, 0.68) !important;
            color: #5b3a00 !important;
            box-shadow: 0 8px 24px rgba(217, 154, 22, 0.34), inset 0 0 0 1px rgba(255, 246, 199, 0.82);
            animation: nb-mobile-menu-glow 2.4s ease-in-out infinite;
        }
        [data-nb-mobile-nav-trigger]:hover,
        [data-nb-mobile-nav-trigger]:focus-visible {
            box-shadow: 0 10px 30px rgba(217, 154, 22, 0.52), 0 0 20px rgba(246, 196, 83, 0.38), inset 0 0 0 1px rgba(255, 248, 214, 0.95);
        }
        @keyframes nb-mobile-menu-glow {
            0%, 100% {
                box-shadow: 0 8px 20px rgba(217, 154, 22, 0.25), 0 0 6px rgba(246, 196, 83, 0.16), inset 0 0 0 1px rgba(255, 246, 199, 0.76);
            }
            50% {
                box-shadow: 0 10px 30px rgba(217, 154, 22, 0.48), 0 0 18px rgba(246, 196, 83, 0.38), inset 0 0 0 1px rgba(255, 249, 222, 0.95);
            }
        }
        @media (prefers-reduced-motion: reduce) {
            [data-nb-mobile-nav-trigger] { animation: none; }
        }
    `;
    document.head.appendChild(style);

    const sidebarPlaceholder = document.createComment('najm-bahar-sidebar-home');
    sidebar.parentNode?.insertBefore(sidebarPlaceholder, sidebar);

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.setAttribute('data-nb-mobile-nav-trigger', 'true');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', 'nb-mobile-nav-sheet');
    trigger.className = 'lg:hidden fixed bottom-5 left-5 z-[1200] inline-flex items-center gap-2 rounded-full px-4 py-3 font-bold shadow-xl border';
    trigger.innerHTML = '<i class="fas fa-wallet" aria-hidden="true"></i><span>منوی نجم بهار</span>';

    const sheet = document.createElement('div');
    sheet.id = 'nb-mobile-nav-sheet';
    sheet.setAttribute('data-nb-mobile-nav-sheet', 'true');
    sheet.className = 'hidden fixed inset-0 lg:hidden';
    sheet.style.zIndex = '2147482000';
    sheet.innerHTML = `
        <button type="button" data-nb-mobile-nav-backdrop class="absolute inset-0 bg-slate-950/45" aria-label="بستن منوی نجم بهار"></button>
        <section role="dialog" aria-modal="true" aria-label="منوی نجم بهار" class="absolute inset-x-0 bottom-0 max-h-[82dvh] overflow-hidden rounded-t-3xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <div>
                    <h2 class="font-black text-slate-900">منوی نجم بهار</h2>
                    <p class="mt-1 text-xs text-slate-500">مسیرهای سریع مدیریت مالی</p>
                </div>
                <button type="button" data-nb-mobile-nav-close class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-700" aria-label="بستن">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div data-nb-mobile-nav-content class="max-h-[calc(82dvh-5rem)] overflow-y-auto overscroll-contain p-4"></div>
        </section>`;

    document.body.append(trigger, sheet);

    const sheetContent = sheet.querySelector('[data-nb-mobile-nav-content]');
    const originalToggle = sidebar.querySelector('.najm-bahar-sidebar-toggle');
    const originalBody = sidebar.querySelector('.najm-bahar-sidebar-body');

    const closeSheet = () => {
        sheet.classList.add('hidden');
        trigger.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    };

    const openSheet = () => {
        sheet.classList.remove('hidden');
        trigger.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
        sheet.querySelector('[data-nb-mobile-nav-close]')?.focus();
    };

    trigger.addEventListener('click', openSheet);
    sheet.querySelector('[data-nb-mobile-nav-close]')?.addEventListener('click', closeSheet);
    sheet.querySelector('[data-nb-mobile-nav-backdrop]')?.addEventListener('click', closeSheet);
    sheet.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeSheet();
    });
    sheet.addEventListener('click', (event) => {
        if (event.target.closest('a')) closeSheet();
    });

    let accountCard = null;
    let systemCard = null;
    let tabs = null;
    let activeTab = 'account';

    const createDashboardTabs = () => {
        if (!isDashboard) return;

        const findDashboardCard = (title) => Array.from(main.querySelectorAll('.nb-card')).find((card) =>
            Array.from(card.querySelectorAll('h2')).some((heading) => heading.textContent.trim().includes(title))
        );

        accountCard = findDashboardCard('نمای کلی حساب شما');
        systemCard = findDashboardCard('گزارش کلی سامانه');
        if (!accountCard || !systemCard) return;

        tabs = document.createElement('div');
        tabs.setAttribute('data-nb-dashboard-tabs', 'true');
        tabs.className = 'hidden lg:hidden grid grid-cols-2 gap-1 rounded-2xl bg-slate-100 p-1';
        tabs.innerHTML = `
            <button type="button" data-nb-tab="account" class="rounded-xl px-3 py-3 text-sm font-bold transition" aria-selected="true">حساب من</button>
            <button type="button" data-nb-tab="system" class="rounded-xl px-3 py-3 text-sm font-bold transition" aria-selected="false">وضعیت سامانه</button>`;
        accountCard.parentNode?.insertBefore(tabs, accountCard);

        const renderTabs = () => {
            const accountActive = activeTab === 'account';
            accountCard.classList.toggle('hidden', !accountActive && mobileQuery.matches);
            systemCard.classList.toggle('hidden', accountActive && mobileQuery.matches);
            tabs.querySelectorAll('[data-nb-tab]').forEach((button) => {
                const selected = button.dataset.nbTab === activeTab;
                button.setAttribute('aria-selected', selected ? 'true' : 'false');
                button.classList.toggle('bg-white', selected);
                button.classList.toggle('text-emerald-700', selected);
                button.classList.toggle('shadow-sm', selected);
                button.classList.toggle('text-slate-500', !selected);
            });
        };

        tabs.addEventListener('click', (event) => {
            const button = event.target.closest('[data-nb-tab]');
            if (!button) return;
            activeTab = button.dataset.nbTab;
            renderTabs();
        });

        tabs._render = renderTabs;
    };

    createDashboardTabs();

    const applyResponsiveState = () => {
        if (mobileQuery.matches) {
            trigger.classList.remove('hidden');
            tabs?.classList.remove('hidden');
            sidebarHost.classList.add('hidden');
            if (sheetContent && sidebar.parentElement !== sheetContent) sheetContent.appendChild(sidebar);
            if (originalToggle) originalToggle.style.display = 'none';
            if (originalBody) originalBody.style.display = 'block';
            sidebar.classList.remove('mobile-open');
            sidebar.style.boxShadow = 'none';
            sidebar.style.border = '0';
            sidebar.style.borderRadius = '0';
            tabs?._render?.();
            return;
        }

        closeSheet();
        trigger.classList.add('hidden');
        tabs?.classList.add('hidden');
        sidebarHost.classList.remove('hidden');
        if (sidebarPlaceholder.parentNode && sidebar.parentElement !== sidebarPlaceholder.parentNode) {
            sidebarPlaceholder.parentNode.insertBefore(sidebar, sidebarPlaceholder.nextSibling);
        }
        if (originalToggle) originalToggle.style.display = '';
        if (originalBody) originalBody.style.display = '';
        sidebar.style.boxShadow = '';
        sidebar.style.border = '';
        sidebar.style.borderRadius = '';
        accountCard?.classList.remove('hidden');
        systemCard?.classList.remove('hidden');
    };

    applyResponsiveState();
    mobileQuery.addEventListener?.('change', applyResponsiveState);
})();
