@extends('layouts.admin')

@section('title', 'چت با نجم‌هدا - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'چت با نجم‌هدا')
@section('page-description', 'گفت‌وگوی آزاد و وزارت هوشمند مدیرکل')

@push('styles')
<style>
    .nh-chat-shell{background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(15,23,42,.08);overflow:hidden}.nh-chat-head{padding:1rem 1.25rem;border-bottom:1px solid #e5e7eb;display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between}.nh-tabs{display:flex;gap:.5rem;flex-wrap:wrap}.nh-tab{border:1px solid #cbd5e1;background:#fff;border-radius:999px;padding:.55rem 1rem;font-weight:700;cursor:pointer}.nh-tab.active{background:#1d4ed8;color:#fff;border-color:#1d4ed8}.nh-pane{display:none}.nh-pane.active{display:block}.nh-ministry{padding:1rem 1.25rem;background:#f8fafc;border-bottom:1px solid #e5e7eb}.nh-toolbar{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}.nh-window{width:auto;min-width:120px}.nh-ministry-group{margin-top:1rem}.nh-ministry-group-title{font-size:.78rem;color:#64748b;font-weight:700;margin-bottom:.45rem}.nh-ministry-actions{display:flex;gap:.5rem;flex-wrap:wrap}.nh-intent{border:1px solid #bfdbfe;background:#eff6ff;color:#1e3a8a;border-radius:10px;padding:.55rem .8rem;font-weight:700;cursor:pointer}.nh-intent.domain{background:#fff;border-color:#dbe3ee;color:#334155}.nh-intent:hover{background:#dbeafe}.nh-intent.domain:hover{background:#f1f5f9}.nh-intent[disabled]{opacity:.55;cursor:wait}.nh-command-strip,.nh-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem;margin-top:1rem}.nh-command-card,.nh-summary-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:.8rem}.nh-command-card .v,.nh-summary-card .v{font-size:1.5rem;font-weight:800}.nh-command-card .k,.nh-summary-card .k{font-size:.8rem;color:#64748b}.nh-command-card.urgent{border-color:#fecaca}.nh-command-card.decision{border-color:#fde68a}.nh-command-card.prepared{border-color:#bfdbfe}.nh-detail-summary-wrap{margin-top:.9rem}.nh-detail-summary-title{font-size:.76rem;color:#64748b;font-weight:700;margin-bottom:.35rem}.nh-messages{height:52vh;min-height:420px;overflow:auto;padding:1.25rem;background:#f8fafc}.nh-msg{display:flex;margin-bottom:1rem}.nh-msg.user{justify-content:flex-start}.nh-bubble{max-width:84%;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:.85rem 1rem;line-height:1.8;white-space:pre-wrap}.nh-msg.user .nh-bubble{background:#1d4ed8;color:#fff;border-color:#1d4ed8}.nh-items{margin-top:.8rem;display:grid;gap:.7rem}.nh-item{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:.8rem}.nh-item-top{display:flex;gap:.5rem;align-items:flex-start;justify-content:space-between}.nh-item-meta{font-size:.78rem;color:#64748b;margin-top:.3rem}.nh-item-actions{display:flex;flex-wrap:wrap;gap:.45rem;margin-top:.7rem;align-items:center}.nh-action-form{display:inline-flex;margin:0}.nh-badge{font-size:.72rem;border-radius:999px;padding:.2rem .5rem;background:#e2e8f0;white-space:nowrap}.nh-badge.p0{background:#fee2e2;color:#991b1b}.nh-badge.p1{background:#fef3c7;color:#92400e}.nh-badge.p2{background:#dbeafe;color:#1e40af}.nh-badge.p3{background:#e2e8f0;color:#334155}.nh-footer{padding:1rem 1.25rem;border-top:1px solid #e5e7eb}.nh-form{display:flex;gap:.75rem;align-items:flex-end}.nh-form textarea{flex:1;resize:none;min-height:48px;max-height:160px}.nh-status{font-size:.8rem;color:#64748b;margin-top:.5rem}.nh-ministry-note,.nh-link{font-size:.82rem}.nh-ministry-note{color:#64748b;margin-top:.65rem}.nh-link{text-decoration:none}.nh-session{margin:1rem 1.25rem 0}.nh-empty{font-size:.82rem;color:#64748b;margin-top:.5rem}@media(max-width:768px){.nh-command-strip,.nh-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.nh-messages{height:48vh;min-height:360px}.nh-bubble{max-width:96%}.nh-chat-head{align-items:flex-start}.nh-form{flex-direction:column}.nh-form textarea,.nh-form button{width:100%}.nh-window{width:100%}}
</style>
@endpush

@section('content')
<div class="container-fluid py-3" dir="rtl"><div class="nh-chat-shell">
    @if(session('success'))<div class="alert alert-success nh-session mb-0">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger nh-session mb-0">{{ session('error') }}</div>@endif

    <div class="nh-chat-head">
        <div><div class="fw-bold fs-5">نجم هدا</div><div class="text-muted small">همراه مدیریتی EarthCoop</div></div>
        <div class="nh-tabs" role="tablist" aria-label="حالت گفتگو"><button type="button" class="nh-tab active" data-pane="ministry">وزارت هوشمند</button><button type="button" class="nh-tab" data-pane="free-chat">گفت‌وگوی آزاد</button></div>
        <select id="agentSelect" class="form-select form-select-sm" style="width:auto" aria-label="عامل نجم هدا"><option value="steward">خادم / Steward</option><option value="guide">راهنما / Guide</option><option value="pilot">Pilot</option><option value="engineer">Engineer</option><option value="architect">Architect</option></select>
    </div>

    <div id="ministry" class="nh-pane active"><div class="nh-ministry">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div><div class="fw-bold">وزارت هوشمند مدیرکل</div><div class="text-muted small">یک میز فرمان گفت‌وگویی روی همان Founder Ops و approval lifecycle موجود؛ نه یک مسیر مدیریتی موازی.</div></div>
            <div class="nh-toolbar"><select id="windowSelect" class="form-select form-select-sm nh-window" aria-label="بازه گزارش"><option value="6">۶ ساعت</option><option value="24" selected>۲۴ ساعت</option><option value="72">۳ روز</option><option value="168">۷ روز</option></select><a class="nh-link" href="{{ route('admin.najm-hoda.founder-ops.index') }}">میز کار کامل مدیرکل ←</a></div>
        </div>

        <div class="nh-command-strip" id="globalSummaryCards" aria-label="وضعیت کل مدیریت">
            <div class="nh-command-card urgent"><div class="v" data-global="urgent">—</div><div class="k">فوری / مهم</div></div>
            <div class="nh-command-card decision"><div class="v" data-global="founder_decisions">—</div><div class="k">منتظر تصمیم من</div></div>
            <div class="nh-command-card prepared"><div class="v" data-global="prepared">—</div><div class="k">آماده توسط نجم</div></div>
            <div class="nh-command-card"><div class="v" data-global="information">—</div><div class="k">صرفاً جهت اطلاع</div></div>
        </div>

        <div class="nh-ministry-group"><div class="nh-ministry-group-title">فرمان‌های روزانه</div><div class="nh-ministry-actions">
            <button class="nh-intent" type="button" data-intent="morning_brief">☀ صبح مدیرکل</button>
            <button class="nh-intent" type="button" data-intent="urgent_items">⚠ کارهای فوری من</button>
            <button class="nh-intent" type="button" data-intent="pending_approvals">✓ در انتظار تأیید من</button>
            <button class="nh-intent" type="button" data-intent="communications">✉ ارتباطات</button>
            <button class="nh-intent" type="button" data-intent="system_health">♡ سلامت سامانه</button>
            <button class="nh-intent" type="button" data-intent="end_of_day">☾ پایان روز مدیرکل</button>
        </div></div>

        <div class="nh-ministry-group"><div class="nh-ministry-group-title">حوزه‌های مدیریتی</div><div class="nh-ministry-actions">
            <button class="nh-intent domain" type="button" data-intent="users_registration">👤 کاربران و ثبت‌نام</button>
            <button class="nh-intent domain" type="button" data-intent="reference_data">⌖ مکان / صنف / تخصص</button>
            <button class="nh-intent domain" type="button" data-intent="support_moderation">☏ پشتیبانی و شکایات</button>
            <button class="nh-intent domain" type="button" data-intent="groups">◉ گروه‌ها</button>
            <button class="nh-intent domain" type="button" data-intent="governance">⚖ انتخابات و حکمرانی</button>
            <button class="nh-intent domain" type="button" data-intent="najm_bahar">◈ نجم بهار</button>
            <button class="nh-intent domain" type="button" data-intent="stock">▦ سهام و تأمین مالی</button>
            <button class="nh-intent domain" type="button" data-intent="secretariat">▤ دبیرخانه</button>
            <button class="nh-intent domain" type="button" data-intent="authority">⌘ اختیارها و واگذاری‌ها</button>
        </div></div>

        <div id="detailSummaryWrap" class="nh-detail-summary-wrap" hidden><div class="nh-detail-summary-title">خلاصه همین درخواست</div><div id="detailSummaryCards" class="nh-summary"></div></div>
        <div class="nh-ministry-note">متن آزاد در این تب فقط به intentهای خواندنی شناخته‌شده نگاشت می‌شود. اجرای حساس فقط با دکمه صریح کارت و از routeهای Founder Ops موجود انجام می‌شود؛ متن چت هیچ approval یا authority را دور نمی‌زند.</div>
    </div></div>
    <div id="free-chat" class="nh-pane"></div>

    <div class="nh-messages" id="chatMessages" aria-live="polite">
        <div class="nh-msg bot"><div class="nh-bubble"><strong>نجم هدا — وزارت هوشمند</strong><div class="mt-1">در حال آماده‌کردن وضعیت مدیرکل هستم. می‌توانید هر حوزه را از دکمه‌های بالا باز کنید یا سؤال مدیریتی بنویسید.</div></div></div>
    </div>

    <div class="nh-footer"><form id="chatForm" class="nh-form">@csrf<textarea id="messageInput" class="form-control" rows="1" maxlength="5000" placeholder="مثلاً: مکان‌ها و تخصص‌های منتظر تأیید را نشان بده"></textarea><button class="btn btn-primary px-4" type="submit" id="sendButton">ارسال</button></form><div class="nh-status" id="chatStatus">وزارت هوشمند فعال است؛ وضعیت کل در حال بارگذاری است.</div></div>
</div></div>
@endsection

@push('scripts')
<script>
(() => {
    const csrf=document.querySelector('meta[name="csrf-token"]')?.content||document.querySelector('#chatForm input[name="_token"]')?.value||'';
    const messages=document.getElementById('chatMessages'),form=document.getElementById('chatForm'),input=document.getElementById('messageInput'),sendButton=document.getElementById('sendButton'),status=document.getElementById('chatStatus'),agentSelect=document.getElementById('agentSelect'),windowSelect=document.getElementById('windowSelect'),detailSummaryWrap=document.getElementById('detailSummaryWrap'),detailSummaryCards=document.getElementById('detailSummaryCards');
    const ministryUrl=@json(route('admin.najm-hoda.founder-ops.ministry.chat')),freeChatUrl=@json(route('admin.najm-hoda.chat.send'));
    let activePane='ministry',lastIntent='morning_brief';
    const labels={urgent:'فوری / مهم',founder_decisions:'منتظر تصمیم من',prepared:'آماده توسط نجم',information:'صرفاً جهت اطلاع',pending:'منتظر',overdue:'عقب‌افتاده',pending_decisions:'تصمیم ارتباطی',total:'مجموع',health_attention_items:'هشدار سلامت',runtime_status:'وضعیت runtime',active_delegations:'اختیار فعال',total_actions:'اقدام تعریف‌شده'};
    const domainLabels={users:'کاربران',invitations:'دعوت‌ها',support:'پشتیبانی',reports_moderation:'نظارت',moderation:'نظارت',reference_data:'داده پایه',locations:'مکان‌ها',approvals:'داده پایه',groups:'گروه‌ها',governance:'انتخابات',najm_bahar:'نجم بهار',financial_risk:'سلامت مالی',stock:'سهام',secretariat:'دبیرخانه',email:'ایمیل',blog:'محتوا',notifications:'اطلاعیه',runtime_health:'سلامت نجم',founder_approvals:'تصمیم مدیرکل',authority:'اختیارها'};
    function escapeHtml(v){const d=document.createElement('div');d.textContent=v==null?'':String(v);return d.innerHTML}
    function buttonClass(style){return style==='success'?'btn-success':style==='outline-danger'?'btn-outline-danger':style==='primary'?'btn-primary':'btn-outline-primary'}
    function actionForm(action){const form=document.createElement('form');form.method='POST';form.action=action.url;form.className='nh-action-form';form.dataset.confirm=action.confirm?'1':'0';form.innerHTML=`<input type="hidden" name="_token" value="${escapeHtml(csrf)}">${action.decision?`<input type="hidden" name="decision" value="${escapeHtml(action.decision)}">`:''}<button type="submit" class="btn btn-sm ${buttonClass(action.style)}">${escapeHtml(action.label||'اقدام')}</button>`;return form}
    function addMessage(text,who='bot',management=null){
        const w=document.createElement('div');w.className=`nh-msg ${who}`;const b=document.createElement('div');b.className='nh-bubble';b.innerHTML=`<strong>${who==='user'?'شما':'نجم هدا'}</strong><div class="mt-1">${escapeHtml(text)}</div>`;
        if(management?.items?.length){const items=document.createElement('div');items.className='nh-items';management.items.slice(0,20).forEach(item=>{const p=String(item.priority||item.risk||'P3').toLowerCase(),title=item.title||item.label||domainLabels[item.domain]||'مورد مدیریتی',kind=item.kind==='approval'?'منتظر تصمیم شما':(item.kind==='proposal'?'آماده بررسی':(item.status||item.sla_status||'جهت اطلاع'));const row=document.createElement('div');row.className='nh-item';row.innerHTML=`<div class="nh-item-top"><span>${escapeHtml(title)}</span><span class="nh-badge ${escapeHtml(p)}">${escapeHtml(item.priority||item.risk||'')}</span></div><div class="nh-item-meta">${escapeHtml(kind)}${item.domain?' · '+escapeHtml(domainLabels[item.domain]||item.domain):''}${item.entity_id?' · #'+escapeHtml(item.entity_id):''}</div>`;const actions=document.createElement('div');actions.className='nh-item-actions';if(item.ui?.workbench_url){const a=document.createElement('a');a.href=item.ui.workbench_url;a.className='btn btn-sm btn-outline-secondary';a.textContent='رسیدگی / جزئیات';actions.appendChild(a)}(item.ui?.actions||[]).forEach(action=>actions.appendChild(actionForm(action)));if(actions.children.length)row.appendChild(actions);items.appendChild(row)});b.appendChild(items)}
        if(management?.items&&management.items.length===0){const empty=document.createElement('div');empty.className='nh-empty';empty.textContent='موردی برای نمایش در این بخش ثبت نشده است.';b.appendChild(empty)}
        w.appendChild(b);messages.appendChild(w);messages.scrollTop=messages.scrollHeight
    }
    function renderGlobal(cards){if(!cards)return;['urgent','founder_decisions','prepared','information'].forEach(k=>{const el=document.querySelector(`[data-global="${k}"]`);if(el&&Object.prototype.hasOwnProperty.call(cards,k))el.textContent=cards[k]})}
    function normalizeCard(k,v){if(v&&typeof v==='object'&&!Array.isArray(v))return {label:v.label||labels[k]||k,value:v.value??'—'};return {label:labels[k]||k,value:v}}
    function renderDetail(cards,intent){detailSummaryCards.innerHTML='';if(!cards||Object.keys(cards).length===0||['morning_brief','end_of_day'].includes(intent)){detailSummaryWrap.hidden=true;return}Object.entries(cards).slice(0,4).forEach(([k,v])=>{const data=normalizeCard(k,v),c=document.createElement('div');c.className='nh-summary-card';c.innerHTML=`<div class="v">${escapeHtml(data.value)}</div><div class="k">${escapeHtml(data.label)}</div>`;detailSummaryCards.appendChild(c)});detailSummaryWrap.hidden=false}
    async function requestJson(url,payload){const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(payload)});let d={};try{d=await r.json()}catch(_){}if(!r.ok){const e=new Error(d.message||d.error||`HTTP ${r.status}`);e.payload=d;throw e}return d}
    async function runMinistry(payload,{silent=false}={}){document.querySelectorAll('.nh-intent').forEach(b=>b.disabled=true);status.textContent='در حال خواندن وضعیت واقعی Founder Ops...';try{const hours=Number(windowSelect.value||24),d=await requestJson(ministryUrl,{...payload,hours});lastIntent=d.management?.intent||payload.intent||lastIntent;if(!silent)addMessage(d.message||'گزارش آماده شد.','bot',d.management||null);renderGlobal(d.management?.global_summary_cards||(['morning_brief','end_of_day'].includes(lastIntent)?d.management?.summary_cards:null));renderDetail(d.management?.summary_cards||{},lastIntent);status.textContent='گزارش از داده‌های canonical Founder Ops تهیه شد.';return d}catch(e){if(e.payload?.management?.meta?.reason==='unclassified_management_question'){addMessage(e.payload.message,'bot');status.textContent='این متن به intent خواندنی امن نگاشت نشد؛ هیچ اقدامی اجرا نشد.'}else{addMessage('امکان دریافت گزارش مدیریتی وجود نداشت: '+e.message,'bot');status.textContent='خطا در وزارت هوشمند.'}return null}finally{document.querySelectorAll('.nh-intent').forEach(b=>b.disabled=false)}}
    document.querySelectorAll('.nh-intent').forEach(b=>b.addEventListener('click',()=>runMinistry({intent:b.dataset.intent})));
    document.querySelectorAll('.nh-tab').forEach(tab=>tab.addEventListener('click',()=>{document.querySelectorAll('.nh-tab').forEach(t=>t.classList.remove('active'));tab.classList.add('active');document.querySelectorAll('.nh-pane').forEach(p=>p.classList.remove('active'));document.getElementById(tab.dataset.pane)?.classList.add('active');activePane=tab.dataset.pane;const ministry=activePane==='ministry';agentSelect.style.visibility=ministry?'hidden':'visible';windowSelect.style.visibility=ministry?'visible':'hidden';input.placeholder=ministry?'مثلاً: چه چیزهایی منتظر تأیید من است؟':'سؤال خود را از نجم هدا بپرسید...';status.textContent=ministry?'وزارت هوشمند فعال است؛ سؤال‌های مدیریتی امن از Founder Ops پاسخ می‌گیرند.':'گفت‌وگوی آزاد فعال است؛ متن به runtime گفت‌وگوی نجم هدا می‌رود.'}));
    agentSelect.style.visibility='hidden';
    windowSelect.addEventListener('change',()=>runMinistry({intent:'morning_brief'}));
    form.addEventListener('submit',async e=>{e.preventDefault();const text=input.value.trim();if(!text)return;addMessage(text,'user');input.value='';sendButton.disabled=true;try{if(activePane==='ministry'){await runMinistry({message:text})}else{status.textContent='نجم هدا در حال پاسخ است...';const d=await requestJson(freeChatUrl,{message:text,agent:agentSelect.value});addMessage(d.response||d.message||'پاسخی دریافت نشد.','bot');status.textContent='پاسخ دریافت شد.'}}catch(error){addMessage('خطا در گفت‌وگو: '+error.message,'bot');status.textContent='خطا در گفت‌وگو.'}finally{sendButton.disabled=false;input.focus()}});
    input.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();form.requestSubmit()}});
    messages.addEventListener('submit',e=>{const f=e.target.closest('.nh-action-form');if(!f)return;if(f.dataset.confirm==='1'&&!window.confirm('این اقدام از lifecycle رسمی Founder Ops اجرا می‌شود. ادامه می‌دهید؟'))e.preventDefault()});
    runMinistry({intent:'morning_brief'});
})();
</script>
@endpush
