import {
    initializeLocationSelector,
    normalizePickerPayload,
    registrationPayload,
    microContinuationTypes,
    filterPayloadByType,
    projectScopePayload,
    projectScopeSelectionValues,
    pickerLevelLabel,
    selectionValues,
    shouldRenderNextLevel,
    shouldStopRegistrationAtProposal,
    locationDisplayLabel,
} from './location-selector-core.js';

const FA_TYPE_LABELS = Object.freeze({
    global: 'جهانی', continent: 'قاره', country: 'کشور', province: 'استان / ایالت', county: 'شهرستان / ناحیه',
    section: 'بخش', city: 'شهر', rural_district: 'دهستان', village: 'روستا', urban_region: 'منطقه',
    neighborhood: 'محله', street: 'خیابان', alley: 'کوچه', complex: 'مجتمع', building: 'ساختمان',
});
const localizeLocationTypeLabel = (type, locale = (typeof document !== 'undefined' ? document.documentElement.lang : 'fa')) => {
    const documentIsRtl = typeof document !== 'undefined' && String(document.documentElement.dir || '').toLowerCase() === 'rtl';
    const usePersianLabel = documentIsRtl || String(locale || '').toLowerCase().startsWith('fa');
    return usePersianLabel && FA_TYPE_LABELS[type?.key] ? FA_TYPE_LABELS[type.key] : (type?.label || type?.canonical_name || type?.key || '');
};
const proposalParentPayload = (identity) => {
    const value = String(identity || '');
    if (value.startsWith('location:')) return { parent_location_id: Number(value.slice(9)) };
    if (value.startsWith('proposal:')) return { parent_location_proposal_id: Number(value.slice(9)) };
    return {};
};
const openProposal = (item) => ['pending', 'ready_for_review', 'needs_evidence'].includes(String(item?.status || '')) && item?.selectable !== false;
const allowedTypes = (payload) => (Array.isArray(payload?.allowed_types) ? payload.allowed_types : []).filter((type) => type?.proposal_allowed === true);
const pendingRegistrationEndpointAllowed = (host, typeKey) => {
    if ((host.dataset.locationPurpose || host.dataset.locationSelectorContext) !== 'registration') return true;
    if (typeKey === 'neighborhood') return true;
    const claimTypes = new Set(Array.from(host.closest('form')?.querySelectorAll('input[name="location_structure_claim_ids[]"][data-location-structure-claim-type]') || []).map((input) => input.dataset.locationStructureClaimType));
    if (['urban_region', 'village'].includes(typeKey)) return claimTypes.has('no_neighborhood');
    if (typeKey === 'city') return claimTypes.has('no_urban_region') && claimTypes.has('no_neighborhood');
    return false;
};
const setSelection = (host, id, typeKey = '') => {
    const location = host.querySelector('[data-location-id][name="location_id"]'); const proposal = host.querySelector('[data-location-proposal-id][name="location_proposal_id"]'); const submit = host.closest('form')?.querySelector('[data-location-submit]');
    if (location) location.value = ''; if (proposal) proposal.value = String(id); if (submit) submit.disabled = !pendingRegistrationEndpointAllowed(host, typeKey);
};
const status = (host, text, error = false) => { const node = host.querySelector('[data-location-status]'); if (node) { node.textContent = text; node.classList.toggle('text-danger', error); } };
const removeAfter = (levels, depth) => levels.querySelectorAll('[data-location-depth]').forEach((node) => { if (Number(node.dataset.locationDepth) > depth) node.remove(); });

const pendingStructuralClaimIds = (host) => Array.from(host.closest('form')?.querySelectorAll('input[name="location_structure_claim_ids[]"]') || [])
    .map((input) => Number(input.value))
    .filter((id) => Number.isInteger(id) && id > 0);
const pendingStructuralClaimContextUrl = (url, host) => {
    const ids = pendingStructuralClaimIds(host);
    if (!ids.length) return url;
    const separator = url.includes('?') ? '&' : '?';
    return url + separator + ids.map((id) => `location_structure_claim_ids%5B%5D=${encodeURIComponent(id)}`).join('&');
};
const removePendingStructuralClaimIds = (host, ids) => {
    const form = host.closest('form'); if (!form) return;
    const idSet = new Set(ids.map((id) => Number(id)).filter((id) => Number.isInteger(id) && id > 0));
    form.querySelectorAll('input[name="location_structure_claim_ids[]"]').forEach((input) => {
        if (idSet.has(Number(input.value))) input.remove();
    });
};

