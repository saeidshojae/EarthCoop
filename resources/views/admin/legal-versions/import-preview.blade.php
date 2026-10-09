@extends('layouts.admin')
@section('title', 'بازبینی ورود سند')
@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
<a href="{{ route('admin.legal-versions.index') }}">بازگشت</a>
<h1 class="text-2xl font-bold my-3">پیش‌نمایش ورود {{ $data['title'] }}</h1>
<p class="mb-4">این عملیات فقط پس از تأیید، یک والد جدید و مواد فرزند را می‌سازد. هیچ متن قبلی بازنویسی نمی‌شود و این عملیات به‌معنای انتشار رسمی نیست.</p>
<div class="border rounded p-4 leading-loose mb-4">
<h2 class="font-bold">{{ $data['title'] }}</h2>
<p class="whitespace-pre-wrap">{{ $data['content'] }}</p>
@foreach($data['children'] as $section)
<details class="border rounded p-3 my-3"><summary class="font-bold">{{ $section['title'] }}</summary><p class="whitespace-pre-wrap">{{ $section['content'] }}</p></details>
@endforeach
</div>
<form method="POST" action="{{ route('admin.legal-versions.import', $data['slug']) }}">
@csrf
<input type="hidden" name="preview_sha256" value="{{ $previewSha256 }}">
<label class="block mb-3"><input type="checkbox" name="confirm_import" value="1" required> متن را بررسی کرده‌ام و ورود بدون انتشار را تأیید می‌کنم.</label>
<button class="btn btn-primary" type="submit">ایجاد والد و مواد فرزند</button>
</form>
</div>
@endsection
