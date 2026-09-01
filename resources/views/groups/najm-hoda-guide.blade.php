@extends('layouts.unified')

@section('title', 'راهنمای نجم هدا - ' . $group->name)

@section('content')
<div class="w-full max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-4 sm:px-6 py-5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-gray-900">راهنمای استفاده از نجم هدا</h1>
                <p class="text-sm text-gray-500 mt-1">گروه: {{ $group->name }}</p>
            </div>
            <a href="{{ route('groups.show', $group) }}" class="inline-flex items-center justify-center px-4 py-2 text-sm rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50">
                بازگشت به گروه
            </a>
        </div>

        <div class="p-4 sm:p-6 space-y-6 text-sm leading-7 text-gray-700">
            <section>
                <h2 class="text-base font-bold text-gray-900 mb-2">قواعد کلی</h2>
                <ul class="list-disc pr-5 space-y-1">
                    <li>بهتر است نام «نجم هدا» را در پیام بیاورید تا پاسخ سریع‌تر فعال شود.</li>
                    <li>نیازی به دستور خشک نیست؛ جمله طبیعی هم قابل فهم است.</li>
                    <li>درخواست‌های اجرایی (پست/نظرسنجی/کامنت/واکنش/پاکسازی) فقط با دسترسی مدیر یا بازرس انجام می‌شوند.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-base font-bold text-gray-900 mb-2">نمونه‌های چت برای مدیران</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="rounded-xl border border-gray-200 p-3">
                        <div class="font-semibold mb-1">ایجاد پست</div>
                        <div>«نجم هدا لطفا یک پست شروع همکاری برای گروه بگذار.»</div>
                        <div>«نجم هدا پست بساز | عنوان: ... | متن: ...»</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-3">
                        <div class="font-semibold mb-1">ایجاد نظرسنجی</div>
                        <div>«نجم هدا یک نظرسنجی درباره اولویت این هفته بساز.»</div>
                        <div>«نجم هدا نظرسنجی بساز | سوال: ... | گزینه‌ها: ... , ... , ...»</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-3">
                        <div class="font-semibold mb-1">کامنت زیر پست</div>
                        <div>«نجم هدا زیر پست #12 نظر بگذار: ...»</div>
                        <div>«نجم هدا روی پستم کامنت بذار.»</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-3">
                        <div class="font-semibold mb-1">واکنش</div>
                        <div>«نجم هدا روی پیامم لایک بزن.»</div>
                        <div>«نجم هدا به پست #34 دیس‌لایک بده.»</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-3">
                        <div class="font-semibold mb-1">پیام خصوصی</div>
                        <div>«نجم هدا برای [نام کاربر] پیام خصوصی بفرست: ...»</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-3">
                        <div class="font-semibold mb-1">صورتجلسه و مصوبات</div>
                        <div>«نجم هدا جلسه را جمع‌بندی کن.»</div>
                        <div>«نجم هدا مصوبات جلسه را استخراج کن.»</div>
                    </div>
                </div>
            </section>

            <section>
                <h2 class="text-base font-bold text-gray-900 mb-2">پاکسازی پیام‌های بی‌ارتباط</h2>
                <div class="space-y-1">
                    <div>اجرای دستی: «نجم هدا پیام‌های بی‌ارتباط را پاکسازی کن.»</div>
                    <div>زمان‌بندی: «نجم هدا هر 24 ساعت پاکسازی را اجرا کن.»</div>
                    <div>توقف: «نجم هدا پاکسازی خودکار را خاموش کن.»</div>
                </div>
            </section>

            <section>
                <h2 class="text-base font-bold text-gray-900 mb-2">وضعیت فعلی این گروه</h2>
                <div class="rounded-xl border border-gray-200 p-4 bg-gray-50 space-y-1">
                    <div>فعال بودن دستیار: <strong>{{ $config && $config->enabled ? 'فعال' : 'غیرفعال' }}</strong></div>
                    <div>حالت پاسخ‌دهی: <strong>{{ $config->auto_reply_mode ?? '-' }}</strong></div>
                    <div>عامل پیش‌فرض: <strong>{{ $config->default_agent ?? '-' }}</strong></div>
                    <div>دامنه دانش: <strong>{{ $config->knowledge_scope ?? '-' }}</strong></div>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
