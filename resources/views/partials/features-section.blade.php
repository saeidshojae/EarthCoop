<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
<!-- Features Section - بخش ویژگی‌های تعاونی ما -->
<section class="py-16 md:py-24 bg-light-gray fade-in-section">
    <div class="container mx-auto px-6 text-center">
        <div class="max-w-4xl mx-auto mb-16">
            <h2 class="text-3xl md:text-5xl font-extrabold font-vazirmatn text-gentle-black mb-6">{{ __('langWelcome.features_title') }}</h2>
            <div class="section-separator"></div>
            <p class="text-lg md:text-xl text-gray-700 mb-8 font-vazirmatn">
                {{ __('langWelcome.features_subtitle') }}
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
            <div class="feature-card p-8 flex flex-col items-center group text-center">
                <div class="w-24 h-24 bg-earth-green/15 rounded-full flex items-center justify-center text-4xl text-earth-green mb-6 transform group-hover:scale-110 transition-transform duration-300">
                    <i class="fas fa-handshake"></i>
                </div>
                <h3 class="text-2xl font-bold font-vazirmatn text-gentle-black mb-3">{{ __('langWelcome.features_card1_title') }}</h3>
                <p class="text-gray-700 text-center font-vazirmatn">
                    {{ __('langWelcome.features_card1_desc') }}
                </p>
            </div>

            <div class="feature-card p-8 flex flex-col items-center group text-center">
                <div class="w-24 h-24 bg-ocean-blue/15 rounded-full flex items-center justify-center text-4xl text-ocean-blue mb-6 transform group-hover:scale-110 transition-transform duration-300">
                    <i class="fas fa-globe"></i>
                </div>
                <h3 class="text-2xl font-bold font-vazirmatn text-gentle-black mb-3">{{ __('langWelcome.features_card2_title') }}</h3>
                <p class="text-gray-700 text-center font-vazirmatn">
                    {{ __('langWelcome.features_card2_desc') }}
                </p>
            </div>

            <div class="feature-card p-8 flex flex-col items-center group text-center">
                <div class="w-24 h-24 bg-digital-gold/15 rounded-full flex items-center justify-center text-4xl text-digital-gold mb-6 transform group-hover:scale-110 transition-transform duration-300">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <h3 class="text-2xl font-bold font-vazirmatn text-gentle-black mb-3">{{ __('langWelcome.features_card3_title') }}</h3>
                <p class="text-gray-700 text-center font-vazirmatn">
                    {{ __('langWelcome.features_card3_desc') }}
                </p>
            </div>
        </div>
    </div>
</section>
