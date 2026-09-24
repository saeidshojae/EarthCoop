const settlementSearchUrl = (parentLocationId, query = '') => {
    const params = new URLSearchParams({ parent_location_id: String(parentLocationId || '') });
    const trimmed = String(query || '').trim();
    if (trimmed) params.set('q', trimmed);
    return '/location/reference-settlements?' + params.toString();
};

const settlementChildrenUrl = (externalId) =>
    '/location/reference-settlements/' + encodeURIComponent(String(externalId || '')) + '/children';

const settlementSelectableForClaim = (item) => {
    if (!item || item.governance_authorized === true || item.operational_promotion_allowed === true) return false;
    if (['unverified_settlement', 'needs_review'].includes(item.classification) && item.residential_eligibility === 'unverified') return true;
    return item.classification === 'verified_residential_village' && item.residential_eligibility === 'verified';
};

const settlementNeighborhoodProposalPayload = (settlementId, typeId, name, locale = 'fa') => ({
    parent_reference_settlement_id: Number(settlementId),
    location_type_id: Number(typeId),
    canonical_name: String(name || '').trim(),
    localized_names: { [locale]: String(name || '').trim() },
});

const mountSettlementRegistrationBridge = (shell) => {
    if (!shell) return;
    const form = shell.closest('form');
    const selector = form?.querySelector('[data-location-selector]');
    const selectorContext = selector?.dataset.locationSelectorContext || selector?.dataset.locationPurpose || '';
    if (!['registration', 'profile', 'admin-user-residence'].includes(selectorContext)) return;
    const locationInput = form?.querySelector('[data-location-id][name="location_id"]');
    const proposalInput = form?.querySelector('[data-location-proposal-id][name="location_proposal_id"]');
    const settlementInput = form?.querySelector('[data-reference-settlement-external-id]');
    const submit = form?.querySelector('[data-location-submit]');
    const path = selector?.querySelector('[data-location-path]');
    const queryInput = shell.querySelector('[data-reference-settlement-query]');
    const searchButton = shell.querySelector('[data-reference-settlement-search]');
    const status = shell.querySelector('[data-reference-settlement-status]');
    const results = shell.querySelector('[data-reference-settlement-results]');
    const neighborhoodHost = shell.querySelector('[data-reference-settlement-neighborhood]');
    if (!form || !selector || !locationInput || !proposalInput || !settlementInput || !queryInput || !searchButton || !status || !results || !neighborhoodHost) return;

    let parentLocationId = '';
    let selectedSettlement = null;
    let settlementNeighborhoodProposalId = '';
    let requestSerial = 0;
    let persistedHydrationAttempted = false;
    const persistedSettlementExternalId = String(shell.dataset.referenceSettlementCurrentExternalId || '');
    const persistedSettlementName = String(shell.dataset.referenceSettlementCurrentName || '');
    const persistedNeighborhoodProposalId = String(shell.dataset.referenceSettlementCurrentNeighborhoodProposalId || '');

    const setStatus = (message, error = false) => {
        status.textContent = message;
        status.classList.toggle('text-danger', error);
    };

    const villageSelect = () => Array.from(selector.querySelectorAll('[data-location-select]')).find((select) => {
        const wrapper = select.closest('[data-location-depth]');
        const label = wrapper?.querySelector('label')?.textContent || '';
        return label.includes('روستا') || Array.from(select.options).some((option) => option.dataset.typeKey === 'village');
    }) || null;

    const removePresentedSettlement = () => {
        const select = villageSelect();
        if (select) {
            select.querySelectorAll('option[data-reference-settlement-option]').forEach((option) => option.remove());
            if (String(select.value).startsWith('reference-settlement:')) select.value = '';
        }
    };


    const clearNeighborhood = () => {
        neighborhoodHost.innerHTML = '';
        if (settlementNeighborhoodProposalId && proposalInput.value === settlementNeighborhoodProposalId) {
            proposalInput.value = '';
        }
        settlementNeighborhoodProposalId = '';
    };
    const clearSettlement = () => {
        settlementInput.value = '';
        selectedSettlement = null;
        results.innerHTML = '';
        clearNeighborhood();
        removePresentedSettlement();
    };
    const hide = () => {
        clearSettlement();
        parentLocationId = '';
        shell.hidden = true;
        setStatus('');
    };

    const presentSettlement = (item, retries = 4) => {
        removePresentedSettlement();
        if (selectorContext !== 'registration') return;
        const select = villageSelect();
        if (select) {
            const option = document.createElement('option');
            option.value = 'reference-settlement:' + item.external_id;
            option.textContent = item.name_fa + ' — در انتظار بررسی سکونت';
            option.dataset.referenceSettlementOption = '';
            option.dataset.typeKey = 'village';
            select.appendChild(option);
            select.value = option.value;
        } else if (retries > 0) {
            window.setTimeout(() => {
                if (selectedSettlement?.external_id === item.external_id) presentSettlement(item, retries - 1);
            }, 50);
        }
    };
    const dispatchReferenceSelection = (proposal = null) => {
        if (!selectedSettlement || !parentLocationId) return;
        selector.dispatchEvent(new CustomEvent('earthcoop-location-reference-selected', {
            bubbles: true,
            detail: {
                anchorLocationId: parentLocationId,
                settlement: selectedSettlement,
                proposal,
                context: selectorContext,
            },
        }));
    };


    const chooseNeighborhood = (id, label, proposal = null) => {
        settlementNeighborhoodProposalId = String(id);
        proposalInput.value = settlementNeighborhoodProposalId;
        if (submit) submit.disabled = false;
        presentSettlement(selectedSettlement);
        dispatchReferenceSelection({
            ...(proposal || {}),
            id: Number(id),
            label,
            canonical_name: proposal?.canonical_name || label,
            type_key: 'neighborhood',
            status: proposal?.status || 'pending',
            selectable: true,
            children_url: proposal?.children_url || '/location/proposals/' + encodeURIComponent(id) + '/children',
        });
        setStatus('آبادی و محلهٔ دقیق انتخاب شدند. جزئیات نشانی بعد از محله اختیاری است و می‌توانید مسیر را ادامه دهید.');
    };

    const renderNeighborhoods = (payload, preferredProposalId = '') => {
        neighborhoodHost.innerHTML = '';
        const allowed = Array.isArray(payload?.allowed_types)
            ? payload.allowed_types.find((type) => type.key === 'neighborhood' && type.proposal_allowed === true)
            : null;
        if (!allowed) {
            neighborhoodHost.innerHTML = '<div class="small text-muted">برای این آبادی فعلاً ادامهٔ محله قابل ثبت نیست.</div>';
            return;
        }

        const label = document.createElement('label');
        label.className = 'form-label small text-secondary mb-1';
        label.textContent = 'محله';
        const select = document.createElement('select');
        select.className = 'form-select';
        select.dataset.referenceSettlementNeighborhoodSelect = '';
        select.innerHTML = '<option value="">یک گزینه را انتخاب کنید</option>';
        const proposalMap = new Map();
        (Array.isArray(payload?.proposals) ? payload.proposals : []).forEach((proposal) => {
            proposalMap.set(String(proposal.id), proposal);
            const option = document.createElement('option');
            option.value = String(proposal.id);
            option.textContent = proposal.label + ' — در انتظار تأیید';
            option.dataset.pendingNeighborhood = '';
            select.appendChild(option);
        });
        select.addEventListener('change', () => {
            const option = select.selectedOptions[0];
            if (!option?.value) {
                proposalInput.value = '';
                presentSettlement(selectedSettlement);
                dispatchReferenceSelection(null);
                if (submit) submit.disabled = false;
                return;
            }
            chooseNeighborhood(
                option.value,
                option.textContent.replace(' — در انتظار تأیید', ''),
                proposalMap.get(String(option.value)) || null,
            );
        });

        const proposalShell = document.createElement('div');
        proposalShell.className = 'border rounded-3 p-3 bg-light mt-2';
        const toggle = document.createElement('button');
        toggle.type = 'button'; toggle.className = 'btn btn-outline-secondary btn-sm'; toggle.textContent = '+ افزودن محله جدید';
        const panel = document.createElement('div');
        panel.className = 'vstack gap-2 mt-2 d-none';
        const input = document.createElement('input');
        input.type = 'text'; input.className = 'form-control form-control-sm'; input.maxLength = 255; input.placeholder = 'نام محله';
        const actions = document.createElement('div'); actions.className = 'd-flex gap-2';
        const save = document.createElement('button'); save.type = 'button'; save.className = 'btn btn-primary btn-sm'; save.textContent = 'ثبت محله';
        const cancel = document.createElement('button'); cancel.type = 'button'; cancel.className = 'btn btn-link btn-sm'; cancel.textContent = 'انصراف';
        const feedback = document.createElement('div'); feedback.className = 'small text-secondary';
        actions.append(save, cancel); panel.append(input, actions, feedback); proposalShell.append(toggle, panel);
        toggle.addEventListener('click', () => panel.classList.toggle('d-none'));
        cancel.addEventListener('click', () => { panel.classList.add('d-none'); feedback.textContent = ''; });

        save.addEventListener('click', async () => {
            const name = input.value.trim();
            if (!name || !selectedSettlement) {
                feedback.textContent = 'نام محله را وارد کنید.'; feedback.classList.add('text-danger'); return;
            }
            save.disabled = true; feedback.classList.remove('text-danger'); feedback.textContent = 'در حال ثبت پیشنهاد محله...';
            const csrf = form.querySelector('input[name="_token"]')?.value || '';
            try {
                const response = await fetch('/locations/proposals', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) },
                    body: JSON.stringify(settlementNeighborhoodProposalPayload(selectedSettlement.id, allowed.id, name, document.documentElement.lang || 'fa')),
                });
                const result = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(result.message || 'ثبت محله ممکن نشد.');
                let option = Array.from(select.options).find((candidate) => candidate.value === String(result.id));
                if (!option) {
                    option = document.createElement('option');
                    option.value = String(result.id);
                    option.textContent = (result.canonical_name || name) + ' — در انتظار تأیید';
                    option.dataset.pendingNeighborhood = '';
                    select.appendChild(option);
                }
                select.value = option.value;
                chooseNeighborhood(result.id, result.canonical_name || name, result);
                panel.classList.add('d-none'); feedback.textContent = '';
            } catch (error) {
                feedback.textContent = error?.message || 'ثبت محله ممکن نشد.'; feedback.classList.add('text-danger');
            } finally { save.disabled = false; }
        });

        neighborhoodHost.append(label, select, proposalShell);

        if (preferredProposalId) {
            const preferred = Array.from(select.options).find((option) => option.value === String(preferredProposalId));
            if (preferred) {
                select.value = preferred.value;
                chooseNeighborhood(
                    preferred.value,
                    preferred.textContent.replace(' — در انتظار تأیید', ''),
                    proposalMap.get(String(preferred.value)) || null,
                );
            }
        }
    };

    const loadNeighborhoods = async (item, preferredProposalId = '') => {
        neighborhoodHost.innerHTML = '<div class="small text-muted">در حال دریافت محله‌ها...</div>';
        try {
            const response = await fetch(settlementChildrenUrl(item.external_id), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(payload.message || 'دریافت محله‌ها ممکن نشد.');
            renderNeighborhoods(payload, preferredProposalId);
        } catch (error) {
            neighborhoodHost.innerHTML = '<div class="small text-danger">' + (error?.message || 'دریافت محله‌ها ممکن نشد.') + '</div>';
        }
    };

    const choose = (item, preferredProposalId = '') => {
        if (!settlementSelectableForClaim(item)) return;
        selectedSettlement = item;
        settlementInput.value = item.external_id;
        locationInput.value = '';
        proposalInput.value = '';
        settlementNeighborhoodProposalId = '';
        if (submit) submit.disabled = false;
        presentSettlement(item);
        dispatchReferenceSelection(null);
        results.querySelectorAll('button[data-settlement-external-id]').forEach((button) => {
            button.setAttribute('aria-pressed', button.dataset.settlementExternalId === item.external_id ? 'true' : 'false');
        });
        setStatus('آبادی مرجع انتخاب شد. در صورت وجود محله، آن را انتخاب کنید یا محلهٔ جدید پیشنهاد دهید.');
        void loadNeighborhoods(item, preferredProposalId);
    };

    const renderResults = (items) => {
        results.innerHTML = '';
        const selectable = (Array.isArray(items) ? items : []).filter(settlementSelectableForClaim);
        if (!selectable.length) { setStatus('آبادی مطابق این نام زیر والد انتخاب‌شده پیدا نشد.'); return; }
        selectable.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button'; button.className = 'btn btn-outline-secondary text-right';
            button.dataset.settlementExternalId = item.external_id; button.setAttribute('aria-pressed', 'false');
            button.textContent = item.name_fa + ' — در انتظار بررسی سکونت';
            button.addEventListener('click', () => choose(item));
            results.appendChild(button);
        });
        setStatus('آبادی درست را انتخاب کنید.');
    };

    const requestSettlements = async (query, render = true) => {
        if (!parentLocationId) return false;
        const serial = ++requestSerial;
        const response = await fetch(settlementSearchUrl(parentLocationId, query), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const payload = await response.json().catch(() => ({}));
        if (serial !== requestSerial || !response.ok) return false;
        const items = Array.isArray(payload.data) ? payload.data : [];
        if (render) renderResults(items);
        return items.length > 0;
    };

    selector.addEventListener('earthcoop-location-selection-changed', async (event) => {
        hide();
        const item = event.detail?.item || null;
        if (item?.type_key !== 'rural_district' || !event.detail?.locationId) return;

        parentLocationId = String(event.detail.locationId);
        shell.hidden = false;
        setStatus('اگر آبادی شما در فهرست مسیر نیست، نام آن را در بانک مرجع جست‌وجو کنید.');

        if (!persistedHydrationAttempted && persistedSettlementExternalId) {
            persistedHydrationAttempted = true;
            try {
                const serial = ++requestSerial;
                const response = await fetch(
                    settlementSearchUrl(parentLocationId, persistedSettlementName || ''),
                    { credentials: 'same-origin', headers: { Accept: 'application/json' } }
                );
                const payload = await response.json().catch(() => ({}));
                if (serial !== requestSerial || !response.ok) return;

                const matched = (Array.isArray(payload.data) ? payload.data : []).find((candidate) =>
                    String(candidate.external_id || '') === persistedSettlementExternalId
                );
                if (matched && settlementSelectableForClaim(matched)) {
                    choose(matched, persistedNeighborhoodProposalId);
                    setStatus('مسیر آبادی فعلی شما بازیابی شد؛ می‌توانید آن را نگه دارید یا تغییر دهید.');
                }
            } catch (error) {
                console.warn('EarthCoop persisted reference settlement could not be hydrated:', error);
                setStatus('جست‌وجوی آبادی آماده است؛ بازیابی خودکار آبادی فعلی ممکن نشد و می‌توانید آن را دوباره جست‌وجو کنید.', true);
            }
        }
    });

    searchButton.addEventListener('click', async () => {
        const query = queryInput.value.trim();
        if (query.length < 2) { setStatus('حداقل دو حرف از نام آبادی را وارد کنید.', true); return; }
        searchButton.disabled = true; setStatus('در حال جست‌وجو...');
        try {
            if (!await requestSettlements(query, true)) setStatus('آبادی مطابق این نام زیر والد انتخاب‌شده پیدا نشد.');
        } catch { setStatus('جست‌وجوی آبادی ممکن نشد.', true); }
        finally { searchButton.disabled = false; }
    });
};

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-reference-settlement-picker]').forEach(mountSettlementRegistrationBridge);
}

export { settlementSearchUrl, settlementChildrenUrl, settlementSelectableForClaim, settlementNeighborhoodProposalPayload, mountSettlementRegistrationBridge };
