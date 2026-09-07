@extends('layouts.admin')

@section('title', 'میز کار روزانه نجم هدا')
@section('page-title', 'میز کار روزانه نجم هدا')
@section('page-description', 'خلاصه مدیریتی، تصمیم‌های امروز و کارهای آماده اقدام')

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
    $statusLabels = ['overdue'=>'عقب‌افتاده','within_sla'=>'در مهلت','pending'=>'منتظر','prepared'=>'آماده بررسی','attention'=>'نیازمند توجه','open'=>'باز','draft'=>'پیش‌نویس'];
    $priorityLabels = ['P0'=>'بحرانی','P1'=>'تصمیم','P2'=>'کار امروز','P3'=>'اطلاع'];
    $priorityClasses = ['P0'=>'danger','P1'=>'warning','P2'=>'primary','P3'=>'secondary'];
    $typeLabels = [
        'province'=>'استان','district'=>'شهرستان','county'=>'شهرستان','city'=>'شهر','section'=>'بخش','rural'=>'دهستان','village'=>'روستا',
        'region'=>'منطقه','neighborhood'=>'محله','street'=>'خیابان','alley'=>'کوچه','occupational_field'=>'صنف',
        'experience_field'=>'تخصص/تجربه','specialty'=>'صنف/تخصص','experience'=>'تخصص/تجربه',
    ];
    $moderationClassLabels = [
        'spam'=>'هرزنامه','abuse'=>'رفتار آزاردهنده','harassment'=>'آزار','fraud'=>'تقلب','violence'=>'خشونت',
        'privacy'=>'حریم خصوصی','other'=>'سایر','unknown'=>'نامشخص',
    ];

    $pendingDecisions = (int) data_get($executiveWorkQueue, 'needs_founder_decision', 0);
    $preparedWork = (int) data_get($executiveWorkQueue, 'prepared_by_najm_hoda', 0);
    $attentionOnly = (int) data_get($executiveWorkQueue, 'attention_only', 0);
    $newMembers = (int) data_get($snapshot, 'users.new_members', 0);

    $supportApprovalItems = $approvalItems->filter(fn($i) => data_get($i,'domain') === 'support' && data_get($i,'domain_action') === 'send_reply');
    $referenceApprovalItems = $approvalItems->filter(fn($i) => in_array(data_get($i,'domain'), ['reference_data','locations'], true) && data_get($i,'domain_action') === 'approve');
    $moderationApprovalItems = $approvalItems->filter(fn($i) => data_get($i,'domain') === 'reports_moderation' && data_get($i,'domain_action') === 'resolve_report');
    $emailApprovalItems = $approvalItems->filter(fn($i) => data_get($i,'domain') === 'email');
    $contentApprovalItems = $approvalItems->filter(fn($i) => data_get($i,'domain') === 'blog');
    $announcementApprovalItems = $approvalItems->filter(fn($i) => data_get($i,'domain') === 'notifications');

    $referenceCount = count($referenceCandidates ?? []);
    $supportCount = count($supportDrafts ?? []);
    $moderationCount = count($moderationCases ?? []);
    $emailCount = count($emailDrafts ?? []);
    $contentCount = count($contentDrafts ?? []);
    $announcementCount = count($announcementDrafts ?? []);
    $secretariatCount = count($secretariatFollowUps ?? []);
    $financialRiskCount = count($financialRiskFindings ?? []);
    $communicationApprovalCount = $emailApprovalItems->count() + $contentApprovalItems->count() + $announcementApprovalItems->count();
@endphp

