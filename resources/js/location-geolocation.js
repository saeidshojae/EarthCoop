const messageForResult = (data) => {
    switch (data?.status) {
        case 'matched':
            return {
                tone: 'success',
                text: 'موقعیت تقریبی شناسایی شد. برای ثبت نهایی، محل سکونت را با انتخاب دستی تأیید کنید.',
            };
        case 'conflict':
            return {
                tone: 'warning',
                text: 'موقعیت تقریبی با انتخاب دستی شما متفاوت است. انتخاب دستی شما حفظ شده است؛ در صورت نیاز مسیر را بررسی و تأیید کنید.',
            };
        case 'unmatched':
            return {
                tone: 'secondary',
                text: 'موقعیت تقریبی به یک مکان رسمی در سامانه تطبیق داده نشد. انتخاب دستی همچنان در دسترس است.',
            };
        case 'provider_error':
        default:
            return {
                tone: 'secondary',
                text: 'تشخیص خودکار موقعیت در دسترس نیست. می‌توانید بدون محدودیت از انتخاب دستی ادامه دهید.',
            };
    }
};

const mountGeolocationAssist = (root) => {
    const detectButton = root.querySelector('[data-location-geolocation-detect]');
    const manualButton = root.querySelector('[data-location-geolocation-manual]');
    const status = root.querySelector('[data-location-geolocation-status]');
    const locationInput = root.querySelector('[data-location-id]');
    const form = root.closest('form');

    if (!detectButton || !manualButton || !status) return;

    const showStatus = (text, tone = 'secondary') => {
        status.textContent = text;
        status.classList.remove('d-none', 'text-secondary', 'text-success', 'text-warning', 'text-danger');
        status.classList.add(`text-${tone}`);
    };

    const focusManualSelection = () => {
        const firstSelect = root.querySelector('[data-location-levels] select:not([disabled])');
        if (firstSelect) {
            firstSelect.focus();
            firstSelect.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    };

    manualButton.addEventListener('click', focusManualSelection);

    detectButton.addEventListener('click', () => {
        if (!('geolocation' in navigator)) {
            showStatus('مرورگر شما تشخیص موقعیت را پشتیبانی نمی‌کند. انتخاب دستی همچنان در دسترس است.');
            focusManualSelection();
            return;
        }

        detectButton.disabled = true;
        showStatus('در حال تشخیص تقریبی موقعیت شما...');

        navigator.geolocation.getCurrentPosition(async (position) => {
            try {
                const csrfToken = form?.querySelector('input[name="_token"]')?.value
                    || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    || '';

                const response = await fetch('/location-governance/geolocation/match', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    },
                    body: JSON.stringify({
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        locale: document.documentElement.lang || 'fa',
                        manual_location_id: locationInput?.value ? Number(locationInput.value) : null,
                    }),
                });

                if (!response.ok) throw new Error(`Geolocation match failed with ${response.status}`);

                const data = await response.json();
                const message = messageForResult(data);
                showStatus(message.text, message.tone);
            } catch (error) {
                showStatus('تشخیص خودکار موقعیت کامل نشد. انتخاب دستی بدون محدودیت ادامه دارد.', 'secondary');
            } finally {
                detectButton.disabled = false;
            }
        }, () => {
            detectButton.disabled = false;
            showStatus('دسترسی به موقعیت داده نشد یا موقعیت قابل دریافت نبود. انتخاب دستی همچنان در دسترس است.', 'secondary');
            focusManualSelection();
        }, {
            enableHighAccuracy: false,
            timeout: 10000,
            maximumAge: 300000,
        });
    });
};

document.querySelectorAll('[data-location-geolocation]').forEach((controls) => {
    const root = controls.closest('[data-location-selector]');
    if (root) mountGeolocationAssist(root);
});
