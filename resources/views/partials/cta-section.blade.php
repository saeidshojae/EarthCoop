<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
<!-- Final Call to Action - بخش نهایی دعوت به عمل -->
<section class="relative py-20 md:py-32 bg-gradient-to-br from-earth-green to-ocean-blue text-pure-white text-center fade-in-section">
    <div class="absolute inset-0 bg-black/50 z-0"></div>
    <div class="container mx-auto px-6 relative z-10">
        <h2 class="text-4xl md:text-5xl lg:text-6xl font-extrabold font-vazirmatn mb-8 leading-tight animate-pulse">
            آماده‌اید بخشی از راه‌حل باشید؟
        </h2>
        <p class="text-xl md:text-2xl mb-12 max-w-4xl mx-auto font-vazirmatn opacity-90">
            امروز به EarthCoop بپیوندید و به بازیگری کلیدی در شکل‌دهی آینده‌ای پایدار و عادلانه‌تر برای همه تبدیل شوید.
        </p>
        <div class="flex flex-col sm:flex-row-reverse gap-6 justify-center">
            <a href="#about" class="border-2 border-pure-white text-pure-white px-12 py-5 rounded-full shadow-xl hover:bg-pure-white hover:text-purple-700 group transition duration-300 font-vazirmatn text-xl font-medium flex items-center justify-center">
                ماموریت ما را کشف کنید <i class="fas fa-question-circle mr-3 group-hover:text-purple-700 transition-colors duration-300"></i>
            </a>
            <button onclick="openModal()" class="bg-digital-gold text-pure-white px-12 py-5 rounded-full shadow-2xl hover:bg-opacity-90 hover:scale-105 transition duration-300 font-vazirmatn text-xl font-bold animate-glow flex items-center justify-center cursor-pointer">
                اکنون به EarthCoop بپیوندید <i class="fas fa-hand-holding-seedling mr-3"></i>
            </button>
        </div>
    </div>
</section>
