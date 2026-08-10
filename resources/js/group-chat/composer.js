export function createComposer({ api, store, lifecycle, actions }) {
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
    });
}
