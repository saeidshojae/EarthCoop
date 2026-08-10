export function createActions({ lifecycle, root = document }) {
    const handlers = new Map();
    lifecycle.on(root, 'click', event => {
        const target = event.target.closest?.('[data-group-chat-action]');
        if (!target || !root.contains(target)) return;
        const handler = handlers.get(target.dataset.groupChatAction);
        if (!handler) return;
        event.preventDefault();
        handler({ event, target });
    });

    return {
        register(name, handler) {
            handlers.set(name, handler);
            return () => handlers.delete(name);
        },
        destroy() { handlers.clear(); },
    };
}
