export function createUnread({ api, store, feed, lifecycle, groupId }) {
    const pending = new Set();
    let intersectionObserver = null;
    let mutationObserver = null;

    const descriptorFor = element => {
        if (element.id.startsWith('blog-')) {
            const id = element.id.slice(5);
            return { key: `blog-${id}`, type: 'blog', id, url: `/blog/${id}/mark-read` };
        }
        if (element.id.startsWith('poll-')) {
            const id = element.id.slice(5);
            return { key: `poll-${id}`, type: 'poll', id, url: `/poll/${id}/mark-read` };
        }
        return null;
    };

    const markVisibleRead = async element => {
        const descriptor = descriptorFor(element);
        if (!descriptor || pending.has(descriptor.key) || element.dataset.feedUnread === '0') return;
        pending.add(descriptor.key);
        try {
            await api.json(descriptor.url, { method: 'POST' });
            element.dataset.feedUnread = '0';
            feed.markRead(descriptor.type, descriptor.id);
            window.dispatchEvent(new CustomEvent('group-feed:read-state-changed'));
            intersectionObserver?.unobserve(element);
        } catch {
            pending.delete(descriptor.key);
        }
    };

    const observeFeedItems = () => {
        document.querySelectorAll('[id^="blog-"], [id^="poll-"]').forEach(element => {
            if (element.dataset.readObserved === 'true') return;
            element.dataset.readObserved = 'true';
            intersectionObserver?.observe(element);
        });
    };

    return {
        set(counts) { store.setState({ unread: { ...counts } }); },
        async markAllRead() {
            const data = await api.json(`/api/groups/${groupId}/mark-all-read`, { method: 'POST' });
            store.setState({ unread: data?.unread || {} });
            return data;
        },
        initialize() {
            if (intersectionObserver || typeof IntersectionObserver === 'undefined') return;
            intersectionObserver = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && entry.intersectionRatio >= 0.5) markVisibleRead(entry.target);
                });
            }, { threshold: 0.5, rootMargin: '0px' });

            observeFeedItems();
            const root = document.getElementById('chat-box') || document.getElementById('group-feed');
            if (root && typeof MutationObserver !== 'undefined') {
                mutationObserver = new MutationObserver(observeFeedItems);
                mutationObserver.observe(root, { childList: true, subtree: true });
            }
            lifecycle.add(() => {
                intersectionObserver?.disconnect();
                mutationObserver?.disconnect();
                intersectionObserver = null;
                mutationObserver = null;
                pending.clear();
            });
        },
    };
}
