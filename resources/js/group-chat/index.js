import { ApiClient } from './api-client.js';
import { createStore } from './store.js';
import { createLifecycle } from './lifecycle.js';
import { createReconciler } from './realtime.js';
import { createRenderer } from './renderer.js';
import { createComposer } from './composer.js';
import { createFeed } from './feed.js';
import { createUnread } from './unread.js';
import { createActions } from './actions.js';
import { createFeedback } from './feedback.js';

if (!window.GroupChatFeedback) {
    window.GroupChatFeedback = createFeedback();
}

const pageLifecycle = window.GroupChatLifecycle || createLifecycle();
window.GroupChatLifecycle = pageLifecycle;

if (!window.__groupChatPageLifecycleCleanupInstalled) {
    window.__groupChatPageLifecycleCleanupInstalled = true;
    pageLifecycle.on(window, 'pagehide', () => pageLifecycle.destroy(), { once: true });
}

// Action delegation is safe for both legacy and modular runtimes and must not
// depend on the migration feature flag. It is the single owner of page actions.
const pageActions = createActions({ lifecycle: pageLifecycle });

if (window.groupId) {
    const sequenceKey = `group-feed-sequence:${window.groupId}`;
    const lifecycle = pageLifecycle;
    const api = new ApiClient({ timeoutMs: Number(window.__groupChatRequestTimeoutMs || 15000) });
    const store = createStore({ connection: navigator.onLine === false ? 'offline' : 'connecting', unread: {} });
    const reconciler = createReconciler({ initialSequence: localStorage.getItem(sequenceKey) || 0 });
    const renderer = createRenderer({ root: document.getElementById('chat-box') || document.getElementById('group-feed') });
    renderer.register('message', {
        render(item) {
            if (typeof window.appendMessage !== 'function') return null;
            return window.appendMessage(item);
        },
        mutate(item, context) {
            const adapter = window.GroupChatLegacyMessageMutations?.[context.action];
            return typeof adapter === 'function' ? adapter(item) : false;
        },
    });
    ['post', 'poll', 'comment'].forEach(type => {
        renderer.register(type, {
            render(item, context) {
                const adapter = window.GroupChatLegacyFeedRenderers?.[type]?.render;
                return typeof adapter === 'function' ? adapter(item, context) : null;
            },
            mutate(item, context) {
                const adapter = window.GroupChatLegacyFeedRenderers?.[type]?.[context.action];
                return typeof adapter === 'function' ? adapter(item, context) : false;
            },
        });
    });
    const actions = pageActions;

    const app = {
        api,
        store,
        lifecycle,
        reconciler,
        renderer,
        actions,
        composer: createComposer({ api, store }),
        feed: createFeed({ store, renderer }),
        unread: createUnread({ api, store, groupId: window.groupId }),
        destroy() {
            actions.destroy();
            lifecycle.destroy();
            store.destroy();
        },
    };

    app.feed.hydrate('initial');

    lifecycle.on(window, 'online', () => store.setState({ connection: 'connecting' }));
    lifecycle.on(window, 'offline', () => store.setState({ connection: 'offline' }));
    window.GroupChat = app;
    document.dispatchEvent(new CustomEvent('group-chat:ready', { detail: app }));
}
