<style>
    /* Adaptive presentation layer for the existing #groupInfoPanel.
       Content/permissions stay owned by group_info_panel; this file only owns shell geometry and secondary presentation. */
    #groupInfoPanel.group-info-panel {
        position: fixed !important;
        z-index: 1250 !important;
        overflow: hidden !important;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity .22s ease, visibility .22s ease, transform .28s cubic-bezier(.22,.8,.25,1) !important;
    }

    #groupInfoPanel.group-info-panel.is-open {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }

    #groupInfoPanel .group-info-panel__inner {
        max-height: inherit;
        overflow-y: auto;
        overscroll-behavior: contain;
        scrollbar-gutter: stable;
        padding-bottom: max(1.5rem, env(safe-area-inset-bottom));
    }

    /* The legacy aside must not reserve a desktop column once the panel becomes an overlay. */
    #group-chat-main-container .grid:has(> aside #groupInfoPanel) {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    #group-chat-main-container aside:has(#groupInfoPanel) {
        min-width: 0;
        min-height: 0;
    }

    #groupInfoPanel .control-center-secondary-tabs {
        display: flex;
        align-items: center;
        gap: .35rem;
        margin: -.15rem 0 .85rem;
        padding: .28rem;
        max-width: 100%;
        overflow-x: auto;
        overscroll-behavior-inline: contain;
        scrollbar-width: thin;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background: #f8fafc;
    }

    #groupInfoPanel .control-center-secondary-tab {
        flex: 0 0 auto;
        min-height: 36px;
        padding: .42rem .72rem;
        border: 0;
        border-radius: 10px;
        background: transparent;
        color: #64748b;
        font-size: .72rem;
        font-weight: 850;
        white-space: nowrap;
        cursor: pointer;
        transition: background-color .16s ease, color .16s ease, box-shadow .16s ease;
    }

    #groupInfoPanel .control-center-secondary-tab:hover {
        color: #0f766e;
        background: #ecfdf5;
    }

    #groupInfoPanel .control-center-secondary-tab.is-active {
        color: #047857;
        background: #fff;
        box-shadow: 0 6px 18px -15px rgba(15, 23, 42, .8);
    }

    #groupInfoPanel .control-center-secondary-pane[hidden] {
        display: none !important;
    }

    #groupInfoPanel .control-center-secondary-pane--synthetic {
        margin-top: .2rem;
        padding: .9rem;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        background: #fff;
    }

    #groupInfoPanel .control-center-secondary-pane--synthetic > p {
        margin: 0 0 .75rem;
        color: #64748b;
        font-size: .74rem;
        line-height: 1.8;
    }

    #groupInfoPanel .control-center-secondary-pane > .control-center-action-grid {
        margin-bottom: .9rem;
    }

    @media (max-width: 767px) {
        #groupInfoPanel.group-info-panel {
            top: auto !important;
            right: 0 !important;
            bottom: 0;
            left: 0;
            width: 100%;
            max-width: none !important;
            height: auto !important;
            max-height: min(90dvh, 760px) !important;
            border-radius: 28px 28px 0 0 !important;
            transform: translateY(calc(100% + 24px));
            box-shadow: 0 -24px 64px -30px rgba(15, 23, 42, .5) !important;
        }

        #groupInfoPanel.group-info-panel::before {
            content: '';
            display: block;
            width: 46px;
            height: 5px;
            margin: 9px auto 0;
            border-radius: 999px;
            background: #cbd5e1;
        }

        #groupInfoPanel.group-info-panel.is-open {
            transform: translateY(0);
        }

        #groupInfoPanel .group-info-panel__inner {
            padding-top: .75rem;
        }

        #groupInfoPanel .control-center-secondary-tabs {
            margin-inline: -.05rem;
        }

        #groupInfoPanel .control-center-secondary-tab {
            min-height: 34px;
            padding: .4rem .62rem;
            font-size: .68rem;
        }

        [data-group-hero-content] {
            max-height: min(42dvh, 300px);
            overflow-y: auto;
            overscroll-behavior: contain;
            padding-top: .65rem !important;
            padding-bottom: .7rem !important;
        }

        [data-group-hero-content] .grid.grid-cols-1 {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: .45rem !important;
        }

        [data-group-hero-content] .grid.grid-cols-1 > * {
            min-width: 0;
            padding: .55rem .45rem !important;
            font-size: .72rem;
        }

        [data-group-hero-content] p {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            overflow: hidden;
        }
    }

    @media (min-width: 768px) {
        #groupInfoPanel.group-info-panel {
            top: 50% !important;
            right: auto !important;
            bottom: auto;
            left: 50%;
            width: min(960px, calc(100vw - 3rem));
            max-width: 960px !important;
            height: auto !important;
            max-height: min(88dvh, 860px) !important;
            border-radius: 28px !important;
            transform: translate(-50%, calc(-50% + 18px));
            box-shadow: 0 30px 90px -34px rgba(15, 23, 42, .55) !important;
        }

        #groupInfoPanel.group-info-panel.is-open {
            transform: translate(-50%, -50%);
        }

        #groupInfoPanel .panel-close-btn {
            display: flex !important;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        #groupInfoPanel.group-info-panel,
        #groupInfoPanel .control-center-secondary-tab {
            transition: none !important;
        }
    }
