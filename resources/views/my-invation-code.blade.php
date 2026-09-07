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
    .invite-toast.show { opacity: 1; transform: translateX(50%) translateY(0); }
</style>
@endpush

@section('content')
@php
    $setting = \App\Models\Setting::find(1);
    $inviteLimit = max(1, (int) ($setting?->count_invation ?? 10));

    \App\Models\InvitationCode::where('user_id', auth()->id())
        ->where('used', 0)
        ->whereNotNull('expire_at')
        ->where('expire_at', '<=', now())
        ->delete();

    $codes = \App\Models\InvitationCode::where('user_id', auth()->id())
        ->with('usedBy')
        ->orderByDesc('created_at')
        ->get();

    $usedCount = $codes->where('used', 1)->count();
    $availableCount = max(0, $inviteLimit - $codes->count());
@endphp

<div class="invite-page-shell container mx-auto flex flex-col lg:flex-row gap-6 p-4 sm:p-6 md:p-8">
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
                        <p class="text-sm sm:text-base leading-7 text-slate-600 max-w-3xl">
                            با یک دعوت شخصی و شفاف، کسانی را که می‌شناسید به جمع اعضای EarthCoop اضافه کنید. هر کد تا ۷۲ ساعت معتبر است و فقط یک‌بار استفاده می‌شود.
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

            @if ($errors->any())
                <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm sm:text-base">
                    <ul class="m-0 pr-5 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <article class="invite-info-card p-4 sm:p-5">
                    <div class="flex items-center gap-2 mb-3 text-emerald-700 font-bold">
                        <i class="fas fa-ticket-alt" aria-hidden="true"></i>
                        <h2>سهمیه دعوت شما</h2>
                    </div>
                    <p class="text-sm leading-7 text-slate-600">
                        سقف فعلی شما <strong class="text-slate-800">{{ $inviteLimit }} دعوت</strong> است و تنظیمات سامانه مبنای این عدد است.
                    </p>
                </article>

                <article class="invite-info-card p-4 sm:p-5">
                    <div class="flex items-center gap-2 mb-3 text-blue-700 font-bold">
                        <i class="fas fa-clock" aria-hidden="true"></i>
                        <h2>کد کوتاه‌مدت و یک‌بارمصرف</h2>
                    </div>
                    <p class="text-sm leading-7 text-slate-600">
                        کد استفاده‌نشده پس از ۷۲ ساعت منقضی می‌شود؛ اگر مصرف نشود، می‌توانید کد دیگری بسازید.
                    </p>
                </article>

                <article class="invite-info-card p-4 sm:p-5">
                    <div class="flex items-center gap-2 mb-3 text-amber-600 font-bold">
                        <i class="fas fa-award" aria-hidden="true"></i>
                        <h2>اعتبار مشارکت دعوت</h2>
                    </div>
                    <p class="text-sm leading-7 text-slate-600 mb-2">
                        دعوت موفق می‌تواند طبق قواعد جاری سامانه برای دعوت‌کننده اعتبار مشارکت ثبت کند.
                    </p>
                    <a href="{{ route('participation.credit-regulation') }}" class="inline-flex items-center gap-2 text-sm font-bold text-emerald-700 hover:text-emerald-800">
                        مشاهده نظام‌نامه اعتبارات مشارکت
                        <i class="fas fa-arrow-left text-xs" aria-hidden="true"></i>
                    </a>
                </article>
            </div>

            <section class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 sm:p-5 md:p-6 mb-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-5">
                    <div>
                        <div class="flex items-center gap-2 text-emerald-900 font-extrabold mb-2">
                            <i class="fas fa-check-circle" aria-hidden="true"></i>
                            <h2>حساب مشارکتی شما فعال است</h2>
                        </div>
                        <p class="text-sm sm:text-base leading-7 text-emerald-800">
                            {{ $usedCount }} دعوت استفاده شده، {{ $availableCount }} ظرفیت قابل ایجاد و {{ $inviteLimit }} سهمیه کل دارید.
                        </p>
                    </div>

                    @if($codes->count() >= $inviteLimit)
                        <span class="invite-primary-action inline-flex w-full md:w-auto items-center justify-center gap-2 px-7 py-3.5 rounded-full bg-slate-200 text-slate-500 font-bold cursor-not-allowed" aria-disabled="true">
                            <i class="fas fa-plus-circle" aria-hidden="true"></i>
                            سهمیه تکمیل است
                        </span>
                    @else
                        <a href="{{ route('profile.generate-code') }}" class="invite-primary-action inline-flex w-full md:w-auto items-center justify-center gap-2 px-7 py-3.5 rounded-full bg-emerald-600 text-white font-bold shadow-lg shadow-emerald-600/20 hover:bg-emerald-700 transition">
                            <i class="fas fa-plus-circle" aria-hidden="true"></i>
                            ساخت کد دعوت جدید
                        </a>
                    @endif
                </div>
            </section>

            <div class="flex items-end justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-xl sm:text-2xl font-extrabold text-slate-800 mb-1">کدهای دعوت شما</h2>
                    <p class="text-sm text-slate-500">برای کدهای فعال، دعوت کامل را مستقیماً به اشتراک بگذارید یا کپی کنید.</p>
                </div>
                <span class="hidden sm:inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $codes->count() }} / {{ $inviteLimit }}</span>
            </div>

            @if($codes->isEmpty())
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                    <i class="fas fa-envelope-open-text text-3xl text-slate-300 mb-3" aria-hidden="true"></i>
                    <p class="font-bold text-slate-700 mb-1">هنوز کد دعوتی ایجاد نکرده‌اید</p>
                    <p class="text-sm text-slate-500">وقتی آماده بودید، از دکمه بالا یک کد کوتاه‌مدت بسازید.</p>
                </div>
            @else
                <div class="mobile-invite-cards md:hidden space-y-3">
                    @foreach($codes as $code)
                        <article class="invite-code-card p-4">
                            <div class="flex items-start justify-between gap-3 mb-4">
                                <div>
                                    <div class="text-xs text-slate-500 mb-1">کد دعوت</div>
                                    <span class="invite-code-value rounded-xl bg-slate-900 text-white px-3 py-2 text-sm font-extrabold">{{ $code->code }}</span>
                                </div>
                                @if($code->used == 0)
                                    <span class="rounded-full bg-emerald-50 text-emerald-700 px-3 py-1 text-xs font-bold">فعال</span>
                                @else
                                    <span class="rounded-full bg-blue-50 text-blue-700 px-3 py-1 text-xs font-bold">استفاده شده</span>
                                @endif
                            </div>

                            <dl class="grid grid-cols-2 gap-3 text-sm mb-4">
                                <div class="rounded-xl bg-slate-50 p-3">
                                    <dt class="text-xs text-slate-500 mb-1">ایجاد</dt>
                                    <dd class="font-bold text-slate-700">{{ verta($code->created_at)->format('Y/m/d') }}</dd>
                                </div>
                                <div class="rounded-xl bg-slate-50 p-3">
                                    <dt class="text-xs text-slate-500 mb-1">انقضا</dt>
                                    <dd class="font-bold text-slate-700">{{ $code->expire_at ? verta($code->expire_at)->format('Y/m/d H:i') : '-' }}</dd>
                                </div>
                            </dl>

                            @if($code->used == 1)
                                <p class="text-xs leading-6 text-slate-500 mb-1">
                                    استفاده شده توسط: {{ $code->usedBy ? $code->usedBy->fullName() : 'نامشخص' }}
                                </p>
                            @else
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" onclick="shareInviteCode('{{ $code->code }}')" class="rounded-full bg-blue-600 text-white px-4 py-3 text-sm font-bold flex items-center justify-center gap-2">
                                        <i class="fas fa-share-alt" aria-hidden="true"></i>
                                        اشتراک دعوت
                                    </button>
                                    <button type="button" onclick="copyInviteCode('{{ $code->code }}')" class="rounded-full border border-slate-300 bg-white text-slate-700 px-4 py-3 text-sm font-bold flex items-center justify-center gap-2">
                                        <i class="far fa-copy" aria-hidden="true"></i>
                                        کپی دعوت
                                    </button>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>

                <div class="hidden md:block desktop-invite-table overflow-hidden rounded-2xl border border-slate-200">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-4 py-3 text-center font-bold">کد</th>
                                <th class="px-4 py-3 text-center font-bold">وضعیت</th>
                                <th class="px-4 py-3 text-center font-bold">ایجاد</th>
                                <th class="px-4 py-3 text-center font-bold">انقضا</th>
                                <th class="px-4 py-3 text-center font-bold">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($codes as $code)
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="px-4 py-4 text-center"><span class="invite-code-value rounded-lg bg-slate-900 text-white px-3 py-2 text-xs font-extrabold">{{ $code->code }}</span></td>
                                    <td class="px-4 py-4 text-center">
                                        @if($code->used == 0)
                                            <span class="rounded-full bg-emerald-50 text-emerald-700 px-3 py-1 text-xs font-bold">فعال</span>
                                        @else
                                            <span class="rounded-full bg-blue-50 text-blue-700 px-3 py-1 text-xs font-bold">استفاده شده</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-center text-slate-600">{{ verta($code->created_at)->format('Y/m/d') }}</td>
                                    <td class="px-4 py-4 text-center text-slate-600">{{ $code->expire_at ? verta($code->expire_at)->format('Y/m/d H:i') : '-' }}</td>
                                    <td class="px-4 py-4 text-center">
                                        @if($code->used == 0)
                                            <div class="inline-flex items-center gap-2">
                                                <button type="button" onclick="shareInviteCode('{{ $code->code }}')" class="rounded-full bg-blue-600 text-white px-4 py-2 font-bold hover:bg-blue-700 transition">
                                                    <i class="fas fa-share-alt ml-1" aria-hidden="true"></i>اشتراک
                                                </button>
                                                <button type="button" onclick="copyInviteCode('{{ $code->code }}')" class="rounded-full border border-slate-300 bg-white text-slate-700 px-4 py-2 font-bold hover:bg-slate-50 transition">
                                                    <i class="far fa-copy ml-1" aria-hidden="true"></i>کپی دعوت
                                                </button>
                                            </div>
                                        @else
                                            <span class="text-xs text-slate-500">{{ $code->usedBy ? $code->usedBy->fullName() : 'مصرف شده' }}</span>
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

