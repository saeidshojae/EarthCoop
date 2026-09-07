@extends('layouts.unified')

@section('title', 'دعوت از دوستان - ' . config('app.name', 'EarthCoop'))

@push('styles')
<style>
    .invite-page-shell { font-family: 'Vazirmatn', sans-serif; }
    .invite-surface {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 1.5rem;
        box-shadow: 0 12px 34px rgba(15, 23, 42, .06);
    }
    .invite-info-card {
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        background: linear-gradient(145deg, #fff 0%, #f8fafc 100%);
    }
    .invite-code-card {
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        background: #fff;
        box-shadow: 0 4px 16px rgba(15, 23, 42, .05);
    }
    .invite-code-value {
        direction: ltr;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        letter-spacing: .12em;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }
    .invite-toast {
        position: fixed;
        right: 50%;
        bottom: 1.5rem;
        transform: translateX(50%) translateY(1rem);
        opacity: 0;
        pointer-events: none;
        z-index: 9999;
        transition: .2s ease;
    }
    .invite-toast.show {
        opacity: 1;
        transform: translateX(50%) translateY(0);
    }
</style>
@endpush

@section('content')
<div class="invite-page-shell container mx-auto flex flex-col lg:flex-row gap-6 p-4 sm:p-6 md:p-8" dir="rtl">
    @include('partials.sidebar-unified')

    <main class="flex-grow min-w-0">
        <section class="invite-surface p-4 sm:p-6 md:p-8">
            <header class="mb-7 md:mb-9">
                <div class="flex items-start gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <i class="fas fa-user-plus text-xl" aria-hidden="true"></i>
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-800 mb-2">دعوت از دوستان</h1>
                        <p class="text-sm sm:text-base leading-7 text-slate-600 max-w-3xl mb-0">
                            با یک دعوت شخصی و شفاف، کسانی را که می‌شناسید به EarthCoop دعوت کنید. هر کد کوتاه‌مدت و یک‌بارمصرف است و صدور آن برای اعضای واجد شرایط مشارکت فعال می‌شود.
                        </p>
                    </div>
                </div>
            </header>

            @if(session('success'))
                <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 text-sm sm:text-base">
                    <i class="fas fa-check-circle ml-2" aria-hidden="true"></i>{{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm sm:text-base">
                    <i class="fas fa-exclamation-circle ml-2" aria-hidden="true"></i>{{ session('error') }}
                </div>
            @endif
            @if(session('info'))
                <div class="mb-5 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-blue-800 text-sm sm:text-base">
                    <i class="fas fa-info-circle ml-2" aria-hidden="true"></i>{{ session('info') }}
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <article class="invite-info-card p-4 sm:p-5">
                    <div class="flex items-center gap-2 mb-3 text-emerald-700 font-bold">
                        <i class="fas fa-ticket-alt" aria-hidden="true"></i>
                        <h2 class="m-0">سهمیه دعوت موفق</h2>
                    </div>
                    <div class="text-2xl font-extrabold text-slate-800 mb-2">{{ $successfulInvitations }} / {{ $quota }}</div>
                    <p class="text-sm leading-7 text-slate-600 mb-0">{{ $remainingSlots }} سهمیه قابل استفاده باقی مانده است.</p>
                </article>

                <article class="invite-info-card p-4 sm:p-5">
                    <div class="flex items-center gap-2 mb-3 text-blue-700 font-bold">
                        <i class="fas fa-clock" aria-hidden="true"></i>
                        <h2 class="m-0">اعتبار هر کد</h2>
                    </div>
                    <div class="text-2xl font-extrabold text-slate-800 mb-2">{{ $expiryHours }} ساعت</div>
                    <p class="text-sm leading-7 text-slate-600 mb-0">کد منقضی یا دعوت رهاشده، سهمیه موفق شما را برای همیشه مصرف نمی‌کند.</p>
                </article>

                <article class="invite-info-card p-4 sm:p-5">
                    <div class="flex items-center gap-2 mb-3 text-amber-600 font-bold">
                        <i class="fas fa-award" aria-hidden="true"></i>
                        <h2 class="m-0">اعتبار مشارکت دعوت</h2>
                    </div>
                    <div class="text-2xl font-extrabold text-slate-800 mb-2">{{ $rewardPoints }} امتیاز</div>
                    <p class="text-sm leading-7 text-slate-600 mb-2">دعوت موفق طبق قواعد جاری سامانه برای دعوت‌کننده اعتبار مشارکت ثبت می‌کند.</p>
                    <a href="{{ route('participation.credit-regulation') }}" class="inline-flex items-center gap-2 text-sm font-bold text-emerald-700 hover:text-emerald-800 no-underline">
                        مشاهده نظام‌نامه اعتبارات مشارکت
                        <i class="fas fa-arrow-left text-xs" aria-hidden="true"></i>
                    </a>
                </article>
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:p-5 text-amber-950 leading-8 mb-6">
                <strong class="block mb-1">دعوت موفق چه زمانی ثبت می‌شود؟</strong>
                <p class="mb-0 text-sm sm:text-base">وقتی فرد دعوت‌شده مراحل الزامی ثبت‌نام و تکمیل پروفایل را در مهلت معتبر دعوت کامل کند. پاداش از دارایی دعوت‌شده برداشت نمی‌شود؛ امتیاز مشارکت برای دعوت‌کننده ثبت می‌شود.</p>
            </div>

            @if($participationStatus === \App\Services\MembershipParticipationEligibilityService::NO_NAJM_BAHAR_ACCOUNT)
                <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5 sm:p-6 mb-8">
                    <h2 class="text-lg sm:text-xl font-extrabold text-blue-950 mb-2">ابتدا حساب نجم بهار را فعال کنید</h2>
                    <p class="text-sm sm:text-base text-blue-900 leading-7 mb-4">برای ورود به فعالیت‌های مشارکتی EarthCoop، ابتدا توافقنامه مالی نجم بهار را مطالعه و تأیید کنید تا حساب اصلی شما ایجاد شود.</p>
                    <a href="{{ route('najm-bahar.agreement') }}" class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-full bg-blue-700 px-7 py-3.5 text-white font-bold no-underline">
                        <i class="fas fa-file-signature" aria-hidden="true"></i> مشاهده و تأیید توافقنامه مالی
                    </a>
                </div>
            @elseif($participationStatus === \App\Services\MembershipParticipationEligibilityService::MEMBERSHIP_FEE_DUE)
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 sm:p-6 mb-8">
                    <h2 class="text-lg sm:text-xl font-extrabold text-amber-950 mb-2">حق عضویت دوره جاری هنوز پرداخت نشده است</h2>
                    <p class="text-sm sm:text-base text-amber-900 leading-7 mb-4">پس از پرداخت حق عضویت دوره جاری، امکان ساخت و ارسال دعوت‌نامه برای شما فعال می‌شود.</p>
                    <a href="{{ route('najm-bahar.dashboard') }}" class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-full bg-amber-600 px-7 py-3.5 text-white font-bold no-underline">
                        <i class="fas fa-wallet" aria-hidden="true"></i> رفتن به نجم بهار و پرداخت حق عضویت
                    </a>
                </div>
            @else
                <section class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 sm:p-5 md:p-6 mb-8">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-5">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 text-emerald-950 font-extrabold mb-2">
                                <i class="fas fa-check-circle" aria-hidden="true"></i>
                                <h2 class="m-0">حساب مشارکتی شما فعال است</h2>
                            </div>
                            <p class="text-sm sm:text-base leading-7 text-emerald-800 mb-0">{{ $occupiedSlots }} سهمیه در حال استفاده یا تکمیل‌شده است و {{ $remainingSlots }} سهمیه آزاد دارید.</p>
                        </div>

                        @if($canIssueInvitation)
                            <form method="POST" action="{{ route('profile.member-invitations.store') }}" class="m-0 w-full md:w-auto shrink-0">
                                @csrf
                                <button type="submit" class="invite-primary-action inline-flex w-full md:w-auto items-center justify-center gap-2 rounded-full bg-emerald-700 px-7 py-3.5 text-white font-bold border-0 cursor-pointer shadow-lg shadow-emerald-700/20 hover:bg-emerald-800 transition whitespace-nowrap">
                                    <i class="fas fa-plus-circle" aria-hidden="true"></i>
                                    ساخت کد دعوت جدید
                                </button>
                            </form>
                        @else
                            <span class="invite-primary-action inline-flex w-full md:w-auto items-center justify-center rounded-full bg-slate-200 px-7 py-3.5 text-slate-600 font-bold" aria-disabled="true">فعلاً سهمیه آزاد ندارید</span>
                        @endif
                    </div>
                </section>
            @endif

            <div class="flex items-end justify-between gap-4 mb-4">
                <div class="min-w-0">
                    <h2 class="text-xl sm:text-2xl font-extrabold text-slate-800 mb-1">کدهای دعوت شما</h2>
                    <p class="text-sm text-slate-500 mb-0">برای کدهای فعال، دعوت کامل را مستقیماً به اشتراک بگذارید یا کپی کنید.</p>
                </div>
                <span class="hidden sm:inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $successfulInvitations }} / {{ $quota }}</span>
            </div>

            @if($codes->isEmpty())
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                    <i class="fas fa-envelope-open-text text-3xl text-slate-300 mb-3" aria-hidden="true"></i>
                    <p class="font-bold text-slate-700 mb-1">هنوز کد دعوتی ایجاد نکرده‌اید</p>
                    <p class="text-sm text-slate-500 mb-0">وقتی آماده بودید، از دکمه بالا یک کد کوتاه‌مدت بسازید.</p>
                </div>
            @else
                <div class="mobile-invite-cards md:hidden space-y-3">
                    @foreach($codes as $code)
                        @php
                            $expired = $code->expire_at && $code->expire_at->lt(now()) && !$code->completed_at;
                        @endphp
                        <article class="invite-code-card p-4">
                            <div class="flex items-start justify-between gap-3 mb-4">
                                <div class="min-w-0">
                                    <div class="text-xs text-slate-500 mb-1">کد دعوت</div>
                                    <span class="invite-code-value rounded-xl bg-slate-900 text-white px-3 py-2 text-sm font-extrabold">{{ $code->code }}</span>
                                </div>
                                @if($code->completed_at)
                                    <span class="rounded-full bg-emerald-50 text-emerald-700 px-3 py-1 text-xs font-bold whitespace-nowrap">دعوت موفق</span>
                                @elseif($expired)
                                    <span class="rounded-full bg-slate-100 text-slate-600 px-3 py-1 text-xs font-bold whitespace-nowrap">منقضی‌شده</span>
                                @elseif($code->used)
                                    <span class="rounded-full bg-blue-50 text-blue-700 px-3 py-1 text-xs font-bold whitespace-nowrap">ثبت‌نام در جریان</span>
                                @else
                                    <span class="rounded-full bg-amber-50 text-amber-700 px-3 py-1 text-xs font-bold whitespace-nowrap">آماده استفاده</span>
                                @endif
                            </div>

                            <dl class="grid grid-cols-2 gap-3 text-sm mb-4">
                                <div class="rounded-xl bg-slate-50 p-3">
                                    <dt class="text-xs text-slate-500 mb-1">ایجاد</dt>
                                    <dd class="font-bold text-slate-700 mb-0">{{ verta($code->created_at)->format('Y/m/d H:i') }}</dd>
                                </div>
                                <div class="rounded-xl bg-slate-50 p-3">
                                    <dt class="text-xs text-slate-500 mb-1">انقضا</dt>
                                    <dd class="font-bold text-slate-700 mb-0">{{ $code->expire_at ? verta($code->expire_at)->format('Y/m/d H:i') : '-' }}</dd>
                                </div>
                            </dl>

                            @if(!$code->used && !$expired)
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" onclick="shareInviteCode('{{ $code->code }}')" class="rounded-full bg-blue-600 text-white px-3 py-3 text-sm font-bold border-0 flex items-center justify-center gap-2 cursor-pointer">
                                        <i class="fas fa-share-alt" aria-hidden="true"></i>
                                        اشتراک دعوت
                                    </button>
                                    <button type="button" onclick="copyInviteCode('{{ $code->code }}')" class="rounded-full border border-slate-300 bg-white text-slate-700 px-3 py-3 text-sm font-bold flex items-center justify-center gap-2 cursor-pointer">
                                        <i class="far fa-copy" aria-hidden="true"></i>
                                        کپی دعوت
                                    </button>
                                </div>
                            @elseif($code->completed_at && $code->usedBy)
                                <p class="text-xs leading-6 text-slate-500 mb-0">دعوت تکمیل‌شده توسط: {{ $code->usedBy->fullName() }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>

                <div class="hidden md:block desktop-invite-table overflow-hidden rounded-2xl border border-slate-200">
                    <table class="w-full text-sm text-center">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-4 py-3 font-bold">کد</th>
                                <th class="px-4 py-3 font-bold">وضعیت</th>
                                <th class="px-4 py-3 font-bold">ایجاد</th>
                                <th class="px-4 py-3 font-bold">انقضا</th>
                                <th class="px-4 py-3 font-bold">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($codes as $code)
                                @php
                                    $expired = $code->expire_at && $code->expire_at->lt(now()) && !$code->completed_at;
                                @endphp
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="px-4 py-4"><span class="invite-code-value rounded-lg bg-slate-900 text-white px-3 py-2 text-xs font-extrabold">{{ $code->code }}</span></td>
                                    <td class="px-4 py-4">
                                        @if($code->completed_at)
                                            <span class="rounded-full bg-emerald-50 text-emerald-700 px-3 py-1 text-xs font-bold">دعوت موفق</span>
                                        @elseif($expired)
                                            <span class="rounded-full bg-slate-100 text-slate-600 px-3 py-1 text-xs font-bold">منقضی‌شده</span>
                                        @elseif($code->used)
                                            <span class="rounded-full bg-blue-50 text-blue-700 px-3 py-1 text-xs font-bold">ثبت‌نام در جریان</span>
                                        @else
                                            <span class="rounded-full bg-amber-50 text-amber-700 px-3 py-1 text-xs font-bold">آماده استفاده</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-slate-600">{{ verta($code->created_at)->format('Y/m/d H:i') }}</td>
                                    <td class="px-4 py-4 text-slate-600">{{ $code->expire_at ? verta($code->expire_at)->format('Y/m/d H:i') : '-' }}</td>
                                    <td class="px-4 py-4">
                                        @if(!$code->used && !$expired)
                                            <div class="inline-flex items-center gap-2">
                                                <button type="button" onclick="shareInviteCode('{{ $code->code }}')" class="rounded-full bg-blue-600 text-white px-4 py-2.5 text-sm font-bold border-0 cursor-pointer">
                                                    <i class="fas fa-share-alt ml-1" aria-hidden="true"></i> اشتراک دعوت
                                                </button>
                                                <button type="button" onclick="copyInviteCode('{{ $code->code }}')" class="rounded-full border border-slate-300 bg-white text-slate-700 px-4 py-2.5 text-sm font-bold cursor-pointer">
                                                    <i class="far fa-copy ml-1" aria-hidden="true"></i> کپی
                                                </button>
                                            </div>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </main>
</div>

<div id="inviteToast" class="invite-toast rounded-full bg-slate-900 text-white px-5 py-3 text-sm font-bold shadow-xl" role="status" aria-live="polite"></div>
@endsection

@push('scripts')
<script>
const invitationWelcomeUrl = @json(url('/'));

function buildInviteMessage(code) {
    const url = invitationWelcomeUrl + '?invite=' + encodeURIComponent(code);
    return `من به EarthCoop پیوسته‌ام؛ بستری برای همکاری و مشارکت از محله تا جهان.\nاگر دوست داری تو هم از اعضای نخستین باشی، با دعوت من بپیوند.\nکد دعوت: ${code}\n${url}`;
}

function showInviteToast(message) {
    const toast = document.getElementById('inviteToast');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    window.clearTimeout(showInviteToast.timer);
    showInviteToast.timer = window.setTimeout(() => toast.classList.remove('show'), 2200);
}

function copyToClipboard(message) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(message).then(() => showInviteToast('متن کامل دعوت کپی شد.'));
    }

    const textarea = document.createElement('textarea');
    textarea.value = message;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    textarea.remove();
    showInviteToast('متن کامل دعوت کپی شد.');
    return Promise.resolve();
}

async function shareInviteCode(code) {
    const message = buildInviteMessage(code);

    if (navigator.share) {
        try {
            await navigator.share({ title: 'دعوت به EarthCoop', text: message });
            return;
        } catch (error) {
            if (error && error.name === 'AbortError') return;
        }
    }

    copyToClipboard(message);
}

function copyInviteCode(code) {
    copyToClipboard(buildInviteMessage(code));
}
</script>
@endpush
