@php
    $systemicElectionCount = $group->elections()->count();
    $internalElectionCount = $group->polls()->where('main_type', 0)->count();
    $combinedElectionCount = $systemicElectionCount + $internalElectionCount;
    $latestSystemicElection = $group->elections()->latest('id')->first();
    $systemicState = $latestSystemicElection?->lifecycle_status?->value ?? $latestSystemicElection?->lifecycle_status;
    $systemicStateLabels = [
        'scheduled' => 'زمان‌بندی‌شده',
        'open' => 'در حال رأی‌گیری',
        'closed' => 'پنجره رأی‌گیری بسته',
        'tallying' => 'در حال شمارش',
        'awaiting_acceptance' => 'در انتظار پذیرش مسئولیت',
        'appointing' => 'در حال نصب مسئولان',
        'filled' => 'مسئولیت‌ها تکمیل شده',
        'exhausted' => 'فهرست جایگزین پایان یافته',
        'cancelled' => 'لغوشده',
    ];
@endphp

<div hidden
     data-election-surface-bridge
     data-election-surface-stats-url="{{ route('groups.election-surface.stats', $group) }}"
     data-systemic-election-count="{{ $systemicElectionCount }}"
     data-internal-election-count="{{ $internalElectionCount }}"
     data-combined-election-count="{{ $combinedElectionCount }}">
</div>

<template id="groupElectionKindTemplate">
    <div class="election-kind-shell" data-election-kind-shell>
        <div class="election-kind-tabs" data-election-kind-tabs role="tablist" aria-label="انواع انتخابات گروه">
            <button type="button" class="election-kind-tab is-active" data-election-kind-tab="systemic" role="tab" aria-selected="true">
                <i class="fas fa-landmark" aria-hidden="true"></i>
                <span>انتخابات سیستمی</span>
                <b>{{ $systemicElectionCount }}</b>
            </button>
            <button type="button" class="election-kind-tab" data-election-kind-tab="internal" role="tab" aria-selected="false" tabindex="-1">
                <i class="fas fa-box-ballot" aria-hidden="true"></i>
                <span>انتخابات داخلی گروه</span>
                <b>{{ $internalElectionCount }}</b>
            </button>
        </div>

        <section class="election-kind-pane is-active" data-election-kind-pane="systemic" role="tabpanel">
            <article class="systemic-election-summary {{ $electionAvailable ? 'is-live' : '' }}">
                <div class="systemic-election-summary__icon" aria-hidden="true"><i class="fas fa-check-to-slot"></i></div>
                <div class="systemic-election-summary__body">
                    <div class="systemic-election-summary__heading">
                        <div>
                            <span class="systemic-election-summary__eyebrow">انتخابات اصلی حکمرانی گروه</span>
                            <h4>انتخابات سیستمی</h4>
                        </div>
                        @if($electionAvailable)
                            <span class="systemic-election-summary__status is-live"><i class="fas fa-circle"></i> در حال برگزاری</span>
                        @elseif($latestSystemicElection)
                            <span class="systemic-election-summary__status">{{ $systemicStateLabels[$systemicState] ?? 'چرخه ثبت‌شده' }}</span>
                        @else
                            <span class="systemic-election-summary__status">هنوز آغاز نشده</span>
                        @endif
                    </div>

                    @if($electionAvailable)
                        <p>چرخه جاری انتخابات سیستمی فعال است. اعضای واجد شرایط می‌توانند مستقیماً برگه رأی خود را مشاهده و ویرایش کنند.</p>
                        <div class="systemic-election-summary__actions">
                            @if($canParticipateElection ?? false)
                                <button type="button" class="systemic-election-primary" data-chat-page-action="open-election">
                                    <i class="fas fa-check-to-slot" aria-hidden="true"></i><span>شرکت در انتخابات</span>
                                </button>
                            @else
                                <span class="systemic-election-restricted"><i class="fas fa-circle-info"></i> امکان ثبت رأی برای حساب شما فعال نیست.</span>
                            @endif
                            <a class="systemic-election-secondary" href="{{ route('elections.portal', $group) }}">
                                <i class="fas fa-chart-column" aria-hidden="true"></i><span>مشاهده وضعیت انتخابات</span>
                            </a>
                        </div>
                    @elseif($latestSystemicElection)
                        <p>آخرین چرخه سیستمی این گروه ثبت شده است. برای مشاهده وضعیت، نتایج امن و بازبینی چرخه وارد پرتال انتخابات شوید.</p>
                        <a class="systemic-election-secondary" href="{{ route('elections.portal', $group) }}">
                            <i class="fas fa-chart-column" aria-hidden="true"></i><span>مشاهده پرتال انتخابات</span>
                        </a>
                    @else
                        <p>انتخابات سیستمی پس از تحقق حدنصاب و سیاست انتخاباتی گروه به‌صورت خودکار فعال می‌شود.</p>
                    @endif
                </div>
            </article>
        </section>

        <section class="election-kind-pane" data-election-kind-pane="internal" role="tabpanel" hidden>
            <div class="internal-election-intro">
                <div><i class="fas fa-box-ballot" aria-hidden="true"></i></div>
                <p><strong>انتخابات داخلی گروه</strong><span>رأی‌گیری‌هایی که مدیران گروه برای موضوع‌ها و مسئولیت‌های داخلی ایجاد می‌کنند در این بخش باقی می‌مانند.</span></p>
            </div>
            <div data-internal-election-host></div>
        </section>
    </div>
