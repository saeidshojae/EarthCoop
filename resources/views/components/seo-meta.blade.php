@props([
    'title' => null,
    'description' => null,
    'canonical' => null,
    'robots' => 'index,follow',
    'type' => 'website',
    'image' => null,
    'jsonLd' => [],
])
@php
    $canonicalUrl = $canonical ?: app(\App\Support\Seo\CanonicalUrl::class)->forRequest(request());
    $resolvedTitle = trim((string) ($title ?: config('seo.default_title')));
    $resolvedDescription = trim((string) ($description ?: config('seo.default_description')));
    $resolvedImage = $image ?: app(\App\Support\Seo\CanonicalUrl::class)->to(config('seo.default_image', '/icons/icon.svg'));
    $jsonLdItems = isset($jsonLd['@context']) ? [$jsonLd] : $jsonLd;
@endphp
<title>{{ $resolvedTitle }}</title>
<meta name="description" content="{{ $resolvedDescription }}">
<meta name="robots" content="{{ $robots }}">
<link rel="canonical" href="{{ $canonicalUrl }}">
<meta property="og:locale" content="fa_IR">
<meta property="og:type" content="{{ $type }}">
<meta property="og:title" content="{{ $resolvedTitle }}">
<meta property="og:description" content="{{ $resolvedDescription }}">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:image" content="{{ $resolvedImage }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $resolvedTitle }}">
<meta name="twitter:description" content="{{ $resolvedDescription }}">
<meta name="twitter:image" content="{{ $resolvedImage }}">
@foreach($jsonLdItems as $payload)
<script type="application/ld+json">@json($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>
@endforeach
