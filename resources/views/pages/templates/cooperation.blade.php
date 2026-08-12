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
    .section-separator {
        width: 100px;
        height: 5px;
        background: linear-gradient(90deg, var(--color-earth-green), var(--color-ocean-blue), var(--color-digital-gold));
        border-radius: 5px;
        margin: 0 auto 2.5rem auto;
    }

    /* Specific styles for the cooperation page */
    .cooperation-card {
        background: linear-gradient(145deg, #ffffff 0%, #f0f4f7 100%);
        box-shadow: 0 12px 35px rgba(0, 0, 0, 0.08);
        transition: all 0.4s ease;
        border-radius: 18px;
        overflow: hidden;
        position: relative;
        border: 1px solid rgba(220, 220, 220, 0.3);
    }

    .cooperation-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 20px 45px rgba(0, 0, 0, 0.15);
    }

    .cooperation-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 6px;
        background: linear-gradient(90deg, var(--color-earth-green), var(--color-ocean-blue), var(--color-digital-gold));
    }

    .email-label {
        background: linear-gradient(90deg, var(--color-digital-gold), var(--color-accent-peach));
        color: var(--color-pure-white);
        padding: 1.5rem 2.5rem;
        border-radius: 1.5rem;
        box-shadow: 0 15px 30px rgba(245, 158, 11, 0.4), 0 0 40px rgba(245, 158, 11, 0.2);
        transition: all 0.3s ease-in-out;
        cursor: pointer;
        position: relative;
        overflow: hidden;
        animation: email-glow 2s infinite alternate;
    }

    .email-label:hover {
        transform: scale(1.03);
        box-shadow: 0 20px 40px rgba(245, 158, 11, 0.6), 0 0 50px rgba(245, 158, 11, 0.3);
    }

    .email-label::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.2);
        transform: skewX(-20deg);
        transition: all 0.5s ease;
    }

    .email-label:hover::before {
        left: 100%;
    }

    @keyframes email-glow {
        0% { box-shadow: 0 0 10px rgba(245, 158, 11, 0.5), 0 0 20px rgba(245, 158, 11, 0.3); }
        50% { box-shadow: 0 0 20px rgba(245, 158, 11, 0.8), 0 0 30px rgba(245, 158, 11, 0.5); }
        100% { box-shadow: 0 0 10px rgba(245, 158, 11, 0.5), 0 0 20px rgba(245, 158, 11, 0.3); }
    }

    /* Page content styling */
    .page-content-cooperation {
        text-align: justify;
    }

    .page-content-cooperation h1,
    .page-content-cooperation h2,
    .page-content-cooperation h3 {
        color: var(--color-gentle-black);
        font-weight: 700;
        margin-top: 2rem;
        margin-bottom: 1rem;
    }

    .page-content-cooperation h2 {
        color: var(--color-earth-green);
    }

    .page-content-cooperation h3 {
        color: var(--color-ocean-blue);
    }

    .page-content-cooperation p {
        margin-bottom: 1.5rem;
        line-height: 1.8;
    }

    .page-content-cooperation ul,
    .page-content-cooperation ol {
        margin-bottom: 1.5rem;
        padding-right: 2rem;
    }

    .page-content-cooperation li {
        margin-bottom: 0.75rem;
    }

    .page-content-cooperation a {
        color: var(--color-earth-green);
        text-decoration: underline;
    }

    .page-content-cooperation a:hover {
        color: var(--color-dark-green);
    }

    .page-content-cooperation img {
        max-width: 100%;
        height: auto;
        border-radius: 0.5rem;
        margin: 1.5rem 0;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
</style>
@endpush

@section('content')
<main>
    <!-- Cooperation Opportunities Section -->
    <section class="py-16 md:py-24 bg-pure-white fade-in-section">
        <div class="container mx-auto px-6 text-center">
            <div class="max-w-4xl mx-auto mb-12">
                <h1 class="text-4xl md:text-5xl font-extrabold font-vazirmatn text-gentle-black mb-6">
                    {{ $page->translated_title }}
                </h1>
                <div class="section-separator"></div>

                @if($page->meta_description)
                    <p class="text-lg md:text-xl text-gray-700 font-vazirmatn leading-relaxed mb-10">
                        {{ $page->translated_meta_description ?? $page->meta_description }}
                    </p>
                @endif

                <!-- Dynamic Content -->
                <div class="text-lg md:text-xl text-gray-700 font-vazirmatn leading-relaxed mb-10 text-right">
                    {!! $page->translated_content !!}
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-8 mb-16">
                <!-- Skill Card 1 -->
                <div class="cooperation-card p-8 flex flex-col items-center group text-center">
                    <div class="w-24 h-24 bg-earth-green/15 rounded-full flex items-center justify-center text-4xl text-earth-green mb-6 transform group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-palette"></i>
                    </div>
                    <h3 class="text-2xl font-bold font-vazirmatn text-gentle-black mb-3">گرافیست و طراح رابط کاربری</h3>
                    <p class="text-gray-700 text-center font-vazirmatn">
                        خلاقیت خود را در طراحی رابط‌های کاربری زیبا و کاربرپسند به کار گیرید.
                    </p>
                </div>

                <!-- Skill Card 2 -->
                <div class="cooperation-card p-8 flex flex-col items-center group text-center">
                    <div class="w-24 h-24 bg-ocean-blue/15 rounded-full flex items-center justify-center text-4xl text-ocean-blue mb-6 transform group-hover:scale-110 transition-transform duration-300">
                        <i class="fab fa-android"></i>
                        <i class="fab fa-apple ml-2"></i>
                    </div>
                    <h3 class="text-2xl font-bold font-vazirmatn text-gentle-black mb-3">برنامه نویس اپلیکیشن اندروید و آی او آس</h3>
                    <p class="text-gray-700 text-center font-vazirmatn">
                        به تیم ما در توسعه اپلیکیشن‌های موبایل قدرتمند و کارآمد بپیوندید.
                    </p>
                </div>

                <!-- Skill Card 3 -->
                <div class="cooperation-card p-8 flex flex-col items-center group text-center">
                    <div class="w-24 h-24 bg-digital-gold/15 rounded-full flex items-center justify-center text-4xl text-digital-gold mb-6 transform group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-robot"></i>
                    </div>
                    <h3 class="text-2xl font-bold font-vazirmatn text-gentle-black mb-3">توسعه دهنده دستیارهای هوش مصنوعی</h3>
                    <p class="text-gray-700 text-center font-vazirmatn">
                        در ساخت دستیارهای هوشمند و نوآورانه با ما همکاری کنید.
                    </p>
                </div>

                <!-- Skill Card 4 -->
                <div class="cooperation-card p-8 flex flex-col items-center group text-center">
                    <div class="w-24 h-24 bg-accent-peach/15 rounded-full flex items-center justify-center text-4xl text-accent-peach mb-6 transform group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-chart-pie"></i>
                        <i class="fas fa-film ml-2"></i>
                    </div>
                    <h3 class="text-2xl font-bold font-vazirmatn text-gentle-black mb-3">سازنده اینفوگرافی و موشن گرافی</h3>
                    <p class="text-gray-700 text-center font-vazirmatn">
                        با ساخت محتوای بصری جذاب، پیام ما را به بهترین شکل منتقل کنید.
                    </p>
                </div>
            </div>

            <!-- Email Label -->
            <div class="flex justify-center">
                <div class="email-label text-xl sm:text-2xl md:text-4xl font-extrabold font-poppins flex flex-wrap items-center justify-center gap-2 md:gap-4 rtl:space-x-reverse" onclick="copyEmail()">
                    <i class="fas fa-envelope text-3xl sm:text-4xl md:text-5xl"></i>
                    <span class="break-all">Team@EarthCoop.ir</span>
                </div>
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
    });

    // Copy email to clipboard
    function copyEmail() {
        const email = 'Team@EarthCoop.ir';
        navigator.clipboard.writeText(email).then(() => {
            alert('ایمیل کپی شد: ' + email);
        }).catch(err => {
            console.error('Error copying email:', err);
        });
    }
</script>
@endpush
@endsection