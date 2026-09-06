const contributionMobileSchemas = {
    'tab-posts': { primaryIndex: 0 },
    'tab-comments': { primaryIndex: 0 },
    'tab-replies': { primaryIndex: 0 },
    'tab-reactions': { primaryIndex: 1 },
    'tab-polls': { primaryIndex: 1 },
    'tab-votes': { primaryIndex: 0 },
};

const cloneCellContent = (cell, target) => {
    Array.from(cell.childNodes).forEach((node) => target.appendChild(node.cloneNode(true)));
};

const buildMobileCards = (panelId, schema) => {
    const panel = document.getElementById(panelId);
    if (!panel || panel.dataset.mobileCardsReady === '1') return;

    const wrapper = panel.querySelector('.data-table-wrapper');
    const table = wrapper?.querySelector('table.data-table');
    if (!wrapper || !table) return;

    const headers = Array.from(table.querySelectorAll('thead th')).map((th) => th.textContent.trim());
    const rows = Array.from(table.querySelectorAll('tbody > tr'));
    const list = document.createElement('div');
    list.className = 'contribution-mobile-cards';
    list.setAttribute('role', 'list');
    list.setAttribute('aria-label', panel.querySelector('h3')?.textContent.trim() || 'فهرست مشارکت‌ها');

    rows.forEach((row) => {
        const cells = Array.from(row.querySelectorAll(':scope > td'));
        if (!cells.length) return;

        const colspan = Number(cells[0].getAttribute('colspan') || 1);
        if (cells.length === 1 && colspan > 1) {
            const empty = document.createElement('div');
            empty.className = 'contribution-mobile-empty';
            empty.textContent = cells[0].textContent.trim();
            list.appendChild(empty);
            return;
        }

        const card = document.createElement('article');
        card.className = 'contribution-mobile-card';
        card.setAttribute('role', 'listitem');

        const head = document.createElement('div');
        head.className = 'contribution-mobile-card__head';
        const title = document.createElement('div');
        title.className = 'contribution-mobile-card__title';
        const primaryCell = cells[schema.primaryIndex] || cells[0];
        cloneCellContent(primaryCell, title);
        head.appendChild(title);
        card.appendChild(head);

        const details = document.createElement('div');
        details.className = 'contribution-mobile-card__details';

        cells.forEach((cell, index) => {
            if (index === schema.primaryIndex) return;
            const item = document.createElement('div');
            item.className = 'contribution-mobile-card__detail';

            const label = document.createElement('span');
            label.className = 'contribution-mobile-card__label';
            label.textContent = headers[index] || 'جزئیات';

            const value = document.createElement('div');
            value.className = 'contribution-mobile-card__value';
            cloneCellContent(cell, value);

            item.append(label, value);
            details.appendChild(item);
        });

        card.appendChild(details);
        list.appendChild(card);
    });

    panel.classList.add('mobile-adapted-participation');
    panel.dataset.mobileCardsReady = '1';
    wrapper.insertAdjacentElement('afterend', list);
};

Object.entries(contributionMobileSchemas).forEach(([panelId, schema]) => buildMobileCards(panelId, schema));
