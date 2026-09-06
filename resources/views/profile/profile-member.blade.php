@extends('profile.profile-member-base')

@push('styles')
<style>
    .reputation-public-card--embedded {
        width: 100%;
        max-width: 780px;
        margin: 1.1rem auto 1.75rem;
        padding: 1.05rem 1.15rem;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        box-shadow: 0 3px 14px rgba(15, 23, 42, .06);
        direction: rtl;
        color: #0f172a;
    }
    .reputation-public-card__summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: .85rem;
    }
    .reputation-public-card__title {
        color: #334155;
        font-size: .86rem;
        font-weight: 800;
        margin-bottom: .25rem;
    }
    .reputation-public-card__score-row {
        display: flex;
        align-items: baseline;
        gap: .35rem;
        flex-wrap: wrap;
    }
    .reputation-public-card__score {
        color: var(--color-dark-green, #047857);
        font-size: 1.75rem;
        font-weight: 900;
        line-height: 1;
        font-variant-numeric: tabular-nums;
    }
    .reputation-public-card__unit {
        color: #64748b;
        font-size: .78rem;
        font-weight: 700;
    }
    .reputation-public-card__level {
        display: inline-flex;
        align-items: center;
        min-height: 2rem;
        padding: .3rem .7rem;
        border-radius: 999px;
        border: 1px solid #a7f3d0;
        background: #ecfdf5;
        color: #047857;
        font-size: .78rem;
        font-weight: 800;
        white-space: nowrap;
    }
    .reputation-public-card__grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .6rem;
    }
    .reputation-public-card__metric {
        min-width: 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
        padding: .68rem .75rem;
        border: 1px solid #e2e8f0;
        border-radius: .75rem;
        background: #f8fafc;
    }
    .reputation-public-card__metric span {
        color: #64748b;
        font-size: .76rem;
        font-weight: 700;
        min-width: 0;
    }
    .reputation-public-card__metric strong {
        color: #047857;
        font-size: .95rem;
        font-weight: 900;
        font-variant-numeric: tabular-nums;
    }
    .reputation-public-card__legacy {
        margin-top: .65rem;
        color: #64748b;
        font-size: .75rem;
    }

    @media (max-width: 640px) {
        .reputation-public-card--embedded {
            width: auto;
            margin: .85rem .75rem 1.25rem;
            padding: .9rem;
            border-radius: .9rem;
        }
        .reputation-public-card__summary {
            align-items: flex-start;
            gap: .75rem;
        }
        .reputation-public-card__score { font-size: 1.55rem; }
        .reputation-public-card__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .reputation-public-card__metric { padding: .65rem; }
    }

    @media (max-width: 360px) {
        .reputation-public-card--embedded { margin-inline: .55rem; }
        .reputation-public-card__summary { flex-direction: column; }
        .reputation-public-card__level { align-self: flex-start; }
    }
</style>
@endpush

@section('content')
@php
    $publicReputationSummary = app(\App\Services\ParticipationPointSummaryService::class)
        ->publicReputationSummary((int) $user->id);
    $publicBreakdown = $publicReputationSummary['reputation_breakdown'];
@endphp

@parent

<template id="public-reputation-card-template">
    <section class="reputation-public-card--embedded" aria-label="اعتبار و مشارکت کاربر">
        <div class="reputation-public-card__summary">
            <div>
                <div class="reputation-public-card__title">اعتبار و مشارکت</div>
                <div class="reputation-public-card__score-row">
                    <strong class="reputation-public-card__score">{{ number_format($publicReputationSummary['total_points']) }}</strong>
                    <span class="reputation-public-card__unit">امتیاز</span>
                </div>
            </div>
            <span class="reputation-public-card__level">سطح {{ $publicReputationSummary['level_label'] }}</span>
        </div>

        <div class="reputation-public-card__grid" aria-label="ابعاد اعتبار">
            <div class="reputation-public-card__metric"><span>مشارکت</span><strong>{{ number_format($publicBreakdown['participation']) }}</strong></div>
            <div class="reputation-public-card__metric"><span>اعتمادپذیری</span><strong>{{ number_format($publicBreakdown['reliability']) }}</strong></div>
            <div class="reputation-public-card__metric"><span>تخصص</span><strong>{{ number_format($publicBreakdown['expertise']) }}</strong></div>
            <div class="reputation-public-card__metric"><span>اعتماد مدنی</span><strong>{{ number_format($publicBreakdown['civic_trust']) }}</strong></div>
        </div>

        @if(($publicBreakdown['legacy_other'] ?? 0) !== 0)
            <div class="reputation-public-card__legacy">سابقه قدیمی / سایر: {{ number_format($publicBreakdown['legacy_other']) }} امتیاز</div>
        @endif
    </section>
</template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const template = document.getElementById('public-reputation-card-template');
    const container = document.querySelector('.profile-member-container');
    if (!template || !container) return;

    const reputationCard = template.content.cloneNode(true);
    const firstSection = container.firstElementChild;
    if (firstSection) {
        firstSection.after(reputationCard);
    } else {
        container.append(reputationCard);
    }
});
</script>
@endpush
