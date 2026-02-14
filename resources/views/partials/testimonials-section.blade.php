<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
<!-- Community Testimonials / Success Stories - بخش صدای جامعه جهانی ما -->
<section id="testimonials" class="py-16 md:py-24 bg-pure-white fade-in-section">
    <div class="container mx-auto px-6 text-center">
        <h2 class="text-3xl md:text-5xl font-extrabold font-vazirmatn text-gentle-black mb-6">
            {{ __('langWelcome.testimonials_title') }}
        </h2>
        <div class="section-separator"></div>
        <p class="text-lg md:text-xl text-gray-700 mb-12 max-w-4xl mx-auto font-vazirmatn">
            {{ __('langWelcome.testimonials_subtitle') }}
        </p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            @php
                $borderColors = ['border-earth-green', 'border-ocean-blue', 'border-digital-gold'];
                
                // تابع برای ایجاد placeholder avatar
                $createPlaceholder = function($color) {
                    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="250" height="250" viewBox="0 0 250 250"><rect fill="' . $color . '" width="250" height="250"/><circle cx="125" cy="100" r="40" fill="white" opacity="0.9"/><path d="M 50 200 Q 125 160 200 200" stroke="white" stroke-width="30" fill="none" stroke-linecap="round" opacity="0.9"/></svg>';
                    return 'data:image/svg+xml;base64,' . base64_encode($svg);
                };
                
                // بررسی وجود تصاویر محلی و استفاده از placeholder در صورت عدم وجود
                $avatarFiles = ['1764726699.jpg', '1767311177.jpg', '1767312014.jpg'];
                $avatarColors = ['#10b981', '#3b82f6', '#f59e0b'];
                
                $getAvatar = function($index) use ($avatarFiles, $avatarColors, $createPlaceholder) {
                    $filePath = public_path('images/users/avatars/' . $avatarFiles[$index]);
                    return file_exists($filePath) 
                        ? asset('images/users/avatars/' . $avatarFiles[$index]) 
                        : $createPlaceholder($avatarColors[$index]);
                };
                
                $defaultTestimonials = [
                    [
                        'quote' => __('langWelcome.testimonials_card1_quote'),
                        'name' => __('langWelcome.testimonials_card1_name'),
                        'role' => __('langWelcome.testimonials_card1_role'),
                        'avatar' => $getAvatar(0),
                    ],
                    [
                        'quote' => __('langWelcome.testimonials_card2_quote'),
                        'name' => __('langWelcome.testimonials_card2_name'),
                        'role' => __('langWelcome.testimonials_card2_role'),
                        'avatar' => $getAvatar(1),
                    ],
                    [
                        'quote' => __('langWelcome.testimonials_card3_quote'),
                        'name' => __('langWelcome.testimonials_card3_name'),
                        'role' => __('langWelcome.testimonials_card3_role'),
                        'avatar' => $getAvatar(2),
                    ],
                ];
                
                $finalTestimonials = isset($testimonials) && $testimonials->count() > 0 
                    ? $testimonials->toArray() 
                    : $defaultTestimonials;
                
                // اگر testimonials واقعی کمتر از 3 تا بود، بقیه را از default بگیریم
                if (isset($testimonials) && $testimonials->count() > 0 && $testimonials->count() < 3) {
                    $finalTestimonials = array_merge(
                        $testimonials->toArray(),
                        array_slice($defaultTestimonials, $testimonials->count(), 3 - $testimonials->count())
                    );
                }
                
                $finalTestimonials = array_slice($finalTestimonials, 0, 3);
            @endphp
            
            @foreach($finalTestimonials as $index => $testimonial)
                <div class="testimonial-card p-8 flex flex-col items-center text-center">
                    <img src="{{ $testimonial['avatar'] }}"
                          alt="{{ $testimonial['name'] }}"
                          class="w-24 h-24 rounded-full mb-6 object-cover border-4 {{ $borderColors[$index % 3] }} shadow-md">
                    <div class="text-xl text-gray-800 italic mb-5 font-vazirmatn relative z-10 before:content-['"'] before:absolute before:-top-4 before:-right-4 before:text-5xl before:text-gray-200 after:content-['"'] after:absolute after:-bottom-4 after:-left-4 after:text-5xl after:text-gray-200">
                        {{ $testimonial['quote'] }}
                    </div>
                    <div class="mt-auto pt-4">
                        <p class="font-bold text-xl font-vazirmatn text-gentle-black">{{ $testimonial['name'] }}</p>
                        <p class="text-sm font-vazirmatn text-gray-600">
                            {{ $testimonial['role'] }}
                            @if(!empty($testimonial['location']))
                                {{ '، ' . $testimonial['location'] }}
                            @endif
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

