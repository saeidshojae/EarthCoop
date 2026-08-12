@extends('layouts.unified')



















@section('title', 'توافقنامه حساب نجم بهار - ' . config('app.name', 'EarthCoop'))




























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









        background: var(--nb-color-white);









        border-radius: var(--nb-radius-lg);









        padding: var(--nb-space-8);









        border: 1px solid var(--nb-color-neutral-200);









        box-shadow: var(--nb-shadow-md);









    }



















    .agreement-content {









        color: #1f2937;









        line-height: 1.9;









    }



















    .agreement-section-title {









        font-weight: 700;









        font-size: 1.15rem;









        color: var(--color-dark-green);









        display: flex;









        align-items: center;









        gap: 0.5rem;









        margin-bottom: 0.75rem;









    }

    .agreement-accordion-button {
        width: 100%;
        border: 0;
        background: transparent;
        cursor: pointer;
        text-align: right;
        justify-content: space-between;
        padding: 1rem 1.25rem;
        margin: 0;
    }

    .agreement-accordion-heading {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .agreement-accordion-chevron { transition: transform 0.25s ease; }

    .agreement-accordion-button[aria-expanded="true"] .agreement-accordion-chevron {
        transform: rotate(180deg);
    }

    .agreement-accordion-panel {
        display: none;
        padding: 0 1.25rem 1.25rem;
    }

    .agreement-accordion-panel.active { display: block; }



















    .agreement-section {









        padding: 0;

        border: 1px solid rgba(148, 163, 184, 0.28);

        border-radius: 1rem;

        overflow: hidden;

        margin-bottom: 1rem;









        border-bottom: 1px dashed rgba(148, 163, 184, 0.3);









    }



















    .agreement-section:last-child {









        border-bottom: none;









    }



















    .agreement-subsection {









        margin-top: 1rem;









        padding: 1rem 1rem 1rem 1.5rem;









        background: rgba(148, 163, 184, 0.08);









        border-radius: 1rem;









    }



















    .agreement-subsection-title {









        font-weight: 600;









        color: #1f2937;









        margin-bottom: 0.5rem;









        display: flex;









        align-items: center;









        gap: 0.5rem;









    }

    .agreement-subsection-button {
        width: 100%;
        border: 0;
        background: transparent;
        cursor: pointer;
        text-align: right;
        justify-content: space-between;
        margin: 0;
        padding: 0;
    }

    .agreement-subsection-heading {
        display: inline-flex;
        align-items: center;
    }

    .agreement-subsection-chevron { transition: transform 0.25s ease; }

    .agreement-subsection-button[aria-expanded="true"] .agreement-subsection-chevron {
        transform: rotate(180deg);
    }

    .agreement-subsection-panel {
        display: none;
        padding-top: 0.75rem;
    }

    .agreement-subsection-panel.active { display: block; }



















    .agreement-highlights {









        display: grid;









        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));









        gap: 1rem;









    }



















    .agreement-highlight {









        background: rgba(16, 185, 129, 0.08);









        border: 1px solid rgba(16, 185, 129, 0.2);









        border-radius: 1.25rem;









        padding: 1.5rem;









        transition: transform 0.2s ease, box-shadow 0.2s ease;









    }



















    .agreement-highlight:hover {









        transform: translateY(-4px);









        box-shadow: 0 16px 32px rgba(16, 185, 129, 0.12);









    }



















    .agreement-actions {









        background: rgba(59, 130, 246, 0.08);









        border: 1px solid rgba(59, 130, 246, 0.2);









        border-radius: 1.5rem;









        padding: 2rem;









    }



















    .agreement-button {









        display: inline-flex;









        align-items: center;









        gap: 0.5rem;









        background: linear-gradient(135deg, var(--color-earth-green), var(--color-dark-green));









        color: white;









        padding: 0.9rem 2rem;









        border-radius: 999px;









        font-weight: 700;









        transition: transform 0.2s ease, box-shadow 0.2s ease;









    }



















    .agreement-button:hover {









        transform: translateY(-2px);









        box-shadow: 0 16px 32px rgba(16, 185, 129, 0.25);









    }



















    .agreement-button.secondary {









        background: white;









        border: 1px solid rgba(59, 130, 246, 0.4);









        color: var(--color-ocean-blue);









    }



















    .agreement-note {









        color: #4b5563;









        font-size: 0.9rem;









    }



















    .agreement-alert {









        background: rgba(248, 113, 113, 0.1);









        border: 1px solid rgba(248, 113, 113, 0.3);









        color: #b91c1c;









        border-radius: 1rem;









        padding: 1rem 1.25rem;









        display: flex;









        align-items: center;









        gap: 0.75rem;









    }



















    .fade-in-section {









        opacity: 0;









        transform: translateY(12px);









        transition: opacity 0.6s ease, transform 0.6s ease;









    }



















    .fade-in-section.is-visible {









        opacity: 1;









        transform: translateY(0);









    }



















    @media (max-width: 768px) {









        .agreement-card {









            padding: 1.75rem;









        }



















        .agreement-hero {









            padding: 2rem 1.5rem;









        }









    }









</style>









@endpush



















@section('content')









