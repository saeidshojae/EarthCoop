@extends('layouts.admin')

@section('title', 'میز کار روزانه نجم هدا')
@section('page-title', 'میز کار روزانه نجم هدا')
@section('page-description', 'خلاصه مدیریتی، تصمیم‌ها و کارهای آماده اقدام در EarthCoop')

@section('content')
@php
    $summary = data_get($brief, 'summary', []);
    $authority = data_get($brief, 'authority', []);
    $approvals = $approvalInbox ?? data_get($brief, 'founder_approvals', []);
    $approvalItems = collect(data_get($approvals, 'items', []));
    $queue = collect(data_get($executiveWorkQueue, 'items', []));

    $domainLabels = [
        'users'=>'کاربران','groups'=>'گروه‌ها','support'=>'پشتیبانی','governance'=>'انتخابات و حکمرانی',
        'reports_moderation'=>'گزارش‌ها و نظارت','moderation'=>'گزارش‌ها و نظارت','reference_data'=>'داده‌های پایه',
        'locations'=>'مکان‌ها','invitations'=>'دعوت‌ها','secretariat'=>'دبیرخانه','najm_bahar'=>'نجم بهار',
        'stock'=>'سهام','notifications'=>'اطلاعیه‌ها','blog'=>'محتوا','email'=>'ایمیل','admin_settings'=>'تنظیمات مدیریتی',
        'runtime_health'=>'سلامت نجم هدا','financial_risk'=>'سلامت مالی','founder_approvals'=>'تصمیم‌های مدیرکل',
        'approvals'=>'تأیید داده‌های پایه','management_coverage'=>'پوشش مدیریتی','authority'=>'اختیارها',
    ];
    $riskLabels = ['critical'=>'بحرانی','high'=>'زیاد','medium'=>'متوسط','low'=>'کم','unknown'=>'نامشخص'];
    $statusLabels = ['overdue'=>'عقب‌افتاده','within_sla'=>'در مهلت','pending'=>'منتظر','prepared'=>'آماده بررسی','attention'=>'نیازمند توجه','open'=>'باز'];
    $priorityLabels = ['P0'=>'بحرانی','P1'=>'نیازمند تصمیم','P2'=>'کار امروز','P3'=>'اطلاع'];
    $priorityClasses = ['P0'=>'danger','P1'=>'warning','P2'=>'primary','P3'=>'secondary'];
    $typeLabels = [
        'province'=>'استان','district'=>'شهرستان','city'=>'شهر','section'=>'بخش','rural'=>'دهستان','village'=>'روستا',
        'region'=>'منطقه','neighborhood'=>'محله','street'=>'خیابان','alley'=>'کوچه','occupational_field'=>'صنف',
        'experience_field'=>'تخصص/تجربه','specialty'=>'صنف/تخصص','experience'=>'تخصص/تجربه',
    ];
    $duplicateLabels = ['high'=>'زیاد','medium'=>'متوسط','low'=>'کم'];

    $pendingDecisions = (int) data_get($executiveWorkQueue, 'needs_founder_decision', 0);
    $preparedWork = (int) data_get($executiveWorkQueue, 'prepared_by_najm_hoda', 0);
    $attentionOnly = (int) data_get($executiveWorkQueue, 'attention_only', 0);
    $newMembers = (int) data_get($snapshot, 'users.new_members', 0);
    $pendingReference = (int) data_get($snapshot, 'approvals.total', 0);
    $openSupport = (int) data_get($snapshot, 'support.active', 0);
    $activeElections = (int) data_get($snapshot, 'governance.active', 0);

    $supportApprovalItems = $approvalItems->filter(fn($i) => data_get($i,'domain') === 'support' && data_get($i,'domain_action') === 'send_reply');
    $referenceApprovalItems = $approvalItems->filter(fn($i) => in_array(data_get($i,'domain'), ['reference_data','locations'], true) && data_get($i,'domain_action') === 'approve');
    $moderationApprovalItems = $approvalItems->filter(fn($i) => data_get($i,'domain') === 'reports_moderation' && data_get($i,'domain_action') === 'resolve_report');
    $emailApprovalItems = $approvalItems->filter(fn($i) => data_get($i,'domain') === 'email');
    $contentApprovalItems = $approvalItems->filter(fn($i) => data_get($i,'domain') === 'blog');
    $announcementApprovalItems = $approvalItems->filter(fn($i) => data_get($i,'domain') === 'notifications');