const rememberPendingStructuralClaim = (host, id, depth, claimType = '') => {
    const form = host.closest('form'); if (!form || !id) return;
    let input = form.querySelector(`input[name="location_structure_claim_ids[]"][value="${id}"]`);
    if (!input) { input = document.createElement('input'); input.type = 'hidden'; input.name = 'location_structure_claim_ids[]'; input.value = String(id); form.appendChild(input); }
    input.dataset.locationStructureClaimId = '';
    input.dataset.locationStructureClaimDepth = String(depth);
    if (claimType) input.dataset.locationStructureClaimType = claimType;
};
const addPendingStructuralPanel = (host, wrapper, payload, parentIdentity, depth) => {
    const choices = (Array.isArray(payload?.structural_choices) ? payload.structural_choices : [])
        .filter((choice) => ['no_urban_region', 'no_neighborhood'].includes(String(choice?.claim_type || '')));
    if (!String(parentIdentity || '').startsWith('proposal:') || !choices.length) return;

    const proposalId = Number(String(parentIdentity).slice(9));
    if (!proposalId) return;

    const shell = document.createElement('div');
    shell.className = 'location-exception-shell';
    shell.dataset.locationExceptionShell = '';

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'btn btn-link btn-sm p-0 text-decoration-none';
    toggle.dataset.locationExceptionToggle = '';
    toggle.textContent = choices.some((choice) => choice.claim_type === 'no_urban_region')
        ? 'منطقه من در فهرست نیست'
        : 'محله من در فهرست نیست';
    toggle.setAttribute('aria-expanded', 'false');

    const panel = document.createElement('div');
    panel.className = 'location-exception-panel border rounded-3 p-3 mt-2 bg-light d-none vstack gap-3';
    panel.dataset.locationExceptionPanel = '';

    const state = document.createElement('div');
    state.className = 'small text-secondary';
    state.setAttribute('aria-live', 'polite');

    choices.forEach((choice) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline-secondary btn-sm align-self-start';
        button.dataset.locationStructuralChoice = choice.claim_type;
        button.textContent = choice.claim_type === 'no_urban_region'
            ? 'این شهر منطقه‌بندی ندارد'
            : 'این محدوده محله‌بندی ندارد';

        let claimId = Number(choice.claim_id);
        const selected = Number.isInteger(claimId)
            && claimId > 0
            && pendingStructuralClaimIds(host).includes(claimId);
        button.setAttribute('aria-pressed', selected ? 'true' : 'false');

        if (choice.status === 'approved' && claimId > 0) {
            rememberPendingStructuralClaim(host, claimId, depth, choice.claim_type);
            button.disabled = true;
            button.setAttribute('aria-pressed', 'true');
            state.textContent = 'این وضعیت قبلاً تأیید شده است.';
        }

        button.addEventListener('click', async () => {
            if (Number.isInteger(claimId) && claimId > 0 && pendingStructuralClaimIds(host).includes(claimId)) {
                removePendingStructuralClaimIds(host, [claimId]);
                button.setAttribute('aria-pressed', 'false');
                state.textContent = 'این اعلام از مسیر فعلی شما برداشته شد.';
                return;
            }

            const csrf = host.closest('form')?.querySelector('input[name="_token"]')?.value || '';
            button.disabled = true;
            state.textContent = 'در حال ثبت...';
            try {
                const response = await fetch(
                    `/location/proposals/${encodeURIComponent(proposalId)}/structure-claims`,
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                        },
                        body: JSON.stringify({ claim_type: choice.claim_type }),
                    },
                );
                const result = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(result.message || 'ثبت وضعیت ساختاری ممکن نشد.');

                claimId = Number(result.id);
                rememberPendingStructuralClaim(host, claimId, depth, choice.claim_type);
                button.setAttribute('aria-pressed', 'true');
                state.textContent = 'ثبت شد و در انتظار بررسی است.';

                const url = pendingStructuralClaimContextUrl(
                    `/location/proposals/${encodeURIComponent(proposalId)}/children`,
                    host,
                );
                const refreshed = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!refreshed.ok) throw new Error('دریافت مسیر بعد ممکن نشد.');

                panel.classList.add('d-none');
                toggle.setAttribute('aria-expanded', 'false');
                removeAfter(host.querySelector('[data-location-levels]'), depth - 1);
                appendPendingLevel(host, await refreshed.json(), depth, parentIdentity);
            } catch (error) {
                button.disabled = false;
                state.textContent = error?.message || 'ثبت وضعیت ساختاری ممکن نشد.';
            }
        });

        panel.appendChild(button);
    });

    panel.appendChild(state);
    toggle.addEventListener('click', () => {
        const opening = panel.classList.contains('d-none');
        panel.classList.toggle('d-none', !opening);
        toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
    });
    shell.append(toggle, panel);
    wrapper.appendChild(shell);
};

