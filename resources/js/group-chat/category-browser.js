export function createCategoryBrowser({ api, lifecycle }) {
    const overlay = document.getElementById('categoryBlogsOverlay');
    const modal = document.getElementById('categoryBlogsModal');
    const list = document.getElementById('catList');
    const empty = document.getElementById('catEmpty');
    const loading = document.getElementById('catLoading');
    const title = document.getElementById('catModalTitle');
    let controller = null;
    const cache = new Map();
    const cacheTtlMs = 30_000;

    const close = () => {
        modal?.style.setProperty('display', 'none');
        overlay?.style.setProperty('display', 'none');
        modal?.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };
    const open = () => {
        modal?.style.setProperty('display', 'flex');
        overlay?.style.setProperty('display', 'block');
        modal?.setAttribute('aria-hidden', 'false');
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
            row.className = 'category-browser__row';
            const details = document.createElement('div');
            details.className = 'category-browser__details';
            const link = document.createElement('a');
            link.className = 'category-browser__title';
            link.href = item.url;
            link.textContent = item.title;
            link.title = item.title;
            const date = document.createElement('small');
            date.className = 'category-browser__date';
            date.textContent = item.date;
            const view = document.createElement('a');
            view.className = 'category-browser__view';
            view.href = item.url;
            view.textContent = 'مشاهده';
            details.append(link, date);
            row.append(details, view);
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
            const requestUrl = `${url}?${query}`;
            const cached = cache.get(requestUrl);
            if (cached && Date.now() - cached.storedAt < cacheTtlMs) return render(cached.data);
            const data = await api.json(requestUrl, { signal: controller.signal });
            cache.set(requestUrl, { data, storedAt: Date.now() });
            render(data);
        } catch (error) {
            if (error.name === 'AbortError') return;
            loading.style.display = 'none';
            empty.textContent = 'خطا در دریافت لیست پست‌ها.';
            empty.style.display = 'block';
        }
    };

    close();
    lifecycle.on(document, 'click', event => {
        if (event.target.closest?.('#closeCatModal') || event.target === modal || event.target === overlay) return close();
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
