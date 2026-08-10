const legacyActionTargets = {
    reply: ['replyToMessageFromButton', ({ target }) => [target, target.dataset.messageId]],
    pin: ['pinMessage', ({ target }) => [target.dataset.messageId]],
    reaction: ['toggleReaction', ({ target }) => [target.dataset.messageId, target.dataset.reactionType]],
    'cancel-reply': ['cancelReply'],
    'close-search': ['closeChatSearch'],
    'close-report': ['closeReportBox'],
    'submit-report': ['submitReport'],
    'open-group-info': ['openGroupInfo'],
    'open-blog': ['openBlogBox'],
    'open-poll': ['openPollBox'],
    'open-election': ['openElectionBox'],
    'open-election-admin': ['openElection2Box'],
    'manage-members': ['showManageMembersModal'],
    'manage-reports': ['showManageReportsModal'],
    'group-settings': ['showGroupSettingsModal'],
    unpin: ['unpinMessage', ({ target }) => [target.dataset.messageId]],
    'close-election': ['closeElectionBox'],
    'close-group-info': ['closeGroupInfo'],
    'open-chat-search': ['openChatSearch'],
    'clear-chat': ['clearChatHistory'],
    'delete-chat': ['deleteChat'],
    'report-user': ['reportUser'],
    'reply-content': ['replyToMessage', ({ target }) => [target.dataset.replyTarget, '', target.dataset.replyText || '']],
    'delete-poll': ['deletePoll', ({ target }) => [Number(target.dataset.pollId), target.dataset.deleteUrl]],
    'report-message': ['reportMessage', ({ target }) => [Number(target.dataset.messageId)]],
    'delete-message': ['deleteMessage', ({ target }) => [Number(target.dataset.messageId)]],
    'toggle-skill-list': ['toggleSkillList', ({ target }) => [Number(target.dataset.pollId)]],
    'submit-vote': ['submitVote', ({ target }) => [target]],
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

export function createActions({ lifecycle, root = document }) {
    const handlers = new Map();
    lifecycle.on(root, 'click', event => {
        const target = event.target.closest?.('[data-group-chat-action], [data-legacy-chat-action], [data-chat-page-action]');
        if (!target || !root.contains(target)) return;
        const action = target.dataset.groupChatAction || target.dataset.legacyChatAction || target.dataset.chatPageAction;
        if (action === 'profile') return;
        if (action === 'modal-backdrop' && event.target !== target) return;
        const context = { event, target };
        const handler = handlers.get(action);
        const handled = handler ? handler(context) !== false : invokePageChrome(action, context) || invokeGlobal(action, context);
        if (!handled && action !== 'modal-backdrop' && action !== 'close-modal') return;
        event.preventDefault();
        if (action === 'modal-backdrop' || action === 'close-modal') {
            const close = target.dataset.modalId === 'manageMembersModal'
                ? window.closeManageMembersModal
                : window.closeManageReportsModal;
            if (typeof close === 'function') close();
        }
    });

    return {
        register(name, handler) {
            handlers.set(name, handler);
            return () => handlers.delete(name);
        },
        destroy() { handlers.clear(); },
    };
}
