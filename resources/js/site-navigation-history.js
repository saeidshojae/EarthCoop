const samePath = (left, right) => {
    try {
        const a = new URL(left, window.location.origin);
        const b = new URL(right, window.location.origin);
        return a.origin === b.origin && a.pathname.replace(/\/$/, '') === b.pathname.replace(/\/$/, '');
    } catch {
        return false;
    }
};

const sameOriginReferrer = () => {
    if (!document.referrer) return false;

    try {
        return new URL(document.referrer).origin === window.location.origin;
    } catch {
        return false;
    }
};

const resolveFallbackUrl = (header) => {
    const brand = header?.querySelector('.site-header-mobile-brand');
    return brand?.href || new URL('/home', window.location.origin).href;
};

const navigateBack = (fallbackUrl, event = null) => {
    event?.preventDefault?.();

    // A real browser-history traversal preserves A → B → C → B → A.
    // We deliberately avoid server-side "previous URL" links because clicking
    // those creates a new navigation entry and can ping-pong between two pages.
    if (window.history.length > 1 && sameOriginReferrer()) {
        window.history.back();
        return;
    }

    window.location.assign(fallbackUrl);
};

const installUnifiedHeaderBackNavigation = () => {
    const header = document.querySelector('header.site-header-unified');
    if (!header) return;

    const fallbackUrl = resolveFallbackUrl(header);
    const isFallbackPage = samePath(window.location.href, fallbackUrl);

    window.earthcoopNavigateBack = (event = null) => navigateBack(fallbackUrl, event);

    // Desktop legacy markup already contains a back anchor on non-home pages.
    // Convert it to browser-history behavior and make Home the safe no-JS href.
    const desktopBack = header.querySelector('a[aria-label="بازگشت"]');
    if (desktopBack) {
        desktopBack.href = fallbackUrl;
        desktopBack.dataset.earthcoopHistoryBack = 'true';
        desktopBack.addEventListener('click', (event) => navigateBack(fallbackUrl, event));
    }

    if (isFallbackPage) return;

    const mobileBar = header.querySelector('.site-header-mobile-bar');
    if (!mobileBar || mobileBar.querySelector('.site-header-mobile-back')) return;

    const mobileBack = document.createElement('button');
    mobileBack.type = 'button';
    mobileBack.className = 'site-header-mobile-back';
    mobileBack.dataset.earthcoopHistoryBack = 'true';
    mobileBack.setAttribute('aria-label', 'بازگشت');
    mobileBack.setAttribute('title', 'بازگشت');
    mobileBack.innerHTML = '<i class="fas fa-arrow-left" aria-hidden="true"></i>';
    mobileBack.addEventListener('click', (event) => navigateBack(fallbackUrl, event));

    mobileBar.appendChild(mobileBack);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', installUnifiedHeaderBackNavigation, { once: true });
} else {
    installUnifiedHeaderBackNavigation();
}
