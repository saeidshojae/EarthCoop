@extends('layouts.unified')

@section('title', $page->translated_title)
<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
@push('styles')
<style>
    /* Custom Tailwind Configuration - Color and font configuration */
    :root {
        --color-earth-green: #10b981; /* Earth Green */
        --color-ocean-blue: #3b82f6; /* Ocean Blue */
        --color-digital-gold: #f59e0b; /* Digital Gold */
        --color-pure-white: #ffffff; /* Pure White */
        --color-light-gray: #f8fafc; /* Light Gray */
        --color-gentle-black: #1e293b; /* Gentle Black */
        --color-dark-green: #047857; /* Dark Green */
        --color-dark-blue: #1d4ed8; /* Dark Blue */
        --color-accent-peach: #ff7e5f; /* Accent Peach */
        --color-accent-sky: #6dd5ed; /* Accent Sky */
        --color-purple-700: #6B46C1; /* Dark Purple for hover */
        --color-dark-gold: #d97706; /* Darker gold for hover state */
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
    .bg-dark-gold { background-color: var(--color-dark-gold); }

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

    /* Specific styling for this page */
    .about-section-card {
        background: linear-gradient(145deg, #ffffff 0%, #f0f4f7 100%);
        box-shadow: 0 12px 35px rgba(0, 0, 0, 0.08);
        transition: all 0.4s ease;
        border-radius: 18px;
        overflow: hidden;
        position: relative;
        border: 1px solid rgba(220, 220, 220, 0.3);
    }

    .about-section-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 20px 45px rgba(0, 0, 0, 0.15);
    }

    .about-section-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 6px;
        background: linear-gradient(90deg, var(--color-earth-green), var(--color-ocean-blue), var(--color-digital-gold));
    }

    .icon-box {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    /* Styling for the 3D globe canvas */
    #globe-canvas {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 100%;
        height: 100%;
        max-width: 800px;
        max-height: 800px;
        z-index: 0;
        opacity: 0.2;
    }

    @media (max-width: 768px) {
        #globe-canvas {
            width: 100%;
            height: 100%;
        }
    }
</style>
@endpush

