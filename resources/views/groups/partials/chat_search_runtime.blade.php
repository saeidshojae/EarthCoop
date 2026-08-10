<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- باز/بسته‌شدن پنل ---
    const wrap = document.getElementById('gc-search-wrap');
    const input = document.getElementById('gc-search-input');
    const btn = document.getElementById('btn-chat-search');
    const dd = document.getElementById('gc-search-dd');
    const statusEl = dd?.querySelector('.gc-search-status');

    if (!wrap || !input || !btn || !dd) {
        // Search DOM elements may not exist on all pages - this is expected
        // console.warn('Search DOM missing', {wrap, input, btn, dd});
        return;
    }

    function openSearch() {
        wrap.hidden = false;
        btn.setAttribute('aria-expanded', 'true');
        setTimeout(() => input.focus(), 10);
    }

    function closeSearch() {
        wrap.hidden = true;
        btn.setAttribute('aria-expanded', 'false');
    }

    function toggleSearch() {
        wrap.hidden ? openSearch() : closeSearch();
    }

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleSearch();
    });
    document.addEventListener('click', (e) => {
        const inside = e.target.closest('#gc-search-wrap') || e.target.closest('#btn-chat-search');
        if (!inside) closeSearch();
    });
    wrap.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeSearch();
            btn.focus();
        }
    });

    // هوک برای اسپینر آیکن/استاتوس
    window.__setSearching = function(on) {
        statusEl.style.display = on ? 'flex' : 'none';
        btn.classList.toggle('searching', !!on);
    };
    window.__ensureSearchOpen = function() {
        if (wrap.hidden) openSearch();
    };

    // --- سرچ AJAX ---
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
    const groupId = {{ $group->id }};
    const listEl = dd.querySelector('.gc-search-list');
    const moreBtn = dd.querySelector('.gc-search-more');
    const clearBtn = document.getElementById('gc-search-clear');

    let page = 1,
        loading = false,
        lastQuery = '',
        items = [],
        activeIndex = -1,
        hasMore = false;
    const openDD = () => dd.hidden = false;
    const closeDD = () => {
        dd.hidden = true;
        activeIndex = -1;
        updateActive();
    };

    function debounce(fn, ms = 300) {
        let t;
        return (...a) => {
            clearTimeout(t);
            t = setTimeout(() => fn(...a), ms);
        }
    }

    function setStatus(txt) {
        statusEl.textContent = txt;
        statusEl.style.display = txt ? 'flex' : 'none';
    }

    function setMore(vis) {
        moreBtn.hidden = !vis;
    }

    function renderList() {
        listEl.innerHTML = '';
        items.forEach((it, idx) => {
            const li = document.createElement('li');
            li.className = 'gc-search-item' + (idx === activeIndex ? ' active' : '');
            li.dataset.index = idx;

            const type = document.createElement('div');
            type.className = 'type';
            type.textContent = it.type === 'message' ? 'پیام' : it.type === 'post' ? 'پست' : 'نظرسنجی';

            const meta = document.createElement('div');
            meta.className = 'meta';
            const title = document.createElement('div');
            title.className = 'title';
            title.textContent = it.title || (it.type === 'post' ? 'پست' : it.type === 'poll' ?
                'نظرسنجی' : 'کاربر');
            const snip = document.createElement('div');
            snip.className = 'snip';
            snip.innerHTML = it.snippet || '';
            const date = document.createElement('small');
            date.style.color = '#6b7280';
            date.textContent = it.date || '';
            meta.appendChild(title);
            meta.appendChild(snip);
            meta.appendChild(date);
            li.appendChild(type);
            li.appendChild(meta);
            li.addEventListener('click', () => goTo(it));
            listEl.appendChild(li);
        });
    }

    function updateActive() {
        listEl.querySelectorAll('.gc-search-item').forEach((el, i) => el.classList.toggle('active', i ===
            activeIndex));
    }

    function goTo(it) {
        closeDD();
        if (!it?.url) return;
        location.hash = it.url;
    }

    async function fetchPage(reset = false) {
        if (loading) return;
        loading = true;
        window.__setSearching(true);
        setStatus('در حال جستجو…');
        if (reset) {
            items = [];
            page = 1;
            listEl.innerHTML = '';
            setMore(false);
        }

        try {
            const url = new URL(`{{ url('/groups') }}/${groupId}/search`, window.location.origin);
            url.searchParams.set('q', lastQuery);
            url.searchParams.set('page', page);
            url.searchParams.set('limit', 20);

            const res = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                credentials: 'same-origin'
            });

            if (!res.ok) {
                // برای کمک به دیباگ
                const txt = await res.text();
                console.error('Search HTTP Error', res.status, txt);
                throw new Error('HTTP ' + res.status);
            }

            const data = await res.json();
            const newItems = Array.isArray(data?.items) ? data.items : [];
            hasMore = !!data?.has_more;

            items = items.concat(newItems);
            renderList();
            setStatus(newItems.length ? '' : (page === 1 ? 'چیزی پیدا نشد.' : ''));
            setMore(hasMore);
            openDD();
            window.__ensureSearchOpen();
        } catch (e) {
            console.error('Search fetch error', e);
            setStatus('خطا در دریافت نتایج');
            openDD();
            window.__ensureSearchOpen();
        } finally {
            loading = false;
            window.__setSearching(false);
        }
    }

    const onInput = debounce(() => {
        const q = input.value.trim();
        if (!q) {
            closeDD();
            return;
        }
        lastQuery = q;
        fetchPage(true);
    }, 300);

    input.addEventListener('input', onInput);
    input.addEventListener('focus', () => {
        if (items.length) openDD();
        else if (input.value.trim()) {
            lastQuery = input.value.trim();
            fetchPage(true);
        }
    });
    clearBtn.addEventListener('click', () => {
        input.value = '';
        closeDD();
        items = [];
        listEl.innerHTML = '';
    });
    moreBtn.addEventListener('click', () => {
        if (!hasMore) return;
        page += 1;
        fetchPage(false);
    });

    // بستن dropdown با کلیک بیرون از سرچ‌بار
    document.addEventListener('click', (e) => {
        const box = e.target.closest('.gc-searchbar');
        if (!box) closeDD();
    });

    // ناوبری کیبورد
    input.addEventListener('keydown', (e) => {
        if (dd.hidden) return;
        const max = items.length - 1;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIndex = activeIndex < max ? activeIndex + 1 : 0;
            updateActive();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIndex = activeIndex > 0 ? activeIndex - 1 : max;
            updateActive();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeIndex >= 0) goTo(items[activeIndex]);
        } else if (e.key === 'Escape') {
            closeDD();
        }
    });
});
</script>
