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

    return {
        render,
        mutate,
        register(type, renderer) {
            registry.set(type, renderer);
            return () => registry.delete(type);
        },
        supports(type) { return registry.has(type); },
    };
}
