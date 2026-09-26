{{-- Home-only presentation polish for the shared sidebar and Najm Hoda launcher. --}}
<style>
    .home-sidebar .sidebar-section-label {
        margin: .7rem .25rem .25rem;
        padding: .35rem .7rem .2rem;
        color: #64748b;
        font-size: .72rem;
        font-weight: 700;
        line-height: 1.5;
        letter-spacing: .01em;
        pointer-events: none;
    }

    .home-sidebar .sidebar-section-label:first-child {
        margin-top: .35rem;
    }

    body.dark-mode .home-sidebar .sidebar-section-label {
        color: #94a3b8;
    }

    @media (max-width: 768px) {
        #najm-hoda-widget[data-route-name="home"] {
            bottom: calc(82px + env(safe-area-inset-bottom)) !important;
            right: 12px !important;
        }

        #najm-hoda-widget[data-route-name="home"] .najm-hoda-toggle-btn {
            width: 48px;
            height: 48px;
            font-size: 22px;
            box-shadow: 0 4px 12px rgba(55, 196, 180, .38);
        }
    }

    @media (max-width: 480px) {
        #najm-hoda-widget[data-route-name="home"] {
            bottom: calc(86px + env(safe-area-inset-bottom)) !important;
            right: 10px !important;
        }
    }
</style>

<script>
(function () {
    const installHomeSidebarSections = () => {
        const sidebarList = document.querySelector('.unified-public-sidebar .home-sidebar-nav > ul');
        if (!sidebarList || sidebarList.querySelector('[data-sidebar-section]')) return;

        const sections = [
            { key: 'network', label: 'شبکه و ارتباطات', route: @json(route('notifications.index')) },
            { key: 'governance', label: 'حکمرانی و مشارکت', route: @json(route((bool) config('location-governance.runtime_enabled') ? 'location-governance.me' : 'history.index')) },
            { key: 'economy', label: 'اقتصاد', route: @json(route('najm-bahar.dashboard')) },
            { key: 'account-support', label: 'حساب و راهنما', route: @json(route('my-invation-code')) },
        ];

        for (const section of sections) {
            const anchor = Array.from(sidebarList.querySelectorAll('a[href]'))
                .find(link => link.href === section.route);
            const item = anchor?.closest('li.sidebar-menu-item');
            if (!item) continue;

            const label = document.createElement('li');
            label.className = 'sidebar-section-label';
            label.dataset.sidebarSection = section.key;
            label.setAttribute('aria-hidden', 'true');
            label.textContent = section.label;
            item.before(label);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', installHomeSidebarSections, { once: true });
    } else {
        installHomeSidebarSections();
    }
})();
</script>
