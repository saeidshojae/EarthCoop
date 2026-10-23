@extends('layouts.admin')
@section('title', 'نسخه‌های اسناد حقوقی')
@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
<h1 class="text-2xl font-bold mb-3">مدیریت نسخه‌های اسناد حقوقی</h1>
<p class="mb-4">متن مواد را در ویرایشگرهای موجود مدیریت کنید. انتشار در این بخش، یک نسخه ثابت از مواد همان لحظه ثبت می‌کند.</p>
<div class="border rounded p-4 mb-4">
<h2 class="font-bold mb-2">ورود بازبینی‌شده متون اولیه به پنل</h2>
<p class="mb-2">ورود هر سند ابتدا پیش‌نمایش دارد و هیچ رکورد موجود را تغییر نمی‌دهد. اگر سند قبلاً وارد شده باشد، از بخش «اسناد نیازمند نسخه» در پایین همین صفحه ادامه دهید؛ ورود مجدد لازم نیست.</p>
<a class="btn btn-outline-primary m-1" href="{{ route('admin.legal-versions.import-preview', 'membership') }}">اساسنامه عضویت</a>
<a class="btn btn-outline-primary m-1" href="{{ route('admin.legal-versions.import-preview', 'terms') }}">شرایط استفاده</a>
<a class="btn btn-outline-primary m-1" href="{{ route('admin.legal-versions.import-preview', 'najm-bahar') }}">توافقنامه نجم‌بهار</a>
</div>
@if(session('success'))<div role="status" class="alert alert-success mb-3">{{ session('success') }}</div>@endif
@if($errors->any())<div role="alert" class="alert alert-danger mb-3">{{ $errors->first() }}</div>@endif

@php
$documentsNeedingVersion = $documents->filter(fn ($document) => $document->versions->isEmpty());
@endphp
@if($documentsNeedingVersion->isNotEmpty())
<section class="border border-warning rounded p-4 mb-5">
<h2 class="font-bold mb-2">اسناد واردشده که هنوز نسخه ندارند</h2>
<p class="mb-3">برای هر سند، اطلاعات از رکورد موجود پر شده است. ساخت پیش‌نویس متن را منتشر نمی‌کند؛ انتشار فقط از صفحه پیش‌نمایش نسخه و پس از تأیید جداگانه انجام می‌شود.</p>
@foreach($documentsNeedingVersion as $document)
<form method="POST" action="{{ route('admin.legal-versions.draft') }}" class="border rounded p-3 mb-3">
@csrf
<h3 class="font-bold mb-3">{{ $document->title }} <small>({{ $document->slug }})</small></h3>
<input type="hidden" name="slug" value="{{ $document->slug }}">
<input type="hidden" name="title" value="{{ $document->title }}">
<input type="hidden" name="source_type" value="{{ $document->source_type }}">
<input type="hidden" name="source_root_id" value="{{ $document->source_root_id }}">
<div class="flex flex-wrap items-end gap-3">
<label>شماره نسخه
<input class="form-control" name="version_label" value="1.0" required pattern="[0-9]+(\.[0-9]+){1,3}">
</label>
<label>نوع انتشار
<select class="form-control" name="publication_policy" required>
<option value="minor">تغییر جزئی — بدون اعلان و پذیرش مجدد</option>
<option value="notify">اطلاع‌رسانی — بدون پذیرش مجدد</option>
<option value="material" selected>تغییر اساسی — الزام پذیرش مجدد</option>
</select>
</label>
<button class="btn btn-primary" type="submit">ساخت پیش‌نویس این سند</button>
</div>
<p class="text-sm mt-2">ریشه والد: {{ $document->source_root_id }} — نوع منبع: {{ $document->source_type }}</p>
</form>
@endforeach
</section>
@endif

@if($documents->isNotEmpty())
<h2 class="font-bold text-xl mb-3">اسناد نسخه‌بندی‌شده</h2>
@foreach($documents->filter(fn ($document) => $document->versions->isNotEmpty()) as $document)
<section class="border rounded p-4 mb-4">
<h3 class="font-bold">{{ $document->title }} <small>({{ $document->slug }})</small></h3>
@if($document->source_root_id)
<form method="POST" action="{{ route('admin.legal-versions.draft') }}" class="border rounded p-3 my-3">
    @csrf
    <input type="hidden" name="slug" value="{{ $document->slug }}">
    <input type="hidden" name="title" value="{{ $document->title }}">
    <input type="hidden" name="source_type" value="{{ $document->source_type }}">
    <input type="hidden" name="source_root_id" value="{{ $document->source_root_id }}">
    <div class="flex flex-wrap items-end gap-3">
        <label>شماره نسخه جدید
            <input class="form-control" name="version_label" placeholder="مثلاً 1.1" required>
        </label>
        <label>سیاست انتشار
            <select class="form-control" name="publication_policy" required>
                <option value="minor">تغییر جزئی — بدون اعلان و پذیرش مجدد</option>
                <option value="notify">اطلاع‌رسانی — بدون پذیرش مجدد</option>
                <option value="material" selected>تغییر اساسی — اعلان و الزام پذیرش مجدد</option>
            </select>
        </label>
        <button class="btn btn-primary" type="submit">ساخت پیش‌نویس نسخه جدید</button>
    </div>
</form>
@endif
@foreach($document->versions as $version)
<div class="flex flex-wrap gap-3 items-center border-t py-3">
<span>نسخه {{ $version->version_label }}</span>
<span>{{ $version->status }}</span>
<span>سیاست انتشار:
    {{ ['minor' => 'جزئی', 'notify' => 'اطلاع‌رسانی', 'material' => 'اساسی'][$version->publication_policy ?? 'material'] ?? 'اساسی' }}
</span>
@if($version->status === 'published')
    <span>پذیرش: {{ $acceptanceSummary[$version->acceptance_required_version_id] ?? 0 }} از {{ $memberCount }} عضو؛ در انتظار: {{ max(0, $memberCount - ($acceptanceSummary[$version->acceptance_required_version_id] ?? 0)) }}</span>
    @foreach(($deliverySummary[$version->id] ?? collect()) as $delivery)
        <span>
            {{ $delivery->channel === 'email' ? 'ایمیل' : 'درون‌برنامه‌ای' }}:
            {{ ['queued' => 'در صف', 'sending' => 'در حال ارسال', 'sent' => 'ارسال‌شده', 'failed' => 'ناموفق', 'skipped' => 'ردشده'][$delivery->status] ?? $delivery->status }}
            ({{ $delivery->total }})
        </span>
    @endforeach
@endif
<a href="{{ route('admin.legal-versions.preview', $version) }}" class="btn btn-outline-primary">بازبینی نسخه</a>
@if($version->status === 'draft')
<span class="text-sm">برای انتشار، نسخه را باز کنید و پس از بررسی، تأیید انتشار را انجام دهید.</span>
@elseif($version->status === 'published')
<span class="text-sm">این نسخه منتشر شده است.</span>
@endif
</div>
@endforeach
</section>
@endforeach
@else
<p class="text-sm">هنوز سندی برای نسخه‌بندی ثبت نشده است.</p>
@endif
</div>
@endsection
