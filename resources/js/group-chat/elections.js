export function createElections({ actions, lifecycle, store }) {
    let optionCount = document.querySelectorAll('#el_dynamic-inputs input[name="options[]"]').length || 1;
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
        if (backdrop) backdrop.style.display = 'block';
        if (modal) modal.style.display = 'block';
        store.setState({ electionAdminOpen: Boolean(modal) });
        return Boolean(modal);
    };
    const closeAdmin = () => {
        const backdrop = document.getElementById('back');
        const modal = document.getElementById('electionOptionsBox');
        if (backdrop) backdrop.style.display = 'none';
        if (modal) modal.style.display = 'none';
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
    lifecycle.on(document, 'keydown', event => {
        if (event.key === 'Escape' && store.getState().electionOpen) close();
    });
    lifecycle.add(() => {
        close();
        closeAdmin();
    });

    return Object.freeze({ open, close, openAdmin, closeAdmin });
}
