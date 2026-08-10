export function createFeed({ store, renderer }) {
    return {
        apply(items, source = 'unknown') {
            const rendered = items.map(item => renderer.render(item, { source })).filter(Boolean);
            store.setState(state => ({ feedVersion: (state.feedVersion || 0) + 1 }));
            return rendered;
        },
        mutate(item, source = 'unknown') {
            const changed = renderer.mutate(item, { source, action: item?.action });
            if (changed) store.setState(state => ({ feedVersion: (state.feedVersion || 0) + 1 }));
            return changed;
        },
    };
}
