@extends('layouts.unified')

@section('title', 'پنل نجم‌هدا - ' . $group->name)

@php
    $actionMetaMap = \App\Models\NajmHodaGroupActionItem::query()
        ->where('group_id', $group->id)
        ->latest('id')
        ->take(100)
        ->get()
        ->mapWithKeys(function ($item) {
            return [(string) $item->id => [
                'details' => $item->details,
                'due_text' => $item->due_text,
                'source' => data_get($item->meta, 'source'),
                'evidence' => data_get($item->meta, 'evidence'),
                'origin' => data_get($item->meta, 'origin'),
                'management_history' => array_values((array) data_get($item->meta, 'management_history', [])),
            ]];
        });
@endphp

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8 space-y-6" style="direction: rtl;">
    <div class="bg-white border border-emerald-100 rounded-3xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-emerald-100 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-bold text-gray-900">پنل نجم‌هدا برای مدیر/بازرس گروه</h1>
                <p class="text-sm text-gray-500">{{ $group->name }}</p>
                <p class="text-xs text-gray-400 mt-1">تنظیم اختیارات دستیار و مدیریت صف اقدام همین گروه</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('groups.najm-hoda.guide', $group) }}" class="px-3 py-1.5 text-xs rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 transition">راهنمای استفاده</a>
                <a href="{{ route('groups.show', $group) }}" class="px-3 py-1.5 text-xs rounded-lg border border-emerald-200 text-emerald-700 hover:bg-emerald-50 transition">بازگشت به گروه</a>
                <button id="group-hoda-refresh" type="button" class="px-3 py-1.5 text-xs rounded-lg border border-emerald-200 text-emerald-700 hover:bg-emerald-50 transition">بروزرسانی</button>
            </div>
        </div>

        <div class="p-6 space-y-6">
            <div id="group-hoda-global-notice" class="hidden text-sm rounded-xl border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3"></div>

            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 text-xs">
                <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-3 text-emerald-700">کل صف اقدام: <span id="group-hoda-stat-total" class="font-bold text-emerald-800">-</span></div>
                <div class="rounded-xl border border-blue-100 bg-blue-50 p-3 text-blue-700">باز: <span id="group-hoda-stat-open" class="font-bold text-blue-800">-</span></div>
                <div class="rounded-xl border border-green-100 bg-green-50 p-3 text-green-700">انجام‌شده: <span id="group-hoda-stat-done" class="font-bold text-green-800">-</span></div>
                <div class="rounded-xl border border-red-100 bg-red-50 p-3 text-red-700">معوق: <span id="group-hoda-stat-overdue" class="font-bold text-red-800">-</span></div>
                <div class="rounded-xl border border-amber-100 bg-amber-50 p-3 text-amber-700">بدون مسئول: <span id="group-hoda-stat-unassigned" class="font-bold text-amber-800">-</span></div>
                <div class="rounded-xl border border-rose-100 bg-rose-50 p-3 text-rose-700">فوری: <span id="group-hoda-stat-urgent" class="font-bold text-rose-800">-</span></div>
            </div>

            <form id="group-hoda-settings-form" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm"><span>فعال بودن دستیار</span><input type="checkbox" id="group-hoda-enabled"></label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 text-sm">
                    <span class="block text-xs text-gray-600 mb-1">نقش رفتاری دستیار</span>
                    <select id="group-hoda-assistant-role" class="w-full text-sm border-0 p-0 focus:ring-0"><option value="secretary">منشی</option><option value="advisor">مشاور</option><option value="admin">ادمین</option><option value="hybrid">ترکیبی</option></select>
                </label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm"><span>حالت جلسه</span><input type="checkbox" id="group-hoda-meeting-mode"></label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm"><span>راهنمایی فعال</span><input type="checkbox" id="group-hoda-proactive"></label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm"><span>ارسال پیام خصوصی</span><input type="checkbox" id="group-hoda-allow-private-messages"></label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 text-sm">
                    <span class="block text-xs text-gray-600 mb-1">روش پیام خصوصی</span>
                    <select id="group-hoda-private-message-mode" class="w-full text-sm border-0 p-0 focus:ring-0"><option value="direct">مستقیم</option><option value="request">درخواست چت</option></select>
                </label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm"><span>پیشنهاد قبل از اجرا</span><input type="checkbox" id="group-hoda-action-propose-before-execute"></label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm"><span>ایجاد پست</span><input type="checkbox" id="group-hoda-action-create-post"></label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm"><span>ایجاد نظرسنجی</span><input type="checkbox" id="group-hoda-action-create-poll"></label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm"><span>ایجاد دیدگاه</span><input type="checkbox" id="group-hoda-action-create-comment"></label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm"><span>واکنش به پیام</span><input type="checkbox" id="group-hoda-action-react-message"></label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm"><span>واکنش به پست</span><input type="checkbox" id="group-hoda-action-react-post"></label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm"><span>واکنش به دیدگاه</span><input type="checkbox" id="group-hoda-action-react-comment"></label>
                <div class="md:col-span-3 flex items-center justify-between gap-3 mt-1">
                    <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm hover:bg-emerald-700 transition">ذخیره تنظیمات گروه</button>
                    <div id="group-hoda-message" class="text-xs text-gray-500"></div>
                </div>
            </form>

            <section class="border border-gray-200 rounded-2xl overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-center gap-3 justify-between bg-gray-50/60">
                    <div>
                        <div class="font-semibold text-sm text-gray-800">صف اقدام نجم‌هدا</div>
                        <div class="text-xs text-gray-500 mt-0.5">همان صفی که از چت خصوصی نجم‌هدا خوانده و مدیریت می‌شود.</div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <input id="group-hoda-items-search" type="text" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5" placeholder="جستجو در صف اقدام...">
                        <select id="group-hoda-items-status-filter" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5">
                            <option value="">همه وضعیت‌ها</option><option value="open">باز</option><option value="in_progress">در حال انجام</option><option value="blocked">مسدود</option><option value="done">انجام شده</option><option value="cancelled">لغو شده</option>
                        </select>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600"><tr><th class="text-right px-3 py-2">عنوان و جزئیات</th><th class="text-right px-3 py-2">مسئول</th><th class="text-right px-3 py-2">سررسید</th><th class="text-right px-3 py-2">وضعیت</th><th class="text-right px-3 py-2">اولویت</th><th class="text-right px-3 py-2">اقدام</th></tr></thead>
                        <tbody id="group-hoda-items-body"><tr><td colspan="6" class="px-3 py-4 text-center text-gray-400">در حال بارگذاری...</td></tr></tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const settingsUrl = @json(route('groups.najm-hoda.settings', $group));
    const itemsUrl = @json(route('groups.najm-hoda.action-items', $group));
    const actionMeta = @json($actionMetaMap);
    const ids = {
        enabled: 'group-hoda-enabled', role: 'group-hoda-assistant-role', meeting: 'group-hoda-meeting-mode', proactive: 'group-hoda-proactive',
        allowPm: 'group-hoda-allow-private-messages', pmMode: 'group-hoda-private-message-mode', propose: 'group-hoda-action-propose-before-execute',
        createPost: 'group-hoda-action-create-post', createPoll: 'group-hoda-action-create-poll', createComment: 'group-hoda-action-create-comment',
        reactMessage: 'group-hoda-action-react-message', reactPost: 'group-hoda-action-react-post', reactComment: 'group-hoda-action-react-comment',
    };
    const settingsForm = document.getElementById('group-hoda-settings-form');
    const refreshBtn = document.getElementById('group-hoda-refresh');
    const statusFilter = document.getElementById('group-hoda-items-status-filter');
    const searchInput = document.getElementById('group-hoda-items-search');
    const itemsBody = document.getElementById('group-hoda-items-body');
    const messageBox = document.getElementById('group-hoda-message');
    const globalNotice = document.getElementById('group-hoda-global-notice');
    let members = [];

    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (m) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]));
    const showMessage = (text, isError = false) => {
        if (!messageBox) return;
        messageBox.textContent = text;
        messageBox.className = `text-xs ${isError ? 'text-red-600' : 'text-emerald-700'}`;
    };
    const setDisabled = (id, disabled) => { const el = document.getElementById(id); if (el) el.disabled = !!disabled; };
    const isActive = (item) => !['done','cancelled'].includes(item.status);
    const isOverdue = (item) => !!item.due_at && isActive(item) && new Date(item.due_at).getTime() < Date.now();
    const statusLabels = { open:'باز', in_progress:'در حال انجام', blocked:'مسدود', done:'انجام شده', cancelled:'لغو شده' };
    const priorityLabels = { low:'کم', medium:'متوسط', high:'زیاد', urgent:'فوری' };
    const sourceLabel = (source) => {
        const m = String(source || '').match(/^(message|post|poll):(\d+)$/);
        if (!m) return source || 'منبع ثبت نشده';
        return `${m[1] === 'message' ? 'پیام' : (m[1] === 'post' ? 'پست' : 'نظرسنجی')} #${m[2]}`;
    };
    const historyHtml = (history) => {
        if (!Array.isArray(history) || history.length === 0) return '<div class="text-gray-400">تغییر مدیریتی ثبت‌شده‌ای وجود ندارد.</div>';
        return history.slice().reverse().slice(0, 8).map((h) => {
            const changes = h?.changes || {};
            const parts = [];
            if (changes.status) parts.push(`وضعیت → ${statusLabels[changes.status] || changes.status}`);
            if (changes.priority) parts.push(`اولویت → ${priorityLabels[changes.priority] || changes.priority}`);
            if (Object.prototype.hasOwnProperty.call(changes, 'assigned_user_id')) parts.push(`مسئول تغییر کرد`);
            if (Object.prototype.hasOwnProperty.call(changes, 'due_at')) parts.push(`موعد تغییر کرد`);
            return `<div class="py-1 border-b border-gray-100 last:border-0">${esc(parts.join('، ') || 'تغییر ثبت شد')} <span class="text-gray-400">— کاربر #${esc(h?.changed_by_user_id || '-')} / ${esc(h?.changed_at || '')}</span></div>`;
        }).join('');
    };

    const memberOptions = (selected) => ['<option value="">بدون مسئول</option>'].concat(members.map((m) => `<option value="${esc(m.id)}" ${String(m.id) === String(selected || '') ? 'selected' : ''}>${esc(m.name)}</option>`)).join('');

    const renderItems = (items) => {
        if (!itemsBody) return;
        if (!Array.isArray(items) || items.length === 0) {
            itemsBody.innerHTML = '<tr><td colspan="6" class="px-3 py-5 text-center text-gray-400">موردی در صف اقدام یافت نشد.</td></tr>';
            return;
        }
        itemsBody.innerHTML = items.map((item) => {
            const meta = actionMeta[String(item.id)] || {};
            const overdue = isOverdue(item);
            const unassigned = isActive(item) && !item.assigned_user_id && !item.assignee_name;
            const urgent = isActive(item) && item.priority === 'urgent';
            const flags = [overdue ? '<span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-[11px]">معوق</span>' : '', unassigned ? '<span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[11px]">بدون مسئول</span>' : '', urgent ? '<span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 text-[11px]">فوری</span>' : ''].filter(Boolean).join(' ');
            const evidence = meta.evidence ? `<div><span class="font-semibold">شاهد:</span> «${esc(meta.evidence)}»</div>` : '<div class="text-gray-400">شاهد مستقیمی در metadata ثبت نشده است.</div>';
            const source = `<div><span class="font-semibold">منبع:</span> ${esc(sourceLabel(meta.source))}</div>`;
            const details = meta.details || item.details || '';
            return `
                <tr class="border-b border-gray-100 align-top" data-item-id="${esc(item.id)}">
                    <td class="px-3 py-3 min-w-[300px]">
                        <div class="font-semibold text-gray-800">${esc(item.title || '-')}</div>
                        ${details ? `<div class="text-xs text-gray-500 mt-1 line-clamp-2">${esc(details)}</div>` : ''}
                        <div class="flex flex-wrap gap-1 mt-2">${flags}</div>
                        <details class="mt-2 text-xs text-gray-600">
                            <summary class="cursor-pointer text-emerald-700">منبع، شاهد و تاریخچه</summary>
                            <div class="mt-2 rounded-lg bg-gray-50 border border-gray-100 p-3 space-y-2">${source}${evidence}${meta.due_text ? `<div><span class="font-semibold">موعد متنی اولیه:</span> ${esc(meta.due_text)}</div>` : ''}<div><span class="font-semibold">تاریخچه:</span>${historyHtml(meta.management_history)}</div></div>
                        </details>
                    </td>
                    <td class="px-3 py-3 min-w-[180px]"><select class="queue-assignee w-full text-xs border border-gray-200 rounded-lg px-2 py-1.5">${memberOptions(item.assigned_user_id)}</select></td>
                    <td class="px-3 py-3 min-w-[185px]"><input class="queue-due w-full text-xs border ${overdue ? 'border-red-300 bg-red-50' : 'border-gray-200'} rounded-lg px-2 py-1.5" type="datetime-local" value="${esc(item.due_at || '')}"></td>
                    <td class="px-3 py-3 min-w-[150px]"><select class="queue-status w-full text-xs border border-gray-200 rounded-lg px-2 py-1.5">${Object.entries(statusLabels).map(([k,v]) => `<option value="${k}" ${item.status === k ? 'selected' : ''}>${v}</option>`).join('')}</select></td>
                    <td class="px-3 py-3 min-w-[125px]"><select class="queue-priority w-full text-xs border ${urgent ? 'border-rose-300 bg-rose-50' : 'border-gray-200'} rounded-lg px-2 py-1.5">${Object.entries(priorityLabels).map(([k,v]) => `<option value="${k}" ${item.priority === k ? 'selected' : ''}>${v}</option>`).join('')}</select></td>
                    <td class="px-3 py-3"><button type="button" class="queue-save px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs hover:bg-emerald-700" data-id="${esc(item.id)}">ذخیره</button></td>
                </tr>`;
        }).join('');
    };

    const applyGlobalLocks = (g = {}) => {
        const globallyDisabled = !g.assistant_enabled;
        if (globalNotice) {
            if (globallyDisabled) { globalNotice.classList.remove('hidden'); globalNotice.textContent = 'نجم‌هدا در سطح سایت توسط مدیر سایت غیرفعال شده است؛ تنظیمات گروهی فقط قابل مشاهده هستند.'; }
            else globalNotice.classList.add('hidden');
        }
        setDisabled(ids.enabled, globallyDisabled); setDisabled(ids.role, globallyDisabled); setDisabled(ids.meeting, globallyDisabled || !g.meeting_mode_enabled);
        setDisabled(ids.proactive, globallyDisabled || !g.allow_proactive_guidance); setDisabled(ids.allowPm, globallyDisabled || !g.allow_private_messages); setDisabled(ids.pmMode, globallyDisabled || !g.allow_private_messages);
        setDisabled(ids.propose, globallyDisabled || !g.action_executor_enabled || !g.action_propose_before_execute); setDisabled(ids.createPost, globallyDisabled || !g.action_executor_enabled || !g.action_allow_create_post);
        setDisabled(ids.createPoll, globallyDisabled || !g.action_executor_enabled || !g.action_allow_create_poll); setDisabled(ids.createComment, globallyDisabled || !g.action_executor_enabled || !g.action_allow_create_comment);
        setDisabled(ids.reactMessage, globallyDisabled || !g.action_executor_enabled || !g.action_allow_react_message); setDisabled(ids.reactPost, globallyDisabled || !g.action_executor_enabled || !g.action_allow_react_post); setDisabled(ids.reactComment, globallyDisabled || !g.action_executor_enabled || !g.action_allow_react_comment);
        const submit = settingsForm?.querySelector('button[type="submit"]');
        if (submit) { submit.disabled = globallyDisabled; submit.classList.toggle('opacity-60', globallyDisabled); submit.classList.toggle('cursor-not-allowed', globallyDisabled); }
    };

    const loadSettings = async () => {
        try {
            const res = await fetch(settingsUrl, { headers: { Accept:'application/json' } });
            const data = await res.json();
            if (!res.ok || data.status !== 'success') throw new Error(data.message || 'خطا در دریافت تنظیمات');
            const s = data.settings || {}, g = data.global || {};
            document.getElementById(ids.enabled).checked = !!s.enabled; document.getElementById(ids.role).value = s.assistant_role || 'secretary'; document.getElementById(ids.meeting).checked = !!s.meeting_mode_enabled;
            document.getElementById(ids.proactive).checked = !!s.allow_proactive_guidance; document.getElementById(ids.allowPm).checked = !!s.allow_private_messages; document.getElementById(ids.pmMode).value = s.private_message_mode || 'direct';
            document.getElementById(ids.propose).checked = !!s.action_propose_before_execute; document.getElementById(ids.createPost).checked = !!s.action_allow_create_post; document.getElementById(ids.createPoll).checked = !!s.action_allow_create_poll;
            document.getElementById(ids.createComment).checked = !!s.action_allow_create_comment; document.getElementById(ids.reactMessage).checked = !!s.action_allow_react_message; document.getElementById(ids.reactPost).checked = !!s.action_allow_react_post; document.getElementById(ids.reactComment).checked = !!s.action_allow_react_comment;
            const st = data.stats || {};
            document.getElementById('group-hoda-stat-total').textContent = st.action_items_total ?? 0; document.getElementById('group-hoda-stat-open').textContent = st.action_items_open ?? 0;
            document.getElementById('group-hoda-stat-done').textContent = st.action_items_done ?? 0; document.getElementById('group-hoda-stat-overdue').textContent = st.action_items_overdue ?? 0;
            applyGlobalLocks(g);
        } catch (error) { showMessage(error.message || 'خطا در دریافت تنظیمات.', true); }
    };

    const fetchItems = async (q = '', status = '') => {
        const res = await fetch(`${itemsUrl}?q=${encodeURIComponent(q)}&status=${encodeURIComponent(status)}`, { headers:{ Accept:'application/json' } });
        const data = await res.json();
        if (!res.ok || data.status !== 'success') throw new Error(data.message || 'خطا در دریافت صف اقدام');
        members = Array.isArray(data.members) ? data.members : members;
        return Array.isArray(data.items) ? data.items : [];
    };

    const loadItems = async () => {
        try { renderItems(await fetchItems(searchInput?.value || '', statusFilter?.value || '')); }
        catch (error) { showMessage(error.message || 'خطا در دریافت صف اقدام.', true); }
    };
    const loadAttentionStats = async () => {
        try {
            const items = await fetchItems('', '');
            document.getElementById('group-hoda-stat-unassigned').textContent = items.filter((i) => isActive(i) && !i.assigned_user_id && !i.assignee_name).length;
            document.getElementById('group-hoda-stat-urgent').textContent = items.filter((i) => isActive(i) && i.priority === 'urgent').length;
        } catch (_) {}
    };

    const updateItem = async (id, payload) => {
        const res = await fetch(`${itemsUrl}/${id}`, { method:'PUT', headers:{ 'Content-Type':'application/json', Accept:'application/json', 'X-CSRF-TOKEN':csrfToken, 'X-Requested-With':'XMLHttpRequest' }, body:JSON.stringify(payload) });
        const data = await res.json();
        if (!res.ok || data.status !== 'success') throw new Error(data.message || 'خطا در بروزرسانی مورد اقدام');
        return data;
    };

    itemsBody?.addEventListener('click', async (event) => {
        const btn = event.target.closest('.queue-save');
        if (!btn) return;
        const row = btn.closest('tr[data-item-id]');
        const id = btn.dataset.id;
        const due = row.querySelector('.queue-due')?.value || null;
        const payload = { status:row.querySelector('.queue-status')?.value, priority:row.querySelector('.queue-priority')?.value, assigned_user_id:row.querySelector('.queue-assignee')?.value || null, due_at:due || null };
        btn.disabled = true; btn.textContent = '...';
        try { await updateItem(id, payload); showMessage('مورد صف اقدام بروزرسانی شد.'); await Promise.all([loadSettings(), loadItems(), loadAttentionStats()]); }
        catch (error) { showMessage(error.message || 'خطا در بروزرسانی مورد اقدام.', true); }
        finally { btn.disabled = false; btn.textContent = 'ذخیره'; }
    });

    settingsForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const payload = { enabled:document.getElementById(ids.enabled).checked, assistant_role:document.getElementById(ids.role).value, meeting_mode_enabled:document.getElementById(ids.meeting).checked, allow_proactive_guidance:document.getElementById(ids.proactive).checked, allow_private_messages:document.getElementById(ids.allowPm).checked, private_message_mode:document.getElementById(ids.pmMode).value, action_propose_before_execute:document.getElementById(ids.propose).checked, action_allow_create_post:document.getElementById(ids.createPost).checked, action_allow_create_poll:document.getElementById(ids.createPoll).checked, action_allow_create_comment:document.getElementById(ids.createComment).checked, action_allow_react_message:document.getElementById(ids.reactMessage).checked, action_allow_react_post:document.getElementById(ids.reactPost).checked, action_allow_react_comment:document.getElementById(ids.reactComment).checked };
        try {
            const res = await fetch(settingsUrl, { method:'PUT', headers:{ 'Content-Type':'application/json', Accept:'application/json', 'X-CSRF-TOKEN':csrfToken, 'X-Requested-With':'XMLHttpRequest' }, body:JSON.stringify(payload) });
            const data = await res.json(); if (!res.ok || data.status !== 'success') throw new Error(data.message || 'خطا در ذخیره تنظیمات');
            showMessage('تنظیمات با موفقیت ذخیره شد.'); await loadSettings();
        } catch (error) { showMessage(error.message || 'خطا در ذخیره تنظیمات.', true); }
    });

    refreshBtn?.addEventListener('click', async () => { await Promise.all([loadSettings(), loadItems(), loadAttentionStats()]); showMessage('اطلاعات بروزرسانی شد.'); });
    statusFilter?.addEventListener('change', loadItems);
    searchInput?.addEventListener('input', () => { clearTimeout(window.__groupHodaSearchDebounce); window.__groupHodaSearchDebounce = setTimeout(loadItems, 350); });

    showMessage('در حال بارگذاری پنل...');
    Promise.all([loadSettings(), loadItems(), loadAttentionStats()]).then(() => showMessage('پنل آماده است.'));
})();
</script>
@endpush