<div class="bg-light-gray/60 py-10 md:py-12" style="background-color: var(--color-light-gray);">









    <div class="nb-page-container" style="max-width: 72rem;">









        @if(session('error'))









            <div class="agreement-alert">









                <i class="fas fa-exclamation-triangle"></i>









                <div>{{ session('error') }}</div>









            </div>









        @endif



















        @if(session('success'))









            <div class="agreement-alert" style="border-color: rgba(16, 185, 129, 0.3); color: #065f46; background: rgba(16, 185, 129, 0.1);">









                <i class="fas fa-check-circle"></i>









                <div>{{ session('success') }}</div>









            </div>









        @endif



















        @if($errors->any())









            <div class="agreement-alert">









                <i class="fas fa-exclamation-triangle"></i>









                <div>









                    <ul class="list-disc list-inside">









                        @foreach($errors->all() as $error)









                            <li>{{ $error }}</li>









                        @endforeach









                    </ul>









                </div>









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









                @if($agreements->isNotEmpty())









                    @foreach($agreements as $agreement)









                        <div class="agreement-section">









                            <button type="button"
                                    class="agreement-section-title agreement-accordion-button"
                                    aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                                    aria-controls="agreement-panel-{{ $agreement->id }}"
                                    onclick="toggleAgreementSection(this)">









                                <span class="agreement-accordion-heading">

                                <i class="fas fa-file-contract"></i>









                                {{ $agreement->title }}

                                </span>

                                <i class="fas fa-chevron-down agreement-accordion-chevron" aria-hidden="true"></i>









                            </button>

                            <div id="agreement-panel-{{ $agreement->id }}"
                                 class="agreement-accordion-panel{{ $loop->first ? ' active' : '' }}">









                            <div class="agreement-content">









                                {!! $agreement->content !!}









                            </div>



















                            @if($agreement->children->isNotEmpty())









                                @foreach($agreement->children as $child)









                                    <div class="agreement-subsection">









                                        <button type="button"
                                                class="agreement-subsection-title agreement-subsection-button"
                                                aria-expanded="false"
                                                aria-controls="agreement-child-panel-{{ $child->id }}"
                                                onclick="toggleAgreementSubsection(this)">

                                            <span class="agreement-subsection-heading">









                                            <i class="fas fa-level-down-alt text-sm ml-2"></i>









                                            {{ $child->title }}

                                            </span>

                                            <i class="fas fa-chevron-down agreement-subsection-chevron" aria-hidden="true"></i>









                                        </button>









                                        <div id="agreement-child-panel-{{ $child->id }}" class="agreement-content agreement-subsection-panel">









                                            {!! $child->content !!}









                                        </div>









                                    </div>









                                @endforeach









                            @endif

                            </div>









                        </div>









                    @endforeach









                @else









                    <div class="agreement-content">









                        <p class="text-slate-500">در حال حاضر توافقنامه‌ای ثبت نشده است.</p>









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









            <h3>{{ $hasAcceptedAgreement ? 'وضعیت توافقنامه شما' : 'گام بعدی شما چیست؟' }}</h3>









            <p>
                {{ $hasAcceptedAgreement
                    ? 'این صفحه همیشه برای مطالعه متن جاری توافقنامه و آگاهی از به‌روزرسانی‌های آن در دسترس شماست.'
                    : 'پس از تایید توافقنامه، حساب نجم بهار شما در کمتر از چند لحظه فعال می‌شود و می‌توانید موجودی خود را مدیریت کنید.' }}
            </p>



















            @if($hasAcceptedAgreement)

                <div class="agreement-alert inline-flex flex-col items-center gap-3" style="border-color: rgba(16, 185, 129, 0.35); color: #065f46; background: rgba(16, 185, 129, 0.12);">
                    <div class="flex items-center justify-center gap-2 font-bold">
                        <i class="fas fa-check-circle"></i>
                        <span>شما قبلاً این توافقنامه را پذیرفته‌اید.</span>
                    </div>
                    <a href="{{ route('najm-bahar.dashboard') }}" class="agreement-button secondary">
                        <i class="fas fa-arrow-left"></i>
                        بازگشت به داشبورد نجم بهار
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









                @if($hasAcceptedAgreement && auth()->user()->najm_bahar_agreement_accepted_at)

                    تاریخ موافقت شما با توافقنامه: <strong>{{ verta(auth()->user()->najm_bahar_agreement_accepted_at)->format('Y/m/d') }}</strong>

                @elseif($agreements->isNotEmpty())









                    آخرین به‌روزرسانی متن توافقنامه: <strong>{{ $agreements->first()->updated_at ? verta($agreements->first()->updated_at)->format('Y/m/d') : '-' }}</strong>









                @endif









            </div>









        </section>









    </div>









</div>









@endsection



















@push('scripts')









<script>
    function toggleAgreementSection(button) {
        const targetPanel = document.getElementById(button.getAttribute('aria-controls'));
        const isOpen = button.getAttribute('aria-expanded') === 'true';

        document.querySelectorAll('.agreement-accordion-button').forEach(otherButton => {
            otherButton.setAttribute('aria-expanded', 'false');
        });
        document.querySelectorAll('.agreement-accordion-panel.active').forEach(panel => {
            panel.classList.remove('active');
        });

        if (!isOpen && targetPanel) {
            button.setAttribute('aria-expanded', 'true');
            targetPanel.classList.add('active');
        }
    }

    function toggleAgreementSubsection(button) {
        const targetPanel = document.getElementById(button.getAttribute('aria-controls'));
        const isOpen = button.getAttribute('aria-expanded') === 'true';
        const parentPanel = button.closest('.agreement-accordion-panel');

        parentPanel?.querySelectorAll('.agreement-subsection-button').forEach(otherButton => {
            otherButton.setAttribute('aria-expanded', 'false');
        });
        parentPanel?.querySelectorAll('.agreement-subsection-panel.active').forEach(panel => {
            panel.classList.remove('active');
        });

        if (!isOpen && targetPanel) {
            button.setAttribute('aria-expanded', 'true');
            targetPanel.classList.add('active');
        }
    }









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
