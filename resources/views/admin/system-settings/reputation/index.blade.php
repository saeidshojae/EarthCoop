@extends('layouts.admin')

@section('title', 'تنظیمات امتیازدهی')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">مدیریت قواعد امتیازدهی</h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">سیاست امتیازدهی، ابعاد اعتبار و قابلیت تبدیل را مدیریت کنید؛ داده‌های ممیزی پایین صفحه فقط‌خواندنی هستند.</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">کنترل جلوگیری از تکرار امتیاز بر پایه هویت رویداد پایدار در خود سامانه اعمال می‌شود و از این صفحه قابل تغییر نیست.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 px-4 py-3 rounded-lg mb-6">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.reputation.update') }}">
        @csrf
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="p-6 border-b border-slate-200 dark:border-slate-700"><h3 class="text-lg font-semibold text-slate-900 dark:text-white">قواعد فعلی</h3></div>
            <div class="p-6">
                <div class="mb-4 overflow-x-auto">
                    <div class="flex space-x-2 rtl:space-x-reverse min-w-max" role="tablist">
                        @foreach($grouped as $gKey => $g)
                            @if(count($g['rules']) > 0)
                                <button type="button" class="tab-btn px-3 py-2 rounded-md text-sm border" data-tab="{{ $gKey }}">{{ $g['label'] }} ({{ count($g['rules']) }})</button>
                            @endif
                        @endforeach
                    </div>
                </div>

                @foreach($grouped as $gKey => $g)
                    <div class="tab-panel mb-6" data-panel="{{ $gKey }}" style="display: none;">
                        @if($gKey === 'archived')
                            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                قواعد این بخش فقط برای سابقه و ممیزی نگهداری می‌شوند و قابل فعال‌سازی یا تبدیل نیستند. حذف فیزیکی آن‌ها می‌تواند رهگیری سوابق تاریخی را ناقص کند.
                            </div>
                        @endif

                        @if(count($g['rules']) === 0)
                            <div class="text-slate-500">موردی برای نمایش وجود ندارد.</div>
                        @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-right min-w-[980px]">
                                <thead>
                                    <tr class="text-sm text-slate-600 dark:text-slate-300">
                                        <th class="py-2">#</th><th class="py-2">کلید فنی</th><th class="py-2">عنوان فارسی</th><th class="py-2">وزن (امتیاز)</th><th class="py-2">سقف روزانه</th><th class="py-2">بُعد</th><th class="py-2">قابل تبدیل</th><th class="py-2">فعال</th><th class="py-2">توضیحات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($g['rules'] as $i => $rule)
                                    @php
                                        $isDeprecated = in_array($rule->key, $deprecatedRuleKeys ?? [], true);
                                    @endphp
                                    <tr class="border-t border-slate-100 dark:border-slate-700 {{ ! $rule->active || $isDeprecated ? 'opacity-70' : '' }}">
                                        <td class="py-3">{{ $i + 1 }}</td>
                                        <td class="py-3 font-mono text-xs" dir="ltr">{{ $rule->key }}</td>
                                        <td class="py-3 text-slate-600 dark:text-slate-300">
                                            {{ $faLabels[$rule->key] ?? 'سایر' }}
                                            @if($isDeprecated)
                                                <div class="mt-1 text-xs text-amber-700 font-semibold">قاعده منسوخ؛ فقط برای سابقه نگهداری می‌شود</div>
                                            @endif
                                        </td>
                                        <td class="py-3"><input type="number" name="weights[{{ $rule->key }}]" value="{{ $rule->weight }}" class="px-3 py-2 border rounded-md w-28" {{ $isDeprecated ? 'readonly' : '' }}></td>
                                        <td class="py-3"><input type="number" name="daily_cap[{{ $rule->key }}]" value="{{ $rule->daily_cap ?? '' }}" class="px-3 py-2 border rounded-md w-28" placeholder="بدون سقف" {{ $isDeprecated ? 'readonly' : '' }}></td>
                                        <td class="py-3">
                                            <select name="dimension[{{ $rule->key }}]" class="px-3 py-2 border rounded-md" {{ $isDeprecated ? 'disabled' : '' }}>
                                                @foreach(['participation' => 'مشارکت','reliability' => 'اعتمادپذیری','expertise' => 'تخصص','civic_trust' => 'اعتماد مدنی'] as $dimension => $label)
                                                    <option value="{{ $dimension }}" {{ $rule->dimension === $dimension ? 'selected' : '' }}>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="py-3"><input type="checkbox" name="convertible[{{ $rule->key }}]" value="1" {{ $rule->convertible ? 'checked' : '' }} {{ $isDeprecated ? 'disabled' : '' }}></td>
                                        <td class="py-3"><input type="checkbox" name="active[{{ $rule->key }}]" value="1" {{ $rule->active ? 'checked' : '' }} {{ $isDeprecated ? 'disabled' : '' }}></td>
                                        <td class="py-3"><input type="text" name="description[{{ $rule->key }}]" value="{{ $rule->description }}" class="px-3 py-2 border rounded-md w-full min-w-64" {{ $isDeprecated ? 'readonly' : '' }}></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="p-6 border-t border-slate-100 dark:border-slate-700 text-left"><button type="submit" class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">ذخیره تغییرات</button></div>
        </div>
    </form>

    <section class="mt-8 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="p-6 border-b border-slate-200 dark:border-slate-700 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
            <div><h2 class="text-lg font-semibold text-slate-900 dark:text-white">ممیزی رویدادهای امتیاز</h2><p class="text-sm text-slate-500 mt-1">۵۰ رویداد اخیر با شناسه پایدار رویداد و تصویر سیاست صدور. شناسه‌های فنی صرفاً برای رهگیری نمایش داده می‌شوند.</p></div>
            <span class="text-xs px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">فقط‌خواندنی</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-right min-w-[1200px] text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/40 text-slate-600 dark:text-slate-300"><tr><th class="px-4 py-3">زمان</th><th class="px-4 py-3">کاربر</th><th class="px-4 py-3">اقدام</th><th class="px-4 py-3">تغییر</th><th class="px-4 py-3">بُعد</th><th class="px-4 py-3">قابل تبدیل هنگام صدور</th><th class="px-4 py-3">مصرف‌شده</th><th class="px-4 py-3">منبع / مرجع</th><th class="px-4 py-3">شناسه رویداد</th></tr></thead>
                <tbody>
                    @forelse($recentPointEvents as $event)
                        <tr class="border-t border-slate-100 dark:border-slate-700 align-top">
                            <td class="px-4 py-3 whitespace-nowrap">{{ optional($event->created_at)->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-3"><div>{{ $event->user?->fullName() ?? ('#' . $event->user_id) }}</div><div class="text-xs text-slate-500">{{ $event->user?->email }}</div></td>
                            <td class="px-4 py-3"><div>{{ $faLabels[$event->action] ?? 'سایر' }}</div><div class="font-mono text-[11px] text-slate-400" dir="ltr">{{ $event->action }}</div></td>
                            <td class="px-4 py-3 font-semibold {{ $event->delta < 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ $event->delta > 0 ? '+' : '' }}{{ number_format($event->delta) }}</td>
                            <td class="px-4 py-3">{{ $dimensionLabels[$event->dimension] ?? 'سابقه قدیمی / سایر' }}</td>
                            <td class="px-4 py-3">{{ $event->convertible ? 'بله' : 'خیر' }}</td>
                            <td class="px-4 py-3">{{ number_format((int) ($event->consumed_points_total ?? 0)) }}</td>
                            <td class="px-4 py-3 text-xs"><div>{{ $event->source ?: '-' }}</div><div class="font-mono text-slate-500" dir="ltr">{{ $event->reference_id ?: '-' }}</div></td>
                            <td class="px-4 py-3 font-mono text-xs break-all max-w-sm" dir="ltr">{{ $event->event_key ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-8 text-center text-slate-500">هنوز رویداد امتیازی ثبت نشده است.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-6 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="p-6 border-b border-slate-200 dark:border-slate-700 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
            <div><h2 class="text-lg font-semibold text-slate-900 dark:text-white">دفتر مصرف و تبدیل مشارکت</h2><p class="text-sm text-slate-500 mt-1">۳۰ درخواست اخیر؛ مصرف دقیق امتیازها با شناسه تبدیل و نسخه سیاست قابل رهگیری است.</p></div>
            <span class="text-xs px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">فقط‌خواندنی</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-right min-w-[1050px] text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/40 text-slate-600 dark:text-slate-300"><tr><th class="px-4 py-3">زمان</th><th class="px-4 py-3">کاربر</th><th class="px-4 py-3">درخواست</th><th class="px-4 py-3">مصرف ثبت‌شده</th><th class="px-4 py-3">بهار فعال</th><th class="px-4 py-3">نسبت</th><th class="px-4 py-3">نسخه سیاست</th><th class="px-4 py-3">وضعیت</th><th class="px-4 py-3">شناسه تبدیل</th></tr></thead>
                <tbody>
                    @forelse($recentConversions as $conversion)
                        <tr class="border-t border-slate-100 dark:border-slate-700 align-top">
                            <td class="px-4 py-3 whitespace-nowrap">{{ optional($conversion->created_at)->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-3 font-mono">#{{ $conversion->user_id }}</td>
                            <td class="px-4 py-3">{{ number_format($conversion->requested_points) }}</td>
                            <td class="px-4 py-3">{{ number_format((int) ($conversion->consumed_points_total ?? $conversion->consumed_points)) }}</td>
                            <td class="px-4 py-3">{{ number_format($conversion->amount_gol) }}</td>
                            <td class="px-4 py-3">{{ number_format($conversion->ratio) }} : 1</td>
                            <td class="px-4 py-3 text-xs">{{ $conversion->policy_version ?: ($conversion->policy_version_id ? '#' . $conversion->policy_version_id : '-') }}</td>
                            <td class="px-4 py-3">{{ $conversionStatusLabels[$conversion->status] ?? 'نامشخص' }}</td>
                            <td class="px-4 py-3 font-mono text-xs break-all max-w-sm" dir="ltr">{{ $conversion->conversion_key }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-8 text-center text-slate-500">هنوز تبدیل امتیازی ثبت نشده است.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
(function(){
    const buttons = document.querySelectorAll('.tab-btn');
    const panels = document.querySelectorAll('.tab-panel');
    function showTab(key){
        panels.forEach(p => p.style.display = (p.getAttribute('data-panel') === key) ? '' : 'none');
        buttons.forEach(b => b.classList.remove('bg-emerald-600','text-white'));
        document.querySelectorAll('.tab-btn[data-tab="'+key+'"]').forEach(b => b.classList.add('bg-emerald-600','text-white'));
    }
    if(buttons.length){
        const first = buttons[0].getAttribute('data-tab');
        showTab(first);
        buttons.forEach(b => b.addEventListener('click', () => showTab(b.getAttribute('data-tab'))));
    }
})();
</script>
@endsection