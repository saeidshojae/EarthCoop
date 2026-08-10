export function createLifecycle() {
    const cleanups = new Set();
    const intervalCleanups = new Map();
    let destroyed = false;

    const add = cleanup => {
        if (destroyed) cleanup();
        else cleanups.add(cleanup);
        return cleanup;
    };

    return {
        on(target, type, listener, options) {
            target.addEventListener(type, listener, options);
            add(() => target.removeEventListener(type, listener, options));
            return listener;
        },
        interval(callback, ms) {
            const id = globalThis.setInterval(callback, ms);
            const cleanup = () => {
                globalThis.clearInterval(id);
                intervalCleanups.delete(id);
                cleanups.delete(cleanup);
            };
            intervalCleanups.set(id, cleanup);
            add(cleanup);
            return id;
        },
        clearInterval(id) {
            const cleanup = intervalCleanups.get(id);
            if (cleanup) cleanup();
            else globalThis.clearInterval(id);
        },
        timeout(callback, ms) {
            let id;
            const cleanup = () => {
                globalThis.clearTimeout(id);
                cleanups.delete(cleanup);
            };
            id = globalThis.setTimeout(() => {
                cleanups.delete(cleanup);
                callback();
            }, ms);
            add(cleanup);
            return id;
        },
        add,
        destroy() {
            if (destroyed) return;
            destroyed = true;
            [...cleanups].reverse().forEach(cleanup => cleanup());
            cleanups.clear();
        },
        get destroyed() { return destroyed; },
    };
}
