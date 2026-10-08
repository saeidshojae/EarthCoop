@php
$renderNode = function (array $node) use (&$renderNode) {
    $content = '<details class="ec-legal-clause"><summary class="ec-legal-clause-heading">' . e($node['title'] ?? '') . '</summary>';
    $content .= '<div class="ec-legal-clause-body">' . nl2br(e($node['content'] ?? '')) . '</div>';
    foreach ($node['children'] ?? [] as $child) {
        $content .= $renderNode($child);
    }
    return $content . '</details>';
};
@endphp
<div class="ec-legal-document" dir="rtl">
{!! $renderNode($snapshot['root']) !!}
</div>