<div id="copySuccessMessage" class="invite-toast rounded-full bg-slate-900 text-white px-5 py-3 text-sm font-bold shadow-xl" role="status" aria-live="polite">
    متن دعوت کپی شد.
</div>
@endsection

@push('scripts')
<script>
    const invitationWelcomeUrl = @json(route('welcome'));

    function buildInviteMessage(code) {
        const url = invitationWelcomeUrl + '?invite=' + encodeURIComponent(code);
        return `من به EarthCoop پیوسته‌ام؛ بستری برای همکاری از محله تا جهان.\nاگر دوست داری تو هم از اعضای نخستین باشی، با دعوت من بپیوند.\n\nکد دعوت: ${code}\n${url}\n\nاین کد تا ۷۲ ساعت معتبر است.`;
    }

    async function shareInviteCode(code) {
        const message = buildInviteMessage(code);

        if (navigator.share) {
            try {
                await navigator.share({
                    title: 'دعوت به EarthCoop',
                    text: message,
                });
                return;
            } catch (error) {
                if (error && error.name === 'AbortError') return;
            }
        }

        copyToClipboard(message);
    }

    function copyInviteCode(code) {
        const message = buildInviteMessage(code);
        copyToClipboard(message);
    }

    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text)
                .then(showCopyMessage)
                .catch(() => fallbackCopyToClipboard(text));
            return;
        }

        fallbackCopyToClipboard(text);
    }

    function fallbackCopyToClipboard(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();

        try {
            document.execCommand('copy');
            showCopyMessage();
        } catch (error) {
            window.prompt('متن دعوت را کپی کنید:', text);
        } finally {
            textarea.remove();
        }
    }

    function showCopyMessage() {
        const toast = document.getElementById('copySuccessMessage');
        if (!toast) return;
        toast.classList.add('show');
        window.setTimeout(() => toast.classList.remove('show'), 2200);
    }
</script>
@endpush
