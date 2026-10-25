@extends('layouts.unified')

@section('title', $page->translated_title)

@push('styles')
<style>
    /* Custom Tailwind Configuration - Color and font configuration */
    :root {
        --color-earth-green: #10b981;
        --color-ocean-blue: #3b82f6;
        --color-digital-gold: #f59e0b;
        --color-pure-white: #ffffff;
        --color-light-gray: #f8fafc;
        --color-gentle-black: #1e293b;
        --color-dark-green: #047857;
        --color-dark-blue: #1d4ed8;
        --color-accent-peach: #ff7e5f;
        --color-accent-sky: #6dd5ed;
        --color-purple-700: #6B46C1;
    }

    /* Utility classes for custom colors */
    .bg-earth-green { background-color: var(--color-earth-green); }
    .text-earth-green { color: var(--color-earth-green); }
    .bg-ocean-blue { background-color: var(--color-ocean-blue); }
    .text-ocean-blue { color: var(--color-ocean-blue); }
    .bg-digital-gold { background-color: var(--color-digital-gold); }
    .text-digital-gold { color: var(--color-digital-gold); }
    .bg-pure-white { background-color: var(--color-pure-white); }
    .text-pure-white { color: var(--color-pure-white); }
    .bg-light-gray { background-color: var(--color-light-gray); }
    .text-light-gray { color: var(--color-light-gray); }
    .bg-gentle-black { background-color: var(--color-gentle-black); }
    .text-gentle-black { color: var(--color-gentle-black); }
    .bg-dark-green { background-color: var(--color-dark-green); }
    .bg-dark-blue { background-color: var(--color-dark-blue); }
    .bg-accent-peach { background-color: var(--color-accent-peach); }
    .text-accent-peach { color: var(--color-accent-peach); }
    .bg-accent-sky { background-color: var(--color-accent-sky); }
    .text-accent-sky { color: var(--color-accent-sky); }
    .text-purple-700 { color: var(--color-purple-700); }

    /* Font Families */
    .font-vazirmatn { font-family: 'Vazirmatn', sans-serif; }
    .font-poppins { font-family: 'Poppins', sans-serif; }

    /* Custom animations */
    @keyframes bounce-custom {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-15px); }
    }
    .animate-bounce-custom { animation: bounce-custom 3s infinite ease-in-out; }

    .fade-in-section {
        opacity: 0;
        transform: translateY(30px);
        transition: opacity 0.8s ease-out, transform 0.8s ease-out;
    }

    .fade-in-section.is-visible {
        opacity: 1;
        transform: translateY(0);
    }

    /* Gradient backgrounds */
    .hero-gradient {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(59, 130, 246, 0.15) 100%);
    }

    .section-separator {
        width: 100px;
        height: 5px;
        background: linear-gradient(90deg, var(--color-earth-green), var(--color-ocean-blue), var(--color-digital-gold));
        border-radius: 5px;
        margin: 0 auto 2.5rem auto;
    }

    /* Help Guide Card Styles */
    .help-guide-card {
        background: linear-gradient(145deg, #ffffff 0%, #f0f4f7 100%);
        box-shadow: 0 12px 35px rgba(0, 0, 0, 0.08);
        transition: all 0.4s ease;
        border-radius: 18px;
        overflow: hidden;
        position: relative;
        border: 1px solid rgba(220, 220, 220, 0.3);
        margin-bottom: 1.5rem;
    }

    .help-guide-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 6px;
        background: linear-gradient(90deg, var(--color-earth-green), var(--color-ocean-blue), var(--color-digital-gold));
    }

    .help-guide-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 45px rgba(0, 0, 0, 0.15);
    }

    .help-guide-header {
        appearance: none;
        width: 100%;
        border: 0;
        text-align: right;
        color: inherit;
        font: inherit;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        padding: 1.25rem 1.5rem;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(59, 130, 246, 0.05) 100%);
        transition: background-color 0.25s ease;
    }

    .help-guide-header:focus-visible {
        outline: 3px solid var(--color-ocean-blue);
        outline-offset: -4px;
    }

    .help-guide-header-content { min-width: 0; }

    .help-guide-heading-copy {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: .25rem;
        text-align: right;
    }

    .help-guide-subtitle {
        color: #64748b;
        font-size: .9rem;
        font-weight: 400;
        line-height: 1.6;
    }

    .guide-intro {
        max-width: 54rem;
        margin-inline: auto;
        padding: 1.25rem 1.5rem;
        border: 1px solid rgba(16,185,129,.18);
        border-radius: 1rem;
        background: linear-gradient(135deg, rgba(16,185,129,.06), rgba(59,130,246,.05));
    }

    .guide-intro p { margin: 0; }

    .guide-note {
        display: flex;
        align-items: flex-start;
        gap: .75rem;
        margin-top: 1.25rem;
        padding: 1rem 1.1rem;
        border-radius: .85rem;
        line-height: 1.8;
    }

    .guide-note-info {
        color: #1e3a8a;
        background: #eff6ff;
        border-inline-start: 4px solid #3b82f6;
    }

    .guide-note i { margin-top: .35rem; flex: 0 0 auto; }

    .help-guide-content[hidden] { display: none !important; }

    .help-guide-header:hover {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(59, 130, 246, 0.1) 100%);
    }

    .help-guide-header.expanded {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(59, 130, 246, 0.15) 100%);
    }

    .help-guide-header-content {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }

    .help-guide-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        flex-shrink: 0;
    }

    .help-guide-icon.earth-green {
        background: rgba(16, 185, 129, 0.15);
        color: var(--color-earth-green);
    }

    .help-guide-icon.ocean-blue {
        background: rgba(59, 130, 246, 0.15);
        color: var(--color-ocean-blue);
    }

    .help-guide-icon.digital-gold {
        background: rgba(245, 158, 11, 0.15);
        color: var(--color-digital-gold);
    }

    .help-guide-icon.accent-peach {
        background: rgba(255, 126, 95, 0.15);
        color: var(--color-accent-peach);
    }

    .help-guide-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-gentle-black);
        font-family: 'Vazirmatn', sans-serif;
    }

    .help-guide-chevron {
        font-size: 1.25rem;
        color: var(--color-earth-green);
        transition: transform 0.3s ease;
    }

    .help-guide-header.expanded .help-guide-chevron {
        transform: rotate(180deg);
    }

    .help-guide-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.5s ease-out, padding 0.5s ease-out, opacity 0.5s ease-out;
        opacity: 0;
        padding: 0 2rem;
    }

    .help-guide-content.active {
        max-height: 5000px;
        padding: 2rem;
        opacity: 1;
    }

    .help-guide-content h3 {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--color-ocean-blue);
        margin-top: 1.5rem;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid rgba(59, 130, 246, 0.2);
        font-family: 'Vazirmatn', sans-serif;
    }

    .help-guide-content h3:first-child {
        margin-top: 0;
    }

    .help-guide-content h3 i {
        margin-left: 0.75rem;
        color: var(--color-earth-green);
    }

    .help-guide-content p {
        font-size: 1.05rem;
        line-height: 1.8;
        color: var(--color-gentle-black);
        text-align: justify;
        margin-bottom: 1rem;
        font-family: 'Vazirmatn', sans-serif;
    }

    .help-guide-content ul {
        list-style: none;
        padding-right: 1.5rem;
        margin-bottom: 1rem;
    }

    .help-guide-content ul li {
        position: relative;
        margin-bottom: 0.75rem;
        font-size: 1rem;
        color: var(--color-gentle-black);
        padding-right: 1.5rem;
        line-height: 1.7;
        font-family: 'Vazirmatn', sans-serif;
    }

    .help-guide-content ul li::before {
        content: "\f00c";
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        color: var(--color-earth-green);
        position: absolute;
        right: 0;
        top: 0.25rem;
    }

    .help-guide-content .highlight {
        background: linear-gradient(135deg, var(--color-earth-green), var(--color-ocean-blue));
        color: var(--color-pure-white);
        padding: 0.25rem 0.5rem;
        border-radius: 0.375rem;
        font-weight: 600;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .help-guide-content .note { margin-top: 1rem; }

    /* Page content styling */
    .page-content-help {
        text-align: justify;
        font-family: 'Vazirmatn', sans-serif;
    }

    .page-content-help h1,
    .page-content-help h2,
    .page-content-help h3 {
        color: var(--color-gentle-black);
        font-weight: 700;
        margin-top: 2rem;
        margin-bottom: 1rem;
    }

    .page-content-help h2 {
        color: var(--color-earth-green);
    }

    .page-content-help h3 {
        color: var(--color-ocean-blue);
    }

    .page-content-help p {
        margin-bottom: 1.5rem;
        line-height: 1.8;
    }

    .page-content-help ul,
    .page-content-help ol {
        margin-bottom: 1.5rem;
        padding-right: 2rem;
    }

    .page-content-help li {
        margin-bottom: 0.75rem;
    }

    .page-content-help a {
        color: var(--color-earth-green);
        text-decoration: underline;
    }

    .page-content-help a:hover {
        color: var(--color-dark-green);
    }

    .page-content-help img {
        max-width: 100%;
        height: auto;
        border-radius: 0.5rem;
        margin: 1.5rem 0;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    @media (max-width: 768px) {
        .help-guide-header {
            padding: 1.25rem 1.5rem;
        }

        .help-guide-icon {
            width: 50px;
            height: 50px;
            font-size: 1.5rem;
        }

        .help-guide-title {
            font-size: 1.25rem;
        }

        .help-guide-content {
            padding: 0 1.5rem;
        }

        .help-guide-content.active {
            padding: 1.5rem;
        }
    }
</style>
@endpush

@section('content')
<main>
    <!-- Hero Section for Help Page -->
    <section class="relative hero-gradient py-20 md:py-32 overflow-hidden fade-in-section text-center">
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute top-0 right-0 w-full h-full bg-pure-white/5 to-transparent z-0"></div>
            <div class="absolute inset-0 bg-pure-white/10 backdrop-blur-sm z-0"></div>
        </div>

        <div class="container mx-auto px-6 relative z-10">
            <h1 class="text-4xl md:text-6xl lg:text-7xl font-extrabold text-gentle-black font-vazirmatn mb-6 leading-tight">
                {{ $page->translated_title }}
            </h1>
            <div class="section-separator"></div>
            @if($page->meta_description)
                <p class="text-lg md:text-xl text-gray-700 mb-8 max-w-3xl font-vazirmatn mx-auto">
                    {{ $page->translated_meta_description ?? $page->meta_description }}
                </p>
            @endif
        </div>
    </section>

    <!-- Short introduction: detailed guidance lives in the three accordion cards below. -->
    @if($page->translated_content)
    <section class="py-10 md:py-12 bg-pure-white fade-in-section">
        <div class="container mx-auto px-6">
            <div class="max-w-4xl mx-auto">
                <div class="page-content-help guide-intro text-base md:text-lg text-gray-700 leading-relaxed">
                    {!! $page->translated_content !!}
                </div>
            </div>
        </div>
    </section>
    @endif

    <!-- Progressive, task-oriented guide -->
    <section class="py-10 md:py-16 bg-light-gray fade-in-section" aria-label="راهنمای استفاده">
        <div class="container mx-auto px-4 sm:px-6">
            <div class="max-w-5xl mx-auto">
                <!-- Card 1: Agreement and account creation -->
                <article class="help-guide-card">
                    <button type="button" class="help-guide-header" id="guide-heading-1" data-card="1" aria-expanded="false" aria-controls="content-1">
                        <span class="help-guide-header-content">
                            <span class="help-guide-icon earth-green" aria-hidden="true"><i class="fas fa-user-plus"></i></span>
                            <span class="help-guide-heading-copy">
                                <span class="help-guide-title">پذیرش شرایط و آغاز ثبت‌نام</span>
                                <span class="help-guide-subtitle">از پذیرش مقررات تا تأیید حساب</span>
                            </span>
                        </span>
                        <i class="fas fa-chevron-down help-guide-chevron" aria-hidden="true"></i>
                    </button>
                    <div class="help-guide-content" id="content-1" role="region" aria-labelledby="guide-heading-1" hidden>
                        <p>برای آغاز عضویت، ابتدا اساسنامه، شرایط استفاده و مفاد حقوقی نمایش‌داده‌شده را با دقت مطالعه و پذیرش خود را ثبت کنید.</p>
                        <h3><i class="fas fa-clipboard-check" aria-hidden="true"></i> کد دعوت و ایجاد حساب</h3>
                        <p>اگر در زمان ثبت‌نام کد دعوت الزامی باشد، یک کد معتبر، استفاده‌نشده و منقضی‌نشده وارد کنید. الزام به کد دعوت تابع تنظیمات جاری سامانه است.</p>
                        <p>سپس حساب خود را با واردکردن نشانی ایمیل و تعیین رمز عبور ایجاد کنید یا، اگر این گزینه در صفحه ارائه شده است، از ورود با حساب گوگل استفاده کنید. اگر سامانه تأیید ایمیل را درخواست کرد، پیش از ادامه آن را تکمیل کنید.</p>
                        <div class="guide-note guide-note-info">
                            <i class="fas fa-info-circle" aria-hidden="true"></i>
                            <span>مراحل و گزینه‌های در دسترس ممکن است با توجه به تنظیمات فعال سامانه متفاوت باشند. پیام‌های همان صفحه را مبنا قرار دهید.</span>
                        </div>
                    </div>
                </article>

                <!-- Card 2: Registration details -->
                <article class="help-guide-card">
                    <button type="button" class="help-guide-header" id="guide-heading-2" data-card="2" aria-expanded="false" aria-controls="content-2">
                        <span class="help-guide-header-content">
                            <span class="help-guide-icon ocean-blue" aria-hidden="true"><i class="fas fa-clipboard-list"></i></span>
                            <span class="help-guide-heading-copy">
                                <span class="help-guide-title">مراحل ثبت‌نام در EarthCoop</span>
                                <span class="help-guide-subtitle">اطلاعات هویتی، زمینه‌های فعالیت و محل سکونت</span>
                            </span>
                        </span>
                        <i class="fas fa-chevron-down help-guide-chevron" aria-hidden="true"></i>
                    </button>
                    <div class="help-guide-content" id="content-2" role="region" aria-labelledby="guide-heading-2" hidden>
                        <h3><i class="fas fa-id-card" aria-hidden="true"></i> ۱. اطلاعات هویتی</h3>
                        <p>فیلدهای هویتی نمایش‌داده‌شده را دقیق و مطابق قالب فرم تکمیل کنید. این اطلاعات می‌تواند شامل موارد زیر باشد:</p>
                        <ul>
                            <li>نام و نام خانوادگی؛</li>
                            <li>تاریخ تولد و جنسیت؛</li>
                            <li>ملیت؛</li>
                            <li>کد ملی، در صورت درخواست فرم؛</li>
                            <li>شماره تلفن همراه و کد کشور.</li>
                        </ul>
                        <p>سامانه برخی شناسه‌ها و شماره‌های تماس را برای جلوگیری از ثبت اطلاعات تکراری اعتبارسنجی می‌کند. تاریخ تولد و جنسیت نیز در تعیین برخی ابعاد عضویت گروهی کاربرد دارند.</p>

                        <h3><i class="fas fa-briefcase" aria-hidden="true"></i> ۲. زمینه‌های صنفی و تخصصی</h3>
                        <p>زمینه‌های شغلی و صنفی و نیز زمینه‌های علمی یا تجربی مرتبط با خود را از فهرست‌های موجود انتخاب کنید. انتخاب‌ها به‌صورت سلسله‌مراتبی ارائه می‌شوند و امکان انتخاب چند زمینه، مطابق امکانات فرم، وجود دارد.</p>
                        <p>این اطلاعات به تعیین عضویت‌های صنفی و تخصصی شما کمک می‌کنند. در صورت تغییر زمینه‌های فعالیت در آینده، عضویت‌های مرتبط نیز ممکن است بر اساس اطلاعات جدید تغییر کنند.</p>

                        <h3><i class="fas fa-map-marked-alt" aria-hidden="true"></i> ۳. محل سکونت اصلی</h3>
                        <p>مسیر معتبر محل سکونت را از میان گزینه‌های ارائه‌شده انتخاب کنید. توالی سطوح مکانی در همه مناطق یکسان نیست؛ انتخاب‌گر بر اساس ساختار معتبر و داده‌های موجود، مسیر قابل انتخاب را نشان می‌دهد.</p>
                        <p>ثبت‌نام در سطحی پایان می‌یابد که سامانه برای مسیر انتخاب‌شده به‌عنوان پایه معتبر محل سکونت می‌پذیرد. الزام همیشگی به انتخاب محله یا ثبت خیابان، کوچه، مجتمع و ساختمان را فرض نکنید؛ سطح قابل ثبت به ساختار همان مکان بستگی دارد.</p>
                        <p>اگر مکان موردنظر در فهرست نیست، از گزینه پیشنهاد مکان استفاده کنید. پیشنهادها ممکن است برای جمع‌آوری شواهد یا بررسی انسانی در انتظار بمانند. حمایت کاربران به‌تنهایی باعث تأیید خودکار مکان نمی‌شود.</p>
                        <div class="guide-note guide-note-info">
                            <i class="fas fa-map-pin" aria-hidden="true"></i>
                            <span>پس از ثبت محل سکونت، می‌توانید از بخش «مکان و حکمرانی من» وضعیت مکانی و گزینه‌های تکمیلی موجود را بررسی کنید.</span>
                        </div>
                    </div>
                </article>

                <!-- Card 3: Dashboard, groups and governance -->
                <article class="help-guide-card">
                    <button type="button" class="help-guide-header" id="guide-heading-3" data-card="3" aria-expanded="false" aria-controls="content-3">
                        <span class="help-guide-header-content">
                            <span class="help-guide-icon digital-gold" aria-hidden="true"><i class="fas fa-house-user"></i></span>
                            <span class="help-guide-heading-copy">
                                <span class="help-guide-title">خانهٔ شما در EarthCoop</span>
                                <span class="help-guide-subtitle">حساب کاربری، گروه‌ها، اجتماع محلی و حکمرانی</span>
                            </span>
                        </span>
                        <i class="fas fa-chevron-down help-guide-chevron" aria-hidden="true"></i>
                    </button>
                    <div class="help-guide-content" id="content-3" role="region" aria-labelledby="guide-heading-3" hidden>
                        <h3><i class="fas fa-user-circle" aria-hidden="true"></i> حساب کاربری</h3>
                        <p>از بخش حساب کاربری برای مشاهده و ویرایش اطلاعاتی که امکان تغییرشان فراهم شده است استفاده کنید. بسته به امکانات فعال، ممکن است بتوانید اطلاعات تکمیلی، تصویر، معرفی کوتاه یا پیوندهای خود را مدیریت کنید. تنظیمات نمایش اطلاعات را با توجه به حریم خصوصی خود بررسی کنید.</p>
                        <p>تغییر زمینه‌های فعالیت یا محل سکونت اصلی می‌تواند بر عضویت‌های گروهی و جایگاه مکانی شما اثر بگذارد.</p>

                        <h3><i class="fas fa-layer-group" aria-hidden="true"></i> گروه‌های من</h3>
                        <p>عضویت‌های شما در ابعاد مختلف سازمان‌دهی می‌شوند:</p>
                        <ul>
                            <li><strong>عمومی و مکانی:</strong> مرتبط با محل سکونت اصلی و ساختار رسمی حکمرانی؛</li>
                            <li><strong>صنفی:</strong> مرتبط با زمینه‌های شغلی و صنفی؛</li>
                            <li><strong>تخصصی:</strong> مرتبط با زمینه‌های علمی و تجربی؛</li>
                            <li><strong>سنی و جنسیتی:</strong> بر اساس دسته‌بندی‌های تعریف‌شده در سامانه.</li>
                        </ul>
                        <p>همه گروه‌های قابل تصور لزوماً ایجاد نشده‌اند و نوع دسترسی شما به وضعیت عضویت و نقش‌تان در هر گروه بستگی دارد. مشاهده یک گروه به‌تنهایی به معنای داشتن حق رأی یا اختیار مدیریتی در آن نیست.</p>

                        <h3><i class="fas fa-map-marked-alt" aria-hidden="true"></i> مکان و حکمرانی من</h3>
                        <p>این بخش برای مشاهده و مدیریت محل سکونت و جایگاه شما در ساختار رسمی حکمرانی است. محل سکونت اصلی تأییدشده مبنای مهمی برای تعیین حوزه‌های رسمی مکانی و حقوق مشارکت در آن‌هاست. روابطی مانند محل کار یا تحصیل، به‌خودی‌خود جایگزین محل سکونت اصلی برای رأی‌دادن در حکمرانی رسمی نمی‌شوند.</p>
                        <p><strong>اجتماع محلی با حکمرانی رسمی یکسان نیست.</strong> در صورت وجود گزینه، ممکن است بتوانید به اجتماع اختیاری مرتبط با خیابان، کوچه، مجتمع یا ساختمان بپیوندید یا از آن خارج شوید. این عضویت خودکار نیست و اجتماع محلی به‌طور خودکار به سطحی از انتخابات رسمی تبدیل نمی‌شود.</p>

                        <h3><i class="fas fa-comments" aria-hidden="true"></i> فعالیت در گروه و گفت‌وگوی گروهی</h3>
                        <p>در گروه‌هایی که برای نقش شما مجاز است، امکاناتی مانند انتشار پست، ثبت نظر، نظرسنجی یا رأی‌گیری می‌تواند در دسترس باشد. قابلیت‌های قابل استفاده را از همان صفحه گروه بررسی کنید.</p>
                        <p>مشاهده گروه و ارسال پیام در گفت‌وگوی گروهی دو قابلیت متفاوت‌اند. ارسال پیام در گفت‌وگوی گروهی به داشتن حساب نجم بهار و پرداخت حق عضویت دوره جاری وابسته است؛ در صورت تکمیل‌نبودن این پیش‌نیاز، سامانه مسیر مربوط را نمایش می‌دهد.</p>

                        <h3><i class="fas fa-user-friends" aria-hidden="true"></i> دعوت از اعضای جدید</h3>
                        <p>سهمیه دعوت، مدت اعتبار کدها و امتیاز دعوت موفق تابع تنظیمات و قواعد جاری سامانه‌اند و ممکن است تغییر کنند. برای اطلاع از مقادیر فعلی، بخش دعوت را بررسی کنید.</p>
                        <p>صدور دعوت می‌تواند به تکمیل اطلاعات پروفایل و واجد شرایط بودن حساب نجم بهار، از جمله پرداخت حق عضویت دوره جاری، وابسته باشد. اگر پیش‌نیازی کامل نباشد، ابتدا دستور نمایش‌داده‌شده را دنبال کنید.</p>

                        <h3><i class="fas fa-vote-yea" aria-hidden="true"></i> انتخابات و مشارکت دموکراتیک</h3>
                        <p>انتخابات رسمی در چارچوب ساختار حکمرانی و بر اساس شرایط مشارکت در حوزه مربوط برگزار می‌شود. برای شرکت، وضعیت انتخابات، شرایط احراز صلاحیت و دستورهای صفحه را بررسی کنید و در صورت بازبودن انتخابات و واجد شرایط بودن، رأی خود را از همان فرم ثبت کنید.</p>
                        <p>آغاز و پایان انتخابات، شمارش آرا و مراحل بعدی تابع چرخه رسمی سامانه است؛ بنابراین نباید حدنصاب یا دوره زمانی ثابتی را برای همه گروه‌ها فرض کرد.</p>
                        <div class="guide-note guide-note-info">
                            <i class="fas fa-lightbulb" aria-hidden="true"></i>
                            <span>اگر گزینه‌ای را نمی‌بینید، ممکن است به نقش شما، وضعیت عضویت، سیاست فعال گروه یا تکمیل‌نبودن یک پیش‌نیاز مربوط باشد.</span>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>
</main>

@push('scripts')
<script>
    // Smooth scroll animation logic
    document.addEventListener('DOMContentLoaded', () => {
        const sections = document.querySelectorAll('.fade-in-section');

        const observerOptions = {
            root: null,
            rootMargin: '0px',
            threshold: 0.1
        };

        const observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        sections.forEach(section => {
            observer.observe(section);
        });

        // Accessible single-open accordion for help guide cards
        const helpGuideHeaders = document.querySelectorAll('.help-guide-header');

        const closeGuideCard = (header) => {
            const contentId = header.getAttribute('aria-controls');
            const content = document.getElementById(contentId);
            if (!content) return;
            header.classList.remove('expanded');
            header.setAttribute('aria-expanded', 'false');
            content.classList.remove('active');
            content.hidden = true;
        };

        helpGuideHeaders.forEach((header) => {
            header.addEventListener('click', () => {
                const wasExpanded = header.getAttribute('aria-expanded') === 'true';

                helpGuideHeaders.forEach((otherHeader) => {
                    if (otherHeader !== header) closeGuideCard(otherHeader);
                });

                const content = document.getElementById(header.getAttribute('aria-controls'));
                if (!content) return;

                if (wasExpanded) {
                    closeGuideCard(header);
                    return;
                }

                header.classList.add('expanded');
                header.setAttribute('aria-expanded', 'true');
                content.hidden = false;
                content.classList.add('active');

                window.setTimeout(() => {
                    const headerElement = document.getElementById('header');
                    const headerHeight = headerElement ? headerElement.offsetHeight : 0;
                    const targetPosition = header.getBoundingClientRect().top + window.scrollY - headerHeight - 24;
                    window.scrollTo({ top: targetPosition, behavior: 'smooth' });
                }, 100);
            });
        });
    });
</script>
@endpush
@endsection