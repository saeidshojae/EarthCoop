@extends('layouts.admin')
@section('title', 'نسخه‌های اسناد حقوقی')
@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
<h1 class="text-2xl font-bold mb-3">مدیریت نسخه‌های اسناد حقوقی</h1>
<p class="mb-4">متن مواد را در ویرایشگرهای موجود مدیریت کنید. انتشار در این بخش، یک نسخه ثابت از مواد همان لحظه ثبت می‌کند.</p>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('admin.legal-versions.draft') }}" class="border rounded p-4 mb-5">
@csrf
<h2 class="font-bold mb-3">ساخت پیش‌نویس نسخه</h2>
<div class="grid grid-cols-1 md:grid-cols-2 gap-3">
<label>شناسه سند <input class="form-control" name="slug" required placeholder="membership"></label>
<label>عنوان سند <input class="form-control" name="title" required></label>
<label>منبع <select class="form-control" name="source_type" required><option value="terms">اساسنامه و شرایط استفاده</option><option value="najm_bahar_agreements">توافقنامه نجم‌بهار</option></select></label>
<label>شناسه ریشه والد <input class="form-control" type="number" min="1" name="source_root_id" required></label>
<label>نسخه <input class="form-control" name="version_label" placeholder="1.0" required></label>
</div>
<p class="mt-2 text-sm">ریشه‌های اساسنامه: @foreach($termRoots as $root) {{ $root->id }}: {{ $root->title }}؛ @endforeach</p>
<p class="text-sm">ریشه‌های نجم‌بهار: @foreach($financialRoots as $root) {{ $root->id }}: {{ $root->title }}؛ @endforeach</p>
<button class="btn btn-primary mt-3" type="submit">ساخت پیش‌نویس</button>
</form>
@foreach($documents as $document)
<section class="border rounded p-4 mb-4">
<h2 class="font-bold">{{ $document->title }} <small>({{ $document->slug }})</small></h2>
@foreach($document->versions as $version)
<div class="flex flex-wrap gap-3 items-center border-t py-3">
<span>نسخه {{ $version->version_label }}</span>
<span>{{ $version->status }}</span>
<a href="{{ route('admin.legal-versions.preview', $version) }}" class="btn btn-outline-primary">پیش‌نمایش</a>
@if($version->status === 'draft')
<form method="POST" action="{{ route('admin.legal-versions.publish', $version) }}" onsubmit="return confirm('نسخه منتشرشده تغییرناپذیر است. تأیید می‌کنید؟')">
@csrf <label><input type="checkbox" name="confirm_publish" value="1" required> تأیید انتشار غیرقابل بازگشت</label>
<button type="submit" class="btn btn-success">انتشار رسمی</button>
</form>
@endif
</div>
@endforeach
</section>
@endforeach
</div>
@endsection
