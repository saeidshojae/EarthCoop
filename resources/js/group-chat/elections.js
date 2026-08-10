export function createElections({ actions, lifecycle, store }) {
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
            window.updateElectionSelect2?.();
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

    actions.register('open-election', open);
    actions.register('close-election', close);
    actions.register('open-election-admin', openAdmin);
    lifecycle.on(document, 'keydown', event => {
        if (event.key === 'Escape' && store.getState().electionOpen) close();
    });
    lifecycle.add(close);

    return Object.freeze({ open, close, openAdmin });
}
