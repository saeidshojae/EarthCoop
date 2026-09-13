const selectors = typeof document !== 'undefined'
    ? Array.from(document.querySelectorAll('[data-location-selector]'))
    : [];

const normalizePickerPayload = (payload) => ({
    locations: Array.isArray(payload?.data) ? payload.data : [],
    proposals: Array.isArray(payload?.proposals) ? payload.proposals : [],
    allowedTypes: Array.isArray(payload?.allowed_types) ? payload.allowed_types : [],
});

const selectionValues = (item) => {
    const identity = String(item?.identity || '');

    if (identity.startsWith('proposal:')) {
        return { locationId: '', proposalId: String(item?.id || identity.slice('proposal:'.length)) };
    }

    if (identity.startsWith('location:') || item?.id) {
        return { locationId: String(item?.id || identity.slice('location:'.length)), proposalId: '' };
    }

    return { locationId: '', proposalId: '' };
};

const shouldRenderNextLevel = (payload) => {
    const normalized = payload?.locations ? payload : normalizePickerPayload(payload);

    return normalized.locations.length > 0
        || normalized.proposals.length > 0
        || normalized.allowedTypes.some((type) => type?.proposal_allowed === true);
};

const pickerItems = (payload) => [
    ...payload.locations.map((item) => ({ ...item, picker_kind: 'location' })),
    ...payload.proposals.map((item) => ({ ...item, picker_kind: 'proposal' })),
];

const buildSelect = (host, payload, depth) => {
    const wrapper = document.createElement('div');
    wrapper.dataset.locationDepth = String(depth);
    wrapper.className = 'vstack gap-2';

    const select = document.createElement('select');
    select.className = 'form-select';
    select.dataset.locationSelect = String(depth);
    select.setAttribute('aria-label', `سطح مکانی ${depth + 1}`);

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = host.dataset.emptyLabel || 'یک گزینه را انتخاب کنید';
    select.appendChild(placeholder);

    pickerItems(payload).forEach((item) => {
        const option = document.createElement('option');
        option.value = item.identity || `${item.picker_kind}:${item.id}`;
        option.textContent = item.picker_kind === 'proposal'
            ? `${item.label} — در انتظار تأیید`
            : item.label;
        option.dataset.endpoint = item.is_residence_endpoint ? '1' : '0';
        option.dataset.hasChildren = item.has_children ? '1' : '0';
        option.dataset.typeKey = item.type_key || '';
        option.dataset.pickerKind = item.picker_kind;
        select.appendChild(option);
    });

    wrapper.appendChild(select);
    return { wrapper, select };
};