const addProposalPanel = (host, wrapper, payload, parentIdentity, select) => {
    const types = allowedTypes(payload); if (!types.length) return;
    const shell = document.createElement('div'); shell.dataset.locationProposalShell = ''; shell.className = 'location-proposal-shell';
    const toggle = document.createElement('button'); toggle.type = 'button'; toggle.dataset.locationProposalToggle = ''; toggle.className = 'btn btn-sm location-proposal-toggle'; toggle.textContent = types.length === 1 ? `+ افزودن ${localizeLocationTypeLabel(types[0])} جدید` : '+ افزودن مکان جدید';
    const panel = document.createElement('div'); panel.dataset.locationProposalPanel = ''; panel.className = 'location-proposal-panel d-none';
    const heading = document.createElement('div'); heading.className = 'location-proposal-heading'; heading.textContent = types.length === 1 ? `افزودن ${localizeLocationTypeLabel(types[0])} جدید` : 'افزودن مکان جدید';
    const type = document.createElement('select'); type.dataset.locationProposalType = ''; type.className = 'form-select form-select-sm';
    types.forEach((item) => { const option = document.createElement('option'); option.value = String(item.id); option.textContent = localizeLocationTypeLabel(item); type.appendChild(option); });
    if (types.length === 1) { type.value = String(types[0].id); type.hidden = true; type.setAttribute('aria-hidden', 'true'); }
    const name = document.createElement('input'); name.type = 'text'; name.className = 'form-control form-control-sm'; name.maxLength = 255; name.placeholder = types.length === 1 ? `نام ${localizeLocationTypeLabel(types[0])} را وارد کنید` : 'نام مکان را وارد کنید'; name.setAttribute('aria-label', 'نام مکان پیشنهادی');
    const actions = document.createElement('div'); actions.className = 'location-proposal-actions';
    const submit = document.createElement('button'); submit.type = 'button'; submit.dataset.locationProposalSubmit = ''; submit.className = 'btn btn-primary btn-sm'; submit.textContent = types.length === 1 ? `ثبت ${localizeLocationTypeLabel(types[0])}` : 'ثبت مکان';
    const cancel = document.createElement('button'); cancel.type = 'button'; cancel.dataset.locationProposalCancel = ''; cancel.className = 'btn btn-link btn-sm'; cancel.textContent = 'انصراف';
    const feedback = document.createElement('div'); feedback.className = 'small text-secondary'; feedback.setAttribute('aria-live', 'polite');
    const closePanel = () => { panel.classList.add('d-none'); toggle.setAttribute('aria-expanded', 'false'); };
    toggle.setAttribute('aria-expanded', 'false');
    toggle.addEventListener('click', () => { const opening = panel.classList.contains('d-none'); panel.classList.toggle('d-none'); toggle.setAttribute('aria-expanded', opening ? 'true' : 'false'); if (opening) name.focus(); });
    cancel.addEventListener('click', () => { closePanel(); feedback.textContent = ''; });
    submit.addEventListener('click', async () => {
        const canonicalName = name.value.trim(); if (!canonicalName) { feedback.textContent = 'نام مکان را وارد کنید.'; return; }
        const csrf = host.closest('form')?.querySelector('input[name="_token"]')?.value || ''; submit.disabled = true; submit.setAttribute('aria-busy', 'true'); feedback.classList.remove('text-danger'); feedback.textContent = 'در حال بررسی و ثبت پیشنهاد...';
        try {
            const response = await fetch('/locations/proposals', { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) }, body: JSON.stringify({ ...proposalParentPayload(parentIdentity), location_type_id: Number(type.value), canonical_name: canonicalName, localized_names: { fa: canonicalName }, location_structure_claim_ids: pendingStructuralClaimIds(host) }) });
            if (!response.ok) throw new Error(`Location proposal request failed: ${response.status}`); const result = await response.json(); if (result?.kind !== 'proposal') throw new Error('Unexpected canonical result below pending parent.');
            let option = Array.from(select.options).find((item) => item.value === `proposal:${result.id}`); if (!option) { option = document.createElement('option'); option.value = `proposal:${result.id}`; option.textContent = `${result.canonical_name} — در انتظار تأیید`; option.dataset.locationPendingBadge = ''; option.dataset.typeKey = result.type_key || ''; select.appendChild(option); }
            select.value = option.value; setSelection(host, result.id, result.type_key || option.dataset.typeKey || ''); closePanel(); feedback.textContent = ''; select.dispatchEvent(new CustomEvent('location-proposal-created', { bubbles: true, detail: { proposalId: Number(result.id) } }));
        } catch (error) { console.warn('EarthCoop deeper proposal failed:', error); feedback.textContent = 'ثبت پیشنهاد مکان ممکن نشد. متن شما حفظ شده است؛ دوباره تلاش کنید.'; feedback.classList.add('text-danger'); } finally { submit.disabled = false; submit.removeAttribute('aria-busy'); }
    });
    actions.append(submit, cancel); panel.append(heading, type, name, actions, feedback); shell.append(toggle, panel); wrapper.appendChild(shell);
};
const appendPendingLevel = (host, payload, depth, parentIdentity, selectedTypeKey = null) => {
    const levels = host.querySelector('[data-location-levels]'); if (!levels) return;
    const allProposals = (Array.isArray(payload?.proposals) ? payload.proposals : []).filter(openProposal); const allTypes = allowedTypes(payload);
    const keys = [...new Set([...allProposals.map((item) => item.type_key), ...allTypes.map((item) => item.key)].filter(Boolean))];
    if (!selectedTypeKey && keys.length > 1) {
        const choice = document.createElement('div'); choice.dataset.locationDepth = String(depth); choice.dataset.locationTypeChoice = ''; choice.className = 'vstack gap-2';
        const label = document.createElement('div'); label.className = 'form-label small text-secondary mb-0'; label.textContent = 'نوع ادامه مسیر';
        const actions = document.createElement('div'); actions.className = 'd-flex flex-wrap gap-2';
        keys.forEach((key) => { const button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-outline-secondary btn-sm'; button.dataset.locationTypeChoiceKey = key; button.textContent = localizeLocationTypeLabel({ key, label: key }); button.addEventListener('click', () => {
            const locationInput = host.querySelector('[data-location-id][name="location_id"]'); const proposalInput = host.querySelector('[data-location-proposal-id][name="location_proposal_id"]'); const submit = host.closest('form')?.querySelector('[data-location-submit]');
            if (locationInput) locationInput.value = ''; if (proposalInput) proposalInput.value = ''; if (submit) submit.disabled = true;
            choice.remove(); appendPendingLevel(host, payload, depth, parentIdentity, key);
        }); actions.appendChild(button); });
        choice.append(label, actions); levels.appendChild(choice); status(host, 'نوع ادامه مسیر را انتخاب کنید.'); return;
    }
    const proposals = selectedTypeKey ? allProposals.filter((item) => item.type_key === selectedTypeKey) : allProposals; const types = selectedTypeKey ? allTypes.filter((item) => item.key === selectedTypeKey) : allTypes;
    if (!proposals.length && !types.length) { status(host, 'در این مسیر پیشنهادی، سطح دقیق‌تری برای ثبت وجود ندارد.'); return; }
    const wrapper = document.createElement('div'); wrapper.dataset.locationDepth = String(depth); wrapper.className = 'vstack gap-2';
    const label = document.createElement('label'); label.className = 'form-label small text-secondary mb-0'; const visibleKeys = [...new Set([...proposals.map((item) => item.type_key), ...types.map((item) => item.key)].filter(Boolean))]; label.textContent = visibleKeys.map((key) => localizeLocationTypeLabel({ key, label: key })).join(' / ');
    const select = document.createElement('select'); select.className = 'form-select'; select.dataset.locationSelect = String(depth); const empty = document.createElement('option'); empty.value = ''; empty.textContent = 'یک گزینه را انتخاب کنید'; select.appendChild(empty);
    proposals.forEach((item) => { const option = document.createElement('option'); option.value = `proposal:${item.id}`; option.textContent = `${item.label} — در انتظار تأیید`; option.dataset.locationPendingBadge = ''; option.dataset.typeKey = item.type_key || ''; select.appendChild(option); });
    wrapper.append(label, select);
    addPendingStructuralPanel(host, wrapper, payload, parentIdentity, depth);
    const proposalPayload = selectedTypeKey
        ? { ...payload, proposals, allowed_types: types, effective_allowed_types: types }
        : payload;
    addProposalPanel(host, wrapper, proposalPayload, parentIdentity, select);
    levels.appendChild(wrapper);
};

