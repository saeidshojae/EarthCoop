const widget = document.getElementById('najm-hoda-widget');
const panel = document.getElementById('najm-hoda-chat-container');
const toggle = document.getElementById('najm-hoda-toggle');
const messages = document.getElementById('najm-hoda-messages');

if (widget && panel && toggle) {
    const PANEL_GAP = 12;
    const COMPOSER_GAP = 10;
    let composerObserver = null;
    let observedComposer = null;
    let scheduledFrame = null;

    const viewportMetrics = () => {
        const viewport = window.visualViewport;
        return {
            top: viewport?.offsetTop ?? 0,
            height: viewport?.height ?? window.innerHeight,
            bottom: (viewport?.offsetTop ?? 0) + (viewport?.height ?? window.innerHeight),
        };
    };

    const currentComposer = () => {
        const composer = document.querySelector('.telegram-style-input')
            || document.querySelector('.chat-composer-shell--restricted');
        if (!composer) return null;

        const rect = composer.getBoundingClientRect();
        const viewport = viewportMetrics();
        const visible = rect.width > 0
            && rect.height > 0
            && rect.bottom > viewport.top
            && rect.top < viewport.bottom;

        return visible ? composer : null;
    };

    const defaultLauncherBottom = () => {
        if (window.matchMedia('(max-width: 480px)').matches) return 86;
        if (window.matchMedia('(max-width: 768px)').matches) return 78;
        return 20;
    };

    const syncComposerObserver = () => {
        if (typeof ResizeObserver === 'undefined') return;
        const composer = currentComposer();
        if (composer === observedComposer) return;

        composerObserver?.disconnect();
        observedComposer = composer;
        if (!composer) return;

        composerObserver = new ResizeObserver(() => scheduleLayout());
        composerObserver.observe(composer);
    };

    const applyLayout = () => {
        scheduledFrame = null;
        syncComposerObserver();

        const viewport = viewportMetrics();
        const compact = window.matchMedia('(max-width: 768px)').matches;
        const narrow = window.matchMedia('(max-width: 480px)').matches;
        const composer = currentComposer();
        const composerRect = composer?.getBoundingClientRect() ?? null;

        let launcherBottom = defaultLauncherBottom();
        if (compact && composerRect) {
            const composerClearance = Math.max(0, viewport.bottom - composerRect.top) + COMPOSER_GAP;
            launcherBottom = Math.max(launcherBottom, composerClearance);
        }

        widget.style.setProperty('bottom', `${Math.round(launcherBottom)}px`, 'important');
        widget.style.setProperty('right', `${narrow ? 12 : compact ? 14 : 20}px`, 'important');

        const toggleRect = toggle.getBoundingClientRect();
        const panelRight = narrow ? 10 : compact ? 15 : Math.max(20, window.innerWidth - toggleRect.right);
        let panelBottom = toggleRect.top - PANEL_GAP;

        if (composerRect) {
            panelBottom = Math.min(panelBottom, composerRect.top - PANEL_GAP);
        }
        panelBottom = Math.min(panelBottom, viewport.bottom - PANEL_GAP);

        const safeTop = viewport.top + PANEL_GAP;
        const availableHeight = Math.max(1, panelBottom - safeTop);
        const desiredHeight = compact ? availableHeight : Math.min(620, availableHeight);
        const panelTop = compact ? safeTop : Math.max(safeTop, panelBottom - desiredHeight);

        panel.style.setProperty('position', 'fixed', 'important');
        panel.style.setProperty('top', `max(${Math.round(panelTop)}px, env(safe-area-inset-top))`, 'important');
        panel.style.setProperty('right', `${Math.round(panelRight)}px`, 'important');
        panel.style.setProperty('bottom', 'auto', 'important');
        panel.style.setProperty('height', `${Math.max(1, Math.round(desiredHeight))}px`, 'important');
        panel.style.setProperty('max-height', `${Math.max(1, Math.round(desiredHeight))}px`, 'important');
        messages?.style.setProperty('min-height', '0');
    };

    const scheduleLayout = () => {
        if (scheduledFrame !== null) return;
        scheduledFrame = window.requestAnimationFrame(applyLayout);
    };

    window.addEventListener('resize', scheduleLayout, { passive: true });
    window.addEventListener('orientationchange', scheduleLayout, { passive: true });
    window.visualViewport?.addEventListener('resize', scheduleLayout, { passive: true });
    window.visualViewport?.addEventListener('scroll', scheduleLayout, { passive: true });
    document.addEventListener('group-chat:composer-replaced', () => {
        observedComposer = null;
        syncComposerObserver();
        scheduleLayout();
    });

    toggle.addEventListener('click', scheduleLayout);
    document.getElementById('najm-hoda-close')?.addEventListener('click', scheduleLayout);

    scheduleLayout();
}
