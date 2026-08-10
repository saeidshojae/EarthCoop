export function createUnread({ api, store, groupId }) {
    return {
        set(counts) { store.setState({ unread: { ...counts } }); },
        async markAllRead() {
            const data = await api.json(`/api/groups/${groupId}/mark-all-read`, { method: 'POST' });
            store.setState({ unread: data?.unread || {} });
            return data;
        },
    };
}
