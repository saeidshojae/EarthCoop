@extends('layouts.unified')

@section('title', ($page->translated_meta_title ?? $page->translated_title) . ' - ' . config('app.name', 'EarthCoop'))

@push('styles')
<style>
    .faq-hero {
        position: relative;
        overflow: hidden;
        background: linear-gradient(135deg, rgba(15, 23, 42, 0.04) 0%, rgba(16, 185, 129, 0.08) 100%);
        border-radius: 2rem;
        padding: 3rem 2.5rem;
        box-shadow: 0 35px 90px rgba(15, 23, 42, 0.08);
    }

    .faq-hero::before,
    .faq-hero::after {
        content: '';
        position: absolute;
        width: 240px;
        height: 240px;
        border-radius: 50%;
        filter: blur(90px);
        opacity: 0.35;
    }

    .faq-hero::before {
        top: -90px;
        right: -80px;
        background: rgba(59, 130, 246, 0.45);
    }

    .faq-hero::after {
        bottom: -110px;
        left: -80px;
        background: rgba(16, 185, 129, 0.45);
    }

    .faq-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.8rem 1rem;
        border-radius: 999px;
        border: 1px solid rgba(148, 163, 184, 0.22);
        background: rgba(255, 255, 255, 0.92);
        color: var(--color-slate-700);
        font-size: 0.9rem;
        font-weight: 700;
    }

    .faq-pill i {
        font-size: 1rem;
        color: var(--color-earth-green);
    }

    .faq-search {
        border-radius: 999px;
        border: 1px solid rgba(148, 163, 184, 0.35);
        background: rgba(255, 255, 255, 0.98);
        box-shadow: 0 24px 55px rgba(15, 23, 42, 0.08);
    }

    .faq-search input {
        width: 100%;
        border: none;
        background: transparent;
        padding: 0.95rem 1rem;
        color: var(--color-gentle-black);
    }

    .faq-search input:focus {
        outline: none;
    }

    .faq-metric-card {
        border-radius: 1.75rem;
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: rgba(255, 255, 255, 0.95);
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
        padding: 1.5rem;
        text-align: right;
    }

    .faq-metric-card h3 {
        margin-top: 0.5rem;
        font-size: 1.9rem;
        letter-spacing: -0.02em;
    }

    .faq-metric-card p {
        margin: 0;
        color: var(--color-slate-500);
        font-size: 0.95rem;
    }

    .faq-accordion-item {
        border-radius: 1.5rem;
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: var(--color-pure-white);
        overflow: hidden;
        transition: border-color 0.3s ease, transform 0.3s ease;
    }

    .faq-accordion-item.active {
        border-color: rgba(16, 185, 129, 0.45);
        transform: translateY(-2px);
    }

    .faq-accordion-header {
        width: 100%;
        padding: 1.4rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        background: rgba(248, 250, 252, 0.95);
        cursor: pointer;
    }

    .faq-accordion-header:hover {
        background: rgba(236, 253, 245, 0.95);
    }

    .faq-accordion-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.45s ease;
        background: rgba(255, 255, 255, 0.98);
    }

    .faq-accordion-content-inner {
        padding: 1.75rem;
        color: var(--color-gentle-black);
        line-height: 1.9;
    }

    .faq-form-card {
        border-radius: 2rem;
        border: 1px solid rgba(226, 232, 240, 0.85);
        background: var(--color-pure-white);
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
        padding: 1.75rem;
    }

    .faq-form-card input,
    .faq-form-card textarea,
    .faq-form-card select {
        width: 100%;
        border-radius: 1rem;
        border: 1px solid rgba(148, 163, 184, 0.35);
        padding: 0.95rem 1rem;
        background: rgba(248, 250, 252, 0.95);
        color: var(--color-gentle-black);
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    .faq-form-card input:focus,
    .faq-form-card textarea:focus,
    .faq-form-card select:focus {
        border-color: var(--color-ocean-blue);
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
        outline: none;
    }

    .faq-tabs button {
        transition: background-color 0.3s ease, color 0.3s ease, transform 0.2s ease;
        background: rgba(248, 250, 252, 0.96);
        color: var(--color-slate-600);
        padding: 0.85rem 1rem;
        border-radius: 999px;
        border: 1px solid transparent;
        font-weight: 700;
    }

    .faq-tabs button:hover {
        transform: translateY(-1px);
    }

    .faq-tabs button.active {
        background: linear-gradient(135deg, var(--color-earth-green), #059669);
        color: var(--color-pure-white);
        box-shadow: 0 18px 30px rgba(5, 150, 105, 0.22);
    }

    .faq-no-results {
        display: none;
        padding: 1.8rem 1.5rem;
        border-radius: 1.5rem;
        border: 1px solid rgba(229, 231, 235, 0.9);
        background: rgba(248, 250, 252, 0.95);
        text-align: center;
        color: var(--color-slate-600);
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

    @media (max-width: 1024px) {
        .faq-hero {
            padding: 2.5rem 1.5rem;
        }
    }

    @media (max-width: 768px) {
        .faq-hero {
            padding: 2rem 1.25rem;
        }

        .faq-metric-card {
            text-align: center;
        }

        .faq-search {
            box-shadow: none;
        }
    }
</style>
@endpush

@section('content')

@php
    $locale = app()->getLocale();
    $faqRaw = $page->content_translations[$locale] ?? $page->content_translations['fa'] ?? '[]';
    $faqSections = json_decode($faqRaw, true);
    $faqSections = is_array($faqSections) ? $faqSections : [];
    $extraContent = $page->translated_content;
    $extraContentDecoded = json_decode($extraContent, true);
    $jsonError = json_last_error();
    $isJsonStructure = $jsonError === JSON_ERROR_NONE && (is_array($extraContentDecoded) || is_object($extraContentDecoded));
    $shouldShowExtraContent = is_string($extraContent) && !$isJsonStructure && trim(strip_tags($extraContent)) !== '';
    $publishedFaqs = $faqQuestions ?? collect();
    $categoryList = collect($publishedFaqs)->pluck('category')->merge(collect($faqSections)->pluck('category'))
        ->filter()->unique()->values();

    // Dynamic counts for metric cards
    $totalQuestions = collect($publishedFaqs)->count() + count($faqSections);
    $totalCategories = $categoryList->count();
@endphp

<div class="bg-light-gray/70 py-12 md:py-16" style="background-color: var(--color-light-gray);">
    <div class="container mx-auto px-5 md:px-10 max-w-6xl space-y-10">
        <section class="faq-hero fade-in-section">
            <div class="grid gap-10 lg:grid-cols-[1.3fr_0.7fr] items-center">
                <div class="space-y-6 text-center md:text-right">
                    <div class="inline-flex items-center gap-3 bg-white/90 rounded-full px-5 py-2 border border-slate-200 shadow-sm">
                        <span class="text-earth-green text-lg"><i class="fas fa-circle-question"></i></span>
                        <span class="text-sm font-semibold text-slate-700">{{ __('pages.faq.hero_badge') }}</span>
                    </div>
                    <h1 class="text-3xl md:text-5xl font-extrabold text-gentle-black font-vazirmatn leading-tight">
                        {{ $page->translated_title ?? 'سوالات متداول' }}
                    </h1>
                    <p class="text-lg md:text-xl text-slate-600 max-w-3xl mx-auto md:mx-0 leading-relaxed">
                        {{ $page->translated_meta_description ?? __('pages.faq.subtitle') }}
                    </p>
                    <div class="flex flex-wrap justify-center md:justify-start gap-3">
                        <span class="faq-pill">
                            <i class="fas fa-check-circle"></i>
                            {{ __('pages.faq.pill_ready') }}
                        </span>
                        <span class="faq-pill">
                            <i class="fas fa-headset"></i>
                            {{ __('pages.faq.pill_support') }}
                        </span>
                        <span class="faq-pill">
                            <i class="fas fa-rocket"></i>
                            {{ __('pages.faq.pill_quick') }}
                        </span>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row gap-4 items-center sm:justify-end">
                    <div class="faq-metric-card">
                        <p class="text-xs uppercase tracking-[0.2em] mb-3">{{ __('pages.faq.metrics.questions') }}</p>
                        <h3 class="text-3xl font-bold text-gentle-black">{{ $totalQuestions }}</h3>
                        <p>{{ __('pages.faq.metrics.questions_label') }}</p>
                    </div>
                    <div class="faq-metric-card">
                        <p class="text-xs uppercase tracking-[0.2em] mb-3">{{ __('pages.faq.metrics.categories') }}</p>
                        <h3 class="text-3xl font-bold text-gentle-black">{{ $totalCategories }}</h3>
                        <p>{{ __('pages.faq.metrics.categories_label') }}</p>
                    </div>
                </div>
            </div>
            <div class="mt-10 faq-search max-w-3xl mx-auto md:mx-0 rounded-full border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-3 px-4 py-3">
                    <i class="fas fa-search text-slate-400 text-lg"></i>
                    <input type="text" id="faq-search-input" placeholder="{{ __('pages.faq.search_placeholder') }}" class="w-full bg-transparent text-slate-700 placeholder-slate-400 focus:outline-none">
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-3 gap-8 fade-in-section">
            <div class="lg:col-span-2 space-y-5" id="faq-accordion">
                @foreach($publishedFaqs as $question)
                    <article class="faq-accordion-item" data-category="{{ $question->category ?? 'عمومی' }}">
                        <button type="button" class="faq-accordion-header">
                            <div class="flex items-center gap-3 text-right">
                                <span class="w-11 h-11 rounded-full bg-ocean-blue/10 text-ocean-blue flex items-center justify-center text-lg">
                                    <i class="fas fa-question"></i>
                                </span>
                                <div class="text-right">
                                    <h3 class="text-lg font-extrabold text-gentle-black">{{ $question->title }}</h3>
                                    <span class="text-xs text-slate-500">{{ $question->category ?? 'عمومی' }}</span>
                                </div>
                            </div>
                            <i class="fas fa-chevron-down text-slate-400 transition-transform duration-300"></i>
                        </button>
                        <div class="faq-accordion-content">
                            <div class="faq-accordion-content-inner prose max-w-none">
                                {!! nl2br(e($question->answer)) !!}
                            </div>
                        </div>
                    </article>
                @endforeach

                @forelse($faqSections as $section)
                    <article class="faq-accordion-item" data-category="{{ $section['category'] ?? 'عمومی' }}">
                        <button type="button" class="faq-accordion-header">
                            <div class="flex items-center gap-3 text-right">
                                <span class="w-11 h-11 rounded-full bg-earth-green/10 text-earth-green flex items-center justify-center text-lg">
                                    <i class="fas {{ $section['icon'] ?? 'fa-circle-question' }}"></i>
                                </span>
                                <div class="text-right">
                                    <h3 class="text-lg font-extrabold text-gentle-black">{{ $section['question'] ?? '' }}</h3>
                                    @if(!empty($section['category_label']))
                                        <span class="text-xs text-slate-500">{{ $section['category_label'] }}</span>
                                    @endif
                                </div>
                            </div>
                            <i class="fas fa-chevron-down text-slate-400 transition-transform duration-300"></i>
                        </button>
                        <div class="faq-accordion-content">
                            <div class="faq-accordion-content-inner prose max-w-none">
                                {!! $section['answer'] ?? '' !!}
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="faq-accordion-item">
                        <div class="faq-accordion-content-inner">
                            <p class="text-slate-600 text-center">{{ __('pages.faq.no_questions') }}</p>
                        </div>
                    </div>
                @endforelse
                <div class="faq-no-results" id="faq-no-results">
                    <p class="text-lg font-semibold text-slate-700">{{ __('pages.faq.no_results_title') }}</p>
                    <p class="mt-2 text-slate-500">{{ __('pages.faq.no_results_message') }}</p>
                </div>
            </div>

            <aside class="space-y-6">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-md p-6">
                    <h2 class="text-xl font-extrabold text-gentle-black mb-4">{{ __('pages.faq.categories_title') }}</h2>
                    <div class="faq-tabs flex flex-wrap gap-3" id="faq-categories">
                        <button type="button" class="active px-4 py-2 rounded-full bg-earth-green/10 text-earth-green font-semibold" data-category="all">{{ __('pages.faq.category_all') }}</button>
                        @foreach($categoryList as $category)
                            <button type="button" class="px-4 py-2 rounded-full bg-slate-100 text-slate-600 font-semibold" data-category="{{ $category }}">
                                {{ $category }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="faq-form-card">
                    <div class="space-y-4">
                        <div>
                            <h2 class="text-xl font-extrabold text-gentle-black">{{ __('pages.faq.ask_new_title') }}</h2>
                            <p class="text-sm text-slate-600">{{ __('pages.faq.ask_new_subtitle') }}</p>
                        </div>
                        @if(session('success'))
                            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm">
                                {{ session('success') }}
                            </div>
                        @endif
                        <form method="POST" action="{{ route('questions.store') }}" class="space-y-4">
                            @csrf
                            <div>
                                <label class="block text-sm font-semibold text-slate-600 mb-2">{{ __('pages.faq.form.title_label') }}</label>
                                <input type="text" name="title" required class="w-full px-4 py-3" placeholder="{{ __('pages.faq.form.title_placeholder') }}" value="{{ old('title') }}">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-600 mb-2">{{ __('pages.faq.form.category_label') }}</label>
                                <select name="category" class="w-full px-4 py-3">
                                    <option value="general">{{ __('pages.faq.form.option_general') }}</option>
                                    <option value="membership">{{ __('pages.faq.form.option_membership') }}</option>
                                    <option value="finance">{{ __('pages.faq.form.option_finance') }}</option>
                                    <option value="projects">{{ __('pages.faq.form.option_projects') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-600 mb-2">{{ __('pages.faq.form.question_label') }}</label>
                                <textarea name="question" rows="4" required class="w-full px-4 py-3 resize-none" placeholder="{{ __('pages.faq.form.question_placeholder') }}">{{ old('question') }}</textarea>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-slate-600 mb-2">{{ __('pages.faq.form.name_label') }}</label>
                                    <input type="text" name="contact_name" class="w-full px-4 py-3" placeholder="{{ __('pages.faq.form.name_placeholder') }}" value="{{ old('contact_name') }}">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-slate-600 mb-2">{{ __('pages.faq.form.email_label') }}</label>
                                    <input type="email" name="contact_email" class="w-full px-4 py-3" placeholder="{{ __('pages.faq.form.email_placeholder') }}" value="{{ old('contact_email') }}">
                                </div>
                            </div>
                            <button type="submit" class="w-full px-6 py-3 rounded-full bg-ocean-blue text-white font-semibold hover:bg-dark-blue transition">
                                {{ __('pages.faq.form.submit') }}
                            </button>
                        </form>
                    </div>
                </div>
            </aside>
        </section>

        @if($shouldShowExtraContent)
            <section class="faq-form-card p-8 fade-in-section">
                <div class="prose prose-lg max-w-none text-right font-vazirmatn" style="direction: rtl; color: var(--color-gentle-black);">
                    {!! $extraContent !!}
                </div>
            </section>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const accordionItems = document.querySelectorAll('.faq-accordion-item');
        const searchInput = document.getElementById('faq-search-input');
        const tabButtons = document.querySelectorAll('#faq-categories button');
        const noResults = document.getElementById('faq-no-results');
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

        const closeAll = () => {
            accordionItems.forEach(other => {
                other.classList.remove('active');
                const content = other.querySelector('.faq-accordion-content');
                content.style.maxHeight = null;
                const icon = other.querySelector('.fa-chevron-down');
                if (icon) icon.style.transform = 'rotate(0deg)';
            });
        };

        accordionItems.forEach(item => {
            const header = item.querySelector('.faq-accordion-header');
            const content = item.querySelector('.faq-accordion-content');
            const icon = header.querySelector('.fa-chevron-down');
            header.addEventListener('click', () => {
                const isActive = item.classList.contains('active');
                closeAll();
                if (!isActive) {
                    item.classList.add('active');
                    content.style.maxHeight = content.scrollHeight + 'px';
                    icon.style.transform = 'rotate(-180deg)';
                }
            });
        });

        if (accordionItems.length) {
            accordionItems[0].querySelector('.faq-accordion-header').click();
        }

        const updateNoResults = () => {
            const visible = Array.from(accordionItems).some(item => item.style.display !== 'none');
            noResults.style.display = visible ? 'none' : 'block';
        };

        const filterItems = () => {
            const term = searchInput.value.trim().toLowerCase();
            accordionItems.forEach(item => {
                const text = item.innerText.toLowerCase();
                item.style.display = text.includes(term) ? '' : 'none';
            });
            updateNoResults();
        };

        searchInput.addEventListener('input', filterItems);

        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                const category = button.dataset.category;
                tabButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                accordionItems.forEach(item => {
                    if (category === 'all' || item.dataset.category === category) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
                updateNoResults();
            });
        });
    });
</script>
@endpush
