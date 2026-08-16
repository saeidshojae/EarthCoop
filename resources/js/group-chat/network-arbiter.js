const safeMethods = new Set(['GET', 'HEAD', 'OPTIONS']);

const state = globalThis.__earthcoopGroupNetworkState || {
    activeMutations: 0,
    backgroundControllers: new Set(),
    mutationWaiters: new Set(),
};
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
        || /\/groups\/\d+\/session-participation\/state$/.test(path);
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
        // The sync endpoint renders user-sensitive fragments per event. Large
        // batches can monopolize constrained/local PHP servers, delaying user
        // mutations until their client timeout. Keep batches short; the cursor
        // journal will continue from the returned cursor on the next pass.
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
        }

        if (mutation) beginMutation();

        let controller = null;
        let upstreamAbort = null;
        try {
            if (background) {
                controller = new AbortController();
                state.backgroundControllers.add(controller);
                const upstreamSignal = init?.signal;
                upstreamAbort = () => controller.abort(upstreamSignal?.reason);
                upstreamSignal?.addEventListener?.('abort', upstreamAbort, { once: true });
                init = { ...init, signal: controller.signal };
            }

            const requestInput = url && typeof input === 'string' ? url.toString() : input;
            return await nativeFetch(requestInput, init);
        } finally {
            if (controller) state.backgroundControllers.delete(controller);
            if (upstreamAbort) init?.signal?.removeEventListener?.('abort', upstreamAbort);
            if (mutation) endMutation();
        }
    };
}

export function groupNetworkState() {
    return {
        activeMutations: state.activeMutations,
        backgroundRequests: state.backgroundControllers.size,
    };
}
