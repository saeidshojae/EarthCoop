export function createTabs({ store, lifecycle, root = document.getElementById('groupInfoPanel') }) {
    if (!root) return { activate: () => false };

    const tabs = Array.from(root.querySelectorAll('.panel-tabs .tab[data-tab]'));
    const contents = Array.from(root.querySelectorAll('.panel-tab-contents > .tab-content'));
    const activate = name => {
        const tab = tabs.find(item => item.dataset.tab === name);
        const content = contents.find(item => item.id === name);
        if (!tab || !content) return false;
        tabs.forEach(item => item.classList.toggle('active', item === tab));
        contents.forEach(item => item.classList.toggle('active', item === content));
        store.setState({ activeInfoTab: name });
        if (name === 'stats') window.GroupInfoPanel?.loadStats?.();
        return true;
    };

    tabs.forEach(tab => lifecycle.on(tab, 'click', () => activate(tab.dataset.tab)));
    const initial = tabs.find(tab => tab.classList.contains('active'))?.dataset.tab;
    if (initial) store.setState({ activeInfoTab: initial });

    return Object.freeze({ activate });
}
