const attentionTypeLabels = {
    overdue: 'معوق',
    due_soon: 'نزدیک موعد',
    blocked: 'مسدود',
    urgent: 'فوری',
    unassigned: 'بدون مسئول',
};

const attentionStatusLabels = {
    open: 'باز',
    in_progress: 'در حال انجام',
    blocked: 'مسدود',
    done: 'انجام‌شده',
    cancelled: 'لغوشده',
};

const attentionPriorityLabels = {
    low: 'کم',
    medium: 'متوسط',
    high: 'زیاد',
    urgent: 'فوری',
};

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[char]));

const humanDate = (value) => {
    if (!value) return 'هنوز ثبت نشده';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat('fa-IR', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
};

const bootNajmHodaAttentionPanel = () => {
    const routeMatch = window.location.pathname.match(/^\/groups\/(\d+)\/najm-hoda\/panel\/?$/);
    if (!routeMatch) return;

    const legacySettingsForm = document.getElementById('group-hoda-settings-form');
    if (!legacySettingsForm || document.getElementById('group-hoda-attention-panel')) return;

    const groupId = routeMatch[1];
    const endpoint = `/groups/${groupId}/najm-hoda/attention`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let currentTimezone = 'Asia/Tehran';

    const section = document.createElement('section');
    section.id = 'group-hoda-attention-panel';
    section.className = 'border border-violet-200 rounded-2xl overflow-hidden bg-white';
    section.innerHTML = `
        <div class="px-4 py-3 border-b border-violet-100 bg-violet-50/60 flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="font-semibold text-sm text-gray-900">پیگیری فعال نجم هدا</div>
                <div class="text-xs text-gray-500 mt-0.5">تشخیص موارد نیازمند توجه، جلوگیری از هشدار تکراری و گزارش مدیریتی این گروه</div>
            </div>
            <button type="button" id="group-hoda-attention-refresh" class="px-3 py-1.5 text-xs rounded-lg border border-violet-200 text-violet-700 hover:bg-violet-50 transition">بروزرسانی پیگیری</button>
        </div>
        <div class="p-4 space-y-4">
            <div id="group-hoda-attention-message" class="text-xs text-gray-500"></div>
            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-2 text-xs">
                <div class="rounded-xl border border-violet-100 bg-violet-50 p-3">فعال: <strong id="attention-stat-active">-</strong></div>
                <div class="rounded-xl border border-red-100 bg-red-50 p-3">معوق: <strong id="attention-stat-overdue">-</strong></div>
                <div class="rounded-xl border border-orange-100 bg-orange-50 p-3">نزدیک موعد: <strong id="attention-stat-due-soon">-</strong></div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">مسدود: <strong id="attention-stat-blocked">-</strong></div>
                <div class="rounded-xl border border-rose-100 bg-rose-50 p-3">فوری: <strong id="attention-stat-urgent">-</strong></div>
                <div class="rounded-xl border border-amber-100 bg-amber-50 p-3">بدون مسئول: <strong id="attention-stat-unassigned">-</strong></div>
            </div>
            <form id="group-hoda-attention-form" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm"><span>پیگیری فعال</span><input type="checkbox" id="attention-enabled"></label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 text-sm"><span class="block text-xs text-gray-600 mb-1">حالت اعلان</span><select id="attention-digest-mode" class="w-full text-sm border-0 p-0 focus:ring-0"><option value="immediate">فوری</option><option value="daily">خلاصه روزانه</option><option value="off">فقط ثبت داخلی؛ بدون اعلان</option></select></label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 text-sm"><span class="block text-xs text-gray-600 mb-1">ساعت خلاصه روزانه</span><input id="attention-preferred-time" type="time" class="w-full text-sm border-0 p-0 focus:ring-0"></label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 text-sm"><span class="block text-xs text-gray-600 mb-1">بازه نزدیک موعد (ساعت)</span><input id="attention-due-soon-hours" type="number" min="1" max="720" class="w-full text-sm border-0 p-0 focus:ring-0"></label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 text-sm"><span class="block text-xs text-gray-600 mb-1">فاصله تکرار هشدار (دقیقه)</span><input id="attention-suppress-minutes" type="number" min="60" max="10080" class="w-full text-sm border-0 p-0 focus:ring-0"></label>
                <div class="border border-gray-200 rounded-xl px-3 py-2 text-xs text-gray-600"><div>منطقه زمانی: <strong id="attention-timezone">-</strong></div><div class="mt-1">آخرین ارزیابی: <span id="attention-last-evaluated">-</span></div><div class="mt-1">آخرین گزارش: <span id="attention-last-digest">-</span></div></div>
                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm"><span>هشدار معوق</span><input type="checkbox" id="attention-alert-overdue"></label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm"><span>هشدار نزدیک موعد</span><input type="checkbox" id="attention-alert-due-soon"></label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm"><span>هشدار مسدود</span><input type="checkbox" id="attention-alert-blocked"></label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm"><span>هشدار فوری</span><input type="checkbox" id="attention-alert-urgent"></label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm"><span>هشدار بدون مسئول</span><input type="checkbox" id="attention-alert-unassigned"></label>
                <div class="md:col-span-3"><button type="submit" class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm hover:bg-violet-700 transition">ذخیره تنظیمات پیگیری</button></div>
            </form>
            <div>
                <div class="text-sm font-semibold text-gray-800 mb-2">موارد فعال نیازمند توجه</div>
                <div id="group-hoda-attention-events" class="space-y-2"><div class="text-xs text-gray-400">در حال بارگذاری...</div></div>
            </div>
        </div>`;

    legacySettingsForm.insertAdjacentElement('afterend', section);

    const message = document.getElementById('group-hoda-attention-message');
    const eventsBox = document.getElementById('group-hoda-attention-events');
    const setMessage = (text, error = false) => {
        message.textContent = text;
        message.className = `text-xs ${error ? 'text-red-600' : 'text-violet-700'}`;
    };

    const renderEvents = (events) => {
        if (!Array.isArray(events) || events.length === 0) {
            eventsBox.innerHTML = '<div class="rounded-xl border border-emerald-100 bg-emerald-50 text-emerald-700 px-3 py-3 text-xs">در حال حاضر event فعالی نیازمند توجه نیست.</div>';
            return;
        }
        eventsBox.innerHTML = events.map((event) => {
            const item = event.action_item || {};
            return `<div class="rounded-xl border border-gray-200 p-3 text-xs">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="font-semibold text-gray-800">${escapeHtml(item.title || 'مورد اقدام')}</div>
                    <span class="px-2 py-0.5 rounded-full bg-violet-100 text-violet-700">${escapeHtml(attentionTypeLabels[event.event_type] || event.event_type)}</span>
                </div>
                <div class="mt-2 text-gray-500 flex flex-wrap gap-x-4 gap-y-1">
                    <span>وضعیت: ${escapeHtml(attentionStatusLabels[item.status] || item.status || '-')}</span>
                    <span>اولویت: ${escapeHtml(attentionPriorityLabels[item.priority] || item.priority || '-')}</span>
                    <span>مسئول: ${escapeHtml(item.assignee_name || 'تعیین نشده')}</span>
                    <span>موعد: ${escapeHtml(humanDate(item.due_at))}</span>
                    <span>تکرار مشاهده: ${Number(event.occurrences || 0)}</span>
                    <span>آخرین مشاهده: ${escapeHtml(humanDate(event.last_seen_at))}</span>
                </div>
            </div>`;
        }).join('');
    };

    const render = (attention) => {
        const policy = attention?.policy || {};
        const stats = attention?.stats || {};
        currentTimezone = policy.timezone || currentTimezone;
        document.getElementById('attention-enabled').checked = !!policy.enabled;
        document.getElementById('attention-digest-mode').value = policy.digest_mode || 'daily';
        document.getElementById('attention-preferred-time').value = policy.preferred_time || '08:00';
        document.getElementById('attention-due-soon-hours').value = policy.due_soon_hours ?? 48;
        document.getElementById('attention-suppress-minutes').value = policy.suppress_minutes ?? 720;
        document.getElementById('attention-alert-overdue').checked = !!policy.alert_overdue;
        document.getElementById('attention-alert-due-soon').checked = !!policy.alert_due_soon;
        document.getElementById('attention-alert-blocked').checked = !!policy.alert_blocked;
        document.getElementById('attention-alert-urgent').checked = !!policy.alert_urgent;
        document.getElementById('attention-alert-unassigned').checked = !!policy.alert_unassigned;
        document.getElementById('attention-timezone').textContent = currentTimezone;
        document.getElementById('attention-last-evaluated').textContent = humanDate(policy.last_evaluated_at);
        document.getElementById('attention-last-digest').textContent = humanDate(policy.last_digest_at);
        document.getElementById('attention-stat-active').textContent = stats.active_events ?? 0;
        document.getElementById('attention-stat-overdue').textContent = stats.overdue ?? 0;
        document.getElementById('attention-stat-due-soon').textContent = stats.due_soon ?? 0;
        document.getElementById('attention-stat-blocked').textContent = stats.blocked ?? 0;
        document.getElementById('attention-stat-urgent').textContent = stats.urgent ?? 0;
        document.getElementById('attention-stat-unassigned').textContent = stats.unassigned ?? 0;
        renderEvents(attention?.events || []);
    };

    const load = async () => {
        try {
            setMessage('در حال دریافت وضعیت پیگیری...');
            const response = await fetch(endpoint, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await response.json();
            if (!response.ok || data.status !== 'success') throw new Error(data.message || 'خطا در دریافت وضعیت پیگیری');
            render(data.attention);
            setMessage('وضعیت پیگیری بروزرسانی شد.');
        } catch (error) {
            setMessage(error.message || 'خطا در دریافت وضعیت پیگیری.', true);
        }
    };

    document.getElementById('group-hoda-attention-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const payload = {
            enabled: document.getElementById('attention-enabled').checked,
            digest_mode: document.getElementById('attention-digest-mode').value,
            preferred_time: document.getElementById('attention-preferred-time').value || '08:00',
            timezone: currentTimezone,
            due_soon_hours: Number(document.getElementById('attention-due-soon-hours').value || 48),
            suppress_minutes: Number(document.getElementById('attention-suppress-minutes').value || 720),
            alert_overdue: document.getElementById('attention-alert-overdue').checked,
            alert_due_soon: document.getElementById('attention-alert-due-soon').checked,
            alert_blocked: document.getElementById('attention-alert-blocked').checked,
            alert_urgent: document.getElementById('attention-alert-urgent').checked,
            alert_unassigned: document.getElementById('attention-alert-unassigned').checked,
        };
        try {
            setMessage('در حال ذخیره تنظیمات پیگیری...');
            const response = await fetch(endpoint, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (!response.ok || data.status !== 'success') throw new Error(data.message || 'خطا در ذخیره تنظیمات پیگیری');
            render(data.attention);
            setMessage(data.message || 'تنظیمات پیگیری ذخیره شد.');
        } catch (error) {
            setMessage(error.message || 'خطا در ذخیره تنظیمات پیگیری.', true);
        }
    });

    document.getElementById('group-hoda-attention-refresh')?.addEventListener('click', load);
    load();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootNajmHodaAttentionPanel, { once: true });
} else {
    bootNajmHodaAttentionPanel();
}