async function loadProposalChildren(host, select, proposalId) {
    const levels = host.querySelector('[data-location-levels]'); if (!levels) return; const depth = Number(select.dataset.locationSelect || 0); removeAfter(levels, depth);
    const isRegistration = (host.dataset.locationPurpose || host.dataset.locationSelectorContext) === 'registration';
    const selectedTypeKey = select.options[select.selectedIndex]?.dataset?.typeKey || '';
    if (isRegistration && selectedTypeKey === 'neighborhood') {
        status(host, 'سطح پایهٔ محل سکونت شما مشخص شد. برای تکمیل ثبت‌نام، «ثبت محل سکونت و ادامه» را بزنید؛ جزئیات محلی مانند خیابان، کوچه، مجتمع یا ساختمان را می‌توانید بعداً از بخش «مکان و حکمرانی من» تکمیل کنید.');
        return;
    }
    status(host, 'در حال دریافت گزینه‌های سطح بعد...');
    try {
        const response = await fetch(pendingStructuralClaimContextUrl(`/location/proposals/${encodeURIComponent(proposalId)}/children`, host), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!response.ok) throw new Error(`Proposal children request failed: ${response.status}`);
        const payload = await response.json();
        const allProposals = (Array.isArray(payload?.proposals) ? payload.proposals : []).filter(openProposal);
        const allTypes = allowedTypes(payload);
        const hasStructuralChoices = Array.isArray(payload?.structural_choices) && payload.structural_choices.length > 0;
        if (!allProposals.length && !allTypes.length && hasStructuralChoices) {
            const wrapper = document.createElement('div'); wrapper.dataset.locationDepth = String(depth + 1); wrapper.className = 'vstack gap-2';
            addPendingStructuralPanel(host, wrapper, payload, `proposal:${proposalId}`, depth + 1);
            levels.appendChild(wrapper);
        } else {
            appendPendingLevel(host, payload, depth + 1, `proposal:${proposalId}`);
        }
        status(host, 'این مکان در انتظار تأیید است؛ وضعیت ساختاری یا مسیر دقیق‌تر را مشخص کنید.');
    } catch (error) { console.warn('EarthCoop proposal children failed:', error); status(host, 'دریافت سطح بعد ممکن نشد؛ انتخاب فعلی شما حفظ شده است.', true); }
}

