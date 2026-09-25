const selectors = typeof document !== 'undefined'
    ? Array.from(document.querySelectorAll('[data-location-selector]'))
    : [];

const OPEN_PROPOSAL_STATUSES = new Set(['pending', 'ready_for_review', 'needs_evidence']);
const MICRO_LOCATION_TYPES = new Set(['street', 'alley', 'complex', 'building']);
const UI_STRUCTURAL_CLAIM_TYPES = new Set(['no_urban_region', 'no_neighborhood']);
const PICKER_STATES = Object.freeze({ loading: 'loading', empty: 'empty', error: 'error', stale: 'stale', ready: 'ready' });
const TYPE_LABELS = Object.freeze({
    global: 'جهانی', continent: 'قاره', country: 'کشور', province: 'استان / ایالت', county: 'شهرستان / ناحیه',
    section: 'بخش', city: 'شهر', rural_district: 'دهستان', village: 'روستا', settlement: 'آبادی', urban_region: 'منطقه',
    neighborhood: 'محله', street: 'خیابان', alley: 'کوچه', complex: 'مجتمع', building: 'ساختمان',
});
const locationDisplayLabel = (item) => {
    const name = String(item?.label || item?.canonical_name || '').trim();
    const prefix = TYPE_LABELS[item?.type_key] || '';
    if (!prefix || !name) return name;
    const normalizedPrefix = prefix.split(' / ')[0].trim();
    if (item?.type_key === 'urban_region' && name.startsWith('منطقه شهری ')) {
        return 'منطقه ' + name.slice('منطقه شهری '.length).trim();
    }
    if (item?.type_key === 'province' && (name === 'ایالت' || name.startsWith('ایالت '))) {
        return name;
    }
    return name === normalizedPrefix || name.startsWith(normalizedPrefix + ' ') ? name : normalizedPrefix + ' ' + name;
};
const isActiveLocation = (item) => item?.status === undefined || item?.status === null || item?.status === 'active';
const isOpenProposal = (item) => OPEN_PROPOSAL_STATUSES.has(String(item?.status || '')) && item?.selectable !== false;
const normalizePickerPayload = (payload) => ({
    locations: (Array.isArray(payload?.data) ? payload.data : []).filter(isActiveLocation),
    proposals: (Array.isArray(payload?.proposals) ? payload.proposals : []).filter(isOpenProposal),
    allowedTypes: Array.isArray(payload?.allowed_types) ? payload.allowed_types : [],
    effectiveAllowedTypes: Array.isArray(payload?.effective_allowed_types) ? payload.effective_allowed_types : [],
    structuralChoices: Array.isArray(payload?.structural_choices) ? payload.structural_choices : [],
    officialGovernanceBase: payload?.official_governance_base === true,
    registrationEndpointAllowed: payload?.registration_endpoint_allowed === true,
});
const registrationPayload = (payload) => {
    const continuationTypes = payload.effectiveAllowedTypes.length ? payload.effectiveAllowedTypes : payload.allowedTypes;
    const allowedTypeIds = new Set(continuationTypes.filter((type) => !MICRO_LOCATION_TYPES.has(type?.key)).map((type) => Number(type.id)));
    return {
        ...payload,
        locations: payload.locations.filter((item) => !MICRO_LOCATION_TYPES.has(item?.type_key)),
        proposals: payload.proposals.filter((item) => !MICRO_LOCATION_TYPES.has(item?.type_key)),
        allowedTypes: continuationTypes.filter((type) => allowedTypeIds.has(Number(type.id))),
        effectiveAllowedTypes: [],
    };
};
const microContinuationTypes = (payload) => {
    const types = payload.effectiveAllowedTypes.length ? payload.effectiveAllowedTypes : payload.allowedTypes;
    const byKey = new Map();
    types.filter((type) => MICRO_LOCATION_TYPES.has(type?.key)).forEach((type) => byKey.set(type.key, type));
    [...payload.locations, ...payload.proposals].filter((item) => MICRO_LOCATION_TYPES.has(item?.type_key)).forEach((item) => {
        if (!byKey.has(item.type_key)) byKey.set(item.type_key, { id: item.location_type_id || null, key: item.type_key, label: TYPE_LABELS[item.type_key] || item.type_key, proposal_allowed: false });
    });
    return [...byKey.values()];
};
const filterPayloadByType = (payload, typeKey) => ({
    ...payload,
    locations: payload.locations.filter((item) => item?.type_key === typeKey),
    proposals: payload.proposals.filter((item) => item?.type_key === typeKey),
    allowedTypes: payload.allowedTypes.filter((type) => type?.key === typeKey),
    effectiveAllowedTypes: payload.effectiveAllowedTypes.filter((type) => type?.key === typeKey),
});
const projectScopePayload = (payload) => ({
    locations: payload.locations, proposals: [],
    allowedTypes: payload.allowedTypes.map((type) => ({ ...type, proposal_allowed: false })),
    effectiveAllowedTypes: [], structuralChoices: [], officialGovernanceBase: false,
});
const selectionValues = (item) => {
    const identity = String(item?.identity || '');
    if (identity.startsWith('proposal:')) {
        if (!isOpenProposal(item)) return { locationId: '', proposalId: '' };
        return { locationId: '', proposalId: String(item?.id || identity.slice('proposal:'.length)) };
    }
    if (identity.startsWith('location:') || item?.id) {
        if (!isActiveLocation(item)) return { locationId: '', proposalId: '' };
        return { locationId: String(item?.id || identity.slice('location:'.length)), proposalId: '' };
    }
    return { locationId: '', proposalId: '' };
};
const projectScopeSelectionValues = (item) => {
    if (!isActiveLocation(item)) return { locationId: '', governanceAreaId: '' };
    const identity = String(item?.identity || '');
    if (identity.startsWith('governance:')) return { locationId: '', governanceAreaId: String(item?.governance_area_id || item?.id || identity.slice('governance:'.length)) };
    if (identity.startsWith('location:') || item?.id) return { locationId: String(item?.id || identity.slice('location:'.length)), governanceAreaId: '' };
    return { locationId: '', governanceAreaId: '' };
};
const shouldRenderNextLevel = (payload) => {
    const normalized = payload?.locations ? payload : normalizePickerPayload(payload);
    return normalized.locations.length > 0
        || normalized.proposals.length > 0
        || normalized.structuralChoices.some((choice) => UI_STRUCTURAL_CLAIM_TYPES.has(String(choice?.claim_type || '')))
        || normalized.allowedTypes.some((type) => type?.proposal_allowed === true);
};
const shouldStopRegistrationAtProposal = (context, item) =>
    context === 'registration' && item?.type_key === 'neighborhood';
