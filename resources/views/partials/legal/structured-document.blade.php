{{-- Long legal texts are split at second-level Markdown headings into accessible, independent clauses. --}}
@php
    $source = file_get_contents(resource_path($sourcePath));
    $parts = preg_split('/(?=^##\\s+)/mu', $source, -1, PREG_SPLIT_NO_EMPTY);
    $intro = array_shift($parts);
    $renderMarkdown = static fn (string $value) => IlluminateSupportStr::markdown($value, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
@endphp
<div class="ec-legal-document" dir="rtl">
    <div class="ec-legal-document-intro prose max-w-none">{!! $renderMarkdown($intro) !!}</div>
    <div class="ec-legal-clauses">
        @foreach($parts as $index => $part)
            @php
                $lines = explode("\n", $part, 2);
                $heading = trim(preg_replace('/^##\\s+/u', '', $lines[0]));
                $body = $lines[1] ?? '';
            @endphp
            <details class="ec-legal-clause" @if($index === 0) open @endif>
                <summary class="ec-legal-clause-heading">
                    <span class="ec-legal-clause-number">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                    <span class="ec-legal-clause-name">{{ $heading }}</span>
                    <i class="fas fa-chevron-down ec-legal-chevron" aria-hidden="true"></i>
                </summary>
                <div class="ec-legal-clause-body prose max-w-none">{!! $renderMarkdown($body) !!}</div>
            </details>
        @endforeach
    </div>
</div>
