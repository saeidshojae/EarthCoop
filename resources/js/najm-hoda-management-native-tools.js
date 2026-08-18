const PANEL_ID = 'najm-hoda-management-panel-v2';

const canManage = () => Boolean(
    window.groupId
    && window.GroupChatConfig?.canManageSession
    && [2, 3].includes(Number(window.GroupChatConfig?.yourRole))
);

const isManager = () => Number(window.GroupChatConfig?.yourRole) === 3;

function toast(message, type = 'info') {
    if (window.GroupChatFeedback?.toast) {
        window.GroupChatFeedback.toast(message, { type });
    }
}

function card(key, icon, title, description) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'nh-mgmt-card';
    button.dataset.key = key;
    button.innerHTML = `
        <span class="nh-mgmt-icon"><i class="fas ${icon}"></i></span>
        <div class="nh-mgmt-card-title">${title}</div>
        <div class="nh-mgmt-card-desc">${description}</div>
    `;
    return button;
}

function invoke(action, fallbackSelector = null) {
    try {
        if (action()) return true;
    } catch (error) {
        console.warn('Najm Hoda native management action failed:', error);
    }

    const fallback = fallbackSelector ? document.querySelector(fallbackSelector) : null;
    if (fallback) {
        fallback.click();
        return true;
    }

    toast('این ابزار در صفحه فعلی در دسترس نیست.', 'info');
    return false;
}

function openIntraGroupElectionAdmin() {
    return invoke(
        () => {
            const handler = window.GroupChat?.elections?.openAdmin;
            if (typeof handler !== 'function') return false;
            handler();
            return true;
        },
        '[data-chat-page-action="open-election-admin"]'
    );
}

function openElectionManagementTab() {
    const electionTab = document.querySelector('#groupInfoPanel [data-tab="election"], #groupInfoPanel .tab[data-tab="election"]');
    if (!electionTab) {
        toast('بخش انتخابات درون‌گروهی در این گروه در دسترس نیست.', 'info');
        return false;
    }

    if (window.innerWidth < 1024) {
        const infoTrigger = document.querySelector('[data-group-chat-action="open-group-info"]');
        infoTrigger?.click();
    }

    electionTab.click();
    return true;
}

function install() {
    if (!canManage()) return false;

    const panel = document.getElementById(PANEL_ID);
    const host = panel?.querySelector('[data-nh-sections]');
    if (!panel || !host || panel.dataset.nativeToolsInstalled === '1') return false;

    panel.dataset.nativeToolsInstalled = '1';

    // The older content-tools card pointed to the cooperative/system election
    // overlay. System elections choose managers/inspectors and are deliberately
    // outside this console scope for now.
    panel.querySelector('[data-content-tools-grid] [data-key="election"]')?.remove();

    const section = document.createElement('section');
    section.className = 'nh-mgmt-section';
    section.innerHTML = `
        <div class="nh-mgmt-section-head">مدیریت و حکمرانی گروه</div>
        <div class="nh-mgmt-grid" data-native-management-grid></div>
    `;

    const grid = section.querySelector('[data-native-management-grid]');
    const items = [
        ['intra-election-create', 'fa-square-poll-vertical', 'ایجاد انتخابات درون‌گروهی', 'عنوان، نوع، مدت رأی‌گیری و نامزدها را با فرم اصلی گروه ثبت کنید.', openIntraGroupElectionAdmin],
        ['intra-election-manage', 'fa-list-check', 'مدیریت انتخابات درون‌گروهی', 'فهرست و وضعیت انتخابات ایجادشده داخل همین گروه را ببینید.', openElectionManagementTab],
        ['group-edit', 'fa-pen-to-square', 'ویرایش گروه', 'اطلاعات و تنظیمات قابل ویرایش گروه را از پنل اصلی باز کنید.', () => invoke(
            () => {
                const handler = window.GroupChatPageChrome?.openGroupEdit;
                if (typeof handler !== 'function') return false;
                handler();
                return true;
            },
            '[data-chat-page-action="open-group-edit"]'
        )],
        ['guest-add', 'fa-user-plus', 'افزودن مهمان', 'فرایند موجود افزودن کاربر مهمان به گروه را باز کنید.', () => invoke(() => false, '#addUserButton')],
        ['manager-chat-request', 'fa-comments', 'درخواست چت مدیران', 'درخواست گفتگوی مدیریتی با مدیران گروه را از مسیر فعلی باز کنید.', () => invoke(() => false, '#addChatRequestButton')],
        ['members-manage', 'fa-users-gear', 'مدیریت اعضا', 'پنل موجود مدیریت اعضا و نقش‌ها را باز کنید.', () => invoke(
            () => {
                if (typeof window.showManageMembersModal !== 'function') return false;
                window.showManageMembersModal();
                return true;
            },
            '[data-group-chat-action="manage-members"]'
        )],
        ['group-settings', 'fa-gear', 'تنظیمات گروه', 'پنل تنظیمات مدیریتی موجود گروه را باز کنید.', () => invoke(
            () => {
                if (typeof window.showGroupSettingsModal !== 'function') return false;
                window.showGroupSettingsModal();
                return true;
            },
            '[data-group-chat-action="group-settings"]'
        )],
    ];

    if (isManager()) {
        items.push([
            'reports-manage',
            'fa-flag',
            'گزارش‌ها و رسیدگی',
            'پنل گزارش‌های مدیریتی گروه را باز کنید.',
            () => invoke(
                () => {
                    if (typeof window.showManageReportsModal !== 'function') return false;
                    window.showManageReportsModal();
                    return true;
                },
                '[data-group-chat-action="manage-reports"]'
            ),
        ]);
    }

    items.forEach(([key, icon, title, description, handler]) => {
        const button = card(key, icon, title, description);
        button.addEventListener('click', handler);
        grid.appendChild(button);
    });

    host.appendChild(section);
    return true;
}

let attempts = 0;
const timer = window.setInterval(() => {
    attempts += 1;
    if (install() || attempts > 160) window.clearInterval(timer);
}, 75);

if (document.readyState !== 'loading') install();