const pickerItems = (payload) => [
    ...payload.locations.map((item) => ({ ...item, picker_kind: String(item?.identity || '').startsWith('governance:') ? 'governance' : 'location' })),
    ...payload.proposals.map((item) => ({ ...item, picker_kind: 'proposal' })),
];
const pickerLevelLabel = (payload, depth) => {
    const keys = [...(Array.isArray(payload?.locations) ? payload.locations : []).map((item) => item?.type_key), ...(Array.isArray(payload?.allowedTypes) ? payload.allowedTypes : []).map((item) => item?.key)]
        .filter(Boolean).filter((key, index, all) => all.indexOf(key) === index);
    if (keys.length > 0 && keys.every((key) => Object.prototype.hasOwnProperty.call(TYPE_LABELS, key))) return keys.map((key) => TYPE_LABELS[key]).join(' / ');
    return `سطح مکانی ${depth + 1}`;
};
const buildMicroTypeChoice = (host, payload, depth, onChosen) => {
    const types = microContinuationTypes(payload); if (types.length < 2) return null;
    const wrapper = document.createElement('div'); wrapper.dataset.locationDepth = String(depth); wrapper.dataset.locationTypeChoice = ''; wrapper.className = 'vstack gap-2';
    const label = document.createElement('div'); label.className = 'form-label fw-bold mb-0'; label.textContent = 'نشانی شما در ادامه کدام است؟';
    const hint = document.createElement('div'); hint.className = 'small text-secondary'; hint.textContent = 'فقط یک نوع را انتخاب کنید؛ سپس گزینه‌های همان نوع نمایش داده می‌شوند.';
    const actions = document.createElement('div'); actions.className = 'd-flex flex-wrap gap-2';
    types.forEach((type) => {
        const button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-outline-secondary btn-sm'; button.dataset.locationTypeChoiceKey = type.key; button.textContent = TYPE_LABELS[type.key] || type.label || type.key;
        button.addEventListener('click', () => onChosen(type.key, wrapper)); actions.appendChild(button);
    });
    wrapper.append(label, hint, actions); return wrapper;
};
const buildSelect = (host, payload, depth) => {
    const wrapper = document.createElement('div'); wrapper.dataset.locationDepth = String(depth); wrapper.className = 'vstack gap-2';
    const label = document.createElement('label');
    const selectId = `location-level-${depth}-${Math.random().toString(36).slice(2, 8)}`;
    label.htmlFor = selectId; label.className = 'form-label small text-secondary mb-0'; label.textContent = pickerLevelLabel(payload, depth);
    const select = document.createElement('select'); select.id = selectId; select.className = 'form-select'; select.dataset.locationSelect = String(depth); select.setAttribute('aria-label', label.textContent);
    const placeholder = document.createElement('option'); placeholder.value = ''; placeholder.textContent = host.dataset.emptyLabel || 'یک گزینه را انتخاب کنید'; select.appendChild(placeholder);
    pickerItems(payload).forEach((item) => {
        const option = document.createElement('option'); option.value = item.identity || `${item.picker_kind}:${item.id}`;
        option.textContent = item.picker_kind === 'proposal' ? `${item.label} — در انتظار تأیید` : item.label;
        option.dataset.endpoint = item.is_residence_endpoint ? '1' : '0'; option.dataset.hasChildren = item.has_children ? '1' : '0'; option.dataset.typeKey = item.type_key || ''; option.dataset.pickerKind = item.picker_kind;
        select.appendChild(option);
    });
    wrapper.append(label, select); return { wrapper, select };
};
const STRUCTURAL_CLAIM_GROUPS = Object.freeze({
    urban_region: {
        claimTypes: ['no_urban_region'],
        question: 'اگر این شهر منطقه‌بندی ندارد',
    },
    neighborhood: {
        claimTypes: ['no_neighborhood'],
        question: 'اگر این محدوده محله‌بندی ندارد',
    },
});
const STRUCTURAL_CLAIM_COPY = Object.freeze({
    no_urban_region: {
        title: 'این شهر منطقه‌بندی ندارد',
        detail: 'خود شهر نمایندهٔ این سطح رسمی است و مسیر مکانی می‌تواند به سطح بعد ادامه پیدا کند.',
    },
    no_neighborhood: {
        title: 'این محدوده محله‌بندی ندارد',
        detail: 'پایان حوزهٔ رسمی به معنی پایان نشانی نیست؛ در ویرایش مکان می‌توانید خیابان و جزئیات پایین‌تر را ادامه دهید.',
    },
});
const rememberStructuralClaim = (host, claimId, depth, claimType = '') => {
    const id = Number(claimId); if (!Number.isInteger(id) || id <= 0) return;
    const form = host.closest('[data-location-form]') || host.closest('form'); if (!form) return;
    const selector = `input[data-location-structure-claim-id][value="${id}"]`;
    const existing = form.querySelector(selector);
    if (existing) {
        existing.dataset.locationStructureClaimDepth = String(depth);
        if (claimType) existing.dataset.locationStructureClaimType = claimType;
        return;
    }
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'location_structure_claim_ids[]';
    input.value = String(id);
    input.dataset.locationStructureClaimId = '';
    input.dataset.locationStructureClaimDepth = String(depth);
    if (claimType) input.dataset.locationStructureClaimType = claimType;
    form.appendChild(input);
};
const clearStructuralClaimsAfterDepth = (form, depth) => {
    if (!form) return;
    form.querySelectorAll('[data-location-structure-claim-id][data-location-structure-claim-depth]').forEach((input) => {
        if (Number(input.dataset.locationStructureClaimDepth) >= depth) input.remove();
    });
};
const clearStructuralClaimTypes = (form, claimTypes) => {
    if (!form) return;
    const types = new Set(claimTypes);
    form.querySelectorAll('[data-location-structure-claim-id][data-location-structure-claim-type]').forEach((input) => {
        if (types.has(input.dataset.locationStructureClaimType)) input.remove();
    });
};

const selectedStructuralClaimIds = (form) => {
    if (!form) return [];
    return Array.from(form.querySelectorAll('input[name="location_structure_claim_ids[]"]'))
        .map((input) => Number(input.value))
        .filter((id) => Number.isInteger(id) && id > 0);
};
const forgetStructuralClaim = (form, claimId) => {
    if (!form) return;
    const id = Number(claimId);
    if (!Number.isInteger(id) || id <= 0) return;
    form.querySelectorAll('[data-location-structure-claim-id]').forEach((input) => {
        if (Number(input.value) === id) input.remove();
    });
};
const structuralClaimContextUrl = (url, form) => {
    const ids = selectedStructuralClaimIds(form);
    if (!ids.length) return url;
    const separator = url.includes('?') ? '&' : '?';
    const query = ids.map((id) => `location_structure_claim_ids%5B%5D=${encodeURIComponent(id)}`).join('&');
    return `${url}${separator}${query}`;
};