const buildProposalPanel = (host, allowedTypes, parentLocationId, onCreated) => {
    const proposableTypes = allowedTypes.filter((type) => type?.proposal_allowed === true);
    if (!parentLocationId || proposableTypes.length === 0) return null;

    const shell = document.createElement('div');
    shell.className = 'border rounded-3 p-3 bg-light';
    shell.dataset.locationProposalShell = '';

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'btn btn-outline-secondary btn-sm';
    toggle.textContent = 'مکان من در فهرست نیست';
    toggle.dataset.locationProposalToggle = '';

    const panel = document.createElement('div');
    panel.className = 'vstack gap-2 mt-3 d-none';
    panel.dataset.locationProposalPanel = '';

    const typeSelect = document.createElement('select');
    typeSelect.className = 'form-select form-select-sm';
    typeSelect.setAttribute('aria-label', 'نوع مکان پیشنهادی');
    proposableTypes.forEach((type) => {
        const option = document.createElement('option');
        option.value = String(type.id);
        option.textContent = type.label || type.key;
        typeSelect.appendChild(option);
    });

    const nameInput = document.createElement('input');
    nameInput.type = 'text';
    nameInput.className = 'form-control form-control-sm';
    nameInput.maxLength = 255;
    nameInput.placeholder = 'نام مکان را وارد کنید';
    nameInput.setAttribute('aria-label', 'نام مکان پیشنهادی');

    const actions = document.createElement('div');
    actions.className = 'd-flex flex-wrap gap-2';

    const submit = document.createElement('button');
    submit.type = 'button';
    submit.className = 'btn btn-primary btn-sm';
    submit.textContent = 'ثبت پیشنهاد مکان';

    const cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.className = 'btn btn-link btn-sm text-decoration-none';
    cancel.textContent = 'انصراف';

    const feedback = document.createElement('div');
    feedback.className = 'small text-secondary';
    feedback.setAttribute('aria-live', 'polite');

    actions.append(submit, cancel);
    panel.append(typeSelect, nameInput, actions, feedback);
    shell.append(toggle, panel);

    toggle.addEventListener('click', () => {
        panel.classList.toggle('d-none');
        if (!panel.classList.contains('d-none')) nameInput.focus();
    });
    cancel.addEventListener('click', () => {
        panel.classList.add('d-none');
        feedback.textContent = '';
    });

    submit.addEventListener('click', async () => {
        const canonicalName = nameInput.value.trim();
        if (!canonicalName) {
            feedback.textContent = 'نام مکان را وارد کنید.';
            feedback.classList.add('text-danger');
            return;
        }

        submit.disabled = true;
        feedback.classList.remove('text-danger');
        feedback.textContent = 'در حال بررسی و ثبت پیشنهاد...';

        const form = host.closest('[data-location-form]') || host.closest('form');
        const csrf = form?.querySelector('input[name="_token"]')?.value || '';
        const locale = document.documentElement.lang || 'fa';

        try {
            const response = await fetch('/locations/proposals', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                },
                body: JSON.stringify({
                    parent_location_id: Number(parentLocationId),
                    location_type_id: Number(typeSelect.value),
                    canonical_name: canonicalName,
                    localized_names: { [locale]: canonicalName },
                }),
            });

            if (!response.ok) throw new Error(`Location proposal request failed: ${response.status}`);
            const result = await response.json();
            await onCreated(result);
            panel.classList.add('d-none');
        } catch (error) {
            console.warn('EarthCoop location proposal could not be created:', error);
            feedback.textContent = 'ثبت پیشنهاد مکان ممکن نشد. دوباره تلاش کنید.';
            feedback.classList.add('text-danger');
        } finally {
            submit.disabled = false;
        }
    });

    return shell;
};