<div class="container-fluid py-3" dir="rtl">
    @if(session('success'))<div class="alert alert-success shadow-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger shadow-sm">{{ session('error') }}</div>@endif

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h3 class="mb-1">میز کار روزانه مدیرکل</h3>
            <div class="text-muted">فقط چیزهایی که امروز لازم است بدانید یا درباره‌شان تصمیم بگیرید.</div>
        </div>
        <div class="btn-group" role="group" aria-label="بازه گزارش">
            @foreach([6=>'۶ ساعت',24=>'۲۴ ساعت',72=>'۳ روز',168=>'۷ روز'] as $window=>$label)
                <a href="{{ route('admin.najm-hoda.founder-ops.index',['hours'=>$window]) }}" class="btn btn-sm {{ $hours===$window?'btn-primary':'btn-outline-secondary' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="fw-bold mb-1">خلاصه اجرایی نجم هدا</div>
                <div class="text-muted">
                    در {{ $hours }} ساعت اخیر <strong class="text-dark">{{ $newMembers }}</strong> عضو جدید،
                    <strong class="text-dark">{{ $pendingDecisions }}</strong> تصمیم منتظر شما،
                    <strong class="text-dark">{{ $preparedWork }}</strong> کار آماده‌شده توسط نجم هدا و
                    <strong class="text-dark">{{ $attentionOnly }}</strong> اطلاع مدیریتی ثبت شده است.
                </div>
            </div>
            <a href="#today-work" class="btn btn-primary">شروع رسیدگی</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><a href="#today-work" class="text-decoration-none text-reset"><div class="card h-100 border-danger shadow-sm"><div class="card-body"><div class="small text-muted">بحرانی</div><div class="fs-2 fw-bold text-danger">{{ data_get($summary,'P0',0) }}</div><div class="small">رسیدگی فوری</div></div></div></a></div>
        <div class="col-6 col-xl-3"><a href="#decisions" class="text-decoration-none text-reset"><div class="card h-100 border-warning shadow-sm"><div class="card-body"><div class="small text-muted">تصمیم‌های من</div><div class="fs-2 fw-bold">{{ $pendingDecisions }}</div><div class="small">تأیید یا رد</div></div></div></a></div>
        <div class="col-6 col-xl-3"><a href="#prepared" class="text-decoration-none text-reset"><div class="card h-100 border-primary shadow-sm"><div class="card-body"><div class="small text-muted">آماده توسط نجم هدا</div><div class="fs-2 fw-bold text-primary">{{ $preparedWork }}</div><div class="small">پیشنهاد و پیش‌نویس</div></div></div></a></div>
        <div class="col-6 col-xl-3"><div class="card h-100 shadow-sm"><div class="card-body"><div class="small text-muted">اطلاع مدیریتی</div><div class="fs-2 fw-bold">{{ $attentionOnly }}</div><div class="small">بدون اقدام فوری</div></div></div></div>
    </div>

    <div class="card mb-3 shadow-sm" id="today-work">
        <div class="card-header d-flex justify-content-between align-items-center"><strong>کارهای امروز، به ترتیب اولویت</strong><span class="badge bg-secondary">{{ data_get($executiveWorkQueue,'total',0) }} مورد</span></div>
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
                        'secretariat' => '#secretariat',
                        default => '#system-status',
                    };
                @endphp
                <div class="list-group-item py-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="d-flex gap-3 align-items-start">
                            <span class="badge bg-{{ $priorityClasses[$priority] ?? 'secondary' }} mt-1">{{ $priorityLabels[$priority] ?? $priority }}</span>
                            <div>
                                <div class="fw-semibold">{{ data_get($item,'title','مورد مدیریتی') }}</div>
                                <div class="small text-muted mt-1">{{ $domainLabels[$domain] ?? 'سایر امور' }} · @if($kind==='approval')منتظر تصمیم شما@elseif($kind==='proposal')آماده‌شده توسط نجم هدا@else جهت اطلاع@endif</div>
                            </div>
                        </div>
                        <a href="{{ $target }}" class="btn btn-sm btn-outline-primary">رسیدگی</a>
                    </div>
                </div>
            @empty
                <div class="list-group-item text-center text-muted py-4">کار معوق یا فوری ندارید.</div>
            @endforelse
        </div>
    </div>

    <div id="decisions" class="mb-3">
        @if($approvalItems->isNotEmpty())
            <div class="card border-warning shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center"><strong>تصمیم‌های منتظر شما</strong><span class="badge bg-warning text-dark">{{ $approvalItems->count() }}</span></div>
                <div class="card-body py-2">
                    <div class="small text-muted">تصمیم‌های هر حوزه دقیقاً در همان بخش پایین صفحه با دکمه «تأیید» و «رد» نمایش داده می‌شوند.</div>
                </div>
            </div>
        @else
            <div class="alert alert-success shadow-sm mb-0">در حال حاضر هیچ تصمیمی منتظر شما نیست.</div>
        @endif
    </div>

    <div id="prepared">
        @if($referenceCount || $referenceApprovalItems->isNotEmpty())
            <div class="card mb-3 shadow-sm" id="reference-data">
                <div class="card-header d-flex justify-content-between align-items-center"><strong>مکان‌ها، صنف‌ها و تخصص‌ها</strong><span class="badge bg-secondary">{{ $referenceCount + $referenceApprovalItems->count() }}</span></div>
                <div class="card-body p-0">
                    @if($referenceCount)
                        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>نوع</th><th>عنوان</th><th>بررسی مشابهت</th><th>نظر نجم هدا</th><th>اقدام</th></tr></thead><tbody>
                        @foreach($referenceCandidates as $candidate)
                            @php $risk=data_get($candidate,'duplicate_risk','low'); @endphp
                            <tr>
                                <td>{{ $typeLabels[data_get($candidate,'type')] ?? 'داده پایه' }}</td>
                                <td class="fw-semibold">{{ data_get($candidate,'name') }}</td>
                                <td>@if($risk==='high')<span class="badge bg-danger">احتمال تکرار زیاد</span>@elseif($risk==='medium')<span class="badge bg-warning text-dark">نیازمند دقت</span>@else<span class="badge bg-success">مشابه مهمی یافت نشد</span>@endif</td>
                                <td>{{ data_get($candidate,'recommendation') ?: 'نیازمند بررسی شما' }}</td>
                                <td><form method="POST" action="{{ route('admin.najm-hoda.founder-ops.reference.request-approve',['type'=>data_get($candidate,'type'),'id'=>data_get($candidate,'id')]) }}">@csrf<button class="btn btn-sm btn-outline-primary">آماده تصمیم نهایی کن</button></form></td>
                            </tr>
                        @endforeach
                        </tbody></table></div>
                    @endif
                    @if($referenceApprovalItems->isNotEmpty())
                        <div class="p-3 border-top bg-light"><strong class="d-block mb-2">تصمیم نهایی من</strong>
                        @foreach($referenceApprovalItems as $approval)
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 border-bottom">
                                <span>{{ $typeLabels[data_get($approval,'context.entity_type')] ?? 'داده پایه' }} شماره {{ data_get($approval,'context.entity_id') }}</span>
                                <div class="d-flex gap-2">
                                    <form method="POST" action="{{ route('admin.najm-hoda.founder-ops.reference-approvals.decision',data_get($approval,'id')) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-success">تأیید</button></form>
                                    <form method="POST" action="{{ route('admin.najm-hoda.founder-ops.reference-approvals.decision',data_get($approval,'id')) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="btn btn-sm btn-outline-danger">رد</button></form>
                                </div>
                            </div>
                        @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if($supportCount || $supportApprovalItems->isNotEmpty())
            <div class="card mb-3 shadow-sm" id="support">
                <div class="card-header d-flex justify-content-between"><strong>پشتیبانی کاربران</strong><span class="badge bg-secondary">{{ $supportCount + $supportApprovalItems->count() }}</span></div>
                <div class="card-body">
                    @if($supportCount)<div class="alert alert-light border small">پاسخ پیشنهادی نجم هدا را می‌توانید ویرایش و ذخیره کنید. پس از ارسال برای تأیید نهایی، متن تا تعیین‌تکلیف همان درخواست قفل می‌شود.</div>@endif
                    @foreach($supportDrafts ?? [] as $draft)
                        <div class="border rounded p-3 mb-3">
                            <div class="fw-semibold mb-2">{{ $draft->ticket?->subject ?? 'تیکت '.$draft->ticket_id }}</div>
                            <form method="POST" action="{{ route('admin.najm-hoda.founder-ops.support-drafts.update',$draft) }}" class="mb-2">
                                @csrf @method('PATCH')
                                <label class="form-label small text-muted">پاسخ پیشنهادی نجم هدا</label>
                                <textarea name="body" rows="5" class="form-control mb-2" required>{{ old('body', $draft->body) }}</textarea>
                                <button class="btn btn-sm btn-outline-secondary">ذخیره ویرایش</button>
                            </form>
                            <form method="POST" action="{{ route('admin.najm-hoda.founder-ops.support-drafts.request-send',$draft) }}">@csrf<button class="btn btn-sm btn-primary">ارسال برای تأیید نهایی</button></form>
                        </div>
                    @endforeach
                    @foreach($supportApprovalItems as $approval)
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border rounded p-3 mb-2 bg-light"><span class="fw-semibold">پاسخ پشتیبانی شماره {{ data_get($approval,'context.entity_id') }} آماده ارسال است.</span><div class="d-flex gap-2"><form method="POST" action="{{ route('admin.najm-hoda.founder-ops.support-approvals.decision',data_get($approval,'id')) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-success">تأیید و ارسال</button></form><form method="POST" action="{{ route('admin.najm-hoda.founder-ops.support-approvals.decision',data_get($approval,'id')) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="btn btn-sm btn-outline-danger">رد</button></form></div></div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($moderationCount || $moderationApprovalItems->isNotEmpty())
            <div class="card mb-3 shadow-sm" id="moderation">
                <div class="card-header d-flex justify-content-between"><strong>گزارش‌ها و پرونده‌های نظارتی</strong><span class="badge bg-secondary">{{ $moderationCount + $moderationApprovalItems->count() }}</span></div>
                <div class="card-body">
                    @foreach($moderationCases ?? [] as $case)
                        <div class="border rounded p-3 mb-2"><div class="d-flex flex-wrap justify-content-between gap-2"><strong>{{ $moderationClassLabels[$case->classification] ?? 'گزارش نظارتی' }}</strong><span class="badge bg-{{ $case->severity==='high'?'danger':'warning' }}">شدت {{ $riskLabels[$case->severity] ?? $case->severity }}</span></div><div class="my-2" style="white-space:pre-wrap">{{ $case->summary }}</div><form method="POST" action="{{ route('admin.najm-hoda.founder-ops.moderation.request-resolve',['sourceType'=>$case->source_type,'sourceId'=>$case->source_id]) }}">@csrf<button class="btn btn-sm btn-outline-primary">برای تصمیم من آماده کن</button></form></div>
                    @endforeach
                    @foreach($moderationApprovalItems as $approval)
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border rounded p-3 mb-2 bg-light"><span>اقدام پیشنهادی برای پرونده شماره {{ data_get($approval,'context.entity_id') }}</span><div class="d-flex gap-2"><form method="POST" action="{{ route('admin.najm-hoda.founder-ops.moderation-approvals.decision',data_get($approval,'id')) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-success">تأیید اقدام</button></form><form method="POST" action="{{ route('admin.najm-hoda.founder-ops.moderation-approvals.decision',data_get($approval,'id')) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="btn btn-sm btn-outline-danger">رد</button></form></div></div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($emailCount || $contentCount || $announcementCount || $communicationApprovalCount)
            <div class="card mb-3 shadow-sm" id="communications">
                <div class="card-header"><strong>ارتباطات و انتشار</strong></div>
                <div class="card-body">
                    @if($emailCount || $contentCount || $announcementCount)<div class="alert alert-light border small">متن‌های پیشنهادی نجم هدا قبل از تأیید نهایی کاملاً قابل ویرایش‌اند. «ذخیره ویرایش» فقط نسخه پیشنهادی را به‌روزرسانی می‌کند و هیچ ارسال یا انتشاری انجام نمی‌دهد.</div>@endif
                    <div class="row g-3">
                        <div class="col-lg-4"><div class="border rounded p-3 h-100"><strong>ایمیل‌ها</strong>
                            @forelse($emailDrafts ?? [] as $draft)
                                <div class="border-top mt-3 pt-3">
                                    <form method="POST" action="{{ route('admin.najm-hoda.founder-ops.email-drafts.update',$draft) }}" class="mb-2">
                                        @csrf @method('PATCH')
                                        <label class="form-label small text-muted">موضوع</label>
                                        <input name="subject" class="form-control form-control-sm mb-2" value="{{ old('subject', $draft->subject) }}" required>
                                        <label class="form-label small text-muted">متن پیشنهادی</label>
                                        <textarea name="body" rows="6" class="form-control form-control-sm mb-2" required>{{ old('body', $draft->body) }}</textarea>
                                        <button class="btn btn-sm btn-outline-secondary">ذخیره ویرایش</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.najm-hoda.founder-ops.email-drafts.request-send',$draft) }}">@csrf<button class="btn btn-sm btn-primary">ارسال برای تأیید نهایی</button></form>
                                </div>
                            @empty<div class="small text-muted mt-2">پیش‌نویسی نیست.</div>@endforelse
                            @foreach($emailApprovalItems as $approval)<div class="border-top mt-2 pt-2"><div class="small fw-semibold mb-2">ایمیل شماره {{ data_get($approval,'context.entity_id') }} منتظر تصمیم شماست.</div><div class="d-flex gap-2"><form method="POST" action="{{ route('admin.najm-hoda.founder-ops.email-approvals.decision',data_get($approval,'id')) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-success">تأیید و ارسال</button></form><form method="POST" action="{{ route('admin.najm-hoda.founder-ops.email-approvals.decision',data_get($approval,'id')) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="btn btn-sm btn-outline-danger">رد</button></form></div></div>@endforeach
                        </div></div>

                        <div class="col-lg-4"><div class="border rounded p-3 h-100"><strong>محتوا</strong>
                            @forelse($contentDrafts ?? [] as $draft)
                                <div class="border-top mt-3 pt-3">
                                    <form method="POST" action="{{ route('admin.najm-hoda.founder-ops.content-drafts.update',$draft) }}" class="mb-2">
                                        @csrf @method('PATCH')
                                        <label class="form-label small text-muted">عنوان</label>
                                        <input name="title" class="form-control form-control-sm mb-2" value="{{ old('title', $draft->title) }}" required>
                                        <label class="form-label small text-muted">دسته‌بندی</label>
                                        <select name="category_id" class="form-select form-select-sm mb-2" required>
                                            <option value="">انتخاب دسته‌بندی</option>
                                            @foreach($contentCategories ?? [] as $category)
                                                <option value="{{ $category->id }}" @selected((string) old('category_id', $draft->category_id) === (string) $category->id)>{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                        <label class="form-label small text-muted">متن پیشنهادی</label>
                                        <textarea name="body" rows="6" class="form-control form-control-sm mb-2" required>{{ old('body', $draft->body) }}</textarea>
                                        <button class="btn btn-sm btn-outline-secondary">ذخیره ویرایش</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.najm-hoda.founder-ops.content-drafts.request-publish',$draft) }}">@csrf<button class="btn btn-sm btn-primary">ارسال برای تأیید نهایی</button></form>
                                </div>
                            @empty<div class="small text-muted mt-2">پیش‌نویسی نیست.</div>@endforelse
                            @foreach($contentApprovalItems as $approval)<div class="border-top mt-2 pt-2"><div class="small fw-semibold mb-2">محتوای شماره {{ data_get($approval,'context.entity_id') }} منتظر تصمیم شماست.</div><div class="d-flex gap-2"><form method="POST" action="{{ route('admin.najm-hoda.founder-ops.content-approvals.decision',data_get($approval,'id')) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-success">تأیید و انتشار</button></form><form method="POST" action="{{ route('admin.najm-hoda.founder-ops.content-approvals.decision',data_get($approval,'id')) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="btn btn-sm btn-outline-danger">رد</button></form></div></div>@endforeach
                        </div></div>

                        <div class="col-lg-4"><div class="border rounded p-3 h-100"><strong>اطلاعیه‌ها</strong>
                            @forelse($announcementDrafts ?? [] as $draft)
                                <div class="border-top mt-3 pt-3">
                                    <form method="POST" action="{{ route('admin.najm-hoda.founder-ops.announcement-drafts.update',$draft) }}" class="mb-2">
                                        @csrf @method('PATCH')
                                        <label class="form-label small text-muted">عنوان</label>
                                        <input name="title" class="form-control form-control-sm mb-2" value="{{ old('title', $draft->title) }}" required>
                                        <label class="form-label small text-muted">متن پیشنهادی</label>
                                        <textarea name="content" rows="6" class="form-control form-control-sm mb-2" required>{{ old('content', $draft->content) }}</textarea>
                                        <button class="btn btn-sm btn-outline-secondary">ذخیره ویرایش</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.najm-hoda.founder-ops.announcement-drafts.request-publish',$draft) }}">@csrf<button class="btn btn-sm btn-primary">ارسال برای تأیید نهایی</button></form>
                                </div>
                            @empty<div class="small text-muted mt-2">پیش‌نویسی نیست.</div>@endforelse
                            @foreach($announcementApprovalItems as $approval)<div class="border-top mt-2 pt-2"><div class="small fw-semibold mb-2">اطلاعیه شماره {{ data_get($approval,'context.entity_id') }} منتظر تصمیم شماست.</div><div class="d-flex gap-2"><form method="POST" action="{{ route('admin.najm-hoda.founder-ops.announcement-approvals.decision',data_get($approval,'id')) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-success">تأیید و انتشار</button></form><form method="POST" action="{{ route('admin.najm-hoda.founder-ops.announcement-approvals.decision',data_get($approval,'id')) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="btn btn-sm btn-outline-danger">رد</button></form></div></div>@endforeach
                        </div></div>
                    </div>
                </div>
            </div>
        @endif

        @if($secretariatCount)
            <div class="card mb-3 shadow-sm" id="secretariat"><div class="card-header"><strong>پیگیری‌های دبیرخانه</strong></div><div class="card-body">
                @foreach($secretariatFollowUps as $proposal)<div class="border-bottom py-2"><div class="fw-semibold">ثبت {{ $proposal->dispatch?->record?->registry_number ?? '-' }}</div><div class="small text-muted mb-1">فوریت: {{ $riskLabels[$proposal->urgency] ?? 'عادی' }}</div><div style="white-space:pre-wrap">{{ $proposal->proposal }}</div></div>@endforeach
                <div class="small text-muted mt-3">نجم هدا فعلاً پیگیری را پیشنهاد می‌دهد؛ ارسال رسمی فقط پس از اتصال مسیر واقعی ارسال فعال خواهد شد.</div>
            </div></div>
        @endif
    </div>

    @if(! $referenceCount && ! $supportCount && ! $moderationCount && ! $emailCount && ! $contentCount && ! $announcementCount && ! $secretariatCount && ! $approvalItems->count())
        <div class="alert alert-light border shadow-sm">حوزه‌های عملیاتی آرام هستند؛ چیزی برای رسیدگی روزانه ثبت نشده است.</div>
    @endif

    @if($financialRiskCount)
        <div class="card mb-3 border-danger shadow-sm"><div class="card-header"><strong>هشدار مالی</strong></div><div class="card-body">@foreach($financialRiskFindings as $finding)<div class="border-bottom py-2"><div class="d-flex justify-content-between gap-2"><strong>{{ $finding->title ?? $finding->risk_code ?? 'ریسک مالی' }}</strong><span class="badge bg-danger">{{ $riskLabels[$finding->severity] ?? $finding->severity }}</span></div><div class="small text-muted mt-1">{{ $finding->description ?? '' }}</div></div>@endforeach</div></div>
    @endif

    <div class="card mb-3 shadow-sm" id="system-status">
        <div class="card-header"><strong>نمای سریع وضعیت EarthCoop</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-6 col-lg-3"><div class="border rounded p-3"><div class="small text-muted">کاربران جدید</div><div class="fs-4 fw-bold">{{ data_get($snapshot,'users.new_members',0) }}</div></div></div>
                <div class="col-6 col-lg-3"><div class="border rounded p-3"><div class="small text-muted">داده پایه منتظر</div><div class="fs-4 fw-bold">{{ data_get($snapshot,'approvals.total',0) }}</div></div></div>
                <div class="col-6 col-lg-3"><div class="border rounded p-3"><div class="small text-muted">تیکت فعال</div><div class="fs-4 fw-bold">{{ data_get($snapshot,'support.active',0) }}</div></div></div>
                <div class="col-6 col-lg-3"><div class="border rounded p-3"><div class="small text-muted">انتخابات فعال</div><div class="fs-4 fw-bold">{{ data_get($snapshot,'governance.active',0) }}</div></div></div>
            </div>
            <div class="row g-3 mt-1 small">
                <div class="col-md-4"><strong>نجم بهار</strong><div class="text-muted mt-1">پروژه منتظر بررسی: {{ data_get($snapshot,'najm_bahar.pending_projects',0) }} · تراکنش زمان‌بندی‌شده ناموفق: {{ data_get($snapshot,'najm_bahar.scheduled_failed',0) }}</div></div>
                <div class="col-md-4"><strong>سهام و تأمین مالی</strong><div class="text-muted mt-1">مزایده در حال اجرا: {{ data_get($snapshot,'stock.auctions_running',0) }} · نیازمند تطبیق مالی: {{ data_get($snapshot,'stock.reconciliation_needed',0) }}</div></div>
                <div class="col-md-4"><strong>سلامت نجم هدا</strong><div class="text-muted mt-1">وضعیت: {{ data_get($snapshot,'runtime_health.status','سالم') === 'healthy' ? 'سالم' : data_get($snapshot,'runtime_health.status','سالم') }}</div></div>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#technical-status" aria-expanded="false" aria-controls="technical-status">نمایش وضعیت فنی و پوشش قابلیت‌های نجم هدا</button>
        <div class="collapse mt-2" id="technical-status">
            <div class="card card-body small">
                <div class="row g-3">
                    <div class="col-md-4"><span class="text-muted">حوزه‌های کاملاً مدیریت‌شده</span><div class="fs-5 fw-bold">{{ data_get($executiveConnectivity,'summary.managed',0) }}</div></div>
                    <div class="col-md-4"><span class="text-muted">قابلیت‌های محدود یا مشروط</span><div class="fs-5 fw-bold">{{ data_get($executiveConnectivity,'summary.partial',0) }}</div></div>
                    <div class="col-md-4"><span class="text-muted">شکاف‌های اتصال باقی‌مانده</span><div class="fs-5 fw-bold">{{ data_get($executiveConnectivity,'summary.remaining_connectivity_gaps',0) }}</div></div>
                </div>
                <hr>
                <div>اقدام‌های تعریف‌شده: <strong>{{ data_get($authority,'total_actions',0) }}</strong> · اختیارهای واگذارشده فعال: <strong>{{ data_get($authority,'active_delegations_count',0) }}</strong> · حالت ایمن در ابهام: <strong>{{ data_get($authority,'fail_closed')?'فعال':'غیرفعال' }}</strong></div>
            </div>
        </div>
    </div>

    <div class="small text-muted text-center pb-2">کارهای حساس بدون تصمیم صریح شما اجرا نمی‌شوند؛ نجم هدا در موارد مبهم یا خارج از اختیار، اقدام را متوقف می‌کند.</div>
</div>
@endsection