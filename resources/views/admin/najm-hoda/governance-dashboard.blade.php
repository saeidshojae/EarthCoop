@extends('layouts.admin')

@section('title', 'Governance Dashboard - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'Najm Hoda Governance Dashboard')
@section('page-description', 'Governance KPIs, autonomy controls, and oversight explainability')

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-800">Najm Hoda Governance Dashboard</h2>
        <div class="flex items-center gap-2">
            <select id="windowHours" class="border rounded px-2 py-1">
                <option value="1">1h</option>
                <option value="6">6h</option>
                <option value="24" selected>24h</option>
                <option value="72">72h</option>
            </select>
            <button id="governanceRefresh" class="px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700">Refresh</button>
        </div>
    </div>

    <div id="governanceSummary" class="grid grid-cols-1 md:grid-cols-4 gap-4"></div>

    <div class="bg-white border rounded-lg p-4">
        <h3 class="font-semibold mb-3">KPI / SLO Status</h3>
        <div class="overflow-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-gray-100 text-right">
                        <th class="p-2">Metric</th>
                        <th class="p-2">Value</th>
                        <th class="p-2">Target</th>
                        <th class="p-2">Status</th>
                    </tr>
                </thead>
                <tbody id="governanceTableBody"></tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-white border rounded-lg p-4 lg:col-span-2">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold">Autonomy Oversight Console</h3>
                <button id="oversightRefresh" class="px-3 py-1.5 bg-indigo-600 text-white rounded hover:bg-indigo-700">Refresh Oversight</button>
            </div>
            <div id="oversightStatus" class="hidden mb-3 text-sm px-3 py-2 rounded"></div>
            <div id="oversightSummary" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4"></div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-2 mb-3">
                <input id="approvalSearch" type="text" class="border rounded px-2 py-1" placeholder="Search action / id">
                <select id="approvalRiskFilter" class="border rounded px-2 py-1">
                    <option value="">All risk levels</option>
                    <option value="low">low</option>
                    <option value="medium">medium</option>
                    <option value="high">high</option>
                    <option value="critical">critical</option>
                    <option value="unknown">unknown</option>
                </select>
                <select id="approvalSlaFilter" class="border rounded px-2 py-1">
                    <option value="">All SLA states</option>
                    <option value="within_sla">within_sla</option>
                    <option value="overdue">overdue</option>
                </select>
                <select id="approvalPageSize" class="border rounded px-2 py-1">
                    <option value="5">5 / page</option>
                    <option value="10" selected>10 / page</option>
                    <option value="20">20 / page</option>
                    <option value="50">50 / page</option>
                </select>
            </div>
            <div class="overflow-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-gray-100 text-right">
                            <th class="p-2">Approval ID</th>
                            <th class="p-2">Action</th>
                            <th class="p-2">Risk</th>
                            <th class="p-2">SLA</th>
                            <th class="p-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="approvalTableBody"></tbody>
                </table>
            </div>
            <div class="flex items-center justify-between mt-3">
                <div id="approvalPaginationMeta" class="text-xs text-gray-500">-</div>
                <div class="flex items-center gap-2">
                    <button id="approvalPrevPage" class="px-2 py-1 border rounded text-sm">Prev</button>
                    <span id="approvalPageIndicator" class="text-sm text-gray-700">1 / 1</span>
                    <button id="approvalNextPage" class="px-2 py-1 border rounded text-sm">Next</button>
                </div>
            </div>
        </div>

        <div class="bg-white border rounded-lg p-4">
            <h3 class="font-semibold mb-3">Override Controls</h3>
            <div id="controlStateBox" class="text-sm text-gray-700 space-y-2 mb-4"></div>
            <div class="grid grid-cols-1 gap-2">
                <button data-control-action="pause" class="control-action px-3 py-2 bg-amber-600 text-white rounded hover:bg-amber-700">Pause 30m</button>
                <button data-control-action="resume" class="control-action px-3 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700">Resume</button>
                <button data-control-action="activate_kill_switch" class="control-action px-3 py-2 bg-red-600 text-white rounded hover:bg-red-700">Activate Kill Switch (15m)</button>
                <button data-control-action="deactivate_kill_switch" class="control-action px-3 py-2 bg-green-700 text-white rounded hover:bg-green-800">Deactivate Kill Switch</button>
                <button data-control-action="set_override" class="control-action px-3 py-2 bg-slate-700 text-white rounded hover:bg-slate-800">Force Propose Mode</button>
                <button data-control-action="clear_override" class="control-action px-3 py-2 bg-gray-700 text-white rounded hover:bg-gray-800">Clear Override</button>
            </div>
        </div>
    </div>

    <div class="bg-white border rounded-lg p-4">
        <h3 class="font-semibold mb-3">Explainability Recommendations</h3>
        <div id="oversightRecommendations" class="space-y-2 text-sm"></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const summaryEl = document.getElementById('governanceSummary');
    const tableBody = document.getElementById('governanceTableBody');
    const refreshBtn = document.getElementById('governanceRefresh');
    const windowEl = document.getElementById('windowHours');

    const oversightRefreshBtn = document.getElementById('oversightRefresh');
    const oversightSummaryEl = document.getElementById('oversightSummary');
    const oversightStatusEl = document.getElementById('oversightStatus');
    const approvalTableBody = document.getElementById('approvalTableBody');
    const oversightRecommendationsEl = document.getElementById('oversightRecommendations');
    const controlStateBox = document.getElementById('controlStateBox');
    const approvalSearchEl = document.getElementById('approvalSearch');
    const approvalRiskFilterEl = document.getElementById('approvalRiskFilter');
    const approvalSlaFilterEl = document.getElementById('approvalSlaFilter');
    const approvalPageSizeEl = document.getElementById('approvalPageSize');
    const approvalPrevPageBtn = document.getElementById('approvalPrevPage');
    const approvalNextPageBtn = document.getElementById('approvalNextPage');
    const approvalPageIndicatorEl = document.getElementById('approvalPageIndicator');
    const approvalPaginationMetaEl = document.getElementById('approvalPaginationMeta');

    const baselineUrl = @json(route('admin.najm-hoda.autonomy.governance.baseline'));
    const snapshotUrl = @json(route('admin.najm-hoda.autonomy.governance.snapshot'));
    const costsUrl = @json(route('admin.najm-hoda.autonomy.costs.status'));
    const oversightUrl = @json(route('admin.najm-hoda.autonomy.oversight.console'));
    const controlsUrl = @json(route('admin.najm-hoda.autonomy.controls'));
    const controlsUpdateUrl = @json(route('admin.najm-hoda.autonomy.controls.update'));
    const approvalsDecisionUrlPattern = @json(route('admin.najm-hoda.autonomy.approvals.decision', ['approvalId' => '__APPROVAL_ID__']));
    const approvalsVetoUrlPattern = @json(route('admin.najm-hoda.autonomy.approvals.veto', ['approvalId' => '__APPROVAL_ID__']));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let oversightBusy = false;
    let approvalsCache = [];
    let approvalPagination = null;
    let approvalPage = 1;
    let oversightPolicyHints = {};

    function setOversightStatus(type, message) {
        if (!message) {
            oversightStatusEl.className = 'hidden mb-3 text-sm px-3 py-2 rounded';
            oversightStatusEl.textContent = '';
            return;
        }

        const palette = {
            loading: 'bg-blue-50 text-blue-700 border border-blue-200',
            success: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
            error: 'bg-red-50 text-red-700 border border-red-200',
        };
        oversightStatusEl.className = `mb-3 text-sm px-3 py-2 rounded ${palette[type] || palette.loading}`;
        oversightStatusEl.textContent = message;
    }

    function setOversightBusy(loading) {
        oversightBusy = loading;
        oversightRefreshBtn.disabled = loading;
        oversightRefreshBtn.classList.toggle('opacity-60', loading);
        document.querySelectorAll('.control-action, .approval-action').forEach((btn) => {
            btn.disabled = loading;
            btn.classList.toggle('opacity-60', loading);
        });
        if (loading) setOversightStatus('loading', 'Loading oversight data...');
    }

    function canAbility(key) {
        return Boolean(oversightPolicyHints?.ability?.[key]);
    }

    function applyPolicyHintsToControls() {
        const canControls = canAbility('controls_update');
        const canKillSwitch = canAbility('controls_kill_switch');
        const canOverride = canAbility('controls_override');

        document.querySelectorAll('.control-action').forEach((btn) => {
            const action = btn.getAttribute('data-control-action');
            let allowed = canControls;
            if (action === 'activate_kill_switch' || action === 'deactivate_kill_switch') {
                allowed = canKillSwitch;
            } else if (action === 'set_override' || action === 'clear_override') {
                allowed = canOverride;
            }
            btn.disabled = oversightBusy || !allowed;
            btn.classList.toggle('opacity-60', btn.disabled);
            btn.title = allowed ? '' : 'Insufficient permission for this action';
        });
    }

    function card(title, value, hint = '') {
        return `
            <div class="bg-white border rounded-lg p-4">
                <div class="text-xs text-gray-500 mb-1">${title}</div>
                <div class="text-xl font-bold text-gray-800">${value}</div>
                <div class="text-xs text-gray-400 mt-1">${hint}</div>
            </div>
        `;
    }

    function fmt(v) {
        if (v === null || v === undefined) return '-';
        if (typeof v === 'number') return v.toFixed(4).replace(/\.?0+$/, '');
        return String(v);
    }

    function statusBadge(status) {
        const map = {
            ok: 'bg-emerald-100 text-emerald-800',
            warning: 'bg-amber-100 text-amber-800',
            breach: 'bg-red-100 text-red-800',
            no_data: 'bg-gray-100 text-gray-700',
        };
        const cls = map[status] || map.no_data;
        return `<span class="px-2 py-1 rounded text-xs ${cls}">${status || 'no_data'}</span>`;
    }

    function postJson(url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(payload || {}),
        }).then(async (res) => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data?.success) {
                const msg = data?.message || data?.error || data?.reason || `HTTP ${res.status}`;
                throw new Error(msg);
            }
            return data;
        });
    }

    function renderSummary(snapshot, costs) {
        const metrics = snapshot?.metrics || {};
        const evals = snapshot?.evaluation || {};
        const allStatuses = Object.values(evals).map((x) => x?.status || 'no_data');
        const breachCount = allStatuses.filter((x) => x === 'breach').length;
        const warningCount = allStatuses.filter((x) => x === 'warning').length;
        const dailyCost = fmt(costs?.daily_total ?? 0);
        const dailyBudget = fmt(costs?.daily_budget ?? 0);

        summaryEl.innerHTML = [
            card('Event Count', snapshot?.event_count ?? 0),
            card('Breach', breachCount),
            card('Warning', warningCount),
            card('Auto Success', fmt(metrics.auto_action_success_rate ?? 0)),
            card('Daily AI Cost', dailyCost, `Budget: ${dailyBudget}`),
        ].join('');
    }

    function renderTable(baseline, snapshot) {
        const metrics = snapshot?.metrics || {};
        const evals = snapshot?.evaluation || {};
        const keys = Object.keys(baseline || {});
        if (!keys.length) {
            tableBody.innerHTML = `<tr><td class="p-2 text-gray-500" colspan="4">No data available.</td></tr>`;
            return;
        }

        tableBody.innerHTML = keys.map((key) => {
            const b = baseline[key] || {};
            const e = evals[key] || {};
            const value = metrics[key];
            const targetMin = b.target_min !== undefined ? `>= ${fmt(b.target_min)}` : '';
            const targetMax = b.target_max !== undefined ? `<= ${fmt(b.target_max)}` : '';
            const target = [targetMin, targetMax].filter(Boolean).join(' / ') || '-';
            return `
                <tr class="border-b">
                    <td class="p-2">${b.label || key}</td>
                    <td class="p-2">${fmt(value)}</td>
                    <td class="p-2">${target}</td>
                    <td class="p-2">${statusBadge(e.status)}</td>
                </tr>
            `;
        }).join('');
    }

    function oversightCard(title, value, hint = '') {
        return `
            <div class="border rounded p-3 bg-gray-50">
                <div class="text-xs text-gray-500 mb-1">${title}</div>
                <div class="text-lg font-semibold text-gray-800">${value}</div>
                <div class="text-xs text-gray-400 mt-1">${hint}</div>
            </div>
        `;
    }

    function renderApprovalRows() {
        const rows = Array.isArray(approvalsCache) ? approvalsCache : [];
        const pager = approvalPagination || {
            current_page: 1,
            last_page: 1,
            total: rows.length,
            from: rows.length ? 1 : 0,
            to: rows.length,
        };
        approvalPage = Number(pager.current_page || 1);
        const pageCount = Number(pager.last_page || 1);

        approvalPaginationMetaEl.textContent = `${pager.total || 0} approvals matched (${pager.from || 0}-${pager.to || 0})`;
        approvalPageIndicatorEl.textContent = `${approvalPage} / ${pageCount}`;
        approvalPrevPageBtn.disabled = approvalPage <= 1 || oversightBusy;
        approvalNextPageBtn.disabled = approvalPage >= pageCount || oversightBusy;

        if (!rows.length) {
            approvalTableBody.innerHTML = `<tr><td class="p-2 text-gray-500" colspan="5">No approvals for current filter.</td></tr>`;
            return;
        }

        const canApprove = canAbility('approval_approve');
        const canReject = canAbility('approval_reject');
        const canVeto = canAbility('approval_veto');

        approvalTableBody.innerHTML = rows.map((row) => {
            const id = row?.id || '';
            const slaStatus = row?.sla_status || 'within_sla';
            const badge = slaStatus === 'overdue'
                ? '<span class="px-2 py-1 rounded text-xs bg-red-100 text-red-800">overdue</span>'
                : '<span class="px-2 py-1 rounded text-xs bg-emerald-100 text-emerald-800">within_sla</span>';
            return `
                <tr class="border-b">
                    <td class="p-2 font-mono text-xs">${id}</td>
                    <td class="p-2">${row?.action || '-'}</td>
                    <td class="p-2">${row?.risk || '-'}</td>
                    <td class="p-2">${badge}</td>
                    <td class="p-2">
                        <div class="flex flex-wrap gap-1">
                            <button class="approval-action px-2 py-1 bg-emerald-600 text-white rounded text-xs ${canApprove ? '' : 'opacity-60'}" ${canApprove ? '' : 'disabled title="Insufficient permission"'} data-id="${id}" data-mode="approve">Approve</button>
                            <button class="approval-action px-2 py-1 bg-amber-600 text-white rounded text-xs ${canReject ? '' : 'opacity-60'}" ${canReject ? '' : 'disabled title="Insufficient permission"'} data-id="${id}" data-mode="reject">Reject</button>
                            <button class="approval-action px-2 py-1 bg-red-600 text-white rounded text-xs ${canVeto ? '' : 'opacity-60'}" ${canVeto ? '' : 'disabled title="Insufficient permission"'} data-id="${id}" data-mode="veto">Veto</button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function renderOversight(snapshot) {
        const approvals = snapshot?.approvals || {};
        const delegation = snapshot?.delegation || {};
        const audit = snapshot?.audit || {};
        const events = snapshot?.events || {};
        const controls = snapshot?.controls || {};
        oversightPolicyHints = snapshot?.policy_hints || {};
        const state = controls?.state || {};
        const kill = controls?.kill_switch || {};
        const override = controls?.override || {};

        oversightSummaryEl.innerHTML = [
            oversightCard('Pending approvals', approvals.pending_count ?? 0),
            oversightCard('Overdue approvals', approvals.overdue_count ?? 0),
            oversightCard('Active delegations', delegation.active_count ?? 0),
            oversightCard('Failed runs', audit.failed_count ?? 0, `Events: ${events.recent_count ?? 0}`),
        ].join('');

        approvalsCache = Array.isArray(approvals.pending) ? approvals.pending : [];
        approvalPagination = approvals.pagination || null;
        renderApprovalRows();

        const recommendations = snapshot?.recommended_actions || [];
        if (!recommendations.length) {
            oversightRecommendationsEl.innerHTML = `<div class="text-gray-500">No recommendation.</div>`;
        } else {
            oversightRecommendationsEl.innerHTML = recommendations.map((item) => `
                <div class="border rounded p-3 bg-gray-50">
                    <div class="flex items-center justify-between">
                        <div class="font-medium text-gray-800">${item?.type || 'unknown'}</div>
                        <span class="text-xs px-2 py-1 rounded bg-blue-100 text-blue-800">${item?.priority || 'low'}</span>
                    </div>
                    <div class="text-gray-600 mt-1">${item?.reason || '-'}</div>
                    <div class="text-gray-800 mt-1"><strong>Action:</strong> ${item?.action || '-'}</div>
                </div>
            `).join('');
        }

        controlStateBox.innerHTML = `
            <div><strong>Status:</strong> ${state?.status || '-'}</div>
            <div><strong>Kill switch:</strong> ${(kill?.active ? 'active' : 'inactive')}</div>
            <div><strong>Override mode:</strong> ${override?.force_mode || 'none'}</div>
            <div><strong>Allow low-risk apply:</strong> ${override?.allow_apply_low_risk === true ? 'true' : 'false'}</div>
            <div class="mt-2 text-xs text-gray-500"><strong>Policy hints:</strong> read=${canAbility('oversight_read')}, write=${canAbility('controls_update')}</div>
        `;
        applyPolicyHintsToControls();
    }

    async function loadGovernance() {
        const w = Math.max(1, Math.min(168, Number(windowEl.value) || 24));
        const [bRes, sRes] = await Promise.all([
            fetch(baselineUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
            fetch(`${snapshotUrl}?window_hours=${w}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
        ]);
        const bData = await bRes.json();
        const sData = await sRes.json();
        const cRes = await fetch(costsUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const cData = await cRes.json();
        if (!bData?.success || !sData?.success) throw new Error('governance_load_failed');

        renderSummary(sData.snapshot || {}, cData?.status || {});
        renderTable(bData.baseline || {}, sData.snapshot || {});
    }

    async function loadOversight() {
        setOversightBusy(true);
        try {
            const query = new URLSearchParams({
                limit: '200',
                approval_page: String(Math.max(1, approvalPage)),
                approval_page_size: String(Math.max(5, Number(approvalPageSizeEl.value) || 10)),
                approval_risk: (approvalRiskFilterEl.value || '').trim(),
                approval_sla: (approvalSlaFilterEl.value || '').trim(),
                approval_q: (approvalSearchEl.value || '').trim(),
                approval_sort_by: 'requested_at',
                approval_sort_dir: 'desc',
            });
            const res = await fetch(`${oversightUrl}?${query.toString()}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            if (!data?.success) throw new Error('oversight_load_failed');
            renderOversight(data.snapshot || {});
            setOversightStatus('success', 'Oversight data updated.');
            setTimeout(() => setOversightStatus('', ''), 1500);
        } catch (err) {
            setOversightStatus('error', `Oversight load failed: ${err.message || 'unknown error'}`);
            throw err;
        } finally {
            setOversightBusy(false);
        }
    }

    async function handleApprovalAction(id, mode) {
        if (!id || !mode) return;
        if (mode === 'approve' && !canAbility('approval_approve')) return;
        if (mode === 'reject' && !canAbility('approval_reject')) return;
        if (mode === 'veto' && !canAbility('approval_veto')) return;

        let url = '';
        let payload = {};

        if (mode === 'approve') {
            url = approvalsDecisionUrlPattern.replace('__APPROVAL_ID__', id);
            payload = { decision: 'approve' };
        } else if (mode === 'reject') {
            const reason = prompt('Reject reason:');
            if (!reason || !reason.trim()) return;
            url = approvalsDecisionUrlPattern.replace('__APPROVAL_ID__', id);
            payload = { decision: 'reject', reason };
        } else {
            const reason = prompt('Veto reason (optional):') || 'veto_by_operator';
            url = approvalsVetoUrlPattern.replace('__APPROVAL_ID__', id);
            payload = { reason };
        }

        setOversightBusy(true);
        try {
            await postJson(url, payload);
            setOversightStatus('success', `Approval action '${mode}' executed.`);
            await loadOversight();
        } catch (err) {
            setOversightStatus('error', `Approval action failed: ${err.message || 'unknown error'}`);
            throw err;
        } finally {
            setOversightBusy(false);
        }
    }

    async function handleControlAction(action) {
        if (!action) return;
        if (!canAbility('controls_update')) return;
        if ((action === 'activate_kill_switch' || action === 'deactivate_kill_switch') && !canAbility('controls_kill_switch')) return;
        if ((action === 'set_override' || action === 'clear_override') && !canAbility('controls_override')) return;

        const payload = { action };

        if (action === 'pause') {
            payload.minutes = 30;
            payload.reason = 'pause_from_oversight_console';
        } else if (action === 'activate_kill_switch') {
            payload.minutes = 15;
            payload.reason = 'kill_switch_from_oversight_console';
        } else if (action === 'set_override') {
            payload.force_mode = 'propose';
            payload.allow_apply_low_risk = false;
            payload.reason = 'force_propose_from_oversight_console';
        } else if (action === 'clear_override') {
            payload.reason = 'clear_override_from_oversight_console';
        } else {
            payload.reason = `${action}_from_oversight_console`;
        }

        setOversightBusy(true);
        try {
            await postJson(controlsUpdateUrl, payload);
            const controlsRes = await fetch(controlsUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const controlsData = await controlsRes.json().catch(() => ({}));
            if (controlsData?.success) {
                renderOversight({
                    controls: {
                        state: controlsData.state || {},
                        kill_switch: controlsData.kill_switch || {},
                        override: controlsData.override || {},
                    },
                    approvals: { pending: [], pending_count: 0, overdue_count: 0 },
                    delegation: { active_count: 0 },
                    audit: { failed_count: 0 },
                    events: { recent_count: 0 },
                    recommended_actions: [],
                });
            }
            setOversightStatus('success', `Control action '${action}' executed.`);
            await loadOversight();
        } catch (err) {
            setOversightStatus('error', `Control action failed: ${err.message || 'unknown error'}`);
            throw err;
        } finally {
            setOversightBusy(false);
        }
    }

    refreshBtn.addEventListener('click', () => loadGovernance().catch(() => {}));
    windowEl.addEventListener('change', () => loadGovernance().catch(() => {}));
    oversightRefreshBtn.addEventListener('click', () => loadOversight().catch(() => {}));
    approvalSearchEl.addEventListener('input', () => {
        approvalPage = 1;
        loadOversight().catch(() => {});
    });
    approvalRiskFilterEl.addEventListener('change', () => {
        approvalPage = 1;
        loadOversight().catch(() => {});
    });
    approvalSlaFilterEl.addEventListener('change', () => {
        approvalPage = 1;
        loadOversight().catch(() => {});
    });
    approvalPageSizeEl.addEventListener('change', () => {
        approvalPage = 1;
        loadOversight().catch(() => {});
    });
    approvalPrevPageBtn.addEventListener('click', () => {
        approvalPage = Math.max(1, approvalPage - 1);
        loadOversight().catch(() => {});
    });
    approvalNextPageBtn.addEventListener('click', () => {
        approvalPage += 1;
        loadOversight().catch(() => {});
    });

    approvalTableBody.addEventListener('click', (e) => {
        const target = e.target.closest('.approval-action');
        if (!target) return;
        const id = target.getAttribute('data-id');
        const mode = target.getAttribute('data-mode');
        handleApprovalAction(id, mode).catch(() => {});
    });

    document.querySelectorAll('.control-action').forEach((btn) => {
        btn.addEventListener('click', () => {
            const action = btn.getAttribute('data-control-action');
            handleControlAction(action).catch(() => {});
        });
    });

    loadGovernance().catch(() => {});
    loadOversight().catch(() => {});
})();
</script>
@endpush
