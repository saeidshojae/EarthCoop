export function createTabs({ store, lifecycle, root = document.getElementById('groupInfoPanel') }) {
    if (!root) return { activate: () => false };

    const tabs = Array.from(root.querySelectorAll('.panel-tabs .tab[data-tab]'));
    const contents = Array.from(root.querySelectorAll('.panel-tab-contents > .tab-content'));
    const activate = name => {
        const tab = tabs.find(item => item.dataset.tab === name);
        const content = contents.find(item => item.id === name);
        if (!tab || !content) return false;

        tabs.forEach(item => {
            const active = item === tab;
            item.classList.toggle('active', active);
            item.setAttribute('aria-selected', String(active));
            item.setAttribute('tabindex', active ? '0' : '-1');
        });
        contents.forEach(item => {
            const active = item === content;
            item.classList.toggle('active', active);
            item.hidden = !active;
        });

        store.setState({ activeInfoTab: name });
        if (name === 'governance') window.GroupInfoPanel?.loadStats?.();
        return true;
    };

    tabs.forEach(tab => lifecycle.on(tab, 'click', () => activate(tab.dataset.tab)));
    const initial = tabs.find(tab => tab.classList.contains('active'))?.dataset.tab;
    if (initial) activate(initial);

    return Object.freeze({ activate });
}
