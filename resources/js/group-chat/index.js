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
import { createPolls } from './polls.js';
import { createElections } from './elections.js';
import { createTabs } from './tabs.js';
import { createSkillLists } from './skill-lists.js';
import { installLegacyRenderers } from './legacy-renderers.js';
import { createTyping } from './typing.js';
import { createRealtimeRuntime } from './realtime-runtime.js';
import { createOperations } from './operations.js';
import { createCategoryBrowser } from './category-browser.js';

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
    const store = createStore({
        connection: navigator.onLine === false ? 'offline' : 'connecting',
        unread: {},
        lastReadMessageId: Number(window.GroupChatConfig?.lastReadMessageId) || null,
        scrollRestored: false,
    });
    const reconciler = createReconciler({ initialSequence: localStorage.getItem(sequenceKey) || 0 });
    const renderer = createRenderer({ root: document.getElementById('chat-box') || document.getElementById('group-feed') });
    const actions = pageActions;

    const feed = createFeed({ store, renderer });
    const app = {
        api,
        store,
        lifecycle,
        reconciler,
        renderer,
        actions,
        composer: createComposer({ api, store, lifecycle, actions }),
        feed,
        unread: createUnread({ api, store, feed, lifecycle, groupId: window.groupId }),
        destroy() {
            actions.destroy();
            lifecycle.destroy();
            store.destroy();
        },
    };
    app.polls = createPolls({ api, store, feed, actions, lifecycle });
    app.elections = createElections({ actions, lifecycle, store });
    app.tabs = createTabs({ store, lifecycle });
    app.skillLists = createSkillLists({ actions, store, lifecycle });
    app.installLegacyRenderers = callbacks => installLegacyRenderers({ app, callbacks });
    app.typing = createTyping({ store, lifecycle, authUserId: window.authUserId });
    app.operations = createOperations({ api, store, feed, actions, lifecycle, groupId: window.groupId });
    app.categoryBrowser = createCategoryBrowser({ api, lifecycle });
    app.unread.initialize();
    app.installRealtime = options => {
        if (!app.realtimeRuntime) app.realtimeRuntime = createRealtimeRuntime({ app, groupId: window.groupId, authUserId: window.authUserId, ...options });
        return app.realtimeRuntime;
    };

    const debug = Boolean(
        window.__groupChatDebug || window.__chatPollingDebug
        || localStorage.getItem('__groupChatDebug') === '1'
        || localStorage.getItem('__chatPollingDebug') === '1'
    );
    const realtimeRuntime = app.installRealtime({ debug });
    const feedBridge = app.installLegacyRenderers({ updateLastPostCursor: id => realtimeRuntime.advancePost(id) });
    app.composer.initializeSubmission({ feed, realtime: realtimeRuntime });
    app.composer.initializePostSubmission({ feedBridge });
    lifecycle.timeout(() => {
        realtimeRuntime.initialize();
        realtimeRuntime.startPolling();
    }, 2000);

    app.feed.hydrate('initial');

    lifecycle.on(window, 'online', () => store.setState({ connection: 'connecting' }));
    lifecycle.on(window, 'offline', () => store.setState({ connection: 'offline' }));
    window.GroupChat = app;
    document.dispatchEvent(new CustomEvent('group-chat:ready', { detail: app }));
}