@endphp

<div class="container-fluid py-3" dir="rtl">
    @if(session('success'))
        <div class="alert alert-success shadow-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger shadow-sm">{{ session('error') }}</div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h3 class="mb-1">میز کار روزانه مدیرکل</h3>
            <div class="text-muted">نجم هدا وضعیت EarthCoop را جمع‌بندی کرده و کارهای مهم را برای تصمیم یا اقدام آماده کرده است.</div>
        </div>
        <div class="btn-group" role="group" aria-label="بازه گزارش">
            @foreach([6=>'۶ ساعت',24=>'۲۴ ساعت',72=>'۳ روز',168=>'۷ روز'] as $window=>$label)
                <a href="{{ route('admin.najm-hoda.founder-ops.index',['hours'=>$window]) }}" class="btn btn-sm {{ $hours===$window?'btn-primary':'btn-outline-secondary' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    <div class="alert alert-light border shadow-sm mb-4">
        <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center">
            <div>
                <div class="fw-bold mb-1">خلاصه اجرایی نجم هدا</div>
                <div>
                    در {{ $hours }} ساعت اخیر
                    <strong>{{ $newMembers }}</strong> عضو جدید ثبت شده،
                    <strong>{{ $pendingDecisions }}</strong> تصمیم منتظر شماست،
                    <strong>{{ $preparedWork }}</strong> کار توسط نجم هدا آماده بررسی شده و
                    <strong>{{ $attentionOnly }}</strong> مورد فقط نیازمند آگاهی شماست.
                </div>
            </div>
            <a href="#today-work" class="btn btn-primary">رفتن به کارهای امروز</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="card h-100 border-danger"><div class="card-body"><div class="text-muted small">بحرانی</div><div class="fs-2 fw-bold text-danger">{{ data_get($summary,'P0',0) }}</div><div class="small">نیازمند رسیدگی فوری</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100 border-warning"><div class="card-body"><div class="text-muted small">تصمیم‌های من</div><div class="fs-2 fw-bold">{{ $pendingDecisions }}</div><div class="small">منتظر تأیید یا رد شما</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100 border-primary"><div class="card-body"><div class="text-muted small">آماده توسط نجم هدا</div><div class="fs-2 fw-bold text-primary">{{ $preparedWork }}</div><div class="small">پیش‌نویس یا پیشنهاد آماده</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">اطلاع مدیریتی</div><div class="fs-2 fw-bold">{{ data_get($summary,'P3',0) }}</div><div class="small">نیاز به اقدام فوری ندارد</div></div></div></div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a class="btn btn-sm btn-outline-primary" href="#today-work">کارهای امروز</a>
        <a class="btn btn-sm btn-outline-primary" href="#decisions">تصمیم‌های منتظر من</a>
        <a class="btn btn-sm btn-outline-primary" href="#reference-data">مکان، صنف و تخصص</a>
        <a class="btn btn-sm btn-outline-primary" href="#support">پشتیبانی</a>
        <a class="btn btn-sm btn-outline-primary" href="#moderation">نظارت</a>
        <a class="btn btn-sm btn-outline-primary" href="#communications">ارتباطات</a>
        <a class="btn btn-sm btn-outline-primary" href="#system-status">وضعیت سامانه</a>
    </div>

    <div class="card mb-4" id="today-work">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>کارهای امروز به ترتیب اولویت</strong>
            <span class="badge bg-secondary">{{ data_get($executiveWorkQueue,'total',0) }} مورد</span>
        </div>
        <div class="list-group list-group-flush">
            @forelse($queue->take(12) as $item)
                @php
                    $priority = data_get($item,'priority','P3');
                    $domain = data_get($item,'domain','');
                    $kind = data_get($item,'kind','attention');
                    $target = match($domain) {
                        'support' => '#support',
                        'reference_data','locations','approvals' => '#reference-data',
                        'reports_moderation','moderation' => '#moderation',
                        'email','blog','notifications' => '#communications',
                        default => '#system-status',
                    };
                @endphp
                <div class="list-group-item py-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="d-flex gap-3 align-items-start">
                            <span class="badge bg-{{ $priorityClasses[$priority] ?? 'secondary' }} mt-1">{{ $priorityLabels[$priority] ?? $priority }}</span>
                            <div>
                                <div class="fw-semibold">{{ data_get($item,'title','مورد مدیریتی') }}</div>
                                <div class="small text-muted mt-1">
                                    {{ $domainLabels[$domain] ?? 'سایر امور' }}
                                    @if($kind==='approval') · منتظر تصمیم شما @elseif($kind==='proposal') · آماده‌شده توسط نجم هدا @else · جهت اطلاع @endif
                                    @if(data_get($item,'status')) · {{ $statusLabels[data_get($item,'status')] ?? data_get($item,'status') }} @endif
                                </div>
                            </div>
                        </div>
                        <a href="{{ $target }}" class="btn btn-sm btn-outline-secondary">مشاهده و رسیدگی</a>
                    </div>
                </div>
            @empty
                <div class="list-group-item text-center text-muted py-4">در این بازه کاری برای رسیدگی وجود ندارد.</div>
            @endforelse
        </div>
    </div>

    <div class="row g-3 mb-4" id="decisions">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between"><strong>موارد نیازمند توجه مدیرکل</strong><span class="badge bg-secondary">{{ data_get($summary,'total_attention_items',0) }}</span></div>
                <div class="list-group list-group-flush">
                    @forelse(data_get($brief,'items',[]) as $item)
                        @php $p=data_get($item,'priority','P3'); $d=data_get($item,'domain',''); @endphp
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <div class="fw-semibold">{{ data_get($item,'title') }}</div>
                                    <div class="small text-muted">حوزه: {{ $domainLabels[$d] ?? 'سایر امور' }}</div>
                                </div>
                                <span class="badge bg-{{ $priorityClasses[$p] ?? 'secondary' }} align-self-start">{{ $priorityLabels[$p] ?? $p }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="list-group-item text-muted">موردی برای توجه ثبت نشده است.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><strong>صف تصمیم‌های من</strong></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span>منتظر تصمیم</span><strong>{{ data_get($approvals,'pending',0) }}</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>عقب‌افتاده از مهلت</span><strong class="text-danger">{{ data_get($approvals,'overdue',0) }}</strong></div>
                    <div class="d-flex justify-content-between"><span>داخل مهلت</span><strong>{{ data_get($approvals,'within_sla',0) }}</strong></div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><strong>حدود اختیار نجم هدا</strong></div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between mb-2"><span>اقدام‌های تعریف‌شده</span><strong>{{ data_get($authority,'total_actions',0) }}</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>اختیارهای واگذارشده فعال</span><strong>{{ data_get($authority,'active_delegations_count',0) }}</strong></div>
                    <div class="d-flex justify-content-between"><span>حالت ایمن در صورت ابهام</span><strong>{{ data_get($authority,'fail_closed')?'فعال':'غیرفعال' }}</strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4" id="reference-data">
        <div class="card-header d-flex justify-content-between align-items-center"><strong>مکان‌ها، صنف‌ها و تخصص‌های منتظر بررسی</strong><span class="badge bg-secondary">{{ count($referenceCandidates ?? []) }}</span></div>
        <div class="card-body p-0">
            <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>نوع</th><th>عنوان</th><th>احتمال تکراری بودن</th><th>موارد مشابه</th><th>نظر نجم هدا</th><th>اقدام</th></tr></thead><tbody>
            @forelse($referenceCandidates ?? [] as $candidate)
                @php $risk=data_get($candidate,'duplicate_risk','low'); @endphp
                <tr>
                    <td>{{ $typeLabels[data_get($candidate,'type')] ?? 'داده پایه' }}</td>
                    <td class="fw-semibold">{{ data_get($candidate,'name') }}</td>
                    <td><span class="badge bg-{{ $risk==='high'?'danger':($risk==='medium'?'warning':'success') }}">{{ $duplicateLabels[$risk] ?? 'کم' }}</span></td>
                    <td>@forelse(data_get($candidate,'similar',[]) as $similar)<div>{{ data_get($similar,'name') }} <small class="text-muted">({{ round(data_get($similar,'similarity',0)*100) }}٪ شباهت)</small></div>@empty<span class="text-muted">مورد مشابه مهمی پیدا نشد.</span>@endforelse</td>
                    <td>{{ data_get($candidate,'recommendation') ?: 'نیازمند بررسی مدیرکل' }}</td>
                    <td><form method="POST" action="{{ route('admin.najm-hoda.founder-ops.reference.request-approve',['type'=>data_get($candidate,'type'),'id'=>data_get($candidate,'id')]) }}">@csrf<button class="btn btn-sm btn-outline-success">ارسال برای تصمیم نهایی</button></form></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted text-center py-4">مورد منتظر بررسی وجود ندارد.</td></tr>
            @endforelse
            </tbody></table></div>
        </div>
    </div>

    @if($referenceApprovalItems->isNotEmpty())
        <div class="card mb-4 border-warning">
            <div class="card-header"><strong>تصمیم نهایی درباره داده‌های پایه</strong></div>
            <div class="card-body">
                @foreach($referenceApprovalItems as $approval)
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom py-3">
                        <div><div class="fw-semibold">{{ $typeLabels[data_get($approval,'context.entity_type')] ?? 'داده پایه' }} شماره {{ data_get($approval,'context.entity_id') }}</div><div class="small text-muted">وضعیت مهلت: {{ $statusLabels[data_get($approval,'sla_status')] ?? 'منتظر' }}</div></div>
                        <div class="d-flex gap-2">
                            <form method="POST" action="{{ route('admin.najm-hoda.founder-ops.reference-approvals.decision',data_get($approval,'id')) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-success">تأیید نهایی</button></form>
                            <form method="POST" action="{{ route('admin.najm-hoda.founder-ops.reference-approvals.decision',data_get($approval,'id')) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="btn btn-sm btn-outline-danger">رد</button></form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card mb-4" id="support">
        <div class="card-header d-flex justify-content-between"><strong>پشتیبانی کاربران</strong><span class="badge bg-secondary">{{ count($supportDrafts ?? []) }} پاسخ آماده</span></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>تیکت</th><th>موضوع</th><th>پاسخ پیشنهادی نجم هدا</th><th>اقدام</th></tr></thead><tbody>
        @forelse($supportDrafts ?? [] as $draft)
            <tr><td>{{ $draft->ticket?->tracking_code ?? '#'.$draft->ticket_id }}</td><td>{{ $draft->ticket?->subject ?? '-' }}</td><td style="min-width:360px;white-space:pre-wrap">{{ $draft->body }}</td><td><form method="POST" action="{{ route('admin.najm-hoda.founder-ops.support-drafts.request-send',$draft) }}">@csrf<button class="btn btn-sm btn-outline-primary">بفرست برای تأیید من</button></form></td></tr>
        @empty<tr><td colspan="4" class="text-muted text-center py-4">پاسخ آماده‌ای وجود ندارد.</td></tr>@endforelse
        </tbody></table></div></div>
    </div>

    @if($supportApprovalItems->isNotEmpty())
        <div class="card mb-4 border-warning"><div class="card-header"><strong>پاسخ‌های پشتیبانی منتظر تصمیم من</strong></div><div class="card-body">
            @foreach($supportApprovalItems as $approval)
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom py-3"><div><div class="fw-semibold">پاسخ آماده شماره {{ data_get($approval,'context.entity_id') }}</div><div class="small text-muted">{{ $statusLabels[data_get($approval,'sla_status')] ?? 'منتظر' }}</div></div><div class="d-flex gap-2"><form method="POST" action="{{ route('admin.najm-hoda.founder-ops.support-approvals.decision',data_get($approval,'id')) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-success">تأیید و ارسال</button></form><form method="POST" action="{{ route('admin.najm-hoda.founder-ops.support-approvals.decision',data_get($approval,'id')) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="btn btn-sm btn-outline-danger">رد</button></form></div></div>
            @endforeach
        </div></div>
    @endif

    <div class="card mb-4" id="moderation">
        <div class="card-header d-flex justify-content-between"><strong>گزارش‌ها و پرونده‌های نظارتی</strong><span class="badge bg-secondary">{{ count($moderationCases ?? []) }}</span></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>منبع</th><th>دسته</th><th>شدت</th><th>جمع‌بندی نجم هدا</th><th>اقدام</th></tr></thead><tbody>
        @forelse($moderationCases ?? [] as $case)
            <tr><td>{{ $case->source_type }} #{{ $case->source_id }}</td><td>{{ $case->classification }}</td><td>{{ $riskLabels[$case->severity] ?? $case->severity }}</td><td style="min-width:360px;white-space:pre-wrap">{{ $case->summary }}</td><td><form method="POST" action="{{ route('admin.najm-hoda.founder-ops.moderation.request-resolve',['sourceType'=>$case->source_type,'sourceId'=>$case->source_id]) }}">@csrf<button class="btn btn-sm btn-outline-primary">بفرست برای تصمیم من</button></form></td></tr>
        @empty<tr><td colspan="5" class="text-muted text-center py-4">پرونده آماده‌ای وجود ندارد.</td></tr>@endforelse
        </tbody></table></div></div>
    </div>

    @if($moderationApprovalItems->isNotEmpty())
        <div class="card mb-4 border-warning"><div class="card-header"><strong>پرونده‌های نظارتی منتظر تصمیم من</strong></div><div class="card-body">
            @foreach($moderationApprovalItems as $approval)
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom py-3"><div><div class="fw-semibold">پرونده شماره {{ data_get($approval,'context.entity_id') }}</div></div><div class="d-flex gap-2"><form method="POST" action="{{ route('admin.najm-hoda.founder-ops.moderation-approvals.decision',data_get($approval,'id')) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-success">تأیید اقدام</button></form><form method="POST" action="{{ route('admin.najm-hoda.founder-ops.moderation-approvals.decision',data_get($approval,'id')) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="btn btn-sm btn-outline-danger">رد</button></form></div></div>
            @endforeach
        </div></div>
    @endif

    <div class="card mb-4" id="communications">
        <div class="card-header"><strong>ارتباطات آماده‌شده توسط نجم هدا</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-4"><div class="border rounded p-3 h-100"><div class="d-flex justify-content-between mb-3"><strong>ایمیل‌ها</strong><span class="badge bg-secondary">{{ count($emailDrafts ?? []) }}</span></div>@forelse($emailDrafts ?? [] as $draft)<div class="border-top py-2"><div class="fw-semibold">{{ $draft->subject ?? 'ایمیل آماده ارسال' }}</div><div class="small text-muted mb-2">{{ \Illuminate\Support\Str::limit($draft->body ?? '',120) }}</div><form method="POST" action="{{ route('admin.najm-hoda.founder-ops.email-drafts.request-send',$draft) }}">@csrf<button class="btn btn-sm btn-outline-primary">ارسال برای تأیید من</button></form></div>@empty<div class="text-muted small">ایمیل آماده‌ای نیست.</div>@endforelse</div></div>
                <div class="col-lg-4"><div class="border rounded p-3 h-100"><div class="d-flex justify-content-between mb-3"><strong>محتوا</strong><span class="badge bg-secondary">{{ count($contentDrafts ?? []) }}</span></div>@forelse($contentDrafts ?? [] as $draft)<div class="border-top py-2"><div class="fw-semibold">{{ $draft->title ?? 'محتوای آماده انتشار' }}</div><div class="small text-muted mb-2">{{ \Illuminate\Support\Str::limit($draft->body ?? $draft->content ?? '',120) }}</div><form method="POST" action="{{ route('admin.najm-hoda.founder-ops.content-drafts.request-publish',$draft) }}">@csrf<button class="btn btn-sm btn-outline-primary">ارسال برای تأیید من</button></form></div>@empty<div class="text-muted small">محتوای آماده‌ای نیست.</div>@endforelse</div></div>
                <div class="col-lg-4"><div class="border rounded p-3 h-100"><div class="d-flex justify-content-between mb-3"><strong>اطلاعیه‌ها</strong><span class="badge bg-secondary">{{ count($announcementDrafts ?? []) }}</span></div>@forelse($announcementDrafts ?? [] as $draft)<div class="border-top py-2"><div class="fw-semibold">{{ $draft->title ?? 'اطلاعیه آماده انتشار' }}</div><div class="small text-muted mb-2">{{ \Illuminate\Support\Str::limit($draft->body ?? $draft->message ?? '',120) }}</div><form method="POST" action="{{ route('admin.najm-hoda.founder-ops.announcement-drafts.request-publish',$draft) }}">@csrf<button class="btn btn-sm btn-outline-primary">ارسال برای تأیید من</button></form></div>@empty<div class="text-muted small">اطلاعیه آماده‌ای نیست.</div>@endforelse</div></div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><strong>پیگیری‌های دبیرخانه</strong></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>شماره ثبت</th><th>وضعیت</th><th>فوریت</th><th>پیشنهاد نجم هدا</th></tr></thead><tbody>
        @forelse($secretariatFollowUps ?? [] as $proposal)
            <tr><td>{{ $proposal->dispatch?->record?->registry_number ?? '-' }}</td><td>{{ $proposal->dispatch?->status ?? '-' }}</td><td>{{ $riskLabels[$proposal->urgency] ?? ($proposal->urgency==='high'?'زیاد':'عادی') }}</td><td style="min-width:360px;white-space:pre-wrap">{{ $proposal->proposal }}</td></tr>
        @empty<tr><td colspan="4" class="text-muted text-center py-4">پیگیری آماده‌ای وجود ندارد.</td></tr>@endforelse
        </tbody></table></div><div class="small text-muted p-3 border-top">نجم هدا در این بخش فقط پیگیری را پیشنهاد می‌دهد؛ ارسال رسمی دبیرخانه تا اتصال transport واقعی انجام نمی‌شود.</div></div>
    </div>

    @if(count($financialRiskFindings ?? []))
        <div class="card mb-4 border-danger"><div class="card-header"><strong>هشدارهای سلامت مالی</strong></div><div class="card-body">
            @foreach($financialRiskFindings as $finding)
                <div class="border-bottom py-2"><div class="d-flex justify-content-between"><span class="fw-semibold">{{ $finding->title ?? $finding->risk_code ?? 'ریسک مالی' }}</span><span class="badge bg-danger">{{ $riskLabels[$finding->severity] ?? $finding->severity }}</span></div><div class="small text-muted mt-1">{{ $finding->description ?? '' }}</div></div>
            @endforeach
        </div></div>
    @endif

    <div class="card mb-4" id="system-status">
        <div class="card-header"><strong>نمای سریع وضعیت EarthCoop</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">کاربران جدید</div><div class="fs-4 fw-bold">{{ $newMembers }}</div></div></div>
                <div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">داده پایه منتظر بررسی</div><div class="fs-4 fw-bold">{{ $pendingReference }}</div></div></div>
                <div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">تیکت پشتیبانی فعال</div><div class="fs-4 fw-bold">{{ $openSupport }}</div></div></div>
                <div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">انتخابات فعال</div><div class="fs-4 fw-bold">{{ $activeElections }}</div></div></div>
            </div>
            <hr>
            <div class="row g-3 small">
                <div class="col-md-4"><strong>نجم بهار</strong><div class="text-muted mt-1">پروژه‌های منتظر بررسی: {{ data_get($snapshot,'najm_bahar.projects_submitted',0) }}<br>تراکنش‌های عقب‌افتاده: {{ data_get($snapshot,'najm_bahar.scheduled_overdue',0) }}</div></div>
                <div class="col-md-4"><strong>سهام و تأمین مالی</strong><div class="text-muted mt-1">مزایده‌های در حال اجرا: {{ data_get($snapshot,'stock.running',0) }}<br>نیازمند تطبیق مالی: {{ data_get($snapshot,'stock.settlement_allocations.reconciliation_required',0) }}</div></div>
                <div class="col-md-4"><strong>سلامت نجم هدا</strong><div class="text-muted mt-1">وضعیت: {{ data_get($snapshot,'runtime_health.status','healthy')==='healthy'?'سالم':(data_get($snapshot,'runtime_health.status')==='warning'?'نیازمند توجه':'بحرانی') }}</div></div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><strong>وضعیت اتصال قابلیت‌های مدیریتی</strong></div>
        <div class="card-body small">
            <div class="row g-3">
                <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="text-muted">قابلیت‌های مدیریت‌شده</div><div class="fs-4 fw-bold">{{ data_get($executiveConnectivity,'summary.managed',0) }}</div></div></div>
                <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="text-muted">قابلیت‌های محدود/مشروط</div><div class="fs-4 fw-bold">{{ data_get($executiveConnectivity,'summary.partial',0) }}</div></div></div>
                <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="text-muted">شکاف‌های اتصال باقی‌مانده</div><div class="fs-4 fw-bold">{{ data_get($executiveConnectivity,'summary.blocked',0) }}</div></div></div>
            </div>
        </div>
    </div>

    <div class="text-muted small pb-4">این صفحه برای کار روزانه مدیرکل طراحی شده است. کارهای حساس بدون تصمیم صریح شما اجرا نمی‌شوند و نجم هدا در موارد مبهم به‌صورت ایمن از اقدام خودکار خودداری می‌کند.</div>
</div>
@endsection
