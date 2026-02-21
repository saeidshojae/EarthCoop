@extends('layouts.unified')

@section('title', 'پنل نجم‌هدا - ' . $group->name)

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8 space-y-6" style="direction: rtl;">
    <div class="bg-white border border-emerald-100 rounded-3xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-emerald-100 flex items-center justify-between">
            <div>
                <h1 class="text-lg font-bold text-gray-900">پنل نجم‌هدا برای مدیر/بازرس گروه</h1>
                <p class="text-sm text-gray-500">{{ $group->name }}</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('groups.najm-hoda.guide', $group) }}"
                    class="px-3 py-1.5 text-xs rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 transition">
                    راهنمای استفاده
                </a>
                <a href="{{ route('groups.show', $group) }}"
                    class="px-3 py-1.5 text-xs rounded-lg border border-emerald-200 text-emerald-700 hover:bg-emerald-50 transition">
                    بازگشت به گروه
                </a>
                <button id="group-hoda-refresh" type="button"
                    class="px-3 py-1.5 text-xs rounded-lg border border-emerald-200 text-emerald-700 hover:bg-emerald-50 transition">
                    بروزرسانی
                </button>
            </div>
        </div>

        <div class="p-6 space-y-4">
            <div id="group-hoda-global-notice" class="hidden text-sm rounded-xl border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3"></div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-3 text-emerald-700">کل مصوبات: <span id="group-hoda-stat-total" class="font-bold text-emerald-800">-</span></div>
                <div class="rounded-xl border border-blue-100 bg-blue-50 p-3 text-blue-700">باز: <span id="group-hoda-stat-open" class="font-bold text-blue-800">-</span></div>
                <div class="rounded-xl border border-green-100 bg-green-50 p-3 text-green-700">انجام‌شده: <span id="group-hoda-stat-done" class="font-bold text-green-800">-</span></div>
                <div class="rounded-xl border border-red-100 bg-red-50 p-3 text-red-700">معوق: <span id="group-hoda-stat-overdue" class="font-bold text-red-800">-</span></div>
            </div>

            <form id="group-hoda-settings-form" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm">
                    <span>فعال بودن دستیار</span>
                    <input type="checkbox" id="group-hoda-enabled">
                </label>

                <label class="border border-gray-200 rounded-xl px-3 py-2 text-sm">
                    <span class="block text-xs text-gray-600 mb-1">نقش رفتاری دستیار</span>
                    <select id="group-hoda-assistant-role" class="w-full text-sm border-0 p-0 focus:ring-0">
                        <option value="secretary">منشی</option>
                        <option value="advisor">مشاور</option>
                        <option value="admin">ادمین</option>
                        <option value="hybrid">ترکیبی</option>
                    </select>
                </label>

                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm">
                    <span>حالت جلسه</span>
                    <input type="checkbox" id="group-hoda-meeting-mode">
                </label>

                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm">
                    <span>راهنمایی فعال</span>
                    <input type="checkbox" id="group-hoda-proactive">
                </label>

                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm">
                    <span>ارسال پیام خصوصی</span>
                    <input type="checkbox" id="group-hoda-allow-private-messages">
                </label>

                <label class="border border-gray-200 rounded-xl px-3 py-2 text-sm">
                    <span class="block text-xs text-gray-600 mb-1">روش پیام خصوصی</span>
                    <select id="group-hoda-private-message-mode" class="w-full text-sm border-0 p-0 focus:ring-0">
                        <option value="direct">مستقیم</option>
                        <option value="request">درخواست چت</option>
                    </select>
                </label>

                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm">
                    <span>پیشنهاد قبل از اجرا</span>
                    <input type="checkbox" id="group-hoda-action-propose-before-execute">
                </label>

                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm">
                    <span>ایجاد پست</span>
                    <input type="checkbox" id="group-hoda-action-create-post">
                </label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm">
                    <span>ایجاد نظرسنجی</span>
                    <input type="checkbox" id="group-hoda-action-create-poll">
                </label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm">
                    <span>ایجاد دیدگاه</span>
                    <input type="checkbox" id="group-hoda-action-create-comment">
                </label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm">
                    <span>واکنش به پیام</span>
                    <input type="checkbox" id="group-hoda-action-react-message">
                </label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm">
                    <span>واکنش به پست</span>
                    <input type="checkbox" id="group-hoda-action-react-post">
                </label>
                <label class="border border-gray-200 rounded-xl px-3 py-2 flex items-center justify-between text-sm">
                    <span>واکنش به دیدگاه</span>
                    <input type="checkbox" id="group-hoda-action-react-comment">
                </label>

                <div class="md:col-span-3 flex items-center justify-between gap-3 mt-1">
                    <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm hover:bg-emerald-700 transition">ذخیره تنظیمات گروه</button>
                    <div id="group-hoda-message" class="text-xs text-gray-500"></div>
                </div>
            </form>

            <div class="border border-gray-200 rounded-2xl overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-center gap-2 justify-between">
                    <div class="font-semibold text-sm text-gray-700">مصوبات</div>
                    <div class="flex items-center gap-2">
                        <input id="group-hoda-items-search" type="text" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5" placeholder="جستجو...">
                        <select id="group-hoda-items-status-filter" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5">
                            <option value="">همه وضعیت‌ها</option>
                            <option value="open">باز</option>
                            <option value="in_progress">در حال انجام</option>
                            <option value="blocked">مسدود</option>
                            <option value="done">انجام شده</option>
                            <option value="cancelled">لغو شده</option>
                        </select>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600">
                            <tr>
                                <th class="text-right px-3 py-2">عنوان</th>
                                <th class="text-right px-3 py-2">مسئول</th>
                                <th class="text-right px-3 py-2">سررسید</th>
                                <th class="text-right px-3 py-2">وضعیت/اولویت</th>
                                <th class="text-right px-3 py-2">اقدام</th>
                            </tr>
                        </thead>
                        <tbody id="group-hoda-items-body">
                            <tr>
                                <td colspan="5" class="px-3 py-4 text-center text-gray-400">در حال بارگذاری...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
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

    const ids = {
        enabled: 'group-hoda-enabled',
        role: 'group-hoda-assistant-role',
        meeting: 'group-hoda-meeting-mode',
        proactive: 'group-hoda-proactive',
        allowPm: 'group-hoda-allow-private-messages',
        pmMode: 'group-hoda-private-message-mode',
        propose: 'group-hoda-action-propose-before-execute',
        createPost: 'group-hoda-action-create-post',
        createPoll: 'group-hoda-action-create-poll',
        createComment: 'group-hoda-action-create-comment',
        reactMessage: 'group-hoda-action-react-message',
        reactPost: 'group-hoda-action-react-post',
        reactComment: 'group-hoda-action-react-comment',
    };

    const settingsForm = document.getElementById('group-hoda-settings-form');
    const refreshBtn = document.getElementById('group-hoda-refresh');
    const statusFilter = document.getElementById('group-hoda-items-status-filter');
    const searchInput = document.getElementById('group-hoda-items-search');
    const itemsBody = document.getElementById('group-hoda-items-body');
    const messageBox = document.getElementById('group-hoda-message');
    const globalNotice = document.getElementById('group-hoda-global-notice');

    const showMessage = (text, isError = false) => {
        if (!messageBox) return;
        messageBox.textContent = text;
        messageBox.className = `text-xs ${isError ? 'text-red-600' : 'text-emerald-700'}`;
    };

    const setDisabled = (id, disabled) => {
        const el = document.getElementById(id);
        if (el) el.disabled = !!disabled;
    };

    const renderItems = (items) => {
        if (!itemsBody) return;
        if (!Array.isArray(items) || items.length === 0) {
            itemsBody.innerHTML = '<tr><td colspan="5" class="px-3 py-4 text-center text-gray-400">موردی یافت نشد.</td></tr>';
            return;
        }

        const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
        const statusLabels = { open: 'باز', in_progress: 'در حال انجام', blocked: 'مسدود', done: 'انجام شده', cancelled: 'لغو شده' };
        const priorityLabels = { low: 'کم', medium: 'متوسط', high: 'زیاد', urgent: 'فوری' };

        itemsBody.innerHTML = items.map((item) => `
            <tr class="border-b border-gray-100">
                <td class="px-3 py-2">${esc(item.title || '-')}</td>
                <td class="px-3 py-2">${esc(item.assigned_user?.name || item.assignee_name || '-')}</td>
                <td class="px-3 py-2">${esc(item.due_at || '-')}</td>
                <td class="px-3 py-2">${esc(statusLabels[item.status] || item.status || '-')} / ${esc(priorityLabels[item.priority] || item.priority || '-')}</td>
                <td class="px-3 py-2 text-gray-500">-</td>
            </tr>
        `).join('');
    };

    const applyGlobalLocks = (g = {}) => {
        const globallyDisabled = !g.assistant_enabled;
        if (globalNotice) {
            if (globallyDisabled) {
                globalNotice.classList.remove('hidden');
                globalNotice.textContent = 'نجم‌هدا در سطح سایت توسط مدیر سایت غیرفعال شده است؛ تنظیمات گروهی فقط قابل مشاهده هستند.';
            } else {
                globalNotice.classList.add('hidden');
            }
        }

        setDisabled(ids.enabled, globallyDisabled);
        setDisabled(ids.role, globallyDisabled);
        setDisabled(ids.meeting, globallyDisabled || !g.meeting_mode_enabled);
        setDisabled(ids.proactive, globallyDisabled || !g.allow_proactive_guidance);
        setDisabled(ids.allowPm, globallyDisabled || !g.allow_private_messages);
        setDisabled(ids.pmMode, globallyDisabled || !g.allow_private_messages);
        setDisabled(ids.propose, globallyDisabled || !g.action_executor_enabled || !g.action_propose_before_execute);
        setDisabled(ids.createPost, globallyDisabled || !g.action_executor_enabled || !g.action_allow_create_post);
        setDisabled(ids.createPoll, globallyDisabled || !g.action_executor_enabled || !g.action_allow_create_poll);
        setDisabled(ids.createComment, globallyDisabled || !g.action_executor_enabled || !g.action_allow_create_comment);
        setDisabled(ids.reactMessage, globallyDisabled || !g.action_executor_enabled || !g.action_allow_react_message);
        setDisabled(ids.reactPost, globallyDisabled || !g.action_executor_enabled || !g.action_allow_react_post);
        setDisabled(ids.reactComment, globallyDisabled || !g.action_executor_enabled || !g.action_allow_react_comment);

        if (settingsForm) {
            const submit = settingsForm.querySelector('button[type="submit"]');
            if (submit) {
                submit.disabled = globallyDisabled;
                submit.classList.toggle('opacity-60', globallyDisabled);
                submit.classList.toggle('cursor-not-allowed', globallyDisabled);
            }
        }
    };

    const loadSettings = async () => {
        try {
            const res = await fetch(settingsUrl, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            if (!res.ok || data.status !== 'success') throw new Error(data.message || 'خطا در دریافت تنظیمات');

            const s = data.settings || {};
            const g = data.global || {};
            document.getElementById(ids.enabled).checked = !!s.enabled;
            document.getElementById(ids.role).value = s.assistant_role || 'secretary';
            document.getElementById(ids.meeting).checked = !!s.meeting_mode_enabled;
            document.getElementById(ids.proactive).checked = !!s.allow_proactive_guidance;
            document.getElementById(ids.allowPm).checked = !!s.allow_private_messages;
            document.getElementById(ids.pmMode).value = s.private_message_mode || 'direct';
            document.getElementById(ids.propose).checked = !!s.action_propose_before_execute;
            document.getElementById(ids.createPost).checked = !!s.action_allow_create_post;
            document.getElementById(ids.createPoll).checked = !!s.action_allow_create_poll;
            document.getElementById(ids.createComment).checked = !!s.action_allow_create_comment;
            document.getElementById(ids.reactMessage).checked = !!s.action_allow_react_message;
            document.getElementById(ids.reactPost).checked = !!s.action_allow_react_post;
            document.getElementById(ids.reactComment).checked = !!s.action_allow_react_comment;

            const st = data.stats || {};
            document.getElementById('group-hoda-stat-total').textContent = st.action_items_total ?? 0;
            document.getElementById('group-hoda-stat-open').textContent = st.action_items_open ?? 0;
            document.getElementById('group-hoda-stat-done').textContent = st.action_items_done ?? 0;
            document.getElementById('group-hoda-stat-overdue').textContent = st.action_items_overdue ?? 0;

            applyGlobalLocks(g);
        } catch (error) {
            showMessage(error.message || 'خطا در دریافت تنظیمات.', true);
        }
    };

    const loadItems = async () => {
        try {
            const q = encodeURIComponent(searchInput?.value || '');
            const status = encodeURIComponent(statusFilter?.value || '');
            const res = await fetch(`${itemsUrl}?q=${q}&status=${status}`, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            if (!res.ok || data.status !== 'success') throw new Error(data.message || 'خطا در دریافت مصوبات');
            renderItems(data.items || []);
        } catch (error) {
            showMessage(error.message || 'خطا در دریافت مصوبات.', true);
        }
    };

    settingsForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const payload = {
            enabled: document.getElementById(ids.enabled).checked,
            assistant_role: document.getElementById(ids.role).value,
            meeting_mode_enabled: document.getElementById(ids.meeting).checked,
            allow_proactive_guidance: document.getElementById(ids.proactive).checked,
            allow_private_messages: document.getElementById(ids.allowPm).checked,
            private_message_mode: document.getElementById(ids.pmMode).value,
            action_propose_before_execute: document.getElementById(ids.propose).checked,
            action_allow_create_post: document.getElementById(ids.createPost).checked,
            action_allow_create_poll: document.getElementById(ids.createPoll).checked,
            action_allow_create_comment: document.getElementById(ids.createComment).checked,
            action_allow_react_message: document.getElementById(ids.reactMessage).checked,
            action_allow_react_post: document.getElementById(ids.reactPost).checked,
            action_allow_react_comment: document.getElementById(ids.reactComment).checked,
        };

        try {
            const res = await fetch(settingsUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (!res.ok || data.status !== 'success') throw new Error(data.message || 'خطا در ذخیره تنظیمات');
            showMessage('تنظیمات با موفقیت ذخیره شد.');
            await loadSettings();
        } catch (error) {
            showMessage(error.message || 'خطا در ذخیره تنظیمات.', true);
        }
    });

    refreshBtn?.addEventListener('click', async () => {
        await loadSettings();
        await loadItems();
        showMessage('اطلاعات بروزرسانی شد.');
    });

    statusFilter?.addEventListener('change', loadItems);
    searchInput?.addEventListener('input', () => {
        clearTimeout(window.__groupHodaSearchDebounce);
        window.__groupHodaSearchDebounce = setTimeout(loadItems, 350);
    });

    showMessage('در حال بارگذاری تنظیمات...');
    loadSettings();
    loadItems();
})();
</script>
@endpush