const buildStructuralClaimPanel = (host, choices, locationId, depth, onChanged, proposalId = null, referenceSettlementExternalId = null) => {
    if ((!locationId && !proposalId && !referenceSettlementExternalId) || !Array.isArray(choices) || choices.length === 0) return null;
    const shell = document.createElement('div');
    shell.className = 'location-structural-claims vstack gap-3';
    shell.dataset.locationStructuralClaims = '';
    const byType = new Map(choices.map((choice) => [choice.claim_type, choice]));

    Object.values(STRUCTURAL_CLAIM_GROUPS).forEach((group) => {
        const available = group.claimTypes.map((type) => byType.get(type)).filter(Boolean);
        if (available.length === 0) return;

        const section = document.createElement('div');
        section.className = 'border-top pt-3';
        const title = document.createElement('div');
        title.className = 'small fw-semibold mb-2';
        title.textContent = group.question;
        const actions = document.createElement('div');
        actions.className = 'vstack gap-2';
        const state = document.createElement('div');
        state.className = 'small text-secondary';
        state.setAttribute('aria-live','polite');
        const form = host.closest('[data-location-form]') || host.closest('form');

        available.forEach((choice) => {
            const copy = STRUCTURAL_CLAIM_COPY[choice.claim_type]; if (!copy) return;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-secondary btn-sm align-self-start';
            button.dataset.locationStructuralChoice = choice.claim_type;
            button.textContent = copy.title;
            button.setAttribute('aria-pressed', 'false');

            const statusValue = String(choice.status || 'available');
            let activeClaimId = Number(choice.claim_id);
            const explicitlySelected = Number.isInteger(activeClaimId) && activeClaimId > 0 && selectedStructuralClaimIds(form).includes(activeClaimId);
            if (statusValue === 'approved' && activeClaimId > 0) {
                rememberStructuralClaim(host, activeClaimId, depth, choice.claim_type);
                button.disabled = true;
                button.setAttribute('aria-pressed','true');
                state.textContent = 'این وضعیت قبلاً تأیید شده است.';
            } else if (OPEN_PROPOSAL_STATUSES.has(statusValue)) {
                if (explicitlySelected) rememberStructuralClaim(host, activeClaimId, depth, choice.claim_type);
                button.setAttribute('aria-pressed', explicitlySelected ? 'true' : 'false');
                state.textContent = explicitlySelected
                    ? 'این اعلام در مسیر شما انتخاب شده و در انتظار بررسی است.'
                    : 'این اعلام قبلاً ثبت شده است؛ در صورت تطابق می‌توانید همان را انتخاب کنید.';
            }

            button.addEventListener('click', async () => {
                if (Number.isInteger(activeClaimId) && activeClaimId > 0 && selectedStructuralClaimIds(form).includes(activeClaimId)) {
                    forgetStructuralClaim(form, activeClaimId);
                    button.setAttribute('aria-pressed', 'false');
                    state.textContent = 'این اعلام از مسیر فعلی شما برداشته شد.';
                    await onChanged({ mode: 'cleared', id: activeClaimId, claim_type: choice.claim_type });
                    return;
                }

                const csrf = form?.querySelector('input[name="_token"]')?.value || '';
                button.disabled = true;
                state.textContent = 'در حال ثبت...';
                try {
                    const endpoint = proposalId
                        ? `/location/proposals/${encodeURIComponent(proposalId)}/structure-claims`
                        : (referenceSettlementExternalId
                            ? `/location/reference-settlements/${encodeURIComponent(referenceSettlementExternalId)}/structure-claims`
                            : '/locations/structure-claims');
                    const body = proposalId || referenceSettlementExternalId
                        ? { claim_type: choice.claim_type }
                        : { location_id: Number(locationId), claim_type: choice.claim_type };
                    const response = await fetch(
                        endpoint,
                        {
                            method:'POST',
                            credentials:'same-origin',
                            headers:{ Accept:'application/json','Content-Type':'application/json', ...(csrf ? {'X-CSRF-TOKEN':csrf}:{}) },
                            body:JSON.stringify(body),
                        },
                    );
                    const result = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(result.message || ('Structural claim request failed: ' + response.status));
                    activeClaimId = Number(result.id);
                    rememberStructuralClaim(host, activeClaimId, depth, choice.claim_type);
                    button.setAttribute('aria-pressed','true');
                    state.textContent = 'ثبت شد و در انتظار بررسی است.';
                    await onChanged(result);
                } catch (error) {
                    console.warn('EarthCoop structural claim failed:', error);
                    button.disabled = false;
                    state.textContent = error?.message || 'ثبت این وضعیت ممکن نشد؛ دوباره تلاش کنید.';
                }
            });

            const detail = document.createElement('div');
            detail.className = 'small text-secondary';
            detail.textContent = copy.detail;
            actions.append(button, detail);
        });
        section.append(title, actions, state);
        shell.appendChild(section);
    });

    return shell.children.length ? shell : null;
};

const exceptionTargetKeys = (payload) => {
    const keys = [
        ...(Array.isArray(payload?.locations) ? payload.locations : []).map((item) => item?.type_key),
        ...(Array.isArray(payload?.proposals) ? payload.proposals : []).map((item) => item?.type_key),
        ...(Array.isArray(payload?.effectiveAllowedTypes) ? payload.effectiveAllowedTypes : []).map((item) => item?.key),
        ...(Array.isArray(payload?.allowedTypes) ? payload.allowedTypes : []).map((item) => item?.key),
    ].filter(Boolean);
    return [...new Set(keys)];
};
const exceptionLinkLabel = (payload) => {
    const keys = exceptionTargetKeys(payload);
    const priority = [
        ['urban_region', 'منطقه من در فهرست نیست'],
        ['village', 'روستا یا آبادی من در فهرست نیست'],
        ['settlement', 'روستا یا آبادی من در فهرست نیست'],
        ['neighborhood', 'محله من در فهرست نیست'],
        ['street', 'خیابان من در فهرست نیست'],
        ['alley', 'کوچه من در فهرست نیست'],
        ['complex', 'مجتمع من در فهرست نیست'],
        ['building', 'ساختمان من در فهرست نیست'],
    ];
    for (const [key, label] of priority) if (keys.includes(key)) return label;
    return 'مکان من در فهرست نیست';
};
const closeExceptionPanel = (panel) => {
    if (!panel) return;
    panel.classList.add('d-none');
    const shell = panel.closest('[data-location-exception-shell]');
    const toggle = shell?.querySelector('[data-location-exception-toggle]');
    toggle?.setAttribute('aria-expanded', 'false');
};

