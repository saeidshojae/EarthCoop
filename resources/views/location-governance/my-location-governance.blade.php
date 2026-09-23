@extends('layouts.unified')

@section('title', 'مکان و حکمرانی من - ' . config('app.name', 'EarthCoop'))


@push('styles')
<style>
.location-governance-dashboard{max-width:1040px;padding-bottom:6rem}
.location-page-header{padding-bottom:.25rem}
.location-page-header h1{font-weight:800;letter-spacing:-.02em}
.location-hero{background:linear-gradient(135deg,rgba(var(--bs-primary-rgb),.075),rgba(255,255,255,.98));border:1px solid rgba(var(--bs-primary-rgb),.14)!important;border-radius:1rem}
.location-hero-name{font-size:1.12rem;font-weight:800}
.location-path-disclosure{border-top:1px solid rgba(var(--bs-primary-rgb),.1);margin-top:.85rem;padding-top:.75rem}
.location-path-disclosure>summary{cursor:pointer;list-style:none;color:var(--bs-primary);font-size:.82rem;font-weight:700}
.location-path-disclosure>summary::-webkit-details-marker{display:none}
.location-path{display:flex;flex-wrap:wrap;align-items:center;gap:.35rem .55rem;margin-top:.7rem}
.location-path-item{display:inline-flex;align-items:center;gap:.45rem;font-weight:600}
.location-path-item:not(:last-child)::after{content:"‹";color:var(--bs-secondary-color);font-weight:400}
.governance-overview{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}
.governance-stat{padding:.85rem;border:1px solid var(--bs-border-color);border-radius:.85rem;background:var(--bs-body-bg)}
.governance-stat strong{display:block;font-size:1.35rem;line-height:1.2;margin-bottom:.25rem}
.governance-disclosure{border:1px solid var(--bs-border-color);border-radius:.9rem;overflow:hidden}
.governance-disclosure>summary{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.9rem 1rem;cursor:pointer;font-weight:700;list-style:none;background:rgba(var(--bs-primary-rgb),.035)}
.governance-disclosure>summary::-webkit-details-marker{display:none}
.governance-disclosure[open]>summary{border-bottom:1px solid var(--bs-border-color)}
.governance-disclosure-body{padding:.85rem 1rem}
.membership-summary-row{border:1px solid var(--bs-border-color);border-radius:.9rem;overflow:hidden;background:var(--bs-body-bg)}
.membership-summary-row>summary{display:flex;align-items:center;gap:.7rem;padding:.85rem 1rem;cursor:pointer;list-style:none;min-height:54px}
.membership-summary-row>summary::-webkit-details-marker{display:none}
.membership-summary-row[open]>summary{border-bottom:1px solid var(--bs-border-color);background:rgba(var(--bs-primary-rgb),.025)}
.membership-summary-counts{margin-inline-start:auto;display:flex;gap:.4rem;flex-wrap:wrap}
.membership-total-pill{display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .65rem;border-radius:999px;background:rgba(var(--bs-primary-rgb),.08);color:var(--bs-primary);font-size:.78rem;font-weight:700}
.location-governance-edit{min-height:46px;border:0;border-radius:.85rem;padding:.7rem 1.05rem;font-weight:800;color:#fff;background:linear-gradient(135deg,var(--bs-primary),#7c3aed);box-shadow:0 .45rem 1.15rem rgba(var(--bs-primary-rgb),.2);transition:transform .18s ease,box-shadow .18s ease,filter .18s ease;position:relative;overflow:hidden}
.location-governance-edit::after{content:"";position:absolute;inset:-50%;background:linear-gradient(110deg,transparent 38%,rgba(255,255,255,.24) 50%,transparent 62%);transform:translateX(65%);transition:transform .55s ease}
.location-governance-edit:hover,.location-governance-edit:focus-visible{color:#fff;transform:translateY(-2px);box-shadow:0 .7rem 1.45rem rgba(var(--bs-primary-rgb),.28);filter:saturate(1.08)}
.location-governance-edit:hover::after,.location-governance-edit:focus-visible::after{transform:translateX(-65%)}
.location-governance-edit:active{transform:translateY(0);box-shadow:0 .3rem .75rem rgba(var(--bs-primary-rgb),.2)}
.location-governance-edit i{transition:transform .18s ease}
.location-governance-edit:hover i{transform:translateY(-1px) scale(1.08)}
.governance-chain{position:relative}
.governance-chain-item{position:relative;display:flex;gap:.8rem;padding:.55rem .2rem .55rem 1rem}
.governance-chain-item:not(:last-child)::after{content:"";position:absolute;right:.38rem;top:1.35rem;bottom:-.35rem;width:2px;background:var(--bs-border-color)}
.governance-chain-dot{width:.85rem;height:.85rem;margin-top:.25rem;border-radius:50%;background:var(--bs-primary);flex:0 0 auto;z-index:1}
.membership-icon{width:2.4rem;height:2.4rem;display:grid;place-items:center;border-radius:.75rem;background:rgba(var(--bs-primary-rgb),.1);color:var(--bs-primary)}
.active-membership-box{padding:.75rem;border-radius:.75rem;background:rgba(var(--bs-primary-rgb),.05)}
.observer-memberships{border-top:1px solid var(--bs-border-color);padding-top:.6rem}
.observer-memberships summary{display:flex;align-items:center;justify-content:space-between;gap:1rem;cursor:pointer;min-height:44px;font-weight:600;list-style:none}
.observer-memberships summary::-webkit-details-marker{display:none}
.location-governance-tabs{gap:.45rem;padding:.35rem;border:1px solid rgba(var(--bs-primary-rgb),.1);border-radius:1rem;background:rgba(var(--bs-primary-rgb),.035);box-shadow:inset 0 1px 0 rgba(255,255,255,.7)}
.location-governance-tabs.nav-tabs{border-bottom:0}
.location-governance-tabs .nav-item{min-width:0}
.location-governance-tabs .nav-link{min-height:46px;border:0!important;border-radius:.75rem!important;font-weight:800;color:var(--bs-secondary-color);position:relative;transition:color .2s ease,background-color .2s ease,box-shadow .2s ease,transform .2s ease}
.location-governance-tabs .nav-link::before{content:"";position:absolute;right:50%;left:50%;bottom:.35rem;height:3px;border-radius:999px;background:linear-gradient(90deg,var(--bs-primary),#7c3aed);transition:right .22s ease,left .22s ease}
.location-governance-tabs .nav-link:hover{color:var(--bs-primary);background:rgba(var(--bs-primary-rgb),.055);transform:translateY(-1px)}
.location-governance-tabs .nav-link.active{color:var(--bs-primary);background:var(--bs-body-bg);box-shadow:0 .3rem .85rem rgba(31,41,55,.09)}
.location-governance-tabs .nav-link.active::before{right:22%;left:22%}
.location-governance-tabs .nav-link:focus-visible{outline:3px solid rgba(var(--bs-primary-rgb),.22);outline-offset:2px}
.community-option{border:1px solid var(--bs-border-color);border-radius:.9rem;padding:1rem}
.community-location-icon{width:2.5rem;height:2.5rem;display:grid;place-items:center;border-radius:.75rem;background:rgba(var(--bs-primary-rgb),.1);color:var(--bs-primary)}
@media(prefers-reduced-motion:reduce){.location-governance-edit,.location-governance-edit::after,.location-governance-edit i,.location-governance-tabs .nav-link,.location-governance-tabs .nav-link::before{transition:none!important}.location-governance-edit:hover,.location-governance-tabs .nav-link:hover{transform:none}}
@media(max-width:767.98px){.location-governance-dashboard{padding-left:.75rem;padding-right:.75rem}.location-page-header{text-align:center}.location-page-header h1{font-size:1.35rem}.location-hero .card-body{padding:1rem!important}.location-hero-name{font-size:1.05rem}.location-governance-edit{width:100%;min-height:48px}.location-governance-tabs{gap:.25rem;padding:.28rem}.location-governance-tabs .nav-link{min-height:45px;padding:.55rem .35rem;font-size:.88rem}.location-path{font-size:.82rem;line-height:1.8}.governance-overview{grid-template-columns:repeat(3,minmax(0,1fr));gap:.45rem}.governance-stat{padding:.7rem .45rem;text-align:center}.governance-stat strong{font-size:1.15rem;margin-bottom:.2rem}.governance-stat span{font-size:.72rem!important}.membership-summary-row>summary{align-items:center;flex-wrap:nowrap;padding:.75rem}.membership-summary-counts{width:auto;margin-inline-start:auto}.membership-icon{width:2.15rem;height:2.15rem}.governance-chain-item{padding-top:.45rem;padding-bottom:.45rem}}
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
        'urban_region' => 'منطقه',
        'village' => 'روستا',
        'local' => 'حوزه محلی',
        'neighborhood' => 'محله',
    ];
    $pendingBaseProposal = collect($pendingGovernanceProposals ?? [])->first();
    $pendingBaseStructuralClaim = collect($pendingGovernanceStructuralClaims ?? [])->first();
    $baseGovernanceArea = collect($governanceAreas)->first();
    $membershipTotal = collect($membershipsByDimension)->sum(function ($bucket) {
        $bucket = collect($bucket);
        return collect($bucket->get('active', []))->count() + collect($bucket->get('observer', []))->count();
    });
    $activeMembershipTotal = collect($membershipsByDimension)->sum(fn ($bucket) => collect(collect($bucket)->get('active', []))->count());
    $observerMembershipTotal = collect($membershipsByDimension)->sum(fn ($bucket) => collect(collect($bucket)->get('observer', []))->count());
    $residencePath = $currentResidence?->location
        ? app(\App\Services\LocationGovernance\LocationTreeResolver::class)->ancestors($currentResidence->location)->push($currentResidence->location)
        : collect();
    $displayAreaName = static function ($area) {
        if ($area === null) {
            return '—';
        }

        $locale = app()->getLocale();
        $language = strtolower((string) strtok(str_replace('_', '-', $locale), '-'));
        $localized = is_array($area->localized_names) ? $area->localized_names : [];
        $location = $area->locations?->first();
        $locationLocalized = is_array($location?->localized_names) ? $location->localized_names : [];

        // Preserve the independent governance-area name when localized, then
        // fall back to its mapped location before using a canonical identifier.
        return $localized[$locale]
            ?? $localized[$language]
            ?? $locationLocalized[$locale]
            ?? $locationLocalized[$language]
            ?? \App\Support\GovernanceAreaDisplayName::for($area);
    };
    $displayLocationName = static fn ($location): string => $location
        ? \App\Support\LocationDisplayName::for($location)
        : '—';
    $baseDisplayName = $pendingBaseProposal ? \App\Support\LocationDisplayName::for($pendingBaseProposal) : null
        ?? ($pendingBaseStructuralClaim?->location ? $displayLocationName($pendingBaseStructuralClaim->location) : null)
        ?? ($baseGovernanceArea ? $displayAreaName($baseGovernanceArea) : '—');
    $baseDisplayType = $pendingBaseProposal?->type?->key
        ?? $pendingBaseStructuralClaim?->location?->type?->key
        ?? $baseGovernanceArea?->governance_type;
    $hasPendingBase = $pendingBaseProposal !== null || $pendingBaseStructuralClaim !== null;
@endphp

<div class="container py-3 py-md-5 location-governance-dashboard" dir="rtl" data-my-location-governance>
    <header class="location-page-header mb-3 mb-md-4">
        <div>
            <h1 class="h3 mb-1">مکان و حکمرانی من</h1>
            <p class="text-muted mb-0">خلاصه محل سکونت، حوزه پایه و عضویت‌های رسمی شما در ارث‌کوپ.</p>
        </div>
    </header>

    <section class="card shadow-sm border-0 location-hero mb-3 mb-md-4" data-base-governance-summary>
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="text-muted small">محل سکونت من</span>
                        @if($currentResidence)<span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">تأییدشده</span>@endif
                    </div>
                    <div class="location-hero-name mb-2">{{ $currentResidence?->location ? $displayLocationName($currentResidence->location) : 'محل سکونت ثبت نشده است.' }}</div>
                    <div class="d-flex flex-wrap align-items-center gap-2 small">
                        <span class="text-muted">حوزه پایه حکمرانی:</span>
                        <strong>{{ $baseDisplayName }}</strong>
                        @if($baseDisplayType)<span class="badge text-bg-light border">{{ $governanceTypeLabels[$baseDisplayType] ?? $baseDisplayType }}</span>@endif
                        @if($hasPendingBase)<span class="badge bg-warning text-dark">در انتظار تأیید</span>@endif
                    </div>
                    @if($residencePath->isNotEmpty())
                        <details class="location-path-disclosure">
                            <summary><i class="fas fa-route ms-1" aria-hidden="true"></i>مشاهده مسیر کامل محل سکونت</summary>
                            <div class="location-path" aria-label="مسیر محل سکونت">
                                @foreach($residencePath as $location)
                                    <span class="location-path-item">{{ $displayLocationName($location) }}</span>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </div>
                <div class="align-self-lg-center">
                    <a href="{{ route('profile.edit') }}?section=location#section-location" class="btn location-governance-edit"><i class="fas fa-location-dot ms-2"></i>ویرایش محل سکونت</a>
                </div>
            </div>
            @if($pendingResidenceIntent?->locationProposal)
                <div class="alert alert-warning mt-3 mb-0" data-pending-residence-intent>
                    <div class="d-flex flex-wrap align-items-center gap-2"><strong>جزئیات دقیق در انتظار تأیید</strong><span class="badge bg-warning text-dark">در انتظار تأیید</span></div>
                    <div class="small mt-1">{{ \App\Support\LocationDisplayName::for($pendingResidenceIntent->locationProposal) }} — عضویت‌های این سطح تا زمان بررسی مکان، در انتظار تأیید می‌مانند.</div>
                </div>
            @endif
            @if($pendingResidenceIntent?->referenceSettlementResidenceClaim?->settlement)
                @php
                    $pendingSettlement = $pendingResidenceIntent->referenceSettlementResidenceClaim->settlement;
                    $pendingSettlementClaim = $pendingResidenceIntent->referenceSettlementResidenceClaim;
                @endphp
                <div class="alert alert-warning mt-3 mb-0" data-pending-reference-settlement>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <strong>محل دقیق آبادی در انتظار بررسی</strong>
                        <span class="badge bg-warning text-dark">در انتظار تأیید</span>
                    </div>
                    <div class="small mt-1">
                        {{ $pendingSettlement->name_fa ?: $pendingSettlement->external_id }}
                        — وضعیت درخواست: {{ $pendingSettlementClaim->status }}.
                        حوزهٔ رسمی و حق رأی این آبادی تا تصمیم مستقل حکمرانی ایجاد نمی‌شود.
                    </div>
                </div>
            @endif
            @if($pendingBaseStructuralClaim?->location)
                <div class="alert alert-warning mt-3 mb-0" data-pending-structural-base>
                    <div class="d-flex flex-wrap align-items-center gap-2"><strong>حوزه پایه در انتظار تأیید ساختار</strong><span class="badge bg-warning text-dark">در انتظار تأیید</span></div>
                    <div class="small mt-1">{{ $displayLocationName($pendingBaseStructuralClaim->location) }} — تا تأیید «بدون محله»، عضویت این سطح رسمی و انتخاباتی نمی‌شود و سطوح بالاتر ناظر می‌مانند.</div>
                </div>
            @endif
        </div>
    </section>

    <ul class="nav nav-tabs nav-fill location-governance-tabs mb-3" id="locationGovernanceTabs" role="tablist">
        <li class="nav-item" role="presentation"><button class="nav-link active" id="official-tab" data-bs-toggle="tab" data-bs-target="#official-governance" type="button" role="tab" aria-controls="official-governance" aria-selected="true">حکمرانی رسمی</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="communities-tab" data-bs-toggle="tab" data-bs-target="#local-communities" type="button" role="tab" aria-controls="local-communities" aria-selected="false">اجتماعات محلی</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="official-governance" role="tabpanel" aria-labelledby="official-tab" tabindex="0">
            <section class="card shadow-sm border-0 mb-3 mb-md-4" aria-labelledby="governance-heading">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                        <div><h2 id="governance-heading" class="h5 mb-1">حکمرانی رسمی من</h2><p class="text-muted small mb-0">حوزه پایه شما مبنای حضور رسمی است و سطوح بالاتر به‌صورت ناظر در دسترس‌اند.</p></div>
                    </div>
                    <div class="governance-overview mb-3">
                        <div class="governance-stat"><strong>{{ $governanceLevelCount ?? collect($governanceAreas)->count() }}</strong><span class="small text-muted">سطح حکمرانی</span></div>
                        <div class="governance-stat"><strong>{{ $activeMembershipTotal }}</strong><span class="small text-muted">عضویت فعال</span></div>
                        <div class="governance-stat"><strong>{{ $observerMembershipTotal }}</strong><span class="small text-muted">عضویت ناظر</span></div>
                    </div>
                    <details class="governance-disclosure">
                        <summary><span>مشاهده زنجیره {{ $governanceLevelCount ?? collect($governanceAreas)->count() }} سطحی</span><i class="fas fa-chevron-down text-muted" aria-hidden="true"></i></summary>
                        <div class="governance-disclosure-body">
                            <div class="governance-chain" data-governance-chain>
                                @foreach(($pendingGovernanceProposals ?? collect()) as $proposal)
                                    <div class="governance-chain-item" data-pending-governance-proposal="{{ $proposal->id }}">
                                        <span class="governance-chain-dot" aria-hidden="true"></span>
                                        <div class="min-w-0">
                                            <div class="fw-semibold">{{ \App\Support\LocationDisplayName::for($proposal) }} <span class="badge bg-warning text-dark me-1">در انتظار تأیید</span></div>
                                            <div class="small text-muted">{{ $governanceTypeLabels[$proposal->type?->key] ?? $proposal->type?->key }}</div>
                                        </div>
                                    </div>
                                @endforeach
                                @foreach(($pendingGovernanceStructuralClaims ?? collect()) as $claim)
                                    @if($claim?->location)
                                        <div class="governance-chain-item" data-pending-governance-structure-claim="{{ $claim->id }}">
                                            <span class="governance-chain-dot" aria-hidden="true"></span>
                                            <div class="min-w-0">
                                                <div class="fw-semibold">{{ $displayLocationName($claim->location) }} <span class="badge bg-warning text-dark me-1">ساختار در انتظار تأیید</span></div>
                                                <div class="small text-muted">{{ $governanceTypeLabels[$claim->location?->type?->key] ?? $claim->location?->type?->key }}</div>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                                @forelse($governanceAreas as $area)
                                    <div class="governance-chain-item" data-governance-area="{{ $area->id }}"><span class="governance-chain-dot" aria-hidden="true"></span><div class="min-w-0"><div class="fw-semibold">{{ $displayAreaName($area) }}</div><div class="small text-muted">{{ $governanceTypeLabels[$area->governance_type] ?? $area->governance_type }}</div></div></div>
                                @empty
                                    <div class="alert alert-light border mb-0">برای محل فعلی شما هنوز زنجیره حکمرانی رسمی قابل نمایش نیست.</div>
                                @endforelse
                            </div>
                        </div>
                    </details>
                </div>
            </section>

            <section class="mb-3 mb-md-4" aria-labelledby="memberships-heading">
                <div class="d-flex align-items-end justify-content-between gap-3 mb-3"><div><h2 id="memberships-heading" class="h5 mb-1">عضویت‌های حکمرانی من</h2><p class="text-muted small mb-0">پنج خانواده عضویت رسمی؛ جزئیات هرکدام را فقط در صورت نیاز باز کنید.</p></div><span class="membership-total-pill"><i class="fas fa-layer-group" aria-hidden="true"></i>{{ $membershipTotal }} عضویت</span></div>
                <div class="d-grid gap-2">
                    @foreach($dimensionLabels as $dimension => $dimensionMeta)
                        @php($bucket = collect($membershipsByDimension->get($dimension, [])))
                        @php($activeMemberships = collect($bucket->get('active', [])))
                        @php($observerMemberships = collect($bucket->get('observer', [])))
                        <details class="membership-summary-row" data-membership-dimension="{{ $dimension }}">
                            <summary>
                                <span class="membership-icon"><i class="fas {{ $dimensionMeta['icon'] }}"></i></span>
                                <strong>{{ $dimensionMeta['label'] }}</strong>
                                <span class="membership-summary-counts">
                                    <span class="badge bg-primary-subtle text-primary-emphasis">{{ $activeMemberships->count() }} فعال</span>
                                    <span class="badge text-bg-light border">{{ $observerMemberships->count() }} ناظر</span>
                                </span>
                            </summary>
                            <div class="p-3">
                                <div class="active-membership-box mb-2"><div class="small fw-semibold mb-2">عضویت فعال</div>
                                    @forelse($activeMemberships as $group)<div class="small py-1">{{ $group->name ?: $group->dimension_value_key }}@if($group->governanceArea)<span class="text-muted fw-normal"> — {{ $displayAreaName($group->governanceArea) }}</span>@endif</div>@empty<div class="text-muted small">عضویت فعالی در این بُعد وجود ندارد.</div>@endforelse
                                </div>
                                <div class="observer-memberships"><div class="d-flex align-items-center justify-content-between mb-1"><span class="small fw-semibold">عضویت‌های ناظر</span><span class="badge text-bg-secondary">{{ $observerMemberships->count() }}</span></div>
                                    @forelse($observerMemberships as $group)<div class="small py-1 border-bottom">{{ $group->name ?: $group->dimension_value_key }}@if($group->governanceArea)<span class="text-muted"> — {{ $displayAreaName($group->governanceArea) }}</span>@endif</div>@empty<div class="text-muted small">عضویت ناظری در این بُعد وجود ندارد.</div>@endforelse
                                </div>
                            </div>
                        </details>
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
                        <strong>نشانی محلی شما هنوز تکمیل نشده است.</strong>
                        <div class="small mt-1">ثبت خیابان، کوچه، مجتمع یا ساختمان اختیاری است. با تکمیل نشانی می‌توانید اجتماعات محلی مربوط به محل زندگی خود را ببینید، عضو شوید یا در صورت فراهم بودن شرایط اجتماع تازه‌ای ایجاد کنید.</div>
                    </div>
                    <a href="{{ route('profile.edit') }}?section=location#section-location" class="btn location-governance-edit w-100"><i class="fas fa-location-dot ms-2"></i>تکمیل نشانی محلی</a>
                @else
                    <div class="d-grid gap-3" data-community-options>
                        @foreach($communityOptions as $option)
                            @php($location = $option['location'])
                            @php($community = $option['community'])
                            @php($communityGroup = $option['group'])
                            @php($isCommunityMember = $option['is_member'] ?? false)
                            @php($localTypeLabels = ['street' => 'خیابان', 'alley' => 'کوچه', 'complex' => 'مجتمع', 'building' => 'ساختمان'])
                            <article class="community-option" data-community-location="{{ $location->id }}">
                                <div class="d-flex align-items-start gap-3">
                                    <span class="community-location-icon"><i class="fas fa-people-roof"></i></span>
                                    <div class="flex-grow-1 min-w-0"><div class="small text-muted">{{ $localTypeLabels[$location->type?->key] ?? 'مکان محلی' }}</div><h3 class="h6 mb-1">{{ $displayLocationName($location) }}</h3>
                                        @if($community)<span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">اجتماع فعال</span>
                                        @elseif($pendingResidenceIntent)<span class="badge bg-warning text-dark">در انتظار تأیید نشانی</span>
                                        @else<span class="badge text-bg-light border">هنوز ایجاد نشده</span>@endif
                                    </div>
                                </div>
                                @if($community)
                                    <div class="small mt-3">اجتماع «{{ \App\Support\GovernanceAreaDisplayName::for($community) }}» برای این مکان فعال است. عضویت در اجتماعات محلی اختیاری است.</div>
                                    @if($isCommunityMember && $communityGroup)
                                        <div class="d-flex flex-column flex-md-row gap-2 mt-3">
                                            <a href="{{ route('groups.show', $communityGroup) }}" class="btn btn-primary" data-community-enter-action>ورود به اجتماع محلی</a>
                                            <form method="POST" action="{{ route('location-governance.community.leave', $community) }}" data-community-leave-action>@csrf @method('DELETE')<button type="submit" class="btn btn-outline-secondary w-100">خروج از اجتماع</button></form>
                                        </div>
                                    @elseif($option['can_join'])
                                        <div class="small text-muted mt-2">این اجتماع در مسیر محل سکونت شماست. در صورت تمایل می‌توانید عضو شوید.</div>
                                        <form method="POST" action="{{ route('location-governance.community.join', $community) }}" class="mt-3" data-community-join-action>@csrf<button type="submit" class="btn btn-primary w-100">عضویت در اجتماع محلی</button></form>
                                    @elseif(!$communityGroup)
                                        <div class="small text-warning mt-2">فضای مشارکت این اجتماع هنوز آماده نشده است.</div>
                                    @endif
                                @elseif($option['can_create'])
                                    <div class="small text-muted mt-3">برای این مکان هنوز اجتماعی ساخته نشده است. ایجاد آن اختیاری است و با ایجاد، خودتان نیز عضو آن می‌شوید.</div>
                                    <form method="POST" action="{{ route('location-governance.community.store', $location) }}" class="mt-2" data-community-create-action>@csrf<button type="submit" class="btn btn-outline-primary w-100">ایجاد اجتماع این {{ $localTypeLabels[$location->type?->key] ?? 'مکان' }}</button></form>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </div></section>
        </div>
    </div>
</div>@endsection