@section('content')
<main>
    <!-- Hero Section for About Page -->
    <section class="relative hero-gradient py-20 md:py-32 overflow-hidden fade-in-section text-center">
        <!-- Canvas for rendering the 3D globe -->
        <canvas id="globe-canvas"></canvas>

        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute top-0 right-0 w-full h-full bg-pure-white/5 to-transparent z-0"></div>
            <div class="absolute inset-0 bg-pure-white/10 backdrop-blur-sm z-0"></div>
        </div>

        <div class="container mx-auto px-6 relative z-10">
            <h1 class="text-4xl md:text-6xl lg:text-7xl font-extrabold text-gentle-black font-vazirmatn mb-6 leading-tight">
                {{ $page->translated_title }}
            </h1>
            @if($page->meta_description)
                <p class="text-lg md:text-xl text-gray-700 mb-8 max-w-3xl font-vazirmatn mx-auto">
                    {{ $page->translated_meta_description ?? $page->meta_description }}
                </p>
            @endif
        </div>
    </section>

    <!-- Section 1: Welcome to EarthCoop Global Partnership System -->
    <section class="py-16 md:py-24 bg-pure-white fade-in-section">
        <div class="container mx-auto px-6 text-center">
            <div class="max-w-4xl mx-auto mb-12">
                <h2 class="text-3xl md:text-5xl font-extrabold font-vazirmatn text-gentle-black mb-6">
                    به سامانه شراکت جهانی <span class="text-earth-green">ارث‌کوپ</span> خوش آمدید
                </h2>
                <div class="section-separator"></div>
                <div class="text-lg md:text-xl text-gray-700 font-vazirmatn leading-relaxed mb-8 text-right">
                    {!! $page->translated_content !!}
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 text-right">
                    <div class="about-section-card p-8 flex flex-col items-center group text-center">
                        <div class="icon-box bg-earth-green/15 text-earth-green transform group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-house-chimney"></i>
                        </div>
                        <h3 class="text-2xl font-bold font-vazirmatn text-gentle-black mb-3">زمین، خانه مشترک همه‌ی ماست.</h3>
                        <p class="text-gray-700 text-center font-vazirmatn">
                            خانه‌ای که ما در آن وارثانی برابر هستیم و در مالکیت، حقوق و مسئولیت‌های آن شریک یکدیگریم.
                        </p>
                    </div>
                    <div class="about-section-card p-8 flex flex-col items-center group text-center">
                        <div class="icon-box bg-ocean-blue/15 text-ocean-blue transform group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <h3 class="text-2xl font-bold font-vazirmatn text-gentle-black mb-3">شراکت جهانی، آغاز جامعه همکارانه</h3>
                        <p class="text-gray-700 text-center font-vazirmatn">
                            این شراکت جهانی، نقطه آغاز ساخت یک جامعه همکارانه و هم‌سرنوشت است که همه‌ی مردم زمین را دربرمی‌گیرد.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section 2: What is EarthCoop? -->
    <section class="py-16 md:py-24 bg-light-gray fade-in-section">
        <div class="container mx-auto px-6 text-center">
            <div class="max-w-4xl mx-auto mb-12">
                <h2 class="text-3xl md:text-5xl font-extrabold font-vazirmatn text-gentle-black mb-6">
                    <span class="text-ocean-blue">ارث‌کوپ</span> چیست؟
                </h2>
                <div class="section-separator"></div>
                <p class="text-lg md:text-xl text-gray-700 font-vazirmatn leading-relaxed mb-8">
                    ارث‌کوپ یک شبکه مشارکت جهانی، با ساختار قانونی و شفاف است که برای همکاری، هم‌فکری و سرمایه‌گذاری جمعی از محله تا سیاره طراحی شده است.
                </p>
                <p class="text-lg md:text-xl text-gray-700 font-vazirmatn leading-relaxed mb-12 font-bold">
                    با عضویت در ارث‌کوپ، شما یکی از سهامداران واقعی این شبکه خواهید بود.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 text-right mb-12">
                    <div class="about-section-card p-8 flex flex-col items-center group text-center">
                        <div class="icon-box bg-digital-gold/15 text-digital-gold transform group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-id-card"></i>
                        </div>
                        <h3 class="text-2xl font-bold font-vazirmatn text-gentle-black mb-3">هویتی</h3>
                        <p class="text-gray-700 text-center font-vazirmatn">(نام، سن، جنسیت و ...)</p>
                    </div>
                    <div class="about-section-card p-8 flex flex-col items-center group text-center">
                        <div class="icon-box bg-earth-green/15 text-earth-green transform group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <h3 class="text-2xl font-bold font-vazirmatn text-gentle-black mb-3">زمینه‌های شغلی و تخصصی</h3>
                        <p class="text-gray-700 text-center font-vazirmatn">شغلی، صنفی، تخصصی و تجربی</p>
                    </div>
                    <div class="about-section-card p-8 flex flex-col items-center group text-center">
                        <div class="icon-box bg-ocean-blue/15 text-ocean-blue transform group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-location-dot"></i>
                        </div>
                        <h3 class="text-2xl font-bold font-vazirmatn text-gentle-black mb-3">موقعیت مکانی دقیق</h3>
                        <p class="text-gray-700 text-center font-vazirmatn">(از قاره تا کوچه)</p>
                    </div>
                </div>

                <h3 class="text-2xl md:text-3xl font-extrabold font-vazirmatn text-gentle-black mb-6">
                    بر این اساس، شما در سه نوع گروه سیستمی عضو می‌شوید:
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-right">
                    <div class="about-section-card p-8 flex flex-col items-center group text-center">
                        <div class="icon-box bg-earth-green/15 text-earth-green transform group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="text-2xl font-bold font-vazirmatn text-gentle-black mb-3">گروه‌های مجامع عمومی</h3>
                        <p class="text-gray-700 text-center font-vazirmatn">(بر اساس مکان سکونت)</p>
                    </div>
                    <div class="about-section-card p-8 flex flex-col items-center group text-center">
                        <div class="icon-box bg-ocean-blue/15 text-ocean-blue transform group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-user-gear"></i>
                        </div>
                        <h3 class="text-2xl font-bold font-vazirmatn text-gentle-black mb-3">گروه‌های تخصصی</h3>
                        <p class="text-gray-700 text-center font-vazirmatn">(بر اساس صنف و تخصص)</p>
                    </div>
                    <div class="about-section-card p-8 flex flex-col items-center group text-center">
                        <div class="icon-box bg-digital-gold/15 text-digital-gold transform group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-people-arrows"></i>
                        </div>
                        <h3 class="text-2xl font-bold font-vazirmatn text-gentle-black mb-3">گروه‌های اختصاصی</h3>
                        <p class="text-gray-700 text-center font-vazirmatn">(بر اساس سن و جنسیت)</p>
                    </div>
                </div>
                <p class="text-lg md:text-xl text-gray-700 font-vazirmatn leading-relaxed mt-12">
                    هر گروه، نقشی مشخص در توسعه پروژه‌های محلی و جهانی خواهد داشت و در چارچوب شفاف، مسئولانه و مشارکتی فعالیت می‌کند.
                </p>
            </div>
        </div>
    </section>

    <!-- Section 3: Invitation to Membership -->
    <section class="relative py-20 md:py-32 bg-gradient-to-br from-earth-green to-ocean-blue text-pure-white text-center fade-in-section">
        <div class="absolute inset-0 bg-black/50 z-0"></div>
        <div class="container mx-auto px-6 relative z-10">
            <h2 class="text-4xl md:text-5xl lg:text-6xl font-extrabold font-vazirmatn mb-8 leading-tight animate-pulse">
                دعوت به عضویت
            </h2>
            <p class="text-xl md:text-2xl mb-12 max-w-4xl mx-auto font-vazirmatn opacity-90">
                اگر شما هم به ساختن دنیایی بهتر برای خود، فرزندانتان و همه‌ی ساکنان این سیاره باور دارید، همین امروز عضویت بالقوه‌ی خود را در <span class="font-bold text-digital-gold">ارث‌کوپ</span> فعال کنید.
            </p>
            <a href="{{ route('register.form') }}" class="bg-digital-gold text-pure-white px-12 py-5 rounded-full shadow-2xl hover:bg-opacity-90 hover:scale-105 transition duration-300 font-vazirmatn text-xl font-bold inline-flex items-center justify-center">
                با هم، آینده‌ای متفاوت بسازیم <i class="fas fa-hand-holding-seedling mr-3"></i>
            </a>
        </div>
    </section>
