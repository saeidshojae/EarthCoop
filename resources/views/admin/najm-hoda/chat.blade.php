@extends('layouts.admin')

@section('title', 'چت با نجم‌هدا - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'چت با نجم‌هدا')
@section('page-description', 'گفت‌وگوی آزاد و وزارت هوشمند مدیرکل')

@push('styles')
<style>
    .nh-chat-shell{background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(15,23,42,.08);overflow:hidden}
    .nh-chat-head{padding:1rem 1.25rem;border-bottom:1px solid #e5e7eb;display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between}
    .nh-tabs{display:flex;gap:.5rem;flex-wrap:wrap}.nh-tab{border:1px solid #cbd5e1;background:#fff;border-radius:999px;padding:.55rem 1rem;font-weight:700;cursor:pointer}.nh-tab.active{background:#1d4ed8;color:#fff;border-color:#1d4ed8}
    .nh-pane{display:none}.nh-pane.active{display:block}
    .nh-ministry{padding:1rem 1.25rem;background:#f8fafc;border-bottom:1px solid #e5e7eb}
    .nh-ministry-actions{display:flex;gap:.5rem;flex-wrap:wrap}.nh-intent{border:1px solid #bfdbfe;background:#eff6ff;color:#1e3a8a;border-radius:10px;padding:.55rem .8rem;font-weight:700;cursor:pointer}.nh-intent:hover{background:#dbeafe}.nh-intent[disabled]{opacity:.55;cursor:wait}
    .nh-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem;margin-top:1rem}.nh-summary-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:.8rem}.nh-summary-card .v{font-size:1.5rem;font-weight:800}.nh-summary-card .k{font-size:.8rem;color:#64748b}
    .nh-messages{height:52vh;min-height:420px;overflow:auto;padding:1.25rem;background:#f8fafc}.nh-msg{display:flex;margin-bottom:1rem}.nh-msg.user{justify-content:flex-start}.nh-bubble{max-width:78%;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:.85rem 1rem;line-height:1.8;white-space:pre-wrap}.nh-msg.user .nh-bubble{background:#1d4ed8;color:#fff;border-color:#1d4ed8}.nh-meta{font-size:.75rem;opacity:.7;margin-top:.35rem}
    .nh-items{margin-top:.8rem;display:grid;gap:.6rem}.nh-item{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:.7rem .8rem}.nh-item-top{display:flex;gap:.5rem;align-items:center;justify-content:space-between}.nh-badge{font-size:.72rem;border-radius:999px;padding:.2rem .5rem;background:#e2e8f0}.nh-badge.p0{background:#fee2e2;color:#991b1b}.nh-badge.p1{background:#fef3c7;color:#92400e}.nh-badge.p2{background:#dbeafe;color:#1e40af}.nh-badge.p3{background:#e2e8f0;color:#334155}
    .nh-footer{padding:1rem 1.25rem;border-top:1px solid #e5e7eb}.nh-form{display:flex;gap:.75rem;align-items:flex-end}.nh-form textarea{flex:1;resize:none;min-height:48px;max-height:160px}.nh-status{font-size:.8rem;color:#64748b;margin-top:.5rem}.nh-ministry-note{font-size:.82rem;color:#64748b;margin-top:.65rem}.nh-link{font-size:.82rem;text-decoration:none}
    @media(max-width:768px){.nh-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.nh-messages{height:48vh;min-height:360px}.nh-bubble{max-width:92%}.nh-chat-head{align-items:flex-start}.nh-form{flex-direction:column}.nh-form textarea,.nh-form button{width:100%}}
</style>
@endpush

@section('content')
<div class="container-fluid py-3" dir="rtl">
    <div class="nh-chat-shell">
        <div class="nh-chat-head">
            <div>
                <div class="fw-bold fs-5">نجم هدا</div>
                <div class="text-muted small">همراه مدیریتی EarthCoop</div>
            </div>
            <div class="nh-tabs" role="tablist" aria-label="حالت گفتگو">
                <button type="button" class="nh-tab active" data-pane="ministry">وزارت هوشمند</button>
                <button type="button" class="nh-tab" data-pane="free-chat">گفت‌وگوی آزاد</button>
            </div>
            <select id="agentSelect" class="form-select form-select-sm" style="width:auto" aria-label="عامل نجم هدا">
                <option value="steward">خادم / Steward</option>
                <option value="guide">راهنما / Guide</option>
                <option value="pilot">Pilot</option>
                <option value="engineer">Engineer</option>
                <option value="architect">Architect</option>
            </select>
        </div>

        <div id="ministry" class="nh-pane active">
            <div class="nh-ministry">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <div class="fw-bold">وزارت هوشمند مدیرکل</div>
                        <div class="text-muted small">گزارش‌های این بخش مستقیماً از Founder Ops خوانده می‌شوند و برای پاسخ factual به مدل زبانی متکی نیستند.</div>
                    </div>
                    <a class="nh-link" href="{{ route('admin.najm-hoda.founder-ops.index') }}">باز کردن میز کار کامل مدیرکل ←</a>
                </div>
                <div class="nh-ministry-actions mt-3" id="ministryActions">
                    <button class="nh-intent" type="button" data-intent="morning_brief">☀ صبح مدیرکل</button>
                    <button class="nh-intent" type="button" data-intent="urgent_items">⚠ کارهای فوری من</button>
                    <button class="nh-intent" type="button" data-intent="pending_approvals">✓ در انتظار تأیید من</button>
                    <button class="nh-intent" type="button" data-intent="communications">✉ ارتباطات</button>
                    <button class="nh-intent" type="button" data-intent="system_health">♡ سلامت سامانه</button>
                    <button class="nh-intent" type="button" data-intent="end_of_day">☾ پایان روز مدیرکل</button>
                </div>
                <div id="summaryCards" class="nh-summary" hidden></div>
                <div class="nh-ministry-note">عملیات حساس از این دکمه‌ها اجرا نمی‌شوند؛ تأیید/رد و ارسال/انتشار همچنان از مسیرهای approval موجود انجام می‌شود.</div>
            </div>
        </div>
        <div id="free-chat" class="nh-pane"></div>

        <div class="nh-messages" id="chatMessages" aria-live="polite">
            <div class="nh-msg bot">
                <div class="nh-bubble">
                    <strong>نجم هدا — وزارت هوشمند</strong>
                    <div class="mt-1">برای شروع می‌توانید «صبح مدیرکل» را بزنید تا مهم‌ترین وضعیت فعلی EarthCoop را از داده‌های واقعی مدیریتی جمع‌بندی کنم؛ یا به تب گفت‌وگوی آزاد بروید و سؤال خودتان را بنویسید.</div>
                </div>
            </div>
        </div>

        <div class="nh-footer">
            <form id="chatForm" class="nh-form">
                @csrf
                <textarea id="messageInput" class="form-control" rows="1" maxlength="5000" placeholder="سؤال یا درخواست خود را بنویسید..."></textarea>
                <button class="btn btn-primary px-4" type="submit" id="sendButton">ارسال</button>
            </form>
            <div class="nh-status" id="chatStatus">در حالت وزارت هوشمند، دکمه‌های بالا داده‌های مدیریتی grounded را می‌خوانند؛ متن آزاد از چت عمومی نجم هدا عبور می‌کند.</div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('#chatForm input[name="_token"]')?.value || '';
    const messages = document.getElementById('chatMessages');
    const form = document.getElementById('chatForm');
    const input = document.getElementById('messageInput');
    const sendButton = document.getElementById('sendButton');
    const status = document.getElementById('chatStatus');
    const agentSelect = document.getElementById('agentSelect');
    const summaryCards = document.getElementById('summaryCards');
    const ministryUrl = @json(route('admin.najm-hoda.founder-ops.ministry.chat'));
    const freeChatUrl = @json(route('admin.najm-hoda.chat.send'));

    const labels = {
        urgent: 'فوری / مهم', founder_decisions: 'منتظر تصمیم من', prepared: 'آماده توسط نجم', information: 'صرفاً جهت اطلاع',
        pending: 'منتظر', overdue: 'عقب‌افتاده', pending_decisions: 'تصمیم ارتباطی', total: 'مجموع', health_attention_items: 'هشدار سلامت', runtime_status: 'وضعیت runtime'
    };

    function escapeHtml(value) {
        const d = document.createElement('div'); d.textContent = value == null ? '' : String(value); return d.innerHTML;
    }
    function addMessage(text, who='bot', management=null) {
        const wrap = document.createElement('div'); wrap.className = `nh-msg ${who}`;
        const bubble = document.createElement('div'); bubble.className = 'nh-bubble';
        bubble.innerHTML = `<strong>${who === 'user' ? 'شما' : 'نجم هدا'}</strong><div class="mt-1">${escapeHtml(text)}</div>`;
        if (management?.items?.length) {
            const items = document.createElement('div'); items.className = 'nh-items';
            management.items.slice(0, 12).forEach(item => {
                const priority = String(item.priority || item.risk || 'P3').toLowerCase();
                const title = item.title || item.label || item.domain || 'مورد مدیریتی';
                const kind = item.kind === 'approval' ? 'منتظر تصمیم شما' : (item.kind === 'proposal' ? 'آماده بررسی' : (item.status || item.sla_status || 'جهت اطلاع'));
                const row = document.createElement('div'); row.className = 'nh-item';
                row.innerHTML = `<div class="nh-item-top"><span>${escapeHtml(title)}</span><span class="nh-badge ${escapeHtml(priority)}">${escapeHtml(item.priority || item.risk || '')}</span></div><div class="small text-muted mt-1">${escapeHtml(kind)}${item.domain ? ' · ' + escapeHtml(item.domain) : ''}</div>`;
                items.appendChild(row);
            });
            bubble.appendChild(items);
        }
        wrap.appendChild(bubble); messages.appendChild(wrap); messages.scrollTop = messages.scrollHeight;
    }
    function renderSummary(cards) {
        if (!cards || Object.keys(cards).length === 0) { summaryCards.hidden = true; summaryCards.innerHTML = ''; return; }
        summaryCards.innerHTML = '';
        Object.entries(cards).slice(0, 4).forEach(([key, value]) => {
            const card = document.createElement('div'); card.className = 'nh-summary-card';
            card.innerHTML = `<div class="v">${escapeHtml(value)}</div><div class="k">${escapeHtml(labels[key] || key)}</div>`;
            summaryCards.appendChild(card);
        });
        summaryCards.hidden = false;
    }
    async function fetchJson(url, options) {
        const response = await fetch(url, options);
        let data = {}; try { data = await response.json(); } catch (_) {}
        if (!response.ok) throw new Error(data.message || data.error || `HTTP ${response.status}`);
        return data;
    }
    async function runIntent(button) {
        const intent = button.dataset.intent; if (!intent) return;
        document.querySelectorAll('.nh-intent').forEach(b => b.disabled = true); status.textContent = 'در حال خواندن وضعیت واقعی Founder Ops...';
        try {
            const data = await fetchJson(ministryUrl, {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf}, body:JSON.stringify({intent, hours:24})});
            addMessage(data.message || 'گزارش آماده شد.', 'bot', data.management || null);
            renderSummary(data.management?.summary_cards || {});
            status.textContent = 'گزارش از داده‌های canonical Founder Ops تهیه شد.';
        } catch (error) {
            addMessage('امکان دریافت گزارش مدیریتی وجود نداشت: ' + error.message, 'bot'); status.textContent = 'خطا در وزارت هوشمند.';
        } finally { document.querySelectorAll('.nh-intent').forEach(b => b.disabled = false); }
    }
    document.querySelectorAll('.nh-intent').forEach(button => button.addEventListener('click', () => runIntent(button)));
    document.querySelectorAll('.nh-tab').forEach(tab => tab.addEventListener('click', () => {
        document.querySelectorAll('.nh-tab').forEach(t => t.classList.remove('active')); tab.classList.add('active');
        document.querySelectorAll('.nh-pane').forEach(p => p.classList.remove('active')); document.getElementById(tab.dataset.pane)?.classList.add('active');
        status.textContent = tab.dataset.pane === 'ministry' ? 'وزارت هوشمند فعال است؛ دکمه‌های مدیریتی از Founder Ops می‌خوانند.' : 'گفت‌وگوی آزاد فعال است؛ متن شما به runtime گفت‌وگوی نجم هدا ارسال می‌شود.';
    }));
    form.addEventListener('submit', async event => {
        event.preventDefault(); const text = input.value.trim(); if (!text) return;
        addMessage(text, 'user'); input.value = ''; sendButton.disabled = true; status.textContent = 'نجم هدا در حال پاسخ است...';
        try {
            const data = await fetchJson(freeChatUrl, {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf}, body:JSON.stringify({message:text, agent:agentSelect.value})});
            addMessage(data.response || data.message || 'پاسخی دریافت نشد.', 'bot'); status.textContent = 'پاسخ دریافت شد.';
        } catch (error) {
            addMessage('خطا در گفت‌وگو: ' + error.message, 'bot'); status.textContent = 'خطا در گفت‌وگوی آزاد.';
        } finally { sendButton.disabled = false; input.focus(); }
    });
    input.addEventListener('keydown', event => { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); form.requestSubmit(); } });
})();
</script>
@endpush
