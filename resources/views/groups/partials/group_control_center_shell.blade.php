<style>
    /* Adaptive presentation layer for the existing #groupInfoPanel.
       Content/permissions stay owned by group_info_panel; this file only owns shell geometry. */
    #groupInfoPanel.group-info-panel {
        position: fixed !important;
        z-index: 1250 !important;
        overflow: hidden !important;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity .22s ease, visibility .22s ease, transform .28s cubic-bezier(.22,.8,.25,1) !important;
    }

    #groupInfoPanel.group-info-panel.is-open {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }

    #groupInfoPanel .group-info-panel__inner {
        max-height: inherit;
        overflow-y: auto;
        overscroll-behavior: contain;
        scrollbar-gutter: stable;
        padding-bottom: max(1.5rem, env(safe-area-inset-bottom));
    }

    /* The legacy aside must not reserve a desktop column once the panel becomes an overlay. */
    #group-chat-main-container .grid:has(> aside #groupInfoPanel) {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    #group-chat-main-container aside:has(#groupInfoPanel) {
        min-width: 0;
        min-height: 0;
    }

    @media (max-width: 767px) {
        #groupInfoPanel.group-info-panel {
            top: auto !important;
            right: 0 !important;
            bottom: 0;
            left: 0;
            width: 100%;
            max-width: none !important;
            height: auto !important;
            max-height: min(90dvh, 760px) !important;
            border-radius: 28px 28px 0 0 !important;
            transform: translateY(calc(100% + 24px));
            box-shadow: 0 -24px 64px -30px rgba(15, 23, 42, .5) !important;
        }

        #groupInfoPanel.group-info-panel::before {
            content: '';
            display: block;
            width: 46px;
            height: 5px;
            margin: 9px auto 0;
            border-radius: 999px;
            background: #cbd5e1;
        }

        #groupInfoPanel.group-info-panel.is-open {
            transform: translateY(0);
        }

        #groupInfoPanel .group-info-panel__inner {
            padding-top: .75rem;
        }
    }

    @media (min-width: 768px) {
        #groupInfoPanel.group-info-panel {
            top: 50% !important;
            right: auto !important;
            bottom: auto;
            left: 50%;
            width: min(960px, calc(100vw - 3rem));
            max-width: 960px !important;
            height: auto !important;
            max-height: min(88dvh, 860px) !important;
            border-radius: 28px !important;
            transform: translate(-50%, calc(-50% + 18px));
            box-shadow: 0 30px 90px -34px rgba(15, 23, 42, .55) !important;
        }

        #groupInfoPanel.group-info-panel.is-open {
            transform: translate(-50%, -50%);
        }

        #groupInfoPanel .panel-close-btn {
            display: flex !important;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        #groupInfoPanel.group-info-panel {
            transition: none !important;
        }
    }
</style>

<script type="module">
    const panel = document.getElementById('groupInfoPanel');
    if (panel) {
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'true');
        panel.setAttribute('aria-hidden', panel.classList.contains('is-open') ? 'false' : 'true');
        panel.setAttribute('aria-labelledby', 'groupControlCenterTitle');

        const title = panel.querySelector('.panel-hero__title');
        if (title) title.id = 'groupControlCenterTitle';

        const tablist = panel.querySelector('.panel-tabs');
        if (tablist) {
            tablist.setAttribute('role', 'tablist');
            tablist.setAttribute('aria-label', 'بخش‌های پنل گروه');
        }
    }
</script>
