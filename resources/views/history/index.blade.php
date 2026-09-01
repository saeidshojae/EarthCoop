@extends('history.index-base')

@push('styles')
<style>
    .reputation-private-summary {
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        background: #f8fafc;
        direction: rtl;
    }
    .reputation-private-summary__top,
    .reputation-private-summary__economy {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .75rem;
        margin-bottom: 1rem;
    }
    .reputation-private-summary__dimensions {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .75rem;
        margin-bottom: 1rem;
    }
    .reputation-private-summary__box {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: .8rem;
        padding: .9rem;
        text-align: center;
    }
    .reputation-private-summary__box strong { display: block; color: var(--color-dark-green); font-size: 1.15rem; margin-top: .25rem; }
    .reputation-private-summary__form { display: flex; flex-wrap: wrap; gap: .75rem; align-items: end; }
    .reputation-private-summary__form label { flex: 1 1 220px; }
    .reputation-private-summary__form input { width: 100%; margin-top: .35rem; padding: .65rem .8rem; border: 1px solid #cbd5e1; border-radius: .65rem; }
    .reputation-private-summary__form button { border: 0; border-radius: .65rem; padding: .75rem 1rem; background: var(--color-earth-green); color: white; font-weight: 700; }
    .reputation-private-summary__note { color: #64748b; font-size: .85rem; margin-top: .75rem; }
    @media (max-width: 760px) {
        .reputation-private-summary__top,
        .reputation-private-summary__economy,
        .reputation-private-summary__dimensions { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 480px) {
        .reputation-private-summary__top,
        .reputation-private-summary__economy,
        .reputation-private-summary__dimensions { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
@parent

<template id="reputation-points-summary-template">
    <div class="reputation-private-summary">
        <div class="reputation-private-summary__top">
            <div class="reputation-private-summary__box"><span>امتیاز اعتبار و مشارکت</span><strong>{{ number_format($pointSummary['total_points']) }}</strong></div>
            <div class="reputation-private-summary__box"><span>سطح اعتبار</span><strong>{{ $reputationLevelLabel }}</strong></div>
            <div class="reputation-private-summary__box"><span>سابقه ثبت‌شده</span><strong>{{ number_format($pointTransactions->count()) }} رویداد</strong></div>
        </div>

        <div class="reputation-private-summary__dimensions">
            <div class="reputation-private-summary__box"><span>مشارکت</span><strong>{{ number_format($reputationBreakdown['participation']) }}</strong></div>
            <div class="reputation-private-summary__box"><span>اعتمادپذیری</span><strong>{{ number_format($reputationBreakdown['reliability']) }}</strong></div>
            <div class="reputation-private-summary__box"><span>تخصص</span><strong>{{ number_format($reputationBreakdown['expertise']) }}</strong></div>
            <div class="reputation-private-summary__box"><span>اعتماد مدنی</span><strong>{{ number_format($reputationBreakdown['civic_trust']) }}</strong></div>
        </div>

        @if(($reputationBreakdown['legacy_other'] ?? 0) !== 0)
            <p class="reputation-private-summary__note">سابقه قدیمی / سایر: {{ number_format($reputationBreakdown['legacy_other']) }} امتیاز</p>
        @endif

        <div class="reputation-private-summary__economy">
            <div class="reputation-private-summary__box"><span>مشارکت قابل تبدیل کسب‌شده</span><strong>{{ number_format($convertibleAwardedPoints) }}</strong></div>
            <div class="reputation-private-summary__box"><span>مصرف‌شده در تبدیل</span><strong>{{ number_format($ledgerConsumedPoints + $legacyConvertedPoints) }}</strong></div>
            <div class="reputation-private-summary__box"><span>مشارکت قابل تبدیل باقی‌مانده</span><strong>{{ number_format($remainingConvertiblePoints) }}</strong></div>
        </div>

        @if($reversalAdjustmentPoints > 0)
            <p class="reputation-private-summary__note">تعدیل ناشی از کسر امتیاز مشارکت: {{ number_format($reversalAdjustmentPoints) }} امتیاز</p>
        @endif

        <form class="reputation-private-summary__form" method="POST" action="{{ route('reputation.conversion.convert') }}">
            @csrf
            <label>
                تعداد امتیاز برای تبدیل
                <input type="number" name="points" min="1" max="{{ max(1, $remainingConvertiblePoints) }}" required @disabled($remainingConvertiblePoints < 1)>
            </label>
            <button type="submit" @disabled($remainingConvertiblePoints < 1)>تبدیل امتیاز مشارکت به بهار</button>
        </form>
        <p class="reputation-private-summary__note">تبدیل فقط از ظرفیت مشارکت قابل تبدیل انجام می‌شود؛ امتیاز اعتبار تاریخی شما پس از تبدیل کاهش نمی‌یابد.</p>
    </div>
</template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const panel = document.getElementById('tab-points');
    const template = document.getElementById('reputation-points-summary-template');
    if (!panel || !template) return;

    const summary = template.content.cloneNode(true);
    const legacySummary = panel.querySelector('.mb-4');
    if (legacySummary) {
        legacySummary.replaceWith(summary);
    } else {
        panel.prepend(summary);
    }
});
</script>
@endpush
