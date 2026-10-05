<!-- Mission & Vision Statement - بخش بیانیه ماموریت و چشم‌انداز -->
<section id="about" class="py-16 md:py-24 bg-pure-white fade-in-section">
    <div class="container mx-auto px-6 text-center">
        <div class="max-w-4xl mx-auto mb-12">
            <h2 class="text-3xl md:text-5xl font-extrabold font-vazirmatn text-gentle-black mb-6">
                {{ __('langWelcome.mission_title') }}
            </h2>
            <div class="section-separator"></div>
            <p class="text-lg md:text-xl text-gray-700 font-vazirmatn leading-relaxed">
                {{ __('langWelcome.mission_text') }}
            </p>

            @if(app()->getLocale() === 'fa')
                <div class="mt-8 rounded-2xl border border-gray-200 bg-light-gray p-5 md:p-6 text-right font-vazirmatn">
                    <p class="text-base md:text-lg text-gray-700 leading-8 mb-4">
                        ارث‌کوپ یک سامانه تعاونی و مشارکتی از محله تا جهان است که برای تحقق عدالت، حکمرانی مشارکتی و الگوی «اقتصاد آزاد مردمی» طراحی شده است. برای شناخت عمیق‌تر هر بخش، از مسیرهای موضوعی زیر شروع کنید.
                    </p>
                    <nav aria-label="موضوعات اصلی EarthCoop" class="flex flex-wrap gap-3 justify-center md:justify-start">
                        <a href="/cooperative" class="inline-flex items-center rounded-full border border-earth-green px-4 py-2 text-sm font-semibold text-earth-green hover:bg-earth-green hover:text-white transition">
                            تعاون نوین
                        </a>
                        <a href="/economy" class="inline-flex items-center rounded-full border border-digital-gold px-4 py-2 text-sm font-semibold text-gentle-black hover:bg-digital-gold hover:text-white transition">
                            اقتصاد آزاد مردمی
                        </a>
                        <a href="/governance" class="inline-flex items-center rounded-full border border-ocean-blue px-4 py-2 text-sm font-semibold text-ocean-blue hover:bg-ocean-blue hover:text-white transition">
                            حکمرانی مشارکتی
                        </a>
                        <a href="/justice" class="inline-flex items-center rounded-full border border-gray-400 px-4 py-2 text-sm font-semibold text-gentle-black hover:bg-gentle-black hover:text-white transition">
                            عدالت و حقوق بنیادین
                        </a>
                    </nav>
                </div>
            @endif
        </div>

        <div class="flex justify-center mt-12">
            @if(file_exists(public_path('images/logo.png')))
                <img src="{{ asset('images/logo.png') }}"
                     alt="{{ __('langWelcome.mission_image_alt') }}"
                     class="w-full max-w-2xl rounded-3xl shadow-xl border-4 border-ocean-blue transform hover:scale-105 transition duration-500">
            @else
                <div class="w-full max-w-2xl h-64 rounded-3xl shadow-xl border-4 border-ocean-blue bg-gradient-to-br from-earth-green/20 via-ocean-blue/20 to-digital-gold/20 flex items-center justify-center">
                    <i class="fas fa-globe-americas text-6xl text-ocean-blue opacity-50"></i>
                </div>
            @endif
        </div>
    </div>
</section>
