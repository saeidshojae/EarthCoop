<style>
    /* Final visual polish for the adaptive Group Control Center.
       Capability ownership stays in group_info_panel / group_control_center_shell. */
    #groupInfoPanel .control-center-action-grid > *,
    #groupInfoPanel .control-center-action-grid--tools > * {
        min-width: 0;
    }

    #groupInfoPanel .control-center-tool-card {
        height: 100%;
    }

    /* Primary navigation is four equal, intentional cards rather than a small
       white chip floating inside a wide grey track. */
    #groupInfoPanel .control-center-tabs {
        gap: .5rem;
        padding: .42rem;
        border: 1px solid #e2e8f0;
        border-radius: 17px;
        background: #f8fafc;
    }

    #groupInfoPanel .panel-tabs .tab {
        width: 100%;
        min-height: 44px;
        padding: .62rem .55rem;
        text-align: center;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #fff;
        color: #64748b;
        box-shadow: 0 2px 8px -7px rgba(15, 23, 42, .55);
    }

    #groupInfoPanel .panel-tabs .tab.active {
        border: 1px solid #a7f3d0;
        background: #ecfdf5;
        color: #047857;
        box-shadow: 0 7px 18px -15px rgba(5, 150, 105, .8);
    }

    #groupInfoPanel .panel-tabs .tab:hover {
        border-color: #bbf7d0;
        background: #f0fdf4;
        color: #047857;
    }

    /* Keep the primary creation action visibly primary even when Bootstrap or
       legacy button rules are loaded after the panel stylesheet. */
    #groupInfoPanel .panel-action-btn--primary {
        background: #10b981 !important;
        color: #fff !important;
        border-color: #10b981 !important;
        box-shadow: 0 8px 18px -13px rgba(5, 150, 105, .9);
    }

    #groupInfoPanel .panel-action-btn--primary:hover {
        background: #059669 !important;
        border-color: #059669 !important;
        color: #fff !important;
    }

    /* Group edit now uses the same canonical fullscreen shell contract as the
       other Group Chat modals. These explicit viewport dimensions protect it
       from legacy modal rules and keep it above the Control Center surface. */
    #groupEditFormBox.modal-shell {
        position: fixed !important;
        inset: 0 !important;
        z-index: 100100 !important;
        width: 100vw !important;
        height: 100dvh !important;
        max-width: none !important;
        max-height: none !important;
        margin: 0 !important;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        direction: rtl;
        background: rgba(15, 23, 42, .52) !important;
        backdrop-filter: blur(4px);
    }

    .group-edit-modal__dialog {
        position: relative;
        width: min(560px, calc(100vw - 2rem));
        max-height: min(760px, calc(100dvh - 2rem));
        overflow-y: auto;
        padding: 1.25rem;
        border: 1px solid #dbe7e4;
        border-radius: 22px;
        background: #fff;
        box-shadow: 0 32px 90px -34px rgba(15, 23, 42, .7);
    }

    .group-edit-modal__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .group-edit-modal__eyebrow {
        display: block;
        margin-bottom: .2rem;
        color: #10b981;
        font-size: .7rem;
        font-weight: 800;
    }

    .group-edit-modal__header h2 {
        margin: 0;
        color: #0f172a;
        font-size: 1.05rem;
        font-weight: 900;
    }

    .group-edit-modal__close {
        flex: 0 0 36px;
        width: 36px;
        height: 36px;
        display: grid;
        place-items: center;
        border: 1px solid #e2e8f0;
        border-radius: 50%;
        background: #f8fafc;
        color: #475569;
    }

    .group-edit-modal label {
        display: block;
        margin-bottom: .4rem;
        color: #334155;
        font-size: .78rem;
        font-weight: 800;
    }

    .group-edit-modal__current-avatar img {
        width: 76px;
        height: 76px;
        object-fit: cover;
        border: 1px solid #d1fae5;
        border-radius: 16px;
    }

    .group-edit-modal__actions {
        display: flex;
        gap: .6rem;
        margin-top: 1rem;
    }

    #groupInfoPanel .control-center-exit-row {
        display: flex;
        justify-content: flex-start;
        margin: .2rem 0 .8rem;
        padding: .75rem 0 0;
        border-top: 1px solid #eef2f7;
    }

    /* The assistant stays reachable through its Control Center card; the
       floating launcher disappears while this modal surface owns the screen. */
    body:has(#groupInfoPanel.is-open) .najm-hoda-widget {
        opacity: 0 !important;
        visibility: hidden !important;
        pointer-events: none !important;
        transform: translateY(10px) scale(.92) !important;
    }

    @media (min-width: 1024px) {
        .group-hero__desktop {
            padding-inline: 2rem !important;
            gap: 2rem !important;
        }

        .group-hero__desktop-actions {
            margin-inline-start: 1rem;
            padding-inline: .35rem;
            gap: .65rem !important;
        }
    }

    @media (max-width: 767px) {
        #groupInfoPanel .group-info-panel__inner {
            gap: .72rem;
            padding-inline: .85rem;
            padding-bottom: max(1rem, env(safe-area-inset-bottom));
        }

        #groupInfoPanel .control-center-header {
            min-height: 58px;
            gap: .6rem;
            padding-inline: 2.45rem .1rem;
        }

        #groupInfoPanel .panel-close-btn {
            top: .92rem;
            left: .88rem;
            width: 32px;
            height: 32px;
        }

        #groupInfoPanel .panel-hero__avatar {
            width: 44px;
            height: 44px;
            flex: 0 0 44px;
            border-radius: 14px;
            font-size: .86rem;
        }

        #groupInfoPanel .control-center-eyebrow {
            margin-bottom: .08rem;
            font-size: .62rem;
        }

        #groupInfoPanel .panel-hero__title {
            font-size: .86rem;
            line-height: 1.4;
        }

        #groupInfoPanel .panel-hero__subtitle {
            margin-top: .08rem;
            font-size: .62rem;
        }

        #groupInfoPanel .panel-metrics {
            gap: .3rem;
        }

        #groupInfoPanel .panel-metrics__item {
            min-height: 48px;
            padding: .36rem .22rem;
        }

        #groupInfoPanel .control-center-tabs {
            gap: .28rem;
            padding: .3rem;
            border-radius: 14px;
        }

        #groupInfoPanel .panel-tabs .tab {
            min-height: 42px;
            padding: .45rem .14rem;
            font-size: .64rem;
        }

        #groupInfoPanel .control-center-section-heading {
            margin-bottom: .72rem;
        }

        #groupInfoPanel .control-center-section-heading h3 {
            font-size: .9rem;
        }

        #groupInfoPanel .control-center-section-heading p {
            margin-top: .15rem;
            font-size: .68rem;
            line-height: 1.55;
        }

        #groupInfoPanel .control-center-secondary-tabs {
            margin-bottom: .65rem;
            padding: .22rem;
            gap: .24rem;
            border-radius: 12px;
        }

        #groupInfoPanel .control-center-secondary-tab {
            min-height: 32px;
            padding: .34rem .56rem;
            font-size: .65rem;
        }

        #groupInfoPanel .control-center-action-grid,
        #groupInfoPanel .control-center-action-grid--tools {
            align-items: stretch;
            gap: .5rem;
        }

        #groupInfoPanel .control-center-tool-card {
            height: 100%;
            min-height: 88px;
            padding: .68rem;
            gap: .24rem;
        }

        #groupInfoPanel .control-center-tool-card strong {
            line-height: 1.45;
        }

        #groupInfoPanel .control-center-tool-card span,
        #groupInfoPanel .control-center-tool-card small {
            line-height: 1.5;
        }

        #groupInfoPanel .control-center-exit-row {
            margin-bottom: .7rem;
            padding-top: .65rem;
        }

        #groupInfoPanel .control-center-exit-row .panel-action-btn--danger {
            min-height: 36px;
            padding: .45rem .65rem;
        }

        #groupEditFormBox.modal-shell {
            align-items: flex-end;
            padding: .65rem;
        }

        .group-edit-modal__dialog {
            width: 100%;
            max-height: calc(100dvh - 1.3rem);
            padding: 1rem;
            border-radius: 22px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        #groupInfoPanel .control-center-tool-card,
        #groupInfoPanel .panel-action-btn,
        .group-edit-modal__dialog {
            transition: none !important;
        }
    }
