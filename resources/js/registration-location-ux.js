const residenceSelectors = Array.from(document.querySelectorAll(
    '[data-location-selector-context="registration"], [data-location-selector-context="profile"]'
));

const installResidenceStyles = () => {
    if (!residenceSelectors.length || document.getElementById('location-residence-ux-styles')) return;
    const style = document.createElement('style');
    style.id = 'location-residence-ux-styles';
    style.textContent = `
        .location-residence-surface [data-location-levels] .form-select { min-height: 44px; }
        .location-geolocation-actions .btn { min-height: 44px; display: inline-flex; align-items: center; justify-content: center; }
        [data-location-proposal-shell], .location-proposal-surface { border-radius: .85rem; }
        [data-location-proposal-toggle], .location-proposal-toggle {
            min-height: 44px; white-space: normal; text-align: start;
            background: linear-gradient(135deg, #10b981, #059669); color: #fff; border-color: transparent;
            font-weight: 700; box-shadow: 0 4px 12px rgba(16, 185, 129, .22);
        }
        [data-location-proposal-toggle]:hover, [data-location-proposal-toggle]:focus,
        .location-proposal-toggle:hover, .location-proposal-toggle:focus { color: #fff; filter: brightness(.96); }
        @media (max-width: 640px) {
            .location-geolocation-actions { display: grid !important; grid-template-columns: minmax(0, 1fr); }
            .location-geolocation-actions .btn, [data-location-proposal-toggle] { width: 100%; }
            .location-residence-surface [data-location-levels] { min-width: 0; }
            .location-residence-surface [data-location-levels] .form-select { width: 100%; max-width: 100%; }
        }
    `;
    document.head.appendChild(style);
};

const sleep = (milliseconds) => new Promise((resolve) => window.setTimeout(resolve, milliseconds));

const mountResidenceUx = (selector) => {
    const levels = selector.querySelector('[data-location-levels]');
    const path = selector.querySelector('[data-location-path]');
    const locationInput = selector.querySelector('[data-location-id]');
    const proposalInput = selector.querySelector('[data-location-proposal-id]');
    const submit = selector.closest('form')?.querySelector('[data-location-submit]');
    const currentLocationId = selector.dataset.locationCurrentId || '';
    const currentProposalId = selector.dataset.locationCurrentProposalId || '';
    let currentPath = [];

    try {
        const parsed = JSON.parse(selector.dataset.locationCurrentPath || '[]');
        currentPath = Array.isArray(parsed) ? parsed.map((identity) => String(identity)) : [];
    } catch (error) {
        console.warn('EarthCoop persisted residence path could not be parsed:', error);
    }

    if (currentLocationId && !currentProposalId && locationInput && !locationInput.value) {
        locationInput.value = currentLocationId;
    }

    const selectedLabels = () => Array.from(levels?.querySelectorAll('select') || [])
        .map((select) => select.selectedOptions?.[0])
        .filter((option) => option?.value)
        .map((option) => ({ label: option.textContent?.replace(/\s+—\s+در انتظار تأیید$/, '').trim() || '', proposal: String(option.value || '').startsWith('proposal:') }))
        .filter((item) => item.label);

    const renderPath = (items = selectedLabels()) => {
        if (!path || !items.length) return;
        path.replaceChildren(); path.classList.remove('text-muted');
        items.forEach((item, index) => {
            const node = document.createElement('span'); node.setAttribute('data-location-path-item', '');
            node.className = item.proposal ? 'location-proposal badge bg-warning-subtle text-warning-emphasis' : 'badge bg-primary-subtle text-primary-emphasis';
            node.textContent = item.proposal ? `${item.label} (در انتظار بررسی)` : item.label; path.appendChild(node);
            if (index < items.length - 1) path.appendChild(document.createTextNode(' ← '));
        });
    };

    const waitForPersistedOption = async (depth, identity) => {
        for (let attempt = 0; attempt < 160; attempt += 1) {
            const select = levels?.querySelector(`[data-location-select="${depth}"]`);
            if (select && Array.from(select.options).some((option) => option.value === identity)) return select;
            await sleep(25);
        }
        return null;
    };

    const replayPersistedPath = async () => {
        if (!levels || !currentPath.length) return;

        for (let depth = 0; depth < currentPath.length; depth += 1) {
            const identity = currentPath[depth];
            const select = await waitForPersistedOption(depth, identity);
            if (!select) {
                console.warn(`EarthCoop persisted residence path is stale at depth ${depth}: ${identity}`);
                return;
            }

            select.value = identity;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        const terminalIdentity = currentPath[currentPath.length - 1];
        if (terminalIdentity.startsWith('proposal:')) {
            if (locationInput) locationInput.value = '';
            if (proposalInput) proposalInput.value = currentProposalId;
        } else {
            if (locationInput) locationInput.value = currentLocationId;
            if (proposalInput) proposalInput.value = '';
        }
        if (submit) submit.disabled = false;
        renderPath();
    };

    levels?.addEventListener('change', () => window.setTimeout(() => renderPath(), 0));
    selector.addEventListener('location:hydrate', (event) => {
        const suggestedId = event.detail?.suggested_location_id; if (!suggestedId || !locationInput) return;
        locationInput.value = String(suggestedId); if (proposalInput) proposalInput.value = ''; if (submit) submit.disabled = false;
        renderPath([{ label: event.detail?.suggested_location_label || 'موقعیت تشخیص‌داده‌شده', proposal: false }]);
    });

    const observer = new MutationObserver(() => {
        selector.querySelectorAll('[data-location-proposal-shell]').forEach((shell) => {
            shell.classList.add('location-proposal-surface');
            shell.querySelector('[data-location-proposal-toggle]')?.classList.add('location-proposal-toggle');
        });
        renderPath();
    });
    if (levels) observer.observe(levels, { childList: true, subtree: true });

    void replayPersistedPath();
};

installResidenceStyles();
residenceSelectors.forEach(mountResidenceUx);
