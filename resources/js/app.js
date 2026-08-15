import "bootstrap/dist/css/bootstrap.min.css";
import "../css/app.css";
import "bootstrap";
import "./bootstrap";
import $ from "jquery";
import installSelect2 from "select2";
import "select2/dist/css/select2.min.css";
import "./najm-bahar.js";
import "./najm-bahar-membership-source.js";
import "./najm-hoda-context.js";
import { register } from "swiper/element/bundle";
import "./group-chat/index.js";
import "./group-comment-form-fallback.js";

register();

// Some legacy pages load jQuery plugins before this deferred Vite module runs.
// Preserve that instance so those plugins are not detached by replacing window.$.
const appJQuery = window.jQuery || $;
window.$ = appJQuery;
window.jQuery = appJQuery;
installSelect2(window, appJQuery);

if (appJQuery.fn.select2?.defaults) {
	appJQuery.fn.select2.defaults.set("language", {
		noResults: () => "نتیجه‌ای یافت نشد",
		searching: () => "در حال جستجو...",
	});
}


// PWA registration and user-visible install prompt.
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch((error) => {
            console.warn('EarthCoop service worker registration failed:', error);
        });
    });
}

(() => {
    let deferredInstallPrompt = null;
    const banner = document.querySelector('[data-pwa-install-banner]');
    if (!banner) return;

    const installButton = banner.querySelector('[data-pwa-install-button]');
    const dismissButton = banner.querySelector('[data-pwa-dismiss-button]');
    const closeButton = banner.querySelector('[data-pwa-close-button]');
    const description = banner.querySelector('#pwa-install-description');
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const isIos = /iphone|ipad|ipod/i.test(window.navigator.userAgent);
    const dismissedAt = Number(localStorage.getItem('earthcoop-pwa-dismissed-at') || 0);
    const dismissCooldown = 7 * 24 * 60 * 60 * 1000;
    const canShow = !isStandalone && (!dismissedAt || Date.now() - dismissedAt > dismissCooldown);

    const showBanner = () => {
        if (!canShow) return;
        banner.classList.remove('hidden');
    };

    const hideBanner = (remember = false) => {
        banner.classList.add('hidden');
        if (remember) localStorage.setItem('earthcoop-pwa-dismissed-at', String(Date.now()));
    };

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredInstallPrompt = event;
        showBanner();
    });

    if (isIos && canShow) {
        installButton?.classList.add('hidden');
        if (description && banner.dataset.iosMessage) description.textContent = banner.dataset.iosMessage;
        window.setTimeout(showBanner, 1800);
    }

    installButton?.addEventListener('click', async () => {
        if (!deferredInstallPrompt) return;
        deferredInstallPrompt.prompt();
        await deferredInstallPrompt.userChoice;
        deferredInstallPrompt = null;
        hideBanner(true);
    });

    dismissButton?.addEventListener('click', () => hideBanner(true));
    closeButton?.addEventListener('click', () => hideBanner(true));
    window.addEventListener('appinstalled', () => hideBanner(true));
})();
