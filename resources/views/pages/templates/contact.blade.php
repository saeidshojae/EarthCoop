@extends('layouts.unified')

@section('title', ($page->translated_meta_title ?? $page->translated_title) . ' - ' . config('app.name', 'EarthCoop'))

@push('styles')
<style>
    .contact-page-shell {
        padding-block: 1.25rem 2.5rem;
        overflow-x: clip;
    }

    .contact-page-container {
        width: min(100% - 1.25rem, 72rem);
        margin-inline: auto;
        display: grid;
        gap: 1.25rem;
    }

    .contact-hero {
        position: relative;
        overflow: hidden;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.12) 0%, rgba(59, 130, 246, 0.15) 100%);
        border-radius: 1.35rem;
        padding: 1.35rem;
    }

    .contact-hero-grid {
        position: relative;
        z-index: 10;
        display: grid;
        gap: 1.25rem;
        align-items: center;
        min-width: 0;
    }

    .contact-hero-copy {
        min-width: 0;
        text-align: center;
    }

    .contact-hero-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .5rem;
        max-width: 100%;
        padding: .55rem .85rem;
        border: 1px solid rgba(16, 185, 129, .2);
        border-radius: 999px;
        background: rgba(255,255,255,.86);
        color: var(--color-earth-green);
        box-shadow: 0 4px 14px rgba(15,23,42,.06);
        font-size: .78rem;
        font-weight: 700;
    }

    .contact-hero-title {
        margin: 1rem 0 0;
        color: var(--color-gentle-black);
        font-size: clamp(1.8rem, 8vw, 3rem);
        font-weight: 800;
        line-height: 1.35;
    }

    .contact-hero-subtitle {
        max-width: 42rem;
        margin: .75rem auto 0;
        color: #64748b;
        font-size: .95rem;
        line-height: 1.9;
    }

    .contact-hero-actions {
        display: grid;
        grid-template-columns: 1fr;
        gap: .65rem;
        width: min(100%, 24rem);
        margin: 1.1rem auto 0;
    }

    .contact-hero-action {
        min-height: 48px;
        width: 100%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .55rem;
        padding: .75rem 1.1rem;
        border-radius: 999px;
        font-size: .88rem;
        font-weight: 700;
        text-decoration: none;
        transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease, background-color .2s ease;
    }

    .contact-hero-action:hover,
    .contact-hero-action:focus-visible {
        transform: translateY(-2px);
    }

    .contact-hero-action:focus-visible {
        outline: 3px solid rgba(59,130,246,.18);
        outline-offset: 3px;
    }

    .contact-hero-action--primary {
        border: 1px solid transparent;
        color: #fff;
        background: var(--color-earth-green);
        box-shadow: 0 8px 20px rgba(16,185,129,.22);
    }

    .contact-hero-action--secondary {
        border: 1px solid rgba(148,163,184,.45);
        color: #334155;
        background: rgba(255,255,255,.92);
        box-shadow: 0 6px 16px rgba(15,23,42,.06);
    }

    .contact-hero-action--primary:hover,
    .contact-hero-action--primary:focus-visible { color: #fff; background: var(--color-dark-green); }

    .contact-hero-action--secondary:hover,
    .contact-hero-action--secondary:focus-visible {
        color: var(--color-earth-green);
        border-color: rgba(16,185,129,.45);
        background: #fff;
    }

    .contact-hero-info {
        display: grid;
        gap: .75rem;
        min-width: 0;
    }

    .contact-info-card {
        min-width: 0;
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        align-items: center;
        gap: .85rem;
        padding: .9rem 1rem;
        border: 1px solid rgba(226,232,240,.95);
        border-radius: 1rem;
        background: rgba(255,255,255,.95);
        box-shadow: 0 5px 16px rgba(15,23,42,.06);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .contact-info-card__icon {
        width: 44px;
        height: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 44px;
        border-radius: 999px;
        font-size: 1rem;
    }

    .contact-info-card__copy {
        min-width: 0;
        text-align: start;
    }

    .contact-info-card__copy p {
        margin: 0;
    }

    .contact-info-card__value {
        display: inline-block;
        max-width: 100%;
        margin-top: .15rem;
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    .contact-hero::before,
    .contact-hero::after {
        content: '';
        position: absolute;
        width: 260px;
        height: 260px;
        border-radius: 50%;
        filter: blur(80px);
        opacity: 0.5;
    }

    .contact-hero::before {
        top: -140px;
        left: -100px;
        background: rgba(16, 185, 129, 0.5);
    }

    .contact-hero::after {
        bottom: -140px;
        right: -60px;
        background: rgba(37, 99, 235, 0.45);
    }

    .contact-info-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .contact-info-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 45px rgba(15, 23, 42, 0.12);
    }

    .contact-section {
        background: var(--color-pure-white);
        border-radius: 1.5rem;
        border: 1px solid rgba(226, 232, 240, 0.8);
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.1);
    }

    .contact-form-group {
        position: relative;
    }

    .contact-form-group input,
    .contact-form-group textarea {
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    .contact-form-group input:focus,
    .contact-form-group textarea:focus {
        border-color: var(--color-earth-green);
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
    }

    .fade-in-section {
        opacity: 0;
        transform: translateY(26px);
        transition: opacity 0.6s ease, transform 0.6s ease;
    }

    .fade-in-section.is-visible {
        opacity: 1;
        transform: translateY(0);
    }

    .contact-main-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 1.25rem;
    }

    .contact-form-submit {
        display: flex;
        justify-content: center;
    }

    .contact-form-submit button {
        width: 100%;
        min-height: 48px;
    }

    .contact-side-stack {
        display: grid;
        gap: 1rem;
        min-width: 0;
    }

    .contact-feature-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    @media (min-width: 640px) {
        .contact-page-shell { padding-block: 2rem 3rem; }
        .contact-page-container { width: min(100% - 2rem, 72rem); gap: 1.5rem; }
        .contact-hero { padding: 2rem; border-radius: 1.6rem; }
        .contact-hero-actions {
            display: flex;
            justify-content: center;
            width: auto;
        }
        .contact-hero-action { width: auto; min-width: 9.5rem; }
        .contact-hero-info { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .contact-info-card {
            grid-template-columns: 1fr;
            justify-items: center;
            text-align: center;
            padding: 1rem;
        }
        .contact-info-card__copy { text-align: center; }
        .contact-feature-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .contact-form-submit button { width: auto; min-width: 11rem; }
    }

    @media (min-width: 1024px) {
        .contact-page-shell { padding-block: 4rem; }
        .contact-page-container { gap: 2.5rem; }
        .contact-hero { padding: 3rem 2rem; border-radius: 1.75rem; }
        .contact-hero-grid { grid-template-columns: minmax(0, 1.2fr) minmax(18rem, .8fr); gap: 2.25rem; }
        .contact-hero-copy { text-align: center; }
        .contact-hero-subtitle { font-size: 1.08rem; }
        .contact-hero-info { grid-template-columns: 1fr; gap: 1rem; }
        .contact-info-card {
            grid-template-columns: auto minmax(0, 1fr);
            justify-items: stretch;
            text-align: start;
            padding: 1rem 1.25rem;
        }
        .contact-info-card__copy { text-align: start; }
        .contact-main-grid { grid-template-columns: minmax(0, 1.15fr) minmax(18rem, .85fr); gap: 2rem; }
        .contact-feature-grid { gap: 1.5rem; }
    }

    @media (max-width: 639.98px) {
        .contact-section { border-radius: 1.1rem; box-shadow: 0 10px 28px rgba(15,23,42,.07); }
        .contact-info-card:hover { transform: none; }
        .contact-hero::before,
        .contact-hero::after { opacity: .32; }
        .contact-form-group input,
        .contact-form-group textarea { font-size: 16px; }
    }

    @media (prefers-reduced-motion: reduce) {
        .contact-info-card,
        .contact-hero-action,
        .contact-form-group input,
        .contact-form-group textarea,
        .fade-in-section {
            transition: none !important;
        }

        .contact-info-card:hover,
        .contact-hero-action:hover,
        .contact-hero-action:focus-visible {
            transform: none !important;
        }
    }
</style>
@endpush

@section('content')
@php
    $metaDescription = $page->translated_meta_description ?? __('navigation.footer_contact_description', []);
@endphp

<div class="contact-page-shell bg-light-gray/70" style="background-color: var(--color-light-gray);">
    <div class="contact-page-container">
        {{-- Hero Section --}}
        <section class="contact-hero fade-in-section">
            <div class="contact-hero-grid">
                <div class="contact-hero-copy">
                    <div class="contact-hero-badge">
                        <i class="fas fa-comments"></i>
                        {{ __('pages.contact.hero_badge') }}
                    </div>
                    <h1 class="contact-hero-title font-vazirmatn">
                        {{ $page->translated_title }}
                    </h1>
                    <p class="contact-hero-subtitle">
                        {{ $metaDescription ?? __('pages.contact.subtitle') }}
                    </p>
                    <div class="contact-hero-actions">
                        <a href="#contact-form" class="contact-hero-action contact-hero-action--primary">
                            <i class="fas fa-paper-plane" aria-hidden="true"></i>
                            {{ __('pages.contact.send_message') }}
                        </a>
                        <a href="tel:+989394765289" class="contact-hero-action contact-hero-action--secondary">
                            <i class="fas fa-phone-alt" aria-hidden="true"></i>
                            {{ __('pages.contact.direct_call') }}
                        </a>
                    </div>
                </div>
                <div class="contact-hero-info">
                    <div class="contact-info-card">
                        <span class="contact-info-card__icon bg-earth-green/10 text-earth-green text-xl">
                            <i class="fas fa-phone"></i>
                        </span>
                        <div class="contact-info-card__copy">
                            <p class="text-xs text-slate-500">{{ __('pages.contact.direct_call') }}</p>
                            <a href="tel:+989394765289" class="contact-info-card__value font-bold text-slate-700 hover:text-earth-green transition" dir="ltr">+98 9394765289</a>
                        </div>
                    </div>
                    <div class="contact-info-card">
                        <span class="contact-info-card__icon bg-ocean-blue/10 text-ocean-blue text-xl">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <div class="contact-info-card__copy">
                            <p class="text-xs text-slate-500">{{ __('pages.contact.email_support') }}</p>
                            <a href="mailto:contact@earthcoop.ir" class="contact-info-card__value font-bold text-slate-700 hover:text-earth-green transition">contact@earthcoop.ir</a>
                        </div>
                    </div>
                    <div class="contact-info-card">
                        <span class="contact-info-card__icon bg-digital-gold/10 text-digital-gold text-xl">
                            <i class="fas fa-map-marker-alt"></i>
                        </span>
                        <div class="contact-info-card__copy">
                            <p class="text-xs text-slate-500">{{ __('pages.contact.office') }}</p>
                            <p class="contact-info-card__value font-bold text-slate-700">{{ __('pages.contact.address_line') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Contact Form & Info --}}
        <section class="contact-main-grid fade-in-section">
            {{-- Form --}}
            <div id="contact-form" class="contact-section p-6 lg:p-8 space-y-6">
                <div class="space-y-3">
                    <p class="text-sm font-semibold uppercase tracking-[0.25em] text-earth-green">{{ __('pages.contact.quick_send_label') }}</p>
                    <h2 class="text-2xl font-extrabold text-gentle-black font-vazirmatn">{{ __('pages.contact.form_heading') }}</h2>
                    <p class="text-slate-600 leading-8">{{ __('pages.contact.form_subheading') }}</p>
                </div>

                @if(session('success'))
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        {{ session('success') }}
                    </div>
                @endif

                <form class="grid grid-cols-1 md:grid-cols-2 gap-5" action="{{ route('contact.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="_contact_started_at" value="{{ \Illuminate\Support\Facades\Crypt::encryptString((string) now()->timestamp) }}">
                    <div class="absolute -left-[10000px] top-auto h-px w-px overflow-hidden" aria-hidden="true">
                        <label for="company_website">Website</label>
                        <input type="text" id="company_website" name="company_website" tabindex="-1" autocomplete="off">
                    </div>
                    <div class="contact-form-group md:col-span-1">
                        <label for="name" class="block text-sm font-semibold text-slate-600 mb-2">{{ __('pages.contact.form.name_label') }}</label>
                        <input type="text" id="name" name="name" class="w-full border border-slate-200 rounded-xl px-4 py-3" placeholder="{{ __('pages.contact.form.name_placeholder') }}" autocomplete="name">
                    </div>
                    <div class="contact-form-group md:col-span-1">
                        <label for="email" class="block text-sm font-semibold text-slate-600 mb-2">{{ __('pages.contact.form.email_label') }}</label>
                        <input type="email" id="email" name="email" class="w-full border border-slate-200 rounded-xl px-4 py-3" placeholder="{{ __('pages.contact.form.email_placeholder') }}" autocomplete="email">
                    </div>
                    <div class="contact-form-group md:col-span-1">
                        <label for="subject" class="block text-sm font-semibold text-slate-600 mb-2">{{ __('pages.contact.form.subject_label') }}</label>
                        <input type="text" id="subject" name="subject" class="w-full border border-slate-200 rounded-xl px-4 py-3" placeholder="{{ __('pages.contact.form.subject_placeholder') }}" required autocomplete="off">
                    </div>
                    <div class="contact-form-group md:col-span-1">
                        <label for="phone" class="block text-sm font-semibold text-slate-600 mb-2">{{ __('pages.contact.form.phone_label') }}</label>
                        <input type="text" id="phone" name="phone" class="w-full border border-slate-200 rounded-xl px-4 py-3" placeholder="{{ __('pages.contact.form.phone_placeholder') }}" autocomplete="tel">
                    </div>
                    <div class="contact-form-group md:col-span-2">
                        <label for="message" class="block text-sm font-semibold text-slate-600 mb-2">{{ __('pages.contact.form.message_label') }}</label>
                        <textarea id="message" name="message" rows="5" class="w-full border border-slate-200 rounded-xl px-4 py-3 resize-none" placeholder="{{ __('pages.contact.form.message_placeholder') }}" required>{{ old('message') }}</textarea>
                    </div>
                    <div class="contact-form-submit md:col-span-2">
                        <button type="submit" class="px-8 py-3 rounded-full bg-earth-green text-white font-semibold transition hover:bg-dark-green">
                            {{ __('pages.contact.form.submit') }}
                        </button>
                    </div>
                </form>
            </div>

            {{-- Sidebar Info --}}
            <div class="contact-side-stack">
                <div class="contact-section p-6 space-y-4">
                    <h3 class="text-xl font-extrabold text-gentle-black font-vazirmatn">{{ __('pages.contact.channels_title') }}</h3>
                    <ul class="space-y-3 text-slate-600">
                        <li class="flex items-start gap-3">
                            <span class="w-10 h-10 rounded-full bg-earth-green/10 text-earth-green flex items-center justify-center text-lg">
                                <i class="fas fa-phone-alt"></i>
                            </span>
                            <div>
                                <p class="font-semibold">{{ __('pages.contact.direct_call') }}</p>
                                <a href="tel:+989394765289" class="text-sm text-slate-700 hover:text-earth-green transition" dir="ltr">+98 9394765289</a>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="w-10 h-10 rounded-full bg-ocean-blue/10 text-ocean-blue flex items-center justify-center text-lg">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <div>
                                <p class="font-semibold">{{ __('pages.contact.email_support') }}</p>
                                <a href="mailto:contact@earthcoop.ir" class="text-sm text-slate-700 hover:text-earth-green transition">contact@earthcoop.ir</a>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="contact-section p-6 space-y-4">
                    <h3 class="text-xl font-extrabold text-gentle-black font-vazirmatn">{{ __('pages.contact.hours_title') }}</h3>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                        <p class="font-semibold text-slate-800">{{ __('pages.contact.hours_weekdays') }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ __('pages.contact.hours_time') }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                        <p class="font-semibold text-slate-800">{{ __('pages.contact.online_support_title') }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ __('pages.contact.online_support_message') }}</p>
                    </div>
                </div>

                <div class="contact-section p-6 space-y-4">
                    <h3 class="text-xl font-extrabold text-gentle-black font-vazirmatn">{{ __('pages.contact.tracking_title') }}</h3>
                    <p class="text-sm leading-8 text-slate-600">{{ __('pages.contact.tracking_message') }}</p>
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white/70 p-4 text-sm text-slate-600">
                        <p class="font-semibold text-gentle-black">{{ __('pages.contact.features.collaboration.title') }}</p>
                        <p class="mt-2">{{ __('pages.contact.features.collaboration.desc') }}</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- Features Section --}}
        <section class="contact-feature-grid fade-in-section">
            <div class="contact-section p-6 space-y-3">
                <div class="w-12 h-12 rounded-2xl bg-earth-green/10 text-earth-green flex items-center justify-center text-xl">
                    <i class="fas fa-hands-helping"></i>
                </div>
                <h3 class="text-lg font-extrabold text-gentle-black font-vazirmatn">{{ __('pages.contact.features.quick.title') }}</h3>
                <p class="text-sm leading-8 text-slate-600">{{ __('pages.contact.features.quick.desc') }}</p>
            </div>
            <div class="contact-section p-6 space-y-3">
                <div class="w-12 h-12 rounded-2xl bg-ocean-blue/10 text-ocean-blue flex items-center justify-center text-xl">
                    <i class="fas fa-handshake"></i>
                </div>
                <h3 class="text-lg font-extrabold text-gentle-black font-vazirmatn">{{ __('pages.contact.features.collaboration.title') }}</h3>
                <p class="text-sm leading-8 text-slate-600">{{ __('pages.contact.features.collaboration.desc') }}</p>
            </div>
            <div class="contact-section p-6 space-y-3">
                <div class="w-12 h-12 rounded-2xl bg-digital-gold/10 text-digital-gold flex items-center justify-center text-xl">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <h3 class="text-lg font-extrabold text-gentle-black font-vazirmatn">{{ __('pages.contact.features.advice.title') }}</h3>
                <p class="text-sm leading-8 text-slate-600">{{ __('pages.contact.features.advice.desc') }}</p>
            </div>
        </section>

        {{-- Content Section --}}
        <section class="contact-section p-8 fade-in-section">
            <div class="prose prose-lg max-w-none text-right font-vazirmatn" style="direction: rtl; color: var(--color-gentle-black);">
                {!! $page->translated_content !!}
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const sections = document.querySelectorAll('.fade-in-section');
        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });

        sections.forEach(section => observer.observe(section));
    });
</script>
@endpush