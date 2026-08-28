@php
$systemicElection = $election ?? null;
$systemicElectionEligibility = $systemicElection
    ? \App\Models\ElectionEligibilitySnapshot::query()
        ->where('election_id', $systemicElection->id)
        ->where('user_id', auth()->id())
        ->where('voter_eligible', true)
        ->exists()
    : false;
$systemicElectionBlocked = \App\Models\Block::query()
    ->where('user_id', auth()->id())
    ->where('position', 'election')
    ->exists();
$systemicCanParticipate = (bool) ($systemicElection && $systemicElectionEligibility && ! $systemicElectionBlocked);
$systemicStatus = $systemicElection
    ? ($systemicElection->lifecycle_status?->value ?? (string) $systemicElection->lifecycle_status)
    : null;
$systemicPortalUrl = $systemicElection ? route('elections.portal', $group) : null;
$systemicElectionPayload = $systemicElection ? [
    'id' => (int) $systemicElection->id,
    'cycle' => (int) ($systemicElection->cycle_number ?: 1),
    'status' => $systemicStatus,
    'ends_at' => optional($systemicElection->ends_at)->toIso8601String(),
    'portal_url' => $systemicPortalUrl,
    'can_participate' => $systemicCanParticipate,
    'blocked' => $systemicElectionBlocked,
] : null;
@endphp
<script type="module">
function initializeGroupChatPageChrome() {
    const lifecycle = window.GroupChatLifecycle;
    if (!lifecycle || lifecycle.destroyed) return;

    const groupEditForm = document.getElementById('groupEditModalShell');
    const groupEditOriginalParent = groupEditForm?.parentNode || null;
    const groupEditOriginalNextSibling = groupEditForm?.nextSibling || null;
    let groupEditLastFocus = null;

    function ensureGroupEditPortal() {
        if (groupEditForm && groupEditForm.parentNode !== document.body) {
            document.body.appendChild(groupEditForm);
        }
    }

    function setGroupEditVisible(visible) {
        if (!groupEditForm) return;
        if (visible) {
            groupEditLastFocus = document.activeElement;
            ensureGroupEditPortal();
        }
        groupEditForm.style.cssText = visible
            ? 'display: flex; position: fixed; inset: 0; z-index: 9999; align-items: center; justify-content: center; padding: 1.5rem; direction: rtl;'
            : 'display: none;';
        groupEditForm.setAttribute('aria-hidden', visible ? 'false' : 'true');
        document.body.classList.toggle('group-edit-modal-open', visible);
        if (visible) {
            window.requestAnimationFrame(() => {
                groupEditForm.querySelector('.group-edit-modal__close, textarea, input, button')?.focus?.();
            });
        } else {
            const target = groupEditLastFocus;
            groupEditLastFocus = null;
            if (target?.isConnected) window.requestAnimationFrame(() => target.focus?.());
        }
    }

    function setGroupHeroExpanded(expanded) {
        const hero = document.querySelector('[data-group-hero]');
        const trigger = hero?.querySelector('[data-group-chat-action="toggle-group-hero"]');
        const content = hero?.querySelector('[data-group-hero-content]');
        const chevron = hero?.querySelector('[data-group-hero-chevron]');
        if (!hero || !trigger || !content) return;

        trigger.setAttribute('aria-expanded', String(expanded));
        content.hidden = !expanded;
        content.classList.toggle('is-expanded', expanded);
        chevron?.classList.toggle('rotate-180', expanded);
    }

    function projectSystemicElectionSurface() {
        const election = @json($systemicElectionPayload);

        if (!election) return;

        document.querySelectorAll('[data-group-hero] button').forEach(button => {
            if (!button.querySelector('.fa-vote-yea')) return;

            button.classList.remove('bg-slate-100', 'text-slate-500', 'cursor-not-allowed');
            button.innerHTML = '<i class="fas fa-vote-yea"></i>' + (election.can_participate ? ' شرکت در انتخابات' : ' انتخابات فعال');

            if (election.can_participate) {
                button.disabled = false;
                button.dataset.chatPageAction = 'open-election';
                button.classList.add('bg-indigo-500', 'text-white', 'shadow-sm', 'hover:bg-indigo-600', 'transition');
                button.removeAttribute('title');
            } else {
                button.disabled = true;
                delete button.dataset.chatPageAction;
                button.classList.add('bg-slate-100', 'text-slate-500', 'cursor-not-allowed');
                button.title = election.blocked
                    ? 'دسترسی شما به رأی‌دادن در این انتخابات مسدود شده است.'
                    : 'برای این چرخه در snapshot واجد شرایط رأی‌دادن نیستید.';
            }
        });

        const electionTab = document.getElementById('election');
        if (!electionTab) return;

        const card = document.createElement('div');
        card.className = 'rounded-2xl border border-indigo-100 bg-indigo-50/60 p-4 space-y-3';

        const heading = document.createElement('div');
        heading.className = 'flex items-center justify-between gap-3';
        const title = document.createElement('strong');
        title.className = 'text-slate-900';
        title.textContent = `انتخابات سیستمی · چرخه ${election.cycle}`;
        const badge = document.createElement('span');
        badge.className = 'inline-flex px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold';
        badge.textContent = election.status === 'open' ? 'رأی‌گیری باز' : election.status;
        heading.append(title, badge);

        const meta = document.createElement('p');
        meta.className = 'text-sm text-slate-600 leading-7';
        meta.textContent = election.ends_at
            ? `چرخه فعال است. پایان پنجره رأی‌گیری: ${new Date(election.ends_at).toLocaleString('fa-IR')}`
            : 'چرخه انتخابات سیستمی فعال است.';

        const actions = document.createElement('div');
        actions.className = 'flex flex-wrap gap-2';
        if (election.can_participate) {
            const vote = document.createElement('button');
            vote.type = 'button';
            vote.dataset.chatPageAction = 'open-election';
            vote.className = 'inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold';
            vote.innerHTML = '<i class="fas fa-vote-yea"></i> شرکت در انتخابات';
            actions.appendChild(vote);
        }

        const portal = document.createElement('a');
        portal.href = election.portal_url;
        portal.className = 'inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-indigo-200 text-indigo-700 text-sm font-semibold bg-white';
        portal.innerHTML = '<i class="fas fa-circle-info"></i> جزئیات و تاریخچه انتخابات';
        actions.appendChild(portal);

        card.append(heading, meta, actions);
        electionTab.replaceChildren(card);
    }

    window.GroupChatPageChrome = Object.freeze({
        openGroupEdit() {
            setGroupEditVisible(true);
        },
        cancelGroupEdit() {
            setGroupEditVisible(false);
        },
        showEditPollBox(pollId) {
            const editBox = document.getElementById('edit-poll-box-' + Number(pollId));
            if (!editBox) return;
            editBox.style.display = editBox.style.display === 'none' || editBox.style.display === ''
                ? 'block'
                : 'none';
        },
        toggleGroupHero() {
            const trigger = document.querySelector('[data-group-chat-action="toggle-group-hero"]');
            setGroupHeroExpanded(trigger?.getAttribute('aria-expanded') !== 'true');
        }
    });

    if (groupEditForm) {
        lifecycle.on(groupEditForm, 'click', event => {
            if (event.target === groupEditForm) {
                setGroupEditVisible(false);
            }
        });
        lifecycle.on(document, 'keydown', event => {
            if (event.key === 'Escape' && groupEditForm.getAttribute('aria-hidden') === 'false') {
                event.preventDefault();
                event.stopPropagation();
                setGroupEditVisible(false);
            }
        });
    }

    projectSystemicElectionSurface();

    const pinnedMessages = document.querySelector('.pinned-messages');
    if (pinnedMessages) pinnedMessages.scrollTop = pinnedMessages.scrollHeight;

    document.querySelectorAll('[data-group-chat-flash]').forEach(function(flash) {
        lifecycle.timeout(function() {
            flash.classList.add('group-chat-flash--leaving');
            lifecycle.timeout(function() { flash.remove(); }, 260);
        }, 4200);
    });

    @if (session()->has('success'))
    lifecycle.on(window, 'load', function() {
        window.GroupChatFeedback?.toast(@json(session()->get('success')), { type: 'success' });
    }, { once: true });
    @endif

    lifecycle.add(function() {
        setGroupEditVisible(false);
        if (groupEditOriginalParent && groupEditForm?.parentNode === document.body) {
            if (groupEditOriginalNextSibling?.parentNode === groupEditOriginalParent) {
                groupEditOriginalParent.insertBefore(groupEditForm, groupEditOriginalNextSibling);
            } else {
                groupEditOriginalParent.appendChild(groupEditForm);
            }
        }
        setGroupHeroExpanded(false);
        document.querySelectorAll('[id^="edit-poll-box-"]').forEach(editBox => {
            editBox.style.display = 'none';
        });
        delete window.GroupChatPageChrome;
    });
}

initializeGroupChatPageChrome();
</script>