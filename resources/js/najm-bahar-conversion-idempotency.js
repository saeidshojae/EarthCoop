const conversionForm = document.querySelector('form#conversionForm');

if (conversionForm) {
    let idempotencyInput = conversionForm.querySelector('input[name="idempotency_key"]');

    if (!idempotencyInput) {
        idempotencyInput = document.createElement('input');
        idempotencyInput.type = 'hidden';
        idempotencyInput.name = 'idempotency_key';
        conversionForm.appendChild(idempotencyInput);
    }

    const ensureStableKey = () => {
        if (idempotencyInput.value) return idempotencyInput.value;

        idempotencyInput.value = typeof crypto?.randomUUID === 'function'
            ? crypto.randomUUID()
            : `conversion-${Date.now()}-${Math.random().toString(16).slice(2)}`;

        return idempotencyInput.value;
    };

    conversionForm.addEventListener('submit', ensureStableKey);
}
