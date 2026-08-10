export function createFeedback({ documentRef = globalThis.document, defaultDuration = 4500 } = {}) {
    let toastRegion;
    const ensureRegion = () => {
        if (toastRegion?.isConnected) return toastRegion;
        toastRegion = documentRef.createElement('div');
        toastRegion.id = 'group-chat-toast-region';
        toastRegion.setAttribute('aria-live', 'polite');
        toastRegion.style.cssText = 'position:fixed;left:16px;bottom:16px;z-index:10050;display:grid;gap:8px;max-width:min(420px,calc(100vw - 32px));';
        documentRef.body.appendChild(toastRegion);
        return toastRegion;
    };
    const toast = (message, { type = 'info', duration = defaultDuration } = {}) => {
        const item = documentRef.createElement('div');
        item.setAttribute('role', type === 'error' ? 'alert' : 'status');
        item.style.cssText = `padding:12px 16px;border-radius:12px;color:#fff;box-shadow:0 12px 30px rgba(15,23,42,.24);background:${type === 'error' ? '#b91c1c' : type === 'success' ? '#047857' : '#334155'};direction:rtl;`;
        item.textContent = String(message || '');
        ensureRegion().appendChild(item);
        const remove = () => item.remove();
        if (duration > 0) globalThis.setTimeout(remove, duration);
        return { element: item, remove };
    };
    const ask = ({ message, title, confirmText, cancelText, input = false, defaultValue = '' }) => new Promise(resolve => {
        const previousFocus = documentRef.activeElement;
        const overlay = documentRef.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;z-index:10060;background:rgba(15,23,42,.48);display:grid;place-items:center;padding:20px;';
        overlay.innerHTML = `<div role="dialog" aria-modal="true" aria-labelledby="group-chat-dialog-title" style="width:min(440px,100%);background:#fff;border-radius:18px;padding:20px;direction:rtl;box-shadow:0 24px 70px rgba(15,23,42,.3)"><h2 id="group-chat-dialog-title" style="font-size:18px;font-weight:700;margin:0 0 10px"></h2><p data-dialog-message style="margin:0 0 16px;white-space:pre-wrap"></p>${input ? '<textarea data-dialog-input rows="3" style="width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:10px;margin-bottom:16px"></textarea>' : ''}<div style="display:flex;gap:8px;justify-content:flex-end"><button type="button" data-dialog-cancel style="padding:8px 14px;border:0;border-radius:10px">${cancelText}</button><button type="button" data-dialog-confirm style="padding:8px 14px;border:0;border-radius:10px;background:#047857;color:#fff">${confirmText}</button></div></div>`;
        overlay.querySelector('#group-chat-dialog-title').textContent = title;
        overlay.querySelector('[data-dialog-message]').textContent = message;
        const inputElement = overlay.querySelector('[data-dialog-input]');
        if (inputElement) inputElement.value = defaultValue;
        let settled = false;
        const finish = value => {
            if (settled) return;
            settled = true;
            overlay.remove();
            previousFocus?.focus?.();
            resolve(value);
        };
        overlay.addEventListener('click', event => {
            if (event.target === overlay || event.target.closest('[data-dialog-cancel]')) finish(input ? null : false);
            else if (event.target.closest('[data-dialog-confirm]')) finish(input ? inputElement.value : true);
        });
        overlay.addEventListener('keydown', event => {
            if (event.key === 'Escape') finish(input ? null : false);
        });
        documentRef.body.appendChild(overlay);
        (inputElement || overlay.querySelector('[data-dialog-confirm]')).focus();
    });
    return {
        toast,
        confirm: (message, options = {}) => ask({ message, title: options.title || 'تأیید عملیات', confirmText: options.confirmText || 'تأیید', cancelText: options.cancelText || 'انصراف' }),
        prompt: (message, options = {}) => ask({ message, title: options.title || 'ورود اطلاعات', confirmText: options.confirmText || 'ارسال', cancelText: options.cancelText || 'انصراف', input: true, defaultValue: options.defaultValue || '' }),
    };
}
