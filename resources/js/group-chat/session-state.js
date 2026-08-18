export function createSessionState({ api, lifecycle }) {
    const config = window.GroupChatConfig || {};
    const modal = document.getElementById('sessionScheduleModal');
    const form = document.getElementById('sessionScheduleForm');
    let sessionOpen = Boolean(config.sessionOpen);
    let activeSessionId = null;

    const closeModal = () => {
        if (!modal) return;
        modal.hidden = true; modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('session-schedule-modal-open');
    };
    const openModal = () => {
        if (!modal) return;
        modal.hidden = false; modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('session-schedule-modal-open');
        form?.querySelector('[name="title"]')?.focus();
    };
    const setStatus = (message, error = false) => {
        const node = document.getElementById('sessionScheduleStatus');
        if (!node) return;
        node.hidden = false; node.textContent = message; node.classList.toggle('is-error', error);
    };
    const updateControls = isOpen => {
        sessionOpen = Boolean(isOpen);
        document.querySelectorAll('[data-session-toggle]').forEach(button => {
            button.dataset.sessionOpen = sessionOpen ? '1' : '0';
            const label = button.querySelector('span');
            if (label) label.textContent = sessionOpen ? 'غیرفعال کردن نشست' : 'فعال کردن نشست';
            else button.textContent = sessionOpen ? 'غیرفعال کردن نشست' : 'فعال کردن نشست';
            const icon = button.querySelector('i');
            icon?.classList.toggle('fa-toggle-on', sessionOpen);
            icon?.classList.toggle('fa-toggle-off', !sessionOpen);
        });
    };
    const refreshComposer = async () => {
        try {
            const response = await fetch(window.location.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) return;
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const current = document.querySelector('.chat-composer-shell');
            const fresh = doc.querySelector('.chat-composer-shell');
            if (current && fresh) {
                current.replaceWith(fresh);
                document.dispatchEvent(new CustomEvent('group-chat:composer-replaced'));
            }
        } catch (_) { /* Server authorization remains authoritative. */ }
    };
    const addNotice = (action, payload) => {
        if (!['session_started', 'session_ended'].includes(action)) return;
        const existing = document.querySelector(`[data-session-id="${payload.id}"][data-session-event="${action}"]`);
        if (existing) return;
        const node = document.createElement('article');
        node.className = `group-session-notice group-session-notice--${action === 'session_started' ? 'active' : 'ended'}`;
        node.dataset.sessionId = payload.id; node.dataset.sessionEvent = action;
        const title = action === 'session_started' ? 'جلسه آغاز شد' : 'جلسه پایان یافت';
        const details = [payload.subject ? `<p><b>موضوع:</b> ${escapeHtml(payload.subject)}</p>` : '', payload.agenda ? `<p><b>دستور جلسه:</b> ${escapeHtml(payload.agenda).replace(/\n/g, '<br>')}</p>` : ''].join('');
        node.innerHTML = `<span class="group-session-notice__icon"><i class="fas ${action === 'session_started' ? 'fa-lock' : 'fa-check'}"></i></span><div class="group-session-notice__body"><strong>${title} — ${escapeHtml(payload.title || 'نشست گروه')}</strong>${details}<small>${new Date(action === 'session_started' ? payload.started_at : payload.ended_at).toLocaleString('fa-IR')}</small></div>`;
        const feed = document.getElementById('chat-box');
        feed?.appendChild(node); node.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };
    const escapeHtml = value => String(value || '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
    const publishState = data => {
        window.dispatchEvent(new CustomEvent('group-chat:session-state', { detail: data || {} }));
    };
    const receive = async (action, payload = {}) => {
        if (action === 'session_scheduled') {
            window.GroupChatFeedback?.toast?.(`جلسه «${payload.title}» برنامه‌ریزی شد.`, { type: 'info', duration: 7000 });
            return;
        }
        const isOpen = action === 'session_ended' || Boolean(payload.is_open);
        activeSessionId = isOpen ? null : (Number(payload.id || 0) || activeSessionId);
        updateControls(isOpen); addNotice(action, payload);
        window.GroupChatFeedback?.toast?.(isOpen ? 'جلسه پایان یافت؛ مشارکت عمومی فعال شد.' : `جلسه «${payload.title || ''}» آغاز شد؛ مشارکت عمومی محدود است.`, { type: 'info', duration: 8000 });
        await refreshComposer();
    };
    const submit = async event => {
        event.preventDefault();
        const button = form?.querySelector('[type="submit"]');
        if (!button || button.disabled) return;
        button.disabled = true;
        let data;
        try {
            const values = Object.fromEntries(new FormData(form).entries());
            data = await api.json(config.sessionToggleUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(values) });
        } catch (error) { setStatus(error.message || 'ثبت جلسه انجام نشد.', true); }
        finally { button.disabled = false; }
        if (!data) return;
        closeModal(); form.reset();
        window.GroupChatFeedback?.toast?.(data.message, { type: 'success' });
        if (data.session) void receive(data.session.status === 'scheduled' ? 'session_scheduled' : 'session_started', data.session);
    };
    const endNow = async () => {
        let data;
        try {
            data = await api.json(config.sessionToggleUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' });
        } catch (error) { window.GroupChatFeedback?.toast?.(error.message || 'پایان جلسه ثبت نشد.', { type: 'error' }); }
        if (!data) return;
        window.GroupChatFeedback?.toast?.(data.message, { type: 'success' });
        if (data.session) void receive('session_ended', data.session);
    };

    lifecycle.on(document, 'click', event => {
        if (event.target.closest('[data-session-schedule-close]')) closeModal();
        if (event.target.closest('[data-session-toggle]')) sessionOpen ? openModal() : endNow();
    });
    lifecycle.on(form, 'submit', submit);
    const reconcile = async () => {
        if (document.hidden || !config.participationStateUrl) return;
        try {
            const data = await api.json(config.participationStateUrl);
            publishState(data);
            const nextOpen = Boolean(data.session_open);
            const nextId = Number(data.session?.id || 0) || null;
            if (nextOpen !== sessionOpen || (!nextOpen && nextId !== activeSessionId)) {
                activeSessionId = nextId;
                await receive(nextOpen ? 'session_state_changed' : 'session_started', data.session || { is_open: nextOpen });
            }
        } catch (_) { /* Realtime remains primary; this is only a resilience fallback. */ }
    };
    // Realtime is primary. Keep one shared 15s fallback poll for compatibility;
    // participation badges consume the same state event instead of polling again.
    lifecycle.interval(reconcile, 15000);
    return Object.freeze({ receive, refreshComposer, reconcile });
}