</template>

<style>
    #groupInfoPanel .election-kind-shell { display:grid; gap:.85rem; }
    #groupInfoPanel .election-kind-tabs { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.45rem; padding:.3rem; border:1px solid #dbe7e4; border-radius:14px; background:#f8fafc; }
    #groupInfoPanel .election-kind-tab { min-width:0; min-height:42px; border:0; border-radius:11px; background:transparent; color:#64748b; display:flex; align-items:center; justify-content:center; gap:.45rem; font-size:.73rem; font-weight:850; cursor:pointer; transition:background-color .16s ease,color .16s ease,box-shadow .16s ease; }
    #groupInfoPanel .election-kind-tab b { min-width:22px; height:22px; padding-inline:.35rem; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; background:#e2e8f0; color:#475569; font-size:.66rem; }
    #groupInfoPanel .election-kind-tab.is-active { background:#fff; color:#047857; box-shadow:0 6px 18px -15px rgba(15,23,42,.75); }
    #groupInfoPanel .election-kind-tab.is-active b { background:#d1fae5; color:#047857; }
    #groupInfoPanel .election-kind-pane[hidden] { display:none !important; }
    #groupInfoPanel .systemic-election-summary { display:grid; grid-template-columns:auto minmax(0,1fr); gap:.8rem; padding:.9rem; border:1px solid #dbe7e4; border-radius:16px; background:linear-gradient(145deg,#fff,#f8fafc); }
    #groupInfoPanel .systemic-election-summary.is-live { border-color:#a7f3d0; background:linear-gradient(145deg,#ecfdf5,#fff 72%); box-shadow:0 18px 40px -34px rgba(5,150,105,.65); }
    #groupInfoPanel .systemic-election-summary__icon { width:42px; height:42px; border-radius:13px; display:grid; place-items:center; background:#d1fae5; color:#047857; font-size:1rem; }
    #groupInfoPanel .systemic-election-summary__body { min-width:0; }
    #groupInfoPanel .systemic-election-summary__heading { display:flex; align-items:flex-start; justify-content:space-between; gap:.75rem; }
    #groupInfoPanel .systemic-election-summary__eyebrow { display:block; margin-bottom:.1rem; color:#059669; font-size:.64rem; font-weight:800; }
    #groupInfoPanel .systemic-election-summary h4 { margin:0; color:#0f4c3a; font-size:.86rem; font-weight:900; }
    #groupInfoPanel .systemic-election-summary p { margin:.4rem 0 0; color:#64748b; font-size:.74rem; line-height:1.8; }
    #groupInfoPanel .systemic-election-summary__status { flex:0 0 auto; display:inline-flex; align-items:center; gap:.32rem; padding:.3rem .55rem; border-radius:999px; background:#f1f5f9; color:#64748b; font-size:.64rem; font-weight:800; }
    #groupInfoPanel .systemic-election-summary__status.is-live { background:#d1fae5; color:#047857; }
    #groupInfoPanel .systemic-election-summary__status.is-live i { font-size:.45rem; animation:election-live-pulse 1.8s ease-in-out infinite; }
    #groupInfoPanel .systemic-election-summary__actions { display:flex; flex-wrap:wrap; align-items:center; gap:.5rem; margin-top:.75rem; }
    #groupInfoPanel .systemic-election-primary,#groupInfoPanel .systemic-election-secondary { min-height:38px; display:inline-flex; align-items:center; justify-content:center; gap:.4rem; padding:.5rem .7rem; border-radius:11px; font-size:.72rem; font-weight:850; text-decoration:none; }
    #groupInfoPanel .systemic-election-primary { border:1px solid #059669; background:#059669; color:#fff; box-shadow:0 12px 24px -18px rgba(5,150,105,.9); }
    #groupInfoPanel .systemic-election-secondary { border:1px solid #d1fae5; background:#fff; color:#047857; }
    #groupInfoPanel .systemic-election-restricted { display:inline-flex; align-items:center; gap:.35rem; color:#92400e; background:#fffbeb; border:1px solid #fde68a; border-radius:11px; padding:.5rem .65rem; font-size:.7rem; }
    #groupInfoPanel .internal-election-intro { display:flex; gap:.6rem; align-items:flex-start; padding:.7rem .75rem; margin-bottom:.7rem; border-radius:13px; background:#f8fafc; color:#475569; }
    #groupInfoPanel .internal-election-intro > div { width:32px; height:32px; border-radius:10px; display:grid; place-items:center; background:#e0f2fe; color:#0369a1; flex:0 0 32px; }
    #groupInfoPanel .internal-election-intro p { margin:0; display:grid; gap:.1rem; font-size:.7rem; line-height:1.65; }
    #groupInfoPanel .internal-election-intro strong { color:#0f172a; font-size:.74rem; }
    #groupInfoPanel [data-internal-election-host] .control-center-action-grid { margin-bottom:.7rem; }
    @keyframes election-live-pulse { 0%,100%{opacity:.45;transform:scale(.85)} 50%{opacity:1;transform:scale(1)} }
    @media (max-width:640px) {
        #groupInfoPanel .election-kind-tab { font-size:.67rem; padding-inline:.35rem; }
        #groupInfoPanel .systemic-election-summary { grid-template-columns:1fr; }
        #groupInfoPanel .systemic-election-summary__icon { display:none; }
        #groupInfoPanel .systemic-election-summary__heading { align-items:center; }
        #groupInfoPanel .systemic-election-summary__actions { display:grid; grid-template-columns:1fr; }
        #groupInfoPanel .systemic-election-primary,#groupInfoPanel .systemic-election-secondary { width:100%; }
    }
    @media (prefers-reduced-motion:reduce) { #groupInfoPanel .systemic-election-summary__status.is-live i { animation:none; } }
</style>

<script type="module">
    const lifecycle = window.GroupChatLifecycle;
    if (!lifecycle) throw new Error('GroupChatLifecycle is required by election surface bridge');

    const bridge = document.querySelector('[data-election-surface-bridge]');
    const template = document.getElementById('groupElectionKindTemplate');
    let latestElectionStats = {
        total: Number(bridge?.dataset.combinedElectionCount || 0),
        systemic: { total: Number(bridge?.dataset.systemicElectionCount || 0) },
        internal: { total: Number(bridge?.dataset.internalElectionCount || 0) },
    };

    const updateMetric = stats => {
        const metrics = Array.from(document.querySelectorAll('#groupInfoPanel .panel-metrics__item'));
        const electionMetric = metrics.find(item => item.querySelector('.panel-metrics__label')?.textContent.trim() === 'انتخابات');
        const value = electionMetric?.querySelector('.panel-metrics__value');
        if (value) value.textContent = String(stats.total ?? 0);

        document.querySelectorAll('[data-election-kind-tab]').forEach(button => {
            const badge = button.querySelector('b');
            if (!badge) return;
            const kind = button.dataset.electionKindTab;
            badge.textContent = String(stats[kind]?.total ?? 0);
        });
    };

    const patchOperationalStats = stats => {
        const cards = Array.from(document.querySelectorAll('#stats-content .control-center-stat'));
        const electionCard = cards.find(card => card.querySelector('span')?.textContent.trim() === 'انتخابات');
        const value = electionCard?.querySelector('strong');
        if (value) value.textContent = String(stats.total ?? 0);
    };

    const activateKind = (shell, kind, focus = false) => {
        const tabs = Array.from(shell.querySelectorAll('[data-election-kind-tab]'));
        const panes = Array.from(shell.querySelectorAll('[data-election-kind-pane]'));
        tabs.forEach(tab => {
            const active = tab.dataset.electionKindTab === kind;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', String(active));
            tab.tabIndex = active ? 0 : -1;
            if (active && focus) tab.focus();
        });
        panes.forEach(pane => {
            const active = pane.dataset.electionKindPane === kind;
            pane.classList.toggle('is-active', active);
            pane.hidden = !active;
        });
    };

    const installElectionSurface = (attempt = 0) => {
        const electionHost = document.querySelector('#groupInfoPanel #governance [data-control-center-subpane="elections"]');
        if (!electionHost || !template) {
            if (attempt < 6) lifecycle.timeout(() => installElectionSurface(attempt + 1), 40);
            return;
        }
        if (electionHost.querySelector('[data-election-kind-shell]')) return;

        const legacyElectionSection = Array.from(electionHost.querySelectorAll('.control-center-subsection'))
            .find(section => section.querySelector('.control-center-subsection__title h4')?.textContent.includes('انتخابات گروه'));
        const legacyList = legacyElectionSection?.querySelector('.control-center-list');
        const internalAdminAction = electionHost.querySelector('[data-chat-page-action="open-election-admin"]');
        const legacySystemicAction = electionHost.querySelector('[data-chat-page-action="open-election"]')?.closest('.control-center-tool-card');

        const fragment = template.content.cloneNode(true);
        const shell = fragment.querySelector('[data-election-kind-shell]');
        const internalHost = fragment.querySelector('[data-internal-election-host]');

        if (internalAdminAction && internalHost) {
            const actionGrid = document.createElement('div');
            actionGrid.className = 'control-center-action-grid';
            actionGrid.appendChild(internalAdminAction);
            internalHost.appendChild(actionGrid);
        }
        if (legacyList && internalHost) internalHost.appendChild(legacyList);
        if (internalHost && !legacyList) {
            const empty = document.createElement('div');
            empty.className = 'empty-state';
            empty.textContent = 'انتخابات داخلی برای نمایش وجود ندارد.';
            internalHost.appendChild(empty);
        }

        legacySystemicAction?.remove();
        legacyElectionSection?.remove();
        electionHost.prepend(fragment);

        shell.querySelectorAll('[data-election-kind-tab]').forEach(tab => {
            lifecycle.on(tab, 'click', () => activateKind(shell, tab.dataset.electionKindTab));
            lifecycle.on(tab, 'keydown', event => {
                if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
                event.preventDefault();
                const next = tab.dataset.electionKindTab === 'systemic' ? 'internal' : 'systemic';
                activateKind(shell, next, true);
            });
        });
        updateMetric(latestElectionStats);
    };

    const refreshElectionStats = async () => {
        const url = bridge?.dataset.electionSurfaceStatsUrl;
        if (!url) return;
        try {
            const response = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await response.json();
            if (!response.ok || data.status !== 'success' || !data.elections) return;
            latestElectionStats = data.elections;
            updateMetric(latestElectionStats);
            patchOperationalStats(latestElectionStats);
        } catch (_) {
            // The legacy operational stats request still renders the rest of the cards.
        }
    };

    const statsContainer = document.getElementById('stats-content');
    if (statsContainer) {
        const observer = new MutationObserver(() => patchOperationalStats(latestElectionStats));
        observer.observe(statsContainer, { childList: true, subtree: true });
        lifecycle.add(() => observer.disconnect());
    }
    const statsButton = document.getElementById('loadGroupStatsButton');
    if (statsButton) lifecycle.on(statsButton, 'click', refreshElectionStats);

    installElectionSurface();
    updateMetric(latestElectionStats);
</script>