const buildProposalPanel = (host, allowedTypes, parentLocationId, onCreated, parentProposalId = null, parentReferenceSettlementId = null) => {
    const proposableTypes = allowedTypes.filter((type) => type?.proposal_allowed === true); if ((!parentLocationId && !parentProposalId && !parentReferenceSettlementId) || proposableTypes.length === 0) return null;
    const shell = document.createElement('div'); shell.className = 'vstack gap-2'; shell.dataset.locationProposalShell = '';
    const proposalTypeLabel = (type) => TYPE_LABELS[type?.key] || type?.label || type?.key || 'مکان';
    const toggle = document.createElement('button'); toggle.type = 'button'; toggle.className = 'btn btn-outline-secondary btn-sm align-self-start'; toggle.textContent = proposableTypes.length === 1 ? `افزودن ${proposalTypeLabel(proposableTypes[0])} جدید` : 'افزودن مکان جدید'; toggle.dataset.locationProposalToggle = '';
    const panel = document.createElement('div'); panel.className = 'vstack gap-2 mt-3 d-none'; panel.dataset.locationProposalPanel = '';
    const typeLabel = document.createElement('label'); typeLabel.className = 'form-label small text-secondary mb-0'; typeLabel.textContent = 'نوع مکان پیشنهادی';
    const typeSelect = document.createElement('select'); typeSelect.className = 'form-select form-select-sm'; typeSelect.setAttribute('aria-label', 'نوع مکان پیشنهادی');
    if (proposableTypes.length === 1) { typeLabel.classList.add('d-none'); typeSelect.classList.add('d-none'); }
    proposableTypes.forEach((type) => { const option = document.createElement('option'); option.value = String(type.id); option.textContent = proposalTypeLabel(type); typeSelect.appendChild(option); });
    const nameLabel = document.createElement('label'); nameLabel.className = 'form-label small text-secondary mb-0'; nameLabel.textContent = proposableTypes.length === 1 ? `نام ${proposalTypeLabel(proposableTypes[0])}` : 'نام مکان';
    const nameInput = document.createElement('input'); nameInput.type = 'text'; nameInput.className = 'form-control form-control-sm'; nameInput.maxLength = 255; nameInput.placeholder = proposableTypes.length === 1 ? `نام ${proposalTypeLabel(proposableTypes[0])} را وارد کنید` : 'نام مکان را وارد کنید'; nameInput.setAttribute('aria-label', 'نام مکان پیشنهادی');
    const actions = document.createElement('div'); actions.className = 'd-flex flex-wrap gap-2';
    const submit = document.createElement('button'); submit.type = 'button'; submit.className = 'btn btn-primary btn-sm'; submit.textContent = proposableTypes.length === 1 ? `ثبت ${proposalTypeLabel(proposableTypes[0])}` : 'ثبت مکان';
    const cancel = document.createElement('button'); cancel.type = 'button'; cancel.className = 'btn btn-link btn-sm text-decoration-none'; cancel.textContent = 'انصراف';
    const feedback = document.createElement('div'); feedback.className = 'small text-secondary'; feedback.setAttribute('aria-live', 'polite');
    actions.append(submit, cancel); panel.append(typeLabel, typeSelect, nameLabel, nameInput, actions, feedback); shell.append(toggle, panel);
    toggle.setAttribute('aria-expanded', 'false');
    toggle.addEventListener('click', () => { const opening = panel.classList.contains('d-none'); panel.classList.toggle('d-none'); toggle.setAttribute('aria-expanded', opening ? 'true' : 'false'); if (opening) nameInput.focus(); });
    cancel.addEventListener('click', () => { panel.classList.add('d-none'); toggle.setAttribute('aria-expanded', 'false'); feedback.textContent = ''; });
    submit.addEventListener('click', async () => {
        const canonicalName = nameInput.value.trim(); if (!canonicalName) { feedback.textContent = 'نام مکان را وارد کنید.'; feedback.classList.add('text-danger'); return; }
        submit.disabled = true; feedback.classList.remove('text-danger'); feedback.textContent = 'در حال بررسی و ثبت پیشنهاد...';
        const form = host.closest('[data-location-form]') || host.closest('form'); const csrf = form?.querySelector('input[name="_token"]')?.value || ''; const locale = document.documentElement.lang || 'fa';
        try {
            const response = await fetch('/locations/proposals', { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) }, body: JSON.stringify({
                ...(parentProposalId
                    ? { parent_location_proposal_id: Number(parentProposalId) }
                    : (parentReferenceSettlementId
                        ? { parent_reference_settlement_id: Number(parentReferenceSettlementId) }
                        : { parent_location_id: Number(parentLocationId) })),
                location_type_id: Number(typeSelect.value),
                canonical_name: canonicalName,
                localized_names: { [locale]: canonicalName },
                location_structure_claim_ids: form ? Array.from(form.querySelectorAll('input[name="location_structure_claim_ids[]"]')).map((input) => Number(input.value)).filter((id) => Number.isInteger(id) && id > 0) : [],
            }) });
            if (!response.ok) throw new Error(`Location proposal request failed: ${response.status}`);
            const result = await response.json(); await onCreated(result); panel.classList.add('d-none'); feedback.textContent = '';
        } catch (error) { console.warn('EarthCoop location proposal could not be created:', error); feedback.textContent = 'ثبت پیشنهاد مکان ممکن نشد. متن شما حفظ شده است؛ دوباره تلاش کنید.'; feedback.classList.add('text-danger'); }
        finally { submit.disabled = false; }
    });
    return shell;
};

const buildExceptionDisclosure = ({
    host,
    payload,
    parentLocationId,
    parentProposalId,
    parentReferenceSettlementId,
    depth,
    structuralPanel,
    proposalPanel,
    enableReferenceSearch = false,
}) => {
    if (!structuralPanel && !proposalPanel && !enableReferenceSearch) return null;

    const shell = document.createElement('div');
    shell.className = 'location-exception-shell';
    shell.dataset.locationExceptionShell = '';
    shell.dataset.locationDepth = String(depth);

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'btn btn-link btn-sm p-0 mt-1 text-decoration-none align-self-start';
    toggle.dataset.locationExceptionToggle = '';
    toggle.textContent = exceptionLinkLabel(payload);
    toggle.setAttribute('aria-expanded', 'false');

    const panel = document.createElement('div');
    panel.className = 'location-exception-panel border rounded-3 p-3 mt-2 bg-light d-none vstack gap-3';
    panel.dataset.locationExceptionPanel = '';

    let referenceSlot = null;
    if (enableReferenceSearch) {
        referenceSlot = document.createElement('div');
        referenceSlot.dataset.locationReferenceExceptionSlot = '';
        panel.appendChild(referenceSlot);
    }
    if (structuralPanel) panel.appendChild(structuralPanel);
    if (proposalPanel) panel.appendChild(proposalPanel);

    toggle.addEventListener('click', () => {
        const opening = panel.classList.contains('d-none');
        panel.classList.toggle('d-none', !opening);
        toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
        if (!opening) return;

        host.dispatchEvent(new CustomEvent('earthcoop-location-exception-open', {
            bubbles: true,
            detail: {
                panel,
                referenceSlot,
                parentLocationId,
                parentProposalId,
                parentReferenceSettlementId,
                depth,
                targetKeys: exceptionTargetKeys(payload),
            },
        }));
    });

    shell.append(toggle, panel);
    return { shell, panel, toggle };
};

