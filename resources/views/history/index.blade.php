@extends('history.index-base')

@push('styles')
<style>
    .reputation-surface {
        direction: rtl;
        display: grid;
        gap: 1rem;
        color: #0f172a;
    }
    .reputation-overview,
    .reputation-conversion-card,
    .reputation-history-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        overflow: hidden;
    }
    .reputation-overview {
        padding: 1.35rem;
    }
    .reputation-overview__hero {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1.25rem;
        margin-bottom: 1.1rem;
    }
    .reputation-overview__eyebrow,
    .reputation-section-kicker {
        color: #047857;
        font-size: .82rem;
        font-weight: 800;
        margin-bottom: .3rem;
    }
    .reputation-overview__score-row {
        display: flex;
        align-items: baseline;
        gap: .4rem;
        flex-wrap: wrap;
    }
    .reputation-overview__score {
        color: var(--color-dark-green, #047857);
        font-size: clamp(2rem, 5vw, 3rem);
        font-weight: 900;
        line-height: 1;
    }
    .reputation-overview__unit {
        color: #475569;
        font-weight: 700;
    }
    .reputation-overview__description {
        color: #64748b;
        font-size: .9rem;
        line-height: 1.9;
        margin: .65rem 0 0;
        max-width: 42rem;
    }
    .reputation-level-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 2.25rem;
        padding: .35rem .8rem;
        border-radius: 999px;
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
        white-space: nowrap;
        font-size: .85rem;
        font-weight: 800;
    }
    .reputation-dimensions {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .7rem;
    }
    .reputation-dimension {
        min-width: 0;
        padding: .85rem;
        border-radius: .8rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }
    .reputation-dimension__top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
        margin-bottom: .55rem;
    }
    .reputation-dimension__label {
        color: #475569;
        font-size: .82rem;
        font-weight: 700;
        min-width: 0;
    }
    .reputation-dimension__value {
        color: #047857;
        font-weight: 900;
        font-variant-numeric: tabular-nums;
    }
    .reputation-dimension__track {
        height: 4px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
    }
    .reputation-dimension__fill {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: var(--color-earth-green, #10b981);
    }
    .reputation-legacy-note,
    .reputation-conversion-card__note {
        color: #64748b;
        font-size: .82rem;
        line-height: 1.8;
    }
    .reputation-legacy-note { margin: .8rem 0 0; }

    .reputation-conversion-card {
        padding: 1.35rem;
    }
    .reputation-conversion-card__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    .reputation-conversion-card h3,
    .reputation-history-card h3 {
        font-size: 1.05rem;
        margin: 0;
        color: #0f172a;
    }
    .reputation-conversion-card__available {
        text-align: left;
        white-space: nowrap;
    }
    .reputation-conversion-card__available strong {
        display: block;
        color: #047857;
        font-size: 1.6rem;
        line-height: 1;
    }
    .reputation-conversion-card__available span {
        color: #64748b;
        font-size: .75rem;
    }
    .reputation-conversion-stats {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .65rem;
        margin-bottom: 1rem;
    }
    .reputation-conversion-stat {
        min-width: 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .75rem .85rem;
        border-radius: .75rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #475569;
        font-size: .82rem;
    }
    .reputation-conversion-stat strong {
        color: #0f172a;
        font-variant-numeric: tabular-nums;
    }
    .reputation-conversion-empty {
        padding: .8rem .9rem;
        margin-bottom: .9rem;
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: .75rem;
        color: #64748b;
        font-size: .86rem;
    }
    .reputation-conversion-form {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .7rem;
        align-items: end;
    }
    .reputation-conversion-form label {
        display: grid;
        gap: .4rem;
        color: #334155;
        font-size: .82rem;
        font-weight: 700;
        min-width: 0;
    }
    .reputation-conversion-form input {
        width: 100%;
        min-width: 0;
        min-height: 46px;
        padding: .7rem .8rem;
        border: 1px solid #cbd5e1;
        border-radius: .7rem;
        background: #fff;
        color: #0f172a;
        font-size: 1rem;
    }
    .reputation-conversion-form button {
        min-height: 46px;
        border: 0;
        border-radius: .7rem;
        padding: .7rem 1.15rem;
        background: var(--color-earth-green, #10b981);
        color: #fff;
        font-weight: 800;
        cursor: pointer;
        white-space: nowrap;
    }
    .reputation-conversion-form button:disabled,
    .reputation-conversion-form input:disabled {
        cursor: not-allowed;
        opacity: .55;
    }
    .reputation-conversion-card__note { margin: .8rem 0 0; }

    .reputation-history-card__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.2rem;
        border-bottom: 1px solid #e2e8f0;
    }
    .reputation-history-card__count {
        color: #64748b;
        font-size: .78rem;
        white-space: nowrap;
    }
    .reputation-history__desktop { display: block; }
    .reputation-history__mobile { display: none; }
    .reputation-history-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }
    .reputation-history-table th,
    .reputation-history-table td {
        padding: .85rem .9rem;
        border-bottom: 1px solid #eef2f7;
        text-align: right;
        vertical-align: middle;
        overflow-wrap: anywhere;
    }
    .reputation-history-table th {
        color: #64748b;
        font-size: .76rem;
        font-weight: 800;
        background: #f8fafc;
    }
    .reputation-history-table td {
        color: #334155;
        font-size: .84rem;
    }
    .reputation-history-table tr:last-child td { border-bottom: 0; }
    .reputation-event-title { color: #0f172a; font-weight: 800; }
    .reputation-delta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 3.25rem;
        min-height: 1.9rem;
        padding: .2rem .5rem;
        border-radius: 999px;
        font-weight: 900;
        font-variant-numeric: tabular-nums;
    }
    .reputation-delta--positive { background: #ecfdf5; color: #047857; }
    .reputation-delta--negative { background: #fef2f2; color: #b91c1c; }
    .reputation-history-empty {
        padding: 1.2rem;
        color: #64748b;
        text-align: center;
        font-size: .86rem;
    }

    @media (max-width: 640px) {
        .reputation-surface { gap: .8rem; }
        .reputation-overview,
        .reputation-conversion-card { padding: 1rem; }
        .reputation-overview__hero,
        .reputation-conversion-card__header {
            gap: .75rem;
        }
        .reputation-overview__description { font-size: .84rem; line-height: 1.75; }
        .reputation-dimensions { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .reputation-dimension { padding: .75rem; }
        .reputation-conversion-stats { grid-template-columns: 1fr; }
        .reputation-conversion-form { grid-template-columns: 1fr; }
        .reputation-conversion-form button { width: 100%; min-height: 48px; }
        .reputation-history__desktop { display: none; }
        .reputation-history__mobile { display: grid; }
        .reputation-history-mobile-item {
            padding: .9rem 1rem;
            border-bottom: 1px solid #eef2f7;
        }
        .reputation-history-mobile-item:last-child { border-bottom: 0; }
        .reputation-history-mobile-item__top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            margin-bottom: .45rem;
        }
        .reputation-history-mobile-item__meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            color: #64748b;
            font-size: .76rem;
        }
        .reputation-history-mobile-item__meta span { min-width: 0; overflow-wrap: anywhere; }
    }

    @media (max-width: 380px) {
        .reputation-overview__hero,
        .reputation-conversion-card__header {
            flex-direction: column;
        }
        .reputation-conversion-card__available { text-align: right; }
        .reputation-level-pill { align-self: flex-start; }
    }
</style>
@endpush

@section('content')
@parent

@php
    $actionLabels = [
        'email_verified' => 'تأیید ایمیل',
        'profile_completed' => 'تکمیل پروفایل',
        'invite_member' => 'دعوت موفق عضو',
        'membership_fee_paid' => 'پرداخت حق عضویت',
        'post_created' => 'ایجاد پست',
        'post_liked' => 'پسندیدن پست',
        'post_upvoted' => 'رأی مثبت به پست',
        'comment_created' => 'ثبت نظر',
        'comment_liked' => 'پسندیدن نظر',
        'comment_upvoted' => 'رأی مثبت به نظر',
        'poll_created' => 'ایجاد نظرسنجی',
        'poll_participated' => 'مشارکت در نظرسنجی',
        'bid_placed' => 'ثبت پیشنهاد خرید',
        'bid_won' => 'برنده‌شدن در پیشنهاد',
        'successful_settlement' => 'تسویه موفق',
        'elected_manager' => 'پذیرش مسئولیت مدیر منتخب',
        'elected_inspector' => 'پذیرش مسئولیت بازرس منتخب',
        'professional_referral_completed' => 'تکمیل ارجاع حرفه‌ای',
        'report_received' => 'دریافت گزارش منفی',
        'bid_canceled' => 'لغو پیشنهاد',
        'fraud' => 'تخلف ثبت‌شده',
    ];
    $sourceLabels = [
        'profile' => 'پروفایل کاربری',
        'auth' => 'احراز حساب',
        'registration_completion' => 'تکمیل عضویت',
        'najm_bahar_membership' => 'عضویت نجم بهار',
        'groups' => 'گروه‌ها',
        'group.poll' => 'نظرسنجی گروه',
        'stock.bid' => 'بازار سهام',
        'stock.settlement' => 'تسویه بازار',
        'governance.professional_referral' => 'ارجاع حرفه‌ای',
    ];
    $maxDimensionValue = max(
        1,
        (int) ($reputationBreakdown['participation'] ?? 0),
        (int) ($reputationBreakdown['reliability'] ?? 0),
        (int) ($reputationBreakdown['expertise'] ?? 0),
        (int) ($reputationBreakdown['civic_trust'] ?? 0)
    );
@endphp

<template id="reputation-points-summary-template">
    <div class="reputation-surface">
        <section class="reputation-overview" aria-label="وضعیت اعتبار و مشارکت شما">
            <div class="reputation-overview__hero">
                <div>
                    <div class="reputation-overview__eyebrow">اعتبار و مشارکت</div>
                    <div class="reputation-overview__score-row">
                        <strong class="reputation-overview__score">{{ number_format($pointSummary['total_points']) }}</strong>
                        <span class="reputation-overview__unit">امتیاز</span>
                    </div>
                    <p class="reputation-overview__description">این امتیاز حاصل سابقه فعالیت و مشارکت شما در EarthCoop است و با تبدیل بخشی از امتیاز مشارکت به بهار کاهش نمی‌یابد.</p>
                </div>
                <span class="reputation-level-pill">سطح {{ $reputationLevelLabel }}</span>
            </div>

            <div class="reputation-dimensions" aria-label="ابعاد اعتبار">
                @foreach([
                    'participation' => 'مشارکت',
                    'reliability' => 'اعتمادپذیری',
                    'expertise' => 'تخصص',
                    'civic_trust' => 'اعتماد مدنی',
                ] as $dimensionKey => $dimensionLabel)
                    @php
                        $dimensionValue = (int) ($reputationBreakdown[$dimensionKey] ?? 0);
                        $dimensionPercent = min(100, (int) round(($dimensionValue / $maxDimensionValue) * 100));
                    @endphp
                    <div class="reputation-dimension">
                        <div class="reputation-dimension__top">
                            <span class="reputation-dimension__label">{{ $dimensionLabel }}</span>
                            <strong class="reputation-dimension__value">{{ number_format($dimensionValue) }}</strong>
                        </div>
                        <div class="reputation-dimension__track" aria-hidden="true"><span class="reputation-dimension__fill" style="width: {{ $dimensionPercent }}%"></span></div>
                    </div>
                @endforeach
            </div>

            @if(($reputationBreakdown['legacy_other'] ?? 0) !== 0)
                <p class="reputation-legacy-note">سابقه قدیمی / سایر: {{ number_format($reputationBreakdown['legacy_other']) }} امتیاز</p>
            @endif
        </section>

        <section class="reputation-conversion-card" aria-label="تبدیل امتیاز مشارکت به بهار">
            <div class="reputation-conversion-card__header">
                <div>
                    <div class="reputation-section-kicker">امتیاز اقتصادی شما</div>
                    <h3>امتیاز مشارکت قابل تبدیل</h3>
                </div>
                <div class="reputation-conversion-card__available">
                    <strong>{{ number_format($remainingConvertiblePoints) }}</strong>
                    <span>امتیاز قابل استفاده</span>
                </div>
            </div>

            <div class="reputation-conversion-stats">
                <div class="reputation-conversion-stat"><span>کسب‌شده قابل تبدیل</span><strong>{{ number_format($convertibleAwardedPoints) }}</strong></div>
                <div class="reputation-conversion-stat"><span>تبدیل‌شده</span><strong>{{ number_format($ledgerConsumedPoints + $legacyConvertedPoints) }}</strong></div>
            </div>

            @if($reversalAdjustmentPoints > 0)
                <p class="reputation-legacy-note">تعدیل ناشی از کسر امتیاز مشارکت: {{ number_format($reversalAdjustmentPoints) }} امتیاز</p>
            @endif

            @if($remainingConvertiblePoints < 1)
                <div class="reputation-conversion-empty">در حال حاضر امتیاز مشارکت قابل تبدیل ندارید.</div>
            @endif

            <form class="reputation-conversion-form" method="POST" action="{{ route('reputation.conversion.convert') }}">
                @csrf
                <label>
                    تعداد امتیاز برای تبدیل
                    <input type="number" name="points" min="1" max="{{ max(1, $remainingConvertiblePoints) }}" inputmode="numeric" required @disabled($remainingConvertiblePoints < 1)>
                </label>
                <button type="submit" @disabled($remainingConvertiblePoints < 1)>تبدیل به بهار</button>
            </form>
            <p class="reputation-conversion-card__note">فقط ظرفیت مشارکتِ قابل تبدیل مصرف می‌شود؛ اعتبار و سابقه اجتماعی شما باقی می‌ماند.</p>
        </section>

        <section class="reputation-history-card" aria-label="تاریخچه امتیازات">
            <div class="reputation-history-card__header">
                <h3>تاریخچه امتیازات</h3>
                <span class="reputation-history-card__count">{{ number_format($pointTransactions->count()) }} رویداد ثبت‌شده</span>
            </div>

            @if($pointTransactions->isEmpty())
                <div class="reputation-history-empty">هنوز رویداد امتیازی برای شما ثبت نشده است.</div>
            @else
                <div class="reputation-history__desktop">
                    <table class="reputation-history-table">
                        <thead>
                            <tr>
                                <th style="width:32%">رویداد</th>
                                <th style="width:16%">تغییر</th>
                                <th style="width:27%">بخش</th>
                                <th style="width:25%">تاریخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pointTransactions as $transaction)
                                @php
                                    $eventLabel = $actionLabels[$transaction->action] ?? 'رویداد امتیازی';
                                    $sourceLabel = $sourceLabels[$transaction->source] ?? 'سایر فعالیت‌ها';
                                    $delta = (int) $transaction->delta;
                                @endphp
                                <tr>
                                    <td><span class="reputation-event-title">{{ $eventLabel }}</span></td>
                                    <td><span class="reputation-delta {{ $delta >= 0 ? 'reputation-delta--positive' : 'reputation-delta--negative' }}">{{ $delta >= 0 ? '+' : '' }}{{ number_format($delta) }}</span></td>
                                    <td>{{ $sourceLabel }}</td>
                                    <td>{{ verta($transaction->created_at)->format('Y-m-d H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="reputation-history__mobile">
                    @foreach($pointTransactions as $transaction)
                        @php
                            $eventLabel = $actionLabels[$transaction->action] ?? 'رویداد امتیازی';
                            $sourceLabel = $sourceLabels[$transaction->source] ?? 'سایر فعالیت‌ها';
                            $delta = (int) $transaction->delta;
                        @endphp
                        <article class="reputation-history-mobile-item">
                            <div class="reputation-history-mobile-item__top">
                                <span class="reputation-event-title">{{ $eventLabel }}</span>
                                <span class="reputation-delta {{ $delta >= 0 ? 'reputation-delta--positive' : 'reputation-delta--negative' }}">{{ $delta >= 0 ? '+' : '' }}{{ number_format($delta) }}</span>
                            </div>
                            <div class="reputation-history-mobile-item__meta">
                                <span>{{ $sourceLabel }}</span>
                                <span>{{ verta($transaction->created_at)->format('Y-m-d H:i') }}</span>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const panel = document.getElementById('tab-points');
    const template = document.getElementById('reputation-points-summary-template');
    if (!panel || !template) return;

    panel.replaceChildren(template.content.cloneNode(true));
});
</script>
@endpush
