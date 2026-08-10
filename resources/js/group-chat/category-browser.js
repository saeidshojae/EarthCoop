export function createCategoryBrowser({ api, lifecycle }) {
    const overlay = document.getElementById('categoryBlogsOverlay');
    const modal = document.getElementById('categoryBlogsModal');
    const list = document.getElementById('catList');
    const empty = document.getElementById('catEmpty');
    const loading = document.getElementById('catLoading');
    const title = document.getElementById('catModalTitle');
    let controller = null;

    const close = () => {
        modal?.style.setProperty('display', 'none');
        overlay?.style.setProperty('display', 'none');
        document.body.style.overflow = '';
    };
    const open = () => {
        modal?.style.setProperty('display', 'block');
        overlay?.style.setProperty('display', 'block');
        document.body.style.overflow = 'hidden';
    };
    const render = data => {
        title.replaceChildren(document.createTextNode(`دسته: ${data?.category?.name || '—'} (${data?.count ?? 0})`));
        loading.style.display = 'none';
        list.replaceChildren();
        const items = Array.isArray(data?.items) ? data.items : [];
        empty.style.display = items.length ? 'none' : 'block';
        list.style.display = items.length ? 'block' : 'none';
        items.forEach(item => {
            const row = document.createElement('li');
            row.className = 'category-blog-row';
            const link = document.createElement('a');
            link.href = item.url;
            link.textContent = item.title;
            const date = document.createElement('small');
            date.textContent = item.date;
            row.append(link, date);
            list.appendChild(row);
        });
    };
    const load = async trigger => {
        const url = trigger.dataset.url;
        if (!url) return;
        controller?.abort();
        controller = new AbortController();
        list.replaceChildren();
        list.style.display = 'none';
        empty.style.display = 'none';
        loading.style.display = 'block';
        title.textContent = 'در حال بارگذاری...';
        open();
        try {
            const query = new URLSearchParams({ group_id: trigger.dataset.groupId || '' });
            render(await api.json(`${url}?${query}`, { signal: controller.signal }));
        } catch (error) {
            if (error.name === 'AbortError') return;
            loading.style.display = 'none';
            empty.textContent = 'خطا در دریافت لیست پست‌ها.';
            empty.style.display = 'block';
        }
    };

    close();
    lifecycle.on(document, 'click', event => {
        if (event.target.closest?.('#closeCatModal, #categoryBlogsOverlay')) return close();
        const trigger = event.target.closest?.('.open-category-blogs');
        if (!trigger) return;
        event.preventDefault();
        event.stopPropagation();
        void load(trigger);
    });
    lifecycle.on(document, 'keydown', event => event.key === 'Escape' && close());
    lifecycle.add(() => { controller?.abort(); close(); });
    return Object.freeze({ open, close });
}
