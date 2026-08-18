const MANAGEMENT_TAB_ID = 'najm-hoda-management-tab';
const MANAGEMENT_PANEL_ID = 'najm-hoda-management-panel';

function canManageCurrentGroup() {
    const config = window.GroupChatConfig || {};
    return Boolean(window.groupId && config.canManageSession && [2, 3].includes(Number(config.yourRole)));
}

function roleLabel() {
    return Number(window.GroupChatConfig?.yourRole) === 3 ? 'مدیر گروه' : 'بازرس گروه';
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function managementPageContext(widget) {
    if (typeof widget?.getPageContext === 'function') return widget.getPageContext();
    return {
        route_name: document.getElementById('najm-hoda-widget')?.dataset?.routeName || null,
        module: 'groups',
        resource_type: 'group',
        resource_id: window.groupId || null,
    };
}

function formatReply(content) {
    return escapeHtml(content)
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/\*([^*]+)\*\*/g, '<em>$1</em>')
        .replace(/\n/g, '<br>');
}

function looksAwaitingConfirmation(message) {
    const plain = String(message || '');
    const hasConfirm = plain.includes('تأیید') || plain.includes('تایید');
    const hasCancel = plain.includes('لغو') || plain.includes('انصراف');
    return hasConfirm && hasCancel;
}

function managementCommandCatalog() {
    return [
        {
            section: 'نشست رسمی',
            items: [
                { key: 'create-meeting', icon: 'fa-calendar-plus', title: 'تنظیم نشست', description: 'عنوان، موضوع، دستور جلسه و زمان را ثبت کنید.', tone: 'primary', form: true },
                { key: 'meeting-status', icon: 'fa-clock', title: 'وضعیت نشست‌ها', description: 'نشست فعال و برنامه‌ریزی‌شده را ببینید.', command: 'وضعیت جلسه رسمی را نشان بده' },
                { key: 'end-meeting', icon: 'fa-stop-circle', title: 'پایان نشست', description: 'نشست فعال را با تأیید شما پایان می‌دهد.', command: 'نشست فعال را پایان بده', tone: 'danger' },
            ],
        },
        {
            section: 'صورتجلسه و تصمیمات',
            items: [
                { key: 'view-minutes', icon: 'fa-file-alt', title: 'مشاهده صورتجلسه', description: 'آخرین سند مدیریتی نشست را نمایش می‌دهد.', command: 'صورتجلسه را نشان بده' },
                { key: 'extract-decisions', icon: 'fa-gavel', title: 'استخراج تصمیمات', description: 'تصمیمات را فقط از evidence واقعی نشست پیشنهاد می‌دهد.', command: 'تصمیمات صورتجلسه را استخراج کن' },
                { key: 'approve-minutes', icon: 'fa-file-signature', title: 'تأیید صورتجلسه', description: 'پیش‌نویس را بعد از preview رسمی می‌کند.', command: 'صورتجلسه را تأیید کن', tone: 'primary' },
            ],
        },
        {
            section: 'اقدام و پیگیری',
            items: [
                { key: 'extract-actions', icon: 'fa-tasks', title: 'استخراج اقدامات', description: 'Action Itemهای مبتنی بر شواهد نشست را پیشنهاد می‌دهد.', command: 'موارد اقدام صورتجلسه را استخراج کن' },
                { key: 'action-queue', icon: 'fa-list-check', title: 'صف اقدام', description: 'کارهای باز، مسئول، موعد و وضعیت را ببینید.', command: 'صف اقدام گروه را نشان بده' },
                { key: 'attention', icon: 'fa-bell', title: 'نیازمند توجه', description: 'معوق، مسدود، فوری و بدون مسئول را جمع‌بندی می‌کند.', command: 'الان چه چیزهایی نیاز به توجه من دارد؟', tone: 'warning' },
            ],
        },
        {
            section: 'مشارکت اعضا',
            items: [
                { key: 'participation', icon: 'fa-hand-paper', title: 'مدیریت مشارکت‌ها', description: 'دست‌های بالا، اعضا و مجوزهای موقت نشست.', native: 'participation', tone: 'primary' },
            ],
        },
    ];
}

