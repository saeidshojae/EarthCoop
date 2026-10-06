@extends('layouts.admin')

@section('title', 'صندوق پیام‌های تماس')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-white">صندوق پیام‌های تماس</h1>
            <p class="mt-1 text-sm text-slate-500">پیام‌های عمومی فرم تماس، جدا از تیکت‌های پشتیبانی اعضا</p>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-6">
        @foreach([
            'new' => ['جدید', $stats['new']],
            'reviewing' => ['در حال بررسی', $stats['reviewing']],
            'replied' => ['پاسخ‌داده‌شده', $stats['replied']],
            'converted' => ['تبدیل‌شده', $stats['converted']],
            'closed' => ['بسته', $stats['closed']],
            'spam' => ['اسپم', $stats['spam']],
        ] as $key => [$label, $count])
            <a href="{{ route('admin.contact-messages.index', ['status' => $key]) }}" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
                <div class="text-xs text-slate-500">{{ $label }}</div>
                <div class="mt-1 text-xl font-bold text-slate-800 dark:text-white">{{ $count }}</div>
            </a>
        @endforeach
    </div>

    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden">
        <form method="GET" action="{{ route('admin.contact-messages.index') }}" class="p-4 flex flex-col md:flex-row gap-3 border-b border-slate-200 dark:border-slate-700">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="جستجو در نام، ایمیل، موضوع یا متن..." class="flex-1 rounded-lg border-slate-300 dark:bg-slate-900">
            <select name="status" class="rounded-lg border-slate-300 dark:bg-slate-900">
                <option value="">همه وضعیت‌ها</option>
                @foreach(['new'=>'جدید','reviewing'=>'در حال بررسی','replied'=>'پاسخ‌داده‌شده','converted'=>'تبدیل‌شده','closed'=>'بسته','spam'=>'اسپم'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="px-4 py-2 rounded-lg bg-slate-800 text-white">اعمال</button>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-4 py-3 text-right">فرستنده</th>
                        <th class="px-4 py-3 text-right">موضوع</th>
                        <th class="px-4 py-3 text-right">وضعیت</th>
                        <th class="px-4 py-3 text-right">تاریخ</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($messages as $message)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-800 dark:text-white">{{ $message->name ?: 'بدون نام' }}</div>
                                <div class="text-xs text-slate-500" dir="ltr">{{ $message->email ?: '—' }}</div>
                            </td>
                            <td class="px-4 py-3 max-w-md">
                                <div class="font-medium">{{ $message->subject }}</div>
                                <div class="text-xs text-slate-500 truncate">{{ $message->message }}</div>
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $labels = ['new'=>'جدید','reviewing'=>'در حال بررسی','replied'=>'پاسخ‌داده‌شده','converted'=>'تبدیل‌شده','closed'=>'بسته','spam'=>'اسپم'];
                                @endphp
                                <span class="inline-flex px-2 py-1 rounded-full bg-slate-100 dark:bg-slate-700">{{ $labels[$message->status] ?? $message->status }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-500">@if($message->created_at)<x-temporal.date-time :value="$message->created_at" />@endif</td>
                            <td class="px-4 py-3 text-left">
                                <a href="{{ route('admin.contact-messages.show', $message) }}" class="text-blue-600 hover:underline">مشاهده</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">پیامی یافت نشد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4">{{ $messages->links() }}</div>
    </div>
</div>
@endsection