const initializeLocationSelector = async (host) => {
    const levels = host.querySelector('[data-location-levels]');
    const locationId = host.querySelector('[data-location-id][name="location_id"]');
    const proposalId = host.querySelector('[data-location-proposal-id][name="location_proposal_id"]');
    const form = host.closest('[data-location-form]') || host.closest('form');
    const submit = form?.querySelector('[data-location-submit]');
    const status = host.querySelector('[data-location-status]');
    const country = host.dataset.countryCode || 'IR';

    if (!levels || !locationId || !proposalId) return;

    const setStatus = (message, isError = false) => {
        if (!status) return;
        status.textContent = message;
        status.classList.toggle('text-danger', isError);
    };

    const clearSelection = () => {
        locationId.value = '';
        proposalId.value = '';
        if (submit) submit.disabled = true;
    };

    const setSelection = (item) => {
        const values = selectionValues(item);
        locationId.value = values.locationId;
        proposalId.value = values.proposalId;

        if (values.proposalId) {
            if (submit) submit.disabled = false;
            setStatus('این مکان هنوز در انتظار تأیید است. می‌توانید ادامه دهید؛ حوزهٔ رسمی شما تا زمان تأیید بر مبنای نزدیک‌ترین مکان تأییدشده باقی می‌ماند.');
            return;
        }

        if (values.locationId && item?.is_residence_endpoint) {
            if (submit) submit.disabled = false;
            setStatus('این نقطه برای ثبت محل سکونت معتبر است. در صورت وجود گزینه‌های دقیق‌تر، می‌توانید مسیر را ادامه دهید.');
            return;
        }

        if (submit) submit.disabled = true;
        setStatus('برای ادامه، مسیر را تا یک نقطهٔ معتبر برای سکونت اصلی تکمیل کنید.');
    };

    const load = async (url) => {
        setStatus(host.dataset.loadingLabel || 'در حال دریافت گزینه‌های مکانی...');
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error(`Location options request failed: ${response.status}`);
        return normalizePickerPayload(await response.json());
    };

    const removeDeeperLevels = (depth) => {
        levels.querySelectorAll('[data-location-depth]').forEach((element) => {
            if (Number(element.dataset.locationDepth) > depth) element.remove();
        });
    };

    const appendLevel = (payload, depth, parentLocationId = null) => {
        if (!shouldRenderNextLevel(payload)) return;

        const { wrapper, select } = buildSelect(host, payload, depth);
        levels.appendChild(wrapper);

        const refreshAfterProposal = async (result) => {
            if (result?.kind === 'proposal') {
                setSelection({
                    id: result.id,
                    identity: `proposal:${result.id}`,
                    label: result.canonical_name,
                    status: result.status,
                    selectable: true,
                });
                setStatus('پیشنهاد مکان ثبت شد و به‌عنوان محل دقیق در انتظار تأیید انتخاب شد.');
                return;
            }

            if (result?.kind === 'location' && parentLocationId) {
                const refreshed = await load(`/location/options/${encodeURIComponent(parentLocationId)}/children`);
                const matched = refreshed.locations.find((item) => Number(item.id) === Number(result.id));
                if (matched) {
                    setSelection(matched);
                    if (matched.identity && !Array.from(select.options).some((option) => option.value === matched.identity)) {
                        const option = document.createElement('option');
                        option.value = matched.identity;
                        option.textContent = matched.label;
                        select.appendChild(option);
                    }
                    select.value = matched.identity;
                }
            }
        };

        const proposalPanel = buildProposalPanel(
            host,
            payload.allowedTypes,
            parentLocationId,
            refreshAfterProposal,
        );
        if (proposalPanel) wrapper.appendChild(proposalPanel);

        select.addEventListener('change', async () => {
            removeDeeperLevels(depth);
            const selected = pickerItems(payload).find((item) => (item.identity || `${item.picker_kind}:${item.id}`) === select.value) || null;

            if (!selected) {
                clearSelection();
                return;
            }

            setSelection(selected);
            if (selected.picker_kind === 'proposal') return;

            try {
                const children = await load(`/location/options/${encodeURIComponent(selected.id)}/children`);
                if (shouldRenderNextLevel(children)) {
                    appendLevel(children, depth + 1, selected.id);
                } else {
                    setSelection(selected);
                }
            } catch (error) {
                console.warn('EarthCoop location selector could not load children:', error);
                setStatus(host.dataset.errorLabel || 'دریافت گزینه‌های مکانی ممکن نشد.', true);
            }
        });
    };

    clearSelection();

    try {
        const roots = await load(`/location/options/root?country=${encodeURIComponent(country)}`);
        appendLevel(roots, 0);
        if (!roots.locations.length) setStatus('برای این کشور هنوز گزینهٔ مکانی فعالی ثبت نشده است.');
        else setStatus('مسیر محل سکونت را مرحله‌به‌مرحله انتخاب کنید.');
    } catch (error) {
        console.warn('EarthCoop location selector could not load root options:', error);
        setStatus(host.dataset.errorLabel || 'دریافت گزینه‌های مکانی ممکن نشد.', true);
    }
};

selectors.forEach((host) => { void initializeLocationSelector(host); });

export {
    initializeLocationSelector,
    normalizePickerPayload,
    selectionValues,
    shouldRenderNextLevel,
};
