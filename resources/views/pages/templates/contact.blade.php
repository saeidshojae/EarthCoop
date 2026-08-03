@extends('layouts.unified')

@section('title', ($page->translated_meta_title ?? $page->translated_title) . ' - ' . config('app.name', 'EarthCoop'))

@push('styles')
<style>
    .contact-hero {
        position: relative;
        overflow: hidden;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.12) 0%, rgba(59, 130, 246, 0.15) 100%);
        border-radius: 1.75rem;
        padding: 3rem 2rem;
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

    @media (max-width: 768px) {
        .contact-hero {
            padding: 2.5rem 1.5rem;
        }
    }
</style>
@endpush

@section('content')
@php
    $metaDescription = $page->translated_meta_description ?? __('navigation.footer_contact_description', []);
@endphp

<div class="bg-light-gray/70 py-12 md:py-16" style="background-color: var(--color-light-gray);">
    <div class="container mx-auto px-5 md:px-10 max-w-6xl space-y-10">
        {{-- Hero Section --}}
        <section class="contact-hero fade-in-section">
            <div class="relative z-10 grid gap-8 lg:grid-cols-[1.2fr_0.8fr] items-center">
                <div class="space-y-6 text-center md:text-right">
                    <div class="inline-flex items-center gap-2 rounded-full border border-earth-green/20 bg-white/80 px-4 py-2 text-sm font-semibold text-earth-green shadow-sm">
                        <i class="fas fa-comments"></i>
                        {{ __('pages.contact.hero_badge') }}
                    </div>
                    <h1 class="text-3xl md:text-5xl font-extrabold text-gentle-black font-vazirmatn leading-tight">
                        {{ $page->translated_title }}
                    </h1>
                    <p class="text-lg md:text-xl text-slate-600 max-w-3xl mx-auto md:mx-0 leading-8">
                        {{ $metaDescription ?? __('pages.contact.subtitle') }}
                    </p>
                    <div class="flex flex-wrap gap-3 justify-center md:justify-start">
                        <a href="#contact-form" class="inline-flex items-center gap-2 rounded-full bg-earth-green px-6 py-3 text-sm font-semibold text-white transition hover:bg-dark-green">
                            <i class="fas fa-paper-plane"></i>
                            {{ __('pages.contact.send_message') }}
                        </a>
                        <a href="tel:+982112345678" class="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:border-earth-green hover:text-earth-green">
                            <i class="fas fa-phone-alt"></i>
                            {{ __('pages.contact.direct_call') }}
                        </a>
                    </div>
                </div>
                <div class="grid gap-4">
                    <div class="contact-info-card bg-white/95 rounded-2xl border border-slate-200 px-5 py-4 flex items-center gap-4 shadow-sm">
                        <span class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-earth-green/10 text-earth-green text-xl">
                            <i class="fas fa-phone"></i>
                        </span>
                        <div class="text-right">
                            <p class="text-xs text-slate-500">{{ __('pages.contact.direct_call') }}</p>
                            <a href="tel:+982112345678" class="font-bold text-slate-700 hover:text-earth-green transition" dir="ltr">+98 21 1234 5678</a>
                        </div>
                    </div>
                    <div class="contact-info-card bg-white/95 rounded-2xl border border-slate-200 px-5 py-4 flex items-center gap-4 shadow-sm">
                        <span class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-ocean-blue/10 text-ocean-blue text-xl">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <div class="text-right">
                            <p class="text-xs text-slate-500">{{ __('pages.contact.email_support') }}</p>
                            <a href="mailto:contact@earthcoop.ir" class="font-bold text-slate-700 hover:text-earth-green transition">contact@earthcoop.ir</a>
                        </div>
                    </div>
                    <div class="contact-info-card bg-white/95 rounded-2xl border border-slate-200 px-5 py-4 flex items-center gap-4 shadow-sm">
                        <span class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-digital-gold/10 text-digital-gold text-xl">
                            <i class="fas fa-map-marker-alt"></i>
                        </span>
                        <div class="text-right">
                            <p class="text-xs text-slate-500">{{ __('pages.contact.office') }}</p>
                            <p class="font-bold text-slate-700">{{ __('pages.contact.address_line') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Contact Form & Info --}}
        <section class="grid grid-cols-1 lg:grid-cols-[1.15fr_0.85fr] gap-8 fade-in-section">
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
                        @if(session('ticket_tracking'))
                            <span class="font-semibold">{{ __('pages.contact.tracking_label') }}: {{ session('ticket_tracking') }}</span>
                        @endif
                    </div>
                @endif

                <form class="grid grid-cols-1 md:grid-cols-2 gap-5" action="{{ route('contact.store') }}" method="POST">
                    @csrf
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
                    <div class="md:col-span-2 flex justify-end">
                        <button type="submit" class="px-8 py-3 rounded-full bg-earth-green text-white font-semibold transition hover:bg-dark-green">
                            {{ __('pages.contact.form.submit') }}
                        </button>
                    </div>
                </form>
            </div>

            {{-- Sidebar Info --}}
            <div class="space-y-6">
                <div class="contact-section p-6 space-y-4">
                    <h3 class="text-xl font-extrabold text-gentle-black font-vazirmatn">{{ __('pages.contact.channels_title') }}</h3>
                    <ul class="space-y-3 text-slate-600">
                        <li class="flex items-start gap-3">
                            <span class="w-10 h-10 rounded-full bg-earth-green/10 text-earth-green flex items-center justify-center text-lg">
                                <i class="fas fa-phone-alt"></i>
                            </span>
                            <div>
                                <p class="font-semibold">{{ __('pages.contact.direct_call') }}</p>
                                <a href="tel:+982112345678" class="text-sm text-slate-700 hover:text-earth-green transition" dir="ltr">+98 21 1234 5678</a>
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
        <section class="grid grid-cols-1 md:grid-cols-3 gap-6 fade-in-section">
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