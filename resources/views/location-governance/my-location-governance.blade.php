@extends('layouts.unified')

@section('title', 'مکان و حکمرانی من - ' . config('app.name', 'EarthCoop'))


@push('styles')
<style>
.location-governance-dashboard{max-width:1080px;padding-bottom:6rem}
.location-governance-edit{min-height:44px}
.governance-chain{position:relative}
.governance-chain-item{position:relative;display:flex;gap:.8rem;padding:.55rem .2rem .55rem 1rem}
.governance-chain-item:not(:last-child)::after{content:"";position:absolute;right:.38rem;top:1.35rem;bottom:-.35rem;width:2px;background:var(--bs-border-color)}
.governance-chain-dot{width:.85rem;height:.85rem;margin-top:.25rem;border-radius:50%;background:var(--bs-primary);flex:0 0 auto;z-index:1}
.membership-icon{width:2.4rem;height:2.4rem;display:grid;place-items:center;border-radius:.75rem;background:rgba(var(--bs-primary-rgb),.1);color:var(--bs-primary)}
.active-membership-box{padding:.75rem;border-radius:.75rem;background:rgba(var(--bs-primary-rgb),.05)}
.observer-memberships{border-top:1px solid var(--bs-border-color);padding-top:.6rem}
.observer-memberships summary{display:flex;align-items:center;justify-content:space-between;gap:1rem;cursor:pointer;min-height:44px;font-weight:600;list-style:none}
.observer-memberships summary::-webkit-details-marker{display:none}
@media(max-width:767.98px){.location-governance-dashboard{padding-left:.75rem;padding-right:.75rem}.location-governance-edit{width:100%}.membership-card .card-body{padding:.9rem!important}.governance-chain-item{padding-top:.45rem;padding-bottom:.45rem}}
</style>
@endpush

@section('content')
@php
    $dimensionLabels = [
        'public' => ['label' => 'عمومی', 'icon' => 'fa-users'],
        'profession' => ['label' => 'صنفی و حرفه‌ای', 'icon' => 'fa-briefcase'],
        'specialty' => ['label' => 'تخصصی', 'icon' => 'fa-graduation-cap'],
        'age' => ['label' => 'گروه سنی', 'icon' => 'fa-user-clock'],
        'gender' => ['label' => 'گروه جنسیتی', 'icon' => 'fa-venus-mars'],
    ];
    $governanceTypeLabels = [
        'global' => 'جهان',
        'continent' => 'قاره',
        'country' => 'کشور',
        'province' => 'استان',
        'county' => 'شهرستان',
        'section' => 'بخش',
        'city' => 'شهر',
        'rural_district' => 'دهستان',
        'urban_region' => 'منطقه شهری',
        'village' => 'روستا',
        'local' => 'حوزه محلی',
        'neighborhood' => 'محله',
    ];
    $baseGovernanceArea = collect($governanceAreas)->first();
    $membershipTotal = collect($membershipsByDimension)->sum(function ($bucket) {
        $bucket = collect($bucket);
        return collect($bucket->get('active', []))->count() + collect($bucket->get('observer', []))->count();
    });
@endphp

