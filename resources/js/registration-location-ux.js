const registrationSelector = document.querySelector('[data-location-selector-context="registration"]');

if (registrationSelector) {
    const levels = registrationSelector.querySelector('[data-location-levels]');
    const path = registrationSelector.querySelector('[data-location-path]');
    const locationInput = registrationSelector.querySelector('[data-location-id]');
    const proposalInput = registrationSelector.querySelector('[data-location-proposal-id]');
    const submit = registrationSelector.closest('form')?.querySelector('[data-location-submit]');

    const selectedLabels = () => Array.from(levels?.querySelectorAll('select') || [])
        .map((select) => select.selectedOptions?.[0])
        .filter((option) => option?.value)
        .map((option) => ({
            label: option.textContent?.replace(/\s+—\s+در انتظار تأیید$/, '').trim() || '',
            proposal: String(option.value || '').startsWith('proposal:'),
        }))
        .filter((item) => item.label);

    const renderPath = (items = selectedLabels()) => {
        if (!path) return;
        path.replaceChildren();
        if (!items.length) {
            path.textContent = 'مسیر انتخاب نشده';
            path.classList.add('text-muted');
            return;
        }
        path.classList.remove('text-muted');
        items.forEach((item, index) => {
            const node = document.createElement('span');
            node.setAttribute('data-location-path-item', '');
            node.className = item.proposal ? 'location-proposal badge bg-warning-subtle text-warning-emphasis' : 'badge bg-primary-subtle text-primary-emphasis';
            node.textContent = item.proposal ? `${item.label} (در انتظار بررسی)` : item.label;
            path.appendChild(node);
            if (index < items.length - 1) path.appendChild(document.createTextNode(' ← '));
        });
    };

    levels?.addEventListener('change', () => window.setTimeout(() => renderPath(), 0));

    registrationSelector.addEventListener('location:hydrate', (event) => {
        const suggestedId = event.detail?.suggested_location_id;
        if (!suggestedId || !locationInput) return;
        locationInput.value = String(suggestedId);
        if (proposalInput) proposalInput.value = '';
        if (submit) submit.disabled = false;
        const label = event.detail?.suggested_location_label || 'موقعیت تشخیص‌داده‌شده';
        renderPath([{ label, proposal: false }]);
    });

    const observer = new MutationObserver(() => renderPath());
    if (levels) observer.observe(levels, { childList: true, subtree: true });
    renderPath();
}
