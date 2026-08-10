const legacyActionTargets = {
    reply: ['replyToMessageFromButton', ({ target }) => [target, target.dataset.messageId]],
    pin: ['pinMessage', ({ target }) => [target.dataset.messageId]],
    reaction: ['toggleReaction', ({ target }) => [target.dataset.messageId, target.dataset.reactionType]],
    'cancel-reply': ['cancelReply'],
    'close-report': ['closeReportBox'],
    'submit-report': ['submitReport'],
    'open-blog': ['openBlogBox'],
    'open-poll': ['openPollBox'],
    'open-election': ['openElectionBox'],
    'open-election-admin': ['openElection2Box'],
    'manage-members': ['showManageMembersModal'],
    'manage-reports': ['showManageReportsModal'],
    'group-settings': ['showGroupSettingsModal'],
    unpin: ['unpinMessage', ({ target }) => [target.dataset.messageId]],
    'close-election': ['closeElectionBox'],
    'clear-chat': ['clearChatHistory'],
    'delete-chat': ['deleteChat'],
    'report-user': ['reportUser'],
    'reply-content': ['replyToMessage', ({ target }) => [target.dataset.replyTarget, '', target.dataset.replyText || '']],
    'report-message': ['reportMessage', ({ target }) => [Number(target.dataset.messageId)]],
    'delete-message': ['deleteMessage', ({ target }) => [Number(target.dataset.messageId)]],
    'toggle-skill-list': ['toggleSkillList', ({ target }) => [Number(target.dataset.pollId)]],
    'delete-post': ['deletePost', ({ target }) => [Number(target.dataset.postId)]],
    'show-thread': ['showThread', ({ target }) => [Number(target.dataset.messageId)]],
    'cancel-add-guests': ['cancelAddGuests'],
    'cancel-manager-chat': ['cancelManagerChat'],
    'comment-menu': ['openGlobalMenu', ({ event, target }) => [event, Number(target.dataset.commentId)]],
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
    let postReactionHandler = null;

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
        backdrop?.classList.add('hidden');
        backdrop?.classList.remove('group-info-backdrop--visible');
    };
    const openGroupInfo = () => {
        const panel = document.getElementById('groupInfoPanel');
        const backdrop = document.getElementById('groupInfoBackdrop');
        if (!panel || window.innerWidth >= 1024) return;
        panel.classList.add('is-open');
        backdrop?.classList.remove('hidden');
        backdrop?.classList.add('group-info-backdrop--visible');
    };

    lifecycle.on(root, 'click', event => {
        const reactionButton = event.target.closest?.('.reaction-buttons .btn-like, .reaction-buttons .btn-dislike');
        if (reactionButton && postReactionHandler) {
            const container = reactionButton.closest('.reaction-buttons');
            if (container?.dataset.postId) {
                postReactionHandler(container.dataset.postId, reactionButton.classList.contains('btn-like') ? '1' : '0', container);
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

        const menuAction = event.target.closest?.('.action-menu__list button, .action-menu__list a');
        if (menuAction && !menuAction.classList.contains('btn-reaction')) closeAll();

        const target = event.target.closest?.('[data-group-chat-action], [data-legacy-chat-action], [data-chat-page-action]');
        if (!target || !root.contains(target)) return;
        const action = target.dataset.groupChatAction || target.dataset.legacyChatAction || target.dataset.chatPageAction;
        if (action === 'profile') return;
        if (action === 'modal-backdrop' && event.target !== target) return;
        const context = { event, target };
        const handler = handlers.get(action);
        const ownedHandler = action === 'open-group-info' ? openGroupInfo : action === 'close-group-info' ? closeGroupInfo : null;
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
    lifecycle.on(window, 'resize', () => {
        reposition();
        if (window.innerWidth >= 1024) closeGroupInfo();
    });
    lifecycle.on(root, 'scroll', reposition, true);

    return {
        register(name, handler) {
            handlers.set(name, handler);
            return () => handlers.delete(name);
        },
        setPostReactionHandler(handler) { postReactionHandler = handler; },
        closeAllActionMenus: closeAll,
        closeGroupInfo,
        destroy() {
            handlers.clear();
            postReactionHandler = null;
            closeAll();
        },
    };
}
