export function createTyping({ store, lifecycle, authUserId }) {
    let cleanupTimer = null;
    const indicator = () => {
        const feed = document.getElementById('chat-box') || document.getElementById('group-feed');
        if (!feed) return null;
        let element = document.getElementById('group-typing-indicator');
        if (!element) {
            element = document.createElement('div');
            element.id = 'group-typing-indicator';
            element.className = 'typing-indicator';
            element.setAttribute('role', 'status');
            element.setAttribute('aria-live', 'polite');
            feed.appendChild(element);
        }
        if (element.parentElement === feed && feed.lastElementChild !== element) feed.appendChild(element);
        return element;
    };
    const render = state => {
        const element = indicator();
        if (!element) return;
        const names = Object.values(state.typingUsers || {}).filter(Boolean);
        element.hidden = names.length === 0;
        element.textContent = names.length === 0 ? ''
            : names.length === 1 ? `${names[0]} در حال تایپ...`
                : names.length === 2 ? `${names[0]} و ${names[1]} در حال تایپ...`
                    : `${names[0]} و ${names.length - 1} نفر دیگر در حال تایپ...`;
    };
    const clear = () => {
        store.setState({ typingUsers: Object.freeze({}) });
        cleanupTimer = null;
    };
    const apply = payload => {
        const id = payload?.user_id || payload?.id;
        if (!id || Number(id) === Number(authUserId)) return false;
        store.setState(state => {
            const users = { ...(state.typingUsers || {}) };
            if (payload.is_typing === false) delete users[id];
            else users[id] = payload.user_name || 'کاربر';
            return { typingUsers: Object.freeze(users) };
        });
        lifecycle.clearTimeout(cleanupTimer);
        if (payload.is_typing !== false) cleanupTimer = lifecycle.timeout(clear, 3000);
        return true;
    };
    const unsubscribe = store.subscribe((state, previous) => {
        if (state.typingUsers !== previous.typingUsers) render(state);
    });
    lifecycle.add(() => {
        unsubscribe();
        lifecycle.clearTimeout(cleanupTimer);
        document.getElementById('group-typing-indicator')?.remove();
    });
    render(store.getState());
    return Object.freeze({ apply, clear });
}