function addStyles() {
    if (document.getElementById('najm-hoda-management-styles')) return;
    const style = document.createElement('style');
    style.id = 'najm-hoda-management-styles';
    style.textContent = `
        .nh-console-tabs{display:flex;gap:6px;padding:8px 10px;background:#f4f8f8;border-bottom:1px solid #e4ecec;direction:rtl}
        .nh-console-tab{flex:1;border:0;border-radius:10px;padding:8px 10px;background:transparent;color:#5a6b70;font-size:12px;font-weight:700;cursor:pointer;transition:.2s ease}
        .nh-console-tab.is-active{background:#fff;color:#247f76;box-shadow:0 2px 8px rgba(35,91,87,.08)}
        .nh-console-tab i{margin-left:6px}
        .nh-management-panel{flex:1;min-height:0;overflow:auto;background:#f6f9f9;direction:rtl;color:#26393d}
        .nh-management-hero{padding:16px;background:linear-gradient(145deg,#153f3c,#206c65);color:#fff;position:relative;overflow:hidden}
        .nh-management-hero:after{content:'';position:absolute;width:120px;height:120px;border-radius:50%;background:rgba(255,255,255,.06);left:-28px;top:-48px}
        .nh-management-kicker{font-size:11px;opacity:.72;margin-bottom:5px}
        .nh-management-title{font-size:16px;font-weight:800;margin:0 0 4px}
        .nh-management-subtitle{font-size:11px;opacity:.8;margin:0}
        .nh-management-live{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap}
        .nh-live-chip{display:inline-flex;align-items:center;gap:5px;padding:5px 8px;border-radius:999px;background:rgba(255,255,255,.11);font-size:10px;border:1px solid rgba(255,255,255,.1)}
        .nh-live-dot{width:7px;height:7px;border-radius:50%;background:#86e3b4;box-shadow:0 0 0 3px rgba(134,227,180,.12)}
        .nh-management-body{padding:14px}
        .nh-console-section{margin-bottom:16px}
        .nh-console-section-title{font-size:11px;font-weight:800;color:#6a7b7f;margin:0 2px 8px;display:flex;align-items:center;justify-content:space-between}
        .nh-command-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
        .nh-command-card{border:1px solid #e0e9e8;background:#fff;border-radius:14px;padding:11px;text-align:right;cursor:pointer;transition:.2s ease;min-height:92px;box-shadow:0 2px 8px rgba(36,71,68,.035);color:#2d4246}
        .nh-command-card:hover{transform:translateY(-1px);border-color:#bcded9;box-shadow:0 7px 18px rgba(36,112,103,.09)}
        .nh-command-card.is-danger .nh-command-icon{background:#fff1f1;color:#bc4b4b}
        .nh-command-card.is-warning .nh-command-icon{background:#fff7e8;color:#aa781c}
        .nh-command-card.is-primary .nh-command-icon{background:#eaf8f5;color:#247f76}
        .nh-command-icon{width:30px;height:30px;border-radius:9px;display:flex;align-items:center;justify-content:center;background:#eff5f4;color:#56716d;margin-bottom:8px;font-size:13px}
        .nh-command-title{font-size:12px;font-weight:800;margin-bottom:3px}
        .nh-command-desc{font-size:10px;line-height:1.55;color:#78888c}
        .nh-management-activity{background:#fff;border:1px solid #e0e9e8;border-radius:14px;overflow:hidden;margin-top:14px}
        .nh-activity-head{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-bottom:1px solid #edf2f1}
        .nh-activity-head strong{font-size:11px}
        .nh-activity-clear{border:0;background:transparent;color:#829095;font-size:10px;cursor:pointer}
        .nh-activity-stream{max-height:210px;overflow:auto;padding:10px;background:#fbfdfd}
        .nh-activity-empty{font-size:10px;color:#8a999d;text-align:center;padding:10px}
        .nh-activity-message{margin-bottom:8px;padding:8px 10px;border-radius:11px;font-size:10.5px;line-height:1.65}
        .nh-activity-message.is-user{background:#eaf6f4;margin-right:28px;color:#2d5651}
        .nh-activity-message.is-assistant{background:#fff;border:1px solid #e5eceb;margin-left:18px}
        .nh-confirm-row{display:flex;gap:7px;margin-top:8px}
        .nh-confirm-btn{border:0;border-radius:9px;padding:7px 11px;font-size:10px;font-weight:800;cursor:pointer}
        .nh-confirm-btn.is-confirm{background:#247f76;color:#fff}
        .nh-confirm-btn.is-cancel{background:#eef2f2;color:#59696d}
        .nh-meeting-sheet{display:none;margin:0 14px 14px;background:#fff;border:1px solid #dfe9e7;border-radius:14px;padding:13px;box-shadow:0 8px 24px rgba(29,79,74,.08)}
        .nh-meeting-sheet.is-open{display:block}
        .nh-meeting-sheet h4{font-size:12px;margin:0 0 10px;color:#285b56}
        .nh-field{margin-bottom:8px}
        .nh-field label{display:block;font-size:10px;font-weight:700;color:#66777b;margin-bottom:4px}
        .nh-field input,.nh-field textarea{width:100%;border:1px solid #dce6e4;border-radius:9px;padding:8px 9px;font-size:11px;background:#fbfdfd;outline:none}
        .nh-field input:focus,.nh-field textarea:focus{border-color:#7fc7be;box-shadow:0 0 0 2px rgba(55,196,180,.09)}
        .nh-sheet-actions{display:flex;gap:7px;margin-top:10px}
        .nh-sheet-primary,.nh-sheet-secondary{border:0;border-radius:9px;padding:8px 11px;font-size:10px;font-weight:800;cursor:pointer}
        .nh-sheet-primary{background:#247f76;color:#fff;flex:1}
        .nh-sheet-secondary{background:#eef3f2;color:#5d6b6f}
        .nh-panel-spinner{display:inline-block;width:12px;height:12px;border:2px solid #d7e3e1;border-top-color:#247f76;border-radius:50%;animation:nhspin .8s linear infinite}
        @keyframes nhspin{to{transform:rotate(360deg)}}
        @media(max-width:480px){.nh-command-grid{grid-template-columns:1fr 1fr}.nh-command-card{min-height:86px;padding:10px}.nh-management-body{padding:11px}.nh-management-hero{padding:14px}}
    `;
    document.head.appendChild(style);
}

