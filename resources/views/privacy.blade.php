@extends('layouts.unified')

@section('title', __('privacy.meta_title'))
@section('meta_description', __('privacy.meta_description'))

@section('content')
@php
    $privacy = __('privacy');
    $locale = app()->getLocale();
    $direction = $locale === 'en' ? 'ltr' : 'rtl';
@endphp
<div dir="{{ $direction }}" class="container mx-auto max-w-4xl px-4 py-8 sm:py-12">
    <article class="overflow-hidden rounded-3xl border border-emerald-100 bg-white shadow-sm">
        <header class="bg-gradient-to-r from-emerald-950 to-emerald-800 px-6 py-10 text-white sm:px-10">
            <p class="mb-3 text-sm font-semibold text-emerald-200">{{ $privacy['eyebrow'] }}</p>
            <h1 class="text-3xl font-black leading-tight sm:text-4xl">{{ $privacy['title'] }}</h1>
            <p class="mt-4 max-w-2xl leading-8 text-emerald-50">{{ $privacy['intro'] }}</p>
            <p class="mt-4 text-sm text-emerald-200">{{ $privacy['last_reviewed'] }}</p>
        </header>

        <div class="space-y-10 px-6 py-8 text-start leading-8 text-slate-700 sm:px-10 sm:py-10">
            @foreach ($privacy['sections'] as $section)
                <section @if (!empty($section['highlight'])) id="delete-data" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5" @endif>
                    <h2 class="mb-3 text-2xl font-bold text-slate-950">{{ $section['title'] }}</h2>
                    @if (!empty($section['body']))
                        <p>
                            @if (str_contains($section['body'], 'contact@earthcoop.ir'))
                                {!! str_replace('contact@earthcoop.ir', '<a class="font-semibold text-emerald-700 underline" href="mailto:contact@earthcoop.ir">contact@earthcoop.ir</a>', e($section['body'])) !!}
                            @else
                                {{ $section['body'] }}
                            @endif
                            @if (($section['link'] ?? null) === 'terms')
                                <a class="font-semibold text-emerald-700 underline" href="{{ route('terms') }}">{{ $privacy['terms_label'] }}</a>
                            @endif
                        </p>
                    @endif
                    @if (!empty($section['bullets']))
                        <ul class="list-inside list-disc space-y-2">
                            @foreach ($section['bullets'] as $bullet)
                                <li>{{ $bullet }}</li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        </div>
    </article>
</div>
@endsection
