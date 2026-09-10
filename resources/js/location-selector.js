const selectors = Array.from(document.querySelectorAll('[data-location-selector]'));

const normalizeItems = (payload) => Array.isArray(payload?.data) ? payload.data : [];

const buildSelect = (host, items, depth) => {
    const wrapper = document.createElement('div');
    wrapper.dataset.locationDepth = String(depth);

    const select = document.createElement('select');
    select.className = 'form-select';
    select.dataset.locationSelect = String(depth);
    select.setAttribute('aria-label', `سطح مکانی ${depth + 1}`);

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = host.dataset.emptyLabel || 'یک گزینه را انتخاب کنید';
    select.appendChild(placeholder);

    items.forEach((item) => {
        const option = document.createElement('option');
        option.value = String(item.id);
        option.textContent = item.label;
        option.dataset.endpoint = item.is_residence_endpoint ? '1' : '0';
        option.dataset.hasChildren = item.has_children ? '1' : '0';
        option.dataset.typeKey = item.type_key || '';
        select.appendChild(option);
    });

    wrapper.appendChild(select);
    return { wrapper, select };
};

const initializeLocationSelector = async (host) => {
    const levels = host.querySelector('[data-location-levels]');
    const locationId = host.querySelector('[data-location-id][name="location_id"]');
    const form = host.closest('[data-location-form]') || host.closest('form');
    const submit = form?.querySelector('[data-location-submit]');
    const status = host.querySelector('[data-location-status]');
    const country = host.dataset.countryCode || 'IR';

    if (!levels || !locationId) return;

    const setStatus = (message, isError = false) => {
        if (!status) return;
        status.textContent = message;
        status.classList.toggle('text-danger', isError);
    };

    const setEndpoint = (item) => {
        if (item?.is_residence_endpoint) {
            locationId.value = String(item.id);
            if (submit) submit.disabled = false;
            setStatus('این نقطه برای ثبت محل سکونت معتبر است. در صورت وجود گزینه‌های دقیق‌تر، می‌توانید مسیر را ادامه دهید.');
            return;
        }

        locationId.value = '';
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
        return normalizeItems(await response.json());
    };

    const removeDeeperLevels = (depth) => {
        levels.querySelectorAll('[data-location-depth]').forEach((element) => {
            if (Number(element.dataset.locationDepth) > depth) element.remove();
        });
    };

    const appendLevel = (items, depth) => {
        if (!items.length) return;
        const { wrapper, select } = buildSelect(host, items, depth);
        levels.appendChild(wrapper);

        select.addEventListener('change', async () => {
            removeDeeperLevels(depth);
            const selected = items.find((item) => String(item.id) === select.value) || null;
            setEndpoint(selected);
            if (!selected?.has_children) return;

            try {
                const children = await load(`/location/options/${encodeURIComponent(selected.id)}/children`);
                if (children.length) appendLevel(children, depth + 1);
                else setEndpoint(selected);
            } catch (error) {
                console.warn('EarthCoop location selector could not load children:', error);
                setStatus(host.dataset.errorLabel || 'دریافت گزینه‌های مکانی ممکن نشد.', true);
            }
        });
    };

    locationId.value = '';
    if (submit) submit.disabled = true;

    try {
        const roots = await load(`/location/options/root?country=${encodeURIComponent(country)}`);
        appendLevel(roots, 0);
        if (!roots.length) setStatus('برای این کشور هنوز گزینهٔ مکانی فعالی ثبت نشده است.');
        else setStatus('مسیر محل سکونت را مرحله‌به‌مرحله انتخاب کنید.');
    } catch (error) {
        console.warn('EarthCoop location selector could not load root options:', error);
        setStatus(host.dataset.errorLabel || 'دریافت گزینه‌های مکانی ممکن نشد.', true);
    }
};

selectors.forEach((host) => { void initializeLocationSelector(host); });

export { initializeLocationSelector };
