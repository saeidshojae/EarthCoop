@extends('layouts.unified')

@section('title', 'دعوت از دوستان - ' . config('app.name', 'EarthCoop'))

@section('content')
<div class="container mx-auto flex flex-col lg:flex-row gap-8 p-6 md:p-8" dir="rtl">
    @include('partials.sidebar-unified')

    <main class="flex-grow min-w-0">
        <section class="bg-white border border-slate-100 rounded-3xl shadow-sm p-5 md:p-8 space-y-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-slate-800">دعوت از دوستان</h1>
                <p class="mt-2 text-slate-600 leading-8">
                    عضویت مستقیم در EarthCoop با دعوت یک عضو واجد شرایط انجام می‌شود. دعوت، یک فعالیت مشارکتی است؛ بنابراین صدور دعوت برای اعضایی فعال است که حساب نجم بهار خود را ایجاد کرده و حق عضویت دوره جاری را پرداخته‌اند.
                </p>
            </div>

            @if(session('success'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-red-700">{{ session('error') }}</div>
            @endif
            @if(session('info'))
                <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-blue-800">{{ session('info') }}</div>
            @endif

            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 p-5">
                    <div class="text-sm text-slate-500">سهمیه دعوت موفق</div>
                    <div class="mt-2 text-2xl font-bold text-slate-800">{{ $successfulInvitations }} / {{ $quota }}</div>
                    <div class="mt-2 text-sm text-slate-500">{{ $remainingSlots }} سهمیه قابل استفاده باقی مانده است.</div>
                </div>
                <div class="rounded-2xl border border-slate-200 p-5">
                    <div class="text-sm text-slate-500">اعتبار هر کد</div>
                    <div class="mt-2 text-2xl font-bold text-slate-800">{{ $expiryHours }} ساعت</div>
                    <div class="mt-2 text-sm text-slate-500">کد منقضی یا دعوت رهاشده، سهمیه موفق شما را برای همیشه مصرف نمی‌کند.</div>
                </div>
                <div class="rounded-2xl border border-slate-200 p-5">
                    <div class="text-sm text-slate-500">پاداش هر دعوت موفق</div>
                    <div class="mt-2 text-2xl font-bold text-slate-800">{{ $rewardPoints }} امتیاز مشارکت</div>
                    <div class="mt-2 text-sm text-slate-500">پس از تکمیل ثبت‌نام دعوت‌شده ثبت می‌شود و طبق قواعد جاری Reputation قابل تبدیل است.</div>
                </div>
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-950 leading-8">
                <strong>دعوت موفق چه زمانی ثبت می‌شود؟</strong>
                <p class="mt-1 mb-0">وقتی فرد دعوت‌شده مراحل الزامی ثبت‌نام و تکمیل پروفایل را در مهلت معتبر دعوت کامل کند. پاداش از دارایی دعوت‌شده برداشت نمی‌شود؛ امتیاز مشارکت برای دعوت‌کننده ثبت می‌شود.</p>
            </div>

            @if($participationStatus === \App\Services\MembershipParticipationEligibilityService::NO_NAJM_BAHAR_ACCOUNT)
                <div class="rounded-3xl border border-blue-200 bg-blue-50 p-6">
                    <h2 class="text-xl font-bold text-blue-950">ابتدا حساب نجم بهار را فعال کنید</h2>
                    <p class="mt-2 text-blue-900 leading-8">برای ورود به فعالیت‌های مشارکتی EarthCoop، ابتدا توافقنامه مالی نجم بهار را مطالعه و تأیید کنید تا حساب اصلی شما ایجاد شود.</p>
                    <a href="{{ route('najm-bahar.agreement') }}" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-blue-700 px-5 py-3 text-white font-bold no-underline">
                        <i class="fas fa-file-signature"></i> مشاهده و تأیید توافقنامه مالی
                    </a>
                </div>
            @elseif($participationStatus === \App\Services\MembershipParticipationEligibilityService::MEMBERSHIP_FEE_DUE)
                <div class="rounded-3xl border border-amber-200 bg-amber-50 p-6">
                    <h2 class="text-xl font-bold text-amber-950">حق عضویت دوره جاری هنوز پرداخت نشده است</h2>
                    <p class="mt-2 text-amber-900 leading-8">پرداخت حق عضویت نخستین اقدام مشارکت آگاهانه در EarthCoop است. پس از پرداخت، امکان ساخت و ارسال دعوت‌نامه برای شما فعال می‌شود.</p>
                    <a href="{{ route('najm-bahar.dashboard') }}" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-amber-600 px-5 py-3 text-white font-bold no-underline">
                        <i class="fas fa-wallet"></i> رفتن به نجم بهار و پرداخت حق عضویت
                    </a>
                </div>
            @else
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 rounded-3xl border border-emerald-200 bg-emerald-50 p-5">
                    <div>
                        <h2 class="font-bold text-emerald-950">حساب مشارکتی شما فعال است</h2>
                        <p class="mt-1 text-emerald-800 mb-0">{{ $occupiedSlots }} سهمیه در حال استفاده یا تکمیل‌شده است و {{ $remainingSlots }} سهمیه آزاد دارید.</p>
                    </div>
                    @if($canIssueInvitation)
                        <form method="POST" action="{{ route('profile.member-invitations.store') }}" class="m-0">
                            @csrf
                            <button type="submit" class="inline-flex justify-center items-center gap-2 rounded-xl bg-emerald-700 px-5 py-3 text-white font-bold border-0 cursor-pointer">
                                <i class="fas fa-plus-circle"></i> ساخت کد دعوت جدید
                            </button>
                        </form>
                    @else
                        <span class="inline-flex justify-center items-center rounded-xl bg-slate-200 px-5 py-3 text-slate-600 font-bold">فعلاً سهمیه آزاد ندارید</span>
                    @endif
                </div>
            @endif

            <div>
                <h2 class="text-xl font-bold text-slate-800 mb-4">کدهای دعوت شما</h2>
                <div class="overflow-x-auto rounded-2xl border border-slate-200">
                    <table class="w-full min-w-[720px] text-center">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="p-3">کد</th>
                                <th class="p-3">وضعیت</th>
                                <th class="p-3">ایجاد</th>
                                <th class="p-3">انقضا</th>
                                <th class="p-3">اشتراک‌گذاری</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($codes as $code)
                                @php
                                    $expired = $code->expire_at && $code->expire_at->lt(now()) && !$code->completed_at;
                                @endphp
                                <tr class="border-t border-slate-100">
                                    <td class="p-3 font-mono font-bold">{{ $code->code }}</td>
                                    <td class="p-3">
                                        @if($code->completed_at)
                                            <span class="text-emerald-700 font-bold">دعوت موفق</span>
                                        @elseif($expired)
                                            <span class="text-slate-500">منقضی‌شده</span>
                                        @elseif($code->used)
                                            <span class="text-blue-700">ثبت‌نام در جریان</span>
                                        @else
                                            <span class="text-amber-700">آماده استفاده</span>
                                        @endif
                                    </td>
                                    <td class="p-3">{{ verta($code->created_at)->format('Y/m/d H:i') }}</td>
                                    <td class="p-3">{{ $code->expire_at ? verta($code->expire_at)->format('Y/m/d H:i') : '-' }}</td>
                                    <td class="p-3">
                                        @if(!$code->used && !$expired)
                                            <button type="button" onclick="shareInviteCode('{{ $code->code }}')" class="rounded-lg border border-blue-200 px-3 py-2 text-blue-700 bg-white">
                                                <i class="fas fa-share-alt"></i> اشتراک
                                            </button>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="p-8 text-slate-500">هنوز کد دعوتی ایجاد نکرده‌اید.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>
@endsection

@push('scripts')
<script>
function shareInviteCode(code) {
    const url = new URL(@json(route('register.form')), window.location.origin);
    url.searchParams.set('invitation_code', code);
    const text = `برای پیوستن به EarthCoop از کد دعوت ${code} استفاده کنید:\n${url.toString()}`;

    if (navigator.share) {
        navigator.share({ title: 'دعوت به EarthCoop', text: text, url: url.toString() }).catch(() => {});
        return;
    }

    navigator.clipboard.writeText(text).then(() => alert('لینک و کد دعوت کپی شد.'));
}
</script>
@endpush