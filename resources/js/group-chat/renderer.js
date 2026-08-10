export function createRenderer({ root, renderers = {} } = {}) {
    const registry = new Map(Object.entries(renderers));
    const render = (item, context = {}) => {
        const type = item?.content_type || item?.type;
        const renderer = registry.get(type);
        if (!renderer) return null;
        const renderItem = typeof renderer === 'function' ? renderer : renderer.render;
        if (typeof renderItem !== 'function') return null;
        const node = renderItem(item, context);
        if (node && root && typeof node === 'object' && 'nodeType' in node && !node.isConnected) root.appendChild(node);
        return node;
    };

    const mutate = (item, context = {}) => {
        const type = item?.content_type || item?.type;
        const renderer = registry.get(type);
        if (!renderer || typeof renderer === 'function') return false;
        const action = item.action || context.action;
        const handler = renderer[action] || renderer.mutate;
        return typeof handler === 'function' ? handler(item, { ...context, action }) : false;
    };

    const hydrate = (context = {}) => {
        if (!root) return [];
        return Array.from(root.querySelectorAll('[data-feed-item]')).map(node => {
            const item = {
                id: node.dataset.feedId || node.dataset.messageId || (node.id || '').replace(/^[^-]+-/, ''),
                content_type: node.dataset.feedType || (node.id.startsWith('blog-') ? 'post' : node.id.startsWith('poll-') ? 'poll' : 'message'),
                user_id: node.dataset.feedAuthorId || null,
                unread: node.dataset.feedUnread === '1',
            };
            const adapter = registry.get(item.content_type);
            if (adapter && typeof adapter !== 'function' && typeof adapter.hydrate === 'function') {
                adapter.hydrate(item, node, context);
            }
            return { item, node };
        });
    };

    return {
        render,
        mutate,
        hydrate,
        register(type, renderer) {
            registry.set(type, renderer);
            return () => registry.delete(type);
        },
        supports(type) { return registry.has(type); },
    };
}
