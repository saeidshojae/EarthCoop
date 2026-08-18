export function createElections({ api, feed, actions, lifecycle, store }) {
    let optionCount = document.querySelectorAll('#el_dynamic-inputs input[name="options[]"]').length || 1;
    const notify = (message, type = 'info') => window.GroupChatFeedback?.toast?.(message, { type });

    const close = () => {
        const overlay = document.getElementById('electionVotingOverlay');
        if (overlay) overlay.style.display = 'none';
        document.body.style.overflow = '';
        store.setState({ electionOpen: false });
    };

    const open = () => {
        const overlay = document.getElementById('electionVotingOverlay');
        if (!overlay) return false;
        if (overlay.parentElement !== document.body) document.body.appendChild(overlay);
        overlay.style.display = 'flex';
        overlay.scrollTop = 0;
        document.body.style.overflow = 'hidden';
        store.setState({ electionOpen: true });
        lifecycle.timeout(() => {
            window.GroupElectionModal?.updateElectionSelect2?.();
            window.dispatchEvent(new Event('electionModalOpened'));
        }, 600);
        window.GroupChat?.actions?.closeGroupInfo();
        return true;
    };

    const openAdmin = () => {
        const backdrop = document.getElementById('back');
        const modal = document.getElementById('electionOptionsBox');
        if (modal?.parentElement !== document.body) document.body.appendChild(modal);
        if (backdrop) backdrop.style.display = 'none';
        if (modal) {
            modal.style.display = 'flex';
            modal.scrollTop = 0;
            modal.setAttribute('aria-hidden', 'false');
        }
        document.body.style.overflow = 'hidden';
        store.setState({ electionAdminOpen: Boolean(modal) });
        return Boolean(modal);
    };

    const closeAdmin = () => {
        const backdrop = document.getElementById('back');
        const modal = document.getElementById('electionOptionsBox');
        if (backdrop) backdrop.style.display = 'none';
        if (modal) {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }
        document.body.style.overflow = '';
        store.setState({ electionAdminOpen: false });
        return Boolean(modal);
    };

    const addCandidate = () => {
        const container = document.getElementById('el_dynamic-inputs');
        if (!container) return false;
        optionCount += 1;
        const wrapper = document.createElement('div');
        wrapper.className = 'modal-option-row';
        const input = document.createElement('input');
        input.type = 'text';
        input.name = 'options[]';
        input.placeholder = `نامزد ${optionCount}`;
        input.className = 'modal-input';
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'modal-option-remove';
        remove.dataset.groupChatAction = 'remove-election-candidate';
        remove.setAttribute('aria-label', 'حذف نامزد');
        remove.innerHTML = '<i class="fas fa-times" aria-hidden="true"></i>';
        wrapper.append(input, remove);
        container.appendChild(wrapper);
        input.focus();
        return true;
    };

    const resetAdminForm = form => {
        form.reset();
        const container = document.getElementById('el_dynamic-inputs');
        if (container) {
            container.innerHTML = '<input type="text" name="options[]" placeholder="نامزد ۱" class="modal-input mb-2" />';
        }
        optionCount = 1;
        const specialties = document.getElementById('el_specialties_box');
        if (specialties) specialties.style.display = 'none';
    };

    const submitAdmin = async form => {
        if (form.dataset.submitting === 'true') return;
        form.dataset.submitting = 'true';
        store.setState({ electionAdminStatus: 'creating' });
        try {
            const response = await api.request(form.action, {
                method: 'POST',
                body: new FormData(form),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || (data.status && data.status !== 'success')) {
                const errors = data?.errors ? Object.values(data.errors).flat().join('\n') : '';
                throw new Error([data?.message || 'ایجاد انتخابات با خطا مواجه شد.', errors].filter(Boolean).join('\n'));
            }

            const poll = data.poll || {};
            if (poll.id && poll.html) {
                feed.apply([{ ...poll, id: poll.id, content_type: 'poll', action: 'create' }], 'local-election-create');
            }
            resetAdminForm(form);
            closeAdmin();
            notify(data.message || 'انتخابات با موفقیت ایجاد شد.', 'success');
            store.setState({ electionAdminStatus: 'idle' });
        } catch (error) {
            store.setState({ electionAdminStatus: 'error', electionAdminError: error });
            notify(error.message || 'خطا در ایجاد انتخابات', 'error');
        } finally {
            form.dataset.submitting = 'false';
        }
    };

    actions.register('open-election', open);
    actions.register('close-election', close);
    actions.register('open-election-admin', openAdmin);
    actions.register('close-election-admin', closeAdmin);
    actions.register('add-election-candidate', addCandidate);
    actions.register('remove-election-candidate', ({ target }) => Boolean(target.closest('.modal-option-row')?.remove() ?? true));
    actions.register('election-content', ({ event }) => (event.stopPropagation(), true));
    actions.register('open-election-candidates', () => (window.GroupElectionModal?.openCandidatesModal?.(), true));
    actions.register('open-election-guideline', () => (window.GroupElectionModal?.openGuidelineModal?.(), true));
    actions.register('open-election-top-votes', () => (window.GroupElectionModal?.openTopVotesModal?.(), true));

    const type = document.getElementById('poll_election_type');
    if (type) lifecycle.on(type, 'change', () => {
        const specialties = document.getElementById('el_specialties_box');
        if (specialties) specialties.style.display = type.value === '1' ? 'block' : 'none';
    });

    lifecycle.on(document, 'submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.id !== 'electionFormModal') return;
        event.preventDefault();
        void submitAdmin(form);
    });

    lifecycle.on(document, 'keydown', event => {
        if (event.key === 'Escape' && store.getState().electionOpen) close();
    });
    lifecycle.add(() => {
        close();
        closeAdmin();
    });

    return Object.freeze({ open, close, openAdmin, closeAdmin, submitAdmin });
}
