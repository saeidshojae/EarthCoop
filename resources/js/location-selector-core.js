const selectors = typeof document !== 'undefined'
    ? Array.from(document.querySelectorAll('[data-location-selector]'))
    : [];

const OPEN_PROPOSAL_STATUSES = new Set(['pending', 'ready_for_review', 'needs_evidence']);
const PICKER_STATES = Object.freeze({ loading: 'loading', empty: 'empty', error: 'error', stale: 'stale', ready: 'ready' });
const TYPE_LABELS = Object.freeze({
    global: 'جهانی', continent: 'قاره', country: 'کشور', province: 'استان / ایالت', county: 'شهرستان / ناحیه',
    section: 'بخش', city: 'شهر', rural_district: 'دهستان', village: 'روستا', urban_region: 'منطقه',
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
    return normalized.locations.length > 0 || normalized.proposals.length > 0 || normalized.allowedTypes.some((type) => type?.proposal_allowed === true);
};
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
        option.dataset.endpoint = item.is_residence_endpoint ? '1' : '0'; option.dataset.typeKey = item.type_key || ''; option.dataset.hasChildren = item.has_children ? '1' : '0'; option.dataset.typeKey = item.type_key || ''; option.dataset.pickerKind = item.picker_kind;
        select.appendChild(option);
    });
    wrapper.append(label, select); return { wrapper, select };
};
const STRUCTURAL_CLAIM_COPY = Object.freeze({
    single_urban_region: { title: 'این شهر فقط یک حوزهٔ منطقه‌ای دارد', detail: 'سطح منطقهٔ شهری جداگانه ساخته نمی‌شود و مسیر به سطح واقعی بعدی ادامه پیدا می‌کند.' },
    no_urban_region: { title: 'این شهر منطقهٔ شهری جداگانه ندارد', detail: 'خود شهر نمایندهٔ این سطح حکمرانی است؛ مکان دقیق‌تر همچنان می‌تواند ثبت شود.' },
    single_neighborhood: { title: 'این محدوده فقط یک حوزهٔ محله‌ای دارد', detail: 'محلهٔ مصنوعی ساخته نمی‌شود و خود محدوده می‌تواند مبنای رسمی باشد.' },
    no_neighborhood: { title: 'این محدوده محلهٔ جداگانه ندارد', detail: 'پایان حوزهٔ رسمی به معنی پایان مسیر مکانی نیست و می‌توانید خیابان یا مکان دقیق‌تر را ادامه دهید.' },
});
const rememberStructuralClaim = (host, claimId) => {
    const id = Number(claimId); if (!Number.isInteger(id) || id <= 0) return;
    const form = host.closest('[data-location-form]') || host.closest('form'); if (!form) return;
    const selector = `input[data-location-structure-claim-id][value="${id}"]`; if (form.querySelector(selector)) return;
    const input = document.createElement('input'); input.type = 'hidden'; input.name = 'location_structure_claim_ids[]'; input.value = String(id); input.dataset.locationStructureClaimId = '';
    form.appendChild(input);
};

