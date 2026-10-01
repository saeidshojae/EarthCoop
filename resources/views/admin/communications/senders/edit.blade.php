@extends('layouts.admin')
@section('title', 'ویرایش فرستنده - مرکز ارتباطات')
@section('page-title', 'ویرایش فرستنده')
@section('content')
<div class="container mx-auto max-w-3xl px-4 py-6">
    <form method="POST" action="{{ route('admin.communications.senders.update', $sender) }}" class="space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        @csrf @method('PUT')
        <div><label class="mb-1 block text-sm">کلید</label><input name="key" value="{{ old('key', $sender->key) }}" class="w-full rounded-lg border-gray-300 dark:bg-gray-900" required></div>
        <div class="grid gap-4 md:grid-cols-2"><div><label class="mb-1 block text-sm">ایمیل</label><input type="email" name="email" value="{{ old('email', $sender->email) }}" class="w-full rounded-lg border-gray-300 dark:bg-gray-900" required></div><div><label class="mb-1 block text-sm">نام نمایشی</label><input name="display_name" value="{{ old('display_name', $sender->display_name) }}" class="w-full rounded-lg border-gray-300 dark:bg-gray-900" required></div></div>
        <div><label class="mb-1 block text-sm">Reply-To</label><input type="email" name="reply_to" value="{{ old('reply_to', $sender->reply_to) }}" class="w-full rounded-lg border-gray-300 dark:bg-gray-900"></div>
        <div><label class="mb-1 block text-sm">هدف</label><input name="purpose" value="{{ old('purpose', $sender->purpose) }}" class="w-full rounded-lg border-gray-300 dark:bg-gray-900"></div>
        <div><label class="mb-1 block text-sm">کلید System Identity</label><input name="system_identity_key" value="{{ old('system_identity_key', $sender->system_identity_key) }}" class="w-full rounded-lg border-gray-300 dark:bg-gray-900"></div>
        <input type="hidden" name="is_active" value="0"><label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $sender->is_active))> فعال</label>
        <div class="flex gap-2"><button class="rounded-lg bg-gray-900 px-4 py-2 text-white">ذخیره تغییرات</button><a href="{{ route('admin.communications.senders.index') }}" class="rounded-lg border border-gray-300 px-4 py-2">انصراف</a></div>
    </form>
</div>
@endsection
