import "./bootstrap";
import "./site-navigation-history.js";
import "./location-selector.js";
import "./location-geolocation.js";
import "./registration-location-ux.js";
import "./registration-settlement-bridge.js";
import $ from "jquery";
import installSelect2 from "select2";

const profileEditUsesLegacyJQuery = window.location.pathname.replace(/\/+$/, '') === '/profile/edit';
const appJQuery = profileEditUsesLegacyJQuery ? $ : (window.jQuery || $);

// The profile editor still loads a legacy jQuery bundle for mature profile widgets.
// Keep Select2 on the Vite-owned jQuery instance on that page. The Temporal picker
// owns and binds its own imported jQuery inside temporal-input.js and must not depend
// on whichever legacy global jQuery happens to be active.
window.$ = appJQuery;
window.jQuery = appJQuery;
installSelect2(window, appJQuery);

if (appJQuery.fn.select2?.defaults) {
    appJQuery.fn.select2.defaults.set("language", { noResults: () => "نتیجه‌ای یافت نشد", searching: () => "در حال جستجو..." });
}

const importFeature = (loader, label) => { void loader().catch((error) => { console.warn(`EarthCoop ${label} runtime could not be loaded:`, error); }); };

const loadNajmHodaRuntime = () => {
    if (document.querySelector('#najm-hoda-widget')) {
        importFeature(() => import("./najm-hoda-widget-layout.js"), "Najm Hoda widget layout");
        importFeature(() => import("./najm-hoda-context.js"), "Najm Hoda continuity");
    }

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
    if (hasNajmBaharSidebar) importFeature(() => import("./najm-bahar-dashboard-mobile.js"), "Najm Bahar mobile UX");
    if (hasMembershipFeeUi) importFeature(() => import("./najm-bahar-membership-source.js"), "Najm Bahar membership source");
    if (hasReputationConversion) importFeature(() => import("./najm-bahar-conversion-idempotency.js"), "Najm Bahar conversion idempotency");
};

const loadNajmBaharAdminRuntime = () => {
    const path = window.location.pathname.replace(/\/+$/, '') || '/';
    if (!['/admin/najm-bahar/dashboard', '/admin/najm-bahar/settings'].includes(path)) return;
    importFeature(() => import("./najm-bahar-admin-settings.js"), "Najm Bahar admin settings");
};
const loadPrivateMessagingRuntime = () => { if (document.querySelector('[data-private-conversation]')) { importFeature(() => import("./private-messaging-read-receipts.js"), "private messaging read receipts"); importFeature(() => import("./private-messaging-reaction-picker.js"), "private messaging reaction picker"); } };
const loadMyParticipationRuntime = () => { if (document.querySelector('#tab-posts, #tab-comments, #tab-replies, #tab-reactions, #tab-polls, #tab-votes')) importFeature(() => import("./my-participation-mobile.js"), "My Participation mobile UX"); };
const loadSwiperRuntime = () => { if (document.querySelector('swiper-container')) importFeature(async () => { const { register } = await import("swiper/element/bundle"); register(); }, "Swiper"); };
const loadMemberInvitationRuntime = () => { if (document.querySelector('.invite-page-shell')) importFeature(() => import("./member-invitation-share.js"), "member invitation sharing"); };
const loadProjectFormMobileRuntime = () => { const path = window.location.pathname.replace(/\/+$/, '') || '/'; if (path === '/najm-bahar/projects/create' || /^\/najm-bahar\/projects\/[^/]+\/edit$/.test(path)) importFeature(() => import("./project-form-mobile.js"), "project form mobile UX"); };
const loadTemporalInputRuntime = () => {
    const hasTemporalInputs = Boolean(document.querySelector('[data-temporal-date-input], [data-temporal-datetime-input], [data-temporal-date], [data-temporal-datetime], select[name="birth_date[]"]'));
    if (hasTemporalInputs) importFeature(() => import("./temporal-input.js"), "temporal input");
};
const loadPageScopedRuntime = () => { loadNajmHodaRuntime(); loadNajmBaharRuntime(); loadNajmBaharAdminRuntime(); loadPrivateMessagingRuntime(); loadMyParticipationRuntime(); loadSwiperRuntime(); loadMemberInvitationRuntime(); loadProjectFormMobileRuntime(); loadTemporalInputRuntime(); };
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', loadPageScopedRuntime, { once: true }); else loadPageScopedRuntime();

const localDevelopmentHost = ['localhost', '127.0.0.1', '::1'].includes(window.location.hostname);
if ('serviceWorker' in navigator && localDevelopmentHost) {
    window.addEventListener('load', async () => { try { const registrations = await navigator.serviceWorker.getRegistrations(); await Promise.all(registrations.map(registration => registration.unregister())); const keys = await caches?.keys?.() || []; await Promise.all(keys.filter(key => key.startsWith('earthcoop-')).map(key => caches.delete(key))); } catch (error) { console.warn('EarthCoop local service worker cleanup failed:', error); } });
} else if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => { navigator.serviceWorker.register('/sw.js').catch((error) => { console.warn('EarthCoop service worker registration failed:', error); }); });
}

