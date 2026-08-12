const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;',
}[character]));

export function createSkillLists({ api, actions, store, lifecycle }) {
    const loaded = new Set();
    const renderVisibility = pollId => document.querySelectorAll('.skill-list').forEach(element => {
        element.style.display = Number(element.id.replace('skill-list-', '')) === Number(pollId) ? 'flex' : 'none';
    });
    const renderExperts = (element, payload) => {
        const experts = Array.isArray(payload?.experts) ? payload.experts : [];
        if (!experts.length) {
            element.innerHTML = `<p class="skill-list__state skill-list__state--empty">${escapeHtml(payload?.empty_message || 'متخصصی برای این حوزه یافت نشد.')}</p>`;
            return;
        }
        const pollId = element.id.replace('skill-list-', '');
        element.innerHTML = `<div class="skill-list__header"><strong>متخصصان این حوزه</strong><button type="button" data-chat-page-action="close-skill-list" aria-label="بستن">×</button></div><div class="skill-list__items">${experts.map(expert => `<article class="skill-list__expert"><a href="${escapeHtml(expert.profile_url)}" class="skill-list__identity">${expert.avatar_url ? `<img src="${escapeHtml(expert.avatar_url)}" alt="">` : '<span class="skill-list__avatar"><i class="fas fa-user-tie"></i></span>'}<span>${escapeHtml(expert.name)}</span></a><button type="button" class="skill-list__action${expert.selected ? ' is-selected' : ''}" data-chat-page-action="delegate-vote" data-poll-id="${pollId}" data-expert-id="${expert.id}">${expert.selected ? 'لغو تفویض' : 'تفویض رأی'}</button></article>`).join('')}</div>`;
    };
    const load = async (pollId, force = false) => {
        const element = document.getElementById(`skill-list-${pollId}`);
        if (!element || (!force && loaded.has(Number(pollId)))) return;
        element.innerHTML = '<p class="skill-list__state"><span class="skill-list__spinner" aria-hidden="true"></span>در حال دریافت فهرست متخصصان…</p>';
        try {
            renderExperts(element, await api.json(element.dataset.expertsUrl));
            loaded.add(Number(pollId));
        } catch (error) {
            element.innerHTML = '<p class="skill-list__state skill-list__state--error">دریافت فهرست متخصصان ممکن نشد. دوباره تلاش کنید.</p>';
        }
    };
    const close = () => {
        renderVisibility(null);
        store.setState({ openSkillListId: null });
        return true;
    };
    const open = pollId => {
        if (!document.getElementById(`skill-list-${pollId}`)) return false;
        renderVisibility(pollId);
        store.setState({ openSkillListId: Number(pollId) });
        load(pollId);
        return true;
    };
    const toggle = pollId => store.getState().openSkillListId === Number(pollId) ? close() : open(pollId);
    const delegate = async target => {
        const pollId = Number(target.dataset.pollId);
        const element = document.getElementById(`skill-list-${pollId}`);
        if (!element || target.disabled) return false;
        target.disabled = true;
        try {
            const url = element.dataset.delegationUrlTemplate.replace('__EXPERT__', target.dataset.expertId);
            const result = await api.json(url, { method: 'POST' });
            loaded.delete(pollId);
            await load(pollId, true);
            const status = element.previousElementSibling?.querySelector('.poll-card__delegation-status');
            if (status) status.textContent = result.message;
            return true;
        } catch (error) {
            target.disabled = false;
            return false;
        }
    };
    const restore = () => renderVisibility(store.getState().openSkillListId ?? null);
    actions.register('toggle-skill-list', ({ target }) => toggle(Number(target.dataset.pollId)));
    actions.register('close-skill-list', close);
    actions.register('delegate-vote', ({ target }) => delegate(target));
    lifecycle.add(close);
    return Object.freeze({ open, close, toggle, restore });
}
