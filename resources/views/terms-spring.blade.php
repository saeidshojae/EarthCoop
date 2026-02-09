@extends('layouts.unified')

@section('title', 'توافقنامه حساب نجم بهار - ' . config('app.name', 'EarthCoop'))
<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
@push('styles')
<style>
    .agreement-hero {
        position: relative;
        overflow: hidden;
        border-radius: 1.5rem;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.12) 0%, rgba(56, 189, 248, 0.15) 100%);
        padding: 3rem 2rem;
        box-shadow: 0 24px 48px rgba(15, 118, 110, 0.12);
        border: 1px solid rgba(16, 185, 129, 0.15);
    }

    .agreement-hero::before,
    .agreement-hero::after {
        content: "";
        position: absolute;
        width: 260px;
        height: 260px;
        border-radius: 50%;
        filter: blur(80px);
        opacity: 0.6;
    }

    .agreement-hero::before {
        top: -120px;
        left: -60px;
        background: rgba(16, 185, 129, 0.55);
    }

    .agreement-hero::after {
        bottom: -120px;
        right: -40px;
        background: rgba(59, 130, 246, 0.45);
    }

    .agreement-title {
        font-size: clamp(2rem, 4vw, 3rem);
        font-weight: 800;
        color: var(--color-gentle-black);
        line-height: 1.3;
    }

    .agreement-subtitle {
        color: #4b5563;
        font-size: 1.1rem;
        max-width: 52ch;
    }

    .agreement-points {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-top: 1.5rem;
    }

    .agreement-point {
        background: rgba(255, 255, 255, 0.75);
        border-radius: 1rem;
        padding: 1rem 1.25rem;
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        border: 1px solid rgba(148, 163, 184, 0.2);
        backdrop-filter: blur(8px);
    }

    .agreement-point i {
        color: var(--color-earth-green);
        font-size: 1.1rem;
        margin-top: 0.35rem;
    }

    .agreement-card {
        background: var(--color-pure-white);
        border-radius: 1.5rem;
        padding: 2.5rem;
        border: 1px solid rgba(226, 232, 240, 0.9);
        box-shadow: 0 18px 64px rgba(15, 23, 42, 0.08);
    }

    .agreement-content {
        font-size: 1.05rem;
        line-height: 2;
        color: var(--color-gentle-black);
    }

    .agreement-content p,
    .agreement-content li {
        margin-bottom: 0.85rem;
    }

    .agreement-section {
        margin-bottom: 2.5rem;
        padding-bottom: 2rem;
        border-bottom: 1px solid rgba(226, 232, 240, 0.5);
    }

    .agreement-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .agreement-section-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-dark-green);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .agreement-section-title i {
        color: var(--color-earth-green);
    }

    .agreement-subsection {
        margin-right: 2rem;
        margin-top: 1.5rem;
        padding-right: 1.5rem;
        border-right: 2px solid rgba(16, 185, 129, 0.2);
    }

    .agreement-subsection-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--color-dark-green);
        margin-bottom: 0.75rem;
    }

    .agreement-highlights {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.5rem;
    }

    .agreement-highlight {
        border-radius: 1.25rem;
        background: linear-gradient(160deg, rgba(16, 185, 129, 0.08) 0%, rgba(37, 99, 235, 0.08) 100%);
        padding: 1.75rem;
        border: 1px solid rgba(79, 70, 229, 0.12);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .agreement-highlight:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 45px rgba(14, 165, 233, 0.15);
    }

    .agreement-highlight h4 {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--color-gentle-black);
        margin-bottom: 0.75rem;
    }

    .agreement-highlight p {
        color: #475569;
        line-height: 1.8;
    }

    .agreement-actions {
        background: linear-gradient(120deg, rgba(16, 185, 129, 0.12) 0%, rgba(37, 99, 235, 0.12) 100%);
        border-radius: 1.5rem;
        padding: 2.5rem;
        border: 1px solid rgba(16, 185, 129, 0.25);
        box-shadow: 0 24px 50px rgba(14, 165, 233, 0.18);
    }

    .agreement-actions h3 {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--color-dark-green);
    }

    .agreement-actions p {
        color: #0f172a;
        margin-top: 0.5rem;
        margin-bottom: 1.5rem;
    }

    .agreement-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        background: linear-gradient(135deg, var(--color-earth-green), #047857);
        color: var(--color-pure-white);
        padding: 0.9rem 2.5rem;
        border-radius: 999px;
        font-weight: 700;
        font-size: 1.05rem;
        transition: transform 0.25s ease, box-shadow 0.25s ease;
        box-shadow: 0 18px 30px rgba(5, 150, 105, 0.22);
        border: none;
        cursor: pointer;
    }

    .agreement-button:hover {
        transform: translateY(-3px);
        box-shadow: 0 22px 40px rgba(5, 150, 105, 0.28);
    }

    .agreement-button.secondary {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.95), rgba(29, 78, 216, 0.95));
        box-shadow: 0 18px 30px rgba(29, 78, 216, 0.22);
    }

    .agreement-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: rgba(255, 255, 255, 0.7);
        border: 1px solid rgba(16, 185, 129, 0.25);
        border-radius: 999px;
        padding: 0.5rem 1.25rem;
        color: var(--color-dark-green);
        font-weight: 600;
        backdrop-filter: blur(8px);
    }

    .agreement-note {
        color: #475569;
        font-size: 0.95rem;
    }

    .agreement-alert {
        background: rgba(254, 243, 199, 0.75);
        border-right: 4px solid var(--color-digital-gold);
        border-radius: 1rem;
        padding: 1rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        color: #92400e;
    }

    .agreement-alert i {
        font-size: 1.5rem;
    }

    .fade-in-section {
        opacity: 0;
        transform: translateY(24px);
        transition: opacity 0.6s ease, transform 0.6s ease;
    }

    .fade-in-section.is-visible {
        opacity: 1;
        transform: translateY(0);
    }

    @media (max-width: 768px) {
        .agreement-hero {
            padding: 2.25rem 1.5rem;
        }

        .agreement-card,
        .agreement-actions {
            padding: 1.75rem;
        }

        .agreement-actions h3 {
            font-size: 1.45rem;
        }

        .agreement-subsection {
            margin-right: 1rem;
            padding-right: 1rem;
        }
    }
