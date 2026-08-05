<!-- Tailwind & Bootstrap CSS via Vite -->


















<!-- Global Network Section - بخش شبکه جهانی پررونق ما -->









<section id="network" class="py-16 md:py-24 bg-light-gray fade-in-section">









    <div class="container mx-auto px-6">









        <div class="text-center mb-16">









            <h2 class="text-3xl md:text-5xl font-extrabold font-vazirmatn text-gentle-black mb-6">{{ __('langWelcome.network_title') }}</h2>









            <div class="section-separator"></div>









            <p class="text-lg md:text-xl text-gray-700 max-w-4xl mx-auto font-vazirmatn">









                {{ __('langWelcome.network_subtitle') }}









            </p>









        </div>



















        <figure class="earth-globe mb-16" role="img" aria-label="کره زمین در حال چرخش، نماد شبکه جهانی EarthCoop">
            <div class="earth-globe__float" aria-hidden="true">
                <div class="earth-globe__sphere">
                    <div
                        class="earth-globe__map"
                        style="background-image: url('{{ asset('images/globe/earth-texture.svg') }}')"
                    ></div>
                    <div class="earth-globe__clouds"></div>
                    <div class="earth-globe__light"></div>
                    <div class="earth-globe__atmosphere"></div>
                </div>
            </div>
            <span class="earth-globe__shadow" aria-hidden="true"></span>
        </figure>



















        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">









            <div class="feature-card p-8 flex flex-col items-center group text-center">









                <div class="w-24 h-24 bg-ocean-blue/15 rounded-full flex items-center justify-center text-4xl text-ocean-blue mb-6 transform group-hover:scale-110 transition-transform duration-300">









                    <i class="fas fa-lightbulb"></i>









                </div>









                <h3 class="text-2xl font-bold font-vazirmatn text-gentle-black mb-3">{{ __('langWelcome.network_card1_title') }}</h3>









                <p class="text-gray-700 text-center font-vazirmatn">









                    {{ __('langWelcome.network_card1_desc') }}









                </p>









            </div>



















            <div class="feature-card p-8 flex flex-col items-center group text-center">









                <div class="w-24 h-24 bg-digital-gold/15 rounded-full flex items-center justify-center text-4xl text-digital-gold mb-6 transform group-hover:scale-110 transition-transform duration-300">









                    <i class="fas fa-hand-holding-heart"></i>









                </div>









                <h3 class="text-2xl font-bold font-vazirmatn text-gentle-black mb-3">{{ __('langWelcome.network_card2_title') }}</h3>









                <p class="text-gray-700 text-center font-vazirmatn">









                    {{ __('langWelcome.network_card2_desc') }}









                </p>









            </div>



















            <div class="feature-card p-8 flex flex-col items-center group text-center">









                <div class="w-24 h-24 bg-earth-green/15 rounded-full flex items-center justify-center text-4xl text-earth-green mb-6 transform group-hover:scale-110 transition-transform duration-300">









                    <i class="fas fa-people-carry-box"></i>









                </div>









                <h3 class="text-2xl font-bold font-vazirmatn text-gentle-black mb-3">{{ __('langWelcome.network_card3_title') }}</h3>









                <p class="text-gray-700 text-center font-vazirmatn">









                    {{ __('langWelcome.network_card3_desc') }}









                </p>









            </div>









        </div>









    </div>









</section>

















