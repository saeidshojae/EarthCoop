export const CHAT_HEADER_GESTURE_THRESHOLD = 10;

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
    hero,
    body,
    win,
    threshold = CHAT_HEADER_GESTURE_THRESHOLD,
} = {}) {
    const runtimeWindow = win ?? (typeof window !== 'undefined' ? window : null);
    const runtimeDocument = runtimeWindow?.document ?? (typeof document !== 'undefined' ? document : null);
    const siteHeader = header ?? runtimeDocument?.querySelector('[data-header-context="chat"]');
    const chatContent = content ?? runtimeDocument?.querySelector('.chat-content-wrapper');
    const gestureSurface = hero ?? runtimeDocument?.querySelector('[data-group-hero]');
    const pageBody = body ?? runtimeDocument?.body;

    if (!runtimeWindow || !runtimeDocument || !siteHeader || !chatContent || !gestureSurface || !pageBody) {
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
    let lastTouchY = null;

    const previousHeroInline = {
        position: gestureSurface.style.position,
        top: gestureSurface.style.top,
        zIndex: gestureSurface.style.zIndex,
        transition: gestureSurface.style.transition,
    };

    // The group hero is the persistent chat chrome. Conversation scrolling must
    // happen beneath it, while the optional global header may temporarily sit
    // above it when explicitly revealed from the hero itself.
    gestureSurface.style.setProperty('position', 'sticky');
    gestureSurface.style.setProperty('top', 'var(--chat-site-header-offset, 0px)');
    gestureSurface.style.setProperty('z-index', '950');
    gestureSurface.style.setProperty('transition', 'top .25s cubic-bezier(.4, 0, .2, 1)');

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

    const show = () => {
        if (destroyed || visible) return;
        measure();
        visible = true;
        siteHeader.classList.add('chat-site-header-visible');
        setOffset(headerHeight);
    };

    const hide = ({ force = false } = {}) => {
        if (destroyed || (!visible && !force)) return;
        if (!force && headerInteractionIsOpen(siteHeader)) return;
        visible = false;
        siteHeader.classList.remove('chat-site-header-visible');
        setOffset(0);
    };

    const applyGesture = delta => {
        const action = classifyGroupChatHeaderGesture(delta, threshold);
        if (action === 'show') show();
        if (action === 'hide') hide();
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

        // Gesture scope is intentionally the sticky group hero only.
        // Finger down reveals the global header; finger up hides it again.
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

    gestureSurface.addEventListener('wheel', onWheel, { passive: true });
    gestureSurface.addEventListener('touchstart', onTouchStart, { passive: true });
    gestureSurface.addEventListener('touchmove', onTouchMove, { passive: true });
    gestureSurface.addEventListener('touchend', onTouchEnd, { passive: true });
    gestureSurface.addEventListener('touchcancel', onTouchEnd, { passive: true });
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
            gestureSurface.removeEventListener('wheel', onWheel);
            gestureSurface.removeEventListener('touchstart', onTouchStart);
            gestureSurface.removeEventListener('touchmove', onTouchMove);
            gestureSurface.removeEventListener('touchend', onTouchEnd);
            gestureSurface.removeEventListener('touchcancel', onTouchEnd);
            runtimeWindow.removeEventListener('resize', onResize);
            siteHeader.classList.remove('chat-site-header-visible');
            setOffset(0);
            gestureSurface.style.position = previousHeroInline.position;
            gestureSurface.style.top = previousHeroInline.top;
            gestureSurface.style.zIndex = previousHeroInline.zIndex;
            gestureSurface.style.transition = previousHeroInline.transition;
        },
    });
}