function createTabs(container, header, messages, typing, footer) {
    const tabs = document.createElement('div');
    tabs.className = 'nh-console-tabs';
    tabs.innerHTML = `
        <button type="button" class="nh-console-tab is-active" data-nh-tab="chat"><i class="fas fa-comments"></i> گفتگو</button>
        <button type="button" class="nh-console-tab" id="${MANAGEMENT_TAB_ID}" data-nh-tab="management"><i class="fas fa-briefcase"></i> خدمات مدیریتی</button>
    `;
    header.insertAdjacentElement('afterend', tabs);

    const show = tab => {
        const management = document.getElementById(MANAGEMENT_PANEL_ID);
        const isManagement = tab === 'management';
        messages.style.display = isManagement ? 'none' : 'flex';
        if (typing) typing.style.display = isManagement ? 'none' : '';
        if (footer) footer.style.display = isManagement ? 'none' : '';
        if (management) management.hidden = !isManagement;
        tabs.querySelectorAll('[data-nh-tab]').forEach(button => button.classList.toggle('is-active', button.dataset.nhTab === tab));
    };

    tabs.addEventListener('click', event => {
        const button = event.target.closest('[data-nh-tab]');
        if (button) show(button.dataset.nhTab);
    });

    return { show };
}

function createManagementPanel(container, widget) {
    const panel = document.createElement('section');
    panel.id = MANAGEMENT_PANEL_ID;
    panel.className = 'nh-management-panel';
    panel.hidden = true;

    const groupRole = roleLabel();
    panel.innerHTML = `
        <div class="nh-management-hero">
            <div class="nh-management-kicker">نجم هدا · وزیر مدیریت گروه</div>
            <h3 class="nh-management-title">کنسول خدمات مدیریتی</h3>
            <p class="nh-management-subtitle">${escapeHtml(groupRole)} · گروه جاری</p>
            <div class="nh-management-live">
                <span class="nh-live-chip"><span class="nh-live-dot"></span> دسترسی مدیریتی فعال</span>
                <span class="nh-live-chip" data-nh-session-chip>در حال دریافت وضعیت نشست...</span>
                <span class="nh-live-chip" data-nh-pending-chip>درخواست مشارکت: —</span>
            </div>
        </div>
        <div class="nh-management-body" data-nh-command-sections></div>
        <div class="nh-meeting-sheet" data-nh-meeting-sheet>
            <h4><i class="fas fa-calendar-plus"></i> تنظیم نشست رسمی</h4>
            <div class="nh-field"><label>عنوان نشست</label><input type="text" data-nh-meeting-title maxlength="120" placeholder="مثلاً نشست بررسی بودجه"></div>
            <div class="nh-field"><label>موضوع</label><input type="text" data-nh-meeting-subject maxlength="180" placeholder="موضوع اصلی نشست"></div>
            <div class="nh-field"><label>دستور جلسه</label><textarea data-nh-meeting-agenda rows="2" maxlength="400" placeholder="محورهای مورد بررسی"></textarea></div>
            <div class="nh-field"><label>زمان</label><input type="text" data-nh-meeting-time maxlength="80" placeholder="مثلاً 2026-08-19 18:00 یا الان"></div>
            <div class="nh-sheet-actions">
                <button type="button" class="nh-sheet-primary" data-nh-submit-meeting>آماده‌سازی و بررسی</button>
                <button type="button" class="nh-sheet-secondary" data-nh-close-meeting>بستن</button>
            </div>
        </div>
        <div class="nh-management-body" style="padding-top:0">
            <div class="nh-management-activity">
                <div class="nh-activity-head"><strong>جریان کار مدیریتی</strong><button type="button" class="nh-activity-clear" data-nh-clear>پاک‌کردن نمایش</button></div>
                <div class="nh-activity-stream" data-nh-activity><div class="nh-activity-empty">یک خدمت را انتخاب کنید؛ نجم هدا مراحل لازم را همین‌جا پیش می‌برد.</div></div>
            </div>
        </div>
    `;

    container.insertBefore(panel, document.getElementById('najm-hoda-messages'));
    renderCommandSections(panel, widget);
    bindMeetingForm(panel, widget);
    panel.querySelector('[data-nh-clear]')?.addEventListener('click', () => {
        const stream = panel.querySelector('[data-nh-activity]');
        if (stream) stream.innerHTML = '<div class="nh-activity-empty">نمایش پاک شد؛ سوابق اصلی گفتگو همچنان در تاریخچه نجم هدا محفوظ است.</div>';
    });
    refreshSessionState(panel);
    return panel;
}

