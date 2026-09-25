const residenceSelectors = Array.from(document.querySelectorAll(
    '[data-location-selector-context="registration"], [data-location-selector-context="profile"], [data-location-selector-context="admin-user-residence"]'
));

const installResidenceStyles = () => {
    if (!residenceSelectors.length || document.getElementById('location-residence-ux-styles')) return;
    const style = document.createElement('style');
    style.id = 'location-residence-ux-styles';
    style.textContent = `
        .location-residence-surface [data-location-levels] .form-select { min-height: 44px; }
        .location-geolocation-actions .btn { min-height: 44px; display: inline-flex; align-items: center; justify-content: center; }
        [data-location-proposal-shell], .location-proposal-shell, .location-proposal-surface { border: 0; background: transparent; padding: 0; margin-top: .25rem; }
        [data-location-proposal-toggle], .location-proposal-toggle {
            min-height: 40px; padding: .35rem .25rem; white-space: normal; text-align: start;
            background: transparent; color: #087f5b; border: 0; font-weight: 700; box-shadow: none;
        }
        [data-location-proposal-toggle]:hover, [data-location-proposal-toggle]:focus,
        .location-proposal-toggle:hover, .location-proposal-toggle:focus { color: #066649; background: rgba(16,185,129,.08); }
        [data-location-exception-toggle] {
            min-height: 40px; padding: .35rem .2rem; display: inline-flex; align-items: center;
            color: #64748b; font-weight: 600; line-height: 1.5; text-align: start;
        }
        [data-location-exception-toggle]:hover, [data-location-exception-toggle]:focus { color: #087f5b; background: transparent; }
        [data-location-exception-panel] {
            border-color: rgba(100,116,139,.22) !important;
            background: rgba(248,250,252,.82) !important;
            box-shadow: 0 8px 24px rgba(15,23,42,.04);
        }
        [data-location-exception-panel] [data-reference-settlement-picker] { margin-top: 0 !important; border: 0 !important; padding: 0 !important; background: transparent !important; }
        .location-proposal-panel { margin-top: .5rem; padding: .75rem; border: 1px solid rgba(100,116,139,.2); border-radius: .75rem; background: rgba(248,250,252,.72); display: grid; gap: .65rem; }
        .location-proposal-heading { font-size: .875rem; font-weight: 800; color: #334155; }
        .location-proposal-actions { display: flex; align-items: center; gap: .5rem; }
        .location-proposal-actions .btn-primary,
        [data-location-proposal-panel] .btn-primary { min-height: 42px; font-weight: 700; background: #6f42c1; border-color: #6f42c1; color: #fff; }
        .location-proposal-actions .btn-primary:hover, .location-proposal-actions .btn-primary:focus,
        [data-location-proposal-panel] .btn-primary:hover, [data-location-proposal-panel] .btn-primary:focus { background: #5f37aa; border-color: #5f37aa; color: #fff; }
        .location-proposal-actions .btn-primary:disabled,
        [data-location-proposal-panel] .btn-primary:disabled { opacity: .55; }
        .location-proposal-actions .btn-link,
        [data-location-proposal-panel] .btn-link { min-height: 42px; text-decoration: none; color: #64748b; }
        [data-location-pending-badge] { font-weight: 600; }
        [data-location-type-choice] { padding: .65rem .75rem; border: 1px solid rgba(100,116,139,.18); border-radius: .75rem; background: rgba(248,250,252,.72); }
        [data-location-type-choice] .btn { min-height: 42px; font-weight: 700; }
        @media (max-width: 640px) {
            .location-geolocation-actions { display: grid !important; grid-template-columns: minmax(0, 1fr); }
            .location-geolocation-actions .btn { width: 100%; }
            [data-location-proposal-toggle] { width: auto; max-width: 100%; }
            [data-location-exception-toggle] { width: auto; max-width: 100%; min-height: 44px; }
            [data-location-exception-panel] { padding: .75rem !important; }
            .location-proposal-panel { padding: .7rem; }
            .location-proposal-actions { width: 100%; display: grid; grid-template-columns: minmax(0, 1fr) auto; }
            .location-proposal-actions .btn-primary { width: 100%; }
            .location-residence-surface [data-location-levels] { min-width: 0; }
            .location-residence-surface [data-location-levels] .form-select { width: 100%; max-width: 100%; }
            [data-location-type-choice] .d-flex { display: grid !important; grid-template-columns: repeat(auto-fit, minmax(92px, 1fr)); width: 100%; }
            [data-location-type-choice] .btn { width: 100%; }
        }
    `;
    document.head.appendChild(style);
};

