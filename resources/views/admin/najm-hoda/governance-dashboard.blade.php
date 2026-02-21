@extends('layouts.admin')

@section('title', 'Governance Dashboard - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'Najm Hoda Governance Dashboard')
@section('page-description', 'شاخص های حکمرانی، وضعیت SLO و سلامت تصمیم گیری خودگردان')

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-800">داشبورد حکمرانی نجم‌هدا</h2>
        <div class="flex items-center gap-2">
            <select id="windowHours" class="border rounded px-2 py-1">
                <option value="1">1h</option>
                <option value="6">6h</option>
                <option value="24" selected>24h</option>
                <option value="72">72h</option>
            </select>
            <button id="governanceRefresh" class="px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700">به‌روزرسانی</button>
        </div>
    </div>

    <div id="governanceSummary" class="grid grid-cols-1 md:grid-cols-4 gap-4"></div>

    <div class="bg-white border rounded-lg p-4">
        <h3 class="font-semibold mb-3">وضعیت KPI / SLO</h3>
        <div class="overflow-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-gray-100 text-right">
                        <th class="p-2">شاخص</th>
                        <th class="p-2">مقدار</th>
                        <th class="p-2">هدف</th>
                        <th class="p-2">وضعیت</th>
                    </tr>
                </thead>
                <tbody id="governanceTableBody"></tbody>
            </table>
        </div>
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

    const baselineUrl = @json(route('admin.najm-hoda.autonomy.governance.baseline'));
    const snapshotUrl = @json(route('admin.najm-hoda.autonomy.governance.snapshot'));
    const costsUrl = @json(route('admin.najm-hoda.autonomy.costs.status'));

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
        if (typeof v === 'number') {
            return v.toFixed(4).replace(/\.?0+$/, '');
        }
        return String(v);
    }

    function renderSummary(snapshot, costs) {
        const metrics = snapshot?.metrics || {};
        const evals = snapshot?.evaluation || {};
        const allStatuses = Object.values(evals).map(x => x?.status || 'no_data');
        const breachCount = allStatuses.filter(x => x === 'breach').length;
        const warningCount = allStatuses.filter(x => x === 'warning').length;
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

    function renderTable(baseline, snapshot) {
        const metrics = snapshot?.metrics || {};
        const evals = snapshot?.evaluation || {};
        const keys = Object.keys(baseline || {});
        if (!keys.length) {
            tableBody.innerHTML = `<tr><td class="p-2 text-gray-500" colspan="4">داده‌ای موجود نیست.</td></tr>`;
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

    async function load() {
        const w = Math.max(1, Math.min(168, Number(windowEl.value) || 24));
        const [bRes, sRes] = await Promise.all([
            fetch(baselineUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
            fetch(`${snapshotUrl}?window_hours=${w}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
        ]);
        const bData = await bRes.json();
        const sData = await sRes.json();
        const cRes = await fetch(costsUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const cData = await cRes.json();
        if (!bData?.success || !sData?.success) throw new Error('load failed');

        const baseline = bData.baseline || {};
        const snapshot = sData.snapshot || {};
        const costs = cData?.status || {};
        renderSummary(snapshot, costs);
        renderTable(baseline, snapshot);
    }

    refreshBtn.addEventListener('click', () => load().catch(() => {}));
    windowEl.addEventListener('change', () => load().catch(() => {}));
    load().catch(() => {});
})();
</script>
@endpush
