@php
$renderContent = static function (string $input): string {
    // Legacy CKEditor content may contain HTML; normalize its structural
    // line breaks then strip all tags before rendering as safe Markdown.
    $plain = preg_replace('/<(?:br)\b[^>]*\/?\s*>/iu', "\n", $input);
    $plain = preg_replace('/<\/(?:p|div|li|h[1-6]|ul|ol)>/iu', "\n\n", $plain);
    $plain = html_entity_decode(strip_tags($plain), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return \Illuminate\Support\Str::markdown($plain, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
};
$renderNode = function (array $node) use (&$renderNode, $renderContent): string {
    $content = '<details class="ec-legal-clause"><summary class="ec-legal-clause-heading">' . e($node['title'] ?? '') . '</summary>';
    $content .= '<div class="ec-legal-clause-body prose max-w-none">' . $renderContent((string) ($node['content'] ?? '')) . '</div>';
    foreach ($node['children'] ?? [] as $child) {
        $content .= $renderNode($child);
    }
    return $content . '</details>';
};
@endphp
<div class="ec-legal-document" dir="rtl">
{!! $renderNode($snapshot['root']) !!}
</div>
