@extends('layouts.admin')

@section('title', 'مرکز کنترل مکان و حکمرانی - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'مرکز کنترل مکان و حکمرانی')
@section('page-description', 'بازبینی انسانی ساختار مکانی، پیشنهادهای مردمی و نگاشت‌های حکمرانی')

@section('content')
<div class="container-fluid py-3" dir="rtl">
    <div class="alert alert-info border-0 shadow-sm mb-4" role="status">
        <strong>نقش نجم هدا:</strong>
        تحلیل، کشف مشابهت و پیشنهاد تصمیم. همهٔ تأیید، رد، ادغام و درخواست مدرک فقط با اقدام صریح مدیر انجام می‌شود.
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">حوزه‌های فعال</div>
                <div class="fs-3 fw-bold">{{ number_format($governanceSummary['active']) }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">حوزه‌های رسمی</div>
                <div class="fs-3 fw-bold">{{ number_format($governanceSummary['official']) }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Community</div>
                <div class="fs-3 fw-bold">{{ number_format($governanceSummary['community']) }}</div>
            </div></div>
        </div>
    </div>

    <section class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent"><h2 class="h5 mb-1">تنظیم حد حمایت مردمی</h2></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.location-governance.settings.update') }}" class="row g-3 align-items-end">
                @csrf
                @method('PUT')
                <div class="col-12 col-md-5">
                    <label class="form-label" for="location_proposal_verification_threshold">حد حمایت پیشنهاد مکان</label>
                    <input id="location_proposal_verification_threshold" name="location_proposal_verification_threshold" type="number" min="1" max="1000000" required class="form-control" value="{{ $verificationThreshold }}">
                    <div class="form-text">پیش‌فرض ۱۰ نفر؛ رسیدن به این حد فقط پرونده را آمادهٔ بررسی انسانی می‌کند.</div>
                </div>
                <div class="col-12 col-md-5">
                    <label class="form-label" for="location_structure_claim_verification_threshold">حد حمایت ادعای ساختاری</label>
                    <input id="location_structure_claim_verification_threshold" name="location_structure_claim_verification_threshold" type="number" min="1" max="1000000" required class="form-control" value="{{ $structureClaimVerificationThreshold }}">
                    <div class="form-text">برای ادعاهایی مانند شهر بدون منطقه یا روستای بدون محله؛ تأیید خودکار انجام نمی‌شود.</div>
                </div>
                <div class="col-12 col-md-2">
                    <button class="btn btn-primary w-100">ذخیره تنظیمات</button>
                </div>
            </form>
        </div>
    </section>

    @include('admin.location-governance.partials.proposal-queue')
    @include('admin.location-governance.partials.structure-claim-queue')
    @include('admin.location-governance.partials.settlement-review-queue')

    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-6">
            @include('admin.location-governance.partials.reference-explorer')
        </div>
        <div class="col-12 col-xl-6">
            @include('admin.location-governance.partials.governance-topology')
        </div>
        <div class="col-12 col-xl-6">
            @include('admin.location-governance.partials.community-overview')
        </div>
        <div class="col-12 col-xl-6">
            @include('admin.location-governance.partials.import-diagnostics')
        </div>
        <div class="col-12">
            @include('admin.location-governance.partials.health-diagnostics')
        </div>
    </div>

    <section class="card border-0 shadow-sm">
        <div class="card-header bg-transparent"><h2 class="h5 mb-0">سیاست‌ها و ایمنی</h2></div>
        <div class="card-body small">
            <p class="mb-1">تصمیم‌های حساس Location/Governance انسانی و audit‌شده‌اند.</p>
            <p class="mb-0">نجم هدا مجاز به خلاصه‌سازی، کشف duplicate/anomaly و recommendation است؛ مسیر autonomous approve/reject/merge یا تغییر خام توپولوژی رسمی وجود ندارد.</p>
        </div>
    </section>
</div>
@endsection
