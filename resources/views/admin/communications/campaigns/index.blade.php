@extends('layouts.admin')
@section('title', 'کمپین‌ها - مرکز ارتباطات')
@section('page-title', 'کمپین‌های ارتباطی')
@section('content')
<div class="container mx-auto px-4 py-6 space-y-5">
    @if(session('success'))<div class="rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>@endif

    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">کمپین‌ها</h1>
            <p class="text-sm text-gray-500">ارسال گروهی فقط پس از پیش‌نمایش و تایید مجاز اجرا می‌شود.</p>
        </div>
        @if(auth()->user()?->hasPermission('communications.campaigns.create'))
            <a href="{{ route('admin.communications.campaigns.create') }}" class="rounded-lg bg-gray-900 px-4 py-2 text-sm text-white">کمپین جدید</a>
        @endif
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <table class="min-w-full text-sm">
            <thead><tr class="border-b border-gray-200 text-right dark:border-gray-700"><th class="p-3">نام</th><th class="p-3">وضعیت</th><th class="p-3">قالب</th><th class="p-3">زمان</th><th class="p-3">عملیات</th></tr></thead>
            <tbody>
            @forelse($campaigns as $campaign)
                <tr class="border-b border-gray-100 dark:border-gray-700/60">
                    <td class="p-3">{{ $campaign->name }}</td>
                    <td class="p-3">{{ $campaign->status }}</td>
                    <td class="p-3">{{ $campaign->template?->key }}</td>
                    <td class="p-3">{{ $campaign->scheduled_at?->format('Y-m-d H:i') ?? 'فوری پس از تایید' }}</td>
                    <td class="p-3"><a class="underline" href="{{ route('admin.communications.campaigns.preview', $campaign) }}">پیش‌نمایش</a></td>
                </tr>
            @empty
                <tr><td class="p-6 text-center text-gray-500" colspan="5">هنوز کمپینی ثبت نشده است.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $campaigns->links() }}
</div>
@endsection
