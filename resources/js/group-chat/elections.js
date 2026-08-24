export function createElections({ api, feed, actions, lifecycle, store }) {
    let optionCount = document.querySelectorAll('#el_dynamic-inputs input[name="options[]"]').length || 1;
    const notify = (message, type = 'info') => window.GroupChatFeedback?.toast?.(message, { type });

    const ballotForm = () => document.querySelector('[data-systemic-election-ballot]');
    const ballotChoices = form => [...form.querySelectorAll('[data-election-choice]')];
    const selectedFor = (form, role) => ballotChoices(form).filter(el => el.dataset.electionChoice === role && el.checked);
    const ballotLimits = form => ({
        manager: Math.max(0, Number.parseInt(form.dataset.electionManagerLimit || '0', 10) || 0),
        inspector: Math.max(0, Number.parseInt(form.dataset.electionInspectorLimit || '0', 10) || 0),
    });

    const refreshBallot = form => {
        if (!form) return;
        const limits = ballotLimits(form);
        ['manager', 'inspector'].forEach(role => {
            const badge = form.querySelector(`[data-election-count="${role}"]`);
            if (badge) badge.textContent = `${selectedFor(form, role).length}/${limits[role]}`;
            const tabCount = form.querySelector(`[data-election-role-tab-count="${role}"]`);
            if (tabCount) tabCount.textContent = `${selectedFor(form, role).length}/${limits[role]}`;
        });

        const candidateIds = [...new Set(ballotChoices(form).map(el => el.dataset.candidateId).filter(Boolean))];
        candidateIds.forEach(candidateId => {
            const selectedChoice = ballotChoices(form).find(el => el.dataset.candidateId === candidateId && el.checked);
            form.querySelectorAll(`[data-vote-visibility-for="${candidateId}"]`).forEach(select => {
                const active = Boolean(selectedChoice) && select.dataset.electionRole === selectedChoice.dataset.electionChoice;
                select.disabled = !active;
                select.style.pointerEvents = active ? 'auto' : 'none';
                select.setAttribute('aria-disabled', active ? 'false' : 'true');
            });
        });
    };

    const activateRoleTab = (form, role) => {
        if (!form) return;
        form.querySelectorAll('[data-election-role-tab]').forEach(tab => {
            const active = tab.dataset.electionRoleTab === role;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.tabIndex = active ? 0 : -1;
        });
        form.querySelectorAll('[data-election-role-panel]').forEach(panel => {
            const active = panel.dataset.electionRolePanel === role;
            panel.classList.toggle('is-active', active);
            panel.setAttribute('aria-hidden', active ? 'false' : 'true');
        });
    };

    const setupMobileRoleTabs = form => {
        if (!form || form.querySelector('[data-election-role-tabs]')) return;
        const managerList = form.querySelector('[data-election-list="manager"]');
        const inspectorList = form.querySelector('[data-election-list="inspector"]');
        const managerPanel = managerList?.closest('.col-12');
        const inspectorPanel = inspectorList?.closest('.col-12');
        const row = managerPanel?.parentElement;
        if (!managerPanel || !inspectorPanel || !row || row !== inspectorPanel.parentElement) return;

        managerPanel.dataset.electionRolePanel = 'manager';
        inspectorPanel.dataset.electionRolePanel = 'inspector';
        managerPanel.id = managerPanel.id || 'electionManagerPanel';
        inspectorPanel.id = inspectorPanel.id || 'electionInspectorPanel';

        const limits = ballotLimits(form);
        const tabs = document.createElement('div');
        tabs.className = 'election-role-tabs';
        tabs.dataset.electionRoleTabs = 'true';
        tabs.setAttribute('role', 'tablist');
        tabs.setAttribute('aria-label', 'انتخاب نقش در برگه انتخابات');
        tabs.innerHTML = `
            <button type="button" class="election-role-tab is-active" role="tab" aria-selected="true" aria-controls="${managerPanel.id}" data-election-role-tab="manager">
                <span>مدیران</span><span class="election-role-tab__count" data-election-role-tab-count="manager">${selectedFor(form, 'manager').length}/${limits.manager}</span>
            </button>
            <button type="button" class="election-role-tab" role="tab" aria-selected="false" aria-controls="${inspectorPanel.id}" data-election-role-tab="inspector" tabindex="-1">
                <span>بازرسان</span><span class="election-role-tab__count" data-election-role-tab-count="inspector">${selectedFor(form, 'inspector').length}/${limits.inspector}</span>
            </button>`;
        row.before(tabs);
        activateRoleTab(form, 'manager');
    };

    const syncCommentVisibility = form => {
        if (!form) return;
        const visibility = form.querySelector('#electionCommentVisibility');
        if (visibility) {
            visibility.disabled = false;
            visibility.style.pointerEvents = 'auto';
            visibility.setAttribute('aria-disabled', 'false');
        }
    };

    const updateBallotCountdown = form => {
        if (!form) return;
        const countdown = form.closest('[data-election-systemic-ui]')?.querySelector('#countdownText');
        const progress = form.closest('[data-election-systemic-ui]')?.querySelector('#progressBar');
        const endsAt = Date.parse(form.dataset.electionEndsAt || '');
        const startsAt = Date.parse(form.dataset.electionStartsAt || '');
        if (!countdown || !Number.isFinite(endsAt)) return;

        const tick = () => {
            if (!document.documentElement.contains(form)) return;
            const now = Date.now();
            const remaining = endsAt - now;
            const effectiveStart = Number.isFinite(startsAt) ? startsAt : now;
            const total = Math.max(1, endsAt - effectiveStart);
            if (progress) progress.style.width = `${Math.max(0, Math.min(100, ((now - effectiveStart) / total) * 100))}%`;

            if (remaining <= 0) {
                countdown.textContent = 'این پنجره رأی‌گیری به زمان توقف رسیده است؛ سامانه در حال تثبیت snapshot چرخه است.';
                form.querySelectorAll('input, select, textarea, button[type="submit"]').forEach(el => { el.disabled = true; });
                return;
            }

            const days = Math.floor(remaining / 86400000);
            const hours = Math.floor((remaining % 86400000) / 3600000);
            const minutes = Math.floor((remaining % 3600000) / 60000);
            countdown.textContent = `${days} روز ${hours} ساعت ${minutes} دقیقه تا توقف این پنجره رأی‌گیری`;
            lifecycle.timeout(tick, 30000);
        };
        tick();
    };

    const initializeBallot = () => {
        const form = ballotForm();
        if (!form || form.dataset.lifecycleBound === '1') return;
        form.dataset.lifecycleBound = '1';
        setupMobileRoleTabs(form);
        refreshBallot(form);
        syncCommentVisibility(form);
        updateBallotCountdown(form);
    };

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
        initializeBallot();
        lifecycle.timeout(() => {
            window.GroupElectionModal?.updateElectionSelect2?.();
            window.dispatchEvent(new Event('electionModalOpened'));
        }, 100);
        window.GroupChat?.actions?.closeGroupInfo();
        return true;
    };

    const openAdmin = () => {
        const backdrop = document.getElementById('back');
        const modal = document.getElementById('electionOptionsBox');
        if (modal?.parentElement !== document.body) document.body.appendChild(modal);
        if (backdrop) backdrop.style.display = 'none';
        if (modal) {
            modal.style.display = 'flex';
            modal.scrollTop = 0;
            modal.setAttribute('aria-hidden', 'false');
        }
        document.body.style.overflow = 'hidden';
        store.setState({ electionAdminOpen: Boolean(modal) });
        return Boolean(modal);
    };

    const closeAdmin = () => {
        const backdrop = document.getElementById('back');
        const modal = document.getElementById('electionOptionsBox');
        if (backdrop) backdrop.style.display = 'none';
        if (modal) {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }
        document.body.style.overflow = '';
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

    const resetAdminForm = form => {
        form.reset();
        const container = document.getElementById('el_dynamic-inputs');
        if (container) {
            container.innerHTML = '<input type="text" name="options[]" placeholder="نامزد ۱" class="modal-input mb-2" />';
        }
        optionCount = 1;
        const specialties = document.getElementById('el_specialties_box');
        if (specialties) specialties.style.display = 'none';
    };

    const submitAdmin = async form => {
        if (form.dataset.submitting === 'true') return;
        form.dataset.submitting = 'true';
        store.setState({ electionAdminStatus: 'creating' });
        try {
            const response = await api.request(form.action, {
                method: 'POST',
                body: new FormData(form),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || (data.status && data.status !== 'success')) {
                const errors = data?.errors ? Object.values(data.errors).flat().join('\n') : '';
                throw new Error([data?.message || 'ایجاد انتخابات با خطا مواجه شد.', errors].filter(Boolean).join('\n'));
            }

            const poll = data.poll || {};
            if (poll.id && poll.html) {
                feed.apply([{ ...poll, id: poll.id, content_type: 'poll', action: 'create' }], 'local-election-create');
            }
            resetAdminForm(form);
            closeAdmin();
            notify(data.message || 'انتخابات با موفقیت ایجاد شد.', 'success');
            store.setState({ electionAdminStatus: 'idle' });
        } catch (error) {
            store.setState({ electionAdminStatus: 'error', electionAdminError: error });
            notify(error.message || 'خطا در ایجاد انتخابات', 'error');
        } finally {
            form.dataset.submitting = 'false';
        }
    };

    actions.register('open-election', open);
    actions.register('close-election', close);
    actions.register('open-election-admin', openAdmin);
    actions.register('close-election-admin', closeAdmin);
    actions.register('add-election-candidate', addCandidate);
    actions.register('remove-election-candidate', ({ target }) => Boolean(target.closest('.modal-option-row')?.remove() ?? true));
    actions.register('election-content', ({ event }) => (event.stopPropagation(), false));
    actions.register('open-election-candidates', () => (window.GroupElectionModal?.openCandidatesModal?.(), true));
    actions.register('open-election-guideline', () => (window.GroupElectionModal?.openGuidelineModal?.(), true));
    actions.register('open-election-top-votes', () => (window.GroupElectionModal?.openTopVotesModal?.(), true));

    const type = document.getElementById('poll_election_type');
    if (type) lifecycle.on(type, 'change', () => {
        const specialties = document.getElementById('el_specialties_box');
        if (specialties) specialties.style.display = type.value === '1' ? 'block' : 'none';
    });

    lifecycle.on(document, 'change', event => {
        const input = event.target.closest?.('[data-election-choice]');
        if (!input) return;
        const form = input.closest('[data-systemic-election-ballot]');
        if (!form) return;
        const role = input.dataset.electionChoice;
        const otherRole = role === 'manager' ? 'inspector' : 'manager';
        const limits = ballotLimits(form);

        if (input.checked) {
            const other = form.querySelector(`[data-election-choice="${otherRole}"][data-candidate-id="${input.dataset.candidateId}"]`);
            if (other?.checked) {
                input.checked = false;
                notify('این عضو قبلاً برای نقش دیگر انتخاب شده است. برای تغییر نقش ابتدا انتخاب قبلی او را بردارید.', 'warning');
                refreshBallot(form);
                return;
            }
            if (selectedFor(form, role).length > limits[role]) {
                input.checked = false;
                notify(`حداکثر ${limits[role]} انتخاب برای این نقش مجاز است.`, 'warning');
            }
        }
        refreshBallot(form);
    });

    lifecycle.on(document, 'input', event => {
        const form = event.target.closest?.('[data-systemic-election-ballot]');
        if (!form) return;
        if (event.target.id === 'electionMemberSearch') {
            const query = event.target.value.trim().toLocaleLowerCase('fa');
            form.querySelectorAll('[data-election-member]').forEach(row => {
                row.hidden = query !== '' && !String(row.dataset.memberName || '').includes(query);
            });
        } else if (event.target.id === 'electionComment') {
            syncCommentVisibility(form);
        }
    });

    lifecycle.on(document, 'click', event => {
        const roleTab = event.target.closest?.('[data-election-role-tab]');
        if (roleTab) {
            const form = roleTab.closest('[data-systemic-election-ballot]');
            if (form) {
                event.preventDefault();
                activateRoleTab(form, roleTab.dataset.electionRoleTab);
            }
            return;
        }

        const clearButton = event.target.closest?.('[data-election-clear]');
        if (!clearButton) return;
        const form = clearButton.closest('[data-systemic-election-ballot]');
        if (!form) return;
        ballotChoices(form).forEach(input => { input.checked = false; });
        refreshBallot(form);
    });

    lifecycle.on(document, 'keydown', event => {
        const tab = event.target.closest?.('[data-election-role-tab]');
        if (tab && ['ArrowLeft', 'ArrowRight'].includes(event.key)) {
            const form = tab.closest('[data-systemic-election-ballot]');
            const nextRole = tab.dataset.electionRoleTab === 'manager' ? 'inspector' : 'manager';
            event.preventDefault();
            activateRoleTab(form, nextRole);
            form?.querySelector(`[data-election-role-tab="${nextRole}"]`)?.focus();
            return;
        }
        if (event.key === 'Escape' && store.getState().electionOpen) close();
    });

    lifecycle.on(document, 'submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.id !== 'electionFormModal') return;
        event.preventDefault();
        void submitAdmin(form);
    });

    initializeBallot();
    lifecycle.add(() => {
        close();
        closeAdmin();
    });

    return Object.freeze({ open, close, openAdmin, closeAdmin, submitAdmin, initializeBallot });
}
