@extends('layouts.unified')

@section('title', $page->translated_title)
<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
@section('content')
<div class="container mx-auto px-4 md:px-6 py-8 md:py-12">
    <div class="max-w-4xl mx-auto">
        <!-- Page Header -->
        <header class="mb-8 text-center">
            <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold text-gentle-black mb-4" style="color: var(--color-gentle-black);">
                {{ $page->translated_title }}
            </h1>
            @if($page->meta_description)
                <p class="text-gray-600 text-lg md:text-xl max-w-2xl mx-auto">
                    {{ $page->translated_meta_description ?? $page->meta_description }}
                </p>
            @endif
                </header>

        <!-- Page Content -->
        <article class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200" style="background-color: var(--color-pure-white);">
            <div class="p-6 md:p-8 lg:p-12">
                <div class="prose prose-lg max-w-none page-content" style="font-size: 1.125rem; line-height: 1.8; color: var(--color-gentle-black);">
                    {!! $page->translated_content !!}
                </div>
            </div>
            </article>

        <!-- Back Button -->
        <div class="mt-8 text-center">
            <a href="{{ route('home') }}" 
               class="inline-flex items-center gap-2 bg-earth-green text-pure-white px-6 py-3 rounded-full shadow-md hover:bg-dark-green transition duration-300 font-medium transform hover:scale-105">
                <i class="fas fa-arrow-right ml-2"></i>
                {{ __('navigation.footer_home') }}
            </a>
        </div>
    </div>
</div>

@push('styles')
<style>
    /* Page Content Styling */
    .page-content {
        text-align: justify;
    }

    .page-content h1,
    .page-content h2,
    .page-content h3,
    .page-content h4,
    .page-content h5,
    .page-content h6 {
        color: var(--color-gentle-black);
        font-weight: 700;
        margin-top: 2rem;
        margin-bottom: 1rem;
    }

    .page-content h1 {
        font-size: 2rem;
        border-bottom: 2px solid var(--color-earth-green);
        padding-bottom: 0.5rem;
    }

    .page-content h2 {
        font-size: 1.75rem;
        color: var(--color-earth-green);
    }

    .page-content h3 {
        font-size: 1.5rem;
        color: var(--color-ocean-blue);
    }

    .page-content p {
        margin-bottom: 1.5rem;
        line-height: 1.8;
    }

    .page-content ul,
    .page-content ol {
        margin-bottom: 1.5rem;
        padding-right: 2rem;
    }

    .page-content li {
        margin-bottom: 0.75rem;
    }

    .page-content a {
        color: var(--color-earth-green);
        text-decoration: underline;
        transition: color 0.3s;
    }

    .page-content a:hover {
        color: var(--color-dark-green);
    }

    .page-content img {
        max-width: 100%;
        height: auto;
        border-radius: 0.5rem;
        margin: 1.5rem 0;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .page-content blockquote {
        border-right: 4px solid var(--color-earth-green);
        padding-right: 1.5rem;
        margin: 1.5rem 0;
        font-style: italic;
        color: var(--color-gentle-black);
        background-color: var(--color-light-gray);
        padding: 1rem 1.5rem;
        border-radius: 0.5rem;
    }

    .page-content code {
        background-color: var(--color-light-gray);
        padding: 0.2rem 0.5rem;
        border-radius: 0.25rem;
        font-family: 'Courier New', monospace;
        font-size: 0.9em;
    }

    .page-content pre {
        background-color: var(--color-light-gray);
        padding: 1rem;
        border-radius: 0.5rem;
        overflow-x: auto;
        margin: 1.5rem 0;
    }

    .page-content table {
        width: 100%;
        border-collapse: collapse;
        margin: 1.5rem 0;
    }

    .page-content table th,
    .page-content table td {
        border: 1px solid #ddd;
        padding: 0.75rem;
        text-align: right;
    }

    .page-content table th {
        background-color: var(--color-earth-green);
        color: white;
        font-weight: 600;
    }

    /* Dark Mode Support */
    body.dark-mode .page-content {
        color: #e0e0e0;
    }

    body.dark-mode .page-content h1,
    body.dark-mode .page-content h2,
    body.dark-mode .page-content h3,
    body.dark-mode .page-content h4,
    body.dark-mode .page-content h5,
    body.dark-mode .page-content h6 {
        color: #e0e0e0;
    }

    body.dark-mode .page-content blockquote {
        background-color: #3a3a3a;
        border-color: var(--color-earth-green);
    }

    body.dark-mode .page-content code,
    body.dark-mode .page-content pre {
        background-color: #3a3a3a;
    }

    body.dark-mode .page-content table th {
        background-color: var(--color-earth-green);
    }

    body.dark-mode .page-content table td {
        border-color: #555;
    }

    /* RTL Support */
    [dir="rtl"] .page-content ul,
    [dir="rtl"] .page-content ol {
        padding-right: 2rem;
        padding-left: 0;
    }

    [dir="ltr"] .page-content ul,
    [dir="ltr"] .page-content ol {
        padding-left: 2rem;
        padding-right: 0;
    }

    [dir="rtl"] .page-content blockquote {
        border-right: 4px solid var(--color-earth-green);
        border-left: none;
        padding-right: 1.5rem;
        padding-left: 0;
    }

    [dir="ltr"] .page-content blockquote {
        border-left: 4px solid var(--color-earth-green);
        border-right: none;
        padding-left: 1.5rem;
        padding-right: 0;
    }
</style>
@endpush
@endsection

