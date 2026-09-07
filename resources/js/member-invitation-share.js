const shareMenuId = 'inviteShareMenu';
let activeInviteCode = null;
let returnFocusElement = null;

function getInviteMessage(code) {
    if (typeof window.buildInviteMessage === 'function') {
        return window.buildInviteMessage(code);
    }

    const url = `${window.location.origin}/?invite=${encodeURIComponent(code)}`;
    return `من به EarthCoop پیوسته‌ام؛ بستری برای همکاری و مشارکت از محله تا جهان.\nاگر دوست داری تو هم از اعضای نخستین باشی، با دعوت من بپیوند.\nکد دعوت: ${code}\n${url}`;
}

function getInviteUrl(code) {
    const message = getInviteMessage(code);
    const lastLine = message.trim().split('\n').pop()?.trim() || '';
    if (/^https?:\/\//i.test(lastLine)) return lastLine;
    return `${window.location.origin}/?invite=${encodeURIComponent(code)}`;
}

function showFeedback(message) {
    if (typeof window.showInviteToast === 'function') {
        window.showInviteToast(message);
        return;
    }

    const toast = document.getElementById('inviteToast');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    window.setTimeout(() => toast.classList.remove('show'), 2200);
}

async function copyText(value, successMessage) {
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(value);
        showFeedback(successMessage);
        return;
    }

    const textarea = document.createElement('textarea');
    textarea.value = value;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    textarea.remove();
    showFeedback(successMessage);
}

function injectShareMenu() {
    if (document.getElementById(shareMenuId)) return;

    const style = document.createElement('style');
    style.textContent = `
        .invite-share-layer {
            position: fixed;
            inset: 0;
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: rgba(15, 23, 42, .46);
            backdrop-filter: blur(4px);
        }
        .invite-share-layer.show { display: flex; }
        .invite-share-dialog {
            width: min(100%, 28rem);
            max-height: calc(100vh - 2rem);
            overflow-y: auto;
            border-radius: 1.5rem;
            background: #fff;
            box-shadow: 0 24px 70px rgba(15, 23, 42, .28);
        }
    `;
    document.head.appendChild(style);

    const layer = document.createElement('div');
    layer.innerHTML = `
        <div id="inviteShareMenu" class="invite-share-layer" role="dialog" aria-modal="true" aria-labelledby="inviteShareTitle" aria-hidden="true">
            <div class="invite-share-dialog p-5 sm:p-6" dir="rtl">
                <div class="flex items-start justify-between gap-4 mb-5">
                    <div>
                        <h2 id="inviteShareTitle" class="text-xl font-extrabold text-slate-900 mb-1">اشتراک دعوت</h2>
                        <p class="text-sm leading-6 text-slate-500 mb-0">روش مناسب برای فرستادن دعوت را انتخاب کنید.</p>
                    </div>
                    <button type="button" data-invite-share-close class="w-10 h-10 rounded-full border border-slate-200 bg-white text-slate-500 flex items-center justify-center cursor-pointer shrink-0" aria-label="بستن">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-4">
                    <button type="button" data-invite-share-whatsapp class="rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-4 font-bold flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fab fa-whatsapp text-xl" aria-hidden="true"></i> واتساپ
                    </button>
                    <button type="button" data-invite-share-telegram class="rounded-2xl border border-sky-200 bg-sky-50 text-sky-800 px-4 py-4 font-bold flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fab fa-telegram-plane text-xl" aria-hidden="true"></i> تلگرام
                    </button>
                </div>

                <div class="space-y-2">
                    <button type="button" data-invite-share-copy-message class="w-full rounded-full border border-slate-300 bg-white text-slate-700 px-5 py-3.5 font-bold flex items-center justify-center gap-2 cursor-pointer">
                        <i class="far fa-copy" aria-hidden="true"></i> کپی متن کامل دعوت
                    </button>
                    <button type="button" data-invite-share-copy-link class="w-full rounded-full border border-slate-300 bg-white text-slate-700 px-5 py-3.5 font-bold flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fas fa-link" aria-hidden="true"></i> کپی لینک دعوت
                    </button>
                    <button type="button" data-invite-share-system class="hidden w-full rounded-full bg-slate-900 text-white px-5 py-3.5 font-bold items-center justify-center gap-2 cursor-pointer border-0">
                        <i class="fas fa-share-alt" aria-hidden="true"></i> اشتراک از طریق سیستم
                    </button>
                </div>
            </div>
        </div>
    `;

    const menu = layer.firstElementChild;
    document.body.appendChild(menu);

    menu.addEventListener('click', (event) => {
        if (event.target === menu) closeInviteShareMenu();
    });
    menu.querySelector('[data-invite-share-close]')?.addEventListener('click', closeInviteShareMenu);
    menu.querySelector('[data-invite-share-whatsapp]')?.addEventListener('click', shareInviteToWhatsApp);
    menu.querySelector('[data-invite-share-telegram]')?.addEventListener('click', shareInviteToTelegram);
    menu.querySelector('[data-invite-share-copy-message]')?.addEventListener('click', copyCurrentInviteMessage);
    menu.querySelector('[data-invite-share-copy-link]')?.addEventListener('click', copyCurrentInviteLink);
    menu.querySelector('[data-invite-share-system]')?.addEventListener('click', shareInviteViaSystem);
}