if (typeof document !== 'undefined') {
    document.addEventListener('change', (event) => {
        const select = event.target?.closest?.('[data-location-select]'); if (!select) return; const host = select.closest('[data-location-selector]'); // The canonical selector owns registration proposal changes, including its structural terminal signal.
        // Legacy capture must not stop that selector's change handler or render micro levels.
        if (!host || ['project-scope', 'registration', 'profile', 'admin-user-residence'].includes(host.dataset.locationPurpose || host.dataset.locationSelectorContext)) return;
        const value = String(select.value || ''); if (!value.startsWith('proposal:')) return; event.preventDefault(); event.stopImmediatePropagation(); const id = Number(value.slice(9)); const typeKey = select.options[select.selectedIndex]?.dataset?.typeKey || ''; setSelection(host, id, typeKey); void loadProposalChildren(host, select, id);
    }, true);
    document.addEventListener('location-proposal-created', (event) => {
        const select = event.target?.closest?.('[data-location-select]'); const host = select?.closest?.('[data-location-selector]');
        const id = Number(event.detail?.proposalId || 0); if (!select || !host || !id) return;
        // Registration proposals are reconciled by the canonical selector's onCreated callback.
        if (['project-scope', 'registration', 'profile', 'admin-user-residence'].includes(host.dataset.locationPurpose || host.dataset.locationSelectorContext)) return;
        void loadProposalChildren(host, select, id);
    });
}

export { initializeLocationSelector, normalizePickerPayload, registrationPayload, microContinuationTypes, filterPayloadByType, projectScopePayload, projectScopeSelectionValues, pickerLevelLabel, selectionValues, shouldRenderNextLevel, shouldStopRegistrationAtProposal, locationDisplayLabel, localizeLocationTypeLabel, proposalParentPayload };
