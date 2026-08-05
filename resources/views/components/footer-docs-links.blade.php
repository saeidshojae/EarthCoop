@php
    $docsLinks = $docsLinks ?? config('docs-links');
    $foundationalIndex = $docsLinks['foundational_index'];
    $foundationalDocuments = $docsLinks['foundational'];
    $footerDocsId = $footerDocsId ?? ('footer-foundational-docs-' . uniqid());
    $headingClass = $headingClass ?? 'text-lg font-semibold mb-4 text-earth-green font-vazirmatn';
    $linkClass = $linkClass ?? 'footer-docs-link';
@endphp

<div>
    <h3 class="{{ $headingClass }}">{{ __('langWelcome.docs_footer_title') }}</h3>

    <div class="footer-docs-list font-vazirmatn">
        <a href="{{ $docsLinks['base_url'] }}/fa/introduction" class="{{ $linkClass }} footer-docs-link">{{ __('langWelcome.docs_footer_center') }}</a>

        <div class="footer-docs-foundational">
            <div class="footer-docs-row">
                <a href="{{ $foundationalIndex['href'] }}" class="{{ $linkClass }} footer-docs-link font-semibold">{{ __('langWelcome.docs_footer_foundational') }}</a>
                <button
                    type="button"
                    class="footer-docs-toggle"
                    aria-expanded="false"
                    aria-controls="{{ $footerDocsId }}"
                    data-footer-docs-toggle
                    title="{{ __('langWelcome.docs_footer_toggle') }}"
                >
                    <span class="sr-only">{{ __('langWelcome.docs_footer_toggle') }}</span>
                    <i class="fas fa-chevron-down" aria-hidden="true"></i>
                </button>
            </div>

            <div id="{{ $footerDocsId }}" class="footer-docs-panel" data-footer-docs-panel>
                <div class="footer-docs-sublist">
                    <a href="{{ $foundationalIndex['href'] }}">
                        <span>{{ __($foundationalIndex['title_key']) }}</span>
                    </a>
                    @foreach($foundationalDocuments as $document)
                        <a href="{{ $document['href'] }}">
                            <span>{{ __($document['title_key']) }}</span>
                            <span class="footer-docs-code">{{ $document['code'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        @foreach($docsLinks['main'] as $link)
            <a href="{{ $link['href'] }}" class="{{ $linkClass }} footer-docs-link">{{ __($link['label_key']) }}</a>
        @endforeach
    </div>
</div>

@once
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-footer-docs-toggle]').forEach(function (toggle) {
                const panel = document.getElementById(toggle.getAttribute('aria-controls'));

                if (!panel) return;

                toggle.addEventListener('click', function () {
                    const isOpen = toggle.getAttribute('aria-expanded') === 'true';
                    toggle.setAttribute('aria-expanded', String(!isOpen));
                    panel.classList.toggle('is-open', !isOpen);
                });
            });
        });
    </script>
@endonce