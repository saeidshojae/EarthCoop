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
            <div id="oversightSummary" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4"></div>
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
    const approvalTableBody = document.getElementById('approvalTableBody');
    const oversightRecommendationsEl = document.getElementById('oversightRecommendations');
    const controlStateBox = document.getElementById('controlStateBox');

    const baselineUrl = @json(route('admin.najm-hoda.autonomy.governance.baseline'));
    const snapshotUrl = @json(route('admin.najm-hoda.autonomy.governance.snapshot'));
    const costsUrl = @json(route('admin.najm-hoda.autonomy.costs.status'));
    const oversightUrl = @json(route('admin.najm-hoda.autonomy.oversight.console'));
    const controlsUrl = @json(route('admin.najm-hoda.autonomy.controls'));
    const controlsUpdateUrl = @json(route('admin.najm-hoda.autonomy.controls.update'));
    const approvalsDecisionUrlPattern = @json(route('admin.najm-hoda.autonomy.approvals.decision', ['approvalId' => '__APPROVAL_ID__']));
    const approvalsVetoUrlPattern = @json(route('admin.najm-hoda.autonomy.approvals.veto', ['approvalId' => '__APPROVAL_ID__']));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

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

    function renderOversight(snapshot) {
        const approvals = snapshot?.approvals || {};
        const delegation = snapshot?.delegation || {};
        const audit = snapshot?.audit || {};
        const events = snapshot?.events || {};
        const controls = snapshot?.controls || {};
        const state = controls?.state || {};
        const kill = controls?.kill_switch || {};
        const override = controls?.override || {};

        oversightSummaryEl.innerHTML = [
            oversightCard('Pending approvals', approvals.pending_count ?? 0),
            oversightCard('Overdue approvals', approvals.overdue_count ?? 0),
            oversightCard('Active delegations', delegation.active_count ?? 0),
            oversightCard('Failed runs', audit.failed_count ?? 0, `Events: ${events.recent_count ?? 0}`),
        ].join('');

        const pending = approvals.pending || [];
        if (!pending.length) {
            approvalTableBody.innerHTML = `<tr><td class="p-2 text-gray-500" colspan="5">No pending approvals.</td></tr>`;
        } else {
            approvalTableBody.innerHTML = pending.map((row) => {
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
                                <button class="approval-action px-2 py-1 bg-emerald-600 text-white rounded text-xs" data-id="${id}" data-mode="approve">Approve</button>
                                <button class="approval-action px-2 py-1 bg-amber-600 text-white rounded text-xs" data-id="${id}" data-mode="reject">Reject</button>
                                <button class="approval-action px-2 py-1 bg-red-600 text-white rounded text-xs" data-id="${id}" data-mode="veto">Veto</button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

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
        `;
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
        const res = await fetch(`${oversightUrl}?limit=80`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();
        if (!data?.success) throw new Error('oversight_load_failed');
        renderOversight(data.snapshot || {});
    }

    async function handleApprovalAction(id, mode) {
        if (!id || !mode) return;
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

        await postJson(url, payload);
        await loadOversight();
    }

    async function handleControlAction(action) {
        if (!action) return;
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
        await loadOversight();
    }

    refreshBtn.addEventListener('click', () => loadGovernance().catch(() => {}));
    windowEl.addEventListener('change', () => loadGovernance().catch(() => {}));
    oversightRefreshBtn.addEventListener('click', () => loadOversight().catch(() => {}));

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

