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
            gap: .22rem;
            padding: .25rem;
            border-radius: 13px;
        }

        #groupInfoPanel .panel-tabs .tab {
            min-height: 36px;
            padding: .42rem .12rem;
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

        #groupInfoPanel .control-center-footer {
            margin-top: .2rem;
            padding-top: .72rem;
        }

        #groupInfoPanel .control-center-footer .panel-action-btn--danger {
            min-height: 36px;
            padding: .45rem .65rem;
        }
    }

    /* The assistant remains available through the Control Center itself, but its
       floating launcher must not cover controls while the modal sheet is open. */
    body:has(#groupInfoPanel.is-open) .najm-hoda-widget {
        z-index: 1200 !important;
    }

    @media (prefers-reduced-motion: reduce) {
        #groupInfoPanel .control-center-tool-card,
        #groupInfoPanel .panel-action-btn {
            transition: none !important;
        }
    }
</style>
