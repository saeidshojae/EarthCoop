@extends('layouts.unified')

@push('styles')
<style>
    .seo-pillar-shell { max-width: 1040px; margin: 0 auto; padding: 2rem 1rem 4rem; }
    .seo-pillar-hero, .seo-pillar-section, .seo-pillar-links { background: var(--card-bg, #fff); border: 1px solid rgba(148, 163, 184, .22); border-radius: 1.25rem; box-shadow: 0 14px 40px rgba(15, 23, 42, .06); }
    .seo-pillar-hero { padding: clamp(1.4rem, 4vw, 3rem); margin-bottom: 1.5rem; }
    .seo-pillar-eyebrow { font-size: .9rem; font-weight: 800; opacity: .72; margin-bottom: .7rem; }
    .seo-pillar-hero h1 { font-size: clamp(1.8rem, 5vw, 3rem); line-height: 1.45; font-weight: 900; margin: 0 0 1rem; }
    .seo-pillar-summary { font-size: 1.08rem; line-height: 2.05; margin: 0; opacity: .9; }
    .seo-pillar-breadcrumbs { display: flex; flex-wrap: wrap; gap: .45rem; margin-bottom: 1rem; font-size: .9rem; }
    .seo-pillar-breadcrumbs a { text-decoration: none; }
    .seo-pillar-section { padding: clamp(1.2rem, 3vw, 2rem); margin-bottom: 1rem; }
    .seo-pillar-section h2 { font-size: 1.4rem; font-weight: 850; margin: 0 0 .8rem; }
    .seo-pillar-section p, .seo-pillar-section li { line-height: 2; }
    .seo-pillar-section ul { padding-right: 1.3rem; margin-bottom: 0; }
    .seo-pillar-links { padding: 1.4rem; margin-top: 1.5rem; }
    .seo-pillar-link-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: .75rem; }
    .seo-pillar-link { display: block; padding: .9rem 1rem; border: 1px solid rgba(148, 163, 184, .28); border-radius: .85rem; text-decoration: none; font-weight: 750; }
    .seo-pillar-source-note { margin-top: 1rem; font-size: .9rem; opacity: .78; line-height: 1.8; }
    body.dark-mode .seo-pillar-hero,
    body.dark-mode .seo-pillar-section,
    body.dark-mode .seo-pillar-links { background: rgba(15, 23, 42, .82); }
</style>
@endpush

@section('content')
<div class="seo-pillar-shell">
    <nav class="seo-pillar-breadcrumbs" aria-label="مسیر صفحه">
        @foreach($pillar['breadcrumbs'] as $index => $crumb)
            @if($index > 0)<span aria-hidden="true">/</span>@endif
            @if(!$loop->last)
                <a href="{{ $crumb['path'] }}">{{ $crumb['name'] }}</a>
            @else
                <span aria-current="page">{{ $crumb['name'] }}</span>
            @endif
        @endforeach
    </nav>

    <article>
        <header class="seo-pillar-hero">
            <div class="seo-pillar-eyebrow">{{ $pillar['eyebrow'] }}</div>
            <h1>{{ $pillar['title'] }}</h1>
            <p class="seo-pillar-summary">{{ $pillar['summary'] }}</p>
        </header>

        @foreach($pillar['sections'] as $section)
            <section class="seo-pillar-section">
                <h2>{{ $section['heading'] }}</h2>
                @foreach($section['paragraphs'] ?? [] as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
                @if(!empty($section['bullets']))
                    <ul>
                        @foreach($section['bullets'] as $bullet)
                            <li>{{ $bullet }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    </article>

    @if(!empty($supportingArticles))
        <aside class="seo-pillar-links" aria-label="مقالات مرتبط">
            <h2 class="h5 fw-bold mb-3">مقالات مرتبط</h2>
            <div class="seo-pillar-link-grid">
                @foreach($supportingArticles as $article)
                    <a class="seo-pillar-link" href="{{ $article['path'] }}">{{ $article['label'] }}</a>
                @endforeach
            </div>
        </aside>
    @endif

    <aside class="seo-pillar-links" aria-label="مطالعه بیشتر">
        <h2 class="h5 fw-bold mb-3">مطالعه مرتبط</h2>
        <div class="seo-pillar-link-grid">
            @foreach($pillar['related'] as $link)
                <a class="seo-pillar-link" href="{{ $link['path'] }}">{{ $link['label'] }}</a>
            @endforeach
            @foreach($pillar['docs'] as $document)
                <a class="seo-pillar-link" href="{{ $document['url'] }}">{{ $document['label'] }} <span aria-hidden="true">↗</span></a>
            @endforeach
        </div>
        <p class="seo-pillar-source-note">برای قواعد رسمی و نسخه‌های مرجع EarthCoop، متن اسناد بنیادین در مرکز اسناد ملاک است. این صفحه برای توضیح عمومی و آموزشی مفاهیم نوشته شده است.</p>
    </aside>
</div>
@endsection