</style>
@endpush

@section('content')
@php
    $springAccount = $springAccount ?? \App\Models\Spring::where('user_id', auth()->id())->first();
    $setting = \App\Models\Setting::find(1);
@endphp

<div class="bg-light-gray/60 py-10 md:py-12" style="background-color: var(--color-light-gray);">
    <div class="container mx-auto px-5 md:px-10 max-w-6xl space-y-10">
        @if(session('error'))
            <div class="agreement-alert">
                <i class="fas fa-exclamation-triangle"></i>
                <div>{{ session('error') }}</div>
            </div>
        @endif

        <section class="agreement-hero fade-in-section">
            <div class="agreement-badge fade-in-section">
                <i class="fas fa-seedling"></i>
                <span>قدم نخست برای فعال‌سازی حساب مالی نجم بهار</span>
            </div>
            <div class="mt-6 space-y-4">
                <h1 class="agreement-title">توافقنامه حساب مالی نجم بهار</h1>
                <p class="agreement-subtitle">
                    برای استفاده از سامانه مالی نجم بهار، ابتدا لازم است با اصول و تعهدات این توافقنامه موافقت کنید. مطالعه دقیق این بخش، حق‌وحقوق شما را در مسیر اقتصاد مشارکتی EarthCoop تضمین می‌کند.
                </p>
            </div>
            <div class="agreement-points">
                <div class="agreement-point">
                    <i class="fas fa-shield-check"></i>
                    <p>حفظ امنیت سرمایه اجتماعی و مالی اعضا با رعایت اصول شفافیت، نظارت جمعی و مسئولیت‌پذیری.</p>
                </div>
                <div class="agreement-point">
                    <i class="fas fa-handshake"></i>
                    <p>تاکید بر ارزش‌های همکاری، اعتماد متقابل و مشارکت پایدار در توسعه زیست‌بوم مشترک.</p>
                </div>
                <div class="agreement-point">
                    <i class="fas fa-balance-scale"></i>
                    <p>پایبندی به قوانین، سیاست‌های مالی و سازوکارهای شفاف تعیین‌شده توسط EarthCoop.</p>
                </div>
            </div>
        </section>

        <section class="agreement-card space-y-6 fade-in-section">
            <h2 class="text-2xl md:text-3xl font-extrabold" style="color: var(--color-dark-green);">متن کامل توافقنامه</h2>
            
            <div class="agreement-content prose prose-lg max-w-none" style="direction: rtl; text-align: right;">
                @if(isset($agreements) && $agreements->isNotEmpty())
                    {{-- نمایش توافقنامه‌های جدید از جدول najm_bahar_agreements --}}
                    @foreach($agreements as $index => $agreement)
                        <div class="agreement-section">
                            <div class="agreement-section-title">
                                <i class="fas fa-file-contract"></i>
                                {{ $agreement->title }}
                            </div>
                            <div class="agreement-content">
                                {!! $agreement->content !!}
                            </div>
                            
                            {{-- نمایش زیرمجموعه‌ها --}}
                            @if($agreement->children->isNotEmpty())
                                @foreach($agreement->children as $child)
                                    <div class="agreement-subsection">
                                        <div class="agreement-subsection-title">
                                            <i class="fas fa-level-down-alt text-sm ml-2"></i>
                                            {{ $child->title }}
                                        </div>
                                        <div class="agreement-content">
                                            {!! $child->content !!}
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    @endforeach
                @elseif(isset($oldAgreement) && $oldAgreement)
                    {{-- نمایش توافقنامه قدیمی از settings --}}
                    <div class="agreement-section">
                        <div class="agreement-section-title">
                            <i class="fas fa-file-contract"></i>
                            {{ $oldAgreement['title'] }}
                        </div>
                        <div class="agreement-content">
                            {!! $oldAgreement['content'] !!}
                        </div>
                    </div>
                @else
                    <div class="agreement-content">
                        <p class="text-slate-500 dark:text-slate-400">در حال حاضر توافقنامه‌ای ثبت نشده است.</p>
                    </div>
                @endif
            </div>

            <div class="agreement-highlights mt-8">
                <div class="agreement-highlight">
                    <span class="text-sm font-semibold text-earth-green">مرحله ۱</span>
                    <h4>مطالعه دقیق بندها</h4>
                    <p>تمامی بندهای توافقنامه را با دقت مطالعه کنید تا نسبت به حقوق، تعهدات و سازوکارهای اجرایی حساب نجم بهار آگاه شوید.</p>
                </div>
                <div class="agreement-highlight">
                    <span class="text-sm font-semibold text-earth-green">مرحله ۲</span>
                    <h4>تکمیل پروفایل کاربری</h4>
                    <p>اطلاعات حساب کاربری باید کامل و تایید شده باشد. در صورت کمبود اطلاعات، قبل از تایید توافقنامه، پروفایل خود را تکمیل کنید.</p>
                </div>
                <div class="agreement-highlight">
                    <span class="text-sm font-semibold text-earth-green">مرحله ۳</span>
                    <h4>تایید نهایی و آغاز همکاری</h4>
                    <p>پس از موافقت، حساب مالی شما فعال شده و امکان آغاز فعالیت‌های مالی در سامانه برای شما فراهم می‌شود.</p>
                </div>
            </div>
        </section>

        <section class="agreement-actions text-center space-y-4 fade-in-section">
            <h3>گام بعدی شما چیست؟</h3>
            <p>پس از تایید توافقنامه، حساب نجم بهار شما در کمتر از چند لحظه فعال می‌شود و می‌توانید موجودی خود را مدیریت کنید.</p>

            @if($springAccount && $springAccount->status == 1)
                <div class="bg-white/85 border border-emerald-200 rounded-2xl p-6 space-y-3 text-slate-700">
                    <div class="flex items-center justify-center gap-2 text-earth-green">
                        <i class="fas fa-check-circle text-2xl"></i>
                        <span class="font-extrabold text-lg">این توافقنامه قبلاً تأیید شده است.</span>
                    </div>
                    <p class="agreement-note">
                        تاریخ فعال‌سازی حساب: <strong>{{ verta($springAccount->updated_at ?? now())->format('Y/m/d H:i') }}</strong>
                    </p>
                    <a href="{{ route('spring-accounts') }}" class="agreement-button">
                        <i class="fas fa-wallet"></i>
                        مشاهده حساب نجم بهار
                    </a>
                </div>
            @elseif($isProfileComplete)
                <form action="{{ route('najm-bahar.agreement.process') }}" method="POST" class="inline-flex flex-col items-center gap-3">
                    @csrf
                    <input type="hidden" name="agreement_accepted" value="1">
                    <button type="submit" class="agreement-button">
                        <i class="fas fa-check"></i>
                        موافقت می‌کنم و ادامه می‌دهم
                    </button>
                    <span class="agreement-note">با تایید این گزینه، شرایط توافقنامه را می‌پذیرید.</span>
                </form>
            @else
                <div class="inline-flex flex-col items-center gap-3">
                    <form action="{{ route('profile.edit') }}" method="GET">
                        <button type="submit" class="agreement-button secondary">
                            <i class="fas fa-user-edit"></i>
                            ابتدا حساب کاربری خود را تکمیل کنید
                        </button>
                    </form>
                    <span class="agreement-note">پس از تکمیل اطلاعات و تایید حساب، می‌توانید توافقنامه را تایید کنید.</span>
                </div>
            @endif

            <div class="agreement-note flex items-center justify-center gap-2 text-sm">
                <i class="fas fa-info-circle"></i>
                @if(isset($agreements) && $agreements->isNotEmpty())
                    آخرین به‌روزرسانی: <strong>{{ $agreements->first()->updated_at ? verta($agreements->first()->updated_at)->format('Y/m/d') : '-' }}</strong>
                @elseif($setting)
                    آخرین به‌روزرسانی: <strong>{{ verta($setting->updated_at)->format('Y/m/d') }}</strong>
                @endif
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const sections = document.querySelectorAll('.fade-in-section');
        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        sections.forEach(section => observer.observe(section));
    });
</script>
@endpush
