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
.location-governance-tabs .nav-link{min-height:44px;font-weight:700}
.community-option{border:1px solid var(--bs-border-color);border-radius:.9rem;padding:1rem}
.community-location-icon{width:2.5rem;height:2.5rem;display:grid;place-items:center;border-radius:.75rem;background:rgba(var(--bs-primary-rgb),.1);color:var(--bs-primary)}
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

    <ul class="nav nav-tabs nav-fill location-governance-tabs mb-3" id="locationGovernanceTabs" role="tablist">
        <li class="nav-item" role="presentation"><button class="nav-link active" id="official-tab" data-bs-toggle="tab" data-bs-target="#official-governance" type="button" role="tab" aria-controls="official-governance" aria-selected="true">حکمرانی رسمی</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="communities-tab" data-bs-toggle="tab" data-bs-target="#local-communities" type="button" role="tab" aria-controls="local-communities" aria-selected="false">اجتماعات محلی</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="official-governance" role="tabpanel" aria-labelledby="official-tab" tabindex="0">
            <section class="card shadow-sm border-0 mb-3 mb-md-4" aria-labelledby="governance-heading">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex align-items-center justify-content-between gap-3 mb-2"><h2 id="governance-heading" class="h5 mb-0">زنجیره حکمرانی رسمی</h2><span class="badge text-bg-light border">{{ collect($governanceAreas)->count() }} سطح</span></div>
                    <p class="text-muted small mb-3">از حوزه پایه شما تا سطوح بالاتر؛ جزئیات خرد نشانی و اجتماعات اختیاری در این زنجیره وارد نمی‌شوند.</p>
                    <div class="governance-chain" data-governance-chain>
                        @forelse($governanceAreas as $area)
                            <div class="governance-chain-item" data-governance-area="{{ $area->id }}"><span class="governance-chain-dot" aria-hidden="true"></span><div class="min-w-0"><div class="fw-semibold">{{ $area->canonical_name }}</div><div class="small text-muted">{{ $governanceTypeLabels[$area->governance_type] ?? $area->governance_type }}</div></div></div>
                        @empty
                            <div class="alert alert-light border mb-0">برای محل فعلی شما هنوز زنجیره حکمرانی رسمی قابل نمایش نیست.</div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="mb-3 mb-md-4" aria-labelledby="memberships-heading">
                <div class="d-flex align-items-end justify-content-between gap-3 mb-2"><div><h2 id="memberships-heading" class="h5 mb-1">عضویت‌های حکمرانی من</h2><p class="text-muted small mb-0">عضویت مستقیم شما در حوزه پایه فعال است؛ سطوح بالادست با نقش ناظر در دسترس‌اند.</p></div><span class="badge bg-primary">{{ $membershipTotal }}</span></div>
                <div class="row g-3">
                    @foreach($dimensionLabels as $dimension => $dimensionMeta)
                        @php($bucket = collect($membershipsByDimension->get($dimension, [])))
                        @php($activeMemberships = collect($bucket->get('active', [])))
                        @php($observerMemberships = collect($bucket->get('observer', [])))
                        <div class="col-12 col-lg-6"><article class="card shadow-sm border-0 h-100 membership-card" data-membership-dimension="{{ $dimension }}"><div class="card-body p-3">
                            <div class="d-flex align-items-center gap-2 mb-3"><span class="membership-icon"><i class="fas {{ $dimensionMeta['icon'] }}"></i></span><h3 class="h6 mb-0 flex-grow-1">{{ $dimensionMeta['label'] }}</h3><span class="badge text-bg-light border">{{ $activeMemberships->count() + $observerMemberships->count() }}</span></div>
                            <div class="active-membership-box"><div class="d-flex align-items-center justify-content-between mb-2"><span class="small fw-semibold">عضویت فعال</span><span class="badge bg-primary">{{ $activeMemberships->count() }}</span></div>
                                @forelse($activeMemberships as $group)<div class="small fw-semibold">{{ $group->name ?: $group->dimension_value_key }}@if($group->governanceArea)<span class="text-muted fw-normal"> — {{ $group->governanceArea->canonical_name }}</span>@endif</div>@empty<div class="text-muted small">عضویت فعالی در این بُعد وجود ندارد.</div>@endforelse
                            </div>
                            <details class="observer-memberships mt-2"><summary><span>عضویت‌های ناظر</span><span class="badge text-bg-secondary">{{ $observerMemberships->count() }}</span></summary><div class="pt-2">@forelse($observerMemberships as $group)<div class="small py-1 border-bottom">{{ $group->name ?: $group->dimension_value_key }}@if($group->governanceArea)<span class="text-muted"> — {{ $group->governanceArea->canonical_name }}</span>@endif</div>@empty<div class="text-muted small">عضویت ناظری در این بُعد وجود ندارد.</div>@endforelse</div></details>
                        </div></article></div>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="tab-pane fade" id="local-communities" role="tabpanel" aria-labelledby="communities-tab" tabindex="0" data-local-communities-tab>
            <section class="card shadow-sm border-0 mb-5" aria-labelledby="communities-heading"><div class="card-body p-3 p-md-4">
                <h2 id="communities-heading" class="h5 mb-1">اجتماعات محلی من</h2>
                <p class="text-muted small mb-3">خیابان، کوچه، مجتمع و ساختمان هرکدام می‌توانند اجتماع محلی مستقل خود را داشته باشند. این اجتماعات اختیاری‌اند و سطح تازه‌ای در حکمرانی یا انتخابات رسمی ایجاد نمی‌کنند.</p>
                @if($communityOptions->isEmpty())
                    <div class="alert alert-info border mb-3" data-community-location-guide>
                        <strong>برای ساخت اجتماع محلی، ابتدا نشانی محلی خود را تکمیل کنید.</strong>
                        <div class="small mt-1">ثبت جزئیات زیر محله اجباری نیست؛ اما با افزودن خیابان، کوچه، مجتمع یا ساختمان، می‌توانید برای هرکدام اجتماع محلی مستقل ایجاد یا مشاهده کنید.</div>
                    </div>
                    <a href="{{ route('profile.edit') }}" class="btn btn-primary w-100 w-md-auto"><i class="fas fa-location-dot ms-2"></i>تکمیل نشانی و افزودن مکان محلی</a>
                @else
                    <div class="d-grid gap-3" data-community-options>
                        @foreach($communityOptions as $option)
                            @php($location = $option['location'])
                            @php($community = $option['community'])
                            @php($localTypeLabels = ['street' => 'خیابان', 'alley' => 'کوچه', 'complex' => 'مجتمع', 'building' => 'ساختمان'])
                            <article class="community-option" data-community-location="{{ $location->id }}">
                                <div class="d-flex align-items-start gap-3">
                                    <span class="community-location-icon"><i class="fas fa-people-roof"></i></span>
                                    <div class="flex-grow-1 min-w-0"><div class="small text-muted">{{ $localTypeLabels[$location->type?->key] ?? 'مکان محلی' }}</div><h3 class="h6 mb-1">{{ $location->name ?: $location->canonical_name }}</h3>
                                        @if($community)<span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">اجتماع فعال</span>
                                        @elseif($pendingResidenceIntent)<span class="badge bg-warning text-dark">در انتظار تأیید نشانی</span>
                                        @else<span class="badge text-bg-light border">هنوز ایجاد نشده</span>@endif
                                    </div>
                                </div>
                                @if($community)
                                    <div class="small mt-3">اجتماع «{{ $community->canonical_name }}» برای این مکان فعال است.</div>
                                @elseif($option['can_create'])
                                    <form method="POST" action="{{ route('location-governance.community.store', $location) }}" class="mt-3" data-community-create-action>@csrf<button type="submit" class="btn btn-outline-primary w-100 w-md-auto">ایجاد اجتماع این {{ $localTypeLabels[$location->type?->key] ?? 'مکان' }}</button></form>
                                @elseif($pendingResidenceIntent)
                                    <div class="small text-muted mt-3">پس از تأیید جزئیات نشانی، امکان ایجاد اجتماع برای مکان‌های تأییدشده فعال می‌شود.</div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </div></section>
        </div>
    </div>
</div>@endsection
