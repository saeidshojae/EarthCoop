const settlementSearchUrl = (parentLocationId, query = '') => {
    const params = new URLSearchParams({ parent_location_id: String(parentLocationId || '') });
    const trimmed = String(query || '').trim();
    if (trimmed) params.set('q', trimmed);
    return '/location/reference-settlements?' + params.toString();
};

const settlementSelectableForClaim = (item) => {
    if (!item || item.governance_authorized === true) return false;
    if (item.classification === 'unverified_settlement' && item.residential_eligibility === 'unverified') return true;
    return item.classification === 'verified_residential_village' && item.residential_eligibility === 'verified';
};

const mountSettlementRegistrationBridge = (shell) => {
    if (!shell) return;
    const form = shell.closest('form');
    const selector = form?.querySelector('[data-location-selector][data-location-selector-context="registration"]');
    const hidden = form?.querySelector('[data-reference-settlement-external-id]');
    const locationInput = form?.querySelector('[data-location-id][name="location_id"]');
    const proposalInput = form?.querySelector('[data-location-proposal-id][name="location_proposal_id"]');
    const submit = form?.querySelector('[data-location-submit]');
    const queryInput = shell.querySelector('[data-settlement-search-input]');
    const searchButton = shell.querySelector('[data-settlement-search-submit]');
    const results = shell.querySelector('[data-settlement-search-results]');
    const status = shell.querySelector('[data-settlement-search-status]');
    if (!form || !selector || !hidden || !locationInput || !proposalInput || !queryInput || !searchButton || !results || !status) return;

    let parentLocationId = '';
    let requestSerial = 0;
    const setStatus = (message, error = false) => {
        status.textContent = message;
        status.classList.toggle('text-danger', error);
    };
    const clearSettlement = () => {
        hidden.value = '';
        results.innerHTML = '';
        delete shell.dataset.selectedSettlementExternalId;
    };
    const hide = () => {
        clearSettlement();
        parentLocationId = '';
        shell.classList.add('d-none');
        setStatus('');
    };
    const choose = (item) => {
        if (!settlementSelectableForClaim(item)) return;
        hidden.value = item.external_id;
        shell.dataset.selectedSettlementExternalId = item.external_id;
        locationInput.value = '';
        proposalInput.value = '';
        if (submit) submit.disabled = false;
        setStatus(
            item.classification === 'verified_residential_village'
                ? 'این آبادی در مرجع سکونتی تأیید شده است؛ درخواست سکونت شما ثبت می‌شود، اما اقامت رسمی و حکمرانی هنوز جداگانه تعیین می‌شوند.'
                : 'این آبادی در مرجع جغرافیایی موجود است؛ درخواست بررسی سکونت روی همین شناسه ثبت می‌شود و مکان تکراری ساخته نخواهد شد.'
        );
        results.querySelectorAll('button[data-settlement-external-id]').forEach((button) => {
            button.setAttribute('aria-pressed', button.dataset.settlementExternalId === item.external_id ? 'true' : 'false');
        });
    };
    const render = (items) => {
        results.innerHTML = '';
        const selectable = (Array.isArray(items) ? items : []).filter(settlementSelectableForClaim);
        if (!selectable.length) {
            setStatus('آبادی قابل درخواست در این محدوده یافت نشد.');
            return;
        }
        selectable.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-secondary text-start w-100';
            button.dataset.settlementExternalId = item.external_id;
            button.setAttribute('aria-pressed', 'false');
            button.textContent = item.name_fa + (item.classification === 'verified_residential_village' ? ' — سکونت مرجع تأیید شده' : ' — نیازمند بررسی سکونت');
            button.addEventListener('click', () => choose(item));
            results.appendChild(button);
        });
        setStatus(selectable.length + ' آبادی قابل انتخاب نمایش داده شد.');
    };
    const search = async () => {
        if (!parentLocationId) return;
        const query = queryInput.value.trim();
        if (query && query.length < 2) {
            setStatus('برای جست‌وجو حداقل دو نویسه وارد کنید.', true);
            return;
        }
        const serial = ++requestSerial;
        searchButton.disabled = true;
        setStatus('در حال جست‌وجوی آبادی‌های مرجع...');
        try {
            const response = await fetch(settlementSearchUrl(parentLocationId, query), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('Settlement catalog request failed: ' + response.status);
            const payload = await response.json();
            if (serial !== requestSerial) return;
            render(payload.data);
        } catch (error) {
            if (serial !== requestSerial) return;
            console.warn('EarthCoop reference settlement search failed:', error);
            setStatus('جست‌وجوی آبادی‌های مرجع ممکن نشد؛ انتخاب مکانی فعلی شما تغییر نکرد.', true);
        } finally {
            if (serial === requestSerial) searchButton.disabled = false;
        }
    };

    selector.addEventListener('earthcoop-location-selection-changed', (event) => {
        const item = event.detail?.item || null;
        clearSettlement();
        if (item?.type_key !== 'rural_district' || !event.detail?.locationId) {
            hide();
            return;
        }
        parentLocationId = String(event.detail.locationId);
        shell.classList.remove('d-none');
        setStatus('اگر محل دقیق شما یکی از آبادی‌های این دهستان است، آن را از بانک مرجع انتخاب کنید.');
        void search();
    });
    searchButton.addEventListener('click', () => { void search(); });
    queryInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            void search();
        }
    });
};

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-settlement-registration-bridge]').forEach(mountSettlementRegistrationBridge);
}

export { settlementSearchUrl, settlementSelectableForClaim, mountSettlementRegistrationBridge };
