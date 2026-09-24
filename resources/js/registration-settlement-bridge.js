const settlementSearchUrl = (parentLocationId, query = '') => {
    const params = new URLSearchParams({ parent_location_id: String(parentLocationId || '') });
    const trimmed = String(query || '').trim();
    if (trimmed) params.set('q', trimmed);
    return '/location/reference-settlements?' + params.toString();
};

const settlementChildrenUrl = (externalId, structuralClaimIds = []) => {
    const base = '/location/reference-settlements/' + encodeURIComponent(String(externalId || '')) + '/children';
    const ids = (Array.isArray(structuralClaimIds) ? structuralClaimIds : [])
        .map((id) => Number(id))
        .filter((id) => Number.isInteger(id) && id > 0);
    if (!ids.length) return base;
    const params = new URLSearchParams();
    ids.forEach((id) => params.append('location_structure_claim_ids[]', String(id)));
    return base + '?' + params.toString();
};

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
    const originMarker = document.createComment('earthcoop-reference-settlement-picker-origin');
    shell.parentNode?.insertBefore(originMarker, shell);
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
    if (!form || !selector || !locationInput || !proposalInput || !settlementInput || !queryInput || !searchButton || !status || !results) return;

    let parentLocationId = '';
    let selectedSettlement = null;
    let settlementNeighborhoodProposalId = '';
    let requestSerial = 0;
    let persistedHydrationAttempted = false;
    const persistedSettlementExternalId = String(shell.dataset.referenceSettlementCurrentExternalId || '');
    const persistedSettlementName = String(shell.dataset.referenceSettlementCurrentName || '');
    const persistedNeighborhoodProposalId = String(shell.dataset.referenceSettlementCurrentNeighborhoodProposalId || '');
    let persistedProposalPath = [];
    try {
        const parsed = JSON.parse(shell.dataset.referenceSettlementCurrentProposalPath || '[]');
        persistedProposalPath = Array.isArray(parsed) ? parsed.map((id) => Number(id)).filter((id) => Number.isInteger(id) && id > 0) : [];
    } catch (error) {
        persistedProposalPath = [];
    }

    const setStatus = (message, error = false) => {
        status.textContent = message;
        status.classList.toggle('text-danger', error);
    };
    const selectedStructuralClaimIds = () => Array.from(
        form.querySelectorAll('input[name="location_structure_claim_ids[]"]')
    ).map((input) => Number(input.value)).filter((id) => Number.isInteger(id) && id > 0);
    const rememberStructuralClaim = (claim) => {
        const id = Number(claim?.id);
        if (!Number.isInteger(id) || id <= 0) return;
        let input = form.querySelector(`input[data-location-structure-claim-id][value="${id}"]`);
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'location_structure_claim_ids[]';
            input.value = String(id);
            input.dataset.locationStructureClaimId = '';
            form.appendChild(input);
        }
        input.dataset.locationStructureClaimType = String(claim.claim_type || '');
        input.dataset.referenceSettlementStructureClaim = '';
    };
    const forgetStructuralClaim = (claimId) => {
        const id = Number(claimId);
        form.querySelectorAll('[data-reference-settlement-structure-claim]').forEach((input) => {
            if (Number(input.value) === id) input.remove();
        });
    };

    const closeDisclosure = () => {
        const panel = shell.closest('[data-location-exception-panel]');
        if (!panel) return;
        panel.classList.add('d-none');
        const disclosure = panel.closest('[data-location-exception-shell]');
        disclosure?.querySelector('[data-location-exception-toggle]')?.setAttribute('aria-expanded', 'false');
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


    const clearSettlement = () => {
        const hadReferenceSettlement = Boolean(selectedSettlement || settlementInput.value);
        settlementInput.value = '';
        selectedSettlement = null;
        results.innerHTML = '';
        if (settlementNeighborhoodProposalId && proposalInput.value === settlementNeighborhoodProposalId) {
            proposalInput.value = '';
        }
        settlementNeighborhoodProposalId = '';
        if (hadReferenceSettlement) proposalInput.value = '';
        removePresentedSettlement();
    };
    const hide = () => {
        clearSettlement();
        parentLocationId = '';
        delete selector.dataset.referenceBranchActive;
        shell.hidden = true;
        setStatus('');
        if (originMarker.parentNode && shell.parentNode !== originMarker.parentNode) {
            originMarker.parentNode.insertBefore(shell, originMarker.nextSibling);
        }
    };

    const presentSettlement = (item, retries = 4) => {
        removePresentedSettlement();
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
    const dispatchReferenceSelection = (proposal = null, proposalPath = [], payload = null) => {
        if (!selectedSettlement || !parentLocationId) return;
        selector.dispatchEvent(new CustomEvent('earthcoop-location-reference-selected', {
            bubbles: true,
            detail: {
                anchorLocationId: parentLocationId,
                settlement: selectedSettlement,
                proposal,
                proposalPath: Array.isArray(proposalPath) ? proposalPath : [],
                payload,
                context: selectorContext,
            },
        }));
    };

    const loadSettlementContinuation = async (item, preferredProposalId = '') => {
        try {
            const response = await fetch(
                settlementChildrenUrl(item.external_id, selectedStructuralClaimIds()),
                { credentials: 'same-origin', headers: { Accept: 'application/json' } },
            );
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(payload.message || 'دریافت سطح بعدی ممکن نشد.');

            const preferred = (Array.isArray(payload?.proposals) ? payload.proposals : [])
                .find((proposal) => String(proposal.id) === String(preferredProposalId || '')) || null;

            closeDisclosure();
            if (preferred) {
                settlementNeighborhoodProposalId = String(preferred.id);
                proposalInput.value = String(preferred.id);
                dispatchReferenceSelection(preferred, persistedProposalPath, payload);
            } else {
                dispatchReferenceSelection(null, persistedProposalPath, payload);
            }
            setStatus(preferred
                ? 'مسیر فعلی آبادی و محله بازیابی شد.'
                : 'آبادی انتخاب شد و سطح بعدی در مسیر اصلی نمایش داده شد.');
        } catch (error) {
            setStatus(error?.message || 'دریافت سطح بعدی ممکن نشد.', true);
        }
    };

    const choose = (item, preferredProposalId = '') => {
        if (!settlementSelectableForClaim(item)) return;
        selectedSettlement = item;
        settlementInput.value = item.external_id;
        locationInput.value = '';
        proposalInput.value = '';
        settlementNeighborhoodProposalId = '';
        if (submit) submit.disabled = selectorContext === 'registration';
        presentSettlement(item);
        results.querySelectorAll('button[data-settlement-external-id]').forEach((button) => {
            button.setAttribute('aria-pressed', button.dataset.settlementExternalId === item.external_id ? 'true' : 'false');
        });
        setStatus('آبادی انتخاب شد؛ در حال آماده‌سازی سطح بعدی...');
        void loadSettlementContinuation(item, preferredProposalId);
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

    selector.addEventListener('earthcoop-location-exception-open', (event) => {
        const detail = event.detail || {};
        const targetKeys = Array.isArray(detail.targetKeys) ? detail.targetKeys : [];
        const requestedParent = String(detail.parentLocationId || '');
        if (!parentLocationId || requestedParent !== parentLocationId) return;
        if (!targetKeys.includes('village') && !targetKeys.includes('settlement')) return;

        const mount = detail.referenceSlot || detail.panel;
        if (!(mount instanceof Element)) return;
        mount.appendChild(shell);
        shell.hidden = false;
        setStatus(selectedSettlement
            ? 'آبادی فعلی شما در همین بخش انتخاب شده است؛ می‌توانید آن را نگه دارید یا تغییر دهید.'
            : 'نام آبادی را در بانک مرجع ۱۴۰۴ جست‌وجو کنید. اگر پیدا نشد، از گزینهٔ افزودن روستا/آبادی جدید استفاده کنید.');
    });

    selector.addEventListener('earthcoop-location-selection-changed', async (event) => {
        const item = event.detail?.item || null;
        if (selectedSettlement && item?.picker_kind === 'proposal' && event.detail?.proposalId) {
            return;
        }

        hide();
        if (item?.type_key !== 'rural_district' || !event.detail?.locationId) return;

        parentLocationId = String(event.detail.locationId);
        selector.dataset.referenceBranchActive = '1';
        shell.hidden = true;
        setStatus('');

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