</main>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
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

    // Three.js Globe setup
    let scene, camera, renderer, globe, lines;

    function initGlobe() {
        const canvas = document.getElementById('globe-canvas');
        if (!canvas) return;

        try {
            // Scene
            scene = new THREE.Scene();

            // Camera
            camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
            camera.position.z = 2;

            // Renderer
            renderer = new THREE.WebGLRenderer({ canvas: canvas, alpha: true });
            renderer.setPixelRatio(window.devicePixelRatio);
            resizeGlobe();

            // Globe
            const geometry = new THREE.SphereGeometry(1, 32, 32);
            const material = new THREE.MeshBasicMaterial({ color: 0x3b82f6, transparent: true, opacity: 0.8 });
            globe = new THREE.Mesh(geometry, material);
            scene.add(globe);

            // Lines
            const lineMaterial = new THREE.LineBasicMaterial({ color: 0x10b981, linewidth: 2 });
            const points = [];
            for (let i = 0; i <= 20; i++) {
                const lat = (Math.random() * Math.PI) - Math.PI / 2;
                const lon = (Math.random() * Math.PI * 2) - Math.PI;
                points.push(new THREE.Vector3(
                    Math.cos(lat) * Math.cos(lon),
                    Math.sin(lat),
                    Math.cos(lat) * Math.sin(lon)
                ).multiplyScalar(1.01));
            }
            const lineGeometry = new THREE.BufferGeometry().setFromPoints(points);
            lines = new THREE.Line(lineGeometry, lineMaterial);
            scene.add(lines);

            // Lighting
            const ambientLight = new THREE.AmbientLight(0x404040);
            scene.add(ambientLight);
            const directionalLight = new THREE.DirectionalLight(0xffffff, 0.5);
            directionalLight.position.set(1, 1, 1).normalize();
            scene.add(directionalLight);
        } catch (e) {
            console.error('Error initializing globe:', e);
        }
    }

    function animateGlobe() {
        requestAnimationFrame(animateGlobe);
        if (globe && lines && renderer && scene && camera) {
            globe.rotation.y += 0.002;
            lines.rotation.y += 0.002;
            renderer.render(scene, camera);
        }
    }

    function resizeGlobe() {
        if (!renderer || !camera) return;
        const canvas = renderer.domElement;
        const width = canvas.clientWidth;
        const height = canvas.clientHeight;

        if (canvas.width !== width || canvas.height !== height) {
            renderer.setSize(width, height, false);
            camera.aspect = width / height;
            camera.updateProjectionMatrix();
        }
    }

    window.addEventListener('resize', resizeGlobe);

    // Initialize globe when page loads
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                initGlobe();
                animateGlobe();
            }, 100);
        });
    } else {
        setTimeout(() => {
            initGlobe();
            animateGlobe();
        }, 100);
    }
</script>
@endpush
@endsection