</style>

<script type="module">
    const panel = document.getElementById('groupInfoPanel');
    if (panel) {
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'true');
        panel.setAttribute('aria-hidden', panel.classList.contains('is-open') ? 'false' : 'true');
        panel.setAttribute('aria-labelledby', 'groupControlCenterTitle');

        const title = panel.querySelector('.panel-hero__title');
        if (title) title.id = 'groupControlCenterTitle';

        const tablist = panel.querySelector('.panel-tabs');
        if (tablist) {
            tablist.setAttribute('role', 'tablist');
            tablist.setAttribute('aria-label', 'بخش‌های پنل گروه');
        }

        const lifecycle = window.GroupChatLifecycle;
        if (!lifecycle) throw new Error('GroupChatLifecycle is required by Group Control Center shell');

        const normalize = value => String(value ?? '').trim().toLocaleLowerCase('fa');
        const subsectionByTitle = (section, text) => Array.from(section.querySelectorAll(':scope > .control-center-subsection'))
            .find(item => item.querySelector('.control-center-subsection__title h4')?.textContent.trim().includes(text));

        const secondaryTabDefinitions = {
            content: [
                { key: 'posts', label: 'پست‌ها', source: section => subsectionByTitle(section, 'پست‌ها'), search: 'content' },
                { key: 'polls', label: 'نظرسنجی‌ها', source: section => subsectionByTitle(section, 'نظرسنجی‌ها'), search: 'content' },
            ],
            members: [
                { key: 'people', label: 'اعضا', source: section => subsectionByTitle(section, 'اعضای گروه'), search: 'members' },
                { key: 'leaders', label: 'مدیران و بازرسان', source: section => subsectionByTitle(section, 'مدیران و بازرسان'), search: 'members' },
                { key: 'connections', label: 'ارتباطات', synthetic: true, search: null },
            ],
            governance: [
                { key: 'elections', label: 'انتخابات', synthetic: true, search: null },
                { key: 'sessions', label: 'نشست‌ها', synthetic: true, search: null },
                { key: 'management', label: 'مدیریت', synthetic: true, search: null },
            ],
            tools: [
                { key: 'systems', label: 'سامانه‌های گروه', source: section => section.querySelector(':scope > .control-center-action-grid'), search: null },
                { key: 'my-groups', label: 'گروه‌های من', source: section => subsectionByTitle(section, 'گروه‌های من'), search: null },
            ],
        };

        const createSyntheticPane = (section, definition) => {
            const pane = document.createElement('div');
            pane.className = 'control-center-secondary-pane control-center-secondary-pane--synthetic';

            if (section.id === 'members' && definition.key === 'connections') {
                const intro = document.createElement('p');
                intro.textContent = 'ارتباط مدیریتی با مدیران گروه‌های دیگر و پیگیری درخواست‌های گفت‌وگو از همین بخش انجام می‌شود.';
                pane.appendChild(intro);
                const button = section.querySelector('#addChatRequestButton');
                if (button) pane.appendChild(button);
            }

            if (section.id === 'governance') {
                const grid = document.createElement('div');
                grid.className = 'control-center-action-grid';
                pane.appendChild(grid);
            }

            return pane;
        };

        const organizeGovernance = (section, panes) => {
            const originalGrid = Array.from(section.children).find(child => child.classList?.contains('control-center-action-grid'));
            if (originalGrid) {
                Array.from(originalGrid.children).forEach(card => {
                    const titleText = card.querySelector('strong')?.textContent.trim() || '';
                    const destination = card.matches('[data-session-toggle], [data-session-admin-open]')
                        ? panes.sessions
                        : card.matches('[data-chat-page-action="open-election"], [data-chat-page-action="open-election-admin"]') || titleText.includes('انتخابات')
                            ? panes.elections
                            : panes.management;
                    destination.querySelector('.control-center-action-grid')?.appendChild(card);
                });
                originalGrid.remove();
            }

            const electionSection = subsectionByTitle(section, 'انتخابات گروه');
            if (electionSection) panes.elections.appendChild(electionSection);
            const statsSection = subsectionByTitle(section, 'آمار عملیاتی');
            if (statsSection) panes.management.appendChild(statsSection);
        };

        const activateSecondaryTab = (section, key, focus = false) => {
            const buttons = Array.from(section.querySelectorAll(':scope > .control-center-secondary-tabs [data-control-center-subtab]'));
            const panes = Array.from(section.querySelectorAll(':scope > [data-control-center-subpane]'));
            const button = buttons.find(item => item.dataset.controlCenterSubtab === key);
            const activeSubpane = panes.find(item => item.dataset.controlCenterSubpane === key);
            if (!button || !activeSubpane) return false;

            buttons.forEach(item => {
                const active = item === button;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-selected', String(active));
                item.tabIndex = active ? 0 : -1;
            });
            panes.forEach(item => {
                const active = item === activeSubpane;
                item.hidden = !active;
                item.classList.toggle('is-active', active);
            });
            section.dataset.activeControlCenterSubtab = key;
            if (focus) button.focus();
            section.dispatchEvent(new CustomEvent('control-center:subtab-changed', { detail: { key, activeSubpane } }));
            return true;
        };

        const installSecondaryTabs = section => {
            const definitions = secondaryTabDefinitions[section.id];
            if (!definitions?.length || section.querySelector(':scope > .control-center-secondary-tabs')) return;

            const heading = section.querySelector(':scope > .control-center-section-heading');
            if (!heading) return;

            const nav = document.createElement('div');
            nav.className = 'control-center-secondary-tabs';
            nav.dataset.controlCenterSubtabs = section.id;
            nav.setAttribute('role', 'tablist');
            nav.setAttribute('aria-label', `زیر‌بخش‌های ${heading.querySelector('h3')?.textContent.trim() || section.id}`);

            const panes = {};
            definitions.forEach((definition, index) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'control-center-secondary-tab';
                button.dataset.controlCenterSubtab = definition.key;
                button.setAttribute('role', 'tab');
                button.id = `control-center-${section.id}-${definition.key}-tab`;
                button.textContent = definition.label;
                nav.appendChild(button);

                const pane = definition.synthetic ? createSyntheticPane(section, definition) : definition.source?.(section);
                if (!pane) {
                    button.disabled = true;
                    return;
                }
                pane.classList.add('control-center-secondary-pane');
                pane.dataset.controlCenterSubpane = definition.key;
                pane.dataset.controlCenterSearchScope = definition.search || 'none';
                pane.setAttribute('role', 'tabpanel');
                pane.id = `control-center-${section.id}-${definition.key}-pane`;
                pane.setAttribute('aria-labelledby', button.id);
                button.setAttribute('aria-controls', pane.id);
                panes[definition.key] = pane;

                lifecycle.on(button, 'click', () => activateSecondaryTab(section, definition.key));
                lifecycle.on(button, 'keydown', event => {
                    if (!['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(event.key)) return;
                    event.preventDefault();
                    const enabled = Array.from(nav.querySelectorAll('[data-control-center-subtab]:not(:disabled)'));
                    const current = enabled.indexOf(button);
                    const next = event.key === 'Home' ? 0
                        : event.key === 'End' ? enabled.length - 1
                            : (current + (event.key === 'ArrowRight' ? -1 : 1) + enabled.length) % enabled.length;
                    activateSecondaryTab(section, enabled[next].dataset.controlCenterSubtab, true);
                });

                if (definition.synthetic) section.appendChild(pane);
                if (index > 0) pane.hidden = true;
            });

            heading.after(nav);
            if (section.id === 'governance') organizeGovernance(section, panes);
            const first = definitions.find(definition => panes[definition.key]);
            if (first) activateSecondaryTab(section, first.key);
        };

        const installScopedSearch = (section, searchName, itemSelector) => {
            const input = section.querySelector(`[data-control-center-search="${searchName}"]`);
            if (!input) return;
            const searchBlock = input.closest('.panel-search');
            const count = searchName === 'members' ? section.querySelector('#membersCount') : null;
            const empty = searchName === 'members' ? section.querySelector('#membersSearchEmpty') : section.querySelector('#contentSearchEmpty');

            const filterActiveSubpane = event => {
                event?.stopImmediatePropagation();
                const activeSubpane = section.querySelector(':scope > [data-control-center-subpane]:not([hidden])');
                const searchEnabled = activeSubpane?.dataset.controlCenterSearchScope === searchName;
                if (searchBlock) searchBlock.hidden = !searchEnabled;
                if (count) count.hidden = !searchEnabled;
                if (empty && !searchEnabled) empty.hidden = true;
                if (!searchEnabled || !activeSubpane) return;

                const query = normalize(input.value);
                let shown = 0;
                const allItems = Array.from(section.querySelectorAll(itemSelector));
                allItems.forEach(item => {
                    if (!activeSubpane.contains(item)) {
                        item.hidden = false;
                        return;
                    }
                    const haystack = searchName === 'members'
                        ? normalize(`${item.dataset.name || ''} ${item.dataset.role || ''} ${item.dataset.email || ''}`)
                        : normalize(item.dataset.controlCenterSearchText || item.textContent);
                    const hit = !query || haystack.includes(query);
                    item.hidden = !hit;
                    if (hit) shown++;
                });
                if (count) count.textContent = `نمایش ${shown} از ${activeSubpane.querySelectorAll(itemSelector).length}`;
                if (empty) empty.hidden = shown !== 0 || !query;
            };

            lifecycle.on(input, 'input', filterActiveSubpane, { capture: true });
            lifecycle.on(section, 'control-center:subtab-changed', () => {
                input.dispatchEvent(new Event('input', { bubbles: false }));
            });
            input.dispatchEvent(new Event('input', { bubbles: false }));
        };

        ['content', 'members', 'governance', 'tools'].forEach(id => {
            const section = panel.querySelector(`#${id}`);
            if (section) installSecondaryTabs(section);
        });
        const contentSection = panel.querySelector('#content');
        const membersSection = panel.querySelector('#members');
        if (contentSection) installScopedSearch(contentSection, 'content', '[data-control-center-content-item]');
        if (membersSection) installScopedSearch(membersSection, 'members', '.control-center-member-item');
    }
</script>