(() => {
    const banner = document.querySelector('[data-pwa-install-banner]');
    if (!banner) return;

    let deferredInstallPrompt = null;
    const installButton = banner.querySelector('[data-pwa-install-button]');
    const dismissButton = banner.querySelector('[data-pwa-dismiss-button]');
    const closeButton = banner.querySelector('[data-pwa-close-button]');
    const description = banner.querySelector('#pwa-install-description');
    const triggers = Array.from(document.querySelectorAll('[data-pwa-install-trigger]'));
    const defaultDescription = description?.textContent?.trim() || '';
    const dismissKey = 'earthcoop-pwa-dismissed-at';
    const dismissCooldown = 7 * 24 * 60 * 60 * 1000;
    const userAgent = window.navigator.userAgent || '';
    const isIos = /iphone|ipad|ipod/i.test(userAgent)
        || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);

    const isStandalone = () => {
        const displayModeStandalone = typeof window.matchMedia === 'function'
            && window.matchMedia('(display-mode: standalone)').matches;
        return displayModeStandalone || window.navigator.standalone === true;
    };

    const dismissedAt = () => {
        try {
            return Number(window.localStorage.getItem(dismissKey) || 0);
        } catch (error) {
            return 0;
        }
    };

    const rememberDismissal = () => {
        try {
            window.localStorage.setItem(dismissKey, String(Date.now()));
        } catch (error) {
            // Install access remains available even when browser storage is blocked.
        }
    };

    const canAutoShow = () => {
        const lastDismissal = dismissedAt();
        return !isStandalone() && (!lastDismissal || Date.now() - lastDismissal >= dismissCooldown);
    };

    const setDescription = (message) => {
        if (description && message) description.textContent = message;
    };

    const hideBanner = (remember = false) => {
        banner.classList.add('hidden');
        if (remember) rememberDismissal();
    };

    const showManualInstructions = () => {
        const message = isIos
            ? (banner.dataset.iosMessage || banner.dataset.manualMessage || defaultDescription)
            : (banner.dataset.manualMessage || defaultDescription);
        setDescription(message);
        installButton?.classList.add('hidden');
        banner.classList.remove('hidden');
    };

    const openInstallPanel = () => {
        if (isStandalone()) return;

        if (deferredInstallPrompt) {
            setDescription(defaultDescription);
            installButton?.classList.remove('hidden');
            banner.classList.remove('hidden');
            return;
        }

        showManualInstructions();
    };

    const showAutomaticPrompt = () => {
        if (!canAutoShow()) return;

        if (deferredInstallPrompt) {
            setDescription(defaultDescription);
            installButton?.classList.remove('hidden');
            banner.classList.remove('hidden');
            return;
        }

        if (isIos) showManualInstructions();
    };

    const syncInstallTriggers = () => {
        const installed = isStandalone();
        triggers.forEach((trigger) => {
            trigger.classList.toggle('hidden', installed);
            trigger.setAttribute('aria-hidden', String(installed));
            trigger.tabIndex = installed ? -1 : 0;
        });
    };

    syncInstallTriggers();
    installButton?.classList.toggle('hidden', !deferredInstallPrompt);
    triggers.forEach((trigger) => trigger.addEventListener('click', openInstallPanel));

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredInstallPrompt = event;

        const panelAlreadyOpen = !banner.classList.contains('hidden');
        if (panelAlreadyOpen || canAutoShow()) {
            setDescription(defaultDescription);
            installButton?.classList.remove('hidden');
            banner.classList.remove('hidden');
        }
    });

    if (isIos && canAutoShow()) {
        window.setTimeout(showAutomaticPrompt, 1800);
    }

    installButton?.addEventListener('click', async () => {
        if (!deferredInstallPrompt) {
            showManualInstructions();
            return;
        }

        const promptEvent = deferredInstallPrompt;
        installButton.disabled = true;

        try {
            await promptEvent.prompt();
            const choice = await promptEvent.userChoice;
            deferredInstallPrompt = null;
            installButton.disabled = false;
            installButton.classList.add('hidden');
            hideBanner(choice?.outcome === 'dismissed');
        } catch (error) {
            deferredInstallPrompt = null;
            installButton.disabled = false;
            showManualInstructions();
        }
    });

    dismissButton?.addEventListener('click', () => hideBanner(true));
    closeButton?.addEventListener('click', () => hideBanner(true));

    window.addEventListener('appinstalled', () => {
        deferredInstallPrompt = null;
        hideBanner(false);
        syncInstallTriggers();
    });
})();
