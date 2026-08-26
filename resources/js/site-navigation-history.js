const normalizeUrl = (value) => {
    try {
        const url = new URL(value, window.location.origin);
        return url.origin === window.location.origin ? url.href : null;
    } catch {
        return null;
    }
};

const canUseNativeBack = () => {
    try {
        // A same-origin referrer is the strongest signal that this tab arrived
        // here through EarthCoop navigation. history.length protects normal
        // multi-step navigation even when the browser omits referrer details.
        const referrer = document.referrer ? normalizeUrl(document.referrer) : null;
        return window.history.length > 1 && (Boolean(referrer) || window.history.length > 2);
    } catch {
        return false;
    }
};

const navigateBack = (fallbackUrl, event = null) => {
    event?.preventDefault?.();

    if (canUseNativeBack()) {
        window.history.back();
        return;
    }

    // Direct entry/new tab: deterministic safe fallback.
    window.location.assign(fallbackUrl);
};

const installUnifiedHeaderBackNavigation = () => {
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