function renderCommandSections(panel, widget) {
    const host = panel.querySelector('[data-nh-command-sections]');
    if (!host) return;
    host.innerHTML = '';

    managementCommandCatalog().forEach(group => {
        const section = document.createElement('section');
        section.className = 'nh-console-section';
        const title = document.createElement('div');
        title.className = 'nh-console-section-title';
        title.textContent = group.section;
        const grid = document.createElement('div');
        grid.className = 'nh-command-grid';

        group.items.forEach(item => {
            const card = document.createElement('button');
            card.type = 'button';
            card.className = `nh-command-card${item.tone ? ` is-${item.tone}` : ''}`;
            card.innerHTML = `
                <span class="nh-command-icon"><i class="fas ${item.icon}"></i></span>
                <div class="nh-command-title">${escapeHtml(item.title)}</div>
                <div class="nh-command-desc">${escapeHtml(item.description)}</div>
            `;
            card.addEventListener('click', () => handleCommand(item, panel, widget));
            grid.appendChild(card);
        });

        section.append(title, grid);
        host.appendChild(section);
    });
}

function handleCommand(item, panel, widget) {
    if (item.form) {
        panel.querySelector('[data-nh-meeting-sheet]')?.classList.add('is-open');
        panel.querySelector('[data-nh-meeting-title]')?.focus();
        return;
    }
    if (item.native === 'participation') {
        if (window.GroupChat?.sessionParticipation?.showAdmin) {
            window.GroupChat.sessionParticipation.showAdmin();
        } else {
            const trigger = document.querySelector('[data-session-admin-open]');
            trigger?.click();
        }
        return;
    }
    if (item.command) sendManagementMessage(panel, widget, item.command, item.title);
}

