@extends('layouts.admin')

@section('title', 'Ops Digest - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'Najm Hoda Ops Digest')
@section('page-description', 'خلاصه اجرای عملیات، تاریخچه و رویدادهای اخیر')

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-800">داشبورد عملیات نجـم‌هدا</h2>
        <div class="flex items-center gap-2">
            <input id="opsLimit" type="number" min="1" max="100" value="20" class="border rounded px-2 py-1 w-24">
            <button id="opsRefresh" class="px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700">به‌روزرسانی</button>
        </div>
    </div>

    <div id="opsSummary" class="grid grid-cols-1 md:grid-cols-4 gap-4"></div>

    <div class="bg-white border rounded-lg p-4 space-y-3">
        <h3 class="font-semibold">کنترل خودگردانی (Pause / Resume / Override / Kill Switch)</h3>
        <div id="autonomyControlState" class="text-sm text-gray-700">-</div>
        <div class="flex flex-wrap items-center gap-2">
            <input id="pauseMinutes" type="number" min="1" max="10080" value="60" class="border rounded px-2 py-1 w-24" placeholder="دقیقه">
            <input id="controlReason" type="text" class="border rounded px-2 py-1 min-w-[220px]" placeholder="دلیل (اختیاری)">
            <button id="pauseBtn" class="px-3 py-1.5 bg-amber-600 text-white rounded hover:bg-amber-700">Pause</button>
            <button id="resumeBtn" class="px-3 py-1.5 bg-emerald-600 text-white rounded hover:bg-emerald-700">Resume</button>
            <button id="killSwitchOnBtn" class="px-3 py-1.5 bg-red-700 text-white rounded hover:bg-red-800">Kill Switch ON</button>
            <button id="killSwitchOffBtn" class="px-3 py-1.5 bg-slate-700 text-white rounded hover:bg-slate-800">Kill Switch OFF</button>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <select id="forceMode" class="border rounded px-2 py-1">
                <option value="">Force Mode: none</option>
                <option value="propose">Force propose</option>
                <option value="apply">Force apply</option>
            </select>
            <input id="blockedActions" type="text" class="border rounded px-2 py-1 min-w-[260px]" placeholder="blocked actions (comma-separated)">
            <button id="setOverrideBtn" class="px-3 py-1.5 bg-indigo-600 text-white rounded hover:bg-indigo-700">Set Override</button>
            <button id="clearOverrideBtn" class="px-3 py-1.5 bg-gray-700 text-white rounded hover:bg-gray-800">Clear Override</button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white border rounded-lg p-4">
            <h3 class="font-semibold mb-3">تاریخچه خلاصه اجرا</h3>
            <div class="overflow-auto max-h-[420px]">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-gray-100 text-right">
                            <th class="p-2">زمان</th>
                            <th class="p-2">وضعیت</th>
                            <th class="p-2">Incident</th>
                            <th class="p-2">Escalation</th>
                        </tr>
                    </thead>
                    <tbody id="opsHistoryBody"></tbody>
                </table>
            </div>
        </div>

        <div class="bg-white border rounded-lg p-4">
            <h3 class="font-semibold mb-3">رویدادهای اخیر Ops</h3>
            <div id="opsEvents" class="space-y-2 max-h-[420px] overflow-auto"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const summaryEl = document.getElementById('opsSummary');
    const historyBody = document.getElementById('opsHistoryBody');
    const eventsEl = document.getElementById('opsEvents');
    const refreshBtn = document.getElementById('opsRefresh');
    const limitInput = document.getElementById('opsLimit');
    const digestUrl = @json(route('admin.najm-hoda.ops.digest'));
    const controlsUrl = @json(route('admin.najm-hoda.autonomy.controls'));
    const controlsUpdateUrl = @json(route('admin.najm-hoda.autonomy.controls.update'));
    const controlStateEl = document.getElementById('autonomyControlState');
    const pauseMinutesEl = document.getElementById('pauseMinutes');
    const controlReasonEl = document.getElementById('controlReason');
    const forceModeEl = document.getElementById('forceMode');
    const blockedActionsEl = document.getElementById('blockedActions');
    const pauseBtn = document.getElementById('pauseBtn');
    const resumeBtn = document.getElementById('resumeBtn');
    const killSwitchOnBtn = document.getElementById('killSwitchOnBtn');
    const killSwitchOffBtn = document.getElementById('killSwitchOffBtn');
    const setOverrideBtn = document.getElementById('setOverrideBtn');
    const clearOverrideBtn = document.getElementById('clearOverrideBtn');

    function card(title, value, hint = '') {
        return `
            <div class="bg-white border rounded-lg p-4">
                <div class="text-xs text-gray-500 mb-1">${title}</div>
                <div class="text-xl font-bold text-gray-800">${value}</div>
                <div class="text-xs text-gray-400 mt-1">${hint}</div>
            </div>
        `;
    }

    function fmtDate(value) {
        if (!value) return '-';
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return String(value);
        return d.toLocaleString('fa-IR');
    }

    function renderSummary(lastSummary) {
        if (!lastSummary) {
            summaryEl.innerHTML = card('وضعیت', 'بدون داده');
            return;
        }
        summaryEl.innerHTML = [
            card('وضعیت', lastSummary.status || '-'),
            card('Error Rate', (lastSummary.error_rate_percent ?? 0) + '%'),
            card('Incident Count', lastSummary.incident_count ?? 0),
            card('Escalation Count', lastSummary.escalation_count ?? 0, fmtDate(lastSummary.generated_at)),
        ].join('');
    }

    function renderHistory(history) {
        if (!Array.isArray(history) || history.length === 0) {
            historyBody.innerHTML = `<tr><td class="p-2 text-gray-500" colspan="4">داده‌ای موجود نیست.</td></tr>`;
            return;
        }

        historyBody.innerHTML = history.map(row => `
            <tr class="border-b">
                <td class="p-2">${fmtDate(row.generated_at)}</td>
                <td class="p-2">${row.status || '-'}</td>
                <td class="p-2">${row.incident_count ?? 0}</td>
                <td class="p-2">${row.escalation_count ?? 0}</td>
            </tr>
        `).join('');
    }

    function renderEvents(events) {
        if (!Array.isArray(events) || events.length === 0) {
            eventsEl.innerHTML = `<div class="text-sm text-gray-500">رویدادی موجود نیست.</div>`;
            return;
        }

        eventsEl.innerHTML = events.map(item => `
            <div class="border rounded p-2">
                <div class="font-semibold text-sm">${item.event || '-'}</div>
                <div class="text-xs text-gray-500">${fmtDate(item.timestamp)}</div>
            </div>
        `).join('');
    }

    async function loadDigest() {
        const limit = Math.max(1, Math.min(100, Number(limitInput.value) || 20));
        const res = await fetch(`${digestUrl}?limit=${limit}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (!data || !data.success) {
            throw new Error('Digest fetch failed');
        }

        renderSummary(data.last_summary || null);
        renderHistory(data.history || []);
        renderEvents(data.recent_ops_events || []);
    }

    function renderControls(data) {
        const state = data?.state || {};
        const killSwitch = data?.kill_switch || {};
        const override = data?.override || {};
        const paused = !!state.paused;
        const until = state.paused_until ? fmtDate(state.paused_until) : 'بدون زمان پایان';
        const killActive = !!killSwitch.active;
        const killUntil = killSwitch.active_until ? fmtDate(killSwitch.active_until) : 'بدون زمان پایان';
        const forceMode = override.force_mode || 'none';
        const blocked = Array.isArray(override.blocked_actions) ? override.blocked_actions.join(', ') : '';

        controlStateEl.textContent = `وضعیت: ${paused ? 'PAUSED' : 'RUNNING'} | kill_switch: ${killActive ? 'ON' : 'OFF'} | kill_until: ${killUntil} | paused_until: ${until} | force_mode: ${forceMode} | blocked: ${blocked || '-'}`;
        forceModeEl.value = override.force_mode || '';
        blockedActionsEl.value = blocked;
    }

    async function loadControls() {
        const res = await fetch(controlsUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();
        if (!data?.success) throw new Error('controls fetch failed');
        renderControls(data);
    }

    async function updateControls(payload) {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch(controlsUpdateUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (!data?.success) throw new Error(data?.message || 'control update failed');
        renderControls(data);
    }

    refreshBtn.addEventListener('click', () => loadDigest().catch(() => {}));
    pauseBtn.addEventListener('click', () => updateControls({
        action: 'pause',
        minutes: Number(pauseMinutesEl.value) || 60,
        reason: controlReasonEl.value || null,
    }).catch(() => {}));
    resumeBtn.addEventListener('click', () => updateControls({
        action: 'resume',
        reason: controlReasonEl.value || null,
    }).catch(() => {}));
    killSwitchOnBtn.addEventListener('click', () => updateControls({
        action: 'activate_kill_switch',
        minutes: Number(pauseMinutesEl.value) || 60,
        reason: controlReasonEl.value || null,
    }).catch(() => {}));
    killSwitchOffBtn.addEventListener('click', () => updateControls({
        action: 'deactivate_kill_switch',
        reason: controlReasonEl.value || null,
    }).catch(() => {}));
    setOverrideBtn.addEventListener('click', () => updateControls({
        action: 'set_override',
        force_mode: forceModeEl.value || null,
        blocked_actions: blockedActionsEl.value || '',
        reason: controlReasonEl.value || null,
    }).catch(() => {}));
    clearOverrideBtn.addEventListener('click', () => updateControls({
        action: 'clear_override',
        reason: controlReasonEl.value || null,
    }).catch(() => {}));

    loadDigest().catch(() => {});
    loadControls().catch(() => {});
})();
</script>
@endpush
