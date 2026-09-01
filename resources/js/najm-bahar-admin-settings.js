const CONSTITUTIONAL_FIELDS = [
    'najm_bahar_initial_amount',
    'najm_bahar_initial_active_percentage',
    'najm_bahar_initial_active_type',
    'najm_bahar_initial_active_fixed_amount',
];

const disableConstitutionalControls = () => {
    CONSTITUTIONAL_FIELDS.forEach((field) => {
        document.querySelectorAll(`[name="${field}"]`).forEach((element) => {
            element.disabled = true;
            element.removeAttribute('required');
            element.setAttribute('aria-disabled', 'true');
            element.setAttribute('title', 'این مقدار طبق قانون اساسی نجم بهار ثابت است.');
        });
    });

    const initialAmount = document.querySelector('[name="najm_bahar_initial_amount"]');
    const legacyForm = initialAmount?.closest('form');
    if (legacyForm?.action?.includes('/admin/najm-bahar/dashboard/initial-amount')) {
        const submit = legacyForm.querySelector('button[type="submit"]');
        if (submit) {
            submit.disabled = true;
            submit.setAttribute('aria-disabled', 'true');
            submit.setAttribute('title', 'مبلغ صدور اولیه طبق قانون اساسی نجم بهار ثابت است.');
        }
    }
};

window.toggleAutoActivation = () => {
    const enabled = document.querySelector('input[name="najm_bahar_auto_activation_enabled"]');
    const settings = document.getElementById('auto-activation-settings');
    if (!enabled || !settings) return;
    settings.style.display = enabled.checked ? 'block' : 'none';
};

window.toggleReputationConversion = () => {
    const enabled = document.querySelector('input[name="reputation_conversion_enabled"]');
    const settings = document.getElementById('reputation-conversion-settings');
    if (!enabled || !settings) return;
    settings.style.display = enabled.checked ? 'block' : 'none';
};

window.updateReputationPreview = () => {
    const ratioInput = document.getElementById('reputation_to_gol_ratio');
    if (!ratioInput) return;

    const ratio = Number.parseInt(ratioInput.value, 10) || 100;
    const ratioDisplay = document.getElementById('ratio-display');
    const exampleConversion = document.getElementById('example-conversion');
    const exampleBahar = document.getElementById('example-bahar');
    const examplePoints = 500;
    const gols = Math.floor(examplePoints / ratio);

    if (ratioDisplay) ratioDisplay.textContent = String(ratio);
    if (exampleConversion) exampleConversion.textContent = String(gols);
    if (exampleBahar) exampleBahar.textContent = String(gols * 100);
};

// Compatibility shims for legacy inline handlers on constitutional controls.
// The controls themselves are disabled, so these functions intentionally do not mutate policy state.
window.toggleActiveType = () => {};
window.updatePreview = () => {};

const boot = () => {
    disableConstitutionalControls();
    window.toggleAutoActivation();
    window.toggleReputationConversion();
    window.updateReputationPreview();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
    boot();
}
