@extends('layouts.admin')

@section('title', 'مدیریت قالب - مرکز ارتباطات')
@section('page-title', $template->name)
@section('page-description', 'کلید فنی: ' . $template->key)

@section('content')
@php($labels = \App\Support\Communication\CommunicationAdminLabels::class)
<div class="container mx-auto px-4 py-6 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $template->name }}</h1>
            <p class="mt-1 text-xs text-gray-500">کلید فنی: <span class="font-mono">{{ $template->key }}</span></p>
        </div>
        <a href="{{ route('admin.communications.templates.index') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 dark:border-gray-600 dark:text-gray-200">بازگشت به قالب‌ها</a>
    </div>

    @if(session('success'))<div class="rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>@endif

    @if(auth()->user()?->hasPermission('communications.templates.manage'))
    @php($latest = $template->versions->first())
    <form method="POST" action="{{ route('admin.communications.templates.publish', $template) }}" class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        @csrf
        <h2 class="font-bold text-gray-900 dark:text-white">انتشار نسخه جدید</h2>
        <p class="text-sm text-gray-500">نسخهٔ منتشرشده قابل ویرایش نیست؛ انتشار دوباره نسخه‌ای تازه می‌سازد.</p>
        <div class="grid gap-4 md:grid-cols-2">
            <div><label class="mb-1 block text-sm">زبان</label><input name="locale" value="{{ old('locale', $latest?->locale ?? 'fa') }}" class="w-full rounded-lg border-gray-300 dark:bg-gray-900" required><p class="mt-1 text-xs text-gray-500">برای فارسی از <span class="font-mono">fa</span> استفاده کنید.</p></div>
            <div><label class="mb-1 block text-sm">فرستنده</label><select name="communication_sender_identity_id" class="w-full rounded-lg border-gray-300 dark:bg-gray-900"><option value="">فرستنده نسخه قبلی</option>@foreach($senders as $sender)<option value="{{ $sender->id }}">{{ $sender->display_name }} — {{ $sender->email }}</option>@endforeach</select></div>
        </div>
        <div><label class="mb-1 block text-sm">موضوع پیام</label><input name="subject" value="{{ old('subject', $latest?->subject) }}" class="w-full rounded-lg border-gray-300 dark:bg-gray-900" required></div>
        <div><label class="mb-1 block text-sm">بدنه پیام (HTML)</label><textarea name="body" rows="8" class="w-full rounded-lg border-gray-300 font-mono text-sm dark:bg-gray-900" required>{{ old('body', $latest?->body) }}</textarea></div>
        <div><label class="mb-1 block text-sm">ساختار متغیرها (JSON)</label><textarea name="variables_schema" rows="7" class="w-full rounded-lg border-gray-300 font-mono text-sm dark:bg-gray-900" required>{{ old('variables_schema', json_encode($latest?->variables_schema ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}</textarea><p class="mt-1 text-xs text-gray-500">اگر قالب متغیری ندارد، یک آرایهٔ خالی وارد کنید: <span class="font-mono">[]</span></p></div>
        <button class="rounded-lg bg-gray-900 px-4 py-2 text-white">انتشار نسخه جدید</button>
    </form>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <h2 class="mb-4 font-bold text-gray-900 dark:text-white">تاریخچه نسخه‌ها</h2>
        <div class="space-y-3">
            @forelse($template->versions as $version)
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex flex-wrap justify-between gap-2"><strong>نسخه {{ $version->version }} / {{ $labels::locale($version->locale) }}</strong><span class="text-xs text-gray-500">انتشار: {{ optional($version->published_at)->format('Y-m-d H:i') ?? '—' }}</span></div>
                    <p class="mt-2 text-sm">موضوع: {{ $version->subject }}</p>
                    <p class="mt-1 text-xs text-gray-500">فرستنده: {{ $version->senderIdentity?->display_name ?? 'پیش‌فرض' }}@if($version->senderIdentity?->email) — {{ $version->senderIdentity->email }}@endif</p>
                </div>
            @empty
                <p class="text-sm text-gray-500">نسخه‌ای منتشر نشده است.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
