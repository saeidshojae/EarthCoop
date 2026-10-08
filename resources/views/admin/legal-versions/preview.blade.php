@extends('layouts.admin')
@section('title', 'پیش‌نمایش نسخه')
@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
<a href="{{ route('admin.legal-versions.index') }}">بازگشت به نسخه‌ها</a>
<h1 class="text-2xl font-bold my-3">پیش‌نمایش {{ $version->version_label }} — {{ $version->status }}</h1>
@php
$renderNode = function ($node) use (&$renderNode) {
    $html = '<details open class="border rounded mb-3 p-3"><summary class="font-bold">' . e($node['title']) . '</summary>';
    $html .= '<div class="mt-3 leading-loose">' . nl2br(e($node['content'])) . '</div>';
    foreach ($node['children'] ?? [] as $child) $html .= $renderNode($child);
    return $html . '</details>';
};
@endphp
{!! $renderNode($snapshot['root']) !!}
</div>
@endsection