const buildStructuralClaimPanel = (host, choices, locationId, onChanged) => {
    if (!locationId || !Array.isArray(choices) || choices.length === 0) return null;
    const shell = document.createElement('div'); shell.className = 'location-structural-claims vstack gap-2'; shell.dataset.locationStructuralClaims = '';
    const heading = document.createElement('div'); heading.className = 'small fw-bold'; heading.textContent = 'ساختار این محدوده متفاوت است؟';
    shell.appendChild(heading);
    choices.forEach((choice) => {
        const copy = STRUCTURAL_CLAIM_COPY[choice.claim_type]; if (!copy) return;
        const row = document.createElement('div'); row.className = 'border rounded-3 p-2';
        const button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-link text-decoration-none p-0 fw-semibold'; button.textContent = copy.title;
        const detail = document.createElement('div'); detail.className = 'small text-secondary mt-1'; detail.textContent = copy.detail;
        const state = document.createElement('div'); state.className = 'small mt-1'; state.setAttribute('aria-live','polite');
        const statusValue = String(choice.status || 'available');
        if (statusValue === 'approved') { button.disabled = true; state.textContent = 'تأیید شده'; }
        else if (OPEN_PROPOSAL_STATUSES.has(statusValue)) { button.disabled = true; state.textContent = 'در انتظار بررسی؛ پس از ثبت محل سکونت، حمایت شما نیز ثبت می‌شود.'; }
        button.addEventListener('click', async () => {
            const form = host.closest('[data-location-form]') || host.closest('form'); const csrf = form?.querySelector('input[name="_token"]')?.value || '';
            button.disabled = true; state.textContent = 'در حال ثبت...';
            try {
                const response = await fetch('/locations/structure-claims', { method:'POST', credentials:'same-origin', headers:{ Accept:'application/json','Content-Type':'application/json', ...(csrf ? {'X-CSRF-TOKEN':csrf}:{}) }, body:JSON.stringify({ location_id:Number(locationId), claim_type:choice.claim_type }) });
                if (!response.ok) throw new Error('Structural claim request failed: ' + response.status);
                const result = await response.json(); rememberStructuralClaim(host, result.id); state.textContent = 'در انتظار بررسی؛ می‌توانید مسیر واقعی محل سکونت را ادامه دهید.';
                await onChanged(result);
            } catch (error) { console.warn('EarthCoop structural claim failed:', error); button.disabled = false; state.textContent = 'ثبت این وضعیت ممکن نشد؛ دوباره تلاش کنید.'; }
        });
        row.append(button, detail, state); shell.appendChild(row);
    });
    return shell;
};

const buildProposalPanel = (host, allowedTypes, parentLocationId, onCreated) => {
    const proposableTypes = allowedTypes.filter((type) => type?.proposal_allowed === true); if (!parentLocationId || proposableTypes.length === 0) return null;
    const shell = document.createElement('div'); shell.className = 'border rounded-3 p-3 bg-light'; shell.dataset.locationProposalShell = '';
    const toggle = document.createElement('button'); toggle.type = 'button'; toggle.className = 'btn btn-outline-secondary btn-sm'; toggle.textContent = 'مکان من در فهرست نیست'; toggle.dataset.locationProposalToggle = '';
    const panel = document.createElement('div'); panel.className = 'vstack gap-2 mt-3 d-none'; panel.dataset.locationProposalPanel = '';
    const typeLabel = document.createElement('label'); typeLabel.className = 'form-label small text-secondary mb-0'; typeLabel.textContent = 'نوع مکان پیشنهادی';
    const typeSelect = document.createElement('select'); typeSelect.className = 'form-select form-select-sm'; typeSelect.setAttribute('aria-label', 'نوع مکان پیشنهادی');
    if (proposableTypes.length === 1) { typeLabel.classList.add('d-none'); typeSelect.classList.add('d-none'); }
    proposableTypes.forEach((type) => { const option = document.createElement('option'); option.value = String(type.id); option.textContent = type.label || type.key; typeSelect.appendChild(option); });
    const nameLabel = document.createElement('label'); nameLabel.className = 'form-label small text-secondary mb-0'; nameLabel.textContent = 'نام مکان';
    const nameInput = document.createElement('input'); nameInput.type = 'text'; nameInput.className = 'form-control form-control-sm'; nameInput.maxLength = 255; nameInput.placeholder = 'نام مکان را وارد کنید'; nameInput.setAttribute('aria-label', 'نام مکان پیشنهادی');
    const actions = document.createElement('div'); actions.className = 'd-flex flex-wrap gap-2';
    const submit = document.createElement('button'); submit.type = 'button'; submit.className = 'btn btn-primary btn-sm'; submit.textContent = 'ثبت پیشنهاد مکان';
    const cancel = document.createElement('button'); cancel.type = 'button'; cancel.className = 'btn btn-link btn-sm text-decoration-none'; cancel.textContent = 'انصراف';
    const feedback = document.createElement('div'); feedback.className = 'small text-secondary'; feedback.setAttribute('aria-live', 'polite');
    actions.append(submit, cancel); panel.append(typeLabel, typeSelect, nameLabel, nameInput, actions, feedback); shell.append(toggle, panel);
    toggle.addEventListener('click', () => { panel.classList.toggle('d-none'); if (!panel.classList.contains('d-none')) nameInput.focus(); });
    cancel.addEventListener('click', () => { panel.classList.add('d-none'); feedback.textContent = ''; });
    submit.addEventListener('click', async () => {
        const canonicalName = nameInput.value.trim(); if (!canonicalName) { feedback.textContent = 'نام مکان را وارد کنید.'; feedback.classList.add('text-danger'); return; }
        submit.disabled = true; feedback.classList.remove('text-danger'); feedback.textContent = 'در حال بررسی و ثبت پیشنهاد...';
        const form = host.closest('[data-location-form]') || host.closest('form'); const csrf = form?.querySelector('input[name="_token"]')?.value || ''; const locale = document.documentElement.lang || 'fa';
        try {
            const response = await fetch('/locations/proposals', { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) }, body: JSON.stringify({ parent_location_id: Number(parentLocationId), location_type_id: Number(typeSelect.value), canonical_name: canonicalName, localized_names: { [locale]: canonicalName } }) });
            if (!response.ok) throw new Error(`Location proposal request failed: ${response.status}`);
            const result = await response.json(); await onCreated(result); panel.classList.add('d-none'); feedback.textContent = '';
        } catch (error) { console.warn('EarthCoop location proposal could not be created:', error); feedback.textContent = 'ثبت پیشنهاد مکان ممکن نشد. متن شما حفظ شده است؛ دوباره تلاش کنید.'; feedback.classList.add('text-danger'); }
        finally { submit.disabled = false; }
    });
    return shell;
};

