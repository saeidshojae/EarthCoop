export function createComposer({ api, store }) {
    return {
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
    };
}
