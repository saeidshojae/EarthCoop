const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char]);

export function createPins({ api, actions, lifecycle, groupId }) {
    const navigator = document.getElementById('group-pin-navigator');
    const modal = document.getElementById('group-pin-list-modal');
    const list = modal?.querySelector('[data-pin-list]');
    let pins = [];
    let index = 0;

    const canManage = [2, 3].includes(Number(window.yourRole || 0));
    const toast = (message, type = 'info') => window.GroupChatFeedback?.toast?.(message, { type });
    const current = () => pins[index] || null;
    const pinButton = pin => canManage ? `<button type="button" class="group-pin-modal__remove" data-chat-page-action="unpin-content" data-content-type="${pin.content_type}" data-content-id="${pin.content_id}" aria-label="برداشتن سنجاق"><i class="fas fa-thumbtack"></i></button>` : '';

    const render = () => {
        if (!navigator || !modal) return;
        navigator.hidden = pins.length === 0;
        const pin = current();
        navigator.querySelector('[data-pin-count]').textContent = String(pins.length);
        navigator.querySelector('[data-pin-position]').textContent = pins.length ? `${index + 1} از ${pins.length}` : '';
        navigator.querySelector('[data-pin-label]').textContent = pin?.label || 'سنجاق‌شده';
        navigator.querySelector('[data-pin-preview]').textContent = pin?.preview || '';
        list.innerHTML = pins.map((item, position) => `<article class="group-pin-modal__item ${position === index ? 'is-active' : ''}" data-pin-key="${item.key}"><button type="button" class="group-pin-modal__jump" data-chat-page-action="jump-to-pin" data-pin-key="${item.key}"><span class="group-pin-modal__type"><i class="fas fa-thumbtack"></i>${escapeHtml(item.label)}</span><strong>${escapeHtml(item.preview || 'بدون متن')}</strong><small>${escapeHtml(item.pinned_by ? `سنجاق توسط ${item.pinned_by}` : '')}</small></button>${pinButton(item)}</article>`).join('');
        modal.querySelector('[data-pin-empty]').hidden = pins.length !== 0;
        document.querySelectorAll('[data-pin-action]').forEach(button => {
            const isPinned = pins.some(item => item.content_type === button.dataset.contentType && Number(item.content_id) === Number(button.dataset.contentId));
            button.dataset.chatPageAction = isPinned ? 'unpin-content' : 'pin-content';
            const label = button.querySelector('span');
            if (label) label.textContent = isPinned ? 'برداشتن سنجاق' : 'سنجاق کردن';
        });
    };

    const jump = pin => {
        const element = document.getElementById(pin?.anchor || '');
        if (!element) {
            const url = new URL(window.location.href);
            url.searchParams.set('pin', pin.key);
            url.hash = pin.anchor;
            window.location.assign(url.toString());
            return;
        }
        modal.hidden = true;
        document.body.classList.remove('group-pin-modal-open');
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        element.classList.remove('group-pin-highlight');
        void element.offsetWidth;
        element.classList.add('group-pin-highlight');
        lifecycle.timeout(() => element.classList.remove('group-pin-highlight'), 1800);
    };

    const toggle = async (target, pinned) => {
        const contentType = target.dataset.contentType;
        const contentId = Number(target.dataset.contentId);
        if (!contentType || !contentId) return;
        try {
            const data = await api.json(window.GroupChatConfig.pinsUrl, {
                method: pinned ? 'POST' : 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ content_type: contentType, content_id: contentId }),
            });
            apply(data);
            toast(pinned ? 'محتوا به سنجاق‌شده‌های گروه افزوده شد.' : 'سنجاق محتوا برداشته شد.', 'success');
        } catch (error) { toast(error.message || 'تغییر وضعیت سنجاق انجام نشد.', 'error'); }
    };

    const apply = payload => {
        const pin = payload?.pin;
        if (!pin?.key) return;
        const existing = pins.findIndex(item => item.key === pin.key);
        if (payload.pinned) {
            if (existing >= 0) pins.splice(existing, 1);
            pins.unshift(pin);
            index = 0;
        } else if (existing >= 0) {
            pins.splice(existing, 1);
            index = Math.min(index, Math.max(0, pins.length - 1));
        }
        document.querySelectorAll(`[data-content-type="${CSS.escape(pin.content_type)}"][data-content-id="${pin.content_id}"][data-pin-action]`).forEach(button => {
            button.dataset.chatPageAction = payload.pinned ? 'unpin-content' : 'pin-content';
            button.querySelector('span').textContent = payload.pinned ? 'برداشتن سنجاق' : 'سنجاق کردن';
        });
        render();
    };

    actions.register('pin-content', ({ target }) => (void toggle(target, true), true));
    actions.register('unpin-content', ({ target }) => (void toggle(target, false), true));
    actions.register('previous-pin', () => { if (pins.length) index = (index - 1 + pins.length) % pins.length; render(); jump(current()); return true; });
    actions.register('next-pin', () => { if (pins.length) index = (index + 1) % pins.length; render(); jump(current()); return true; });
    actions.register('open-pin-list', () => { modal.hidden = false; document.body.classList.add('group-pin-modal-open'); render(); return true; });
    actions.register('close-pin-list', () => { modal.hidden = true; document.body.classList.remove('group-pin-modal-open'); return true; });
    actions.register('jump-to-pin', ({ target }) => { const pin = pins.find(item => item.key === target.dataset.pinKey); if (pin) { index = pins.indexOf(pin); jump(pin); render(); } return true; });

    const load = async () => {
        try {
            const data = await api.json(window.GroupChatConfig.pinsUrl);
            pins = Array.isArray(data.pins) ? data.pins : [];
            const focusedKey = new URL(window.location.href).searchParams.get('pin');
            index = Math.max(0, pins.findIndex(item => item.key === focusedKey));
            render();
            if (focusedKey && current()) lifecycle.timeout(() => jump(current()), 120);
        }
        catch (_) { /* Realtime and the next successful mutation can recover the navigator. */ }
    };
    void load();

    return { apply, reload: load };
}
