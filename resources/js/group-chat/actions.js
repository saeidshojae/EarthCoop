const legacyActionTargets = {
    reaction: ['toggleReaction', ({ target }) => [target.dataset.messageId, target.dataset.reactionType]],
    'manage-members': ['showManageMembersModal'],
    'manage-reports': ['showManageReportsModal'],
    'group-settings': ['showGroupSettingsModal'],
    'show-thread': ['showThread', ({ target }) => [Number(target.dataset.messageId)]],
    'comment-menu': ['openGlobalMenu', ({ event, target }) => [{
        stopPropagation: () => event.stopPropagation(),
        currentTarget: target,
        target: event.target,
    }, Number(target.dataset.commentId)]],
    'comment-reaction': ['reactToComment', ({ target }) => [target.dataset.reactionType, Number(target.dataset.commentId)]],
};

function invokeGlobal(action, context) {
    const [name, argsFactory = () => []] = legacyActionTargets[action] || [];
    const handler = name && window[name];
    if (typeof handler !== 'function') return false;
    handler(...argsFactory(context));
    return true;
}

function invokePageChrome(action, context) {
    const methods = {
        'open-group-edit': 'openGroupEdit',
        'cancel-group-edit': 'cancelGroupEdit',
        'toggle-group-hero': 'toggleGroupHero',
        'edit-poll': 'showEditPollBox',
    };
    const method = methods[action];
    const handler = method && window.GroupChatPageChrome?.[method];
    if (typeof handler !== 'function') return false;
    handler(...(action === 'edit-poll' ? [Number(context.target.dataset.pollId)] : []));
    return true;
}

function invokeSearch(action) {
    const method = action === 'open-chat-search' ? 'open' : action === 'close-search' ? 'close' : null;
    const handler = method && window.GroupChatSearch?.[method];
    if (typeof handler !== 'function') return false;
    handler();
    return true;
}