const initializeLocationSelector = async (host) => {
    const levels = host.querySelector('[data-location-levels]');
    const context = host.dataset.locationPurpose || host.dataset.locationSelectorContext || 'residence'; const isProjectScope = context === 'project-scope'; const isRegistration = context === 'registration';
    const locationId = isProjectScope ? host.querySelector('[data-location-id][name="target_location_id"]') : host.querySelector('[data-location-id][name="location_id"]');
    const proposalId = isProjectScope ? host.querySelector('[data-location-proposal-id]') : host.querySelector('[data-location-proposal-id][name="location_proposal_id"]');
    const governanceAreaId = isProjectScope ? host.querySelector('[data-project-governance-area-id][name="governance_area_id"]') : null;
    const form = host.closest('[data-location-form]') || host.closest('form'); const submit = form?.querySelector('[data-location-submit]'); const status = host.querySelector('[data-location-status]'); const locationPath = host.querySelector('[data-location-path]'); const country = (host.dataset.countryCode || '').trim();
    const selectedPath = new Map();
    const renderLocationPath = () => {
        const items = [...selectedPath.entries()]
            .sort((a,b) => a[0]-b[0])
            .map(([depth, item]) => ({
                depth,
                identity: String(item?.identity || ''),
                label: locationDisplayLabel(item),
                type_key: item?.type_key || '',
                pending: item?.picker_kind === 'proposal' || item?.picker_kind === 'reference_settlement',
                picker_kind: item?.picker_kind || '',
            }))
            .filter((item) => item.label);
        if (locationPath) locationPath.textContent = items.length ? items.map((item) => item.label).join(' / ') : 'مسیر انتخاب نشده';
        host.dispatchEvent(new CustomEvent('earthcoop-location-path-changed', {
            bubbles: true,
            detail: { items, context },
        }));
    };
    if (!levels || !locationId || (!isProjectScope && !proposalId) || (isProjectScope && !governanceAreaId)) return;
    const notifySelection = (item = null) => {
        if (isProjectScope) return;
        host.dispatchEvent(new CustomEvent('earthcoop-location-selection-changed', {
            bubbles: true,
            detail: { item, locationId: locationId.value || '', proposalId: proposalId?.value || '', context },
        }));
    };
    const setPickerState = (state, message, isError = false) => { host.dataset.locationState = state; host.setAttribute('aria-busy', state === PICKER_STATES.loading ? 'true' : 'false'); if (!status) return; status.textContent = message; status.classList.toggle('text-danger', isError); status.setAttribute('aria-live', isError ? 'assertive' : 'polite'); };
    const setStatus = (message, isError = false) => setPickerState(isError ? PICKER_STATES.error : PICKER_STATES.ready, message, isError);
    const clearSelection = () => { locationId.value = ''; if (proposalId) proposalId.value = ''; if (governanceAreaId) governanceAreaId.value = ''; if (submit && !isProjectScope) submit.disabled = true; notifySelection(null); };
    const setSelection = (item) => {
        if (isProjectScope) {
            const values = projectScopeSelectionValues(item); locationId.value = values.locationId; governanceAreaId.value = values.governanceAreaId; if (proposalId) proposalId.value = '';
            if (!values.locationId && !values.governanceAreaId) { setPickerState(PICKER_STATES.stale, 'این گزینه دیگر معتبر نیست. لطفاً یک محدوده رسمی و فعال را انتخاب کنید.', true); return; }
            setStatus(values.governanceAreaId ? 'این محدوده حکمرانی رسمی به‌عنوان هدف پروژه انتخاب شد. در صورت وجود گزینه‌های دقیق‌تر، می‌توانید مسیر را ادامه دهید.' : 'این مکان به‌عنوان مکان هدف پروژه انتخاب شد. در صورت وجود گزینه‌های دقیق‌تر، می‌توانید مسیر را ادامه دهید.'); return;
        }
        const values = selectionValues(item); locationId.value = values.locationId; if (proposalId) proposalId.value = values.proposalId; notifySelection(item);
        if (!values.locationId && !values.proposalId) { if (submit) submit.disabled = true; setPickerState(PICKER_STATES.stale, 'این گزینه دیگر معتبر نیست. لطفاً یک مکان فعال را انتخاب کنید.', true); return; }
        if (values.proposalId) {
            if (isRegistration) {
                if (item?.type_key === 'neighborhood') {
                    if (submit) submit.disabled = false;
                    setStatus('سطح پایهٔ محل سکونت شما مشخص شد. برای تکمیل ثبت‌نام، «ثبت محل سکونت و ادامه» را بزنید؛ جزئیات محلی مانند خیابان، کوچه، مجتمع یا ساختمان را می‌توانید بعداً از بخش «مکان و حکمرانی من» تکمیل کنید.');
                } else {
                    if (submit) submit.disabled = true;
                    setStatus('برای تکمیل ثبت‌نام، وضعیت ساختاری این محدوده را تا تعیین حوزهٔ پایه مشخص کنید.');
                }
                return;
            }
            if (submit) submit.disabled = false;
            setStatus('این مکان هنوز در انتظار تأیید است. می‌توانید ادامه دهید؛ حوزهٔ رسمی شما تا زمان تأیید بر مبنای نزدیک‌ترین مکان تأییدشده باقی می‌ماند.');
            return;
        }
        if (values.locationId && item?.is_residence_endpoint) {
            if (isRegistration && item?.type_key !== 'neighborhood') {
                if (submit) submit.disabled = true;
                setStatus('برای تکمیل ثبت‌نام، مسیر را تا محله یا نزدیک‌ترین حوزهٔ پایهٔ ساختاری ادامه دهید.');
                return;
            }
            if (submit) submit.disabled = false;
            setStatus(isRegistration
                ? 'سطح پایهٔ محل سکونت شما مشخص شد. برای تکمیل ثبت‌نام، «ثبت محل سکونت و ادامه» را بزنید؛ جزئیات محلی مانند خیابان، کوچه، مجتمع یا ساختمان را می‌توانید بعداً از بخش «مکان و حکمرانی من» تکمیل کنید.'
                : 'این نقطه برای ثبت محل سکونت معتبر است. در صورت وجود گزینه‌های دقیق‌تر، می‌توانید مسیر را ادامه دهید.');
            return;
        }
        if (submit) submit.disabled = true; setStatus('برای ادامه، مسیر را تا یک نقطهٔ معتبر برای سکونت اصلی تکمیل کنید.');
    };
    const setRegistrationEndpoint = (item) => {
        if (!isRegistration || !item) return false;

        if (item.picker_kind === 'reference_settlement') {
            locationId.value = '';
            if (proposalId) proposalId.value = '';
            if (submit) submit.disabled = false;
            setStatus('سطح پایهٔ محل سکونت شما مشخص شد. برای تکمیل ثبت‌نام، «ثبت محل سکونت و ادامه» را بزنید؛ جزئیات محلی مانند خیابان، کوچه، مجتمع یا ساختمان را می‌توانید بعداً از بخش «مکان و حکمرانی من» تکمیل کنید.');
            return true;
        }

        const values = selectionValues(item);
        if (!values.locationId && !values.proposalId) return false;
        locationId.value = values.locationId;
        if (proposalId) proposalId.value = values.proposalId;
        notifySelection(item);
        if (submit) submit.disabled = false;
        setStatus('سطح پایهٔ محل سکونت شما مشخص شد. برای تکمیل ثبت‌نام، «ثبت محل سکونت و ادامه» را بزنید؛ جزئیات محلی مانند خیابان، کوچه، مجتمع یا ساختمان را می‌توانید بعداً از بخش «مکان و حکمرانی من» تکمیل کنید.');
        return true;
    };
    const load = async (url) => { setPickerState(PICKER_STATES.loading, host.dataset.loadingLabel || 'در حال دریافت گزینه‌های مکانی...'); const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }); if (!response.ok) throw new Error(`Location options request failed: ${response.status}`); const normalized = normalizePickerPayload(await response.json()); return isProjectScope ? projectScopePayload(normalized) : (isRegistration ? registrationPayload(normalized) : normalized); };
    const removeDeeperLevels = (depth) => { levels.querySelectorAll('[data-location-depth]').forEach((element) => { if (Number(element.dataset.locationDepth) > depth) element.remove(); }); };
    const appendLevel = (payload, depth, parentLocationId = null, skipTypeChoice = false, parentProposalId = null, parentReferenceSettlementId = null, parentReferenceSettlementExternalId = null) => {
        if (!shouldRenderNextLevel(payload)) { setPickerState(PICKER_STATES.empty, 'در این سطح گزینهٔ فعال دیگری ثبت نشده است.'); return; }
        if (!isProjectScope && !isRegistration && !skipTypeChoice) {
            const typeChoice = buildMicroTypeChoice(host, payload, depth, (typeKey, choiceWrapper) => {
                clearSelection(); clearStructuralClaimsAfterDepth(form, depth); removeDeeperLevels(depth - 1);
                [...selectedPath.keys()].filter((key) => key >= depth).forEach((key) => selectedPath.delete(key)); renderLocationPath();
                choiceWrapper.remove(); appendLevel(filterPayloadByType(payload, typeKey), depth, parentLocationId, true, parentProposalId, parentReferenceSettlementId, parentReferenceSettlementExternalId);
                setStatus('گزینه‌های ' + (TYPE_LABELS[typeKey] || typeKey) + ' آماده‌اند.');
            });
            if (typeChoice) { typeChoice.dataset.locationTypePayload = JSON.stringify(microContinuationTypes(payload).map((type) => ({ key: type.key, ids: [...payload.locations, ...payload.proposals].filter((item) => item.type_key === type.key).map((item) => String(item.identity || item.id)) }))); levels.appendChild(typeChoice); setStatus('نوع ادامه مسیر را انتخاب کنید.'); return; }
        }
        const { wrapper, select } = buildSelect(host, payload, depth); levels.appendChild(wrapper);
        let exceptionPanel = null;
        const refreshAfterProposal = async (result) => {
            if (result?.kind === 'proposal') { const identity = `proposal:${result.id}`; let option = Array.from(select.options).find((item) => item.value === identity); if (!option) { option = document.createElement('option'); option.value = identity; option.textContent = `${result.canonical_name || result.label || 'مکان پیشنهادی'} — در انتظار تأیید`; option.dataset.endpoint = result.is_residence_endpoint ? '1' : '0'; option.dataset.hasChildren = '1'; option.dataset.typeKey = result.type_key || ''; option.dataset.pickerKind = 'proposal'; option.dataset.locationPendingBadge = ''; select.appendChild(option); } select.value = identity; const proposal = { ...result, identity, label: result.canonical_name || result.label, status: result.status || 'pending', selectable: true, picker_kind: 'proposal' }; selectedPath.set(depth, proposal); renderLocationPath(); setSelection(proposal); closeExceptionPanel(exceptionPanel);
                if (shouldStopRegistrationAtProposal(context, proposal)) {
                    setStatus('سطح پایهٔ محل سکونت شما مشخص شد. برای تکمیل ثبت‌نام، «ثبت محل سکونت و ادامه» را بزنید؛ جزئیات محلی مانند خیابان، کوچه، مجتمع یا ساختمان را می‌توانید بعداً از بخش «مکان و حکمرانی من» تکمیل کنید.');
                    return;
                }
                setStatus('پیشنهاد مکان ثبت شد و در فهرست همین سطح انتخاب شد؛ می‌توانید مسیر را ادامه دهید.'); const childUrl = result.children_url || `/location/proposals/${encodeURIComponent(result.id)}/children`; const children = await load(structuralClaimContextUrl(childUrl, form)); if (isRegistration && children.registrationEndpointAllowed) { setRegistrationEndpoint(proposal); return; } if (shouldRenderNextLevel(children)) appendLevel(children, depth + 1, null, false, result.id); return; }
            if (result?.kind === 'location' && parentLocationId) {
                const refreshed = await load(`/location/options/${encodeURIComponent(parentLocationId)}/children`); const matched = refreshed.locations.find((item) => Number(item.id) === Number(result.id));
                if (matched) { setSelection(matched); closeExceptionPanel(exceptionPanel); if (matched.identity && !Array.from(select.options).some((option) => option.value === matched.identity)) { const option = document.createElement('option'); option.value = matched.identity; option.textContent = matched.label; select.appendChild(option); } select.value = matched.identity; }
                else setPickerState(PICKER_STATES.stale, 'مکان ثبت‌شده در فهرست فعال این سطح دیده نشد؛ انتخاب قبلی شما حفظ شده است.', true);
            }
        };
        if (!isProjectScope) {
            const structuralPanel = buildStructuralClaimPanel(host, payload.structuralChoices, parentLocationId, Math.max(depth - 1, 0), async () => {
                const baseUrl = parentProposalId
                    ? `/location/proposals/${encodeURIComponent(parentProposalId)}/children`
                    : (parentReferenceSettlementExternalId
                        ? `/location/reference-settlements/${encodeURIComponent(parentReferenceSettlementExternalId)}/children`
                        : `/location/options/${encodeURIComponent(parentLocationId)}/children`);
                const refreshed = await load(structuralClaimContextUrl(baseUrl, form));
                if (isRegistration && refreshed.registrationEndpointAllowed) {
                    const parentItem = selectedPath.get(depth - 1);
                    removeDeeperLevels(depth - 1);
                    [...selectedPath.keys()].filter((key) => key >= depth).forEach((key) => selectedPath.delete(key));
                    renderLocationPath();
                    setRegistrationEndpoint(parentItem);
                    return;
                }
                removeDeeperLevels(depth);
                wrapper.remove();
                appendLevel(refreshed, depth, parentLocationId, false, parentProposalId, parentReferenceSettlementId, parentReferenceSettlementExternalId);
                setStatus('وضعیت ساختاری مسیر به‌روزرسانی شد.');
            }, parentProposalId, parentReferenceSettlementExternalId);

            const proposalTypes = payload.effectiveAllowedTypes.length ? payload.effectiveAllowedTypes : payload.allowedTypes;
            const proposalPanel = buildProposalPanel(
                host,
                proposalTypes,
                parentLocationId,
                refreshAfterProposal,
                parentProposalId,
                parentReferenceSettlementId,
            );
            const targetKeys = exceptionTargetKeys(payload);
            const disclosure = buildExceptionDisclosure({
                host,
                payload,
                parentLocationId,
                parentProposalId,
                parentReferenceSettlementId,
                depth,
                structuralPanel,
                proposalPanel,
                enableReferenceSearch: host.dataset.referenceBranchActive === '1'
                    && Boolean(parentLocationId)
                    && (targetKeys.includes('village') || targetKeys.includes('settlement')),
            });
            if (disclosure) {
                exceptionPanel = disclosure.panel;
                wrapper.appendChild(disclosure.shell);
            }
        }
        select.addEventListener('change', async () => {
            if (!isProjectScope) clearStructuralClaimsAfterDepth(form, depth); removeDeeperLevels(depth); [...selectedPath.keys()].filter((key) => key > depth).forEach((key) => selectedPath.delete(key)); const selected = pickerItems(payload).find((item) => (item.identity || `${item.picker_kind}:${item.id}`) === select.value) || null;
            if (!selected) { selectedPath.delete(depth); renderLocationPath(); clearSelection(); setPickerState(PICKER_STATES.empty, isProjectScope ? 'انتخاب محدوده پروژه اختیاری است.' : 'یک گزینه را برای ادامه انتخاب کنید.'); return; }
            closeExceptionPanel(exceptionPanel);
            if (!isProjectScope && selected.type_key === 'urban_region') clearStructuralClaimTypes(form, ['no_urban_region']);
            if (!isProjectScope && selected.type_key === 'neighborhood') clearStructuralClaimTypes(form, ['no_neighborhood']);
            selectedPath.set(depth, selected); renderLocationPath();
            if (selected.navigation_only === true) { clearSelection(); setStatus('سطح بعدی را برای تعیین محل سکونت انتخاب کنید.'); }
            else setSelection(selected);
            const previousLocationId = locationId.value; const previousProposalId = proposalId?.value || ''; const previousGovernanceAreaId = governanceAreaId?.value || ''; const previousSubmitDisabled = submit?.disabled ?? true;
            try {
                const childrenUrl = selected.children_url || (selected.picker_kind === 'proposal' ? `/location/proposals/${encodeURIComponent(selected.id)}/children` : `/location/options/${encodeURIComponent(selected.id)}/children`); const children = await load(structuralClaimContextUrl(childrenUrl, form));
                if (isRegistration && children.registrationEndpointAllowed) {
                    removeDeeperLevels(depth);
                    setRegistrationEndpoint(selected);
                } else if (shouldRenderNextLevel(children)) { appendLevel(children, depth + 1, selected.identity?.startsWith('location:') ? selected.id : null, false, selected.picker_kind === 'proposal' ? selected.id : null); setStatus(isProjectScope ? 'گزینه‌های دقیق‌تر آماده‌اند؛ می‌توانید همین سطح را نگه دارید یا پایین‌تر بروید.' : 'گزینه‌های سطح بعد آماده‌اند.'); }
                else { setSelection(selected); if (!isProjectScope && !selected.is_residence_endpoint) setPickerState(PICKER_STATES.empty, 'این شاخه فعلاً نقطهٔ معتبر دیگری برای سکونت ندارد.'); }
            } catch (error) { console.warn('EarthCoop location selector could not load children:', error); locationId.value = previousLocationId; if (proposalId) proposalId.value = previousProposalId; if (governanceAreaId) governanceAreaId.value = previousGovernanceAreaId; if (submit && !isProjectScope) submit.disabled = previousSubmitDisabled; setPickerState(PICKER_STATES.stale, 'دریافت گزینه‌های جدید ممکن نشد؛ انتخاب معتبر فعلی شما حفظ شده است.', true); }
        });
    };

    host.addEventListener('earthcoop-location-reference-structure-selected', async (event) => {
        if (isProjectScope) return;
        const detail = event.detail || {};
        const anchorLocationId = String(detail.anchorLocationId || '');
        const settlement = detail.settlement || null;
        const claim = detail.claim || null;
        if (!anchorLocationId || !settlement || claim?.claim_type !== 'no_neighborhood') return;

        const anchorEntry = [...selectedPath.entries()].find(([, item]) =>
            String(item?.identity || '') === 'location:' + anchorLocationId
            || String(item?.id || '') === anchorLocationId
        );
        if (!anchorEntry) return;

        const anchorDepth = Number(anchorEntry[0]);
        removeDeeperLevels(anchorDepth + 1);
        [...selectedPath.keys()].filter((key) => key > anchorDepth).forEach((key) => selectedPath.delete(key));

        selectedPath.set(anchorDepth + 1, {
            identity: 'reference-settlement:' + String(settlement.external_id || ''),
            label: String(settlement.name_fa || settlement.label || ''),
            type_key: 'settlement',
            picker_kind: 'reference_settlement',
            status: 'pending',
        });
        locationId.value = '';
        if (proposalId) proposalId.value = '';
        if (submit) submit.disabled = false;
        renderLocationPath();

        if (isRegistration) {
            setStatus('این آبادی بدون محله برای مسیر ثبت‌نام شما انتخاب شد. ثبت‌نام می‌تواند در همین سطح پایان یابد.');
            return;
        }

        let payload = normalizePickerPayload(detail.payload || {});
        if (!shouldRenderNextLevel(payload)) {
            setStatus('بی‌محله بودن انتخاب شد؛ گزینهٔ دقیق‌تری برای ادامه مسیر ثبت نشده است.');
            return;
        }

        let depth = anchorDepth + 2;
        let parentReferenceSettlementId = Number(settlement.id);
        let parentProposalId = null;
        const savedPath = (Array.isArray(detail.proposalPath) ? detail.proposalPath : [])
            .map((id) => Number(id))
            .filter((id) => Number.isInteger(id) && id > 0);

        for (const savedProposalId of savedPath) {
            appendLevel(payload, depth, null, false, parentProposalId, parentReferenceSettlementId);
            const selected = pickerItems(payload).find((item) =>
                item.picker_kind === 'proposal' && Number(item.id) === savedProposalId
            );
            const select = levels.querySelector(`[data-location-select="${depth}"]`);
            if (!selected || !select) {
                setPickerState(PICKER_STATES.stale, 'بخشی از جزئیات ذخیره‌شدهٔ نشانی دیگر در مسیر بی‌محله فعال نیست؛ مسیر معتبر فعلی حفظ شد.', true);
                return;
            }

            select.value = selected.identity || 'proposal:' + selected.id;
            selectedPath.set(depth, selected);
            renderLocationPath();
            setSelection(selected);
            parentProposalId = Number(selected.id);
            parentReferenceSettlementId = null;

            const childrenUrl = selected.children_url || '/location/proposals/' + encodeURIComponent(selected.id) + '/children';
            payload = await load(structuralClaimContextUrl(childrenUrl, form));
            depth += 1;
        }

        if (shouldRenderNextLevel(payload)) {
            appendLevel(payload, depth, null, false, parentProposalId, parentReferenceSettlementId);
        }
        setStatus(savedPath.length
            ? 'نشانی بی‌محلهٔ فعلی بازیابی شد؛ می‌توانید آن را نگه دارید یا دقیق‌تر ادامه دهید.'
            : 'بی‌محله بودن انتخاب شد. در صورت نیاز، خیابان و جزئیات دقیق‌تر نشانی را ادامه دهید.');
    });

    host.addEventListener('earthcoop-location-reference-selected', async (event) => {
        if (isProjectScope) return;
        const detail = event.detail || {};
        const anchorLocationId = String(detail.anchorLocationId || '');
        const settlement = detail.settlement || null;
        const proposal = detail.proposal || null;
        if (!anchorLocationId || !settlement) return;

        const anchorEntry = [...selectedPath.entries()].find(([, item]) =>
            String(item?.identity || '') === 'location:' + anchorLocationId
            || String(item?.id || '') === anchorLocationId
        );
        if (!anchorEntry) return;

        const anchorDepth = Number(anchorEntry[0]);
        // Keep the next rendered level (the village/settlement select and its
        // exception disclosure) mounted. The bridge lives inside that disclosure
        // while a ReferenceSettlement is selected and must survive this transition.
        removeDeeperLevels(anchorDepth + 1);
        [...selectedPath.keys()].filter((key) => key > anchorDepth).forEach((key) => selectedPath.delete(key));

        const settlementItem = {
            identity: 'reference-settlement:' + String(settlement.external_id || ''),
            label: String(settlement.name_fa || settlement.label || ''),
            type_key: 'settlement',
            picker_kind: 'reference_settlement',
            status: 'pending',
        };

        const settlementSelect = levels.querySelector(`[data-location-select="${anchorDepth + 1}"]`);
        if (settlementSelect) {
            let settlementOption = Array.from(settlementSelect.options)
                .find((option) => option.value === settlementItem.identity);
            if (!settlementOption) {
                settlementOption = document.createElement('option');
                settlementOption.value = settlementItem.identity;
                settlementOption.textContent = settlementItem.label + ' — در انتظار بررسی سکونت';
                settlementOption.dataset.referenceSettlementOption = '';
                settlementOption.dataset.typeKey = 'settlement';
                settlementOption.dataset.pickerKind = 'reference_settlement';
                settlementSelect.appendChild(settlementOption);
            }
            settlementSelect.value = settlementItem.identity;
        }

        selectedPath.set(anchorDepth + 1, settlementItem);

        if (!proposal) {
            locationId.value = '';
            if (proposalId) proposalId.value = '';
            if (submit) submit.disabled = isRegistration;
            renderLocationPath();

            const continuation = normalizePickerPayload(detail.payload || {});
            if (isRegistration && continuation.registrationEndpointAllowed) {
                if (submit) submit.disabled = false;
                setStatus('این آبادی بدون محله برای مسیر ثبت‌نام شما انتخاب شد.');
                return;
            }
            if (shouldRenderNextLevel(continuation)) {
                appendLevel(
                    continuation,
                    anchorDepth + 2,
                    null,
                    false,
                    null,
                    Number(settlement.id),
                    String(settlement.external_id || ''),
                );
                setStatus('آبادی انتخاب شد. سطح بعدی را در ادامهٔ مسیر انتخاب کنید.');
            } else {
                setStatus('آبادی انتخاب شد، اما فعلاً سطح بعدی معتبری برای آن ثبت نشده است.');
            }
            return;
        }

        const continuation = normalizePickerPayload(detail.payload || {});
        if (shouldRenderNextLevel(continuation)) {
            appendLevel(
                continuation,
                anchorDepth + 2,
                null,
                false,
                null,
                Number(settlement.id),
                String(settlement.external_id || ''),
            );
        }

        const proposalItem = {
            ...proposal,
            identity: 'proposal:' + String(proposal.id || ''),
            label: String(proposal.label || proposal.canonical_name || ''),
            type_key: proposal.type_key || 'neighborhood',
            picker_kind: 'proposal',
            status: proposal.status || 'pending',
            selectable: true,
        };
        const neighborhoodSelect = levels.querySelector(`[data-location-select="${anchorDepth + 2}"]`);
        if (neighborhoodSelect && Array.from(neighborhoodSelect.options).some((option) => option.value === proposalItem.identity)) {
            neighborhoodSelect.value = proposalItem.identity;
        }
        selectedPath.set(anchorDepth + 2, proposalItem);
        renderLocationPath();
        setSelection(proposalItem);

        if (isRegistration) {
            setStatus('آبادی و محلهٔ دقیق شما مشخص شد. ثبت‌نام می‌تواند در همین‌جا پایان یابد؛ جزئیات نشانی بعدی اختیاری است.');
            return;
        }

        try {
            let children = await load(structuralClaimContextUrl(
                proposal.children_url || '/location/proposals/' + encodeURIComponent(proposal.id) + '/children',
                form,
            ));
            let depth = anchorDepth + 3;
            let parentProposalId = Number(proposal.id);
            const savedPath = (Array.isArray(detail.proposalPath) ? detail.proposalPath : [])
                .map((id) => Number(id))
                .filter((id) => Number.isInteger(id) && id > 0 && id !== Number(proposal.id));

            for (const savedProposalId of savedPath) {
                if (!shouldRenderNextLevel(children)) break;
                appendLevel(children, depth, null, false, parentProposalId);
                const selected = pickerItems(children).find((item) =>
                    item.picker_kind === 'proposal' && Number(item.id) === savedProposalId
                );
                const select = levels.querySelector(`[data-location-select="${depth}"]`);
                if (!selected || !select) {
                    setPickerState(PICKER_STATES.stale, 'بخشی از جزئیات ذخیره‌شدهٔ نشانی دیگر در مسیر فعال نیست؛ مسیر معتبر فعلی حفظ شد.', true);
                    return;
                }

                select.value = selected.identity || 'proposal:' + selected.id;
                selectedPath.set(depth, selected);
                renderLocationPath();
                setSelection(selected);
                parentProposalId = Number(selected.id);
                children = await load(structuralClaimContextUrl(
                    selected.children_url || '/location/proposals/' + encodeURIComponent(selected.id) + '/children',
                    form,
                ));
                depth += 1;
            }

            if (shouldRenderNextLevel(children)) {
                appendLevel(children, depth, null, false, parentProposalId);
                setStatus(savedPath.length
                    ? 'جزئیات فعلی نشانی بازیابی شد؛ می‌توانید همین مسیر را نگه دارید یا دقیق‌تر ادامه دهید.'
                    : 'محلهٔ فعلی انتخاب شد. در صورت نیاز، خیابان و جزئیات دقیق‌تر نشانی را ادامه دهید.');
            } else {
                setStatus('جزئیات فعلی نشانی تا آخرین سطح ثبت‌شده بازیابی شد.');
            }
        } catch (error) {
            console.warn('EarthCoop reference-settlement continuation could not load proposal children:', error);
            setPickerState(PICKER_STATES.stale, 'آبادی و محله حفظ شدند، اما دریافت جزئیات بعدی نشانی ممکن نشد.', true);
        }
    });

    const initialLocationId = locationId.value;
    const initialGovernanceAreaId = governanceAreaId?.value || '';
    if (!isProjectScope || (!initialLocationId && !initialGovernanceAreaId)) clearSelection();
    if (isProjectScope && proposalId) proposalId.value = '';
    host.setAttribute('aria-live', 'polite');

    const hydrateProjectScopePath = async (roots) => {
        const query = initialLocationId ? `target_location_id=${encodeURIComponent(initialLocationId)}` : `governance_area_id=${encodeURIComponent(initialGovernanceAreaId)}`;
        const savedPath = await load(`/location/project-scope/options/path?${query}`);
        const path = savedPath.locations;
        let payload = roots;
        for (let depth = 0; depth < path.length; depth += 1) {
            const expected = path[depth];
            const selected = pickerItems(payload).find((item) => item.identity === expected.identity);
            const select = levels.querySelector(`[data-location-select="${depth}"]`);
            if (!selected || !select) throw new Error(`Saved project scope path is stale at depth ${depth}`);
            select.value = selected.identity;
            setSelection(selected);
            if (depth === path.length - 1) return true;
            const childrenUrl = selected.children_url || `/location/options/${encodeURIComponent(selected.id)}/children`;
            payload = await load(childrenUrl);
            if (!shouldRenderNextLevel(payload)) throw new Error(`Saved project scope path has no active child at depth ${depth + 1}`);
            appendLevel(payload, depth + 1, selected.identity?.startsWith('location:') ? selected.id : null);
        }
        return false;
    };

    try {
        const rootUrl = isProjectScope ? '/location/project-scope/options/root' : '/location/residence/options/root';
        const roots = await load(rootUrl); appendLevel(roots, 0);
        if (!roots.locations.length && !roots.proposals.length) setPickerState(PICKER_STATES.empty, isProjectScope ? 'هنوز محدوده حکمرانی رسمی فعالی ثبت نشده است.' : (country ? 'برای این کشور هنوز گزینهٔ مکانی فعالی ثبت نشده است.' : 'هنوز گزینهٔ مکانی فعالی ثبت نشده است.'));
        else if (isProjectScope && (initialLocationId || initialGovernanceAreaId)) {
            try { await hydrateProjectScopePath(roots); setStatus('محدوده هدف فعلی پروژه بازیابی شد؛ می‌توانید همین سطح را نگه دارید یا مسیر را تغییر دهید.'); }
            catch (error) { console.warn('EarthCoop saved project scope could not be hydrated:', error); locationId.value = initialLocationId; governanceAreaId.value = initialGovernanceAreaId; setPickerState(PICKER_STATES.stale, 'مسیر محدوده هدف ذخیره‌شده دیگر به‌طور کامل فعال نیست؛ مقدار ذخیره‌شده حفظ شده است و برای تغییر باید یک مسیر معتبر انتخاب کنید.', true); }
        } else if (isProjectScope) setStatus('محدوده هدف پروژه را مرحله‌به‌مرحله انتخاب کنید؛ این بخش اختیاری است.');
        else setStatus('مسیر محل سکونت را مرحله‌به‌مرحله انتخاب کنید.');
    } catch (error) { console.warn('EarthCoop location selector could not load root options:', error); setPickerState(PICKER_STATES.error, host.dataset.errorLabel || 'دریافت گزینه‌های مکانی ممکن نشد.', true); }
};

selectors.forEach((host) => { void initializeLocationSelector(host); });
export { initializeLocationSelector, normalizePickerPayload, registrationPayload, microContinuationTypes, filterPayloadByType, projectScopePayload, projectScopeSelectionValues, pickerLevelLabel, selectionValues, shouldRenderNextLevel, shouldStopRegistrationAtProposal, locationDisplayLabel };
