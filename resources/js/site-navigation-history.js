const NAVIGATION_STACK_KEY = 'earthcoop.navigation.stack';
const NAVIGATION_STACK_LIMIT = 50;

const normalizeUrl = (value) => {
    try {
        const url = new URL(value, window.location.origin);
        return url.origin === window.location.origin ? url.href : null;
    } catch {
        return null;
    }
};

const readStack = () => {
    try {
        const parsed = JSON.parse(window.sessionStorage.getItem(NAVIGATION_STACK_KEY) || '[]');
        if (!Array.isArray(parsed)) return [];
        return parsed.map(normalizeUrl).filter(Boolean).slice(-NAVIGATION_STACK_LIMIT);
    } catch {
        return [];
    }
};

const writeStack = (stack) => {
    try {
        window.sessionStorage.setItem(NAVIGATION_STACK_KEY, JSON.stringify(stack.slice(-NAVIGATION_STACK_LIMIT)));
    } catch {
        // sessionStorage can be unavailable in restrictive/private contexts.
    }
};

const currentNavigationType = () => {
    try {
        return window.performance.getEntriesByType('navigation')[0]?.type || 'navigate';
    } catch {
        return 'navigate';
    }
};

const registerCurrentPage = () => {
    const current = normalizeUrl(window.location.href);
    if (!current) return;

    const stack = readStack();
    const navigationType = currentNavigationType();

    if (navigationType === 'back_forward') {
        const existingIndex = stack.lastIndexOf(current);
        if (existingIndex >= 0) {
            writeStack(stack.slice(0, existingIndex + 1));
            return;
        }
    }

    if (stack[stack.length - 1] !== current) {
        stack.push(current);
        writeStack(stack);
    }
};

const navigateBack = (fallbackUrl, event = null) => {
    event?.preventDefault?.();

    const stack = readStack();
    const current = normalizeUrl(window.location.href);

    if (current && stack[stack.length - 1] === current && stack.length > 1) {
        stack.pop();
        const previousUrl = stack[stack.length - 1];
        writeStack(stack);
        window.location.assign(previousUrl);
        return;
    }

    // Direct entry/new tab or lost stack: deterministic safe fallback.
    window.location.assign(fallbackUrl);
};

const installUnifiedHeaderBackNavigation = () => {
    registerCurrentPage();

    const header = document.querySelector('header.site-header-unified');
    if (!header) return;

    const controls = header.querySelectorAll('[data-earthcoop-history-back="true"]');
    if (!controls.length) return;

    const fallbackUrl = controls[0].getAttribute('href') || new URL('/home', window.location.origin).href;
    window.earthcoopNavigateBack = (event = null) => navigateBack(fallbackUrl, event);

    controls.forEach((control) => {
        control.addEventListener('click', (event) => navigateBack(fallbackUrl, event));
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', installUnifiedHeaderBackNavigation, { once: true });
} else {
    installUnifiedHeaderBackNavigation();
}
