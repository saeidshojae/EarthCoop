(() => {
    'use strict';

    const findScope = filterContainer => {
        const directParent = filterContainer.parentElement;
        if (directParent?.querySelector('[data-mobile-group-list], [data-desktop-group-table]')) {
            return directParent;
        }

        return filterContainer.closest('.toggle-content, .accordion-content, .tab-content, .groups-section')
            || directParent
            || document;
    };

    const setItemVisible = (item, visible) => {
        item.classList.toggle('filter-hidden', !visible);
        item.hidden = !visible;

        if (visible) {
            item.style.removeProperty('display');
            item.style.removeProperty('visibility');
        } else {
            item.style.setProperty('display', 'none', 'important');
            item.style.visibility = 'hidden';
        }
    };

    const applyFilter = (filterContainer, selectedFilter) => {
        const targetId = filterContainer.dataset.target;
        if (!targetId) return;

        const scope = findScope(filterContainer);
        const mobileList = scope.querySelector(`[data-mobile-filter-target="${CSS.escape(targetId)}"]`);
        const desktopWrapper = scope.querySelector('[data-desktop-group-table]');
        const targets = [mobileList, desktopWrapper].filter(Boolean);

        targets.forEach(target => {
            target.querySelectorAll('[data-filter-value]').forEach(item => {
                const value = String(item.dataset.filterValue || 'all').trim();
                setItemVisible(item, selectedFilter === 'all' || value === selectedFilter);
            });
        });

        filterContainer.querySelectorAll('.filter-button').forEach(button => {
            const active = button.dataset.filter === selectedFilter;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    };

    const initializeContainer = filterContainer => {
        const active = filterContainer.querySelector('.filter-button.active')
            || filterContainer.querySelector('.filter-button');
        if (!active) return;

        filterContainer.querySelectorAll('.filter-button').forEach(button => {
            button.setAttribute('aria-pressed', button === active ? 'true' : 'false');
        });
        applyFilter(filterContainer, active.dataset.filter || 'all');
    };

    const initialize = () => {
        document.querySelectorAll('.filter-buttons[data-target]').forEach(initializeContainer);
    };

    document.addEventListener('click', event => {
        const button = event.target.closest('.filter-buttons[data-target] .filter-button');
        if (!button) return;

        const filterContainer = button.closest('.filter-buttons[data-target]');
        if (!filterContainer) return;

        applyFilter(filterContainer, button.dataset.filter || 'all');
    }, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})();
