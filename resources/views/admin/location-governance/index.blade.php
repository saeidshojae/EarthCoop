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
        <div class="card-header bg-transparent py-3">
            <h2 class="h5 mb-1">پیشنهادهای در انتظار بررسی</h2>
            <p class="small text-muted mb-0">آستانهٔ فعلی راستی‌آزمایی: {{ $verificationThreshold }} کاربر متمایز</p>
        </div>
        <div class="card-body">
            @forelse($proposals as $proposal)
                @php($review = $hodaReviews->get($proposal->id, []))
                <article class="border rounded-3 p-3 mb-3">
                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                        <div>
                            <h3 class="h6 mb-1">{{ $proposal->canonical_name }}</h3>
                            <div class="small text-muted">
                                وضعیت: {{ $proposal->status->value ?? $proposal->status }} ·
                                نوع: {{ $proposal->type?->key ?: '—' }} ·
                                شواهد: {{ $proposal->evidence_count }}
                            </div>
                            <div class="small text-muted mt-1">
                                والد: {{ $proposal->parentLocation?->canonical_name ?: $proposal->parentLocation?->name ?: '—' }}
                            </div>
                        </div>
                        <div class="alert alert-warning mb-0 py-2 px-3">
                            <strong>پیشنهاد نجم هدا — نیازمند تایید انسانی:</strong>
                            {{ $review['recommendation'] ?? 'review' }}
                            @if(!empty($review['duplicate_candidate_id']))
                                <span class="d-block small">مکان مشابه: #{{ $review['duplicate_candidate_id'] }}</span>
                            @endif
                        </div>
                    </div>

                    <p class="small mt-3 mb-2">{{ $review['rationale'] ?? 'بازبینی انسانی لازم است.' }}</p>

                    <div class="row g-2 mt-1">
                        <div class="col-12 col-xl-6">
                            <form method="POST" action="{{ route('admin.location-governance.proposals.approve', $proposal) }}" class="d-flex gap-2">
                                @csrf
                                <input class="form-control" name="reason" required minlength="4" maxlength="1000" placeholder="دلیل تأیید انسانی">
                                <button class="btn btn-success" type="submit">تأیید</button>
                            </form>
                        </div>
                        <div class="col-12 col-xl-6">
                            <form method="POST" action="{{ route('admin.location-governance.proposals.reject', $proposal) }}" class="d-flex gap-2">
                                @csrf
                                <input class="form-control" name="reason" required minlength="4" maxlength="1000" placeholder="دلیل رد انسانی">
                                <button class="btn btn-outline-danger" type="submit">رد</button>
                            </form>
                        </div>
                        <div class="col-12 col-xl-6">
                            <form method="POST" action="{{ route('admin.location-governance.proposals.request-evidence', $proposal) }}" class="d-flex gap-2">
                                @csrf
                                <input class="form-control" name="reason" required minlength="4" maxlength="1000" placeholder="مدرک یا توضیح موردنیاز">
                                <button class="btn btn-outline-secondary" type="submit">مدرک بیشتر</button>
                            </form>
                        </div>
                        <div class="col-12 col-xl-6">
                            <form method="POST" action="{{ route('admin.location-governance.proposals.merge', $proposal) }}" class="d-flex gap-2">
                                @csrf
                                <input class="form-control" type="number" min="1" name="existing_location_id" required value="{{ $review['duplicate_candidate_id'] ?? '' }}" placeholder="ID مکان موجود">
                                <input class="form-control" name="reason" required minlength="4" maxlength="1000" placeholder="دلیل ادغام">
                                <button class="btn btn-outline-primary" type="submit">ادغام</button>
                            </form>
                        </div>
                    </div>
                </article>
            @empty
                <p class="text-muted mb-0">پیشنهاد بازی برای بازبینی وجود ندارد.</p>
            @endforelse
        </div>
    </section>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <section class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent"><h2 class="h5 mb-0">واردسازی</h2></div>
                <div class="card-body">
                    @forelse($importRuns as $run)
                        <div class="border-bottom py-2 small">
                            <strong>{{ $run->country_code }}</strong> · {{ $run->source }} · {{ $run->dataset_version }}
                            <span class="badge text-bg-light">{{ $run->status }}</span>
                            <div class="text-muted">ایجاد {{ $run->creates }} · بروزرسانی {{ $run->updates }} · تعارض {{ $run->conflicts }}</div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">هنوز اجرای واردسازی ثبت نشده است.</p>
                    @endforelse
                </div>
            </section>
        </div>
        <div class="col-12 col-lg-6">
            <section class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent"><h2 class="h5 mb-0">نگاشت حکمرانی</h2></div>
                <div class="card-body">
                    <p class="mb-2">حوزه‌های رسمی و Community از مسیر canonical GovernanceArea مدیریت می‌شوند.</p>
                    <p class="small text-muted mb-0">این صفحه فقط نمای مدیریتی و بازبینی را ارائه می‌کند؛ تغییر توپولوژی رسمی باید از workflow سیاست‌گذاری مربوط انجام شود.</p>
                </div>
            </section>
        </div>
        <div class="col-12">
            <section class="card border-0 shadow-sm">
                <div class="card-header bg-transparent"><h2 class="h5 mb-0">سیاست‌ها و ایمنی</h2></div>
                <div class="card-body small">
                    <p class="mb-1">تصمیم‌های حساس Location/Governance انسانی و audit‌شده‌اند.</p>
                    <p class="mb-0">نجم هدا مجاز به خلاصه‌سازی، کشف duplicate/anomaly و recommendation است؛ مسیر autonomous approve/reject/merge وجود ندارد.</p>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
