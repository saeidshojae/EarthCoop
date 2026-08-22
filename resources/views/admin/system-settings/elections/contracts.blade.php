@extends('layouts.admin')
@section('title','نسخه‌های قرارداد مسئولیت انتخابات')
@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
<h1 class="text-2xl font-bold mb-2">قراردادهای مدیر و بازرس</h1>
<p class="text-slate-600 mb-5">هر انتشار یک نسخه immutable می‌سازد. هر شش بخش E0 اجباری‌اند.</p>
@if(session('success'))<div class="mb-4 p-4 bg-green-50 border rounded">{{ session('success') }}</div>@endif
<form method="POST" action="{{ route('admin.elections.contracts.store') }}" class="bg-white dark:bg-slate-800 border rounded-xl p-5 space-y-4">@csrf
<select name="position" required><option value="manager">مدیر</option><option value="inspector">بازرس</option></select>
@php($fields=[
'role_scope_and_cycle'=>'سمت، گروه، نوع، سطح، تاریخ شروع و چرخه',
'term_compensation_and_commitment'=>'مدت، حقوق، شیوه پرداخت و تعهد زمانی',
'duties_reporting_and_member_oversight'=>'وظایف، گزارش‌دهی و حق نظارت اعضا',
'conflicts_confidentiality_and_vote_integrity'=>'تعارض منافع، محرمانگی، منع سوءاستفاده و فشار/خرید رأی',
'resignation_suspension_disqualification_and_succession'=>'استعفا، تعلیق، سلب صلاحیت و جانشینی',
'complaint_reply_review_and_acceptance_audit'=>'شکایت، حق پاسخ، مرجع بازبینی و ممیزی پذیرش'])
@foreach($fields as $key=>$label)<label class="block"><span class="font-semibold">{{ $label }}</span><textarea name="{{ $key }}" required rows="4" class="w-full border rounded mt-1"></textarea></label>@endforeach
<input name="change_reason" required maxlength="500" class="w-full border rounded" placeholder="دلیل انتشار نسخه جدید">
<button class="px-4 py-2 bg-blue-600 text-white rounded">انتشار نسخه جدید</button></form>
<div class="mt-6 bg-white dark:bg-slate-800 border rounded-xl overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="p-3">نقش</th><th>نسخه</th><th>وضعیت E0</th><th>انتشار</th><th>دلیل</th></tr></thead><tbody>@foreach($contracts as $c)<tr class="border-t"><td class="p-3">{{ $c->position }}</td><td>v{{ $c->version }}</td><td>{{ $c->e0_compliant && $c->hasCompleteE0Manifest() ? 'کامل' : 'legacy/unverified' }}</td><td>{{ optional($c->published_at)->format('Y-m-d H:i') }}</td><td>{{ $c->change_reason }}</td></tr>@endforeach</tbody></table></div>
</div>
@endsection
