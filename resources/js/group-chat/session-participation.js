export function createSessionParticipation({ api, lifecycle }) {
    const config = window.GroupChatConfig || {};
    const memberModal = document.getElementById('sessionRequestModal');
    const adminModal = document.getElementById('sessionAdminModal');
    const listen = (target, type, handler) => target ? lifecycle.on(target, type, handler) : null;
    const status = (element, message, type = 'info') => {
        if (!element) return;
        element.hidden = false;
        element.className = `session-participation-status is-${type}`;
        element.textContent = message;
    };
    const show = modal => {
        if (!modal) return;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('session-modal-open');
    };
    const hide = modal => {
        if (!modal) return;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.session-participation-modal:not([hidden])')) document.body.classList.remove('session-modal-open');
    };

    const requestStatus = document.getElementById('sessionRequestStatus');
    const submitRequest = document.getElementById('submitSessionRequest');
    const sendRequest = async () => {
        if (!submitRequest || submitRequest.disabled) return;
        submitRequest.disabled = true;
        const previous = submitRequest.innerHTML;
        submitRequest.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال ارسال';
        try {
            const data = await api.json(config.participationRequestUrl, {
                method: 'POST',
                body: JSON.stringify({ message: document.getElementById('sessionRequestMessage')?.value || '' }),
                headers: { 'Content-Type': 'application/json' },
            });
            status(requestStatus, data.message, 'success');
            submitRequest.innerHTML = '<i class="fas fa-check"></i> درخواست ارسال شد';
        } catch (error) {
            status(requestStatus, error.message || 'ارسال درخواست انجام نشد.', 'error');
            submitRequest.disabled = false;
            submitRequest.innerHTML = previous;
        }
    };

    let adminData = { requests: [], members: [] };
    const selectedIds = () => [...(adminModal?.querySelectorAll('.session-member-check:checked') || [])].map(input => Number(input.value));
    const escape = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
    const memberCard = (member, pending = false) => `
        <label class="session-member-card ${member.allowed ? 'is-allowed' : ''}" data-search="${escape(`${member.name} ${member.email || ''}`.toLowerCase())}">
            <input class="session-member-check" type="checkbox" value="${member.user_id || member.id}">
            <span class="session-member-avatar">${escape(member.name?.slice(0, 1) || 'ع')}</span>
            <span class="session-member-copy"><strong>${escape(member.name)}</strong>${pending ? `<small>${escape(member.message || 'درخواست مشارکت')} · ${escape(member.requested_at)}</small>` : `<small>${escape(member.email || (member.allowed ? 'مجاز به مشارکت' : 'عضو گروه'))}</small>`}</span>
            ${pending ? '<span class="session-pending-badge">دست بلند کرده</span>' : member.allowed ? '<span class="session-allowed-badge">مجاز</span>' : ''}
        </label>`;
    const renderAdmin = () => {
        const pending = document.getElementById('sessionPendingList');
        const members = document.getElementById('sessionMembersList');
        if (pending) pending.innerHTML = adminData.requests.length ? adminData.requests.map(item => memberCard(item, true)).join('') : '<div class="session-admin-empty">درخواست تازه‌ای وجود ندارد.</div>';
        if (members) members.innerHTML = adminData.members.length ? adminData.members.map(item => memberCard(item)).join('') : '<div class="session-admin-empty">عضوی برای مدیریت وجود ندارد.</div>';
        const count = document.getElementById('sessionPendingCount');
        if (count) count.textContent = String(adminData.requests.length).replace(/\d/g, digit => '۰۱۲۳۴۵۶۷۸۹'[digit]);
        document.getElementById('sessionAdminLoading')?.setAttribute('hidden', '');
        document.getElementById('sessionAdminContent')?.removeAttribute('hidden');
    };
    const loadAdmin = async () => {
        show(adminModal);
        document.getElementById('sessionAdminLoading')?.removeAttribute('hidden');
        document.getElementById('sessionAdminContent')?.setAttribute('hidden', '');
        try {
            adminData = await api.json(config.participationIndexUrl);
            renderAdmin();
        } catch (error) {
            status(document.getElementById('sessionAdminStatus'), error.message || 'دریافت فهرست اعضا انجام نشد.', 'error');
        }
    };
    const bulk = async action => {
        const ids = selectedIds();
        if (!ids.length) return status(document.getElementById('sessionAdminStatus'), 'حداقل یک عضو را انتخاب کنید.', 'error');
        try {
            const data = await api.json(config.participationBulkUrl, {
                method: 'POST', body: JSON.stringify({ user_ids: ids, action }), headers: { 'Content-Type': 'application/json' },
            });
            status(document.getElementById('sessionAdminStatus'), data.message, 'success');
            await loadAdmin();
        } catch (error) {
            status(document.getElementById('sessionAdminStatus'), error.message || 'تغییر مجوز انجام نشد.', 'error');
        }
    };

    listen(document, 'click', event => {
        if (event.target.closest('[data-session-request-open]')) show(memberModal);
        if (event.target.closest('[data-session-modal-close]')) hide(memberModal);
        if (event.target.closest('[data-session-admin-open]')) loadAdmin();
        if (event.target.closest('[data-session-admin-close]')) hide(adminModal);
        const action = event.target.closest('[data-session-bulk-action]')?.dataset.sessionBulkAction;
        if (action) bulk(action);
    });
    listen(window, 'group-chat:session-closed', () => show(memberModal));
    listen(document.getElementById('sessionMemberSearch'), 'input', event => {
        const query = event.target.value.trim().toLowerCase();
        adminModal?.querySelectorAll('.session-member-card').forEach(card => { card.hidden = query && !card.dataset.search.includes(query); });
    });
    listen(document.getElementById('sessionSelectAll'), 'change', event => {
        adminModal?.querySelectorAll('.session-member-card:not([hidden]) .session-member-check').forEach(input => { input.checked = event.target.checked; });
    });
    listen(submitRequest, 'click', sendRequest);

    return Object.freeze({ showRequest: () => show(memberModal), showAdmin: loadAdmin });
}
