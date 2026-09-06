import "./bootstrap";
import "./site-navigation-history.js";
import $ from "jquery";
import installSelect2 from "select2";

const appJQuery = window.jQuery || $;
window.$ = appJQuery;
window.jQuery = appJQuery;
installSelect2(window, appJQuery);

if (appJQuery.fn.select2?.defaults) {
    appJQuery.fn.select2.defaults.set("language", { noResults: () => "نتیجه‌ای یافت نشد", searching: () => "در حال جستجو..." });
}

const importFeature = (loader, label) => { void loader().catch((error) => { console.warn(`EarthCoop ${label} runtime could not be loaded:`, error); }); };

const loadNajmHodaRuntime = () => {
    if (document.querySelector('#najm-hoda-widget')) importFeature(() => import("./najm-hoda-context.js"), "Najm Hoda continuity");

    if (/^\/groups\/\d+\/najm-hoda\/panel\/?$/.test(window.location.pathname)) {
        importFeature(() => import("./najm-hoda-attention-panel.js"), "Najm Hoda attention panel");
    }

    if (/^\/groups\/\d+(?:\/chat)?\/?$/.test(window.location.pathname) && document.querySelector('#group-chat-main-container')) {
        importFeature(() => import("./najm-hoda-management-console-v2.js"), "Najm Hoda management console");
        importFeature(() => import("./najm-hoda-management-content-tools.js"), "Najm Hoda management content tools");
        importFeature(() => import("./najm-hoda-management-native-tools.js"), "Najm Hoda management native tools");
        importFeature(() => import("./najm-hoda-management-live-attention.js"), "Najm Hoda management live attention");
    }
};

const loadNajmBaharRuntime = () => {
    const onNajmBaharPage = window.location.pathname.startsWith('/najm-bahar');
    const hasMembershipFeeUi = Boolean(document.querySelector('#membershipFeeModal, #payMembershipForm'));
    const hasNajmBaharSidebar = Boolean(document.querySelector('#najm-bahar-sidebar'));
    const hasReputationConversion = Boolean(document.querySelector('form#conversionForm'));
    if (!onNajmBaharPage && !hasMembershipFeeUi && !hasNajmBaharSidebar && !hasReputationConversion) return;
    if (onNajmBaharPage) importFeature(() => import("./najm-bahar.js"), "Najm Bahar");
    if (hasNajmBaharSidebar) {
        importFeature(() => import("./najm-bahar-dashboard-mobile.js"), "Najm Bahar mobile UX");
    }
    if (hasMembershipFeeUi) importFeature(() => import("./najm-bahar-membership-source.js"), "Najm Bahar membership source");
    if (hasReputationConversion) importFeature(() => import("./najm-bahar-conversion-idempotency.js"), "Najm Bahar conversion idempotency");
};

const loadPrivateMessagingRuntime = () => {
    if (!document.querySelector('[data-private-conversation]')) return;
    importFeature(() => import("./private-messaging-read-receipts.js"), "private messaging read receipts");
    importFeature(() => import("./private-messaging-reaction-picker.js"), "private messaging reaction picker");
};

const loadMyParticipationRuntime = () => {
    if (!document.querySelector('#tab-posts, #tab-comments, #tab-replies, #tab-reactions, #tab-polls, #tab-votes')) return;
    importFeature(() => import("./my-participation-mobile.js"), "My Participation mobile UX");
};

const loadSwiperRuntime = () => {
    if (!document.querySelector('swiper-container')) return;
    importFeature(async () => { const { register } = await import("swiper/element/bundle"); register(); }, "Swiper");
};

const loadPageScopedRuntime = () => {
    loadNajmHodaRuntime();
    loadNajmBaharRuntime();
    loadPrivateMessagingRuntime();
    loadMyParticipationRuntime();
    loadSwiperRuntime();
};
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', loadPageScopedRuntime, { once: true }); else loadPageScopedRuntime();

const localDevelopmentHost = ['localhost', '127.0.0.1', '::1'].includes(window.location.hostname);
if ('serviceWorker' in navigator && localDevelopmentHost) {
    window.addEventListener('load', async () => {
        try { const registrations = await navigator.serviceWorker.getRegistrations(); await Promise.all(registrations.map(registration => registration.unregister())); const keys = await caches?.keys?.() || []; await Promise.all(keys.filter(key => key.startsWith('earthcoop-')).map(key => caches.delete(key))); }
        catch (error) { console.warn('EarthCoop local service worker cleanup failed:', error); }
    });
} else if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => { navigator.serviceWorker.register('/sw.js').catch((error) => { console.warn('EarthCoop service worker registration failed:', error); }); });
}

(() => {
    let deferredInstallPrompt = null; const banner = document.querySelector('[data-pwa-install-banner]'); if (!banner) return;
    const installButton = banner.querySelector('[data-pwa-install-button]'); const dismissButton = banner.querySelector('[data-pwa-dismiss-button]'); const closeButton = banner.querySelector('[data-pwa-close-button]'); const description = banner.querySelector('#pwa-install-description'); const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true; const isIos = /iphone|ipad|ipod/i.test(window.navigator.userAgent); const dismissedAt = Number(localStorage.getItem('earthcoop-pwa-dismissed-at') || 0); const dismissCooldown = 7 * 24 * 60 * 60 * 1000; const canShow = !isStandalone && (!dismissedAt || Date.now() - dismissedAt > dismissCooldown);
    const showBanner = () => { if (canShow) banner.classList.remove('hidden'); }; const hideBanner = (remember = false) => { banner.classList.add('hidden'); if (remember) localStorage.setItem('earthcoop-pwa-dismissed-at', String(Date.now())); };
    window.addEventListener('beforeinstallprompt', event => { event.preventDefault(); deferredInstallPrompt = event; showBanner(); });
    if (isIos && canShow) { installButton?.classList.add('hidden'); if (description && banner.dataset.iosMessage) description.textContent = banner.dataset.iosMessage; window.setTimeout(showBanner, 1800); }
    installButton?.addEventListener('click', async () => { if (!deferredInstallPrompt) return; deferredInstallPrompt.prompt(); await deferredInstallPrompt.userChoice; deferredInstallPrompt = null; hideBanner(true); });
    dismissButton?.addEventListener('click', () => hideBanner(true)); closeButton?.addEventListener('click', () => hideBanner(true)); window.addEventListener('appinstalled', () => hideBanner(true));
})();
