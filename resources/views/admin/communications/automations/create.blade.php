@extends('layouts.admin')
@section('title', 'قاعده جدید - مرکز ارتباطات')
@section('page-title', 'قاعده ارتباطی جدید')
@section('page-description', 'تعریف قاعده رویدادمحور، زمان‌بندی‌شده یا شرطی')
@section('content')
@php($labels = \App\Support\Communication\CommunicationAdminLabels::class)
<div class="container mx-auto max-w-4xl px-4 py-6">
    @if($errors->any())<div class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('admin.communications.automations.store') }}" class="space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        @csrf
        <div class="grid gap-4 md:grid-cols-2">
            <div><label class="mb-1 block text-sm">کلید فنی قاعده</label><input name="key" value="{{ old('key') }}" class="w-full rounded-lg border-gray-300 dark:bg-gray-900" required><p class="mt-1 text-xs text-gray-500">برای شناسایی داخلی؛ مانند system.weekly.notice.</p></div>
            <div><label class="mb-1 block text-sm">نام نمایشی</label><input name="name" value="{{ old('name') }}" class="w-full rounded-lg border-gray-300 dark:bg-gray-900" required></div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm">نوع اجرا</label>
                <select name="trigger_type" class="w-full rounded-lg border-gray-300 dark:bg-gray-900" required>
                    @foreach(['event', 'scheduled', 'conditional'] as $type)
                        <option value="{{ $type }}" @selected(old('trigger_type', 'event') === $type)>{{ $labels::trigger($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm">مخاطبان</label>
                <select name="audience_key" class="w-full rounded-lg border-gray-300 dark:bg-gray-900" required>
                    @foreach($audienceKeys as $key)<option value="{{ $key }}" @selected(old('audience_key') === $key)>{{ $labels::audience($key) }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm">شرط اجرا</label>
                <select name="condition_key" class="w-full rounded-lg border-gray-300 dark:bg-gray-900">
                    <option value="">بدون شرط</option>
                    @foreach($conditionKeys as $key)<option value="{{ $key }}" @selected(old('condition_key') === $key)>{{ $labels::condition($key) }}</option>@endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm">شناسه کاربر مشخص</label>
            <input type="number" min="1" name="user_ids[]" value="{{ old('user_ids.0') }}" class="w-full rounded-lg border-gray-300 dark:bg-gray-900" placeholder="فقط هنگام انتخاب «کاربر مشخص»">
            <p class="mt-1 text-xs text-gray-500">برای مخاطب «کاربر مشخص» حداقل یک شناسه کاربر وارد کنید؛ برای سایر مخاطبان این بخش را خالی بگذارید.</p>
        </div>

        <div>
            <label class="mb-1 block text-sm">کلید رویداد</label>
            <input name="event_key" value="{{ old('event_key') }}" class="w-full rounded-lg border-gray-300 dark:bg-gray-900" placeholder="فقط برای اجرای رویدادمحور">
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm">قالب پیام</label>
                <select name="communication_template_id" class="w-full rounded-lg border-gray-300 dark:bg-gray-900" required>
                    @foreach($templates as $template)<option value="{{ $template->id }}" @selected((string) old('communication_template_id') === (string) $template->id)>{{ $template->name }} — {{ $template->key }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm">فرستنده</label>
                <select name="communication_sender_identity_id" class="w-full rounded-lg border-gray-300 dark:bg-gray-900">
                    <option value="">فرستنده پیش‌فرض قالب</option>
                    @foreach($senders as $sender)<option value="{{ $sender->id }}" @selected((string) old('communication_sender_identity_id') === (string) $sender->id)>{{ $sender->display_name }} — {{ $sender->email }}</option>@endforeach
                </select>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm">طبقه‌بندی پیام</label>
                <select name="classification" class="w-full rounded-lg border-gray-300 dark:bg-gray-900">
                    @foreach(['required', 'operational', 'optional'] as $classification)<option value="{{ $classification }}" @selected(old('classification', 'operational') === $classification)>{{ $labels::classification($classification) }}</option>@endforeach
                </select>
            </div>
            <div><label class="mb-1 block text-sm">اولویت</label><input type="number" min="1" max="9" name="priority" value="{{ old('priority', 2) }}" class="w-full rounded-lg border-gray-300 dark:bg-gray-900"></div>
            <div><label class="mb-1 block text-sm">تأخیر اجرا (ثانیه)</label><input type="number" min="0" name="delay_seconds" value="{{ old('delay_seconds', 0) }}" class="w-full rounded-lg border-gray-300 dark:bg-gray-900"></div>
        </div>

        <fieldset class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <legend class="px-2 text-sm font-medium">زمان‌بندی — فقط برای اجرای زمان‌بندی‌شده</legend>
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm">تناوب</label>
                    <select name="frequency" class="w-full rounded-lg border-gray-300 dark:bg-gray-900">
                        @foreach(['weekly', 'daily', 'hourly'] as $frequency)<option value="{{ $frequency }}" @selected(old('frequency', 'weekly') === $frequency)>{{ $labels::frequency($frequency) }}</option>@endforeach
                    </select>
                </div>
                <div><label class="mb-1 block text-sm">فاصله اجرا</label><input type="number" min="1" name="schedule_definition[interval]" value="{{ old('schedule_definition.interval', 1) }}" class="w-full rounded-lg border-gray-300 dark:bg-gray-900"><p class="mt-1 text-xs text-gray-500">مثلاً عدد ۱ یعنی هر یک دوره از تناوب انتخاب‌شده.</p></div>
                <div><label class="mb-1 block text-sm">منطقه زمانی</label><input name="timezone" value="{{ old('timezone', 'Asia/Tehran') }}" class="w-full rounded-lg border-gray-300 dark:bg-gray-900"></div>
            </div>
        </fieldset>

        <input type="hidden" name="is_active" value="0">
        <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active'))> قاعده پس از ایجاد فعال شود</label>
        <div class="flex gap-2"><button class="rounded-lg bg-gray-900 px-4 py-2 text-white">ایجاد قاعده</button><a href="{{ route('admin.communications.automations.index') }}" class="rounded-lg border border-gray-300 px-4 py-2">انصراف</a></div>
    </form>
</div>
@endsection
