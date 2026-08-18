const PANEL_ID = 'najm-hoda-management-panel-v2';
const REFRESH_MS = 60_000;

const canManage = () => Boolean(
    window.groupId
    && window.GroupChatConfig?.canManageSession
    && [2, 3].includes(Number(window.GroupChatConfig?.yourRole))
);

const number = (value) => Number.isFinite(Number(value)) ? Number(value) : 0;

function ensureStyles() {
    if (document.getElementById('nh-management-live-attention-styles')) return;
    const style = document.createElement('style');
    style.id = 'nh-management-live-attention-styles';
    style.textContent = `
        .nh-live-attention{margin-bottom:15px}
        .nh-live-attention-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px}
        .nh-live-stat{background:#fff;border:1px solid #dfe8e7;border-radius:11px;padding:8px 9px;min-height:55px}
        .nh-live-stat strong{display:block;font-size:14px;color:#245f59;margin-top:2px}
        .nh-live-stat span{font-size:9px;color:#78878b}
        .nh-live-attention-foot{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:7px;font-size:9px;color:#87969a}
        .nh-live-attention-refresh{border:0;background:#edf6f5;color:#2b756e;border-radius:8px;padding:5px 8px;font-size:9px;font-weight:800;cursor:pointer}
        @media(max-width:420px){.nh-live-attention-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    `;
    document.head.appendChild(style);
}

function setBadge(node, count) {
    if (!node) return;
    if (count > 0) {
        node.textContent = String(count > 99 ? '99+' : count);
        node.style.display = 'flex';
    } else {
        node.textContent = '';
        node.style.display = 'none';
    }
}

function participationCount(panel) {
    const badge = panel.querySelector('[data-key="participation"] [data-badge]');
    return number(String(badge?.textContent || '').replace('+', ''));
}

function refreshHeaderBadge(panel) {
    const attention = number(panel.dataset.attentionActiveCount);
    const participation = participationCount(panel);
    const headerBadge = document.querySelector('#nh-management-header-button .nh-mgmt-header-badge');
    setBadge(headerBadge, attention + participation);
    panel.dataset.managementAlertCount = String(attention + participation);
}

function ensureSection(panel) {
    let section = panel.querySelector('[data-nh-live-attention]');
    if (section) return section;

    const host = panel.querySelector('[data-nh-sections]');
    if (!host) return null;

    section = document.createElement('section');
    section.className = 'nh-mgmt-section nh-live-attention';
    section.dataset.nhLiveAttention = '1';
    section.innerHTML = `
        <div class="nh-mgmt-section-head"><span>وضعیت زنده نیازمند توجه</span><span data-live-attention-state>در حال بارگذاری…</span></div>
        <div class="nh-live-attention-grid">
            <div class="nh-live-stat"><span>فعال</span><strong data-live-stat="active_events">-</strong></div>
            <div class="nh-live-stat"><span>معوق</span><strong data-live-stat="overdue">-</strong></div>
            <div class="nh-live-stat"><span>نزدیک موعد</span><strong data-live-stat="due_soon">-</strong></div>
            <div class="nh-live-stat"><span>مسدود</span><strong data-live-stat="blocked">-</strong></div>
            <div class="nh-live-stat"><span>فوری</span><strong data-live-stat="urgent">-</strong></div>
            <div class="nh-live-stat"><span>بدون مسئول</span><strong data-live-stat="unassigned">-</strong></div>
        </div>
        <div class="nh-live-attention-foot">
            <span data-live-attention-updated>هنوز بروزرسانی نشده</span>
            <button type="button" class="nh-live-attention-refresh" data-live-attention-refresh>بروزرسانی</button>
        </div>
    `;
    host.prepend(section);
    section.querySelector('[data-live-attention-refresh]')?.addEventListener('click', () => refresh(panel, true));
    return section;
}

function render(panel, attention) {
    const stats = attention?.stats || {};
    const section = ensureSection(panel);
    if (!section) return;

    ['active_events', 'overdue', 'due_soon', 'blocked', 'urgent', 'unassigned'].forEach((key) => {
        const node = section.querySelector(`[data-live-stat="${key}"]`);
        if (node) node.textContent = String(number(stats[key]));
    });

    const state = section.querySelector('[data-live-attention-state]');
    if (state) state.textContent = number(stats.active_events) > 0 ? `${number(stats.active_events)} مورد فعال` : 'همه‌چیز آرام است';

    const updated = section.querySelector('[data-live-attention-updated]');
    if (updated) updated.textContent = `آخرین بروزرسانی: ${new Intl.DateTimeFormat('fa-IR', { hour: '2-digit', minute: '2-digit' }).format(new Date())}`;

    const attentionCard = panel.querySelector('[data-key="attention"]');
    setBadge(attentionCard?.querySelector('[data-badge]'), number(stats.active_events));

    panel.dataset.attentionActiveCount = String(number(stats.active_events));
    refreshHeaderBadge(panel);
    window.dispatchEvent(new CustomEvent('najm-hoda:attention-updated', { detail: { groupId: Number(window.groupId), attention } }));
}

async function refresh(panel, force = false) {
    if (!panel || panel.dataset.attentionLoading === '1') return;
    const now = Date.now();
    const last = number(panel.dataset.attentionLoadedAt);
    if (!force && last && now - last < 15_000) return;

    panel.dataset.attentionLoading = '1';
    const section = ensureSection(panel);
    const state = section?.querySelector('[data-live-attention-state]');
    if (state) state.textContent = 'در حال دریافت…';

    try {
        const response = await fetch(`/groups/${window.groupId}/najm-hoda/attention`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.status !== 'success') throw new Error(data.message || 'attention unavailable');
        panel.dataset.attentionLoadedAt = String(Date.now());
        render(panel, data.attention || {});
    } catch (error) {
        if (state) state.textContent = 'دریافت وضعیت ممکن نشد';
        console.warn('Najm Hoda management attention refresh failed:', error);
    } finally {
        panel.dataset.attentionLoading = '0';
    }
}

function watchParticipationBadge(panel) {
    const badge = panel.querySelector('[data-key="participation"] [data-badge]');
    if (!badge || badge.dataset.nhAggregateObserved === '1') return;
    badge.dataset.nhAggregateObserved = '1';
    new MutationObserver(() => refreshHeaderBadge(panel)).observe(badge, {
        childList: true,
        characterData: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['style'],
    });
    refreshHeaderBadge(panel);
}

function install() {
    if (!canManage()) return false;
    const panel = document.getElementById(PANEL_ID);
    if (!panel || panel.dataset.liveAttentionInstalled === '1') return false;

    panel.dataset.liveAttentionInstalled = '1';
    ensureStyles();
    ensureSection(panel);
    watchParticipationBadge(panel);
    refresh(panel, true);

    document.getElementById('nh-management-header-button')?.addEventListener('click', () => {
        window.setTimeout(() => refresh(panel), 0);
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refresh(panel);
    });

    window.setInterval(() => {
        if (!document.hidden) refresh(panel, true);
    }, REFRESH_MS);

    return true;
}

let attempts = 0;
const timer = window.setInterval(() => {
    attempts += 1;
    if (install() || attempts > 180) window.clearInterval(timer);
}, 75);

if (document.readyState !== 'loading') install();
