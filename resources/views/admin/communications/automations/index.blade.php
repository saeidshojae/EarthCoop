@extends('layouts.admin')

@section('title', 'اتوماسیون‌ها - مرکز ارتباطات')
@section('page-title', 'قواعد و اتوماسیون‌های ارتباطی')
@section('page-description', 'قواعد رویدادمحور، زمان‌بندی‌شده و شرطی')

@section('content')
@php($labels = \App\Support\Communication\CommunicationAdminLabels::class)
<div class="container mx-auto px-4 py-6 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">قواعد و اتوماسیون‌ها</h1>
            <p class="mt-1 text-sm text-gray-500">تعریف‌ها فقط از فهرست‌های ثبت‌شدهٔ مخاطبان و شروط استفاده می‌کنند.</p>
        </div>
        <div class="flex gap-2">
            @if(auth()->user()?->hasPermission('communications.rules.manage'))<a href="{{ route('admin.communications.automations.create') }}" class="rounded-lg bg-gray-900 px-3 py-2 text-sm text-white">قاعده جدید</a>@endif
            <a href="{{ route('admin.communications.index') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600">بازگشت</a>
        </div>
    </div>

    @if(session('success'))<div class="rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>@endif

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr class="text-right text-xs text-gray-500"><th class="px-4 py-3">کلید فنی</th><th class="px-4 py-3">نوع اجرا</th><th class="px-4 py-3">مخاطبان</th><th class="px-4 py-3">قالب پیام</th><th class="px-4 py-3">زمان‌بندی</th><th class="px-4 py-3">وضعیت</th><th class="px-4 py-3">عملیات</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($rules as $rule)
                        @php($audienceKey = data_get($rule->audience_definition, 'key'))
                        <tr class="text-sm">
                            <td class="px-4 py-3 font-mono text-xs">{{ $rule->key }}</td>
                            <td class="px-4 py-3">{{ $labels::trigger($rule->trigger_type) }}</td>
                            <td class="px-4 py-3">
                                <div>{{ $labels::audience($audienceKey) }}</div>
                                @if($audienceKey === 'specific.user' && data_get($rule->audience_definition, 'user_ids'))<div class="mt-1 text-xs text-gray-500">شناسه‌ها: {{ implode('، ', data_get($rule->audience_definition, 'user_ids', [])) }}</div>@endif
                            </td>
                            <td class="px-4 py-3"><div>{{ $rule->template?->name ?? '—' }}</div>@if($rule->template?->key)<div class="mt-1 font-mono text-xs text-gray-500">{{ $rule->template->key }}</div>@endif</td>
                            <td class="px-4 py-3">@if($rule->schedule)<div>{{ $labels::frequency($rule->schedule->frequency) }}</div><div class="mt-1 text-xs text-gray-500">اجرای بعدی: @if($rule->schedule->next_run_at)<x-temporal.date-time :value="$rule->schedule->next_run_at" :timezone="$rule->schedule->timezone" />@else — @endif</div><div class="mt-1 text-[11px] text-gray-400">منطقه زمانی: {{ $rule->schedule->timezone ?: config('app.timezone', 'Asia/Tehran') }}</div>@else — @endif</td>
                            <td class="px-4 py-3">{{ $rule->is_active ? 'فعال' : 'غیرفعال' }}</td>
                            <td class="px-4 py-3">@if($rule->is_active && auth()->user()?->hasPermission('communications.rules.manage'))<form method="POST" action="{{ route('admin.communications.automations.deactivate', $rule) }}">@csrf<button type="submit" class="rounded-lg border border-red-300 px-3 py-1 text-xs text-red-700 hover:bg-red-50">غیرفعال‌کردن</button></form>@else<span class="text-gray-400">—</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">قاعده‌ای ثبت نشده است.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($rules->hasPages())<div class="border-t px-4 py-3">{{ $rules->links() }}</div>@endif
    </div>
</div>
@endsection
