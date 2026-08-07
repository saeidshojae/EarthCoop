@extends('layouts.admin')

@section('title', 'حکمرانی خودمختار نجم هُدی - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'حکمرانی خودمختار نجم هُدی')
@section('page-description', 'نظارت، کنترل، ارزیابی و مدیریت ایمن عملکرد خودمختار نجم هُدی')

@section('content')
<div class="space-y-6" dir="rtl">
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-900">
        <div class="font-bold mb-1"><i class="fas fa-info-circle ml-1"></i> راهنمای این صفحه</div>
        <p class="mb-0">این صفحه برای مشاهده و کنترل سطح خودمختاری نجم هُدی است. اگر درباره یک کنترل مطمئن نیستید، ابتدا فقط «تازه‌سازی وضعیت» را بزنید. کنترل توقف اضطراری و تغییر مرحله انتشار فقط هنگام رخداد عملیاتی یا پس از بررسی وضعیت استفاده شوند.</p>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">داشبورد حکمرانی و نظارت</h2>
            <p class="text-sm text-gray-500 mt-1">نمای فارسیِ وضعیت، تأییدها، کنترل‌های اضطراری و مراحل فعال‌سازی خودمختاری</p>
        </div>
        <div class="flex items-center gap-2">
            <label for="windowHours" class="text-sm text-gray-600">بازه بررسی:</label>
            <select id="windowHours" class="border rounded px-2 py-1">
                <option value="1">۱ ساعت</option>
                <option value="6">۶ ساعت</option>
                <option value="24" selected>۲۴ ساعت</option>
                <option value="72">۷۲ ساعت</option>
            </select>
            <button id="governanceRefresh" class="px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700">تازه‌سازی وضعیت</button>
        </div>
    </div>

    <div id="pageStatus" class="hidden text-sm px-3 py-2 rounded"></div>
    <div id="governanceSummary" class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-5 gap-4"></div>

    <div class="bg-white border rounded-lg p-4">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h3 class="font-semibold">شاخص‌های سلامت و اهداف خدمت</h3>
                <p class="text-xs text-gray-500 mt-1">KPI و SLO معیارهای فنی هستند؛ وضعیت «مناسب» یعنی در محدوده هدف و «نقض» یعنی نیازمند بررسی.</p>
            </div>
        </div>
        <div class="overflow-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="bg-gray-100 text-right"><th class="p-2">شاخص</th><th class="p-2">مقدار</th><th class="p-2">هدف</th><th class="p-2">وضعیت</th></tr></thead>
                <tbody id="governanceTableBody"></tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <div class="bg-white border rounded-lg p-4 xl:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <div>
                    <h3 class="font-semibold">درخواست‌های نیازمند تأیید انسانی</h3>
                    <p class="text-xs text-gray-500 mt-1">عملیات حساس قبل از اجرا در این فهرست منتظر تصمیم مدیر می‌مانند.</p>
                </div>
                <div class="flex gap-2">
                    <button id="evaluationRun" class="px-3 py-1.5 bg-purple-700 text-white rounded hover:bg-purple-800">ارزیابی آزمایشی</button>
                    <button id="oversightRefresh" class="px-3 py-1.5 bg-indigo-600 text-white rounded hover:bg-indigo-700">تازه‌سازی نظارت</button>
                </div>
            </div>
            <div id="oversightSummary" class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-5 gap-3 mb-4"></div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-2 mb-3">
                <input id="approvalSearch" type="text" class="border rounded px-2 py-1" placeholder="جستجو در عملیات یا شناسه">
                <select id="approvalRiskFilter" class="border rounded px-2 py-1">
                    <option value="">همه سطح‌های ریسک</option><option value="low">کم</option><option value="medium">متوسط</option><option value="high">زیاد</option><option value="critical">بحرانی</option><option value="unknown">نامشخص</option>
                </select>
                <select id="approvalSlaFilter" class="border rounded px-2 py-1">
                    <option value="">همه وضعیت‌های زمان پاسخ</option><option value="within_sla">در مهلت</option><option value="overdue">از مهلت گذشته</option>
                </select>
                <select id="approvalPageSize" class="border rounded px-2 py-1"><option value="5">۵ مورد</option><option value="10" selected>۱۰ مورد</option><option value="20">۲۰ مورد</option><option value="50">۵۰ مورد</option></select>
            </div>
            <div class="overflow-auto">
                <table class="min-w-full text-sm">
                    <thead><tr class="bg-gray-100 text-right"><th class="p-2">شناسه</th><th class="p-2">عملیات</th><th class="p-2">ریسک</th><th class="p-2">مهلت</th><th class="p-2">تصمیم مدیر</th></tr></thead>
                    <tbody id="approvalTableBody"></tbody>
                </table>
            </div>
            <div class="flex items-center justify-between mt-3"><div id="approvalPaginationMeta" class="text-xs text-gray-500">-</div><div class="flex items-center gap-2"><button id="approvalPrevPage" class="px-2 py-1 border rounded text-sm">قبلی</button><span id="approvalPageIndicator" class="text-sm">۱ / ۱</span><button id="approvalNextPage" class="px-2 py-1 border rounded text-sm">بعدی</button></div></div>
        </div>

        <div class="bg-white border rounded-lg p-4">
            <h3 class="font-semibold mb-1">کنترل‌های ایمنی و خودمختاری</h3>
            <p class="text-xs text-gray-500 mb-3">این کنترل‌ها مستقیماً رفتار اجرایی نجم هُدی را محدود می‌کنند.</p>
            <div id="controlStateBox" class="text-sm text-gray-700 space-y-2 mb-4 bg-gray-50 border rounded p-3"></div>

            <div class="space-y-4">
                <div><h4 class="font-medium text-sm mb-1">توقف و حالت اضطراری</h4><p class="text-xs text-gray-500 mb-2">برای توقف موقت خودمختاری یا قطع فوری عملیات پرریسک.</p><div class="grid gap-2">
                    <button data-control-action="pause" class="control-action px-3 py-2 bg-amber-600 text-white rounded">توقف خودمختاری برای ۳۰ دقیقه</button>
                    <button data-control-action="resume" class="control-action px-3 py-2 bg-emerald-600 text-white rounded">ازسرگیری خودمختاری</button>
                    <button data-control-action="activate_kill_switch" class="control-action px-3 py-2 bg-red-700 text-white rounded">فعال‌کردن توقف اضطراری برای ۱۵ دقیقه</button>
                    <button data-control-action="deactivate_kill_switch" class="control-action px-3 py-2 bg-green-700 text-white rounded">غیرفعال‌کردن توقف اضطراری</button>
                </div></div>

                <div><h4 class="font-medium text-sm mb-1">محدودکردن به حالت پیشنهاد</h4><p class="text-xs text-gray-500 mb-2">در این حالت نجم هُدی پیشنهاد می‌دهد اما اقدام خودکار انجام نمی‌دهد.</p><div class="grid gap-2">
                    <button data-control-action="set_override" class="control-action px-3 py-2 bg-slate-700 text-white rounded">اجبار به حالت «فقط پیشنهاد»</button>
                    <button data-control-action="clear_override" class="control-action px-3 py-2 bg-gray-700 text-white rounded">حذف محدودیت موقت</button>
                </div></div>

                <div><h4 class="font-medium text-sm mb-1">آزمایش امن تغییرات کد</h4><p class="text-xs text-gray-500 mb-2">Canary یعنی تغییرات ابتدا روی درصد کمی اجرا و سپس مرحله‌ای گسترش داده می‌شوند.</p><div class="grid gap-2">
                    <button data-codeops-action="start" class="codeops-action px-3 py-2 bg-blue-700 text-white rounded">شروع آزمایش مرحله‌ای</button><button data-codeops-action="promote" class="codeops-action px-3 py-2 bg-indigo-700 text-white rounded">گسترش به مرحله بعد</button><button data-codeops-action="evaluate" class="codeops-action px-3 py-2 bg-cyan-700 text-white rounded">ارزیابی مرحله فعلی</button><button data-codeops-action="rollback" class="codeops-action px-3 py-2 bg-rose-700 text-white rounded">بازگشت تغییرات آزمایشی</button>
                </div></div>

                <div><h4 class="font-medium text-sm mb-1">عملیات شبانه‌روزی</h4><p class="text-xs text-gray-500 mb-2">فعال‌سازی خودمختاری عملیاتی در بازه‌های کنترل‌شده.</p><div class="grid gap-2">
                    <button data-ops-action="activate" class="ops-action px-3 py-2 bg-emerald-700 text-white rounded">فعال‌کردن شیفت شب</button><button data-ops-action="tick" class="ops-action px-3 py-2 bg-sky-700 text-white rounded">اجرای یک چرخه عملیاتی دستی</button><button data-ops-action="deactivate" class="ops-action px-3 py-2 bg-gray-700 text-white rounded">غیرفعال‌کردن عملیات ۲۴/۷</button>
                </div></div>

                <div><h4 class="font-medium text-sm mb-1">انتشار تدریجی خودمختاری</h4><p class="text-xs text-gray-500 mb-2">Shadow ابتدا فقط تصمیم‌ها را مشاهده می‌کند؛ مراحل بعدی به‌تدریج اختیار واقعی می‌دهند.</p><div class="grid gap-2">
                    <button data-rollout-action="evaluate" class="rollout-action px-3 py-2 bg-teal-700 text-white rounded">ارزیابی مرحله فعلی</button><button data-rollout-action="advance" class="rollout-action px-3 py-2 bg-emerald-800 text-white rounded">رفتن به مرحله بعد</button><button data-rollout-action="fallback" class="rollout-action px-3 py-2 bg-amber-700 text-white rounded">بازگشت به حالت سایه</button>
                </div></div>

                <div><h4 class="font-medium text-sm mb-1">تأیید نهایی مرحله ششم</h4><p class="text-xs text-gray-500 mb-2">Go یعنی آماده، Conditional Go یعنی آماده با شرط، و No-Go یعنی فعلاً متوقف.</p><div class="grid gap-2">
                    <select id="signoffDecision" class="border rounded px-2 py-2 text-sm"><option value="conditional_go">آماده با شرط</option><option value="go">آماده برای ادامه</option><option value="no_go">فعلاً متوقف شود</option></select>
                    <input id="signoffNote" type="text" class="border rounded px-2 py-2 text-sm" placeholder="یادداشت تصمیم مدیر">
                    <button data-signoff-action="report" class="signoff-action px-3 py-2 bg-slate-700 text-white rounded">ساخت گزارش آمادگی</button><button data-signoff-action="sign" class="signoff-action px-3 py-2 bg-green-800 text-white rounded">ثبت تصمیم نهایی</button>
                </div></div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="bg-white border rounded-lg p-4"><h3 class="font-semibold mb-3">پیشنهادهای نظارتی</h3><div id="oversightRecommendations" class="space-y-2 text-sm"></div></div>
        <div class="bg-white border rounded-lg p-4"><h3 class="font-semibold mb-3">تفویض اختیار</h3><p class="text-xs text-gray-500 mb-3">نمایش اختیارهای فعال و مواردی که به دلیل سیاست‌های ایمنی رد شده‌اند.</p><div id="delegationSummary" class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4"></div><div class="overflow-auto"><table class="min-w-full text-xs"><thead><tr class="bg-gray-100 text-right"><th class="p-2">واگذارکننده</th><th class="p-2">عملیات</th><th class="p-2">دامنه</th><th class="p-2">انقضا</th></tr></thead><tbody id="delegationActiveTableBody"></tbody></table></div><div class="mt-4"><h4 class="text-sm font-medium mb-2">دلایل رد تفویض</h4><div id="delegationDeniedReasons" class="space-y-2 text-sm"></div></div></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const $ = (id) => document.getElementById(id);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const urls = {
        baseline: @json(route('admin.najm-hoda.autonomy.governance.baseline')),
        snapshot: @json(route('admin.najm-hoda.autonomy.governance.snapshot')),
        costs: @json(route('admin.najm-hoda.autonomy.costs.status')),
        oversight: @json(route('admin.najm-hoda.autonomy.oversight.console')),
        controls: @json(route('admin.najm-hoda.autonomy.controls')),
        controlsUpdate: @json(route('admin.najm-hoda.autonomy.controls.update')),
        codeopsUpdate: @json(route('admin.najm-hoda.autonomy.codeops.canary.update')),
        evaluationRun: @json(route('admin.najm-hoda.autonomy.evaluation.run')),
        operationsUpdate: @json(route('admin.najm-hoda.autonomy.operations.update')),
        rolloutUpdate: @json(route('admin.najm-hoda.autonomy.shadow-rollout.update')),
        signoffReport: @json(route('admin.najm-hoda.autonomy.phase6-signoff.report')),
        signoffUpdate: @json(route('admin.najm-hoda.autonomy.phase6-signoff.update')),
        approvalDecision: @json(route('admin.najm-hoda.autonomy.approvals.decision', ['approvalId' => '__ID__'])),
        approvalVeto: @json(route('admin.najm-hoda.autonomy.approvals.veto', ['approvalId' => '__ID__'])),
    };
    let policy = {}, page = 1, pager = null, busy = false;

    const riskFa = {low:'کم',medium:'متوسط',high:'زیاد',critical:'بحرانی',unknown:'نامشخص'};
    const statusFa = {ok:'مناسب',warning:'هشدار',breach:'نقض',no_data:'بدون داده',active:'فعال',inactive:'غیرفعال',paused:'متوقف',idle:'آماده‌به‌کار',shadow:'سایه',limited_live:'اجرای محدود',supervised_live:'اجرای تحت نظارت',autonomous_live:'اجرای خودمختار',go:'آماده',conditional_go:'آماده با شرط',no_go:'متوقف',unknown:'نامشخص'};
    const actionFa = {pause:'توقف موقت',resume:'ازسرگیری',activate_kill_switch:'فعال‌کردن توقف اضطراری',deactivate_kill_switch:'لغو توقف اضطراری',set_override:'اجبار به حالت پیشنهاد',clear_override:'حذف محدودیت',start:'شروع',promote:'گسترش مرحله',evaluate:'ارزیابی',rollback:'بازگشت',activate:'فعال‌سازی',deactivate:'غیرفعال‌سازی',tick:'چرخه دستی',advance:'مرحله بعد',fallback:'بازگشت به سایه',report:'گزارش',sign:'ثبت تصمیم'};
    const fa = (v, map) => map?.[String(v)] || String(v ?? '-');
    const fmt = (v) => v === null || v === undefined ? '-' : (typeof v === 'number' ? String(Math.round(v * 10000) / 10000) : String(v));
    const can = (k) => Boolean(policy?.ability?.[k]);

    function setStatus(type, msg) {
        const el = $('pageStatus');
        if (!msg) { el.className='hidden'; el.textContent=''; return; }
        const cls = type === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : type === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-blue-50 text-blue-700 border border-blue-200';
        el.className = `text-sm px-3 py-2 rounded ${cls}`; el.textContent = msg;
    }
    function card(title, value, hint='') { return `<div class="bg-white border rounded-lg p-4"><div class="text-xs text-gray-500">${title}</div><div class="text-xl font-bold mt-1">${value}</div><div class="text-xs text-gray-400 mt-1">${hint}</div></div>`; }
    function badge(v) { const s=String(v||'no_data'); const cls=s==='ok'?'bg-emerald-100 text-emerald-800':s==='warning'?'bg-amber-100 text-amber-800':s==='breach'?'bg-red-100 text-red-800':'bg-gray-100 text-gray-700'; return `<span class="px-2 py-1 rounded text-xs ${cls}">${fa(s,statusFa)}</span>`; }
    async function getJson(url) { const r=await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest'}}); const d=await r.json().catch(()=>({})); if(!r.ok||d?.success===false) throw new Error(d?.message||d?.reason||`HTTP ${r.status}`); return d; }
    async function postJson(url,payload) { const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf},body:JSON.stringify(payload||{})}); const d=await r.json().catch(()=>({})); if(!r.ok||!d?.success) throw new Error(d?.message||d?.reason||`HTTP ${r.status}`); return d; }

    function renderGovernance(baseline,snapshot,costs) {
        const metrics=snapshot?.metrics||{}, evals=snapshot?.evaluation||{};
        const states=Object.values(evals).map(x=>x?.status||'no_data');
        $('governanceSummary').innerHTML=[card('رویدادهای بررسی‌شده',snapshot?.event_count??0),card('موارد نقض',states.filter(x=>x==='breach').length),card('هشدارها',states.filter(x=>x==='warning').length),card('موفقیت اقدام خودکار',fmt(metrics.auto_action_success_rate??0)),card('هزینه روزانه هوش مصنوعی',fmt(costs?.daily_total??0),`بودجه: ${fmt(costs?.daily_budget??0)}`)].join('');
        const keys=Object.keys(baseline||{}); $('governanceTableBody').innerHTML=keys.length?keys.map(k=>{const b=baseline[k]||{},e=evals[k]||{};const min=b.target_min!==undefined?`حداقل ${fmt(b.target_min)}`:'';const max=b.target_max!==undefined?`حداکثر ${fmt(b.target_max)}`:'';return `<tr class="border-b"><td class="p-2">${b.label||k}</td><td class="p-2">${fmt(metrics[k])}</td><td class="p-2">${[min,max].filter(Boolean).join(' / ')||'-'}</td><td class="p-2">${badge(e.status)}</td></tr>`;}).join(''):'<tr><td colspan="4" class="p-3 text-gray-500">داده‌ای ثبت نشده است.</td></tr>';
    }

    function renderOversight(s) {
        policy=s?.policy_hints||{}; const a=s?.approvals||{}, d=s?.delegation||{}, audit=s?.audit||{}, ev=s?.events||{}, c=s?.controls||{}, code=s?.codeops_canary||{}, op=s?.operations_24x7||{}, roll=s?.shadow_rollout||{}, sign=s?.phase6_signoff||{};
        $('oversightSummary').innerHTML=[card('در انتظار تأیید',a.pending_count??0),card('از مهلت گذشته',a.overdue_count??0),card('تفویض فعال',d.active_count??0),card('اجرای ناموفق',audit.failed_count??0,`رویدادها: ${ev.recent_count??0}`),card('مرحله انتشار',fa(roll.stage||'shadow',statusFa))].join('');
        $('controlStateBox').innerHTML=`<div><strong>وضعیت خودمختاری:</strong> ${fa(c?.state?.status||'-',statusFa)}</div><div><strong>توقف اضطراری:</strong> ${c?.kill_switch?.active?'فعال':'غیرفعال'}</div><div><strong>حالت اجباری:</strong> ${c?.override?.force_mode==='propose'?'فقط پیشنهاد':(c?.override?.force_mode||'ندارد')}</div><div><strong>آزمایش کد:</strong> ${fa(code?.status||'idle',statusFa)}</div><div><strong>عملیات ۲۴/۷:</strong> ${fa(op?.status||'inactive',statusFa)}</div><div><strong>تصمیم نهایی:</strong> ${fa(sign?.last_decision||'unknown',statusFa)}</div>`;
        renderApprovals(a.pending||[],a.pagination||null);
        const rec=s?.recommended_actions||[]; $('oversightRecommendations').innerHTML=rec.length?rec.map(x=>`<div class="border rounded p-3 bg-gray-50"><div class="font-medium">${x?.type||'پیشنهاد'}</div><div class="text-gray-600 mt-1">${x?.reason||'-'}</div><div class="mt-1"><strong>اقدام پیشنهادی:</strong> ${x?.action||'-'}</div></div>`).join(''):'<div class="text-gray-500">در حال حاضر پیشنهاد نظارتی خاصی وجود ندارد.</div>';
        const es=d?.event_summary||{}; $('delegationSummary').innerHTML=[card('نیازمند تأیید',d.require_approval_count??0),card('نزدیک انقضا',d.expiring_soon_count??0),card('رد شده',es.denied??0),card('مجاز شده',es.authorized??0)].join('');
        const active=d?.recent_active||[]; $('delegationActiveTableBody').innerHTML=active.length?active.map(x=>`<tr class="border-b"><td class="p-2">${x.principal_type||'-'}:${x.principal_id||'-'}</td><td class="p-2">${x.action||'-'}</td><td class="p-2">${x.scope||'global'}</td><td class="p-2">${x.expires_at||'-'}</td></tr>`).join(''):'<tr><td colspan="4" class="p-2 text-gray-500">تفویض فعالی وجود ندارد.</td></tr>';
        const reasons=Object.entries(es?.denied_reasons||{}); $('delegationDeniedReasons').innerHTML=reasons.length?reasons.map(([r,n])=>`<div class="flex justify-between border rounded p-2"><span>${r}</span><span>${n}</span></div>`).join(''):'<div class="text-gray-500">مورد ردشده‌ای ثبت نشده است.</div>';
        applyPermissions();
    }

    function renderApprovals(rows,p) {
        pager=p||{current_page:1,last_page:1,total:rows.length,from:rows.length?1:0,to:rows.length}; page=Number(pager.current_page||1); $('approvalPaginationMeta').textContent=`${pager.total||0} مورد (${pager.from||0} تا ${pager.to||0})`; $('approvalPageIndicator').textContent=`${page} / ${pager.last_page||1}`; $('approvalPrevPage').disabled=page<=1||busy; $('approvalNextPage').disabled=page>=Number(pager.last_page||1)||busy;
        $('approvalTableBody').innerHTML=rows.length?rows.map(r=>{const id=r?.id||'',sla=r?.sla_status==='overdue'?'از مهلت گذشته':'در مهلت';return `<tr class="border-b"><td class="p-2 font-mono text-xs">${id}</td><td class="p-2">${r?.action||'-'}</td><td class="p-2">${fa(r?.risk||'unknown',riskFa)}</td><td class="p-2">${sla}</td><td class="p-2"><div class="flex flex-wrap gap-1"><button data-id="${id}" data-mode="approve" class="approval-action px-2 py-1 bg-emerald-600 text-white rounded text-xs">تأیید</button><button data-id="${id}" data-mode="reject" class="approval-action px-2 py-1 bg-amber-600 text-white rounded text-xs">رد</button><button data-id="${id}" data-mode="veto" class="approval-action px-2 py-1 bg-red-600 text-white rounded text-xs">وتوی فوری</button></div></td></tr>`;}).join(''):'<tr><td colspan="5" class="p-3 text-gray-500">درخواستی با فیلتر فعلی وجود ندارد.</td></tr>';
    }

    function applyPermissions() {
        const rules={'.control-action':'controls_update','.codeops-action':'codeops_canary_write','.ops-action':'operations_write','.rollout-action':'shadow_rollout_write','.signoff-action':'phase6_signoff_write'}; Object.entries(rules).forEach(([sel,key])=>document.querySelectorAll(sel).forEach(b=>{b.disabled=busy||!can(key);b.classList.toggle('opacity-50',b.disabled);b.title=can(key)?'':'شما مجوز این عملیات را ندارید.';})); $('evaluationRun').disabled=busy||!can('evaluation_write');
        document.querySelectorAll('.approval-action').forEach(b=>{const m=b.dataset.mode,key=m==='approve'?'approval_approve':m==='reject'?'approval_reject':'approval_veto';b.disabled=busy||!can(key);b.classList.toggle('opacity-50',b.disabled);});
    }
    function setBusy(v){busy=v; applyPermissions();}

    async function loadGovernance(){const w=Math.max(1,Math.min(168,Number($('windowHours').value)||24));const [b,s,c]=await Promise.all([getJson(urls.baseline),getJson(`${urls.snapshot}?window_hours=${w}`),getJson(urls.costs)]);renderGovernance(b.baseline||{},s.snapshot||{},c.status||{});}
    async function loadOversight(){setBusy(true);setStatus('info','در حال دریافت وضعیت نظارتی...');try{const q=new URLSearchParams({limit:'200',approval_page:String(page),approval_page_size:String(Number($('approvalPageSize').value)||10),approval_risk:$('approvalRiskFilter').value||'',approval_sla:$('approvalSlaFilter').value||'',approval_q:($('approvalSearch').value||'').trim(),approval_sort_by:'requested_at',approval_sort_dir:'desc'});const d=await getJson(`${urls.oversight}?${q}`);renderOversight(d.snapshot||{});setStatus('success','اطلاعات نظارتی به‌روز شد.');setTimeout(()=>setStatus('', ''),1800);}catch(e){setStatus('error',`دریافت اطلاعات ناموفق بود: ${e.message}`);}finally{setBusy(false);}}

    async function approvalAction(id,mode){if(!id)return;let url,payload;if(mode==='approve'){url=urls.approvalDecision.replace('__ID__',id);payload={decision:'approve'};}else if(mode==='reject'){const reason=prompt('دلیل رد را بنویسید:');if(!reason?.trim())return;url=urls.approvalDecision.replace('__ID__',id);payload={decision:'reject',reason};}else{const reason=prompt('دلیل وتو را بنویسید (اختیاری):')||'veto_by_operator';url=urls.approvalVeto.replace('__ID__',id);payload={reason};}try{setBusy(true);await postJson(url,payload);setStatus('success','تصمیم ثبت شد.');await loadOversight();}catch(e){setStatus('error',`ثبت تصمیم ناموفق بود: ${e.message}`);}finally{setBusy(false);}}
    async function controlAction(action){const p={action,reason:`${action}_from_fa_governance`};if(action==='pause')p.minutes=30;if(action==='activate_kill_switch')p.minutes=15;if(action==='set_override'){p.force_mode='propose';p.allow_apply_low_risk=false;}try{setBusy(true);await postJson(urls.controlsUpdate,p);setStatus('success',`${fa(action,actionFa)} انجام شد.`);await loadOversight();}catch(e){setStatus('error',`عملیات ناموفق بود: ${e.message}`);}finally{setBusy(false);}}
    async function codeopsAction(action){const p={action,auto_rollback:true,reason:`${action}_from_fa_governance`};if(action==='start')p.phases=[5,25,50,100];try{setBusy(true);await postJson(urls.codeopsUpdate,p);setStatus('success',`${fa(action,actionFa)} انجام شد.`);await loadOversight();}catch(e){setStatus('error',`عملیات آزمایش کد ناموفق بود: ${e.message}`);}finally{setBusy(false);}}
    async function opsAction(action){const p={action,reason:`${action}_from_fa_governance`};if(action==='activate')p.mode='night_only';if(action==='tick'){p.manual=true;p.window_hours=Number($('windowHours').value)||24;}try{setBusy(true);await postJson(urls.operationsUpdate,p);setStatus('success',`${fa(action,actionFa)} انجام شد.`);await loadOversight();}catch(e){setStatus('error',`عملیات ناموفق بود: ${e.message}`);}finally{setBusy(false);}}
    async function rolloutAction(action){const p={action,reason:`${action}_from_fa_governance`};if(action==='evaluate')p.window_hours=Number($('windowHours').value)||24;if(action==='fallback')p.stage='shadow';try{setBusy(true);await postJson(urls.rolloutUpdate,p);setStatus('success',`${fa(action,actionFa)} انجام شد.`);await loadOversight();}catch(e){setStatus('error',`تغییر مرحله ناموفق بود: ${e.message}`);}finally{setBusy(false);}}
    async function signoffAction(action){try{setBusy(true);if(action==='report'){await getJson(`${urls.signoffReport}?window_hours=${Number($('windowHours').value)||24}&history_limit=20`);}else{await postJson(urls.signoffUpdate,{decision:$('signoffDecision').value||'conditional_go',note:($('signoffNote').value||'').trim(),window_hours:Number($('windowHours').value)||24});}setStatus('success',action==='report'?'گزارش آمادگی ساخته شد.':'تصمیم نهایی ثبت شد.');await loadOversight();}catch(e){setStatus('error',`عملیات ناموفق بود: ${e.message}`);}finally{setBusy(false);}}

    $('governanceRefresh').onclick=()=>Promise.all([loadGovernance(),loadOversight()]).catch(()=>{}); $('oversightRefresh').onclick=()=>loadOversight(); $('windowHours').onchange=()=>Promise.all([loadGovernance(),loadOversight()]); $('evaluationRun').onclick=async()=>{try{setBusy(true);await postJson(urls.evaluationRun,{dry_run:true,window_hours:Number($('windowHours').value)||24});setStatus('success','ارزیابی آزمایشی انجام شد؛ تغییری اعمال نشد.');await loadOversight();}catch(e){setStatus('error',`ارزیابی ناموفق بود: ${e.message}`);}finally{setBusy(false);}};
    $('approvalTableBody').onclick=e=>{const b=e.target.closest('.approval-action');if(b)approvalAction(b.dataset.id,b.dataset.mode);}; document.querySelectorAll('.control-action').forEach(b=>b.onclick=()=>controlAction(b.dataset.controlAction)); document.querySelectorAll('.codeops-action').forEach(b=>b.onclick=()=>codeopsAction(b.dataset.codeopsAction)); document.querySelectorAll('.ops-action').forEach(b=>b.onclick=()=>opsAction(b.dataset.opsAction)); document.querySelectorAll('.rollout-action').forEach(b=>b.onclick=()=>rolloutAction(b.dataset.rolloutAction)); document.querySelectorAll('.signoff-action').forEach(b=>b.onclick=()=>signoffAction(b.dataset.signoffAction));
    let searchTimer=null; $('approvalSearch').oninput=()=>{clearTimeout(searchTimer);page=1;searchTimer=setTimeout(loadOversight,350);}; $('approvalRiskFilter').onchange=$('approvalSlaFilter').onchange=$('approvalPageSize').onchange=()=>{page=1;loadOversight();}; $('approvalPrevPage').onclick=()=>{page=Math.max(1,page-1);loadOversight();}; $('approvalNextPage').onclick=()=>{page+=1;loadOversight();};
    loadGovernance().catch(e=>setStatus('error',`بارگذاری شاخص‌ها ناموفق بود: ${e.message}`)); loadOversight();
})();
</script>
@endpush