function bindMeetingForm(panel, widget) {
    const sheet = panel.querySelector('[data-nh-meeting-sheet]');
    panel.querySelector('[data-nh-close-meeting]')?.addEventListener('click', () => sheet?.classList.remove('is-open'));
    panel.querySelector('[data-nh-submit-meeting]')?.addEventListener('click', () => {
        const title = panel.querySelector('[data-nh-meeting-title]')?.value?.trim() || '';
        const subject = panel.querySelector('[data-nh-meeting-subject]')?.value?.trim() || '';
        const agenda = panel.querySelector('[data-nh-meeting-agenda]')?.value?.trim() || '';
        const time = panel.querySelector('[data-nh-meeting-time]')?.value?.trim() || '';
        if (!title || !time) {
            appendActivity(panel, 'assistant', 'برای تنظیم نشست، حداقل «عنوان» و «زمان» را وارد کنید.');
            return;
        }
        const command = [
            'جلسه رسمی تنظیم کن',
            `عنوان: ${title}`,
            subject ? `موضوع: ${subject}` : null,
            agenda ? `دستور جلسه: ${agenda}` : null,
            `زمان: ${time}`,
        ].filter(Boolean).join(' | ');
        sheet?.classList.remove('is-open');
        sendManagementMessage(panel, widget, command, 'تنظیم نشست');
    });
}

function appendActivity(panel, role, content, confirmation = false, widget = null) {
    const stream = panel.querySelector('[data-nh-activity]');
    if (!stream) return;
    stream.querySelector('.nh-activity-empty')?.remove();
    const row = document.createElement('div');
    row.className = `nh-activity-message ${role === 'user' ? 'is-user' : 'is-assistant'}`;
    row.innerHTML = formatReply(content);

    if (confirmation && widget) {
        const controls = document.createElement('div');
        controls.className = 'nh-confirm-row';
        const confirm = document.createElement('button');
        confirm.type = 'button';
        confirm.className = 'nh-confirm-btn is-confirm';
        confirm.innerHTML = '<i class="fas fa-check"></i> تأیید';
        const cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'nh-confirm-btn is-cancel';
        cancel.innerHTML = '<i class="fas fa-times"></i> لغو';
        confirm.addEventListener('click', () => {
            controls.remove();
            sendManagementMessage(panel, widget, 'تأیید', 'تأیید عملیات');
        });
        cancel.addEventListener('click', () => {
            controls.remove();
            sendManagementMessage(panel, widget, 'لغو', 'لغو عملیات');
        });
        controls.append(confirm, cancel);
        row.appendChild(controls);
    }

    stream.appendChild(row);
    stream.scrollTop = stream.scrollHeight;
}

