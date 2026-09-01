@extends('layouts.admin')
@section('title','Override سیاست چرخه انتخابات')
@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
<h1 class="text-2xl font-bold mb-2">Override صریح سیاست چرخه #{{ $election->id }}</h1>
<p class="text-slate-600 mb-5">سیاست جاری: v{{ $election->policyVersion->version }}. تغییر عادی تنظیمات این چرخه را تغییر نمی‌دهد؛ این صفحه فقط برای override استثنایی و دلیل‌دار است.</p>
@if(session('success'))<div class="p-4 mb-4 bg-green-50 border rounded">{{ session('success') }}</div>@endif
<div class="p-4 mb-5 bg-amber-50 border border-amber-300 rounded">این اقدام روی چرخه در جریان اثر مستقیم دارد و در audit immutable ثبت می‌شود. از آن برای ویرایش عادی تنظیمات استفاده نکنید.</div>
<form method="POST" action="{{ route('admin.elections.policy-override.update',$election) }}" class="bg-white dark:bg-slate-800 border rounded-xl p-5">@csrf
<select name="policy_version_id" required class="border rounded p-2">@foreach($policies as $p)<option value="{{ $p->id }}" @selected($p->id===$election->policy_version_id)>v{{ $p->version }} — {{ $p->change_reason }}</option>@endforeach</select>
<textarea name="reason" required maxlength="1000" rows="4" class="block w-full border rounded mt-4" placeholder="دلیل دقیق و مستند override"></textarea>
<button class="mt-4 px-4 py-2 bg-red-600 text-white rounded">ثبت override چرخه جاری</button></form>
<div class="mt-6 bg-white dark:bg-slate-800 border rounded-xl overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="p-3">زمان</th><th>از</th><th>به</th><th>عامل</th><th>دلیل</th></tr></thead><tbody>@forelse($overrides as $o)<tr class="border-t"><td class="p-3">{{ $o->applied_at }}</td><td>v{{ $o->fromPolicy->version }}</td><td>v{{ $o->toPolicy->version }}</td><td>#{{ $o->actor_user_id }}</td><td>{{ $o->reason }}</td></tr>@empty<tr><td colspan="5" class="p-4">هنوز overrideای ثبت نشده است.</td></tr>@endforelse</tbody></table></div>
</div>
@endsection
