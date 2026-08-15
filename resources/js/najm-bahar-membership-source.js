(() => {
    let latestMembershipInfo = null;
    const originalFetch = window.fetch?.bind(window);

    if (originalFetch) {
        window.fetch = async (...args) => {
            const response = await originalFetch(...args);
            try {
                const url = typeof args[0] === 'string' ? args[0] : args[0]?.url;
                if (url && url.includes('membership-fee') && response.ok) {
                    const data = await response.clone().json();
                    if (data && data.payment_source_required === true) {
                        latestMembershipInfo = data;
                    }
                }
            } catch (_) {
                // The observer below still protects submission even if this response is not JSON.
            }
            return response;
        };
    }

    const buildOption = ({ value, title, description, balance, enabled, checked }) => {
        const disabled = enabled ? '' : 'disabled';
        const disabledClass = enabled ? '' : ' opacity-50 cursor-not-allowed';
        return `
            <label class="block rounded-xl border border-gray-200 p-3 cursor-pointer${disabledClass}">
                <div class="flex items-start gap-3">
                    <input type="radio" name="payment_source" value="${value}" ${checked ? 'checked' : ''} ${disabled} class="mt-1">
                    <div class="flex-1">
                        <div class="font-bold text-gray-800">${title}</div>
                        <div class="text-xs text-gray-500 mt-1">${description}</div>
                        <div class="text-sm font-semibold mt-2">موجودی: ${balance || '—'} بهار</div>
                    </div>
                </div>
            </label>`;
    };

    const enhanceForm = (form) => {
        if (!form || form.dataset.paymentSourceEnhanced === '1') return;
        form.dataset.paymentSourceEnhanced = '1';

        const info = latestMembershipInfo || {};
        const canDim = info.can_pay_from_dim !== false;
        const canActive = info.can_pay_from_active !== false;
        const defaultSource = info.default_payment_source === 'active' ? 'active' : 'dim';

        const selector = document.createElement('div');
        selector.className = 'space-y-2 rounded-xl bg-slate-50 border border-slate-200 p-3';
        selector.setAttribute('data-membership-payment-source-selector', 'true');
        selector.innerHTML = `
            <div class="font-bold text-gray-800">منبع پرداخت را انتخاب کنید</div>
            <div class="text-xs text-gray-500">انتخاب شما صریح است و نجم بهار منبع پول را به‌صورت خودکار تغییر نمی‌دهد.</div>
            ${buildOption({
                value: 'dim',
                title: 'پول کمرنگ (Dim)',
                description: 'فقط به اندازه حق عضویت فعال می‌شود و سپس پرداخت انجام می‌شود.',
                balance: info.balance_dim_formatted,
                enabled: canDim,
                checked: defaultSource === 'dim' && canDim,
            })}
            ${buildOption({
                value: 'active',
                title: 'پول فعال',
                description: 'پرداخت از موجودی فعال حساب اصلی یا حساب فرعی انتخاب‌شده انجام می‌شود.',
                balance: info.balance_active_formatted,
                enabled: canActive,
                checked: (defaultSource === 'active' && canActive) || (!canDim && canActive),
            })}
            <p class="text-xs text-red-600 hidden" data-membership-source-error>برای پرداخت، منبع پول را انتخاب کنید.</p>`;

        const subAccountInput = form.querySelector('input[name="sub_account_id"]');
        if (subAccountInput) {
            subAccountInput.insertAdjacentElement('afterend', selector);
        } else {
            form.prepend(selector);
        }

        form.addEventListener('submit', (event) => {
            const selected = form.querySelector('input[name="payment_source"]:checked:not(:disabled)');
            const error = form.querySelector('[data-membership-source-error]');
            if (!selected) {
                event.preventDefault();
                error?.classList.remove('hidden');
                return;
            }
            error?.classList.add('hidden');

            if (subAccountInput) {
                subAccountInput.disabled = selected.value !== 'active';
            }
        });
    };

    const scan = () => enhanceForm(document.getElementById('payMembershipForm'));

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scan);
    } else {
        scan();
    }

    const observer = new MutationObserver(scan);
    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
