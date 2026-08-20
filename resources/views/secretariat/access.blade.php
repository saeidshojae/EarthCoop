@extends('layouts.unified')

@section('title', 'مدیریت دسترسی - ' . $record->title)

@section('content')
<div class="container mx-auto px-4 py-6 max-w-5xl">
    <div class="mb-6">
        <a href="{{ route('secretariat.records.show', [$office, $record]) }}" class="text-sm text-emerald-700 hover:underline">
            <i class="fa-solid fa-arrow-right ml-1"></i> بازگشت به سند
        </a>
        <h1 class="text-2xl font-bold mt-3">مدیریت دسترسی صریح</h1>
        <p class="text-sm text-gray-500 mt-1">{{ $record->title }} — {{ $record->confidentiality }}</p>
        <p class="text-sm text-amber-700 mt-2">لغو دسترسی تاریخچه را حذف نمی‌کند؛ grant قبلی در audit و ACL history باقی می‌ماند.</p>
    </div>

    @if($errors->any())
        <div class="rounded-xl bg-red-50 border border-red-200 text-red-800 p-4 mb-5">
            <ul class="list-disc pr-5 space-y-1 text-sm">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <section class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm p-6 mb-6">
        <h2 class="font-bold text-lg mb-4">اعطای دسترسی مشاهده</h2>
        <form method="POST" action="{{ route('secretariat.acl.grant', [$office, $record]) }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            @csrf
            <div>
                <label class="block text-sm font-medium mb-2">نوع ذی‌نفع</label>
                <select name="principal_type" required class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                    <option value="user">کاربر</option>
                    <option value="group">گروه</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-2">شناسه</label>
                <input type="number" min="1" name="principal_id" required class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700" placeholder="User/Group ID">
            </div>
            <div>
                <label class="block text-sm font-medium mb-2">انقضا (اختیاری)</label>
                <input type="datetime-local" name="expires_at" class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
            </div>
            <button class="rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5">ثبت دسترسی</button>
        </form>
    </section>

    <section class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <h2 class="font-bold">تاریخچه دسترسی‌ها</h2>
            <span class="text-xs text-gray-500">{{ $entries->count() }} مورد</span>
        </div>
        @forelse($entries as $entry)
            @php $active = $entry->isActive(); @endphp
            <div class="p-4 border-b last:border-b-0 border-gray-100 dark:border-gray-700 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <strong>{{ $entry->principal_type }} #{{ $entry->principal_id }}</strong>
                        <span class="text-xs rounded-full px-2 py-1 {{ $active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">
                            {{ $active ? 'فعال' : 'پایان‌یافته' }}
                        </span>
                    </div>
                    <div class="text-xs text-gray-500 mt-2">
                        grant #{{ $entry->id }} · {{ optional($entry->granted_at)->format('Y-m-d H:i') }}
                        @if($entry->expires_at) · انقضا {{ $entry->expires_at->format('Y-m-d H:i') }} @endif
                        @if($entry->revoked_at) · لغو {{ $entry->revoked_at->format('Y-m-d H:i') }} @endif
                    </div>
                </div>
                @if($active)
                    <form method="POST" action="{{ route('secretariat.acl.revoke', [$office, $record, $entry]) }}">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-xl border border-red-200 text-red-700 hover:bg-red-50 px-4 py-2">لغو دسترسی</button>
                    </form>
                @endif
            </div>
        @empty
            <div class="p-8 text-center text-gray-500">هنوز ACL صریحی برای این سند ثبت نشده است.</div>
        @endforelse
    </section>
</div>
@endsection
