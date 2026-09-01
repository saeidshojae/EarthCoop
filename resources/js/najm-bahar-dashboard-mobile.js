(() => {
    const pathname = window.location.pathname;
    const isDashboard = pathname === '/najm-bahar/dashboard';
    const isPersonalWallet = pathname === '/najm-bahar/wallet';

    const mobileQuery = window.matchMedia('(max-width: 1023px)');
    const sidebar = document.getElementById('najm-bahar-sidebar');
    if (!sidebar) return;
    const sidebarHost = sidebar.closest('.nb-sidebar');
    const dashboard = sidebar.closest('.nb-dashboard') || document.querySelector('.nb-dashboard');
    const main = dashboard?.querySelector('main');
    if (!dashboard || !sidebarHost || !main) return;

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
        [data-nb-mobile-nav-trigger]:hover, [data-nb-mobile-nav-trigger]:focus-visible {
            box-shadow: 0 10px 30px rgba(217, 154, 22, 0.52), 0 0 20px rgba(246, 196, 83, 0.38), inset 0 0 0 1px rgba(255, 248, 214, 0.95);
        }
        @keyframes nb-mobile-menu-glow {
            0%, 100% { box-shadow: 0 8px 20px rgba(217, 154, 22, 0.25), 0 0 6px rgba(246, 196, 83, 0.16), inset 0 0 0 1px rgba(255, 246, 199, 0.76); }
            50% { box-shadow: 0 10px 30px rgba(217, 154, 22, 0.48), 0 0 18px rgba(246, 196, 83, 0.38), inset 0 0 0 1px rgba(255, 249, 222, 0.95); }
        }
        .nb-wallet-transaction-list { display: none; }
        @media (prefers-reduced-motion: reduce) { [data-nb-mobile-nav-trigger] { animation: none; } }

        @media (max-width: 1023px) {
            .nb-wallet-hero-compact { padding: 14px 16px !important; border-radius: 22px !important; min-height: 0 !important; }
            .nb-wallet-hero-compact h1 { font-size: 1.34rem !important; margin-top: .45rem !important; line-height: 1.4 !important; }
            .nb-wallet-hero-compact p { font-size: .74rem !important; margin-top: .25rem !important; line-height: 1.55 !important; }
            .nb-wallet-hero-compact .nb-chip { padding: 4px 9px !important; font-size: .68rem !important; }
            .nb-wallet-hero-compact [data-nb-wallet-hero-actions] {
                display: grid !important;
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                width: 100%; gap: 7px !important; margin-top: 2px;
            }
            .nb-wallet-hero-compact [data-nb-wallet-hero-actions] a {
                width: 100%; min-width: 0; min-height: 34px; padding: 6px 8px !important;
                border-radius: 12px !important; font-size: .69rem !important;
                display: inline-flex; align-items: center; justify-content: center; gap: 5px; text-align: center;
            }
            [data-nb-wallet-tabs] { position: sticky; top: var(--unified-header-height, 64px); z-index: 40; margin: 9px 0 11px; }
            [data-nb-wallet-balance] { padding: 14px !important; border-radius: 18px !important; }
            [data-nb-wallet-balance] .nb-metric { font-size: 1.24rem !important; }
            [data-nb-wallet-balance] .nb-stat { padding: 10px !important; border-radius: 13px !important; }

            [data-nb-wallet-points] { padding: 13px !important; border-radius: 18px !important; }
            [data-nb-wallet-points] > div { align-items: center !important; gap: 10px; }
            [data-nb-wallet-points] .nb-wallet-points-compact { min-width: 0; }
            [data-nb-wallet-points] .nb-wallet-points-compact > div:first-child { padding: 8px !important; }
            [data-nb-wallet-points] .nb-wallet-points-compact svg { width: 1.25rem !important; height: 1.25rem !important; }
            [data-nb-wallet-points] .nb-wallet-points-compact > div:last-child { margin-right: 10px !important; }
            [data-nb-wallet-points] .nb-wallet-points-compact h3 { font-size: .86rem !important; margin-bottom: 2px !important; }
            [data-nb-wallet-points] .nb-wallet-points-compact span.text-3xl { font-size: 1.45rem !important; line-height: 1 !important; }
            [data-nb-wallet-points] .nb-wallet-points-compact .text-sm { font-size: .68rem !important; margin-top: 5px !important; gap: 7px !important; }
            [data-nb-wallet-points] [data-nb-wallet-convert] {
                width: auto !important; min-width: 116px; min-height: 38px; margin-top: 0;
                padding: 8px 11px !important; border-radius: 12px !important; font-size: .72rem !important;
                display: inline-flex !important; align-items: center; justify-content: center; flex: 0 0 auto;
            }

            [data-nb-wallet-transactions] { padding: 14px !important; border-radius: 18px !important; }
            [data-nb-wallet-transactions] table { display: none !important; }
            .nb-wallet-transaction-list { display: grid; gap: 8px; margin-top: 9px; }
            .nb-wallet-transaction-item { display: grid; gap: 6px; padding: 10px 11px; border: 1px solid rgba(226,232,240,.9); border-radius: 13px; background: linear-gradient(135deg,#fff,#f8fafc); }
            .nb-wallet-transaction-primary { display: flex; align-items: flex-start; justify-content: space-between; gap: 9px; }
            .nb-wallet-transaction-title { color: #0f172a; font-size: .78rem; font-weight: 800; line-height: 1.5; }
            .nb-wallet-transaction-amount { white-space: nowrap; font-size: .78rem; font-weight: 900; color: #0f766e; }
            .nb-wallet-transaction-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 5px 8px; color: #64748b; font-size: .65rem; }
            .nb-wallet-transaction-status { padding: 3px 7px; border-radius: 999px; background: #dcfce7; color: #15803d; font-weight: 800; }
            .nb-wallet-mobile-service-duplicate { display: none !important; }
        }
    `;
    document.head.appendChild(style);

    const sidebarPlaceholder = document.createComment('najm-bahar-sidebar-home');
    sidebar.parentNode?.insertBefore(sidebarPlaceholder, sidebar);
    const trigger = document.createElement('button');
    trigger.type = 'button'; trigger.setAttribute('data-nb-mobile-nav-trigger', 'true'); trigger.setAttribute('aria-expanded', 'false'); trigger.setAttribute('aria-controls', 'nb-mobile-nav-sheet');
    trigger.className = 'lg:hidden fixed bottom-5 left-5 z-[1200] inline-flex items-center gap-2 rounded-full px-4 py-3 font-bold shadow-xl border';
    trigger.innerHTML = '<i class="fas fa-wallet" aria-hidden="true"></i><span>منوی نجم بهار</span>';
    const sheet = document.createElement('div');
    sheet.id = 'nb-mobile-nav-sheet'; sheet.setAttribute('data-nb-mobile-nav-sheet', 'true'); sheet.className = 'hidden fixed inset-0 lg:hidden'; sheet.style.zIndex = '2147482000';
    sheet.innerHTML = `<button type="button" data-nb-mobile-nav-backdrop class="absolute inset-0 bg-slate-950/45" aria-label="بستن منوی نجم بهار"></button><section role="dialog" aria-modal="true" aria-label="منوی نجم بهار" class="absolute inset-x-0 bottom-0 max-h-[82dvh] overflow-hidden rounded-t-3xl bg-white shadow-2xl"><div class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><div><h2 class="font-black text-slate-900">منوی نجم بهار</h2><p class="mt-1 text-xs text-slate-500">مسیرهای سریع مدیریت مالی</p></div><button type="button" data-nb-mobile-nav-close class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-700" aria-label="بستن"><i class="fas fa-times" aria-hidden="true"></i></button></div><div data-nb-mobile-nav-content class="max-h-[calc(82dvh-5rem)] overflow-y-auto overscroll-contain p-4"></div></section>`;
    document.body.append(trigger, sheet);
    const sheetContent = sheet.querySelector('[data-nb-mobile-nav-content]');
    const originalToggle = sidebar.querySelector('.najm-bahar-sidebar-toggle');
    const originalBody = sidebar.querySelector('.najm-bahar-sidebar-body');
    const closeSheet = () => { sheet.classList.add('hidden'); trigger.setAttribute('aria-expanded', 'false'); document.body.style.overflow = ''; };
    const openSheet = () => { sheet.classList.remove('hidden'); trigger.setAttribute('aria-expanded', 'true'); document.body.style.overflow = 'hidden'; sheet.querySelector('[data-nb-mobile-nav-close]')?.focus(); };
    trigger.addEventListener('click', openSheet); sheet.querySelector('[data-nb-mobile-nav-close]')?.addEventListener('click', closeSheet); sheet.querySelector('[data-nb-mobile-nav-backdrop]')?.addEventListener('click', closeSheet);
    sheet.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeSheet(); }); sheet.addEventListener('click', (event) => { if (event.target.closest('a')) closeSheet(); });

    let accountCard = null, systemCard = null, dashboardTabs = null, activeDashboardTab = 'account';
    const createDashboardTabs = () => {
        if (!isDashboard) return;
        const findDashboardCard = (title) => Array.from(main.querySelectorAll('.nb-card')).find((card) => Array.from(card.querySelectorAll('h2')).some((heading) => heading.textContent.trim().includes(title)));
        accountCard = findDashboardCard('نمای کلی حساب شما'); systemCard = findDashboardCard('گزارش کلی سامانه'); if (!accountCard || !systemCard) return;
        dashboardTabs = document.createElement('div'); dashboardTabs.setAttribute('data-nb-dashboard-tabs', 'true'); dashboardTabs.className = 'hidden lg:hidden grid grid-cols-2 gap-1 rounded-2xl bg-slate-100 p-1';
        dashboardTabs.innerHTML = `<button type="button" data-nb-tab="account" class="rounded-xl px-3 py-3 text-sm font-bold transition" aria-selected="true">حساب من</button><button type="button" data-nb-tab="system" class="rounded-xl px-3 py-3 text-sm font-bold transition" aria-selected="false">وضعیت سامانه</button>`;
        accountCard.parentNode?.insertBefore(dashboardTabs, accountCard);
        dashboardTabs._render = () => { const accountActive = activeDashboardTab === 'account'; accountCard.classList.toggle('hidden', !accountActive && mobileQuery.matches); systemCard.classList.toggle('hidden', accountActive && mobileQuery.matches); dashboardTabs.querySelectorAll('[data-nb-tab]').forEach((button) => { const selected = button.dataset.nbTab === activeDashboardTab; button.setAttribute('aria-selected', selected ? 'true' : 'false'); button.classList.toggle('bg-white', selected); button.classList.toggle('text-emerald-700', selected); button.classList.toggle('shadow-sm', selected); button.classList.toggle('text-slate-500', !selected); }); };
        dashboardTabs.addEventListener('click', (event) => { const button = event.target.closest('[data-nb-tab]'); if (!button) return; activeDashboardTab = button.dataset.nbTab; dashboardTabs._render(); });
    };

    let walletTabs = null, walletCards = [], walletBalanceCard = null, walletPointsCard = null, walletTransactionsCard = null, activeWalletTab = 'account', pointsButton = null, pointsButtonDesktopHtml = '';
    const normalize = (value) => (value || '').replace(/\s+/g, ' ').trim();
    const findCardByText = (needle) => Array.from(main.querySelectorAll('.nb-card')).find((card) => normalize(card.textContent).includes(needle));
    const buildMobileTransactionList = (card) => {
        const table = card?.querySelector('table'); if (!table || card.querySelector('.nb-wallet-transaction-list')) return;
        const headers = Array.from(table.querySelectorAll('thead th')).map((cell) => normalize(cell.textContent)); const rows = Array.from(table.querySelectorAll('tbody tr')); const list = document.createElement('div'); list.className = 'nb-wallet-transaction-list';
        rows.forEach((row) => { const cells = Array.from(row.querySelectorAll('td')).map((cell) => normalize(cell.textContent)); if (!cells.length) return; const record = Object.fromEntries(cells.map((value,index) => [headers[index] || `field-${index}`, value])); const valueFor = (token) => Object.entries(record).find(([key]) => key.includes(token))?.[1] || ''; const description = valueFor('شرح') || cells.find((value) => value.length > 12) || cells[0]; const amount = valueFor('مبلغ') || cells.find((value) => /بهار/.test(value)) || ''; const status = valueFor('وضعیت') || cells.find((value) => /تکمیل|موفق|در انتظار|ناموفق/.test(value)) || ''; const date = valueFor('تاریخ') || cells.find((value) => /\d{2,4}[\/\-]\d{1,2}[\/\-]\d{1,2}/.test(value)) || ''; const item = document.createElement('article'); item.className = 'nb-wallet-transaction-item'; item.innerHTML = `<div class="nb-wallet-transaction-primary"><div class="nb-wallet-transaction-title"></div><div class="nb-wallet-transaction-amount"></div></div><div class="nb-wallet-transaction-meta">${status ? '<span class="nb-wallet-transaction-status"></span>' : ''}${date ? '<span class="nb-wallet-transaction-date"></span>' : ''}</div>`; item.querySelector('.nb-wallet-transaction-title').textContent = description; item.querySelector('.nb-wallet-transaction-amount').textContent = amount; if (status) item.querySelector('.nb-wallet-transaction-status').textContent = status; if (date) item.querySelector('.nb-wallet-transaction-date').textContent = date; list.appendChild(item); });
        if (list.children.length) table.closest('.overflow-x-auto')?.insertAdjacentElement('afterend', list);
    };
    const createWalletTabs = () => {
        if (!isPersonalWallet) return;
        const hero = dashboard.querySelector('.nb-hero'); hero?.classList.add('nb-wallet-hero-compact'); const heroActions = hero?.querySelector('.flex.flex-col.sm\\:flex-row'); heroActions?.setAttribute('data-nb-wallet-hero-actions', 'true');
        walletCards = Array.from(main.querySelectorAll('.nb-card')); walletBalanceCard = findCardByText('موجودی کل حساب'); walletPointsCard = findCardByText('امتیازات نقد'); walletTransactionsCard = findCardByText('تراکنش‌های اخیر');
        walletBalanceCard?.setAttribute('data-nb-wallet-balance', 'true'); walletPointsCard?.setAttribute('data-nb-wallet-points', 'true'); walletTransactionsCard?.setAttribute('data-nb-wallet-transactions', 'true');
        walletPointsCard?.querySelector('.flex.items-center.flex-1')?.classList.add('nb-wallet-points-compact');
        pointsButton = walletPointsCard ? Array.from(walletPointsCard.querySelectorAll('button, a')).find((element) => normalize(element.textContent).includes('نقد')) : null; if (pointsButton) { pointsButton.setAttribute('data-nb-wallet-convert', 'true'); pointsButtonDesktopHtml = pointsButton.innerHTML; }
        buildMobileTransactionList(walletTransactionsCard); if (!walletCards.length) return;
        walletTabs = document.createElement('div'); walletTabs.setAttribute('data-nb-wallet-tabs', 'true'); walletTabs.className = 'hidden lg:hidden grid grid-cols-2 gap-1 rounded-2xl bg-slate-100 p-1 shadow-sm'; walletTabs.innerHTML = `<button type="button" data-nb-wallet-tab="account" class="rounded-xl px-3 py-2.5 text-sm font-bold transition" aria-selected="true">حساب</button><button type="button" data-nb-wallet-tab="activity" class="rounded-xl px-3 py-2.5 text-sm font-bold transition" aria-selected="false">فعالیت</button>`; walletCards[0].parentNode?.insertBefore(walletTabs, walletCards[0]);
        walletTabs._render = () => { const activityActive = activeWalletTab === 'activity'; walletCards.forEach((card) => { const isActivityCard = card === walletPointsCard || card === walletTransactionsCard; card.classList.toggle('hidden', mobileQuery.matches && (activityActive ? !isActivityCard : isActivityCard)); }); walletTabs.querySelectorAll('[data-nb-wallet-tab]').forEach((button) => { const selected = button.dataset.nbWalletTab === activeWalletTab; button.setAttribute('aria-selected', selected ? 'true' : 'false'); button.classList.toggle('bg-white', selected); button.classList.toggle('text-emerald-700', selected); button.classList.toggle('shadow-sm', selected); button.classList.toggle('text-slate-500', !selected); }); };
        walletTabs.addEventListener('click', (event) => { const button = event.target.closest('[data-nb-wallet-tab]'); if (!button) return; activeWalletTab = button.dataset.nbWalletTab; walletTabs._render(); });
    };
    createDashboardTabs(); createWalletTabs();

    const applyResponsiveState = () => {
        if (mobileQuery.matches) {
            trigger.classList.remove('hidden'); dashboardTabs?.classList.remove('hidden'); walletTabs?.classList.remove('hidden'); sidebarHost.classList.add('hidden'); if (sheetContent && sidebar.parentElement !== sheetContent) sheetContent.appendChild(sidebar); if (originalToggle) originalToggle.style.display = 'none'; if (originalBody) originalBody.style.display = 'block'; sidebar.classList.remove('mobile-open'); sidebar.style.boxShadow = 'none'; sidebar.style.border = '0'; sidebar.style.borderRadius = '0'; dashboardTabs?._render?.(); walletTabs?._render?.(); if (pointsButton) pointsButton.textContent = 'تبدیل امتیاز به بهار'; if (isPersonalWallet) main.querySelectorAll('.nb-quick').forEach((item) => item.classList.add('nb-wallet-mobile-service-duplicate')); return;
        }
        closeSheet(); trigger.classList.add('hidden'); dashboardTabs?.classList.add('hidden'); walletTabs?.classList.add('hidden'); sidebarHost.classList.remove('hidden'); if (sidebarPlaceholder.parentNode && sidebar.parentElement !== sidebarPlaceholder.parentNode) sidebarPlaceholder.parentNode.insertBefore(sidebar, sidebarPlaceholder.nextSibling); if (originalToggle) originalToggle.style.display = ''; if (originalBody) originalBody.style.display = ''; sidebar.style.boxShadow = ''; sidebar.style.border = ''; sidebar.style.borderRadius = ''; accountCard?.classList.remove('hidden'); systemCard?.classList.remove('hidden'); walletCards.forEach((card) => card.classList.remove('hidden')); main.querySelectorAll('.nb-quick').forEach((item) => item.classList.remove('nb-wallet-mobile-service-duplicate')); if (pointsButton && pointsButtonDesktopHtml) pointsButton.innerHTML = pointsButtonDesktopHtml;
    };
    applyResponsiveState(); mobileQuery.addEventListener?.('change', applyResponsiveState);
})();