<div class="container py-3 py-md-5 location-governance-dashboard" dir="rtl" data-my-location-governance>
    <header class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3 mb-md-4">
        <div>
            <h1 class="h3 mb-1">مکان و حکمرانی من</h1>
            <p class="text-muted mb-0">خلاصه محل سکونت، حوزه پایه و عضویت‌های رسمی شما در ارث‌کوپ.</p>
        </div>
        <a href="{{ route('profile.edit') }}" class="btn btn-outline-primary location-governance-edit"><i class="fas fa-location-dot ms-2"></i>ویرایش محل سکونت</a>
    </header>

    <div class="row g-3 mb-3 mb-md-4">
        <div class="col-12 col-lg-7">
            <section class="card shadow-sm border-0 h-100" aria-labelledby="residence-heading">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <div class="text-muted small mb-1">محل سکونت</div>
                            <h2 id="residence-heading" class="h5 mb-1">{{ $currentResidence?->location?->name ?: $currentResidence?->location?->canonical_name ?: 'ثبت نشده' }}</h2>
                            @if($currentResidence?->location?->canonical_name && $currentResidence->location->canonical_name !== $currentResidence->location->name)
                                <div class="text-muted small">{{ $currentResidence->location->canonical_name }}</div>
                            @endif
                        </div>
                        @if($currentResidence)
                            <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">تأییدشده</span>
                        @endif
                    </div>
                    @if($pendingResidenceIntent?->locationProposal)
                        <div class="alert alert-warning mt-3 mb-0" data-pending-residence-intent>
                            <div class="d-flex flex-wrap align-items-center gap-2"><strong>جزئیات دقیق در انتظار تأیید</strong><span class="badge bg-warning text-dark">در انتظار تأیید</span></div>
                            <div class="small mt-1">{{ $pendingResidenceIntent->locationProposal->canonical_name }} — تا زمان بررسی، حکمرانی از محل تأییدشده محاسبه می‌شود.</div>
                        </div>
                    @endif
                </div>
            </section>
        </div>
        <div class="col-12 col-lg-5">
            <section class="card shadow-sm border-0 h-100" aria-labelledby="base-governance-heading" data-base-governance-summary>
                <div class="card-body p-3 p-md-4">
                    <div class="text-muted small mb-1">حوزه پایه حکمرانی</div>
                    <h2 id="base-governance-heading" class="h5 mb-2">{{ $baseGovernanceArea?->canonical_name ?: 'هنوز تعیین نشده' }}</h2>
                    @if($baseGovernanceArea)
                        <span class="badge text-bg-light border">{{ $governanceTypeLabels[$baseGovernanceArea->governance_type] ?? $baseGovernanceArea->governance_type }}</span>
                    @endif
                    <div class="small text-muted mt-3">{{ $membershipTotal }} عضویت حکمرانی فعال و ناظر</div>
                </div>
            </section>
        </div>
    </div>

    <section class="card shadow-sm border-0 mb-3 mb-md-4" aria-labelledby="governance-heading">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-2">
                <h2 id="governance-heading" class="h5 mb-0">زنجیره حکمرانی رسمی</h2>
                <span class="badge text-bg-light border">{{ collect($governanceAreas)->count() }} سطح</span>
            </div>
            <p class="text-muted small mb-3">از حوزه پایه شما تا سطوح بالاتر؛ جزئیات خرد نشانی و اجتماعات اختیاری در این زنجیره وارد نمی‌شوند.</p>
            <div class="governance-chain" data-governance-chain>
                @forelse($governanceAreas as $area)
                    <div class="governance-chain-item" data-governance-area="{{ $area->id }}">
                        <span class="governance-chain-dot" aria-hidden="true"></span>
                        <div class="min-w-0">
                            <div class="fw-semibold">{{ $area->canonical_name }}</div>
                            <div class="small text-muted">{{ $governanceTypeLabels[$area->governance_type] ?? $area->governance_type }}</div>
                        </div>
                    </div>
                @empty
                    <div class="alert alert-light border mb-0">برای محل فعلی شما هنوز زنجیره حکمرانی رسمی قابل نمایش نیست.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="mb-3 mb-md-4" aria-labelledby="memberships-heading">
        <div class="d-flex align-items-end justify-content-between gap-3 mb-2">
            <div><h2 id="memberships-heading" class="h5 mb-1">عضویت‌های حکمرانی من</h2><p class="text-muted small mb-0">عضویت مستقیم شما در حوزه پایه فعال است؛ سطوح بالادست با نقش ناظر در دسترس‌اند.</p></div>
            <span class="badge bg-primary">{{ $membershipTotal }}</span>
        </div>
        <div class="row g-3">
            @foreach($dimensionLabels as $dimension => $dimensionMeta)
                @php($bucket = collect($membershipsByDimension->get($dimension, [])))
                @php($activeMemberships = collect($bucket->get('active', [])))
                @php($observerMemberships = collect($bucket->get('observer', [])))
                <div class="col-12 col-lg-6">
                    <article class="card shadow-sm border-0 h-100 membership-card" data-membership-dimension="{{ $dimension }}">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="membership-icon"><i class="fas {{ $dimensionMeta['icon'] }}"></i></span>
                                <h3 class="h6 mb-0 flex-grow-1">{{ $dimensionMeta['label'] }}</h3>
                                <span class="badge text-bg-light border">{{ $activeMemberships->count() + $observerMemberships->count() }}</span>
                            </div>
                            <div class="active-membership-box">
                                <div class="d-flex align-items-center justify-content-between mb-2"><span class="small fw-semibold">عضویت فعال</span><span class="badge bg-primary">{{ $activeMemberships->count() }}</span></div>
                                @forelse($activeMemberships as $group)
                                    <div class="small fw-semibold">{{ $group->name ?: $group->dimension_value_key }}@if($group->governanceArea)<span class="text-muted fw-normal"> — {{ $group->governanceArea->canonical_name }}</span>@endif</div>
                                @empty
                                    <div class="text-muted small">عضویت فعالی در این بُعد وجود ندارد.</div>
                                @endforelse
                            </div>
                            <details class="observer-memberships mt-2">
                                <summary><span>عضویت‌های ناظر</span><span class="badge text-bg-secondary">{{ $observerMemberships->count() }}</span></summary>
                                <div class="pt-2">
                                    @forelse($observerMemberships as $group)
                                        <div class="small py-1 border-bottom">{{ $group->name ?: $group->dimension_value_key }}@if($group->governanceArea)<span class="text-muted"> — {{ $group->governanceArea->canonical_name }}</span>@endif</div>
                                    @empty
                                        <div class="text-muted small">عضویت ناظری در این بُعد وجود ندارد.</div>
                                    @endforelse
                                </div>
                            </details>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    </section>

    <section class="card shadow-sm border-0 mb-5" aria-labelledby="communities-heading">
        <div class="card-body p-3 p-md-4">
            <h2 id="communities-heading" class="h5 mb-1">اجتماعات محلی</h2>
            <p class="text-muted small">اجتماعات محلی اختیاری‌اند و سطح تازه‌ای در حکمرانی یا انتخابات رسمی ایجاد نمی‌کنند.</p>
            @forelse($communities as $community)
                <div class="d-flex align-items-center justify-content-between gap-3 py-2 {{ !$loop->last ? 'border-bottom' : '' }}" data-community-area="{{ $community->id }}"><span class="fw-semibold">{{ $community->canonical_name }}</span><span class="badge text-bg-light border">اجتماع اختیاری</span></div>
            @empty
                <div class="alert alert-light border mb-3">در حال حاضر اجتماع محلی فعالی برای محل سکونت شما ثبت نشده است.</div>
            @endforelse
            @if($canCreateCommunity && $currentResidence?->location)
                <form method="POST" action="{{ route('location-governance.community.store', $currentResidence->location) }}" data-community-create-action>@csrf<button type="submit" class="btn btn-outline-primary w-100 w-md-auto">ایجاد اجتماع محلی برای این مکان</button></form>
            @endif
        </div>
    </section>
</div>@endsection
