export function createFeed({ store, renderer }) {
    const keyFor = item => `${item?.content_type || item?.type}:${item?.id ?? item?.content_id}`;
    const commit = (items, { remove = false } = {}) => store.setState(state => {
        const feed = { ...(state.feed || {}) };
        items.forEach(item => {
            const key = keyFor(item);
            if (remove || item?.action === 'delete') delete feed[key];
            else feed[key] = Object.freeze({ ...(feed[key] || {}), ...item });
        });
        return { feed: Object.freeze(feed), feedVersion: (state.feedVersion || 0) + 1 };
    });

    return {
        hydrate(source = 'initial') {
            const entries = renderer.hydrate({ source });
            commit(entries.map(({ item }) => item));
            return entries;
        },
        apply(items, source = 'unknown') {
            const rendered = items.map(item => renderer.render(item, { source })).filter(Boolean);
            commit(items);
            return rendered;
        },
        mutate(item, source = 'unknown') {
            const changed = renderer.mutate(item, { source, action: item?.action });
            if (changed) commit([item], { remove: item?.action === 'delete' });
            return changed;
        },
        markRead(type, id) {
            commit([{ content_type: type, id, unread: false }]);
        },
    };
}
