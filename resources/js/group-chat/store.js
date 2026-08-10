export function createStore(initialState = {}) {
    let state = Object.freeze({ ...initialState });
    const subscribers = new Set();

    return {
        getState: () => state,
        setState(update) {
            const patch = typeof update === 'function' ? update(state) : update;
            if (!patch || typeof patch !== 'object') return state;
            const previous = state;
            state = Object.freeze({ ...state, ...patch });
            subscribers.forEach(listener => listener(state, previous));
            return state;
        },
        subscribe(listener) {
            subscribers.add(listener);
            return () => subscribers.delete(listener);
        },
        destroy() {
            subscribers.clear();
        },
    };
}
