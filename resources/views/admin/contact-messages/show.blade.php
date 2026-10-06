@extends('layouts.admin')

@section('title', 'پیام تماس')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <div class="mb-6">
        <a href="{{ route('admin.contact-messages.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← بازگشت به صندوق تماس</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-800 dark:text-white">{{ $contactMessage->subject }}</h1>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-emerald-700">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-red-700">{{ session('error') }}</div>
    @endif

    <div class="grid lg:grid-cols-[1fr_320px] gap-6">
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-6">
            <div class="grid md:grid-cols-2 gap-4 text-sm mb-6">
                <div><span class="text-slate-500">نام:</span> <strong>{{ $contactMessage->name ?: '—' }}</strong></div>
                <div><span class="text-slate-500">ایمیل:</span> <strong dir="ltr">{{ $contactMessage->email ?: '—' }}</strong></div>
                <div><span class="text-slate-500">تلفن:</span> <strong dir="ltr">{{ $contactMessage->phone ?: '—' }}</strong></div>
                <div><span class="text-slate-500">منبع:</span> <strong>{{ $contactMessage->source }}</strong></div>
            </div>

            <div class="border-t border-slate-200 dark:border-slate-700 pt-5">
                <div class="text-sm text-slate-500 mb-2">متن پیام</div>
                <div class="whitespace-pre-wrap leading-8 text-slate-800 dark:text-slate-100">{{ $contactMessage->message }}</div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5">
                <h2 class="font-bold mb-3">وضعیت</h2>
                <form method="POST" action="{{ route('admin.contact-messages.status', $contactMessage) }}" class="space-y-3">
                    @csrf
                    <select name="status" class="w-full rounded-lg border-slate-300 dark:bg-slate-900">
                        @foreach(['new'=>'جدید','reviewing'=>'در حال بررسی','replied'=>'پاسخ‌داده‌شده','closed'=>'بسته','spam'=>'اسپم'] as $value => $label)
                            <option value="{{ $value }}" @selected($contactMessage->status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button class="w-full px-4 py-2 rounded-lg bg-slate-800 text-white">ذخیره وضعیت</button>
                </form>
            </div>

            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5">
                <h2 class="font-bold mb-3">پاسخ ایمیلی</h2>
                @if($contactMessage->email)
                    <form method="POST" action="{{ route('admin.contact-messages.reply', $contactMessage) }}" class="space-y-3">
                        @csrf
                        <textarea name="message" rows="6" required minlength="5" maxlength="10000" class="w-full rounded-lg border-slate-300 dark:bg-slate-900" placeholder="متن پاسخ...">{{ old('message') }}</textarea>
                        <button class="w-full px-4 py-2 rounded-lg bg-blue-600 text-white">ارسال از طریق مرکز ارتباطات</button>
                    </form>
                    <p class="mt-2 text-xs text-slate-500">پاسخ از مسیر canonical مرکز ارتباطات و هویت پشتیبانی ارسال می‌شود.</p>
                @else
                    <p class="text-sm text-slate-600">این پیام ایمیل معتبری برای پاسخ ندارد.</p>
                @endif
            </div>

            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5">
                <h2 class="font-bold mb-3">ارتباط با حساب کاربری</h2>
                @if($contactMessage->convertedTicket)
                    <p class="text-sm text-slate-600 mb-3">این پیام قبلاً به تیکت تبدیل شده است.</p>
                    <a href="{{ route('admin.tickets.show', $contactMessage->convertedTicket) }}" class="block text-center px-4 py-2 rounded-lg bg-blue-600 text-white">مشاهده تیکت</a>
                @elseif($registeredUser)
                    <p class="text-sm text-slate-600 mb-3">ایمیل این پیام متعلق به یک عضو ثبت‌شده است.</p>
                    <form method="POST" action="{{ route('admin.contact-messages.convert', $contactMessage) }}">
                        @csrf
                        <button class="w-full px-4 py-2 rounded-lg bg-emerald-600 text-white">تبدیل به تیکت کاربر</button>
                    </form>
                @else
                    <p class="text-sm text-slate-600">این ایمیل به حساب عضو ثبت‌شده‌ای متصل نیست. پیام در صندوق تماس باقی می‌ماند و تیکت کاربری ساخته نمی‌شود.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
