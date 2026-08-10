import test from 'node:test';
import assert from 'node:assert/strict';
import { createStore } from '../../../resources/js/group-chat/store.js';
import { createLifecycle } from '../../../resources/js/group-chat/lifecycle.js';
import { createReconciler } from '../../../resources/js/group-chat/realtime.js';
import { createRenderer } from '../../../resources/js/group-chat/renderer.js';
import { createFeed } from '../../../resources/js/group-chat/feed.js';

test('store publishes immutable state and can unsubscribe', () => {
    const store = createStore({ count: 0 });
    const values = [];
    const unsubscribe = store.subscribe(state => values.push(state.count));
    store.setState(state => ({ count: state.count + 1 }));
    unsubscribe();
    store.setState({ count: 2 });
    assert.deepEqual(values, [1]);
    assert.equal(store.getState().count, 2);
    assert.equal(Object.isFrozen(store.getState()), true);
});

test('lifecycle removes registered listeners exactly once', () => {
    const lifecycle = createLifecycle();
    const target = new EventTarget();
    let calls = 0;
    lifecycle.on(target, 'change', () => calls++);
    target.dispatchEvent(new Event('change'));
    lifecycle.destroy();
    lifecycle.destroy();
    target.dispatchEvent(new Event('change'));
    assert.equal(calls, 1);
});

test('lifecycle cancels pending timeouts and repeating intervals on destroy', async () => {
    const lifecycle = createLifecycle();
    let timeoutCalls = 0;
    let intervalCalls = 0;
    lifecycle.timeout(() => timeoutCalls++, 25);
    lifecycle.interval(() => intervalCalls++, 2);

    await new Promise(resolve => setTimeout(resolve, 8));
    lifecycle.destroy();
    const callsAtDestroy = intervalCalls;
    await new Promise(resolve => setTimeout(resolve, 30));

    assert.equal(timeoutCalls, 0);
    assert.equal(intervalCalls, callsAtDestroy);
});

test('lifecycle can cancel a registered interval before page destroy', async () => {
    const lifecycle = createLifecycle();
    let calls = 0;
    const id = lifecycle.interval(() => calls++, 2);
    await new Promise(resolve => setTimeout(resolve, 8));
    lifecycle.clearInterval(id);
    const callsAtCancel = calls;
    await new Promise(resolve => setTimeout(resolve, 15));
    lifecycle.destroy();

    assert.ok(callsAtCancel > 0);
    assert.equal(calls, callsAtCancel);
});

test('reconciler ignores duplicates and identifies sequence gaps', () => {
    const reconciler = createReconciler({ initialSequence: 4 });
    assert.equal(reconciler.inspect({ event_id: 'a', sequence: 5 }).action, 'apply');
    assert.equal(reconciler.inspect({ event_id: 'a', sequence: 5 }).reason, 'duplicate');
    assert.deepEqual(reconciler.inspect({ event_id: 'c', sequence: 7 }), { action: 'sync', afterSequence: 5 });
});

test('reconciler can inspect notification without consuming canonical event', () => {
    const reconciler = createReconciler({ initialSequence: 8 });
    assert.equal(reconciler.inspect({ event_id: 'notice', sequence: 9 }, { commit: false }).action, 'apply');
    assert.equal(reconciler.sequence, 8);
    assert.equal(reconciler.inspect({ event_id: 'notice', sequence: 9 }).action, 'apply');
    assert.equal(reconciler.sequence, 9);
});

test('feed routes all sources through a registered renderer', () => {
    const sources = [];
    const renderer = createRenderer();
    renderer.register('message', (item, context) => {
        sources.push(context.source);
        return { id: item.id, isConnected: true };
    });
    const store = createStore({ feedVersion: 0 });
    const feed = createFeed({ store, renderer });
    assert.equal(feed.apply([{ id: 1, content_type: 'message' }], 'polling')[0].id, 1);
    feed.apply([{ id: 2, content_type: 'message' }], 'delta');
    assert.deepEqual(sources, ['polling', 'delta']);
    assert.equal(store.getState().feedVersion, 2);
});

test('feed routes normalized mutations through the same renderer boundary', () => {
    const mutations = [];
    const renderer = createRenderer();
    renderer.register('message', {
        render: () => null,
        edit: (item, context) => mutations.push([item.content_id, context.source]),
    });
    const store = createStore({ feedVersion: 3 });
    const feed = createFeed({ store, renderer });
    assert.equal(feed.mutate({ content_type: 'message', content_id: 42, action: 'edit' }, 'websocket'), 1);
    assert.deepEqual(mutations, [[42, 'websocket']]);
    assert.equal(store.getState().feedVersion, 4);
});

test('feed uses the same boundary for post creation and poll updates', () => {
    const calls = [];
    const renderer = createRenderer();
    renderer.register('post', { render: item => (calls.push(['post', 'create', item.content_id]), { isConnected: true }) });
    renderer.register('poll', { update: item => calls.push(['poll', 'update', item.content_id]) });
    const feed = createFeed({ store: createStore(), renderer });
    feed.apply([{ content_type: 'post', content_id: 7 }], 'websocket');
    feed.mutate({ content_type: 'poll', content_id: 9, action: 'update' }, 'websocket');
    assert.deepEqual(calls, [['post', 'create', 7], ['poll', 'update', 9]]);
});

test('renderer accepts boolean legacy adapters without appending them as DOM nodes', () => {
    let appendCalls = 0;
    const renderer = createRenderer({ root: { appendChild: () => appendCalls++ } });
    renderer.register('post', () => true);
    assert.equal(renderer.render({ content_type: 'post' }), true);
    assert.equal(appendCalls, 0);
});
