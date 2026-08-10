export function createComposer({ api, store, lifecycle, actions }) {
    const escape = value => {
        const element = document.createElement('div');
        element.textContent = String(value || '');
        return element.innerHTML;
    };
    const cancelReply = () => {
        const container = document.getElementById('reply-indicator-container');
        if (container) {
            container.replaceChildren();
            container.style.display = 'none';
        }
        const input = document.getElementById('parent_id');
        if (input) input.value = '';
        store.setState({ composerReply: null });
        return true;
    };
    const setReply = ({ id, sender = '', content = '' }) => {
        const messageId = String(id || '').trim();
        const container = document.getElementById('reply-indicator-container');
        const input = document.getElementById('parent_id');
        if (!messageId || !container || !input) return false;
        const normalizedSender = String(sender || 'کاربر').trim() || 'کاربر';
        const preview = String(content || '').replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim().slice(0, 120);
        container.innerHTML = `<div class="reply-info"><div class="reply-arrow"></div><div style="flex:1;min-width:0"><div class="reply-sender-name">${escape(normalizedSender)}</div><div class="reply-content">${escape(preview)}</div></div></div><button type="button" class="btn-cancel-reply" data-legacy-chat-action="cancel-reply"><i class="fas fa-times" aria-hidden="true"></i></button>`;
        container.style.display = 'block';
        input.value = messageId;
        store.setState({ composerReply: Object.freeze({ id: messageId, sender: normalizedSender, content: preview }) });
        document.getElementById('chatForm')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return true;
    };
    const replyFromTarget = target => {
        const bubble = target?.closest('.message-bubble');
        const row = target?.closest('.message-row');
        return setReply({
            id: target?.dataset.messageId || bubble?.dataset.messageId || row?.dataset.messageId,
            sender: bubble?.querySelector('.message-sender')?.textContent || (bubble?.classList.contains('you') ? 'شما' : 'کاربر'),
            content: bubble?.dataset.contentRaw || bubble?.querySelector('.message-content')?.textContent || '',
        });
    };
    const setMenuOpen = open => {
        const menu = document.getElementById('createMenu');
        if (menu) menu.style.display = open ? 'block' : 'none';
        store.setState({ composerMenuOpen: Boolean(open) });
    };
    const setModal = (type, open) => {
        const modal = document.getElementById(type === 'post' ? 'postFormBox' : 'pollOptionsBox');
        if (!modal) return false;
        modal.style.setProperty('display', open ? 'flex' : 'none', 'important');
        if (open) {
            const back = document.getElementById('back');
            if (back) back.style.display = 'none';
        }
        store.setState({ composerModal: open ? type : null });
        return true;
    };
    const openPost = () => setModal('post', true);
    const closePost = () => setModal('post', false);
    const openPoll = () => setModal('poll', true);
    const closePoll = () => setModal('poll', false);

    actions.register('open-blog', openPost);
    actions.register('open-poll', openPoll);
    actions.register('close-post-modal', closePost);
    actions.register('close-poll-modal', closePoll);
    actions.register('reply', ({ target }) => replyFromTarget(target));
    actions.register('cancel-reply', cancelReply);
    actions.register('reply-content', ({ target }) => setReply({ id: target.dataset.replyTarget, content: target.dataset.replyText || '' }));

    const plusButton = document.getElementById('chatCreateToggle');
    const menu = document.getElementById('createMenu');
    const wrapper = plusButton?.closest('.telegram-attach-btn-wrapper');
    const textarea = document.getElementById('message_editor');
    const resize = () => {
        if (!textarea?.scrollHeight) return;
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    };
    if (textarea) lifecycle.on(textarea, 'input', resize);
    if (plusButton) lifecycle.on(plusButton, 'click', event => {
        event.preventDefault();
        event.stopPropagation();
        setMenuOpen(store.getState().composerMenuOpen !== true);
    });
    lifecycle.on(document, 'click', event => {
        const modal = event.target.closest?.('[data-composer-modal]');
        if (modal && event.target === modal) setModal(modal.dataset.composerModal, false);
        if (menu && !wrapper?.contains(event.target) && !menu.contains(event.target)) setMenuOpen(false);
    });
    lifecycle.on(document, 'keydown', event => {
        if (event.key !== 'Escape') return;
        setMenuOpen(false);
        closePost();
        closePoll();
        cancelReply();
    });
    const audioTrigger = document.getElementById('audio-upload-trigger');
    if (audioTrigger) lifecycle.on(audioTrigger, 'click', event => {
        event.preventDefault();
        setMenuOpen(false);
        document.getElementById('voice-file-input')?.click();
    });
    const postTrigger = document.getElementById('create-post-btn');
    if (postTrigger) lifecycle.on(postTrigger, 'click', event => {
        event.preventDefault();
        setMenuOpen(false);
        openPost();
    });
    const pollTrigger = document.getElementById('create-poll-btn');
    if (pollTrigger) lifecycle.on(pollTrigger, 'click', event => {
        event.preventDefault();
        setMenuOpen(false);
        openPoll();
    });
    lifecycle.add(() => {
        setMenuOpen(false);
        closePost();
        closePoll();
        if (textarea) textarea.style.height = '';
    });

    return Object.freeze({
        async submit(url, body, options = {}) {
            store.setState({ composerStatus: 'sending', composerError: null });
            try {
                const data = await api.json(url, { method: 'POST', body, ...options });
                store.setState({ composerStatus: 'idle' });
                return data;
            } catch (error) {
                store.setState({ composerStatus: 'error', composerError: error });
                throw error;
            }
        },
        openPost,
        closePost,
        openPoll,
        closePoll,
        setReply,
        cancelReply,
    });
}
