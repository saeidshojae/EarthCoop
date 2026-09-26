@extends('layouts.unified')

@section('title', __('navigation.footer_home') . ' - ' . config('app.name', 'EarthCoop'))

@push('styles')
<style>
    .home-page-shell {
        --home-surface: linear-gradient(145deg, var(--color-pure-white) 0%, #f0f4f7 100%);
        --home-border: rgba(148, 163, 184, 0.24);
        --home-shadow: 0 12px 35px rgba(15, 23, 42, 0.08);
        --home-shadow-soft: 0 8px 24px rgba(15, 23, 42, 0.06);
        --home-gold: var(--color-digital-gold, #f59e0b);
        padding-top: 1rem;
        padding-bottom: 2.5rem;
    }

    .home-main {
        min-width: 0;
        width: 100%;
        flex: 1 1 auto;
    }

    .home-identity-surface {
        position: relative;
        overflow: hidden;
        background: linear-gradient(145deg, var(--color-pure-white) 0%, #f0f4f7 100%);
        border: 1px solid var(--home-border);
        border-radius: 18px;
        box-shadow: var(--home-shadow);
    }

    .home-identity-surface::before {
        content: '';
        position: absolute;
        inset: 0 0 auto 0;
        height: 6px;
        background: linear-gradient(90deg, var(--color-earth-green), var(--color-ocean-blue), var(--color-digital-gold));
        z-index: 2;
    }

    .home-hero {
        padding: 1.5rem;
    }

    .home-hero-layout {
        display: grid;
        gap: 1.35rem;
        align-items: center;
    }

    .home-hero-copy {
        min-width: 0;
    }

    .home-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        margin-bottom: .7rem;
        padding: .35rem .7rem;
        border-radius: 9999px;
        background: rgba(16, 185, 129, .1);
        color: var(--color-dark-green);
        font-size: .78rem;
        font-weight: 700;
        line-height: 1.5;
    }

    .home-hero-title {
        margin: 0;
        color: var(--color-gentle-black);
        font-size: clamp(1.65rem, 4vw, 2.55rem);
        line-height: 1.35;
        font-weight: 800;
        letter-spacing: -.015em;
    }

    .home-hero-subtitle {
        max-width: 44rem;
        margin: .75rem 0 0;
        color: #64748b;
        font-size: clamp(.92rem, 2vw, 1.05rem);
        line-height: 1.9;
        font-weight: 400;
    }

    .home-hero-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .7rem;
        margin-top: 1.15rem;
    }

    .home-primary-action,
    .home-secondary-action {
        min-height: 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .55rem;
        padding: .7rem 1.05rem;
        border-radius: 9999px;
        font-size: .9rem;
        font-weight: 700;
        line-height: 1.25;
        text-decoration: none;
        transition: transform .2s ease, box-shadow .2s ease, background-color .2s ease, border-color .2s ease;
    }

    .home-primary-action {
        border: 0;
        color: #fff;
        background: linear-gradient(135deg, var(--color-earth-green), var(--color-dark-green));
        box-shadow: 0 8px 18px rgba(16, 185, 129, .22);
    }

    .home-primary-action:hover,
    .home-primary-action:focus-visible {
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 11px 23px rgba(16, 185, 129, .28);
    }

    .home-secondary-action {
        border: 1px solid rgba(59, 130, 246, .28);
        color: var(--color-dark-blue);
        background: rgba(255, 255, 255, .82);
        box-shadow: 0 5px 14px rgba(15, 23, 42, .05);
    }

    .home-secondary-action:hover,
    .home-secondary-action:focus-visible {
        color: var(--color-dark-blue);
        border-color: rgba(59, 130, 246, .48);
        background: #fff;
        transform: translateY(-2px);
        box-shadow: 0 8px 18px rgba(59, 130, 246, .1);
    }

    .home-primary-action:focus-visible,
    .home-secondary-action:focus-visible,
    .home-interactive-card:focus-visible {
        outline: 3px solid rgba(59, 130, 246, .2);
        outline-offset: 3px;
    }

    .home-hero-mark {
        display: none;
        min-height: 190px;
        align-items: center;
        justify-content: center;
        position: relative;
    }

    .home-hero-mark::before,
    .home-hero-mark::after {
        content: '';
        position: absolute;
        border-radius: 9999px;
        pointer-events: none;
    }

    .home-hero-mark::before {
        width: 170px;
        height: 170px;
        background: rgba(16, 185, 129, .08);
        border: 1px solid rgba(16, 185, 129, .14);
    }

    .home-hero-mark::after {
        width: 115px;
        height: 115px;
        background: rgba(59, 130, 246, .07);
        border: 1px solid rgba(59, 130, 246, .12);
    }

    .home-hero-mark-icon {
        position: relative;
        z-index: 1;
        width: 72px;
        height: 72px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        color: #fff;
        font-size: 1.75rem;
        background: linear-gradient(135deg, var(--color-earth-green), var(--color-ocean-blue));
        box-shadow: 0 14px 30px rgba(16, 185, 129, .2);
    }

    .home-section {
        margin-top: 1.35rem;
    }

    .home-section-header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: .85rem;
    }

    .home-section-title {
        margin: 0;
        color: var(--color-gentle-black);
        font-size: clamp(1.05rem, 2.6vw, 1.35rem);
        line-height: 1.5;
        font-weight: 700;
    }

    .home-section-description {
        margin: .2rem 0 0;
        color: #64748b;
        font-size: .84rem;
        line-height: 1.8;
    }

    .home-journey-surface,
    .home-groups-surface,
    .home-admin-surface,
    .home-auctions-surface {
        padding: 1.15rem;
    }

    .home-journey-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .75rem;
    }

    .home-interactive-card {
        position: relative;
        display: flex;
        flex-direction: column;
        min-width: 0;
        min-height: 100%;
        padding: 1rem;
        overflow: hidden;
        border: 1px solid rgba(148, 163, 184, .24);
        border-radius: 14px;
        color: inherit;
        text-decoration: none;
        background: rgba(255, 255, 255, .8);
        box-shadow: 0 5px 16px rgba(15, 23, 42, .045);
        transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    }

    .home-interactive-card:hover,
    .home-interactive-card:focus-visible {
        color: inherit;
        transform: translateY(-2px);
        border-color: color-mix(in srgb, var(--card-accent) 28%, #cbd5e1);
        box-shadow: 0 10px 24px rgba(15, 23, 42, .075);
    }

    .home-card-topline {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .85rem;
    }

    .home-card-icon {
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        display: grid;
        place-items: center;
        border-radius: 12px;
        color: var(--card-accent);
        background: color-mix(in srgb, var(--card-accent) 11%, white);
        font-size: 1.05rem;
    }

    .home-step-number {
        width: 28px;
        height: 28px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        color: var(--card-accent);
        background: color-mix(in srgb, var(--card-accent) 9%, white);
        border: 1px solid color-mix(in srgb, var(--card-accent) 20%, white);
        font-size: .75rem;
        font-weight: 800;
    }

    .home-card-title {
        margin: 0;
        color: var(--color-gentle-black);
        font-size: .96rem;
        font-weight: 700;
        line-height: 1.55;
    }

    .home-card-text {
        margin: .35rem 0 .8rem;
        color: #64748b;
        font-size: .79rem;
        line-height: 1.75;
    }

    .home-card-link {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        margin-top: auto;
        color: var(--card-accent);
        font-size: .78rem;
        font-weight: 700;
    }

    .home-slider-shell {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        aspect-ratio: 2 / 1;
        overflow: hidden;
        border: 1px solid rgba(148, 163, 184, .24);
        border-radius: 14px;
        box-shadow: var(--home-shadow-soft);
        background: #fff;
    }

    swiper-container {
        display: block;
        width: 100%;
        height: 100%;
        max-width: 100%;
        min-width: 0;
        overflow: hidden;
    }

    swiper-slide {
        display: flex;
        width: 100%;
        height: 100%;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    swiper-slide img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform .3s ease;
    }

    .home-slider-shell:hover swiper-slide img {
        transform: scale(1.015);
    }

    .home-admin-copy {
        margin-top: 1rem;
        color: #475569;
        font-size: .92rem;
        line-height: 1.95;
    }

    .home-admin-copy > :last-child {
        margin-bottom: 0;
    }

    .home-groups-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .8rem;
    }

    .group-stat-card {
        --card-accent: var(--color-earth-green);
    }

    .group-stat-card .home-card-topline {
        margin-bottom: .55rem;
    }

    .group-stat-number {
        margin: .05rem 0 .45rem;
        color: var(--color-gentle-black);
        font-size: clamp(2rem, 5vw, 2.75rem);
        font-weight: 800;
        line-height: 1.05;
    }

    .group-stat-meta {
        display: flex;
        align-items: center;
        gap: .42rem;
        padding-top: .65rem;
        margin-top: auto;
        border-top: 1px solid rgba(148, 163, 184, .18);
        color: #64748b;
        font-size: .76rem;
        line-height: 1.5;
    }

    .group-stat-meta i {
        color: var(--card-accent);
    }

    .home-next-action {
        display: grid;
        gap: .85rem;
        padding: 1.15rem;
        border: 1px solid rgba(16, 185, 129, .15);
        border-radius: 14px;
        background: linear-gradient(135deg, rgba(16, 185, 129, .07), rgba(59, 130, 246, .045));
    }

    .home-next-action-copy h3 {
        margin: 0;
        color: var(--color-gentle-black);
        font-size: 1rem;
        font-weight: 700;
    }

    .home-next-action-copy p {
        margin: .3rem 0 0;
        color: #64748b;
        font-size: .82rem;
        line-height: 1.75;
    }

    .home-auctions-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .8rem;
    }

    .home-auction-card h3 {
        margin: 0;
        color: var(--color-gentle-black);
        font-size: .95rem;
        font-weight: 700;
    }

    .home-auction-card p {
        margin: .35rem 0 .9rem;
        color: #64748b;
        font-size: .8rem;
    }

    .home-toast {
        position: fixed;
        z-index: 50;
        bottom: 1rem;
        left: 1rem;
        width: min(92vw, 26rem);
        padding: .9rem 1rem;
        border-radius: 14px;
        border: 1px solid rgba(148, 163, 184, .22);
        background: #fff;
        box-shadow: 0 18px 45px rgba(15, 23, 42, .16);
    }

    .home-toast-icon {
        width: 38px;
        height: 38px;
        display: grid;
        place-items: center;
        flex: 0 0 38px;
        border-radius: 50%;
        color: #fff;
    }

    body.dark-mode .home-identity-surface,
    body.dark-mode .home-interactive-card,
    body.dark-mode .home-slider-shell,
    body.dark-mode .home-toast {
        background: var(--card-dark, #252525);
        border-color: var(--border-dark, #404040);
    }

    body.dark-mode .home-hero-title,
    body.dark-mode .home-section-title,
    body.dark-mode .home-card-title,
    body.dark-mode .group-stat-number,
    body.dark-mode .home-next-action-copy h3,
    body.dark-mode .home-auction-card h3 {
        color: var(--text-dark, #f1f5f9);
    }

    body.dark-mode .home-hero-subtitle,
    body.dark-mode .home-section-description,
    body.dark-mode .home-card-text,
    body.dark-mode .group-stat-meta,
    body.dark-mode .home-admin-copy,
    body.dark-mode .home-next-action-copy p,
    body.dark-mode .home-auction-card p {
        color: #cbd5e1;
    }

    @media (min-width: 768px) {
        .home-hero-layout {
            grid-template-columns: minmax(0, 1fr) 210px;
            gap: 2rem;
        }

        .home-hero-mark {
            display: flex;
        }

        .home-next-action {
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
        }
    }

    @media (max-width: 1023.98px) {
        .home-page-shell {
            padding-top: .75rem;
        }

        .home-layout {
            gap: 1rem;
        }
    }

    @media (max-width: 767.98px) {
        .home-page-shell {
            padding-inline: .7rem;
            padding-bottom: 1.5rem;
        }

        .home-hero,
        .home-journey-surface,
        .home-groups-surface,
        .home-admin-surface,
        .home-auctions-surface {
            padding: .95rem;
        }

        .home-identity-surface {
            border-radius: 12px;
        }

        .home-hero-title {
            font-size: 1.55rem;
            line-height: 1.45;
        }

        .home-hero-subtitle {
            font-size: .88rem;
            line-height: 1.85;
        }

        .home-hero-actions {
            display: grid;
            grid-template-columns: 1fr;
        }

        .home-primary-action,
        .home-secondary-action {
            width: 100%;
            min-height: 48px;
        }

        .home-section-header {
            align-items: flex-start;
            flex-direction: column;
            gap: .25rem;
        }

        .home-journey-grid {
            grid-template-columns: 1fr;
            gap: .65rem;
        }

        .home-interactive-card {
            padding: .9rem;
            min-height: auto;
        }

        .home-journey-grid .home-interactive-card {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            grid-template-areas:
                'icon title step'
                'icon text text'
                'icon link link';
            column-gap: .75rem;
            row-gap: .18rem;
            align-items: start;
        }

        .home-journey-grid .home-card-topline {
            display: contents;
        }

        .home-journey-grid .home-card-icon {
            grid-area: icon;
            width: 40px;
            height: 40px;
            margin-top: .1rem;
        }

        .home-journey-grid .home-step-number {
            grid-area: step;
        }

        .home-journey-grid .home-card-title {
            grid-area: title;
            align-self: center;
        }

        .home-journey-grid .home-card-text {
            grid-area: text;
            margin: .15rem 0 .3rem;
        }

        .home-journey-grid .home-card-link {
            grid-area: link;
            margin-top: .1rem;
        }

        .home-groups-grid,
        .home-auctions-grid {
            grid-template-columns: 1fr;
        }

        .group-stat-card {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: .3rem .8rem;
            align-items: center;
        }

        .group-stat-card .home-card-topline {
            margin: 0;
        }

        .group-stat-number {
            grid-column: 2;
            grid-row: 1 / span 2;
            font-size: 2.1rem;
            margin: 0;
        }

        .group-stat-meta {
            padding-top: .45rem;
            margin-top: .2rem;
        }

        .home-slider-shell {
            aspect-ratio: 16 / 9;
            border-radius: 12px;
        }

        .home-admin-copy {
            font-size: .86rem;
            line-height: 1.9;
        }

        .home-next-action {
            padding: .95rem;
        }

        .home-next-action .home-primary-action {
            width: 100%;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .home-primary-action,
        .home-secondary-action,
        .home-interactive-card,
        swiper-slide img {
            transition: none !important;
        }

        .home-primary-action:hover,
        .home-primary-action:focus-visible,
        .home-secondary-action:hover,
        .home-secondary-action:focus-visible,
        .home-interactive-card:hover,
        .home-interactive-card:focus-visible,
        .home-slider-shell:hover swiper-slide img {
            transform: none !important;
        }
    }
</style>
@endpush

@section('content')
<div class="home-page-shell">
    <div class="home-layout container mx-auto flex flex-col lg:flex-row gap-6 px-3 sm:px-5 md:px-7">
        @include('partials.sidebar-unified')

        <main class="home-main" data-home-redesign>
            <section class="home-identity-surface home-hero" data-home-identity-surface>
                <div class="home-hero-layout">
                    <div class="home-hero-copy">
                        <span class="home-eyebrow"><i class="fas fa-seedling" aria-hidden="true"></i>خانهٔ من در ارث‌کوپ</span>
                        <h1 class="home-hero-title">{{ $homeSetting?->home_titre ?: 'به زمین نو خوش آمدید' }}</h1>
                        <p class="home-hero-subtitle">از همین‌جا مسیر عضویت، حکمرانی، گروه‌ها و مشارکت اقتصادی خود را دنبال کنید. هر بخش یک قدم روشن برای حضور فعال‌تر شما در ارث‌کوپ است.</p>
                        <div class="home-hero-actions">
                            <a href="#home-journey" class="home-primary-action"><i class="fas fa-route" aria-hidden="true"></i>شروع مسیر من</a>
                            <a href="{{ route('groups.index') }}" class="home-secondary-action"><i class="fas fa-users" aria-hidden="true"></i>مشاهده گروه‌های من</a>
                        </div>
                    </div>
                    <div class="home-hero-mark" aria-hidden="true">
                        <div class="home-hero-mark-icon"><i class="fas fa-earth-asia"></i></div>
                    </div>
                </div>
            </section>

            <section class="home-section home-identity-surface home-journey-surface" id="home-journey" data-home-journey>
                <div class="home-section-header">
                    <div>
                        <span class="home-eyebrow"><i class="fas fa-compass" aria-hidden="true"></i>قدم‌های بعدی</span>
                        <h2 class="home-section-title">مسیر من در ارث‌کوپ</h2>
                        <p class="home-section-description">چهار مسیر اصلی را هر زمان لازم بود مرور یا تکمیل کنید.</p>
                    </div>
                </div>

                <div class="home-journey-grid">
                    <a href="{{ route('location-governance.me') }}" class="home-interactive-card" style="--card-accent: var(--color-earth-green);">
                        <div class="home-card-topline">
                            <span class="home-card-icon"><i class="fas fa-location-dot" aria-hidden="true"></i></span>
                            <span class="home-step-number">۱</span>
                        </div>
                        <h3 class="home-card-title">مکان و حکمرانی</h3>
                        <p class="home-card-text">محل سکونت و حوزه‌های رسمی حکمرانی خود را ببینید و در صورت نیاز تکمیل کنید.</p>
                        <span class="home-card-link">بررسی مکان من <i class="fas fa-arrow-left" aria-hidden="true"></i></span>
                    </a>

                    <a href="{{ route('najm-bahar.dashboard') }}" class="home-interactive-card" style="--card-accent: var(--color-ocean-blue);">
                        <div class="home-card-topline">
                            <span class="home-card-icon"><i class="fas fa-coins" aria-hidden="true"></i></span>
                            <span class="home-step-number">۲</span>
                        </div>
                        <h3 class="home-card-title">نجم بهار</h3>
                        <p class="home-card-text">حساب اقتصادی، موجودی و مسیرهای مشارکت مالی خود را از نجم بهار دنبال کنید.</p>
                        <span class="home-card-link">ورود به نجم بهار <i class="fas fa-arrow-left" aria-hidden="true"></i></span>
                    </a>

                    <a href="{{ route('groups.index') }}" class="home-interactive-card" style="--card-accent: var(--color-digital-gold);">
                        <div class="home-card-topline">
                            <span class="home-card-icon"><i class="fas fa-people-group" aria-hidden="true"></i></span>
                            <span class="home-step-number">۳</span>
                        </div>
                        <h3 class="home-card-title">گروه‌های من</h3>
                        <p class="home-card-text">مجامع عمومی، گروه‌های تخصصی و گروه‌های اختصاصی خود را یکجا ببینید.</p>
                        <span class="home-card-link">مشاهده گروه‌ها <i class="fas fa-arrow-left" aria-hidden="true"></i></span>
                    </a>

                    <a href="{{ route('my-invation-code') }}" class="home-interactive-card" style="--card-accent: var(--color-earth-green);">
                        <div class="home-card-topline">
                            <span class="home-card-icon"><i class="fas fa-user-plus" aria-hidden="true"></i></span>
                            <span class="home-step-number">۴</span>
                        </div>
                        <h3 class="home-card-title">دعوت و مشارکت</h3>
                        <p class="home-card-text">دوستان خود را دعوت کنید و شبکهٔ محلی و حرفه‌ای ارث‌کوپ را گسترش دهید.</p>
                        <span class="home-card-link">دعوت دوستان <i class="fas fa-arrow-left" aria-hidden="true"></i></span>
                    </a>
                </div>
            </section>

            <section class="home-section home-identity-surface home-admin-surface" data-home-admin-content>
                <div class="home-section-header">
                    <div>
                        <span class="home-eyebrow"><i class="fas fa-bullhorn" aria-hidden="true"></i>از ارث‌کوپ</span>
                        <h2 class="home-section-title">تازه‌ها و راهنمای مسیر</h2>
                    </div>
                </div>

                @if($homeSliders->isNotEmpty())
                    <div class="home-slider-shell">
                        <swiper-container class="mySwiper"
                            @if($homeSliders->count() > 1) pagination="true" loop="true" autoplay-delay="6000" autoplay-disable-on-interaction="false" @endif
                            style="--swiper-pagination-color: var(--color-earth-green); --swiper-pagination-bullet-inactive-color: #d1d5db;">
                            @foreach($homeSliders as $slider)
                                <swiper-slide>
                                    <img src="{{ asset('images/sliders/' . $slider->src) }}" alt="{{ $slider->alt ?: 'اسلایدر ' . $loop->iteration }}">
                                </swiper-slide>
                            @endforeach
                        </swiper-container>
                    </div>
                @endif

                @if(filled($homeSetting?->home_content))
                    <div class="home-admin-copy prose max-w-none">
                        {!! $homeSetting?->home_content !!}
                    </div>
                @endif
            </section>

            @if($groups->count() > 0)
                <section class="home-section home-identity-surface home-groups-surface" data-home-groups>
                    <div class="home-section-header">
                        <div>
                            <span class="home-eyebrow"><i class="fas fa-users" aria-hidden="true"></i>شبکهٔ من</span>
                            <h2 class="home-section-title">گروه‌های من</h2>
                            <p class="home-section-description">نمای سریع گروه‌هایی که از عضویت و مشارکت شما شکل گرفته‌اند.</p>
                        </div>
                        <a href="{{ route('groups.index') }}" class="home-secondary-action">همه گروه‌ها <i class="fas fa-arrow-left" aria-hidden="true"></i></a>
                    </div>

                    <div class="home-groups-grid">
                        <a href="{{ route('groups.index', ['tab' => 'public']) }}" class="home-interactive-card group-stat-card" style="--card-accent: var(--color-earth-green);" aria-label="مشاهده گروه‌های عمومی من">
                            <div class="home-card-topline">
                                <h3 class="home-card-title">گروه‌های عمومی</h3>
                                <span class="home-card-icon"><i class="fas fa-users" aria-hidden="true"></i></span>
                            </div>
                            <div class="group-stat-number">{{ $generalGroups->count() }}</div>
                            <div class="group-stat-meta"><i class="fas fa-layer-group" aria-hidden="true"></i><span>مجامع عمومی و حوزه‌های حکمرانی</span></div>
                        </a>

                        <a href="{{ route('groups.index', ['tab' => 'specialty']) }}" class="home-interactive-card group-stat-card" style="--card-accent: var(--color-ocean-blue);" aria-label="مشاهده گروه‌های تخصصی من">
                            <div class="home-card-topline">
                                <h3 class="home-card-title">گروه‌های تخصصی</h3>
                                <span class="home-card-icon"><i class="fas fa-briefcase" aria-hidden="true"></i></span>
                            </div>
                            <div class="group-stat-number">{{ $specializedGroups->count() }}</div>
                            <div class="group-stat-meta"><i class="fas fa-graduation-cap" aria-hidden="true"></i><span>حرفه، تخصص و تجربه</span></div>
                        </a>

                        <a href="{{ route('groups.index', ['tab' => 'exclusive']) }}" class="home-interactive-card group-stat-card" style="--card-accent: var(--color-digital-gold);" aria-label="مشاهده گروه‌های اختصاصی من">
                            <div class="home-card-topline">
                                <h3 class="home-card-title">گروه‌های اختصاصی</h3>
                                <span class="home-card-icon"><i class="fas fa-star" aria-hidden="true"></i></span>
                            </div>
                            <div class="group-stat-number">{{ $exclusiveGroups->count() }}</div>
                            <div class="group-stat-meta"><i class="fas fa-sparkles" aria-hidden="true"></i><span>گروه‌های ویژه و اختصاصی شما</span></div>
                        </a>
                    </div>
                </section>
            @endif

            <section class="home-section home-identity-surface home-groups-surface" data-home-next-action>
                <div class="home-next-action">
                    <div class="home-next-action-copy">
                        <span class="home-eyebrow"><i class="fas fa-lightbulb" aria-hidden="true"></i>قدم پیشنهادی</span>
                        <h3>مشارکت را از نزدیک‌ترین مسیر به خودتان ادامه دهید</h3>
                        <p>از «مکان و حکمرانی من» شروع کنید؛ سپس گروه‌های مرتبط را ببینید و در گفتگوها، نظرسنجی‌ها و انتخابات فعال مشارکت کنید.</p>
                    </div>
                    <a href="{{ route('location-governance.me') }}" class="home-primary-action"><i class="fas fa-location-dot" aria-hidden="true"></i>رفتن به مکان و حکمرانی</a>
                </div>
            </section>

            @if(isset($activeAuctions) && $activeAuctions->count() > 0)
                <section class="home-section home-identity-surface home-auctions-surface" data-home-auctions>
                    <div class="home-section-header">
                        <div>
                            <span class="home-eyebrow"><i class="fas fa-gavel" aria-hidden="true"></i>فرصت‌های جاری</span>
                            <h2 class="home-section-title">{{ __('navigation.auctions') }}</h2>
                        </div>
                    </div>
                    <div class="home-auctions-grid">
                        @foreach($activeAuctions as $auction)
                            <article class="home-interactive-card home-auction-card" style="--card-accent: var(--color-digital-gold);">
                                <div class="home-card-topline">
                                    <span class="home-card-icon"><i class="fas fa-gavel" aria-hidden="true"></i></span>
                                </div>
                                <h3>{{ $auction->stock->name ?? 'حراج' }}</h3>
                                <p>پایان: {{ $auction->ends_at->diffForHumans() }}</p>
                                <span class="home-card-link">مشاهده جزئیات <i class="fas fa-arrow-left" aria-hidden="true"></i></span>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        </main>
    </div>
</div>

@if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="home-toast" style="display:none" x-cloak role="status" aria-live="polite">
        <div class="flex items-center gap-3">
            <span class="home-toast-icon" style="background:var(--color-earth-green);"><i class="fas fa-check" aria-hidden="true"></i></span>
            <div class="flex-1 min-w-0">
                <p class="font-semibold mb-1" style="color:var(--color-gentle-black);">موفقیت</p>
                <p class="text-sm text-gray-600 mb-0">{{ session('success') }}</p>
            </div>
            <button @click="show = false" class="text-gray-400 hover:text-gray-600" type="button" aria-label="بستن پیام"><i class="fas fa-times" aria-hidden="true"></i></button>
        </div>
    </div>
@endif

@if(session('error'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="home-toast" style="display:none" x-cloak role="alert" aria-live="assertive">
        <div class="flex items-center gap-3">
            <span class="home-toast-icon" style="background:var(--color-red-tomato);"><i class="fas fa-exclamation" aria-hidden="true"></i></span>
            <div class="flex-1 min-w-0">
                <p class="font-semibold mb-1" style="color:var(--color-gentle-black);">خطا</p>
                <p class="text-sm text-gray-600 mb-0">{{ session('error') }}</p>
            </div>
            <button @click="show = false" class="text-gray-400 hover:text-gray-600" type="button" aria-label="بستن پیام"><i class="fas fa-times" aria-hidden="true"></i></button>
        </div>
    </div>
@endif
@endsection