export function createActions({ lifecycle, root = document }) {
    const handlers = new Map();
    let lastGroupInfoTrigger = null;

    const reactToPost = async (blogId, type, container) => {
        const api = window.GroupChat?.api;
        if (!api) return false;
        try {
            const data = await api.json(`/blogs/${blogId}/react`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type }),
            });
            if (data?.status && data.status !== 'success') {
                window.GroupChatFeedback?.toast(data.message || 'خطا در ثبت واکنش', { type: 'error' });
                return false;
            }
            container.querySelector('.like-count')?.replaceChildren(String(data.likes ?? 0));
            container.querySelector('.dislike-count')?.replaceChildren(String(data.dislikes ?? 0));
            const likeButton = container.querySelector('.btn-like');
            const dislikeButton = container.querySelector('.btn-dislike');
            const activeButton = type === '1' ? likeButton : dislikeButton;
            const inactiveButton = type === '1' ? dislikeButton : likeButton;
            activeButton?.classList.toggle('active');
            inactiveButton?.classList.remove('active');
            return true;
        } catch {
            window.GroupChatFeedback?.toast('❌ خطا در ارتباط با سرور', { type: 'error' });
            return false;
        }
    };

    const positionMenu = menu => {
        const list = menu?.querySelector('.action-menu__list');
        if (!list) return;
        const bounds = document.getElementById('chat-box')?.getBoundingClientRect()
            || { left: 0, top: 0, right: window.innerWidth, bottom: window.innerHeight };
        const margin = 8;
        list.style.left = '';
        list.style.right = '';
        list.style.transform = '';
        list.style.maxWidth = '';
        menu.classList.remove('open-down');
        let rect = list.getBoundingClientRect();
        if (rect.top < bounds.top + margin) {
            menu.classList.add('open-down');
            rect = list.getBoundingClientRect();
        }
        const minLeft = bounds.left + margin;
        const maxRight = bounds.right - margin;
        const maxWidth = Math.max(160, maxRight - minLeft);
        if (rect.width > maxWidth) {
            list.style.maxWidth = `${Math.floor(maxWidth)}px`;
            rect = list.getBoundingClientRect();
        }
        const offset = (rect.left < minLeft ? minLeft - rect.left : 0)
            - (rect.right > maxRight ? rect.right - maxRight : 0);
        if (offset) list.style.transform = `translateX(${Math.round(offset)}px)`;
    };
    const closeAll = () => root.querySelectorAll('[data-action-menu].is-open').forEach(menu => {
        menu.classList.remove('is-open');
        menu.querySelector('.action-menu__toggle')?.setAttribute('aria-expanded', 'false');
    });
    const reposition = () => root.querySelectorAll('[data-action-menu].is-open').forEach(positionMenu);
    const closeGroupInfo = () => {
        const panel = document.getElementById('groupInfoPanel');
        const backdrop = document.getElementById('groupInfoBackdrop');
        panel?.classList.remove('is-open');
        panel?.setAttribute('aria-hidden', 'true');
        backdrop?.classList.add('hidden');
        backdrop?.classList.remove('group-info-backdrop--visible');
        const trigger = lastGroupInfoTrigger;
        lastGroupInfoTrigger = null;
        if (trigger?.isConnected) requestAnimationFrame(() => trigger.focus?.());
    };
    const openGroupInfo = () => {
        const panel = document.getElementById('groupInfoPanel');
        const backdrop = document.getElementById('groupInfoBackdrop');
        if (!panel) return;
        if (!lastGroupInfoTrigger) lastGroupInfoTrigger = document.activeElement;
        panel.classList.add('is-open');
        panel.setAttribute('aria-hidden', 'false');
        backdrop?.classList.remove('hidden');
        backdrop?.classList.add('group-info-backdrop--visible');
        requestAnimationFrame(() => panel.querySelector('#exitNavbar, button, a, input, select, textarea')?.focus?.());
    };

    lifecycle.on(root, 'click', event => {
        const reactionButton = event.target.closest?.('.reaction-buttons .btn-like, .reaction-buttons .btn-dislike');
        if (reactionButton) {
            const container = reactionButton.closest('.reaction-buttons');
            if (container?.dataset.postId) {
                void reactToPost(container.dataset.postId, reactionButton.classList.contains('btn-like') ? '1' : '0', container);
            }
            return;
        }

        const toggle = event.target.closest?.('.action-menu__toggle');
        const menu = toggle?.closest('[data-action-menu]');
        if (toggle && menu) {
            event.preventDefault();
            event.stopPropagation();
            const isOpen = menu.classList.contains('is-open');
            closeAll();
            menu.classList.toggle('is-open', !isOpen);
            toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            if (!isOpen) requestAnimationFrame(() => positionMenu(menu));
            return;
        }

        if (!event.target.closest?.('[data-action-menu].is-open')) closeAll();

        const menuAction = event.target.closest?.('.action-menu__list button, .action-menu__list a');
        if (menuAction && !menuAction.classList.contains('btn-reaction')) closeAll();

        const target = event.target.closest?.('[data-group-chat-action], [data-legacy-chat-action], [data-chat-page-action]');
        if (!target || !root.contains(target)) return;
        const action = target.dataset.groupChatAction || target.dataset.legacyChatAction || target.dataset.chatPageAction;
        if (action === 'profile') return;
        if (action === 'modal-backdrop' && event.target !== target) return;
        const context = { event, target };
        const handler = handlers.get(action);
        const ownedHandler = action === 'open-group-info'
            ? () => {
                lastGroupInfoTrigger = target || document.activeElement;
                openGroupInfo();
            }
            : action === 'close-group-info'
                ? closeGroupInfo
                : null;
        const handled = handler ? handler(context) !== false : ownedHandler ? (ownedHandler(), true) : invokePageChrome(action, context) || invokeSearch(action) || invokeGlobal(action, context);
        if (!handled && action !== 'modal-backdrop' && action !== 'close-modal') return;
        event.preventDefault();
        if (action === 'modal-backdrop' || action === 'close-modal') {
            const close = target.dataset.modalId === 'manageMembersModal'
                ? window.closeManageMembersModal
                : window.closeManageReportsModal;
            if (typeof close === 'function') close();
        }
    });
    lifecycle.on(root, 'keydown', event => {
        if (event.key !== 'Escape') return;
        closeAll();
        closeGroupInfo();
    });
    const groupInfoBackdrop = document.getElementById('groupInfoBackdrop');
    if (groupInfoBackdrop) lifecycle.on(groupInfoBackdrop, 'click', closeGroupInfo);
    lifecycle.on(window, 'resize', reposition);
    lifecycle.on(root, 'scroll', reposition, true);

    return {
        register(name, handler) {
            handlers.set(name, handler);
            return () => handlers.delete(name);
        },
        closeAllActionMenus: closeAll,
        closeGroupInfo,
        destroy() {
            handlers.clear();
            closeAll();
        },
    };
}
