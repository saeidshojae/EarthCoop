const hasInternalReferrer = () => {
    if (!document.referrer) return false;

    try {
        return new URL(document.referrer, window.location.href).origin === window.location.origin;
    } catch {
        return false;
    }
};

const navigateBack = (fallbackUrl, event = null) => {
    if (!hasInternalReferrer()) {
        window.location.assign(fallbackUrl);
        return;
    }

    event?.preventDefault?.();
    window.history.back();
};

const installUnifiedHeaderBackNavigation = () => {
    const controls = document.querySelectorAll('[data-earthcoop-history-back="true"]');
    if (!controls.length) return;

    // public/js/dark-mode.js installs the critical non-Vite handler first on
    // Welcome and unified pages. Reuse it instead of binding a second listener.
    if (typeof window.earthcoopNavigateBack === 'function') {
        return;
    }

    const fallbackUrl = controls[0].getAttribute('href') || new URL('/home', window.location.origin).href;
    window.earthcoopNavigateBack = (event = null) => navigateBack(fallbackUrl, event);

    controls.forEach((control) => {
        if (control.dataset.earthcoopBackBound === 'true') return;
        control.dataset.earthcoopBackBound = 'true';
        control.addEventListener('click', window.earthcoopNavigateBack);
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', installUnifiedHeaderBackNavigation, { once: true });
} else {
    installUnifiedHeaderBackNavigation();
}
