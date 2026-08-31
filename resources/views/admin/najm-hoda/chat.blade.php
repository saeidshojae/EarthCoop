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
.nh-ministry{padding:1rem 1.25rem;background:#f8fafc;border-bottom:1px solid #e5e7eb}.nh-toolbar{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}.nh-window{width:auto;min-width:120px}
.nh-ministry-group{margin-top:1rem}.nh-ministry-group-title{font-size:.78rem;color:#64748b;font-weight:700;margin-bottom:.45rem}.nh-ministry-actions{display:flex;gap:.5rem;flex-wrap:wrap}
.nh-intent{border:1px solid #bfdbfe;background:#eff6ff;color:#1e3a8a;border-radius:10px;padding:.55rem .8rem;font-weight:700;cursor:pointer}.nh-intent.domain{background:#fff;border-color:#dbe3ee;color:#334155}.nh-intent:hover{background:#dbeafe}.nh-intent.domain:hover{background:#f1f5f9}.nh-intent[disabled]{opacity:.55;cursor:wait}
.nh-command-strip,.nh-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem;margin-top:1rem}.nh-command-card,.nh-summary-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:.8rem}.nh-command-card .v,.nh-summary-card .v{font-size:1.5rem;font-weight:800}.nh-command-card .k,.nh-summary-card .k{font-size:.8rem;color:#64748b}.nh-command-card.urgent{border-color:#fecaca}.nh-command-card.decision{border-color:#fde68a}.nh-command-card.prepared{border-color:#bfdbfe}
.nh-report{margin:1rem 1.25rem;border:1px solid #dbe3ee;border-radius:16px;background:#fff;overflow:hidden}.nh-report-head{padding:1rem 1.1rem;border-bottom:1px solid #eef2f7;display:flex;justify-content:space-between;gap:.75rem;align-items:flex-start}.nh-report-title{font-weight:800;font-size:1rem}.nh-report-meta{font-size:.78rem;color:#64748b;margin-top:.2rem}.nh-assessment{font-size:.75rem;border-radius:999px;padding:.3rem .65rem;background:#ecfdf5;color:#166534;white-space:nowrap}.nh-assessment.attention{background:#fff7ed;color:#9a3412}.nh-report-body{padding:1rem 1.1rem}.nh-report-message{line-height:1.95}.nh-action-line{margin-top:.7rem;padding:.7rem .8rem;background:#f8fafc;border-radius:10px;font-size:.88rem}.nh-detail-summary-wrap{margin-top:.9rem}.nh-detail-summary-title{font-size:.76rem;color:#64748b;font-weight:700;margin-bottom:.35rem}
.nh-agency{margin-top:1rem;border:1px solid #dbe3ee;border-radius:14px;padding:.9rem;background:#f8fafc}.nh-agency-title{font-weight:800}.nh-agency-summary{font-size:.82rem;color:#64748b;margin-top:.3rem;line-height:1.8}.nh-agency-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem;margin-top:.8rem}.nh-agency-lane{background:#fff;border:1px solid #e2e8f0;border-radius:11px;padding:.75rem}.nh-agency-lane-title{font-size:.78rem;font-weight:800;margin-bottom:.45rem}.nh-agency-list{display:grid;gap:.35rem}.nh-agency-entry{font-size:.78rem;line-height:1.65;padding:.4rem .5rem;background:#f8fafc;border-radius:8px}.nh-agency-empty{font-size:.76rem;color:#64748b}
.nh-items{margin-top:.8rem;display:grid;gap:.7rem}.nh-item{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:.8rem}.nh-item-top{display:flex;gap:.5rem;align-items:flex-start;justify-content:space-between}.nh-item-meta{font-size:.78rem;color:#64748b;margin-top:.3rem}.nh-item-actions{display:flex;flex-wrap:wrap;gap:.45rem;margin-top:.7rem;align-items:center}.nh-action-form{display:inline-flex;margin:0}.nh-badge{font-size:.72rem;border-radius:999px;padding:.2rem .5rem;background:#e2e8f0;white-space:nowrap}.nh-badge.p0{background:#fee2e2;color:#991b1b}.nh-badge.p1{background:#fef3c7;color:#92400e}.nh-badge.p2{background:#dbeafe;color:#1e40af}.nh-badge.p3{background:#e2e8f0;color:#334155}
.nh-free-chat-intro{margin:1rem 1.25rem;padding:1rem 1.1rem;border:1px solid #dbe3ee;border-radius:14px;background:#f8fafc;line-height:1.9}.nh-conversation-label{padding:1rem 1.25rem .45rem;color:#64748b;font-size:.78rem;font-weight:700;border-top:1px solid #eef2f7}.nh-messages{height:34vh;min-height:260px;overflow:auto;padding:1.25rem;background:#f8fafc}.nh-msg{display:flex;margin-bottom:1rem}.nh-msg.user{justify-content:flex-start}.nh-bubble{max-width:84%;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:.85rem 1rem;line-height:1.8;white-space:pre-wrap}.nh-msg.user .nh-bubble{background:#1d4ed8;color:#fff;border-color:#1d4ed8}
.nh-footer{padding:1rem 1.25rem;border-top:1px solid #e5e7eb}.nh-form{display:flex;gap:.75rem;align-items:flex-end}.nh-form textarea{flex:1;resize:none;min-height:48px;max-height:160px}.nh-send{background:#1d4ed8!important;color:#fff!important;border-color:#1d4ed8!important;min-width:90px}.nh-status{font-size:.8rem;color:#64748b;margin-top:.5rem}.nh-ministry-note,.nh-link{font-size:.82rem}.nh-ministry-note{color:#64748b;margin-top:.65rem}.nh-link{text-decoration:none}.nh-session{margin:1rem 1.25rem 0}.nh-empty{font-size:.82rem;color:#64748b;margin-top:.5rem}
@media(max-width:768px){.nh-command-strip,.nh-summary,.nh-agency-grid{grid-template-columns:repeat(1,minmax(0,1fr))}.nh-report-head{flex-direction:column}.nh-messages{height:32vh;min-height:240px}.nh-bubble{max-width:96%}.nh-chat-head{align-items:flex-start}.nh-form{flex-direction:column}.nh-form textarea,.nh-form button{width:100%}.nh-window{width:100%}}
</style>
@endpush

@section('content')
<div class="container-fluid py-3" dir="rtl">
    <div class="nh-chat-shell">
        @if(session('success'))<div class="alert alert-success nh-session mb-0">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger nh-session mb-0">{{ session('error') }}</div>@endif

        <div class="nh-chat-head">
            <div><div class="fw-bold fs-5">نجم هدا</div><div class="text-muted small">همراه مدیریتی EarthCoop</div></div>
            <div class="nh-tabs" role="tablist" aria-label="حالت گفت‌وگو">
                <button type="button" class="nh-tab active" data-pane="ministry" aria-selected="true">وزارت هوشمند</button>
                <button type="button" class="nh-tab" data-pane="free-chat" aria-selected="false">گفت‌وگوی آزاد</button>
            </div>
            <select id="agentSelect" class="form-select form-select-sm" style="width:auto">
                <option value="steward">خادم / Steward</option><option value="guide">راهنما / Guide</option><option value="pilot">Pilot</option><option value="engineer">Engineer</option><option value="architect">Architect</option>
            </select>
        </div>

        <div id="ministry" class="nh-pane active">
            <div class="nh-ministry">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div><div class="fw-bold">وزارت هوشمند مدیرکل</div><div class="text-muted small">گزارش استثنامحور و تصمیم‌پذیر روی Founder Ops واقعی؛ بدون مسیر اجرایی موازی.</div></div>
                    <div class="nh-toolbar">
                        <select id="windowSelect" class="form-select form-select-sm nh-window"><option value="6">۶ ساعت</option><option value="24" selected>۲۴ ساعت</option><option value="72">۳ روز</option><option value="168">۷ روز</option></select>
                        <a class="nh-link" href="{{ route('admin.najm-hoda.founder-ops.index') }}">میز کار کامل مدیرکل ←</a>
                    </div>
                </div>
                <div class="nh-command-strip">
                    <div class="nh-command-card urgent"><div class="v" data-global="urgent">—</div><div class="k">فوری / مهم</div></div>
                    <div class="nh-command-card decision"><div class="v" data-global="founder_decisions">—</div><div class="k">منتظر تصمیم من</div></div>
                    <div class="nh-command-card prepared"><div class="v" data-global="prepared">—</div><div class="k">آماده توسط نجم</div></div>
                    <div class="nh-command-card"><div class="v" data-global="information">—</div><div class="k">صرفاً جهت اطلاع</div></div>
                </div>
                <div class="nh-ministry-group"><div class="nh-ministry-group-title">فرمان‌های روزانه</div><div class="nh-ministry-actions">
                    <button class="nh-intent" data-intent="morning_brief">☀ صبح مدیرکل</button><button class="nh-intent" data-intent="urgent_items">⚠ کارهای فوری من</button><button class="nh-intent" data-intent="pending_approvals">✓ در انتظار تأیید من</button><button class="nh-intent" data-intent="communications">✉ ارتباطات</button><button class="nh-intent" data-intent="system_health">♡ سلامت سامانه</button><button class="nh-intent" data-intent="end_of_day">☾ پایان روز مدیرکل</button>
                </div></div>
                <div class="nh-ministry-group"><div class="nh-ministry-group-title">حوزه‌های مدیریتی</div><div class="nh-ministry-actions">
                    <button class="nh-intent domain" data-intent="users_registration">👤 کاربران و ثبت‌نام</button><button class="nh-intent domain" data-intent="reference_data">⌖ مکان / صنف / تخصص</button><button class="nh-intent domain" data-intent="support_moderation">☏ پشتیبانی و شکایات</button><button class="nh-intent domain" data-intent="groups">◉ گروه‌ها</button><button class="nh-intent domain" data-intent="governance">⚖ انتخابات و حکمرانی</button><button class="nh-intent domain" data-intent="najm_bahar">◈ نجم بهار</button><button class="nh-intent domain" data-intent="stock">▦ سهام و تأمین مالی</button><button class="nh-intent domain" data-intent="secretariat">▤ دبیرخانه</button><button class="nh-intent domain" data-intent="authority">⌘ اختیارها و واگذاری‌ها</button>
                </div></div>
                <div class="nh-ministry-note">دکمه‌های بالا فقط گزارش جاری را عوض می‌کنند. فرمان حساس از متن آزاد استنباط نمی‌شود؛ اقدام فقط از کارت صریح و lifecycle رسمی Founder Ops انجام می‌شود.</div>
            </div>

            <div id="executiveReport" class="nh-report" aria-live="polite">
                <div class="nh-report-head"><div><div id="reportTitle" class="nh-report-title">صبح مدیرکل</div><div id="reportMeta" class="nh-report-meta">در حال آماده‌سازی گزارش…</div></div><span id="reportAssessment" class="nh-assessment">در حال بررسی</span></div>
                <div class="nh-report-body">
                    <div id="reportMessage" class="nh-report-message">در حال خواندن وضعیت واقعی Founder Ops…</div>
                    <div id="reportAction" class="nh-action-line">اقدام پیشنهادی پس از دریافت گزارش نمایش داده می‌شود.</div>
                    <div id="agencyWrap" class="nh-agency">
                        <div class="nh-agency-title">توان اجرایی نجم هدا در این گزارش</div>
                        <div id="agencySummary" class="nh-agency-summary">در حال تطبیق اختیار، واگذاری و اتصال واقعی…</div>
                        <div class="nh-agency-grid">
                            <div class="nh-agency-lane"><div class="nh-agency-lane-title">خودم می‌توانم انجام دهم</div><div class="nh-agency-list" data-agency-lane="may_do_now"><div class="nh-agency-empty">موردی در این دسته ثبت نشده است.</div></div></div>
                            <div class="nh-agency-lane"><div class="nh-agency-lane-title">می‌توانم آماده کنم</div><div class="nh-agency-list" data-agency-lane="may_prepare"><div class="nh-agency-empty">موردی در این دسته ثبت نشده است.</div></div></div>
                            <div class="nh-agency-lane"><div class="nh-agency-lane-title">تصمیم شما لازم است</div><div class="nh-agency-list" data-agency-lane="needs_founder_decision"><div class="nh-agency-empty">موردی در این دسته ثبت نشده است.</div></div></div>
                            <div class="nh-agency-lane"><div class="nh-agency-lane-title">فعلاً مسدود است</div><div class="nh-agency-list" data-agency-lane="blocked"><div class="nh-agency-empty">موردی در این دسته ثبت نشده است.</div></div></div>
                        </div>
                    </div>
                    <div id="detailSummaryWrap" class="nh-detail-summary-wrap" hidden><div class="nh-detail-summary-title">شاخص‌های همین گزارش</div><div id="detailSummaryCards" class="nh-summary"></div></div>
                    <div id="reportItems" class="nh-items"></div>
                </div>
            </div>
        </div>

        <div id="free-chat" class="nh-pane"><div class="nh-free-chat-intro"><strong>گفت‌وگوی آزاد با نجم هدا</strong><div class="text-muted small mt-1">این حالت گزارش مدیریتی را تغییر نمی‌دهد. برای پرسش عمومی، تحلیل، راهنمایی یا گفت‌وگو با agent انتخابی استفاده کنید.</div></div></div>

        <div id="conversationLabel" class="nh-conversation-label">گفت‌وگو با وزیر — برای پرسیدن «چرا؟»، درخواست تحلیل بیشتر یا سؤال مدیریتی</div>
        <div class="nh-messages" id="chatMessages"><div class="nh-msg bot"><div class="nh-bubble"><strong>نجم هدا</strong><div class="mt-1">گزارش جاری در پنل وزارت نمایش داده می‌شود. اگر درباره آن سؤال داری، همین‌جا بپرس.</div></div></div></div>

        <div class="nh-footer">
            <form id="chatForm" class="nh-form">@csrf<textarea id="messageInput" class="form-control" rows="1" maxlength="5000" placeholder="مثلاً: چرا این مورد را مهم تشخیص دادی؟"></textarea><button class="btn nh-send px-4" type="submit" id="sendButton">ارسال</button></form>
            <div class="nh-status" id="chatStatus">وزارت هوشمند فعال است.</div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(()=>{
    const csrf=document.querySelector('meta[name="csrf-token"]')?.content||document.querySelector('#chatForm input[name="_token"]')?.value||'';
    const messages=document.getElementById('chatMessages'),form=document.getElementById('chatForm'),input=document.getElementById('messageInput'),sendButton=document.getElementById('sendButton'),status=document.getElementById('chatStatus'),agentSelect=document.getElementById('agentSelect'),windowSelect=document.getElementById('windowSelect'),conversationLabel=document.getElementById('conversationLabel'),detailSummaryWrap=document.getElementById('detailSummaryWrap'),detailSummaryCards=document.getElementById('detailSummaryCards'),reportTitle=document.getElementById('reportTitle'),reportMeta=document.getElementById('reportMeta'),reportAssessment=document.getElementById('reportAssessment'),reportMessage=document.getElementById('reportMessage'),reportAction=document.getElementById('reportAction'),reportItems=document.getElementById('reportItems'),agencySummary=document.getElementById('agencySummary');
    const ministryUrl=@json(route('admin.najm-hoda.founder-ops.ministry.chat')),freeChatUrl=@json(route('admin.najm-hoda.chat.send'));
    let activePane='ministry',lastIntent='morning_brief';
    const labels={urgent:'فوری / مهم',founder_decisions:'منتظر تصمیم من',prepared:'آماده توسط نجم',information:'صرفاً جهت اطلاع',pending:'منتظر',overdue:'عقب‌افتاده',pending_decisions:'تصمیم ارتباطی',total:'مجموع',health_attention_items:'هشدار سلامت',runtime_status:'وضعیت runtime'};
    const domainLabels={users:'کاربران',invitations:'دعوت‌ها',support:'پشتیبانی',reports_moderation:'نظارت',moderation:'نظارت',reference_data:'داده پایه',locations:'مکان‌ها',approvals:'داده پایه',groups:'گروه‌ها',governance:'انتخابات',najm_bahar:'نجم بهار',financial_risk:'سلامت مالی',stock:'سهام',secretariat:'دبیرخانه',email:'ایمیل',blog:'محتوا',content:'محتوا',notifications:'اطلاعیه',runtime_health:'سلامت نجم',founder_approvals:'تصمیم مدیرکل',authority:'اختیارها'};
    function esc(value){const div=document.createElement('div');div.textContent=value==null?'':String(value);return div.innerHTML}
    function btnClass(style){return style==='success'?'btn-success':style==='outline-danger'?'btn-outline-danger':style==='primary'?'btn-primary':'btn-outline-primary'}
    function actionForm(action){const f=document.createElement('form');f.method='POST';f.action=action.url;f.className='nh-action-form';f.dataset.confirm=action.confirm?'1':'0';f.innerHTML=`<input type="hidden" name="_token" value="${esc(csrf)}">${action.decision?`<input type="hidden" name="decision" value="${esc(action.decision)}">`:''}<button type="submit" class="btn btn-sm ${btnClass(action.style)}">${esc(action.label||'اقدام')}</button>`;return f}
    function addConversation(text,who='bot'){const wrap=document.createElement('div');wrap.className=`nh-msg ${who}`;wrap.innerHTML=`<div class="nh-bubble"><strong>${who==='user'?'شما':'نجم هدا'}</strong><div class="mt-1">${esc(text)}</div></div>`;messages.appendChild(wrap);messages.scrollTop=messages.scrollHeight}
    function renderGlobal(cards){if(!cards)return;['urgent','founder_decisions','prepared','information'].forEach(key=>{const el=document.querySelector(`[data-global="${key}"]`);if(el&&Object.prototype.hasOwnProperty.call(cards,key))el.textContent=cards[key]})}
    function normalize(key,value){return value&&typeof value==='object'&&!Array.isArray(value)?{label:value.label||labels[key]||key,value:value.value??'—'}:{label:labels[key]||key,value:value}}
    function renderDetail(cards,intent){detailSummaryCards.innerHTML='';if(!cards||Object.keys(cards).length===0||['morning_brief','end_of_day'].includes(intent)){detailSummaryWrap.hidden=true;return}Object.entries(cards).slice(0,4).forEach(([key,value])=>{const item=normalize(key,value),card=document.createElement('div');card.className='nh-summary-card';card.innerHTML=`<div class="v">${esc(item.value)}</div><div class="k">${esc(item.label)}</div>`;detailSummaryCards.appendChild(card)});detailSummaryWrap.hidden=false}
    function agencyEntry(item){const domain=domainLabels[item.domain]||item.domain||'حوزه',action=item.title||item.action||'اقدام',reason=item.reason?` · ${item.reason}`:'';return `${domain} · ${action}${reason}`}
    function renderAgency(agency){agencySummary.textContent=agency?.summary||'داده توان اجرایی برای این گزارش موجود نیست.';['may_do_now','may_prepare','needs_founder_decision','blocked'].forEach(key=>{const lane=document.querySelector(`[data-agency-lane="${key}"]`);if(!lane)return;lane.innerHTML='';const entries=Array.isArray(agency?.[key])?agency[key].slice(0,6):[];if(!entries.length){lane.innerHTML='<div class="nh-agency-empty">موردی در این دسته ثبت نشده است.</div>';return}entries.forEach(item=>{const row=document.createElement('div');row.className='nh-agency-entry';row.textContent=agencyEntry(item);lane.appendChild(row)})})}
    function renderItems(items){reportItems.innerHTML='';if(!items?.length){reportItems.innerHTML='<div class="nh-empty">موردی برای رسیدگی در این گزارش وجود ندارد.</div>';return}items.slice(0,20).forEach(item=>{const priority=String(item.priority||item.risk||'P3').toLowerCase(),title=item.title||item.label||domainLabels[item.domain]||'مورد مدیریتی',kind=item.kind==='approval'?'منتظر تصمیم شما':item.kind==='proposal'?'آماده بررسی':(item.status||item.sla_status||'جهت اطلاع'),row=document.createElement('div');row.className='nh-item';row.innerHTML=`<div class="nh-item-top"><span>${esc(title)}</span><span class="nh-badge ${esc(priority)}">${esc(item.priority||item.risk||'')}</span></div><div class="nh-item-meta">${esc(kind)}${item.domain?' · '+esc(domainLabels[item.domain]||item.domain):''}${item.entity_id?' · #'+esc(item.entity_id):''}</div>`;const actions=document.createElement('div');actions.className='nh-item-actions';if(item.ui?.workbench_url){const link=document.createElement('a');link.href=item.ui.workbench_url;link.className='btn btn-sm btn-outline-secondary';link.textContent='رسیدگی / جزئیات';actions.appendChild(link)}(item.ui?.actions||[]).forEach(action=>actions.appendChild(actionForm(action)));if(actions.children.length)row.appendChild(actions);reportItems.appendChild(row)})}
    function renderReport(data){const management=data.management||{},executive=management.executive||{};lastIntent=management.intent||lastIntent;reportTitle.textContent=executive.title||'گزارش مدیریتی';const scope=executive.scope==='global'?'دید سراسری':'همین حوزه';reportMeta.textContent=`بازه گزارش: ${executive.window_hours||windowSelect.value||24} ساعت · ${scope} · ${management.items?.length||0} مورد کارت‌شده`;reportAssessment.textContent=executive.assessment||'بررسی شد';const attention=!!(executive.action_required||executive.needs_founder_action);reportAssessment.classList.toggle('attention',attention);reportMessage.textContent=data.message||'گزارش آماده شد.';reportAction.textContent='اقدام شما: '+(executive.action_text||'فعلاً اقدامی لازم نیست.');renderAgency(executive.agency||{});renderDetail(management.summary_cards||{},lastIntent);renderItems(management.items||[]);renderGlobal(management.global_summary_cards||(['morning_brief','end_of_day'].includes(lastIntent)?management.summary_cards:null))}
    async function requestJson(url,payload){const response=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(payload)});let data={};try{data=await response.json()}catch(_){}if(!response.ok){const error=new Error(data.message||data.error||`HTTP ${response.status}`);error.payload=data;throw error}return data}
    async function runMinistry(payload,{conversation=false}={}){document.querySelectorAll('.nh-intent').forEach(button=>button.disabled=true);status.textContent='در حال خواندن وضعیت واقعی Founder Ops...';try{const data=await requestJson(ministryUrl,{...payload,hours:Number(windowSelect.value||24)});renderReport(data);if(conversation)addConversation(data.message||'گزارش به‌روزرسانی شد.','bot');status.textContent='گزارش جاری از داده‌های canonical Founder Ops به‌روزرسانی شد.';return data}catch(error){if(error.payload?.management?.meta?.reason==='unclassified_management_question'){if(conversation)addConversation(error.payload.message,'bot');status.textContent='این متن به intent خواندنی امن نگاشت نشد؛ هیچ اقدامی اجرا نشد.'}else{if(conversation)addConversation('امکان دریافت گزارش مدیریتی وجود نداشت: '+error.message,'bot');status.textContent='خطا در وزارت هوشمند.'}return null}finally{document.querySelectorAll('.nh-intent').forEach(button=>button.disabled=false)}}
    function switchPane(pane){activePane=pane;document.querySelectorAll('.nh-pane').forEach(el=>el.classList.toggle('active',el.id===pane));document.querySelectorAll('.nh-tab').forEach(tab=>{const selected=tab.dataset.pane===pane;tab.classList.toggle('active',selected);tab.setAttribute('aria-selected',selected?'true':'false')});const ministry=pane==='ministry';agentSelect.style.visibility=ministry?'hidden':'visible';input.placeholder=ministry?'مثلاً: چرا این مورد را مهم تشخیص دادی؟':'سؤال خود را از نجم هدا بپرسید...';conversationLabel.textContent=ministry?'گفت‌وگو با وزیر — برای پرسیدن «چرا؟»، درخواست تحلیل بیشتر یا سؤال مدیریتی':'گفت‌وگوی آزاد — مستقل از پنل گزارش مدیریتی';status.textContent=ministry?'وزارت هوشمند فعال است.':'گفت‌وگوی آزاد فعال است.'}
    document.querySelectorAll('.nh-intent').forEach(button=>button.addEventListener('click',()=>runMinistry({intent:button.dataset.intent})));
    document.querySelectorAll('.nh-tab').forEach(tab=>tab.addEventListener('click',()=>switchPane(tab.dataset.pane)));
    agentSelect.style.visibility='hidden';windowSelect.addEventListener('change',()=>runMinistry({intent:lastIntent}));
    form.addEventListener('submit',async event=>{event.preventDefault();const text=input.value.trim();if(!text)return;addConversation(text,'user');input.value='';sendButton.disabled=true;try{if(activePane==='ministry')await runMinistry({message:text},{conversation:true});else{const data=await requestJson(freeChatUrl,{message:text,agent:agentSelect.value});addConversation(data.response||data.message||'پاسخی دریافت نشد.','bot')}}catch(error){addConversation('خطا در گفت‌وگو: '+error.message,'bot')}finally{sendButton.disabled=false;input.focus()}});
    input.addEventListener('keydown',event=>{if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();form.requestSubmit()}});
    document.addEventListener('submit',event=>{const target=event.target instanceof Element?event.target:null;const action=target?.closest('.nh-action-form');if(action&&action.dataset.confirm==='1'&&!window.confirm('این اقدام از lifecycle رسمی Founder Ops اجرا می‌شود. ادامه می‌دهید؟'))event.preventDefault()});
    switchPane('ministry');runMinistry({intent:'morning_brief'});
})();
</script>
@endpush
