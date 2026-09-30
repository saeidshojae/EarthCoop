@extends('layouts.admin')

@section('title', 'فرستنده‌ها - مرکز ارتباطات')
@section('page-title', 'هویت‌های فرستنده')
@section('page-description', 'فرستنده‌های رسمی و قابل انتخاب مرکز ارتباطات')

@section('content')
<div class="container mx-auto px-4 py-6 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="text-2xl font-bold text-gray-900 dark:text-white">هویت‌های فرستنده</h1><p class="mt-1 text-sm text-gray-500">فرستنده در حال استفاده حذف نمی‌شود.</p></div>
        <div class="flex gap-2">
            @if(auth()->user()?->hasPermission('communications.senders.manage'))<a href="{{ route('admin.communications.senders.create') }}" class="rounded-lg bg-gray-900 px-3 py-2 text-sm text-white">فرستنده جدید</a>@endif
            <a href="{{ route('admin.communications.index') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600">بازگشت</a>
        </div>
    </div>
    @if(session('success'))<div class="rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-900/50"><tr class="text-right text-xs text-gray-500"><th class="px-4 py-3">کلید</th><th class="px-4 py-3">ایمیل</th><th class="px-4 py-3">نام نمایشی</th><th class="px-4 py-3">مصرف</th><th class="px-4 py-3">وضعیت</th><th class="px-4 py-3"></th></tr></thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">@forelse($senders as $sender)<tr class="text-sm"><td class="px-4 py-3 font-mono text-xs">{{ $sender->key }}</td><td class="px-4 py-3">{{ $sender->email }}</td><td class="px-4 py-3">{{ $sender->display_name }}</td><td class="px-4 py-3">{{ $sender->template_versions_count + $sender->rules_count + $sender->campaigns_count }}</td><td class="px-4 py-3">{{ $sender->is_active ? 'فعال' : 'غیرفعال' }}</td><td class="px-4 py-3">@if(auth()->user()?->hasPermission('communications.senders.manage'))<a href="{{ route('admin.communications.senders.edit', $sender) }}" class="text-blue-600 hover:underline">ویرایش</a>@endif</td></tr>@empty<tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">فرستنده‌ای ثبت نشده است.</td></tr>@endforelse</tbody>
    </table></div>@if($senders->hasPages())<div class="border-t px-4 py-3">{{ $senders->links() }}</div>@endif</div>
</div>
@endsection
