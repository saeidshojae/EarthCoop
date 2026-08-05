<!-- Tailwind & Bootstrap CSS via Vite -->


















<!-- Bahār Economy & Investment Opportunities - بخش اقتصاد بهار و فرصت‌های سرمایه‌گذاری -->









<section id="bahar" class="py-16 md:py-24 bg-gradient-to-br from-ocean-blue/15 to-digital-gold/15 fade-in-section">









    <div class="container mx-auto px-6 flex flex-col items-center justify-center gap-12 text-center">









        <figure class="bahar-coin mb-8" role="img" aria-label="سکه یک‌بهاری EarthCoop، با نمای رو و پشت">
            <div class="bahar-coin__float" aria-hidden="true">
                <div class="bahar-coin__scene">
                    <div class="bahar-coin__rotor">
                        <div
                            class="bahar-coin__edge"
                            style="--bahar-coin-mask: url('{{ asset('images/bahar/coin-front.webp') }}')"
                            aria-hidden="true"
                        >
                            <span class="bahar-coin__edge-layer"></span>
                            <span class="bahar-coin__edge-layer"></span>
                            <span class="bahar-coin__edge-layer"></span>
                            <span class="bahar-coin__edge-layer"></span>
                            <span class="bahar-coin__edge-layer"></span>
                            <span class="bahar-coin__edge-layer"></span>
                            <span class="bahar-coin__edge-layer"></span>
                            <span class="bahar-coin__edge-layer"></span>
                            <span class="bahar-coin__edge-layer"></span>
                            <span class="bahar-coin__edge-layer"></span>
                            <span class="bahar-coin__edge-layer"></span>
                            <span class="bahar-coin__edge-layer"></span>
                            <span class="bahar-coin__edge-layer"></span>
                            <span class="bahar-coin__edge-layer"></span>
                            <span class="bahar-coin__edge-layer"></span>
                            <span class="bahar-coin__edge-layer"></span>
                            <span class="bahar-coin__edge-layer"></span>
                        </div>
                        <img
                            class="bahar-coin__face bahar-coin__face--front"
                            src="{{ asset('images/bahar/coin-front.webp') }}"
                            width="1254"
                            height="1254"
                            alt=""
                            loading="eager"
                            fetchpriority="high"
                            decoding="async"
                            draggable="false"
                        >
                        <img
                            class="bahar-coin__face bahar-coin__face--back"
                            src="{{ asset('images/bahar/coin-back.webp') }}"
                            width="1254"
                            height="1254"
                            alt=""
                            loading="eager"
                            decoding="async"
                            draggable="false"
                        >
                    </div>
                </div>
            </div>
            <span class="bahar-coin__shadow" aria-hidden="true"></span>
        </figure>



















        <div class="md:w-2/3 text-center">









            <h2 class="text-3xl md:text-5xl font-extrabold font-vazirmatn text-gentle-black mb-6">









                {{ __('langWelcome.bahar_title') }}









            </h2>









            <div class="section-separator mx-auto"></div>









            <p class="text-lg md:text-xl text-gray-700 mb-8 font-vazirmatn leading-relaxed">









                {{ __('langWelcome.bahar_subtitle') }}









            </p>









            <div class="grid grid-cols-1 md:grid-cols-3 gap-10 mb-8">









                <div class="feature-card p-8 flex flex-col items-center group text-center">









                    <div class="w-24 h-24 bg-digital-gold/15 rounded-full flex items-center justify-center text-4xl text-digital-gold mb-6 transform group-hover:scale-110 transition-transform duration-300">









                        <i class="fas fa-dollar-sign"></i>









                    </div>









                    <h3 class="text-2xl font-bold font-vazirmatn text-gentle-black mb-3">{{ __('langWelcome.bahar_card1_title') }}</h3>









                    <p class="text-gray-700 text-center font-vazirmatn">{{ __('langWelcome.bahar_card1_desc') }}</p>









                </div>









                <div class="feature-card p-8 flex flex-col items-center group text-center">









                    <div class="w-24 h-24 bg-ocean-blue/15 rounded-full flex items-center justify-center text-4xl text-ocean-blue mb-6 transform group-hover:scale-110 transition-transform duration-300">









                        <i class="fas fa-store"></i>









                    </div>









                    <h3 class="text-2xl font-bold font-vazirmatn text-gentle-black mb-3">{{ __('langWelcome.bahar_card2_title') }}</h3>









                    <p class="text-gray-700 text-center font-vazirmatn">{{ __('langWelcome.bahar_card2_desc') }}</p>









                </div>









                <div class="feature-card p-8 flex flex-col items-center group text-center">









                    <div class="w-24 h-24 bg-earth-green/15 rounded-full flex items-center justify-center text-4xl text-earth-green mb-6 transform group-hover:scale-110 transition-transform duration-300">









                        <i class="fas fa-chart-line"></i>









                    </div>









                    <h3 class="text-2xl font-bold font-vazirmatn text-gentle-black mb-3">{{ __('langWelcome.bahar_card3_title') }}</h3>









                    <p class="text-gray-700 text-center font-vazirmatn">{{ __('langWelcome.bahar_card3_desc') }}</p>









                </div>









            </div>









            <div class="flex flex-col sm:flex-row-reverse gap-4 justify-center">









                <a href="#projects" class="border-2 border-digital-gold text-digital-gold bg-white px-9 py-4 rounded-full shadow-lg hover:shadow-xl hover:bg-digital-gold group transition duration-300 font-vazirmatn text-lg font-medium flex.items-center justify-center">









                    {{ __('langWelcome.bahar_cta_invest') }} <i class="fas fa-hand-holding-usd mr-3 group-hover:text-purple-700 transition-colors duration-300"></i>









                </a>









                <a href="#bahar" class="bg-digital-gold text-pure-white px-9 py-4 rounded-full shadow-lg hover:bg-opacity-90 transition duration-300 font-vazirmatn text-lg font-bold flex items-center justify-center">









                    {{ __('langWelcome.bahar_cta_learn') }} <i class="fas fa-coins mr-3"></i>









                </a>









            </div>









        </div>









    </div>









</section>
