const initializeLocationSelector = async (host) => {
    const levels = host.querySelector('[data-location-levels]');
    const context = host.dataset.locationPurpose || host.dataset.locationSelectorContext || 'residence'; const isProjectScope = context === 'project-scope';
    const locationId = isProjectScope ? host.querySelector('[data-location-id][name="target_location_id"]') : host.querySelector('[data-location-id][name="location_id"]');
    const proposalId = isProjectScope ? host.querySelector('[data-location-proposal-id]') : host.querySelector('[data-location-proposal-id][name="location_proposal_id"]');
    const governanceAreaId = isProjectScope ? host.querySelector('[data-project-governance-area-id][name="governance_area_id"]') : null;
    const form = host.closest('[data-location-form]') || host.closest('form'); const submit = form?.querySelector('[data-location-submit]'); const status = host.querySelector('[data-location-status]'); const locationPath = host.querySelector('[data-location-path]'); const country = (host.dataset.countryCode || '').trim();
    const selectedPath = new Map();
    const renderLocationPath = () => { if (!locationPath) return; const labels = [...selectedPath.entries()].sort((a,b) => a[0]-b[0]).map(([, item]) => locationDisplayLabel(item)).filter(Boolean); locationPath.textContent = labels.length ? labels.join(' / ') : 'مسیر انتخاب نشده'; };
    if (!levels || !locationId || (!isProjectScope && !proposalId) || (isProjectScope && !governanceAreaId)) return;
    const setPickerState = (state, message, isError = false) => { host.dataset.locationState = state; host.setAttribute('aria-busy', state === PICKER_STATES.loading ? 'true' : 'false'); if (!status) return; status.textContent = message; status.classList.toggle('text-danger', isError); status.setAttribute('aria-live', isError ? 'assertive' : 'polite'); };
    const setStatus = (message, isError = false) => setPickerState(isError ? PICKER_STATES.error : PICKER_STATES.ready, message, isError);
    const clearSelection = () => { locationId.value = ''; if (proposalId) proposalId.value = ''; if (governanceAreaId) governanceAreaId.value = ''; if (submit && !isProjectScope) submit.disabled = true; };
    const setSelection = (item) => {
        if (isProjectScope) {
            const values = projectScopeSelectionValues(item); locationId.value = values.locationId; governanceAreaId.value = values.governanceAreaId; if (proposalId) proposalId.value = '';
            if (!values.locationId && !values.governanceAreaId) { setPickerState(PICKER_STATES.stale, 'این گزینه دیگر معتبر نیست. لطفاً یک محدوده رسمی و فعال را انتخاب کنید.', true); return; }
            setStatus(values.governanceAreaId ? 'این محدوده حکمرانی رسمی به‌عنوان هدف پروژه انتخاب شد. در صورت وجود گزینه‌های دقیق‌تر، می‌توانید مسیر را ادامه دهید.' : 'این مکان به‌عنوان مکان هدف پروژه انتخاب شد. در صورت وجود گزینه‌های دقیق‌تر، می‌توانید مسیر را ادامه دهید.'); return;
        }
        const values = selectionValues(item); locationId.value = values.locationId; if (proposalId) proposalId.value = values.proposalId;
        if (!values.locationId && !values.proposalId) { if (submit) submit.disabled = true; setPickerState(PICKER_STATES.stale, 'این گزینه دیگر معتبر نیست. لطفاً یک مکان فعال را انتخاب کنید.', true); return; }
        if (values.proposalId) { if (submit) submit.disabled = false; setStatus('این مکان هنوز در انتظار تأیید است. می‌توانید ادامه دهید؛ حوزهٔ رسمی شما تا زمان تأیید بر مبنای نزدیک‌ترین مکان تأییدشده باقی می‌ماند.'); return; }
        if (values.locationId && item?.is_residence_endpoint) { if (submit) submit.disabled = false; setStatus('این نقطه برای ثبت محل سکونت معتبر است. در صورت وجود گزینه‌های دقیق‌تر، می‌توانید مسیر را ادامه دهید.'); return; }
        if (submit) submit.disabled = true; setStatus('برای ادامه، مسیر را تا یک نقطهٔ معتبر برای سکونت اصلی تکمیل کنید.');
    };
    const load = async (url) => { setPickerState(PICKER_STATES.loading, host.dataset.loadingLabel || 'در حال دریافت گزینه‌های مکانی...'); const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }); if (!response.ok) throw new Error(`Location options request failed: ${response.status}`); const normalized = normalizePickerPayload(await response.json()); return isProjectScope ? projectScopePayload(normalized) : normalized; };
    const removeDeeperLevels = (depth) => { levels.querySelectorAll('[data-location-depth]').forEach((element) => { if (Number(element.dataset.locationDepth) > depth) element.remove(); }); };
    const appendLevel = (payload, depth, parentLocationId = null) => {
        if (!shouldRenderNextLevel(payload)) { setPickerState(PICKER_STATES.empty, 'در این سطح گزینهٔ فعال دیگری ثبت نشده است.'); return; }
        const { wrapper, select } = buildSelect(host, payload, depth); levels.appendChild(wrapper);
        const refreshAfterProposal = async (result) => {
            if (result?.kind === 'proposal') { setSelection({ id: result.id, identity: `proposal:${result.id}`, label: result.canonical_name, status: result.status, selectable: true }); setStatus('پیشنهاد مکان ثبت شد و به‌عنوان محل دقیق در انتظار تأیید انتخاب شد.'); return; }
            if (result?.kind === 'location' && parentLocationId) {
                const refreshed = await load(`/location/options/${encodeURIComponent(parentLocationId)}/children`); const matched = refreshed.locations.find((item) => Number(item.id) === Number(result.id));
                if (matched) { setSelection(matched); if (matched.identity && !Array.from(select.options).some((option) => option.value === matched.identity)) { const option = document.createElement('option'); option.value = matched.identity; option.textContent = matched.label; select.appendChild(option); } select.value = matched.identity; }
                else setPickerState(PICKER_STATES.stale, 'مکان ثبت‌شده در فهرست فعال این سطح دیده نشد؛ انتخاب قبلی شما حفظ شده است.', true);
            }
        };
        if (!isProjectScope) {
            const structuralPanel = buildStructuralClaimPanel(host, payload.structuralChoices, parentLocationId, async () => {
                const refreshed = await load(`/location/options/${encodeURIComponent(parentLocationId)}/children`);
                removeDeeperLevels(depth); wrapper.remove(); appendLevel(refreshed, depth, parentLocationId);
                setStatus('وضعیت ساختاری ثبت شد. مسیر واقعی بعدی بدون ساخت سطح مصنوعی در دسترس است.');
            });
            if (structuralPanel) wrapper.appendChild(structuralPanel);
            const proposalTypes = payload.effectiveAllowedTypes.length ? payload.effectiveAllowedTypes : payload.allowedTypes;
            const proposalPanel = buildProposalPanel(host, proposalTypes, parentLocationId, refreshAfterProposal); if (proposalPanel) wrapper.appendChild(proposalPanel);
        }
        select.addEventListener('change', async () => {
            removeDeeperLevels(depth); [...selectedPath.keys()].filter((key) => key > depth).forEach((key) => selectedPath.delete(key)); const selected = pickerItems(payload).find((item) => (item.identity || `${item.picker_kind}:${item.id}`) === select.value) || null;
            if (!selected) { selectedPath.delete(depth); renderLocationPath(); clearSelection(); setPickerState(PICKER_STATES.empty, isProjectScope ? 'انتخاب محدوده پروژه اختیاری است.' : 'یک گزینه را برای ادامه انتخاب کنید.'); return; }
            selectedPath.set(depth, selected); renderLocationPath();
            if (selected.navigation_only === true) { clearSelection(); setStatus('سطح بعدی را برای تعیین محل سکونت انتخاب کنید.'); }
            else setSelection(selected);
            if (selected.picker_kind === 'proposal') return;
            const previousLocationId = locationId.value; const previousProposalId = proposalId?.value || ''; const previousGovernanceAreaId = governanceAreaId?.value || ''; const previousSubmitDisabled = submit?.disabled ?? true;
            try {
                const childrenUrl = selected.children_url || `/location/options/${encodeURIComponent(selected.id)}/children`; const children = await load(childrenUrl);
                if (shouldRenderNextLevel(children)) { appendLevel(children, depth + 1, selected.identity?.startsWith('location:') ? selected.id : null); setStatus(isProjectScope ? 'گزینه‌های دقیق‌تر آماده‌اند؛ می‌توانید همین سطح را نگه دارید یا پایین‌تر بروید.' : 'گزینه‌های سطح بعد آماده‌اند.'); }
                else { setSelection(selected); if (!isProjectScope && !selected.is_residence_endpoint) setPickerState(PICKER_STATES.empty, 'این شاخه فعلاً نقطهٔ معتبر دیگری برای سکونت ندارد.'); }
            } catch (error) { console.warn('EarthCoop location selector could not load children:', error); locationId.value = previousLocationId; if (proposalId) proposalId.value = previousProposalId; if (governanceAreaId) governanceAreaId.value = previousGovernanceAreaId; if (submit && !isProjectScope) submit.disabled = previousSubmitDisabled; setPickerState(PICKER_STATES.stale, 'دریافت گزینه‌های جدید ممکن نشد؛ انتخاب معتبر فعلی شما حفظ شده است.', true); }
        });
    };

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
export { initializeLocationSelector, normalizePickerPayload, projectScopePayload, projectScopeSelectionValues, pickerLevelLabel, selectionValues, shouldRenderNextLevel, locationDisplayLabel };
