<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
<!-- How It Works Section - بخش مسیر شما برای ایجاد تفاوت -->
<section id="how-it-works" class="py-16 md:py-24 bg-pure-white fade-in-section">
    <div class="container mx-auto px-6">
        <div class="text-center mb-16">
            <h2 class="text-3xl md:text-5xl font-extrabold font-vazirmatn text-gentle-black mb-6">{{ __('langWelcome.how_title') }}</h2>
            <div class="section-separator"></div>
            <p class="text-lg md:text-xl text-gray-700 max-w-4xl mx-auto font-vazirmatn">
                {{ __('langWelcome.how_subtitle') }}
            </p>
        </div>

        <div class="flex flex-col md:flex-row justify-between items-stretch relative">
            <div class="absolute hidden md:block top-1/3 left-1/4 w-1/2 h-2 bg-gray-200 rounded-full z-0"></div>
            <div class="absolute hidden md:flex top-1/3 left-1/4 w-1/2 h-2 justify-between z-10">
                <div class="w-4 h-4 bg-earth-green rounded-full transform -translate-y-1/2"></div>
                <div class="w-4 h-4 bg-ocean-blue rounded-full transform -translate-y-1/2"></div>
                <div class="w-4 h-4 bg-digital-gold rounded-full transform -translate-y-1/2"></div>
                <div class="w-4 h-4 bg-earth-green rounded-full transform -translate-y-1/2"></div>
            </div>

            <div class="how-it-works-step flex flex-col items-center text-center p-6 md:w-1/4 bg-pure-white rounded-xl shadow-md transform hover:-translate-y-2 transition duration-300 relative z-20">
                <div class="w-20 h-20 bg-earth-green text-pure-white rounded-full flex items-center justify-center text-3xl font-bold mb-4 shadow-lg border-4 border-white">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h3 class="text-xl font-semibold font-vazirmatn text-gentle-black mb-3">{{ __('langWelcome.how_step1_title') }}</h3>
                <p class="text-gray-700 font-vazirmatn">
                    {{ __('langWelcome.how_step1_desc') }}
                </p>
            </div>

            <div class="how-it-works-step flex flex-col items-center text-center p-6 md:w-1/4 bg-pure-white rounded-xl shadow-md transform hover:-translate-y-2 transition duration-300 relative z-20">
                <div class="w-20 h-20 bg-ocean-blue text-pure-white rounded-full flex items-center justify-center text-3xl font-bold mb-4 shadow-lg border-4 border-white">
                    <i class="fas fa-users-gear"></i>
                </div>
                <h3 class="text-xl font-semibold font-vazirmatn text-gentle-black mb-3">{{ __('langWelcome.how_step2_title') }}</h3>
                <p class="text-gray-700 font-vazirmatn">
                    {{ __('langWelcome.how_step2_desc') }}
                </p>
            </div>

            <div class="how-it-works-step flex flex-col items-center text-center p-6 md:w-1/4 bg-pure-white rounded-xl shadow-md transform hover:-translate-y-2 transition duration-300 relative z-20">
                <div class="w-20 h-20 bg-digital-gold text-pure-white rounded-full flex items-center justify-center text-3xl font-bold mb-4 shadow-lg border-4 border-white">
                    <i class="fas fa-hands-clapping"></i>
                </div>
                <h3 class="text-xl font-semibold font-vazirmatn text-gentle-black mb-3">{{ __('langWelcome.how_step3_title') }}</h3>
                <p class="text-gray-700 font-vazirmatn">
                    {{ __('langWelcome.how_step3_desc') }}
                </p>
            </div>

            <div class="how-it-works-step flex flex-col items-center text-center p-6 md:w-1/4 bg-pure-white rounded-xl shadow-md transform hover:-translate-y-2 transition duration-300 relative z-20">
                <div class="w-20 h-20 bg-earth-green text-pure-white rounded-full flex items-center justify-center text-3xl font-bold mb-4 shadow-lg border-4 border-white">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <h3 class="text-xl font-semibold font-vazirmatn text-gentle-black mb-3">{{ __('langWelcome.how_step4_title') }}</h3>
                <p class="text-gray-700 font-vazirmatn">
                    {{ __('langWelcome.how_step4_desc') }}
                </p>
            </div>
        </div>

        <div class="mt-16 text-center">
            <button onclick="openModal()" class="bg-earth-green text-pure-white px-10 py-5 rounded-full shadow-lg hover:bg-dark-green transition duration-300 font-vazirmatn text-xl font-bold transform hover:scale-105 flex items-center justify-center mx-auto inline-flex cursor-pointer">
                {{ __('langWelcome.how_cta') }} <i class="fas fa-rocket mr-3"></i>
            </button>
        </div>
    </div>
</section>

