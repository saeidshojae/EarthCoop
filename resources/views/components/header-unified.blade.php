{{-- Unified Header Component - بر اساس طراحی صفحه Home --}}
<header class="bg-pure-white shadow-md py-3 md:py-4 px-3 md:px-6 lg:px-8 sticky top-0 z-50 transition-all duration-300" 
        style="background-color: var(--color-pure-white); overflow: visible;">
    <div class="container mx-auto flex justify-between items-center gap-2 md:gap-4 header-container" style="align-items: center; flex-wrap: nowrap; overflow: visible; position: relative;">
        
        <!-- Logo Section - لینک به خانه -->
        <div class="flex items-center space-x-2 md:space-x-3 md:space-x-reverse rtl:space-x-reverse flex-shrink-0 header-logo-section" style="align-items: center; min-width: 0; overflow: visible; position: relative; z-index: 1;">
            @if(!request()->routeIs('home'))
                <a href="{{ url()->previous() == url()->current() ? route('home') : url()->previous() }}" 
                   class="text-gray-600 hover:text-green-600 transition-colors header-back-button flex-shrink-0"
                   style="margin-right: 0.5rem;">
                    <i class="fa fa-arrow-left text-lg md:text-xl"></i>
                </a>
            @endif
            
            @php
                $logoTarget = auth()->check() ? route('home') : route('welcome');
            @endphp
            <a href="{{ $logoTarget }}" class="flex items-center space-x-4 md:space-x-5 md:space-x-reverse hover:opacity-80 transition-opacity header-logo-link" style="align-items: center; min-width: 0; flex-shrink: 1;">
                <svg width="35" height="35" class="md:w-[45px] md:h-[45px] logo-animated" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="flex-shrink: 0; position: relative; z-index: 1;">
                    <path d="M12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2Z" fill="#10b981" opacity="0.8"/>
                    <path d="M12 2C10.5 4 8 6 8 9C8 12 12 14 12 14C12 14 16 12 16 9C16 6 13.5 4 12 2ZM12 14C12 14 10 16 10 18C10 20 12 22 12 22" fill="#047857"/>
                </svg>
                
                <span class="text-lg md:text-2xl lg:text-3xl font-extrabold text-gentle-black header-logo-text" 
                      style="color: var(--color-gentle-black); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    EarthCoop
                </span>
            </a>
        </div>

        <!-- Desktop Navigation -->
        <nav class="hidden md:flex items-center gap-2 md:gap-3 lg:gap-4 text-gentle-black justify-center flex-nowrap flex-1 min-w-0" 
             style="color: var(--color-gentle-black);">
            
            @if(auth()->check())
                <a href="{{ route('blog.index') }}" 
                   class="relative hover:text-earth-green transition duration-300 font-medium pb-1 group flex items-center whitespace-nowrap text-sm md:text-base gap-2.5">
                    <i class="fas fa-blog text-xs md:text-sm flex-shrink-0" style="color: var(--color-earth-green);"></i> 
                    <span>{{ __('navigation.blog') }}</span>
                    <span class="absolute bottom-0 right-0 w-0 h-0.5 group-hover:w-full transition-all duration-300" 
                          style="background-color: var(--color-earth-green);"></span>
                </a>
                
                <a href="{{ route('stock.book') }}" 
                   class="relative hover:text-earth-green transition duration-300 font-medium pb-1 group flex items-center whitespace-nowrap text-sm md:text-base gap-2.5">
                    <i class="fas fa-chart-line text-xs md:text-sm flex-shrink-0" style="color: var(--color-earth-green);"></i> 
                    <span>{{ __('navigation.stock_office') }}</span>
                    <span class="absolute bottom-0 right-0 w-0 h-0.5 group-hover:w-full transition-all duration-300" 
                          style="background-color: var(--color-earth-green);"></span>
                </a>
            @else
                <a href="{{ route('blog.index') }}" 
                   class="relative hover:text-earth-green transition duration-300 font-medium pb-1 group flex items-center whitespace-nowrap text-sm md:text-base gap-2.5">
                    <i class="fas fa-blog text-xs md:text-sm flex-shrink-0" style="color: var(--color-earth-green);"></i> 
                    <span>{{ __('navigation.blog') }}</span>
                    <span class="absolute bottom-0 right-0 w-0 h-0.5 group-hover:w-full transition-all duration-300" 
                          style="background-color: var(--color-earth-green);"></span>
                </a>
            @endif
            
            @foreach(\App\Models\Page::where('is_published', 1)->where('show_in_header', 1)->get() as $page)
                <a href="{{ url('/pages/' . $page->slug) }}" 
                   class="relative hover:text-earth-green transition duration-300 font-medium pb-1 group flex items-center whitespace-nowrap text-sm md:text-base gap-2.5">
                    <i class="fas fa-file-alt text-xs md:text-sm flex-shrink-0" style="color: var(--color-earth-green);"></i> 
                    <span>{{ $page->translated_title }}</span>
                    <span class="absolute bottom-0 right-0 w-0 h-0.5 group-hover:w-full transition-all duration-300" 
                          style="background-color: var(--color-earth-green);"></span>
                </a>
            @endforeach
        </nav>

        <!-- User Actions -->
        <div class="flex items-center gap-3 flex-shrink-0" style="align-items: center;">
            <!-- Dark Mode Toggle - Desktop -->
            <div class="hidden md:block">
                <div class="theme-toggle" onclick="toggleTheme()" title="{{ __('navigation.theme_toggle') }}" style="margin: 0 0.5rem;">
                    <span class="theme-toggle-icon sun">☀️</span>
                    <span class="theme-toggle-icon moon">🌙</span>
                    <div class="theme-toggle-slider"></div>
                </div>
            </div>
            
            <!-- Language Switcher - Desktop -->
            <div class="hidden md:block relative">
                @php
                    $locales = [
                        'fa' => ['label' => 'فارسی', 'abbr' => 'FA', 'flag' => '🇮🇷'],
                        'en' => ['label' => 'English', 'abbr' => 'EN', 'flag' => '🇬🇧'],
                        'ar' => ['label' => 'العربية', 'abbr' => 'AR', 'flag' => '🇸🇦'],
                    ];
                    $currentLocale = app()->getLocale();
                @endphp
                <button id="locale-toggle-button" type="button" 
                        class="flex items-center bg-light-gray rounded-full px-3 py-1 shadow-sm border border-gray-200 gap-2 transition hover:bg-white cursor-pointer">
                    <span class="text-xs font-semibold tracking-wider">{{ $locales[$currentLocale]['abbr'] ?? strtoupper($currentLocale) }}</span>
                    <svg class="w-3 h-3 text-gentle-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="locale-dropdown" 
                     class="absolute left-0 mt-1 w-32 bg-white border border-gray-200 rounded-lg shadow-lg py-2 hidden z-50"
                     onclick="event.stopPropagation()">
                    @foreach($locales as $code => $meta)
                        @if($currentLocale !== $code)
                            <a href="{{ route('locale.change', $code) }}" 
                               class="flex items-center px-3 py-2 text-xs font-vazirmatn text-gentle-black hover:bg-light-gray transition">
                                <span class="font-semibold tracking-wider">{{ $meta['abbr'] }}</span>
                                <span class="ltr:ml-2 rtl:mr-2 text-[11px] text-gray-500">{{ $meta['label'] }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>

            @auth
                <!-- Knowledge Base Quick Access (Desktop) -->
                <a href="{{ route('support.kb.index') }}"
                   class="hidden md:inline-flex items-center justify-center w-10 h-10 rounded-full border border-gray-200 bg-white shadow-sm hover:bg-gray-50 transition focus:outline-none focus:ring-2 focus:ring-emerald-400"
                   title="پایگاه دانش"
                   aria-label="پایگاه دانش">
                    <i class="fas fa-circle-question" style="color: var(--color-ocean-blue);"></i>
                </a>
            @endauth
            
            <!-- Mobile Menu Button -->
            <button @click="mobileMenuOpen = !mobileMenuOpen" 
                    class="md:hidden text-gentle-black focus:outline-none flex items-center justify-center mobile-menu-button" 
                    style="color: var(--color-gentle-black); width: 45px; height: 45px; flex-shrink: 0; display: flex !important; visibility: visible !important; opacity: 1 !important; position: relative;">
                <i class="fas fa-bars text-2xl mobile-menu-icon-bars" 
                   :class="{ 'hidden': mobileMenuOpen }"
                   style="display: inline-block;"></i>
                <i class="fas fa-times text-2xl mobile-menu-icon-times" 
                   :class="{ 'hidden': !mobileMenuOpen }"
                   style="display: none; position: absolute;"></i>
            </button>

            @auth
                <!-- User Dropdown -->
                @include('components.user-dropdown-unified')
            @else
                <!-- Login/Register Buttons -->
                <a href="{{ route('login') }}" 
                   class="bg-earth-green text-pure-white px-4 py-2 rounded-full shadow-md hover:bg-dark-green transition duration-300 font-medium transform hover:scale-105">
                    {{ __('navigation.login') }}
                </a>
                <a href="{{ route('register.form') }}" 
                   class="bg-ocean-blue text-pure-white px-4 py-2 rounded-full shadow-md hover:bg-dark-blue transition duration-300 font-medium transform hover:scale-105">
                    {{ __('navigation.register') }}
                </a>
            @endauth
        </div>
    </div>
    
    <!-- Mobile Menu -->
    <div x-show="mobileMenuOpen" 
         @click.away="mobileMenuOpen = false"
         x-transition:enter="transition ease-out duration-200" 
         x-transition:enter-start="opacity-0 -translate-y-2" 
         x-transition:enter-end="opacity-100 translate-y-0" 
         x-transition:leave="transition ease-in duration-150" 
         x-transition:leave-start="opacity-100 translate-y-0" 
         x-transition:leave-end="opacity-0 -translate-y-2" 
         class="md:hidden mt-4 pb-4 border-t border-gray-200"
         x-cloak
         style="display: none;">
        <nav class="flex flex-col items-center space-y-2 text-gentle-black" style="color: var(--color-gentle-black);">
            @if(auth()->check())
                <a href="{{ route('blog.index') }}" 
                   @click="mobileMenuOpen = false"
                   class="block w-full text-center py-2 hover:bg-gray-50 rounded-md transition duration-300 flex items-center justify-center">
                    <i class="fas fa-blog ml-2" style="color: var(--color-earth-green);"></i> 
                    <span>{{ __('navigation.blog') }}</span>
                </a>
                <a href="{{ route('stock.book') }}" 
                   @click="mobileMenuOpen = false"
                   class="block w-full text-center py-2 hover:bg-gray-50 rounded-md transition duration-300 flex items-center justify-center">
                    <i class="fas fa-chart-line ml-2" style="color: var(--color-earth-green);"></i> 
                    <span>{{ __('navigation.stock_office') }}</span>
                </a>
            @else
                <a href="{{ route('blog.index') }}" 
                   @click="mobileMenuOpen = false"
                   class="block w-full text-center py-2 hover:bg-gray-50 rounded-md transition duration-300 flex items-center justify-center">
                    <i class="fas fa-blog ml-2" style="color: var(--color-earth-green);"></i> 
                    <span>{{ __('navigation.blog') }}</span>
                </a>
            @endif
            @foreach(\App\Models\Page::where('is_published', 1)->where('show_in_header', 1)->get() as $page)
                <a href="{{ url('/pages/' . $page->slug) }}" 
                   @click="mobileMenuOpen = false"
                   class="block w-full text-center py-2 hover:bg-gray-50 rounded-md transition duration-300 flex items-center justify-center">
                    <i class="fas fa-file-alt ml-2" style="color: var(--color-earth-green);"></i> 
                    <span>{{ $page->translated_title }}</span>
                </a>
            @endforeach

            @auth
                <a href="{{ route('support.kb.index') }}"
                   @click="mobileMenuOpen = false"
                   class="block w-full text-center py-2 hover:bg-gray-50 rounded-md transition duration-300 flex items-center justify-center">
                    <i class="fas fa-circle-question ml-2" style="color: var(--color-ocean-blue);"></i>
                    <span>پایگاه دانش</span>
                </a>
            @endauth
            
            <hr class="w-full border-t border-gray-200 my-2">
            
            <!-- Dark Mode Toggle - Mobile -->
            <div class="flex items-center justify-center w-full py-2">
                <span class="text-sm mr-2">{{ __('navigation.footer_theme') }}:</span>
                <div class="theme-toggle" onclick="toggleTheme()" title="{{ __('navigation.theme_toggle') }}">
                    <span class="theme-toggle-icon sun">☀️</span>
                    <span class="theme-toggle-icon moon">🌙</span>
                    <div class="theme-toggle-slider"></div>
                </div>
            </div>
            
            <!-- Language Switcher - Mobile -->
            <div class="flex items-center justify-center gap-2 w-full py-2">
                @php
                    $locales = [
                        'fa' => ['label' => 'فارسی', 'abbr' => 'FA', 'flag' => '🇮🇷'],
                        'en' => ['label' => 'English', 'abbr' => 'EN', 'flag' => '🇬🇧'],
                        'ar' => ['label' => 'العربية', 'abbr' => 'AR', 'flag' => '🇸🇦'],
                    ];
                    $currentLocale = app()->getLocale();
                @endphp
                @foreach($locales as $code => $meta)
                    <a href="{{ route('locale.change', $code) }}" 
                       class="flex items-center text-sm font-vazirmatn px-3 py-1 rounded-full transition {{ $currentLocale === $code ? 'bg-earth-green text-white' : 'bg-light-gray text-gentle-black hover:bg-white' }}">
                        <span class="font-semibold tracking-wider">{{ $meta['abbr'] }}</span>
                        <span class="ltr:ml-1 rtl:mr-1">{{ $meta['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </nav>
    </div>
</header>

<script>
    // Language Switcher Dropdown Handler
    document.addEventListener('DOMContentLoaded', function() {
        const localeToggleButton = document.getElementById('locale-toggle-button');
        const localeDropdown = document.getElementById('locale-dropdown');
        
        if (localeToggleButton && localeDropdown) {
            // Toggle dropdown on button click
            localeToggleButton.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                const isHidden = localeDropdown.classList.contains('hidden');
                
                // Close all other dropdowns if needed
                document.querySelectorAll('[id^="locale-dropdown"]').forEach(dropdown => {
                    if (dropdown !== localeDropdown) {
                        dropdown.classList.add('hidden');
                    }
                });
                
                // Toggle this dropdown
                if (isHidden) {
                    localeDropdown.classList.remove('hidden');
                } else {
                    localeDropdown.classList.add('hidden');
                }
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                const clickedInsideDropdown = localeDropdown.contains(event.target);
                const clickedToggle = localeToggleButton.contains(event.target);
                
                if (!clickedInsideDropdown && !clickedToggle) {
                    localeDropdown.classList.add('hidden');
                }
            });
            
            // Close dropdown on Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    localeDropdown.classList.add('hidden');
                }
            });
            
            // Prevent dropdown from closing when clicking inside
            localeDropdown.addEventListener('click', function(event) {
                event.stopPropagation();
            });
        }
    });
</script>

