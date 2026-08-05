@php
    $docsLinks = config('docs-links');
    $foundationalIndex = $docsLinks['foundational_index'];
    $foundationalDocuments = $docsLinks['foundational'];
@endphp

<section id="documents" class="py-16 md:py-24 bg-pure-white fade-in-section">
    <div class="container mx-auto px-6">
        <div class="docs-section-shell">
            <div class="docs-section-copy">
                <span class="docs-eyebrow">
                    <i class="fas fa-balance-scale" aria-hidden="true"></i>
                    {{ __('langWelcome.docs_section_eyebrow') }}
                </span>

                <h2 class="text-3xl md:text-5xl font-extrabold font-vazirmatn text-gentle-black leading-tight">
                    {{ __('langWelcome.docs_section_title') }}
                </h2>

                <p class="text-lg md:text-xl text-gray-700 font-vazirmatn leading-relaxed">
                    {{ __('langWelcome.docs_section_text') }}
                </p>

                <div class="docs-actions">
                    <a href="{{ $foundationalIndex['href'] }}" class="docs-primary-action">
                        {{ __('langWelcome.docs_section_primary') }}
                        <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    </a>
                    <a href="{{ $docsLinks['base_url'] }}/fa/introduction" class="docs-secondary-action">
                        {{ __('langWelcome.docs_section_secondary') }}
                        <i class="fas fa-book-open" aria-hidden="true"></i>
                    </a>
                </div>
            </div>

            <div class="docs-card-grid" aria-label="{{ __('langWelcome.docs_section_grid_label') }}">
                @foreach($foundationalDocuments as $document)
                    <a href="{{ $document['href'] }}" class="docs-card">
                        <span class="docs-card-icon">
                            <i class="fas {{ $document['icon'] }}" aria-hidden="true"></i>
                        </span>
                        <span class="docs-card-text">
                            <span class="docs-card-title">{{ __($document['title_key']) }}</span>
                            <span class="docs-card-code">{{ $document['code'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</section>
