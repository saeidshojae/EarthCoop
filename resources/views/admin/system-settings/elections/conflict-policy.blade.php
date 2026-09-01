@extends('layouts.admin')

@section('title', 'ماتریس تعارض مسئولیت انتخاباتی')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <h1 class="text-2xl font-bold mb-2">ماتریس نسخه‌دار تعارض مسئولیت</h1>
    <p class="text-slate-600 mb-6">نسخه مؤثر v{{ $current->version }}. تغییر هر خانه یک نسخه جدید می‌سازد و فقط بر تصمیم‌های آینده اثر دارد.</p>
    @if(session('success'))<div class="mb-4 rounded-xl bg-green-50 border border-green-200 p-4">{{ session('success') }}</div>@endif

    <form method="POST" action="{{ route('admin.elections.conflict-policy.store') }}" class="bg-white dark:bg-slate-800 rounded-xl border p-5 mb-6">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            @php($domains=['public','job','experience','age','gender'])
            @php($positions=['manager','inspector'])
            <select name="current_position" required>@foreach($positions as $v)<option value="{{ $v }}">سمت فعلی: {{ $v }}</option>@endforeach</select>
            <select name="current_domain_type" required>@foreach($domains as $v)<option value="{{ $v }}">نوع فعلی: {{ $v }}</option>@endforeach</select>
            <select name="current_level" required>@foreach($levels as $v)<option value="{{ $v }}">سطح فعلی: {{ $v }}</option>@endforeach</select>
            <select name="new_position" required>@foreach($positions as $v)<option value="{{ $v }}">سمت جدید: {{ $v }}</option>@endforeach</select>
            <select name="new_domain_type" required>@foreach($domains as $v)<option value="{{ $v }}">نوع جدید: {{ $v }}</option>@endforeach</select>
            <select name="new_level" required>@foreach($levels as $v)<option value="{{ $v }}">سطح جدید: {{ $v }}</option>@endforeach</select>
            <select name="decision" required>
                <option value="allowed">مجاز</option>
                <option value="forbidden">ممنوع</option>
                <option value="allowed_with_suspension">مجاز با تعلیق سمت قبلی</option>
            </select>
            <input name="rule_reason" maxlength="500" placeholder="توضیح این قاعده">
            <input name="effective_at" type="datetime-local" placeholder="زمان اثر">
            <input name="change_reason" maxlength="500" required placeholder="دلیل انتشار نسخه جدید">
        </div>
        <button class="mt-4 px-4 py-2 rounded-lg bg-blue-600 text-white">انتشار نسخه جدید با این خانه ماتریس</button>
    </form>

    <div class="bg-white dark:bg-slate-800 rounded-xl border overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead><tr><th class="p-3">فعلی</th><th class="p-3">جدید</th><th class="p-3">تصمیم</th><th class="p-3">دلیل</th></tr></thead>
            <tbody>
            @foreach($current->rules->sortBy(['current_domain_type','current_level','new_domain_type','new_level']) as $rule)
                <tr class="border-t">
                    <td class="p-3">{{ $rule->current_position }} / {{ $rule->current_domain_type }} / {{ $rule->current_level }}</td>
                    <td class="p-3">{{ $rule->new_position }} / {{ $rule->new_domain_type }} / {{ $rule->new_level }}</td>
                    <td class="p-3 font-semibold">{{ $rule->decision }}</td>
                    <td class="p-3">{{ $rule->reason }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-5 text-sm text-slate-500">تاریخچه نسخه‌ها: @foreach($versions as $v) v{{ $v->version }} ({{ optional($v->effective_at)->format('Y-m-d H:i') }}) @if(!$loop->last) — @endif @endforeach</div>
</div>
@endsection
