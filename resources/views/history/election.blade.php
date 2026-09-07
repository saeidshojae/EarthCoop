@extends('layouts.unified')

@section('title', 'انتخابات جاری - ' . config('app.name', 'EarthCoop'))

@push('styles')
<style>
    .election-center-page {
        min-width: 0;
        width: 100%;
        max-width: 1180px;
        margin: 0 auto;
        padding: 1.35rem 1rem 3rem;
        color: var(--color-gentle-black, #182033);
    }

    .election-center-hero {
        min-width: 0;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(16, 185, 129, .2);
        border-radius: 1.6rem;
        padding: 1.5rem;
        background:
            radial-gradient(circle at 100% 0, rgba(16, 185, 129, .14), transparent 38%),
            linear-gradient(135deg, rgba(236, 253, 245, .95), rgba(239, 246, 255, .9));
        box-shadow: 0 16px 40px rgba(15, 23, 42, .06);
    }

    .election-center-hero__top {
        min-width: 0;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .election-center-heading {
        min-width: 0;
        display: flex;
        align-items: flex-start;
        gap: .9rem;
        flex: 1 1 520px;
    }

    .election-center-heading__icon {
        flex: 0 0 3rem;
        width: 3rem;
        height: 3rem;
        display: grid;
        place-items: center;
        border-radius: 1rem;
        color: #047857;
        background: rgba(255, 255, 255, .82);
        border: 1px solid rgba(16, 185, 129, .22);
        box-shadow: 0 8px 24px rgba(16, 185, 129, .12);
        font-size: 1.2rem;
    }

    .election-center-heading h1 {
        margin: 0 0 .35rem;
        font-size: clamp(1.45rem, 2.8vw, 2.05rem);
        font-weight: 900;
        line-height: 1.35;
        color: #0f766e;
    }

    .election-center-heading p {
        margin: 0;
        max-width: 720px;
        line-height: 1.9;
        font-size: .94rem;
        color: #526176;
        overflow-wrap: anywhere;
    }

    .election-history-link {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .5rem;
        min-height: 2.7rem;
        padding: .65rem .95rem;
        border: 1px solid rgba(15, 118, 110, .22);
        border-radius: .85rem;
        background: rgba(255, 255, 255, .8);
        color: #0f766e;
        font-weight: 800;
        text-decoration: none;
        transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
    }

    .election-history-link:hover {
        color: #0f766e;
        background: #fff;
        transform: translateY(-1px);
        box-shadow: 0 8px 20px rgba(15, 23, 42, .08);
    }

    .election-center-summary {
        min-width: 0;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .75rem;
        margin-top: 1.25rem;
        max-width: 650px;
    }

    .election-summary-card {
        min-width: 0;
        display: flex;
        align-items: center;
        gap: .75rem;
        border: 1px solid rgba(148, 163, 184, .24);
        border-radius: 1rem;
        background: rgba(255, 255, 255, .78);
        padding: .85rem 1rem;
    }

    .election-summary-card__number {
        flex: 0 0 auto;
        min-width: 2.45rem;
        height: 2.45rem;
        padding: 0 .5rem;
        border-radius: .8rem;
        display: grid;
        place-items: center;
        background: #ecfdf5;
        color: #047857;
        font-weight: 900;
        font-size: 1.05rem;
    }

    .election-summary-card--attention .election-summary-card__number {
        background: #fff7ed;
        color: #c2410c;
    }

    .election-summary-card strong,
    .election-summary-card span {
        display: block;
        overflow-wrap: anywhere;
    }

    .election-summary-card strong {
        font-size: .9rem;
        color: #172033;
    }

    .election-summary-card span {
        margin-top: .12rem;
        font-size: .76rem;
        color: #718096;
    }

    .election-tabs-shell {
        min-width: 0;
        margin-top: 1.2rem;
    }

    .election-tabs {
        min-width: 0;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .55rem;
        padding: .4rem;
        border-radius: 1rem;
        border: 1px solid #e4eaf1;
        background: #f7f9fc;
    }

    .election-tab {
        min-width: 0;
        border: 0;
        border-radius: .75rem;
        padding: .75rem .8rem;
        background: transparent;
        color: #64748b;
        font-weight: 800;
        line-height: 1.5;
        cursor: pointer;
        transition: background .18s ease, color .18s ease, box-shadow .18s ease;
        overflow-wrap: anywhere;
    }

    .election-tab[aria-selected="true"] {
        background: #fff;
        color: #047857;
        box-shadow: 0 4px 14px rgba(15, 23, 42, .08);
    }

    .election-panel {
        min-width: 0;
        padding-top: 1rem;
    }

    .election-panel[hidden] {
        display: none !important;
    }

    .election-panel-heading {
        min-width: 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: .8rem;
        flex-wrap: wrap;
        margin: .2rem 0 .85rem;
    }

    .election-panel-heading h2 {
        margin: 0;
        font-size: 1.02rem;
        font-weight: 900;
        color: #172033;
    }

    .election-panel-heading span {
        color: #748196;
        font-size: .8rem;
    }

    .election-center-grid {
        min-width: 0;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }

    .election-card {
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: .95rem;
        border: 1px solid #e2e8f0;
        border-radius: 1.2rem;
        background: #fff;
        padding: 1.05rem;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .045);
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }

    .election-card:hover {
        transform: translateY(-2px);
        border-color: rgba(16, 185, 129, .3);
        box-shadow: 0 14px 32px rgba(15, 23, 42, .07);
    }

    .election-card--action {
        border-color: rgba(234, 88, 12, .24);
        box-shadow: 0 8px 28px rgba(234, 88, 12, .055);
    }

    .election-card__meta,
    .election-card__badges {
        min-width: 0;
        display: flex;
        align-items: center;
        gap: .45rem;
        flex-wrap: wrap;
    }

    .election-card__meta {
        justify-content: space-between;
    }

    .election-badge {
        max-width: 100%;
        display: inline-flex;
        align-items: center;
        gap: .32rem;
        border-radius: 999px;
        padding: .35rem .62rem;
        font-size: .72rem;
        font-weight: 800;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }

    .election-badge--systemic {
        background: #ecfdf5;
        color: #047857;
    }

    .election-badge--internal {
        background: #eff6ff;
        color: #1d4ed8;
    }

    .election-badge--status {
        background: #f1f5f9;
        color: #475569;
    }

    .election-badge--attention {
        background: #fff7ed;
        color: #c2410c;
    }

    .election-card__group {
        min-width: 0;
        font-size: .78rem;
        color: #768397;
        font-weight: 700;
        overflow-wrap: anywhere;
    }

    .election-card__title {
        min-width: 0;
        margin: -.15rem 0 0;
        font-size: 1.02rem;
        font-weight: 900;
        line-height: 1.75;
        color: #172033;
        overflow-wrap: anywhere;
    }

    .election-card__state {
        min-width: 0;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .6rem;
    }

    .election-state-box {
        min-width: 0;
        border-radius: .9rem;
        border: 1px solid #edf1f5;
        background: #fafcfd;
        padding: .75rem;
    }

    .election-state-box__label {
        display: block;
        margin-bottom: .26rem;
        color: #8290a5;
        font-size: .69rem;
        font-weight: 700;
    }

    .election-state-box__value {
        display: block;
        color: #263247;
        font-size: .81rem;
        font-weight: 800;
        line-height: 1.75;
        overflow-wrap: anywhere;
    }

    .election-state-box__value--eligible {
        color: #047857;
    }

    .election-state-box__value--blocked {
        color: #b45309;
    }

    .election-card__deadline {
        min-width: 0;
        display: flex;
        align-items: center;
        gap: .45rem;
        color: #66758a;
        font-size: .78rem;
        line-height: 1.7;
        overflow-wrap: anywhere;
    }

    .election-card__actions {
        min-width: 0;
        display: flex;
        align-items: stretch;
        gap: .55rem;
        flex-wrap: wrap;
        margin-top: auto;
    }

    .election-card__actions a {
        min-width: 0;
        min-height: 2.65rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .42rem;
        border-radius: .82rem;
        padding: .65rem .9rem;
        font-size: .8rem;
        font-weight: 900;
        text-decoration: none;
        overflow-wrap: anywhere;
    }

    .election-action--primary {
        flex: 1 1 180px;
        color: #fff;
        background: linear-gradient(135deg, #10b981, #059669);
        box-shadow: 0 7px 18px rgba(5, 150, 105, .18);
    }

    .election-action--primary:hover {
        color: #fff;
        filter: brightness(.98);
    }

    .election-action--secondary {
        flex: 0 1 auto;
        color: #0f766e;
        background: #f0fdfa;
        border: 1px solid #ccfbf1;
    }

    .election-action--secondary:hover {
        color: #0f766e;
        background: #e6fffa;
    }

    .election-empty {
        min-width: 0;
        display: grid;
        place-items: center;
        text-align: center;
        min-height: 220px;
        padding: 1.4rem;
        border: 1px dashed #d9e1ea;
        border-radius: 1.1rem;
        background: #fbfcfe;
        color: #64748b;
    }

    .election-empty__icon {
        width: 3.2rem;
        height: 3.2rem;
        margin: 0 auto .7rem;
        display: grid;
        place-items: center;
        border-radius: 1rem;
        background: #f1f5f9;
        color: #94a3b8;
        font-size: 1.2rem;
    }

    .election-empty strong {
        display: block;
        margin-bottom: .3rem;
        color: #354154;
    }

    .election-empty p {
        margin: 0;
        max-width: 430px;
        font-size: .82rem;
        line-height: 1.85;
        overflow-wrap: anywhere;
    }

    body.dark-mode .election-center-hero,
    body.dark-mode .election-card,
    body.dark-mode .election-tab[aria-selected="true"],
    body.dark-mode .election-summary-card {
        background: #252b34;
        border-color: #3a4655;
    }

    body.dark-mode .election-tabs,
    body.dark-mode .election-state-box,
    body.dark-mode .election-empty {
        background: #1f252d;
        border-color: #3a4655;
    }

    body.dark-mode .election-center-heading h1,
    body.dark-mode .election-card__title,
    body.dark-mode .election-panel-heading h2,
    body.dark-mode .election-summary-card strong,
    body.dark-mode .election-state-box__value,
    body.dark-mode .election-empty strong {
        color: #eef5f2;
    }

    body.dark-mode .election-center-heading p,
    body.dark-mode .election-card__group,
    body.dark-mode .election-card__deadline,
    body.dark-mode .election-panel-heading span,
    body.dark-mode .election-state-box__label {
        color: #a8b3c1;
    }

    @media (max-width: 760px) {
        .election-center-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .election-center-hero {
            padding: 1.1rem;
            border-radius: 1.25rem;
        }

        .election-center-heading {
            flex-basis: 100%;
        }

        .election-history-link {
            width: 100%;
        }
    }

    @media (max-width: 640px) {
        .election-center-page {
            padding: .8rem .65rem 2rem;
        }

        .election-center-heading__icon {
            flex-basis: 2.55rem;
            width: 2.55rem;
            height: 2.55rem;
            border-radius: .8rem;
        }

        .election-center-heading h1 {
            font-size: 1.32rem;
        }

        .election-center-heading p {
            font-size: .83rem;
            line-height: 1.85;
        }

        .election-center-summary {
            grid-template-columns: minmax(0, 1fr);
        }

        .election-tabs {
            gap: .3rem;
            padding: .3rem;
        }

        .election-tab {
            padding: .65rem .45rem;
            font-size: .78rem;
        }

        .election-card {
            padding: .9rem;
            border-radius: 1rem;
        }

        .election-card__state {
            grid-template-columns: minmax(0, 1fr);
        }

        .election-card__actions {
            flex-direction: column;
        }

        .election-card__actions a {
            width: 100%;
            flex: 1 1 auto;
        }
    }
</style>
@endpush

@section('content')
@php
    $systemic = collect($snapshot['systemic'] ?? []);
    $internal = collect($snapshot['internal'] ?? []);
    $summary = $snapshot['summary'] ?? ['action_required' => 0, 'related' => 0, 'total' => 0];
    $defaultTab = $systemic->isEmpty() && $internal->isNotEmpty() ? 'internal' : 'systemic';
@endphp

<div class="election-center-page" dir="rtl" data-current-election-center data-default-election-tab="{{ $defaultTab }}">
    <section class="election-center-hero" aria-labelledby="current-elections-title">
        <div class="election-center-hero__top">
            <div class="election-center-heading">
                <span class="election-center-heading__icon" aria-hidden="true"><i class="fas fa-vote-yea"></i></span>
                <div>
                    <h1 id="current-elections-title">انتخابات جاری</h1>
                    <p>همه انتخابات مرتبط با گروه‌های شما در این مرکز جمع شده‌اند. وضعیت حق رأی و اقدام قابل انجام از قواعد واقعی هر انتخابات خوانده می‌شود تا پیش از ورود به برگه رأی بدانید چه امکانی دارید.</p>
                </div>
            </div>

            <a class="election-history-link" href="{{ route('history.election-history') }}">
                <i class="fas fa-history" aria-hidden="true"></i>
                <span>تاریخچه انتخابات من</span>
            </a>
        </div>

        <div class="election-center-summary" aria-label="خلاصه وضعیت انتخابات">
            <div class="election-summary-card election-summary-card--attention">
                <span class="election-summary-card__number">{{ (int) ($summary['action_required'] ?? 0) }}</span>
                <div>
                    <strong>نیازمند اقدام</strong>
                    <span>انتخاباتی که اکنون می‌توانید در آن رأی ثبت کنید.</span>
                </div>
            </div>
            <div class="election-summary-card">
                <span class="election-summary-card__number">{{ (int) ($summary['related'] ?? 0) }}</span>
                <div>
                    <strong>سایر انتخابات مرتبط</strong>
                    <span>برای پیگیری وضعیت، رأی ثبت‌شده یا مراحل بعدی.</span>
                </div>
            </div>
        </div>
    </section>

    <section class="election-tabs-shell">
        <div class="election-tabs" role="tablist" aria-label="نوع انتخابات">
            <button
                type="button"
                class="election-tab"
                id="systemic-election-tab"
                role="tab"
                aria-controls="systemic-election-panel"
                aria-selected="{{ $defaultTab === 'systemic' ? 'true' : 'false' }}"
                data-election-tab-target="systemic"
            >
                انتخابات سیستمی ({{ $systemic->count() }})
            </button>
            <button
                type="button"
                class="election-tab"
                id="internal-election-tab"
                role="tab"
                aria-controls="internal-election-panel"
                aria-selected="{{ $defaultTab === 'internal' ? 'true' : 'false' }}"
                data-election-tab-target="internal"
            >
                انتخابات درون‌گروهی ({{ $internal->count() }})
            </button>
        </div>

        <div
            class="election-panel"
            id="systemic-election-panel"
            role="tabpanel"
            aria-labelledby="systemic-election-tab"
            data-election-type="systemic"
            @if($defaultTab !== 'systemic') hidden @endif
        >
            <div class="election-panel-heading">
                <h2>انتخابات سیستمی گروه‌های شما</h2>
                <span>چرخه‌های جاری سامانه از شروع تا تکمیل انتصاب</span>
            </div>

            @if($systemic->isNotEmpty())
                <div class="election-center-grid">
                    @foreach($systemic as $item)
                        <article class="election-card {{ ($item['priority_bucket'] ?? '') === 'action_required' ? 'election-card--action' : '' }}" data-election-card>
                            <div class="election-card__meta">
                                <div class="election-card__badges">
                                    <span class="election-badge election-badge--systemic"><i class="fas fa-landmark" aria-hidden="true"></i> سیستمی</span>
                                    <span class="election-badge election-badge--status">{{ $item['status_label'] }}</span>
                                    @if(($item['priority_bucket'] ?? '') === 'action_required')
                                        <span class="election-badge election-badge--attention">نیازمند اقدام</span>
                                    @endif
                                </div>
                                <span class="election-card__group"><i class="fas fa-users" aria-hidden="true"></i> {{ $item['group_name'] }}</span>
                            </div>

                            <h3 class="election-card__title">{{ $item['title'] }}</h3>

                            <div class="election-card__state">
                                <div class="election-state-box">
                                    <span class="election-state-box__label">وضعیت مشارکت شما</span>
                                    <strong class="election-state-box__value {{ $item['eligible'] ? 'election-state-box__value--eligible' : 'election-state-box__value--blocked' }}">{{ $item['eligibility_label'] }}</strong>
                                </div>
                                <div class="election-state-box">
                                    <span class="election-state-box__label">وضعیت رأی شما</span>
                                    <strong class="election-state-box__value">
                                        @if($item['has_voted'])
                                            {{ $item['can_edit_vote'] ? 'رأی ثبت شده و قابل ویرایش است' : 'رأی شما ثبت شده است' }}
                                        @elseif($item['can_vote_now'])
                                            هنوز رأیی ثبت نکرده‌اید
                                        @else
                                            در این مرحله رأی جدید ثبت نمی‌شود
                                        @endif
                                    </strong>
                                </div>
                            </div>

                            @if(!empty($item['deadline_label']))
                                <div class="election-card__deadline"><i class="far fa-clock" aria-hidden="true"></i><span>زمان مرتبط: {{ $item['deadline_label'] }}</span></div>
                            @endif

                            <div class="election-card__actions">
                                <a data-primary-election-action class="election-action--primary" href="{{ $item['primary_action']['url'] }}">
                                    <i class="{{ ($item['primary_action']['kind'] ?? '') === 'vote' ? 'fas fa-check-to-slot' : 'fas fa-circle-info' }}" aria-hidden="true"></i>
                                    {{ $item['primary_action']['label'] }}
                                </a>
                                @if(!empty($item['secondary_action']))
                                    <a class="election-action--secondary" href="{{ $item['secondary_action']['url'] }}">{{ $item['secondary_action']['label'] }}</a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="election-empty">
                    <div>
                        <div class="election-empty__icon"><i class="fas fa-box-open" aria-hidden="true"></i></div>
                        <strong>انتخابات سیستمی جاری ندارید</strong>
                        <p>اگر چرخه سیستمی تازه‌ای در یکی از گروه‌های مرتبط با شما آغاز شود، در همین تب نمایش داده خواهد شد.</p>
                    </div>
                </div>
            @endif
        </div>

        <div
            class="election-panel"
            id="internal-election-panel"
            role="tabpanel"
            aria-labelledby="internal-election-tab"
            data-election-type="internal"
            @if($defaultTab !== 'internal') hidden @endif
        >
            <div class="election-panel-heading">
                <h2>انتخابات درون‌گروهی</h2>
                <span>انتخاباتی که اعضا در داخل گروه‌ها ایجاد کرده‌اند</span>
            </div>

            @if($internal->isNotEmpty())
                <div class="election-center-grid">
                    @foreach($internal as $item)
                        <article class="election-card {{ ($item['priority_bucket'] ?? '') === 'action_required' ? 'election-card--action' : '' }}" data-election-card>
                            <div class="election-card__meta">
                                <div class="election-card__badges">
                                    <span class="election-badge election-badge--internal"><i class="fas fa-users-rectangle" aria-hidden="true"></i> درون‌گروهی</span>
                                    <span class="election-badge election-badge--status">{{ $item['status_label'] }}</span>
                                    @if(($item['priority_bucket'] ?? '') === 'action_required')
                                        <span class="election-badge election-badge--attention">نیازمند اقدام</span>
                                    @endif
                                </div>
                                <span class="election-card__group"><i class="fas fa-users" aria-hidden="true"></i> {{ $item['group_name'] }}</span>
                            </div>

                            <h3 class="election-card__title">{{ $item['title'] }}</h3>

                            <div class="election-card__state">
                                <div class="election-state-box">
                                    <span class="election-state-box__label">وضعیت مشارکت شما</span>
                                    <strong class="election-state-box__value {{ $item['eligible'] ? 'election-state-box__value--eligible' : 'election-state-box__value--blocked' }}">{{ $item['eligibility_label'] }}</strong>
                                </div>
                                <div class="election-state-box">
                                    <span class="election-state-box__label">وضعیت رأی شما</span>
                                    <strong class="election-state-box__value">
                                        @if($item['has_voted'])
                                            {{ $item['can_edit_vote'] ? 'رأی ثبت شده و قابل تغییر است' : 'رأی شما ثبت شده است' }}
                                        @elseif($item['can_vote_now'])
                                            هنوز رأیی ثبت نکرده‌اید
                                        @else
                                            فقط امکان مشاهده دارید
                                        @endif
                                    </strong>
                                </div>
                            </div>

                            @if(!empty($item['deadline_label']))
                                <div class="election-card__deadline"><i class="far fa-clock" aria-hidden="true"></i><span>مهلت: {{ $item['deadline_label'] }}</span></div>
                            @endif

                            <div class="election-card__actions">
                                <a data-primary-election-action class="election-action--primary" href="{{ $item['primary_action']['url'] }}">
                                    <i class="{{ ($item['primary_action']['kind'] ?? '') === 'vote' ? 'fas fa-check-to-slot' : 'fas fa-circle-info' }}" aria-hidden="true"></i>
                                    {{ $item['primary_action']['label'] }}
                                </a>
                                @if(!empty($item['secondary_action']))
                                    <a class="election-action--secondary" href="{{ $item['secondary_action']['url'] }}">{{ $item['secondary_action']['label'] }}</a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="election-empty">
                    <div>
                        <div class="election-empty__icon"><i class="fas fa-box-open" aria-hidden="true"></i></div>
                        <strong>انتخابات درون‌گروهی فعالی ندارید</strong>
                        <p>انتخابات داخلی فعال و مرتبط با گروه‌های شما در این بخش ظاهر می‌شود و از نظرسنجی‌های عادی جدا نگه داشته می‌شود.</p>
                    </div>
                </div>
            @endif
        </div>
    </section>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const root = document.querySelector('[data-current-election-center]');
        if (!root) return;

        const tabs = Array.from(root.querySelectorAll('[data-election-tab-target]'));
        const panels = {
            systemic: root.querySelector('[data-election-type="systemic"]'),
            internal: root.querySelector('[data-election-type="internal"]')
        };

        function activate(target) {
            tabs.forEach(function (tab) {
                const active = tab.dataset.electionTabTarget === target;
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
                tab.setAttribute('tabindex', active ? '0' : '-1');
            });
            Object.entries(panels).forEach(function ([key, panel]) {
                if (panel) panel.hidden = key !== target;
            });
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                activate(tab.dataset.electionTabTarget);
            });
            tab.addEventListener('keydown', function (event) {
                if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
                event.preventDefault();
                const current = tabs.indexOf(tab);
                const next = event.key === 'ArrowLeft'
                    ? (current + 1) % tabs.length
                    : (current - 1 + tabs.length) % tabs.length;
                tabs[next].focus();
                activate(tabs[next].dataset.electionTabTarget);
            });
        });

        activate(root.dataset.defaultElectionTab || 'systemic');
    });
</script>
@endsection
