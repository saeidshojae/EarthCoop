@extends('layouts.admin')

@section('title', 'بازبینی و بازشماری انتخابات')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">بازبینی و بازشماری</h1>
            <p class="text-sm text-slate-500 mt-1">رسیدگی انسانی، توقف موقت و تصمیم مستدل روی پرونده‌های انتخاباتی</p>
        </div>
        <a href="{{ route('admin.elections.dashboard') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-600"><i class="fas fa-arrow-right"></i> بازگشت به مدیریت انتخابات</a>
    </div>

    @if(session('success'))
        <div class="mb-5 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="mb-5 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="flex flex-wrap gap-2 mb-5">
        @foreach([''=>'همه','not_requested'=>'خودکار','requested'=>'درخواست انسانی','in_review'=>'در حال بررسی','decided'=>'مختومه','expired'=>'منقضی'] as $key=>$label)
            <a href="{{ route('admin.elections.reviews', $key === '' ? [] : ['status'=>$key]) }}" class="px-3 py-2 rounded-lg text-sm {{ $status === $key ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="space-y-4">
        @forelse($reviews as $review)
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-5 shadow-sm">
                <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-4">
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-bold text-slate-900 dark:text-white">پرونده #{{ $review->id }}</span>
                            <span class="px-2.5 py-1 rounded-full text-xs bg-slate-100 dark:bg-slate-700">{{ $review->human_status }}</span>
                            <span class="px-2.5 py-1 rounded-full text-xs bg-indigo-50 text-indigo-700">{{ $review->ground }}</span>
                        </div>
                        <div class="text-sm text-slate-600 dark:text-slate-300">
                            انتخابات #{{ $review->election_id }} · {{ optional(optional($review->election)->group)->name ?: 'گروه نامشخص' }}
                        </div>
                        <div class="text-sm text-slate-500">درخواست‌کننده: {{ optional($review->requester)->name ?: '#'.$review->requester_user_id }} @if($review->subject_user_id) · موضوع: {{ optional($review->subject)->name ?: '#'.$review->subject_user_id }} @endif</div>
                        <div class="text-xs text-slate-400">رویداد: {{ $review->challenged_event }} #{{ $review->challenged_event_id }} · حمایت‌ها: {{ $review->support_count }}</div>
                        @if($review->decision_due_at)<div class="text-xs {{ $review->decision_due_at->isPast() && !$review->decided_at ? 'text-rose-600 font-semibold' : 'text-slate-400' }}">مهلت تصمیم: {{ $review->decision_due_at->format('Y-m-d H:i') }}</div>@endif
                        @if($review->statement)<div class="mt-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-900 text-sm text-slate-700 dark:text-slate-300">{{ $review->statement }}</div>@endif
                        @if($review->decision)<div class="mt-3 text-sm"><strong>تصمیم:</strong> {{ $review->decision }} — {{ $review->decision_reason }}</div>@endif
                    </div>

                    @if(!in_array($review->human_status, ['decided','expired'], true))
                    <div class="xl:w-[440px] grid grid-cols-1 md:grid-cols-2 gap-3">
                        <form method="POST" action="{{ route('admin.elections.reviews.stay', $review) }}" class="border border-slate-200 dark:border-slate-700 rounded-xl p-3">@csrf
                            <div class="text-sm font-bold mb-2">توقف موقت</div>
                            <textarea name="reason" rows="3" required class="w-full border rounded-lg p-2 text-sm dark:bg-slate-900" placeholder="دلیل روشن و قابل ممیزی"></textarea>
                            <button class="mt-2 w-full px-3 py-2 bg-amber-500 text-white rounded-lg text-sm font-semibold">اعمال stay</button>
                        </form>
                        <form method="POST" action="{{ route('admin.elections.reviews.decision', $review) }}" class="border border-slate-200 dark:border-slate-700 rounded-xl p-3">@csrf
                            <div class="text-sm font-bold mb-2">تصمیم نهایی</div>
                            <select name="decision" required class="w-full border rounded-lg p-2 text-sm dark:bg-slate-900 mb-2"><option value="upheld">تأیید نتیجه</option><option value="corrected">اصلاح</option><option value="dismissed">رد اعتراض</option></select>
                            <textarea name="reason" rows="2" required class="w-full border rounded-lg p-2 text-sm dark:bg-slate-900" placeholder="استدلال تصمیم"></textarea>
                            <input name="remediation_reference" class="mt-2 w-full border rounded-lg p-2 text-sm dark:bg-slate-900" placeholder="مرجع اقدام اصلاحی (در صورت نیاز)">
                            <button class="mt-2 w-full px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold">ثبت تصمیم</button>
                        </form>
                    </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-slate-800 border rounded-2xl p-10 text-center text-slate-400">پرونده‌ای در این وضعیت وجود ندارد.</div>
        @endforelse
    </div>

    <div class="mt-5">{{ $reviews->links() }}</div>
</div>
@endsection
