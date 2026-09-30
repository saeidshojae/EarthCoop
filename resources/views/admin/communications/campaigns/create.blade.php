@extends('layouts.admin')
@section('title', 'کمپین جدید - مرکز ارتباطات')
@section('page-title', 'کمپین ارتباطی جدید')
@section('content')
<div class="container mx-auto max-w-4xl px-4 py-6">
    @if($errors->any())<div class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('admin.communications.campaigns.store') }}" class="space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        @csrf
        <div><label class="mb-1 block text-sm">نام کمپین</label><input name="name" value="{{ old('name') }}" class="w-full rounded-lg border-gray-300 dark:bg-gray-900" required></div>
        <div class="grid gap-4 md:grid-cols-2">
            <div><label class="mb-1 block text-sm">Audience</label><select name="audience_key" class="w-full rounded-lg border-gray-300 dark:bg-gray-900" required>@foreach($audienceKeys as $key)<option value="{{ $key }}">{{ $key }}</option>@endforeach</select></div>
            <div><label class="mb-1 block text-sm">شناسه کاربران — فقط specific.user</label><input name="user_ids[]" value="{{ old('user_ids.0') }}" type="number" min="1" class="w-full rounded-lg border-gray-300 dark:bg-gray-900"></div>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <div><label class="mb-1 block text-sm">قالب</label><select name="communication_template_id" class="w-full rounded-lg border-gray-300 dark:bg-gray-900" required>@foreach($templates as $template)<option value="{{ $template->id }}">{{ $template->key }}</option>@endforeach</select></div>
            <div><label class="mb-1 block text-sm">فرستنده</label><select name="communication_sender_identity_id" class="w-full rounded-lg border-gray-300 dark:bg-gray-900"><option value="">پیش‌فرض قالب</option>@foreach($senders as $sender)<option value="{{ $sender->id }}">{{ $sender->key }} — {{ $sender->email }}</option>@endforeach</select></div>
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            <div><label class="mb-1 block text-sm">طبقه‌بندی</label><select name="classification" class="w-full rounded-lg border-gray-300 dark:bg-gray-900"><option value="operational">operational</option><option value="optional">optional</option><option value="required">required</option></select></div>
            <div><label class="mb-1 block text-sm">اولویت</label><input name="priority" type="number" min="1" max="9" value="4" class="w-full rounded-lg border-gray-300 dark:bg-gray-900"></div>
            <div><label class="mb-1 block text-sm">زمان ارسال</label><input name="scheduled_at" type="datetime-local" class="w-full rounded-lg border-gray-300 dark:bg-gray-900"></div>
        </div>
        <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">ایجاد کمپین هیچ ایمیلی ارسال نمی‌کند. ارسال فقط پس از پیش‌نمایش و تایید مجاز آغاز می‌شود.</p>
        <div class="flex gap-2"><button class="rounded-lg bg-gray-900 px-4 py-2 text-white">ذخیره پیش‌نویس</button><a href="{{ route('admin.communications.campaigns.index') }}" class="rounded-lg border border-gray-300 px-4 py-2">انصراف</a></div>
    </form>
</div>
@endsection
