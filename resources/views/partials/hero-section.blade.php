<!-- Tailwind & Bootstrap CSS via Vite -->

@vite(['resources/css/app.css', 'resources/js/app.js'])

<!-- Hero Section - بخش قهرمان -->

<section class="relative hero-gradient py-20 md:py-32 overflow-hidden fade-in-section text-right">

    <div class="absolute inset-0 overflow-hidden">

        <div class="absolute top-0 right-0 w-full h-full bg-pure-white/5 to-transparent z-0"></div>

        <div class="absolute top-0 right-0 w-full h-full bg-gradient-to-br from-earth-green/10 via-ocean-blue/10 to-digital-gold/10 opacity-15 z-0"></div>

        <!-- Fallback for browsers that don't support backdrop-blur -->

        <div class="absolute inset-0 bg-pure-white/30 z-0" style="background-color: rgba(255, 255, 255, 0.3);"></div>

        <!-- backdrop-blur for modern browsers -->

        <div class="absolute inset-0 bg-pure-white/10 backdrop-blur-sm z-0" style="backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);"></div>

    </div>

    <div class="container mx-auto flex flex-col md:flex-row items-center justify-between px-6 relative z-10">

        <!-- Titles, text, buttons, stats -->

        <div class="md:w-1/2 text-center md:text-right mb-12 md:mb-0">

            @php
                $heroTitle = __('langWelcome.hero_title');
                $heroHighlight = __('langWelcome.hero_title_highlight');
                $hasHighlight = \Illuminate\Support\Str::contains($heroTitle, $heroHighlight);
                $titleBefore = $hasHighlight ? \Illuminate\Support\Str::before($heroTitle, $heroHighlight) : $heroTitle;
                $titleAfter = $hasHighlight ? \Illuminate\Support\Str::after($heroTitle, $heroHighlight) : '';
                $highlightText = $hasHighlight ? $heroHighlight : '';
            @endphp

            <h1 class="text-4xl md:text-6xl lg:text-7xl font-extrabold text-gentle-black font-vazirmatn mb-6 leading-tight">

                {!! e($titleBefore) !!}

                @if($highlightText !== '')
                    <span class="text-earth-green inline-block animate-pulse-light">{{ $highlightText }}</span>
                @endif

                {!! e($titleAfter) !!}

            </h1>

            <p class="text-lg md:text-xl text-gray-700 mb-8 max-w-lg font-vazirmatn mx-auto md:mx-0">

                {{ __('langWelcome.hero_subtitle') }}

            </p>

            <div class="flex flex-col sm:flex-row-reverse gap-4 justify-center md:justify-end">

                <button onclick="openModal()" class="bg-earth-green text-pure-white px-9 py-4 rounded-full shadow-xl hover:shadow-2xl hover:bg-dark-green transition duration-300 animate-glow font-vazirmatn text-lg font-bold flex items-center justify-center cursor-pointer">

                    {{ __('langWelcome.hero_cta_start') }} <i class="fas fa-arrow-left mr-3"></i>

                </button>

                <a href="#about" class="border-2 border-earth-green text-earth-green bg-white px-9 py-4 rounded-full shadow-lg hover:shadow-xl hover:bg-earth-green group transition duration-300 font-vazirmatn text-lg font-medium flex items-center justify-center">

                    {{ __('langWelcome.hero_cta_more') }} <i class="fas fa-info-circle mr-3 group-hover:text-purple-700 transition-colors duration-300"></i>

                </a>

            </div>

            <div class="mt-12 flex flex-wrap justify-center md:justify-end gap-8">

                <div class="stats-item flex items-center flex-row-reverse">

                    <div class="p-3 bg-earth-green/10 rounded-full text-earth-green text-xl ml-3">

                        <i class="fas fa-users"></i>

                    </div>

                    <div class="text-right">

                        <p class="text-3xl font-bold text-gentle-black font-poppins">{{ isset($stats) ? $stats['members_formatted'] . '+' : '0' }}</p>

                        <p class="text-gray-600 font-vazirmatn">{{ __('langWelcome.hero_stats_members_label') }}</p>

                    </div>

                </div>

                <div class="stats-item flex items-center flex-row-reverse">

                    <div class="p-3 bg-ocean-blue/10 rounded-full text-ocean-blue text-xl ml-3">

                        <i class="fas fa-seedling"></i>

                    </div>

                    <div class="text-right">

                        <p class="text-3xl font-bold text-gentle-black font-poppins">{{ isset($stats) ? $stats['projects_formatted'] . '+' : '0' }}</p>

                        <p class="text-gray-600 font-vazirmatn">{{ __('langWelcome.hero_stats_projects_label') }}</p>

                    </div>

                </div>

                <div class="stats-item flex items-center flex-row-reverse">

                    <div class="p-3 bg-digital-gold/10 rounded-full text-digital-gold text-xl ml-3">

                        <i class="fas fa-globe-americas"></i>

                    </div>

                    <div class="text-right">

                            <p class="text-3xl font-bold text-gentle-black font-poppins">{{ isset($stats) ? $stats['countries_formatted'] . '+' : '0' }}</p>

                            <p class="text-gray-600 font-vazirmatn">{{ __('langWelcome.hero_stats_countries_label') }}</p>

                    </div>

                </div>

            </div>

        </div>

        <!-- ============================================================ -->
        <!-- Image and floating cards - نسخه اصلاح‌شده                    -->
        <!-- ============================================================ -->

        <div class="md:w-1/2 flex justify-center mt-12 md:mt-0">
            <div class="relative w-full max-w-md"> <!-- 🔹 تغییر: max-w-lg به max-w-md -->

                <img src="{{ asset('images/images (1).jpeg') }}"
                     alt="{{ __('langWelcome.hero_card_image_alt') }}"
                     class="w-full rounded-3xl shadow-2xl border-8 border-white transform rotate-3 hover:rotate-0 transition duration-500">

                <!-- 🔹 کارت پایین - سمت راست (کوچک‌تر و شفاف‌تر) -->
                <div class="absolute -bottom-6 -left-6 bg-white/95 backdrop-blur-sm p-3 rounded-2xl shadow-xl flex items-center space-x-2 rtl:space-x-reverse transform -rotate-6 hover:rotate-0 transition duration-500 hero-image-card-right">
                    <div class="w-10 h-10 bg-earth-green rounded-full flex items-center justify-center text-white text-lg flex-shrink-0">
                        <i class="fas fa-leaf"></i>
                    </div>
                    <div class="ml-2 rtl:ml-0 rtl:mr-2">
                        <p class="font-bold text-sm text-gentle-black font-vazirmatn">{{ __('langWelcome.hero_card_right_title') }}</p>
                        <p class="text-xs text-gray-600 font-vazirmatn">{{ __('langWelcome.hero_card_right_subtitle') }}</p>
                    </div>
                </div>

                <!-- 🔹 کارت بالا - سمت چپ (کوچک‌تر و شفاف‌تر) -->
                <div class="absolute -top-6 -right-6 bg-white/95 backdrop-blur-sm p-3 rounded-2xl shadow-xl flex items-center space-x-2 rtl:space-x-reverse transform rotate-6 hover:rotate-0 transition duration-500 hidden md:flex hero-image-card-left">
                    <div class="w-10 h-10 bg-ocean-blue rounded-full flex items-center justify-center text-white text-lg flex-shrink-0">
                        <i class="fas fa-hands-helping"></i>
                    </div>
                    <div class="ml-2 rtl:ml-0 rtl:mr-2">
                        <p class="font-bold text-sm text-gentle-black font-vazirmatn">{{ __('langWelcome.hero_card_left_title') }}</p>
                        <p class="text-xs text-gray-600 font-vazirmatn">{{ __('langWelcome.hero_card_left_subtitle') }}</p>
                    </div>
                </div>

            </div>
        </div>

    </div>

</section>