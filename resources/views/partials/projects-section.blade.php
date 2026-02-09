<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
<!-- Profitable Public Projects - بخش پروژه‌های عمومی سودآور -->
<section id="projects" class="py-16 md:py-24 bg-pure-white fade-in-section">
    <div class="container mx-auto px-6 text-center">
        <div class="max-w-4xl mx-auto mb-16">
            <h2 class="text-3xl md:text-5xl font-extrabold font-vazirmatn text-gentle-black mb-6">
                {{ __('langWelcome.projects_title') }}
            </h2>
            <div class="section-separator"></div>
            <p class="text-lg md:text-xl text-gray-700 mb-8 font-vazirmatn leading-relaxed">
                {{ __('langWelcome.projects_subtitle') }}
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="feature-card p-8 flex flex-col items-center group text-center">
                <div class="w-24 h-24 bg-earth-green/15 rounded-full flex items-center justify-center text-4xl text-earth-green mb-6 transform group-hover:scale-110 transition-transform duration-300">
                    <i class="fas fa-city"></i>
                </div>
                <h3 class="text-2xl font-bold font-vazirmatn text-gentle-black mb-3">{{ __('langWelcome.projects_card1_title') }}</h3>
                <p class="text-gray-700 text-center font-vazirmatn">
                    {{ __('langWelcome.projects_card1_desc') }}
                </p>
            </div>

            <div class="feature-card p-8 flex flex-col items-center group text-center">
                <div class="w-24 h-24 bg-ocean-blue/15 rounded-full flex items-center justify-center text-4xl text-ocean-blue mb-6 transform group-hover:scale-110 transition-transform duration-300">
                    <i class="fas fa-solar-panel"></i>
                </div>
                <h3 class="text-2xl font-bold font-vazirmatn text-gentle-black mb-3">{{ __('langWelcome.projects_card2_title') }}</h3>
                <p class="text-gray-700 text-center font-vazirmatn">
                    {{ __('langWelcome.projects_card2_desc') }}
                </p>
            </div>

            <div class="feature-card p-8 flex flex-col items-center group text-center">
                <div class="w-24 h-24 bg-digital-gold/15 rounded-full flex items-center justify-center text-4xl text-digital-gold mb-6 transform group-hover:scale-110 transition-transform duration-300">
                    <i class="fas fa-hand-holding-heart"></i>
                </div>
                <h3 class="text-2xl font-bold font-vazirmatn text-gentle-black mb-3">{{ __('langWelcome.projects_card3_title') }}</h3>
                <p class="text-gray-700 text-center font-vazirmatn">
                    {{ __('langWelcome.projects_card3_desc') }}
                </p>
            </div>
        </div>

        <div class="mt-16 text-center">
            <a href="{{ route('register.form') }}" class="bg-earth-green text-pure-white px-10 py-5 rounded-full shadow-lg hover:bg-dark-green transition duration-300 font-vazirmatn text-xl font-bold transform hover:scale-105 flex items-center justify-center mx-auto inline-flex">
                {{ __('langWelcome.projects_cta') }} <i class="fas fa-project-diagram mr-3"></i>
            </a>
        </div>
    </div>
</section>