const FA_TYPE_LABELS = Object.freeze({
    global: 'جهانی', continent: 'قاره', country: 'کشور', province: 'استان', county: 'شهرستان',
    section: 'بخش', city: 'شهر', rural_district: 'دهستان', village: 'روستا', settlement: 'آبادی', urban_region: 'منطقه',
    neighborhood: 'محله', street: 'خیابان', alley: 'کوچه', complex: 'مجتمع', building: 'ساختمان',
});
const typedLocationLabel = (label, typeKey) => {
    const name = String(label || '').trim(); const prefix = FA_TYPE_LABELS[typeKey] || '';
    return !prefix || !name || name === prefix || name.startsWith(prefix + ' ') ? name : prefix + ' ' + name;
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

    let canonicalPathItems = [];
    const selectedLabels = () => canonicalPathItems.length
        ? canonicalPathItems
        : Array.from(levels?.querySelectorAll('[data-location-select]') || [])
            .map((select) => select.selectedOptions?.[0])
            .filter((option) => option?.value)
            .map((option) => ({ label: typedLocationLabel(option.textContent?.replace(/\s+—\s+در انتظار تأیید$/, '').trim() || '', option.dataset.typeKey || option.closest('select')?.selectedOptions?.[0]?.dataset.typeKey || ''), proposal: String(option.value || '').startsWith('proposal:') }))
            .filter((item) => item.label);

    const renderPath = (items = selectedLabels()) => {
        if (!path || !items.length) return;
        path.replaceChildren(); path.classList.remove('text-muted');
        const pendingCount = items.filter((item) => item.proposal).length;
        items.forEach((item, index) => {
            const node = document.createElement('span'); node.setAttribute('data-location-path-item', '');
            node.className = item.proposal ? 'location-proposal badge bg-warning-subtle text-warning-emphasis' : 'badge bg-primary-subtle text-primary-emphasis';
            node.textContent = item.label; path.appendChild(node);
            if (index < items.length - 1) path.appendChild(document.createTextNode(' ← '));
        });
        if (pendingCount > 0) {
            const status = document.createElement('span');
            status.setAttribute('data-location-pending-badge', '');
            status.className = 'badge bg-warning-subtle text-warning-emphasis';
            status.textContent = pendingCount === 1 ? 'در انتظار بررسی' : `${pendingCount} سطح در انتظار بررسی`;
            path.appendChild(document.createTextNode(' '));
            path.appendChild(status);
        }
    };

    const identityTypeKey = (identity) => {
        const option = Array.from(levels?.querySelectorAll('[data-location-select] option') || []).find((candidate) => candidate.value === identity);
        return option?.dataset.typeKey || null;
    };
    const waitForPersistedOption = async (depth, identity) => {
        for (let attempt = 0; attempt < 160; attempt += 1) {
            const select = levels?.querySelector(`[data-location-select="${depth}"]`);
            if (select && Array.from(select.options).some((option) => option.value === identity)) return select;
            const choice = levels?.querySelector(`[data-location-depth="${depth}"][data-location-type-choice]`);
            if (choice) {
                let typeKey = identityTypeKey(identity);
                if (!typeKey) {
                    try {
                        const map = JSON.parse(choice.dataset.locationTypePayload || '[]');
                        typeKey = map.find((entry) => Array.isArray(entry.ids) && entry.ids.includes(identity))?.key || null;
                    } catch (error) { typeKey = null; }
                }
                const button = typeKey ? choice.querySelector(`[data-location-type-choice-key="${typeKey}"]`) : null;
                if (button) { button.click(); await sleep(0); continue; }
            }
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

    selector.addEventListener('earthcoop-location-path-changed', (event) => {
        canonicalPathItems = (Array.isArray(event.detail?.items) ? event.detail.items : []).map((item) => ({
            label: typedLocationLabel(item.label || '', item.type_key || ''),
            proposal: item.pending === true,
        }));
        renderPath(canonicalPathItems);
    });
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
        if (!canonicalPathItems.length) renderPath();
    });
    if (levels) observer.observe(levels, { childList: true, subtree: true });

    void replayPersistedPath();
};

installResidenceStyles();
residenceSelectors.forEach(mountResidenceUx);
