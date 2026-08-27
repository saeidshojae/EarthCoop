export const CHAT_HEADER_GESTURE_THRESHOLD = 10;
const CHAT_HEADER_SCROLL_SETTLE_MS = 320;

export function classifyGroupChatHeaderGesture(delta, threshold = CHAT_HEADER_GESTURE_THRESHOLD) {
    if (!Number.isFinite(delta) || Math.abs(delta) < threshold) return 'idle';
    return delta > 0 ? 'hide' : 'show';
}

function headerInteractionIsOpen(header) {
    if (!header) return false;

    // Only top-level header controls should suspend auto-hide. Looking at every
    // descendant with x-show is incorrect because the mobile drawer contains
    // expanded accordion sections even while the drawer itself is closed.
    return Boolean(header.querySelector('[aria-expanded="true"]'));
}

export function createGroupChatHeaderController({
    header,
    content,
    body,
    win,
    threshold = CHAT_HEADER_GESTURE_THRESHOLD,
} = {}) {
    const runtimeWindow = win ?? (typeof window !== 'undefined' ? window : null);
    const runtimeDocument = runtimeWindow?.document ?? (typeof document !== 'undefined' ? document : null);
    const siteHeader = header ?? runtimeDocument?.querySelector('[data-header-context="chat"]');
    const chatContent = content ?? runtimeDocument?.querySelector('.chat-content-wrapper');
    const pageBody = body ?? runtimeDocument?.body;

    if (!runtimeWindow || !runtimeDocument || !siteHeader || !chatContent || !pageBody) {
        return Object.freeze({
            show() {},
            hide() {},
            destroy() {},
            isVisible: () => false,
        });
    }

    let destroyed = false;
    let visible = false;
    let headerHeight = 0;
    let ignoreScrollUntil = 0;
    let lastScrollY = Math.max(0, Number(runtimeWindow.scrollY || runtimeWindow.pageYOffset || 0));
    let lastTouchY = null;

    const now = () => runtimeWindow.performance?.now?.() ?? Date.now();

    const measure = () => {
        if (destroyed) return 0;
        const measured = Number(siteHeader.getBoundingClientRect?.().height || siteHeader.offsetHeight || 0);
        if (measured > 0) headerHeight = measured;
        if (headerHeight > 0) {
            pageBody.style.setProperty('--chat-site-header-height', `${headerHeight}px`);
        }
        return headerHeight;
    };

    const setOffset = value => {
        pageBody.style.setProperty('--chat-site-header-offset', `${Math.max(0, value)}px`);
    };

    const settleScrollAnchoring = () => {
        ignoreScrollUntil = now() + CHAT_HEADER_SCROLL_SETTLE_MS;
    };

    const show = () => {
        if (destroyed || visible) return;
        measure();
        visible = true;
        siteHeader.classList.add('chat-site-header-visible');
        setOffset(headerHeight);
        settleScrollAnchoring();
    };

    const hide = ({ force = false } = {}) => {
        if (destroyed || (!visible && !force)) return;
        if (!force && headerInteractionIsOpen(siteHeader)) return;
        visible = false;
        siteHeader.classList.remove('chat-site-header-visible');
        setOffset(0);
        settleScrollAnchoring();
    };

    const applyGesture = delta => {
        const action = classifyGroupChatHeaderGesture(delta, threshold);
        if (action === 'show') show();
        if (action === 'hide') hide();
    };

    const onScroll = () => {
        const currentScrollY = Math.max(0, Number(runtimeWindow.scrollY || runtimeWindow.pageYOffset || 0));
        if (now() >= ignoreScrollUntil) {
            applyGesture(currentScrollY - lastScrollY);
        }
        lastScrollY = currentScrollY;
    };

    const onWheel = event => {
        applyGesture(Number(event.deltaY || 0));
    };

    const onTouchStart = event => {
        lastTouchY = event.touches?.[0]?.clientY ?? null;
    };

    const onTouchMove = event => {
        const currentTouchY = event.touches?.[0]?.clientY;
        if (!Number.isFinite(currentTouchY) || !Number.isFinite(lastTouchY)) return;

        // Finger moving down means the content is being pulled down: reveal.
        // Finger moving up means forward conversation navigation: hide.
        applyGesture(lastTouchY - currentTouchY);
        lastTouchY = currentTouchY;
    };

    const onTouchEnd = () => {
        lastTouchY = null;
    };

    const onResize = () => {
        measure();
        if (visible) setOffset(headerHeight);
    };

    runtimeWindow.addEventListener('scroll', onScroll, { passive: true });
    runtimeWindow.addEventListener('wheel', onWheel, { passive: true });
    runtimeWindow.addEventListener('touchstart', onTouchStart, { passive: true });
    runtimeWindow.addEventListener('touchmove', onTouchMove, { passive: true });
    runtimeWindow.addEventListener('touchend', onTouchEnd, { passive: true });
    runtimeWindow.addEventListener('touchcancel', onTouchEnd, { passive: true });
    runtimeWindow.addEventListener('resize', onResize, { passive: true });

    measure();
    hide({ force: true });

    return Object.freeze({
        show,
        hide,
        isVisible: () => visible,
        destroy() {
            if (destroyed) return;
            destroyed = true;
            runtimeWindow.removeEventListener('scroll', onScroll);
            runtimeWindow.removeEventListener('wheel', onWheel);
            runtimeWindow.removeEventListener('touchstart', onTouchStart);
            runtimeWindow.removeEventListener('touchmove', onTouchMove);
            runtimeWindow.removeEventListener('touchend', onTouchEnd);
            runtimeWindow.removeEventListener('touchcancel', onTouchEnd);
            runtimeWindow.removeEventListener('resize', onResize);
            siteHeader.classList.remove('chat-site-header-visible');
            setOffset(0);
        },
    });
}
