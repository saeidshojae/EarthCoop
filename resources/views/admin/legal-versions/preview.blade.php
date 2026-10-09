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
@if($version->status === 'draft')
<form method="POST" action="{{ route('admin.legal-versions.publish', $version) }}" onsubmit="return confirm('این دقیقاً همان متنی است که بررسی کردید و پس از انتشار ثابت می‌ماند. تأیید می‌کنید؟')" class="border rounded p-4 mt-4">
@csrf
<input type="hidden" name="preview_sha256" value="{{ $previewSha256 }}">
<label><input type="checkbox" name="confirm_publish" value="1" required> این نسخه را کامل مطالعه کرده‌ام و انتشار آن را تأیید می‌کنم.</label>
<button type="submit" class="btn btn-success mt-3">انتشار همین نسخه بررسی‌شده</button>
<p class="text-sm mt-2">اگر متن از زمان مشاهده این پیش‌نمایش تغییر کرده باشد، انتشار رد می‌شود.</p>
</form>
@endif
</div>
@endsection