async function sendManagementMessage(panel, widget, message, displayLabel = null) {
    if (panel.dataset.busy === '1') return;
    panel.dataset.busy = '1';
    appendActivity(panel, 'user', displayLabel || message);
    const loading = document.createElement('div');
    loading.className = 'nh-activity-message is-assistant';
    loading.innerHTML = '<span class="nh-panel-spinner"></span> نجم هدا در حال بررسی است...';
    panel.querySelector('[data-nh-activity]')?.appendChild(loading);

    try {
        const token = localStorage.getItem('api_token') || '';
        const response = await fetch('/api/najm-hoda/chat', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                ...(token ? { Authorization: `Bearer ${token}` } : {}),
            },
            body: JSON.stringify({
                message,
                agent: document.getElementById('najm-hoda-agent')?.value || 'steward',
                conversation_id: widget.conversationId,
                context: { page: managementPageContext(widget) },
            }),
        });
        const data = await response.json().catch(() => ({}));
        loading.remove();
        if (!response.ok || !data.success) {
            appendActivity(panel, 'assistant', data.message || 'اجرای این خدمت با خطا مواجه شد.');
            return;
        }
        widget.conversationId = Number(data.conversation_id) || widget.conversationId;
        if (widget.conversationId) localStorage.setItem('najm-hoda-active-conversation-id', String(widget.conversationId));
        const awaiting = looksAwaitingConfirmation(data.message);
        appendActivity(panel, 'assistant', data.message || 'انجام شد.', awaiting, widget);
        refreshSessionState(panel);
    } catch (error) {
        loading.remove();
        appendActivity(panel, 'assistant', 'ارتباط با نجم هدا برقرار نشد. دوباره تلاش کنید.');
        console.error('Najm Hoda management command failed:', error);
    } finally {
        panel.dataset.busy = '0';
    }
}

async function refreshSessionState(panel) {
    const config = window.GroupChatConfig || {};
    const sessionChip = panel.querySelector('[data-nh-session-chip]');
    const pendingChip = panel.querySelector('[data-nh-pending-chip]');
    if (!config.participationStateUrl) return;

    try {
        const response = await fetch(config.participationStateUrl, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        });
        const data = await response.json();
        if (!response.ok) return;
        if (sessionChip) sessionChip.textContent = data.session ? `نشست فعال: ${data.session.title || 'نشست رسمی'}` : 'نشست فعال: ندارد';
        if (pendingChip) pendingChip.textContent = `درخواست مشارکت: ${Number(data.pending_requests_count || 0)}`;
    } catch (error) {
        if (sessionChip) sessionChip.textContent = 'وضعیت نشست در دسترس نیست';
    }
}

function installManagementConsole(widget) {
    if (!widget || widget.__managementConsoleInstalled || !canManageCurrentGroup()) return;
    const container = document.getElementById('najm-hoda-chat-container');
    const header = container?.querySelector('.najm-hoda-header');
    const messages = document.getElementById('najm-hoda-messages');
    const typing = document.getElementById('najm-hoda-typing');
    const footer = container?.querySelector('.najm-hoda-footer');
    if (!container || !header || !messages || !footer) return;

    widget.__managementConsoleInstalled = true;
    addStyles();
    createManagementPanel(container, widget);
    createTabs(container, header, messages, typing, footer);

    window.addEventListener('group-chat:session-state', () => {
        const panel = document.getElementById(MANAGEMENT_PANEL_ID);
        if (panel) refreshSessionState(panel);
    });
}

function waitForDependencies() {
    let attempts = 0;
    const tryInstall = () => {
        attempts += 1;
        if (window.NajmHoda && canManageCurrentGroup()) {
            installManagementConsole(window.NajmHoda);
            return true;
        }
        return false;
    };

    if (tryInstall()) return;
    const timer = window.setInterval(() => {
        if (tryInstall() || attempts > 120) window.clearInterval(timer);
    }, 75);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', waitForDependencies, { once: true });
} else {
    waitForDependencies();
}
