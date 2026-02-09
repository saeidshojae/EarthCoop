@extends('layouts.admin')

@section('title', 'تنظیمات امتیازدهی')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">مدیریت قواعد امتیازدهی</h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">در این صفحه می‌توانید وزن امتیازی رویدادهای سیستم را ویرایش کنید.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 px-4 py-3 rounded-lg mb-6">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.reputation.update') }}">
        @csrf
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="p-6 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">قواعد فعلی</h3>
            </div>

            <div class="p-6">
                <div class="mb-4">
                    <div class="flex space-x-2 rtl:space-x-reverse" role="tablist">
                        @foreach($grouped as $gKey => $g)
                            @if(count($g['rules']) > 0)
                                <button type="button" class="tab-btn px-3 py-2 rounded-md text-sm border" data-tab="{{ $gKey }}">{{ $g['label'] }} ({{ count($g['rules']) }})</button>
                            @endif
                        @endforeach
                        @if(count($grouped['other']['rules']) > 0)
                            <button type="button" class="tab-btn px-3 py-2 rounded-md text-sm border" data-tab="other">{{ $grouped['other']['label'] }} ({{ count($grouped['other']['rules']) }})</button>
                        @endif
                    </div>
                </div>

                @foreach($grouped as $gKey => $g)
                    <div class="tab-panel mb-6" data-panel="{{ $gKey }}" style="display: none;">
                        @if(count($g['rules']) === 0)
                            <div class="text-slate-500">موردی برای نمایش وجود ندارد.</div>
                        @else
                        <table class="w-full text-right">
                            <thead>
                                <tr class="text-sm text-slate-600 dark:text-slate-300">
                                    <th class="py-2">#</th>
                                    <th class="py-2">کلید</th>
                                    <th class="py-2">عنوان</th>
                                    <th class="py-2">عنوان (فارسی)</th>
                                    <th class="py-2">وزن (امتیاز)</th>
                                    <th class="py-2">سقف روزانه</th>
                                    <th class="py-2">فعال</th>
                                    <th class="py-2">توضیحات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($g['rules'] as $i => $rule)
                                <tr class="border-t border-slate-100 dark:border-slate-700">
                                    <td class="py-3">{{ $i + 1 }}</td>
                                    <td class="py-3 font-mono">{{ $rule->key }}</td>
                                    <td class="py-3">{{ $rule->label }}</td>
                                    <td class="py-3 text-slate-500">{{ $faLabels[$rule->key] ?? '-' }}</td>
                                    <td class="py-3">
                                        <input type="number" name="weights[{{ $rule->key }}]" value="{{ $rule->weight }}" class="px-3 py-2 border rounded-md w-28">
                                    </td>
                                    <td class="py-3">
                                        <input type="number" name="daily_cap[{{ $rule->key }}]" value="{{ $rule->daily_cap ?? '' }}" class="px-3 py-2 border rounded-md w-28" placeholder="بدون سقف">
                                    </td>
                                    <td class="py-3">
                                        <input type="checkbox" name="active[{{ $rule->key }}]" value="1" {{ $rule->active ? 'checked' : '' }}>
                                    </td>
                                    <td class="py-3">
                                        <input type="text" name="description[{{ $rule->key }}]" value="{{ $rule->description }}" class="px-3 py-2 border rounded-md w-full">
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="p-6 border-t border-slate-100 dark:border-slate-700 text-left">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">
                    ذخیره تغییرات
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    (function(){
        // simple tab behavior
        const buttons = document.querySelectorAll('.tab-btn');
        const panels = document.querySelectorAll('.tab-panel');
        function showTab(key){
            panels.forEach(p => p.style.display = (p.getAttribute('data-panel') === key) ? '' : 'none');
            buttons.forEach(b => b.classList.remove('bg-emerald-600','text-white'));
            document.querySelectorAll('.tab-btn[data-tab="'+key+'"]').forEach(b=>{ b.classList.add('bg-emerald-600','text-white'); });
        }
        if(buttons.length){
            // activate first visible tab
            let first = buttons[0].getAttribute('data-tab');
            showTab(first);
            buttons.forEach(b => b.addEventListener('click', () => showTab(b.getAttribute('data-tab'))));
        }
    })();
</script>

@endsection
