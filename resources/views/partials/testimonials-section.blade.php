<!-- Community Stories / صدای جامعه -->
<section id="testimonials" class="py-16 md:py-24 bg-pure-white fade-in-section">
    @php
        $stories = $communityStories ?? collect();
        $storyCount = $stories->count();
        $gridClass = $storyCount === 1
            ? 'max-w-2xl mx-auto grid-cols-1'
            : ($storyCount === 2 ? 'max-w-5xl mx-auto grid-cols-1 md:grid-cols-2' : 'grid-cols-1 md:grid-cols-3');
        $borderColors = ['border-earth-green', 'border-ocean-blue', 'border-digital-gold'];
    @endphp

    <div class="container mx-auto px-6 text-center">
        <h2 class="text-3xl md:text-5xl font-extrabold font-vazirmatn text-gentle-black mb-6">
            {{ __('communityStories.title') }}
        </h2>
        <div class="section-separator"></div>
        <p class="text-lg md:text-xl text-gray-700 mb-12 max-w-4xl mx-auto font-vazirmatn leading-relaxed">
            {{ __('communityStories.subtitle') }}
        </p>

        @if($stories->isEmpty())
            <div class="testimonial-card max-w-3xl mx-auto p-7 sm:p-10 md:p-12 text-center">
                <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-earth-green/10 text-earth-green flex items-center justify-center mx-auto mb-6" aria-hidden="true">
                    <i class="fas fa-seedling text-2xl sm:text-3xl"></i>
                </div>
                <h3 class="text-2xl md:text-3xl font-extrabold font-vazirmatn text-gentle-black mb-4">
                    {{ __('communityStories.empty_title') }}
                </h3>
                <p class="max-w-2xl mx-auto text-base sm:text-lg text-gray-700 font-vazirmatn leading-8 mb-7">
                    {{ __('communityStories.empty_text') }}
                </p>
                <button type="button" onclick="openModal()" aria-controls="registrationModal" class="welcome-cta welcome-action w-full max-w-sm px-9 py-4 rounded-full bg-earth-green text-pure-white shadow-lg hover:bg-dark-green cursor-pointer mx-auto mb-4 flex items-center justify-center gap-3">
                    <span>{{ __('communityStories.empty_cta') }}</span>
                    <i class="fas fa-user-plus" aria-hidden="true"></i>
                </button>
            </div>
        @else
            <div class="grid {{ $gridClass }} gap-8">
                @foreach($stories as $index => $story)
                    <article class="testimonial-card p-7 sm:p-8 flex flex-col items-center text-center">
                        @if($story->show_avatar && $story->avatar_path)
                            <img src="{{ asset($story->avatar_path) }}"
                                 alt="{{ $story->show_name && $story->display_name ? $story->display_name : __('communityStories.anonymous_member') }}"
                                 class="w-20 h-20 sm:w-24 sm:h-24 rounded-full mb-6 object-cover border-4 {{ $borderColors[$index % 3] }} shadow-md">
                        @else
                            <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-full mb-6 flex items-center justify-center border-4 {{ $borderColors[$index % 3] }} bg-light-gray text-gray-500 shadow-md" aria-hidden="true">
                                <i class="fas fa-user text-2xl"></i>
                            </div>
                        @endif

                        <blockquote class="text-lg sm:text-xl text-gray-800 italic mb-5 font-vazirmatn leading-relaxed relative z-10">
                            {{ $story->body }}
                        </blockquote>

                        <div class="mt-auto pt-4">
                            <p class="font-bold text-lg sm:text-xl font-vazirmatn text-gentle-black">
                                {{ $story->show_name && $story->display_name ? $story->display_name : __('communityStories.anonymous_member') }}
                            </p>
                            @if($story->role || $story->location)
                                <p class="text-sm font-vazirmatn text-gray-600 mt-1">
                                    @if($story->role){{ $story->role }}@endif
                                    @if($story->role && $story->location){{ '، ' }}@endif
                                    @if($story->location){{ $story->location }}@endif
                                </p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
