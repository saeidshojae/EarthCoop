const safeMethods = new Set(['GET', 'HEAD', 'OPTIONS']);

const state = globalThis.__earthcoopGroupNetworkState || {
    activeMutations: 0,
    backgroundControllers: new Set(),
    backgroundFlights: new Map(),
    mutationWaiters: new Set(),
};
if (!state.backgroundFlights) state.backgroundFlights = new Map();
globalThis.__earthcoopGroupNetworkState = state;

function urlOf(input) {
    try {
        const raw = typeof input === 'string' ? input : input?.url;
        return new URL(raw, globalThis.location?.origin || 'http://localhost');
    } catch (_) {
        return null;
    }
}

function isGroupBackground(url) {
    if (!url || globalThis.location && url.origin !== globalThis.location.origin) return false;
    const path = url.pathname;
    return /\/api\/groups\/\d+\/(sync|messages|unread-count|posts\/feed|posts\/reconcile)$/.test(path)
        || /\/groups\/\d+\/(unread-count|session-participation\/state)$/.test(path);
}

function isGroupMutation(url, method) {
    if (safeMethods.has(method) || !url) return false;
    if (globalThis.location && url.origin !== globalThis.location.origin) return false;
    return /(^|\/)(poll|messages|blog|comment)(\/|$)/.test(url.pathname)
        || /\/groups\/\d+\//.test(url.pathname);
}

function tuneBackgroundUrl(url) {
    if (!url) return url;
    if (/\/api\/groups\/\d+\/sync$/.test(url.pathname)) {
        // Rendering many event fragments in a single sync response can monopolize
        // a constrained PHP worker. Keep cursor batches intentionally small.
        const current = Number(url.searchParams.get('limit') || 0);
        if (!current || current > 12) url.searchParams.set('limit', '12');
    }
    return url;
}

function waitForMutations() {
    if (state.activeMutations === 0) return Promise.resolve();
    return new Promise(resolve => state.mutationWaiters.add(resolve));
}

function beginMutation() {
    state.activeMutations += 1;
    state.backgroundControllers.forEach(controller => controller.abort('mutation-priority'));
    state.backgroundControllers.clear();
}

function endMutation() {
    state.activeMutations = Math.max(0, state.activeMutations - 1);
    if (state.activeMutations !== 0) return;
    const waiters = Array.from(state.mutationWaiters);
    state.mutationWaiters.clear();
    waiters.forEach(resolve => resolve());
}

if (!globalThis.__earthcoopGroupNetworkArbiterInstalled && typeof globalThis.fetch === 'function') {
    globalThis.__earthcoopGroupNetworkArbiterInstalled = true;
    const nativeFetch = globalThis.fetch.bind(globalThis);

    globalThis.fetch = async function earthcoopGroupFetch(input, init = {}) {
        const method = String(init?.method || (typeof input !== 'string' ? input?.method : '') || 'GET').toUpperCase();
        let url = urlOf(input);
        const background = safeMethods.has(method) && isGroupBackground(url);
        const mutation = isGroupMutation(url, method);

        if (background) {
            await waitForMutations();
            url = tuneBackgroundUrl(url);

            // Coalesce identical concurrent background reads. Multiple runtimes
            // may legitimately ask for the same state after one DOM/realtime
            // event, but the PHP server should only execute that request once.
            const flightKey = `${method}:${url?.toString() || String(input)}`;
            const existing = state.backgroundFlights.get(flightKey);
            if (existing) {
                const response = await existing;
                return response.clone();
            }

            const controller = new AbortController();
            state.backgroundControllers.add(controller);
            const upstreamSignal = init?.signal;
            const upstreamAbort = () => controller.abort(upstreamSignal?.reason);
            upstreamSignal?.addEventListener?.('abort', upstreamAbort, { once: true });
            const requestInput = url && typeof input === 'string' ? url.toString() : input;
            const flight = nativeFetch(requestInput, { ...init, signal: controller.signal })
                .finally(() => {
                    state.backgroundFlights.delete(flightKey);
                    state.backgroundControllers.delete(controller);
                    upstreamSignal?.removeEventListener?.('abort', upstreamAbort);
                });
            state.backgroundFlights.set(flightKey, flight);
            const response = await flight;
            return response.clone();
        }

        if (mutation) beginMutation();
        try {
            return await nativeFetch(input, init);
        } finally {
            if (mutation) endMutation();
        }
    };
}

export function groupNetworkState() {
    return {
        activeMutations: state.activeMutations,
        backgroundRequests: state.backgroundControllers.size,
        backgroundFlights: state.backgroundFlights.size,
    };
}
