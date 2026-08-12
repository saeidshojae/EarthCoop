export function createRealtimeRuntime({ app, groupId, authUserId, debug = false }) {
    const { api, store, lifecycle, reconciler } = app;
    const sequenceKey = `group-feed-sequence:${groupId}`;
    const deltaSyncEnabled = window.GroupChatConfig?.deltaSyncEnabled === true;
    const state = {
        initialized: false,
        connected: false,
        usingFallback: false,
        pollingStarted: false,
        lastEventAt: 0,
        lastSequence: Number(localStorage.getItem(sequenceKey) || 0),
        lastMessageId: 0,
        lastPostId: 0,
        syncingDelta: false,
        deltaRetryMs: 1000,
    };
    let messagePending = false;
    let postPending = false;
    let reconcilePending = false;
    let channel = null;
    const log = (...args) => { if (debug) console.log(...args); };
    const snapshot = () => Object.freeze({ ...state });
    const publish = () => store.setState({ realtime: snapshot(), connection: navigator.onLine === false ? 'offline' : state.connected ? 'online' : 'connecting' });
    const setHealthy = () => {
        state.connected = true;
        state.usingFallback = false;
        state.lastEventAt = Date.now();
        state.deltaRetryMs = 1000;
        publish();
    };
    const shouldPoll = () => !document.hidden && navigator.onLine !== false && (!state.initialized || state.usingFallback || !state.connected);
    const scanCursors = () => {
        document.querySelectorAll('[data-message-id]').forEach(node => {
            const id = Number(node.dataset.messageId);
            if (id > state.lastMessageId) state.lastMessageId = id;
        });
        document.querySelectorAll('[id^="blog-"]').forEach(node => {
            const id = Number(node.id.replace('blog-', ''));
            if (id > state.lastPostId) state.lastPostId = id;
        });
        publish();
    };
    const applyMessage = (message, source) => {
        const id = Number(message?.id || message?.content_id || message?.message_id);
        if (!id || document.getElementById(`msg-${id}`)) return false;
        const rendered = app.feed.apply([{ ...message, id, content_id: id, content_type: 'message' }], source)[0] || false;
        if (rendered) state.lastMessageId = Math.max(state.lastMessageId, id);
        return rendered;
    };
    const applyDelta = event => {
        if (!event) return;
        const decision = reconciler.inspect(event);
        if (decision.action === 'ignore') return;
        if (decision.action === 'sync') {
            state.usingFallback = true;
            publish();
            return;
        }
        const payload = event.payload || {};
        const type = payload.content_type;
        if (['message', 'file', 'voice'].includes(type)) applyMessage(payload, 'delta');
        else if (['post', 'poll', 'comment'].includes(type)) app.feedBridge.create(type, payload, 'delta');
        else state.usingFallback = true;
        state.lastSequence = Math.max(state.lastSequence, Number(event.sequence || 0));
        reconciler.advance(state.lastSequence);
        localStorage.setItem(sequenceKey, String(state.lastSequence));
        publish();
    };
    const syncDelta = async () => {
        if (!deltaSyncEnabled || state.syncingDelta || navigator.onLine === false) return;
        state.syncingDelta = true;
        publish();
        try {
            let hasMore = true;
            while (hasMore) {
                const data = await api.json(`/api/groups/${groupId}/feed/delta?after_sequence=${state.lastSequence}&limit=100`);
                const events = data?.events || [];
                events.forEach(applyDelta);
                hasMore = Boolean(data?.has_more) && events.length > 0;
            }
            state.deltaRetryMs = 1000;
        } catch (error) {
            state.usingFallback = true;
            if (!document.hidden && navigator.onLine !== false) {
                const delay = state.deltaRetryMs + Math.floor(Math.random() * Math.max(250, state.deltaRetryMs / 4));
                lifecycle.timeout(syncDelta, delay);
                state.deltaRetryMs = Math.min(30000, state.deltaRetryMs * 2);
            }
            if (debug) console.warn('Group delta sync failed.', error);
        } finally {
            state.syncingDelta = false;
            publish();
        }
    };
    const applyEnvelope = event => {
        if (!deltaSyncEnabled) return;
        const decision = reconciler.inspect(event, { commit: false });
        if (decision.action !== 'ignore') void syncDelta();
    };
    const applyMessageEvent = event => {
        setHealthy();
        if (event?.actor_id && Number(event.actor_id) === Number(authUserId)) return;
        if (event?.message) return void applyMessage(event.message, 'websocket');
        const payload = event?.payload || {};
        const action = event?.action || payload.action;
        if (action === 'typing') return void app.typing?.apply(payload);
        const id = payload.message_id || payload.id;
        if (id && ['edit', 'delete', 'reaction', 'mark-read'].includes(action)) app.feedBridge.mutate('message', action, { ...payload, message_id: id }, 'websocket');
    };
    const applyFeedEvent = event => {
        setHealthy();
        const payload = event?.payload || {};
        const action = event?.action || payload.action || '';
        if (['session_scheduled', 'session_started', 'session_ended', 'session_state_changed'].includes(action)) {
            if (Number(event?.actor_id) === Number(authUserId)) return;
            app.sessionState?.receive(action, payload);
            return;
        }
        if (Number(event?.actor_id) === Number(authUserId) && !action.startsWith('poll_')) return;
        if (action === 'session_participation_requested') {
            app.sessionParticipation?.receiveRequest(payload);
            return;
        }
        if (action === 'session_participation_resolved') {
            app.sessionParticipation?.receiveResolution(payload);
            return;
        }
        if (action === 'pin_updated') {
            app.pins?.apply(payload);
            return;
        }
        const match = /^(post|poll|comment)_(created|updated|deleted|reaction|read)$/.exec(action);
        if (!match) return;
        const operation = { created: 'create', updated: 'update', deleted: 'delete' }[match[2]] || match[2];
        if (operation === 'create') app.feedBridge.create(match[1], payload, 'websocket');
        else app.feedBridge.mutate(match[1], operation, payload, 'websocket');
    };
    const initialize = () => {
        if (state.initialized || !window.Echo?.private) return state.initialized;
        try {
            channel = window.Echo.private(`group.${groupId}`);
            channel.subscribed(() => {
                if (deltaSyncEnabled) void syncDelta().finally(setHealthy);
                else setHealthy();
            })
                .error(() => { state.connected = false; state.usingFallback = true; publish(); })
                .listen('.group.message.created', applyMessageEvent)
                .listen('.group.message.updated', applyMessageEvent)
                .listen('.group.feed.updated', applyFeedEvent)
                .listen('.group.realtime.event', applyEnvelope)
                .listen('.group.poll.updated', event => {
                    setHealthy();
                    const poll = event?.poll || event?.payload || {};
                    app.feedBridge.mutate('poll', 'vote', poll, 'websocket-poll');
                })
                .listen('.group.election.updated', event => {
                    setHealthy();
                    document.dispatchEvent(new CustomEvent('group-election-updated', { detail: event || {} }));
                });
            state.initialized = true;
            state.usingFallback = false;
            publish();
            return true;
        } catch (error) {
            state.usingFallback = true;
            publish();
            if (debug) console.warn('Realtime subscription failed.', error);
            return false;
        }
    };
    const pollMessages = async () => {
        if (!shouldPoll() || messagePending) return;
        messagePending = true;
        try {
            const data = await api.json(`/api/groups/${groupId}/messages?last_message_id=${state.lastMessageId || ''}`);
            (data?.messages || []).filter(item => item?.type === 'message').forEach(item => applyMessage(item, 'polling'));
            (data?.updated_messages || []).forEach(item => app.feedBridge.mutate('message', 'edit', item, 'polling'));
            (data?.deleted_message_ids || []).forEach(id => app.feedBridge.mutate('message', 'delete', { id }, 'polling'));
            state.lastMessageId = Math.max(state.lastMessageId, Number(data?.latest_message_id || 0));
            app.skillLists?.restore();
            app.polls?.refreshCountdowns();
            publish();
        } catch (error) { log('Message polling failed.', error); }
        finally { messagePending = false; }
    };
    const pollPosts = async () => {
        if (!shouldPoll() || postPending) return;
        postPending = true;
        try {
            const data = await api.json(`/api/groups/${groupId}/posts/feed?after_id=${state.lastPostId}`);
            (data?.posts || []).forEach(item => app.feedBridge.create('post', item, 'polling-fallback'));
            (data?.deleted_post_ids || []).forEach(id => app.feedBridge.mutate('post', 'delete', { id }, 'polling-fallback'));
            (data?.updated_posts || []).forEach(item => app.feedBridge.mutate('post', 'update', item, 'polling-fallback'));
            state.lastPostId = Math.max(state.lastPostId, Number(data?.latest_post_id || 0));
            publish();
        } catch (error) { log('Post polling failed.', error); }
        finally { postPending = false; }
    };
    const reconcilePosts = async () => {
        if (!shouldPoll() || reconcilePending) return;
        const ids = Array.from(document.querySelectorAll('[id^="blog-"]'), node => Number(node.id.replace('blog-', ''))).filter(Boolean);
        if (!ids.length) return;
        reconcilePending = true;
        try {
            const data = await api.json(`/api/groups/${groupId}/posts/reconcile`, {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ ids }),
            });
            (data?.deleted_ids || []).forEach(id => app.feedBridge.mutate('post', 'delete', { id }, 'reconcile-fallback'));
        } catch (error) { log('Post reconciliation failed.', error); }
        finally { reconcilePending = false; }
    };
    const startPolling = () => {
        if (state.pollingStarted) return;
        let attempts = 0;
        const begin = () => {
            attempts += 1;
            if (!window.scrollPositionRestored && attempts < 10) return lifecycle.timeout(begin, 500);
            state.pollingStarted = true;
            scanCursors();
            lifecycle.interval(pollMessages, 1000);
            lifecycle.interval(pollPosts, 3000);
            lifecycle.interval(reconcilePosts, 10000);
            publish();
        };
        lifecycle.timeout(begin, 500);
    };
    lifecycle.on(window, 'online', () => {
        state.connected = false;
        publish();
        if (deltaSyncEnabled) void syncDelta();
        else initialize();
    });
    lifecycle.on(window, 'offline', () => { state.connected = false; state.usingFallback = false; publish(); });
    lifecycle.on(document, 'visibilitychange', () => {
        if (!document.hidden && deltaSyncEnabled) void syncDelta();
    });
    lifecycle.add(() => {
        if (channel && window.Echo?.leave) window.Echo.leave(`group.${groupId}`);
    });
    scanCursors();
    const advanceMessage = id => { state.lastMessageId = Math.max(state.lastMessageId, Number(id || 0)); publish(); };
    const advancePost = id => { state.lastPostId = Math.max(state.lastPostId, Number(id || 0)); publish(); };
    return Object.freeze({ getState: snapshot, initialize, startPolling, syncDelta, advanceMessage, advancePost });
}
