const sameOriginReferrer = () => {
    if (!document.referrer) return false;

    try {
        return new URL(document.referrer).origin === window.location.origin;
    } catch {
        return false;
    }
};

const navigateBack = (fallbackUrl, event = null) => {
    event?.preventDefault?.();

    // Traverse the browser's real history stack. Unlike a server-generated
    // previous URL, this does not create a new history entry and therefore
    // cannot ping-pong between the last two pages.
    if (window.history.length > 1 && sameOriginReferrer()) {
        window.history.back();
        return;
    }

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