</style>

<script type="module">
(() => {
    const panel = document.getElementById('groupInfoPanel');
    const lifecycle = window.GroupChatLifecycle;
    if (!panel || !lifecycle || lifecycle.destroyed) return;

    /* The exit action belongs to “My groups”. Move the existing canonical link
       instead of cloning it so its route and permissions remain unchanged. */
    const groupsList = panel.querySelector('#groupsList');
    const footer = panel.querySelector('.control-center-footer');
    if (groupsList && footer) {
        const originalParent = footer.parentNode;
        const originalNextSibling = footer.nextSibling;
        const myGroupsSection = groupsList.closest('.control-center-subsection');
        const searchBlock = myGroupsSection?.querySelector('.panel-search');
        if (myGroupsSection) {
            footer.classList.add('control-center-exit-row');
            myGroupsSection.insertBefore(footer, searchBlock || groupsList);
            lifecycle.add(() => {
                footer.classList.remove('control-center-exit-row');
                if (originalParent) originalParent.insertBefore(footer, originalNextSibling);
            });
        }
    }

    /* Server details belong in logs, not in a member-facing panel. Keep known
       database/SQL diagnostics from leaking into the Persian UI. */
    const statsErrorText = panel.querySelector('#stats-error-text');
    if (statsErrorText) {
        const technicalError = /SQLSTATE|Unknown column|select\s|connection|database/i;
        const safeMessage = 'بارگذاری آمار گروه با خطا مواجه شد. لطفاً دوباره تلاش کنید.';
        const sanitizeStatsError = () => {
            const message = String(statsErrorText.textContent || '').trim();
            if (message && technicalError.test(message) && message !== safeMessage) {
                statsErrorText.textContent = safeMessage;
            }
        };
        const observer = new MutationObserver(sanitizeStatsError);
        observer.observe(statsErrorText, { childList: true, characterData: true, subtree: true });
        sanitizeStatsError();
        lifecycle.add(() => observer.disconnect());
    }
})();
</script>
