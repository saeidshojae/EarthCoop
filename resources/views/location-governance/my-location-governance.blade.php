@extends('layouts.unified')

@section('title', 'مکان و حکمرانی من - ' . config('app.name', 'EarthCoop'))

@section('content')
@php
    $dimensionLabels = [
        'public' => 'عمومی',
        'profession' => 'صنفی و حرفه‌ای',
        'specialty' => 'تخصصی',
        'age' => 'گروه سنی',
        'gender' => 'گروه جنسیتی',
    ];
@endphp

<div class="container py-4 py-md-5" dir="rtl" data-my-location-governance>
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-2">مکان و حکمرانی من</h1>
            <p class="text-muted mb-0">نمای یکپارچه محل سکونت، زنجیره حکمرانی رسمی و عضویت‌های مکانی شما در ارث‌کوپ.</p>
        </div>
        <a href="{{ route('profile.edit') }}" class="btn btn-outline-primary align-self-start">
            ویرایش محل سکونت
        </a>
    </div>

    <section class="card shadow-sm border-0 mb-4" aria-labelledby="residence-heading">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                <h2 id="residence-heading" class="h5 mb-0">محل سکونت تأییدشده</h2>
                @if($currentResidence)
                    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">تأییدشده</span>
                @endif
            </div>

            @if($currentResidence?->location)
                <div class="fw-semibold fs-5">{{ $currentResidence->location->name ?: $currentResidence->location->canonical_name }}</div>
                @if($currentResidence->location->canonical_name && $currentResidence->location->canonical_name !== $currentResidence->location->name)
                    <div class="text-muted small mt-1">{{ $currentResidence->location->canonical_name }}</div>
                @endif
            @else
                <div class="alert alert-light border mb-0">هنوز محل سکونت تأییدشده‌ای برای حساب شما ثبت نشده است.</div>
            @endif

            @if($pendingResidenceIntent?->locationProposal)
                <div class="alert alert-warning mt-3 mb-0" data-pending-residence-intent>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <strong>جزئیات دقیق در انتظار تأیید</strong>
                        <span class="badge bg-warning text-dark">در انتظار تأیید</span>
                    </div>
                    <div>{{ $pendingResidenceIntent->locationProposal->canonical_name }}</div>
                    <div class="small mt-1">
                        این مورد هنوز مکان تأییدشده یا سطح رسمی حکمرانی نیست؛ تا زمان بررسی، حکمرانی شما از محل تأییدشده بالا محاسبه می‌شود.
                    </div>
                </div>
            @endif
        </div>
    </section>

    <section class="card shadow-sm border-0 mb-4" aria-labelledby="governance-heading">
        <div class="card-body p-3 p-md-4">
            <h2 id="governance-heading" class="h5 mb-3">زنجیره حکمرانی رسمی</h2>
            <p class="text-muted small">این زنجیره فقط از حوزه‌های رسمی Governance Area ساخته می‌شود و مستقل از جزئیات خرد نشانی و اجتماعات اختیاری است.</p>

            @forelse($governanceAreas as $area)
                <div class="d-flex align-items-center gap-3 py-2 {{ !$loop->last ? 'border-bottom' : '' }}" data-governance-area="{{ $area->id }}">
                    <span class="badge rounded-pill text-bg-light border">{{ $area->governance_type }}</span>
                    <div class="fw-semibold">{{ $area->canonical_name }}</div>
                </div>
            @empty
                <div class="alert alert-light border mb-0">برای محل فعلی شما هنوز زنجیره حکمرانی رسمی قابل نمایش نیست.</div>
            @endforelse
        </div>
    </section>

    <section class="card shadow-sm border-0 mb-4" aria-labelledby="memberships-heading">
        <div class="card-body p-3 p-md-4">
            <h2 id="memberships-heading" class="h5 mb-3">عضویت‌های حکمرانی من</h2>
            <p class="text-muted small">عضویت فعال با نقش مستقیم شما در حوزه پایه نمایش داده می‌شود؛ عضویت ناظر دسترسی شما به حوزه‌های بالادست را نشان می‌دهد.</p>

            <div class="row g-3">
                @foreach($dimensionLabels as $dimension => $label)
                    @php($bucket = collect($membershipsByDimension->get($dimension, [])))
                    @php($activeMemberships = collect($bucket->get('active', [])))
                    @php($observerMemberships = collect($bucket->get('observer', [])))
                    <div class="col-12 col-lg-6">
                        <div class="border rounded-3 p-3 h-100" data-membership-dimension="{{ $dimension }}">
                            <h3 class="h6 mb-3">{{ $label }}</h3>

                            <div class="mb-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="fw-semibold">عضویت فعال</span>
                                    <span class="badge bg-primary">{{ $activeMemberships->count() }}</span>
                                </div>
                                @forelse($activeMemberships as $group)
                                    <div class="small py-1">
                                        {{ $group->name ?: $group->dimension_value_key }}
                                        @if($group->governanceArea)
                                            <span class="text-muted">— {{ $group->governanceArea->canonical_name }}</span>
                                        @endif
                                    </div>
                                @empty
                                    <div class="text-muted small">عضویت فعالی در این بُعد وجود ندارد.</div>
                                @endforelse
                            </div>

                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="fw-semibold">عضویت ناظر</span>
                                    <span class="badge text-bg-secondary">{{ $observerMemberships->count() }}</span>
                                </div>
                                @forelse($observerMemberships as $group)
                                    <div class="small py-1">
                                        {{ $group->name ?: $group->dimension_value_key }}
                                        @if($group->governanceArea)
                                            <span class="text-muted">— {{ $group->governanceArea->canonical_name }}</span>
                                        @endif
                                    </div>
                                @empty
                                    <div class="text-muted small">عضویت ناظری در این بُعد وجود ندارد.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0" aria-labelledby="communities-heading">
        <div class="card-body p-3 p-md-4">
            <h2 id="communities-heading" class="h5 mb-3">اجتماعات محلی</h2>
            <p class="text-muted small">اجتماعات محلی اختیاری و جدا از زنجیره رسمی حکمرانی و انتخابات رسمی هستند.</p>

            @forelse($communities as $community)
                <div class="d-flex align-items-center justify-content-between gap-3 py-2 {{ !$loop->last ? 'border-bottom' : '' }}" data-community-area="{{ $community->id }}">
                    <span class="fw-semibold">{{ $community->canonical_name }}</span>
                    <span class="badge text-bg-light border">اجتماع اختیاری</span>
                </div>
            @empty
                <div class="alert alert-light border mb-0">در حال حاضر اجتماع محلی فعالی برای محل سکونت شما ثبت نشده است.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
