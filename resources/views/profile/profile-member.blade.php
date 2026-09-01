@extends('profile.profile-member-base')

@push('styles')
<style>
    .reputation-public-card {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 20px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        margin-bottom: 2rem;
        overflow: hidden;
        direction: rtl;
    }
    .reputation-public-card__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.25rem 1.5rem;
        background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        color: white;
    }
    .reputation-public-card__score { font-size: 1.8rem; font-weight: 800; }
    .reputation-public-card__level { font-size: .9rem; opacity: .9; }
    .reputation-public-card__grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .75rem;
        padding: 1.25rem 1.5rem;
    }
    .reputation-public-card__metric {
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: .9rem;
        text-align: center;
        background: #f9fafb;
    }
    .reputation-public-card__metric strong { display: block; font-size: 1.15rem; color: var(--color-dark-green); }
    .reputation-public-card__legacy { padding: 0 1.5rem 1.25rem; color: #64748b; font-size: .85rem; }
    @media (max-width: 700px) {
        .reputation-public-card__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
</style>
@endpush

@section('content')
@php
    $publicReputationSummary = app(\App\Services\ParticipationPointSummaryService::class)
        ->publicReputationSummary((int) $user->id);
    $publicBreakdown = $publicReputationSummary['reputation_breakdown'];
@endphp

<div class="reputation-public-card" aria-label="اعتبار و مشارکت کاربر">
    <div class="reputation-public-card__header">
        <div>
            <div>امتیاز اعتبار و مشارکت</div>
            <div class="reputation-public-card__score">{{ number_format($publicReputationSummary['total_points']) }}</div>
        </div>
        <div class="reputation-public-card__level">سطح: {{ $publicReputationSummary['level_label'] }}</div>
    </div>
    <div class="reputation-public-card__grid">
        <div class="reputation-public-card__metric"><span>مشارکت</span><strong>{{ number_format($publicBreakdown['participation']) }}</strong></div>
        <div class="reputation-public-card__metric"><span>اعتمادپذیری</span><strong>{{ number_format($publicBreakdown['reliability']) }}</strong></div>
        <div class="reputation-public-card__metric"><span>تخصص</span><strong>{{ number_format($publicBreakdown['expertise']) }}</strong></div>
        <div class="reputation-public-card__metric"><span>اعتماد مدنی</span><strong>{{ number_format($publicBreakdown['civic_trust']) }}</strong></div>
    </div>
    @if(($publicBreakdown['legacy_other'] ?? 0) !== 0)
        <div class="reputation-public-card__legacy">سابقه قدیمی / سایر: {{ number_format($publicBreakdown['legacy_other']) }} امتیاز</div>
    @endif
</div>

@parent
@endsection