function isMobileShareContext() {
    const mobileUserAgent = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent || '');
    const compactTouchViewport = window.matchMedia('(max-width: 767px)').matches && (navigator.maxTouchPoints || 0) > 0;
    return mobileUserAgent || compactTouchViewport;
}

function openInviteShareMenu(code) {
    injectShareMenu();
    activeInviteCode = code;
    returnFocusElement = document.activeElement;

    const menu = document.getElementById(shareMenuId);
    const systemButton = menu?.querySelector('[data-invite-share-system]');
    if (!menu) return;

    if (systemButton) {
        systemButton.classList.toggle('hidden', !navigator.share);
        systemButton.classList.toggle('flex', Boolean(navigator.share));
    }

    menu.classList.add('show');
    menu.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    menu.querySelector('button')?.focus();
}

function closeInviteShareMenu() {
    const menu = document.getElementById(shareMenuId);
    if (!menu) return;
    menu.classList.remove('show');
    menu.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    returnFocusElement?.focus?.();
    returnFocusElement = null;
}

function openShareTarget(url) {
    window.open(url, '_blank', 'noopener,noreferrer');
}

function shareInviteToWhatsApp() {
    if (!activeInviteCode) return;
    openShareTarget(`https://wa.me/?text=${encodeURIComponent(getInviteMessage(activeInviteCode))}`);
    closeInviteShareMenu();
}

function shareInviteToTelegram() {
    if (!activeInviteCode) return;
    const url = getInviteUrl(activeInviteCode);
    const text = getInviteMessage(activeInviteCode).replace(url, '').trim();
    openShareTarget(`https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`);
    closeInviteShareMenu();
}

async function copyCurrentInviteMessage() {
    if (!activeInviteCode) return;
    await copyText(getInviteMessage(activeInviteCode), 'متن کامل دعوت کپی شد.');
    closeInviteShareMenu();
}

async function copyCurrentInviteLink() {
    if (!activeInviteCode) return;
    await copyText(getInviteUrl(activeInviteCode), 'لینک دعوت کپی شد.');
    closeInviteShareMenu();
}

async function shareInviteViaSystem() {
    if (!activeInviteCode || !navigator.share) return;
    try {
        await navigator.share({ title: 'دعوت به EarthCoop', text: getInviteMessage(activeInviteCode) });
        closeInviteShareMenu();
    } catch (error) {
        if (error?.name !== 'AbortError') console.warn('EarthCoop system share failed:', error);
    }
}

async function shareInviteCode(code) {
    const message = getInviteMessage(code);

    if (isMobileShareContext() && navigator.share) {
        try {
            await navigator.share({ title: 'دعوت به EarthCoop', text: message });
            return;
        } catch (error) {
            if (error?.name === 'AbortError') return;
        }
    }

    openInviteShareMenu(code);
}

window.isMobileShareContext = isMobileShareContext;
window.openInviteShareMenu = openInviteShareMenu;
window.shareInviteViaSystem = shareInviteViaSystem;
window.shareInviteCode = shareInviteCode;

injectShareMenu();

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeInviteShareMenu();
});
