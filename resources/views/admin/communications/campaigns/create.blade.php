@extends('layouts.admin')
@section('title', 'کمپین جدید - مرکز ارتباطات')
@section('page-title', 'کمپین ارتباطی جدید')
@section('page-description', 'تعریف پیش‌نویس ارسال گروهی با مخاطبان و قالب مشخص')
@section('content')
@php($labels = \App\Support\Communication\CommunicationAdminLabels::class)
<div class="container mx-auto max-w-4xl px-4 py-6">
    @if($errors->any())<div class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('admin.communications.campaigns.store') }}" class="space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        @csrf
        <div><label class="mb-1 block text-sm">نام کمپین</label><input name="name" value="{{ old('name') }}" class="w-full rounded-lg border-gray-300 dark:bg-gray-900" required></div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm">مخاطبان</label>
                <select name="audience_key" class="w-full rounded-lg border-gray-300 dark:bg-gray-900" required>
                    @foreach($audienceKeys as $key)<option value="{{ $key }}" @selected(old('audience_key') === $key)>{{ $labels::audience($key) }}</option>@endforeach
                </select>
            </div>
            <div><label class="mb-1 block text-sm">شناسه کاربر — فقط برای «کاربر مشخص»</label><input name="user_ids[]" value="{{ old('user_ids.0') }}" type="number" min="1" class="w-full rounded-lg border-gray-300 dark:bg-gray-900"><p class="mt-1 text-xs text-gray-500">در حالت «کاربر مشخص»، شناسهٔ کاربر مقصد را وارد کنید.</p></div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div><label class="mb-1 block text-sm">قالب پیام</label><select name="communication_template_id" class="w-full rounded-lg border-gray-300 dark:bg-gray-900" required>@foreach($templates as $template)<option value="{{ $template->id }}" @selected((string) old('communication_template_id') === (string) $template->id)>{{ $template->name }} — {{ $template->key }}</option>@endforeach</select></div>
            <div><label class="mb-1 block text-sm">فرستنده</label><select name="communication_sender_identity_id" class="w-full rounded-lg border-gray-300 dark:bg-gray-900"><option value="">فرستنده پیش‌فرض قالب</option>@foreach($senders as $sender)<option value="{{ $sender->id }}" @selected((string) old('communication_sender_identity_id') === (string) $sender->id)>{{ $sender->display_name }} — {{ $sender->email }}</option>@endforeach</select></div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div><label class="mb-1 block text-sm">طبقه‌بندی پیام</label><select name="classification" class="w-full rounded-lg border-gray-300 dark:bg-gray-900">@foreach(['operational', 'optional', 'required'] as $classification)<option value="{{ $classification }}" @selected(old('classification', 'operational') === $classification)>{{ $labels::classification($classification) }}</option>@endforeach</select></div>
            <div><label class="mb-1 block text-sm">اولویت</label><input name="priority" type="number" min="1" max="9" value="{{ old('priority', 4) }}" class="w-full rounded-lg border-gray-300 dark:bg-gray-900"></div>
            <div><label class="mb-1 block text-sm">زمان ارسال</label><x-temporal.date-time-input name="scheduled_at" :value="old('scheduled_at')" class="w-full rounded-lg border-gray-300 dark:bg-gray-900" /><p class="mt-1 text-xs text-gray-500">خالی بگذارید تا پس از تأیید، آمادهٔ ارسال فوری شود.</p></div>
        </div>

        <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">ایجاد کمپین هیچ ایمیلی ارسال نمی‌کند. ارسال فقط پس از پیش‌نمایش مخاطبان و تأیید نهایی مجاز می‌شود.</p>
        <div class="flex gap-2"><button class="rounded-lg bg-gray-900 px-4 py-2 text-white">ذخیره پیش‌نویس</button><a href="{{ route('admin.communications.campaigns.index') }}" class="rounded-lg border border-gray-300 px-4 py-2">انصراف</a></div>
    </form>
</div>
@endsection
