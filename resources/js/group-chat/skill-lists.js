export function createSkillLists({ actions, store, lifecycle }) {
    const render = pollId => {
        document.querySelectorAll('.skill-list').forEach(element => {
            element.style.display = Number(element.id.replace('skill-list-', '')) === Number(pollId) ? 'flex' : 'none';
        });
        const backdrop = document.getElementById('back');
        if (backdrop) backdrop.style.display = pollId == null ? 'none' : 'block';
    };
    const close = () => {
        render(null);
        store.setState({ openSkillListId: null });
        return true;
    };
    const open = pollId => {
        if (!document.getElementById(`skill-list-${pollId}`)) return false;
        window.GroupChat?.composer?.closePost();
        window.GroupChat?.composer?.closePoll();
        window.GroupChat?.elections?.close();
        window.GroupChat?.elections?.closeAdmin();
        render(pollId);
        store.setState({ openSkillListId: Number(pollId) });
        return true;
    };
    const toggle = pollId => store.getState().openSkillListId === Number(pollId) ? close() : open(pollId);
    const restore = () => render(store.getState().openSkillListId ?? null);

    actions.register('toggle-skill-list', ({ target }) => toggle(Number(target.dataset.pollId)));
    actions.register('close-skill-list', close);
    lifecycle.add(close);

    return Object.freeze({ open, close, toggle, restore });
}
