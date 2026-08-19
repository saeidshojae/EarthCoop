@extends('layouts.admin')

@section('title', 'مدیریت کل EarthCoop - نجم هدا')
@section('page-title', 'Founder Operations')
@section('page-description', 'مرکز یکپارچهٔ مدیریت کل EarthCoop توسط نجم هدا')

@section('content')
<div class="container-fluid py-3" dir="rtl">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h4 class="mb-1">گزارش مدیریتی نجم هدا</h4>
            <div class="text-muted small">بازه گزارش: {{ $hours }} ساعت اخیر</div>
        </div>
        <div class="btn-group" role="group" aria-label="بازه گزارش">
            @foreach([6, 24, 72, 168] as $window)
                <a href="{{ route('admin.najm-hoda.founder-ops.index', ['hours' => $window]) }}"
                   class="btn btn-sm {{ $hours === $window ? 'btn-primary' : 'btn-outline-secondary' }}">
                    {{ $window }} ساعت
                </a>
            @endforeach
        </div>
    </div>

    @php
        $summary = data_get($brief, 'summary', []);
        $authority = data_get($brief, 'authority', []);
        $founderApprovals = data_get($brief, 'founder_approvals', []);
        $coverage = data_get($brief, 'management_coverage', []);
    @endphp

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">بحرانی P0</div><div class="fs-3 fw-bold">{{ data_get($summary, 'P0', 0) }}</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">نیازمند تصمیم P1</div><div class="fs-3 fw-bold">{{ data_get($summary, 'P1', 0) }}</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">صف کار P2</div><div class="fs-3 fw-bold">{{ data_get($summary, 'P2', 0) }}</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">اطلاعات P3</div><div class="fs-3 fw-bold">{{ data_get($summary, 'P3', 0) }}</div></div></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>موارد نیازمند توجه</strong>
                    <span class="badge bg-secondary">{{ data_get($summary, 'total_attention_items', 0) }} مورد</span>
                </div>
                <div class="list-group list-group-flush">
                    @forelse(data_get($brief, 'items', []) as $item)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between gap-3 align-items-start">
                                <div>
                                    <div class="fw-semibold">{{ data_get($item, 'title') }}</div>
                                    <div class="small text-muted mt-1">دامنه: {{ data_get($item, 'domain') }}</div>
                                    @if(data_get($item, 'context.count') !== null)
                                        <div class="small mt-1">تعداد: {{ data_get($item, 'context.count') }}</div>
                                    @endif
                                </div>
                                <span class="badge bg-{{ data_get($item, 'priority') === 'P0' ? 'danger' : (data_get($item, 'priority') === 'P1' ? 'warning' : (data_get($item, 'priority') === 'P2' ? 'info' : 'secondary')) }}">
                                    {{ data_get($item, 'priority') }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="list-group-item text-muted">در این بازه موردی برای توجه مدیرکل ثبت نشده است.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><strong>صف تأیید Founder</strong></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span>منتظر تأیید</span><strong>{{ data_get($founderApprovals, 'pending', 0) }}</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>عقب‌افتاده از SLA</span><strong>{{ data_get($founderApprovals, 'overdue', 0) }}</strong></div>
                    <div class="d-flex justify-content-between"><span>داخل SLA</span><strong>{{ data_get($founderApprovals, 'within_sla', 0) }}</strong></div>
                    <div class="small text-muted mt-3">در این مرحله صفحه فقط خواندنی است و تصمیم approve/reject از این نما انجام نمی‌شود.</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><strong>حدود اختیار نجم هدا</strong></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span>Actionهای تعریف‌شده</span><strong>{{ data_get($authority, 'total_actions', 0) }}</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Delegation فعال</span><strong>{{ data_get($authority, 'active_delegations_count', 0) }}</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Fail-closed</span><strong>{{ data_get($authority, 'fail_closed') ? 'بله' : 'خیر' }}</strong></div>
                    <div class="d-flex justify-content-between"><span>Delegation سراسری</span><strong>{{ data_get($authority, 'delegation_globally_enabled') ? 'فعال' : 'غیرفعال' }}</strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card h-100"><div class="card-header"><strong>کاربران</strong></div><div class="card-body"><div>جدید: {{ data_get($snapshot, 'users.new_members', 0) }}</div><div>تأیید ایمیل‌شده: {{ data_get($snapshot, 'users.new_verified_members', 0) }}</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-header"><strong>پشتیبانی</strong></div><div class="card-body"><div>باز: {{ data_get($snapshot, 'support.open', 0) }}</div><div>بدون مسئول: {{ data_get($snapshot, 'support.unassigned_active', 0) }}</div><div>مهم: {{ data_get($snapshot, 'support.high_priority_active', 0) }}</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-header"><strong>تأیید داده پایه</strong></div><div class="card-body"><div>صنف/تخصص: {{ data_get($snapshot, 'approvals.references.total', 0) }}</div><div>مکان: {{ data_get($snapshot, 'approvals.locations.total', 0) }}</div></div></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card h-100"><div class="card-header"><strong>انتخابات</strong></div><div class="card-body"><div>فعال: {{ data_get($snapshot, 'governance.active_elections', 0) }}</div><div>نزدیک پایان: {{ data_get($snapshot, 'governance.ending_within_24h', 0) }}</div><div>عقب‌افتاده: {{ data_get($snapshot, 'governance.overdue_open', 0) }}</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-header"><strong>دبیرخانه</strong></div><div class="card-body"><div>پرونده باز: {{ data_get($snapshot, 'secretariat.open_cases', 0) }}</div><div>موعد ۲۴ ساعت: {{ data_get($snapshot, 'secretariat.dispatches_due_within_24h', 0) }}</div><div>عقب‌افتاده: {{ data_get($snapshot, 'secretariat.overdue_dispatches', 0) }}</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-header"><strong>نجم بهار</strong></div><div class="card-body"><div>پروژه منتظر بررسی: {{ data_get($snapshot, 'najm_bahar.projects_submitted', 0) }}</div><div>تراکنش نزدیک موعد: {{ data_get($snapshot, 'najm_bahar.scheduled_due_within_24h', 0) }}</div><div>عقب‌افتاده: {{ data_get($snapshot, 'najm_bahar.scheduled_overdue', 0) }}</div></div></div></div>
    </div>

    <div class="card">
        <div class="card-header"><strong>پوشش مدیریت یکپارچه</strong></div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-4">
                <div>کل دامنه‌ها: <strong>{{ data_get($coverage, 'counts.total', 0) }}</strong></div>
                <div>Observed: <strong>{{ data_get($coverage, 'counts.observed', 0) }}</strong></div>
                <div>Managed: <strong>{{ data_get($coverage, 'counts.managed', 0) }}</strong></div>
                <div>پوشش: <strong>{{ data_get($coverage, 'integration_coverage_percent', 0) }}%</strong></div>
            </div>
        </div>
    </div>
</div>
@endsection
