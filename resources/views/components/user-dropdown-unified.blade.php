{{-- User Dropdown Component - بر اساس طراحی Home --}}
<div class="relative" x-data="{ userDropdownOpen: false }" x-on:click.away="userDropdownOpen = false">
    <button @click.stop="userDropdownOpen = !userDropdownOpen" 
            class="px-4 py-2 md:px-4 md:py-2 rounded-full md:rounded-full shadow-md transition duration-300 font-medium transform hover:scale-105 active:scale-100 flex items-center justify-center ripple user-dropdown-btn" 
            style="background-color: var(--color-earth-green); color: var(--color-pure-white);"
            :style="userDropdownOpen ? 'background-color: var(--color-earth-green) !important; color: var(--color-pure-white) !important;' : 'background-color: var(--color-earth-green) !important; color: var(--color-pure-white) !important;'">
        @php
            $user = Auth::user();
            $hasAvatar = $user && $user->avatar;
        @endphp
        
        @if($hasAvatar)
            {{-- نمایش عکس پروفایل در موبایل --}}
            <img src="{{ asset('images/users/avatars/' . $user->avatar) }}" 
                 alt="{{ $user->fullName() }}"
                 class="w-10 h-10 md:w-8 md:h-8 rounded-full object-cover border-2 border-white/30 md:ml-2 user-avatar-img"
                 style="display: block;">
        @else
            {{-- نمایش آیکن کاربر در موبایل --}}
            <div class="w-10 h-10 md:w-8 md:h-8 bg-white/20 rounded-full flex items-center justify-center text-white text-lg md:ml-2 user-avatar-icon" style="flex-shrink: 0;">
                <i class="fas fa-user"></i>
            </div>
        @endif
        
        {{-- نام کاربری فقط در دسکتاپ --}}
        <span class="hidden md:inline">{{ $user ? $user->fullName() : '' }}</span>
        
        {{-- آیکن dropdown فقط در دسکتاپ --}}
        <i class="fas fa-chevron-down hidden md:block mr-2 text-sm transition-transform duration-300" 
           :class="{ 'rotate-180': userDropdownOpen }"></i>
    </button>
    
    <div x-show="userDropdownOpen" 
         @click.stop
         x-transition:enter="transition ease-out duration-200" 
         x-transition:enter-start="opacity-0 scale-95" 
         x-transition:enter-end="opacity-100 scale-100" 
         x-transition:leave="transition ease-in duration-150" 
         x-transition:leave-start="opacity-100 scale-100" 
         x-transition:leave-end="opacity-0 scale-95" 
         class="absolute right-0 mt-2 w-72 md:w-80 bg-white rounded-lg shadow-xl py-2 z-[9999] text-right origin-top-right max-h-[calc(100vh-120px)] md:max-h-[80vh] overflow-y-auto chat-user-dropdown" 
         x-cloak
         style="display: none; background-color: var(--color-pure-white); scrollbar-width: thin; scrollbar-color: #10b981 #f3f4f6; right: 0 !important; left: auto !important; transform-origin: top right !important;"
         x-ref="dropdown">
        
        <a href="{{ route('profile.show') }}" 
           class="block px-4 py-2 hover:bg-gray-50 transition duration-200 flex items-center gap-3" 
           style="color: var(--color-earth-green);">
            <i class="fas fa-user-circle"></i>
            <span class="text-right">{{ __('navigation.profile') }}</span>
        </a>
        
        <hr class="my-1 border-gray-200">

        <h6 class="px-4 py-2 text-sm font-bold text-right" style="color: var(--color-ocean-blue); text-align: right !important;">پشتیبانی</h6>

        <a href="{{ route('support.kb.index') }}"
           class="block px-4 py-2 hover:bg-gray-50 transition duration-200 flex items-center gap-3"
           style="color: var(--color-gentle-black);">
            <i class="fas fa-book"></i>
            <span class="text-right">پایگاه دانش</span>
        </a>

        <a href="{{ route('user.tickets.index') }}"
           class="block px-4 py-2 hover:bg-gray-50 transition duration-200 flex items-center gap-3"
           style="color: var(--color-gentle-black);">
            <i class="fas fa-ticket-alt"></i>
            <span class="text-right">تیکت‌ها</span>
        </a>

        <a href="{{ route('user.support-chat.index') }}"
           class="block px-4 py-2 hover:bg-gray-50 transition duration-200 flex items-center gap-3"
           style="color: var(--color-gentle-black);">
            <i class="fas fa-comments"></i>
            <span class="text-right">چت پشتیبانی</span>
        </a>

        <hr class="my-1 border-gray-200">
        
        <h6 class="px-4 py-2 text-sm font-bold text-right" style="color: var(--color-ocean-blue); text-align: right !important;">{{ __('navigation.stock_office_section') }}</h6>
        
        <a href="{{ route('auction.index') }}" 
           class="block px-4 py-2 hover:bg-gray-50 transition duration-200 flex items-center gap-3" 
           style="color: var(--color-gentle-black);">
            <i class="fas fa-gavel"></i>
            <span class="text-right">{{ __('navigation.auctions') }}</span>
        </a>
        
        <a href="{{ route('wallet.index') }}" 
           class="block px-4 py-2 hover:bg-gray-50 transition duration-200 flex items-center gap-3" 
           style="color: var(--color-gentle-black);">
            <i class="fas fa-wallet"></i>
            <span class="text-right">{{ __('navigation.wallet') }}</span>
        </a>
        
        <a href="{{ route('holding.index') }}" 
           class="block px-4 py-2 hover:bg-gray-50 transition duration-200 flex items-center gap-3" 
           style="color: var(--color-gentle-black);">
            <i class="fas fa-chart-line"></i>
            <span class="text-right">{{ __('navigation.holdings') }}</span>
        </a>
        
        @if (auth()->check() && auth()->user()->is_admin == 1)
            <hr class="my-1 border-gray-200">
            <h6 class="px-4 py-2 text-sm font-bold text-right" style="color: var(--color-ocean-blue); text-align: right !important;">{{ __('navigation.admin_section') }}</h6>
            <a href="{{ route('admin.dashboard') }}" 
               class="block px-4 py-2 hover:bg-gray-50 transition duration-200 flex items-center gap-3" 
               style="color: var(--color-gentle-black);">
                <i class="fas fa-cog"></i>
                <span class="text-right">{{ __('navigation.admin_dashboard') }}</span>
            </a>
            <a href="{{ route('admin.blog.dashboard') }}" 
               class="block px-4 py-2 hover:bg-gray-50 transition duration-200 flex items-center gap-3" 
               style="color: var(--color-gentle-black);">
                <i class="fas fa-blog"></i>
                <span class="text-right">{{ __('navigation.admin_blog') }}</span>
            </a>
        @endif
        
        <hr class="my-1 border-gray-200">
        
        <a href="{{ route('terms') }}" 
           class="block px-4 py-2 hover:bg-gray-50 transition duration-200 flex items-center gap-3" 
           style="color: var(--color-gentle-black);">
            <i class="fas fa-file-alt"></i>
            <span class="text-right">{{ __('navigation.charter') }}</span>
        </a>
        
        <a href="{{ route('spring-accounts.agreement') }}" 
           class="block px-4 py-2 hover:bg-gray-50 transition duration-200 flex items-center gap-3" 
           style="color: var(--color-gentle-black);">
            <i class="fas fa-file-contract"></i>
            <span class="text-right">{{ __('navigation.financial_agreement') }}</span>
        </a>
        
        <hr class="my-1 border-gray-200">
        
        <a href="{{ route('logout') }}" 
           class="block px-4 py-2 hover:bg-red-50 transition duration-200 flex items-center gap-3" 
           style="color: var(--color-red-tomato);"
           onclick="event.preventDefault(); document.getElementById('logout-form-unified').submit();">
            <i class="fas fa-sign-out-alt"></i>
            <span class="text-right">{{ __('navigation.logout') }}</span>
        </a>
        
        <form id="logout-form-unified" action="{{ route('logout') }}" method="POST" class="hidden">
            @csrf
        </form>
    </div>
</div>

<script>
    // Fix dropdown positioning on mobile and prevent page shift
    document.addEventListener('DOMContentLoaded', function() {
        const dropdownContainer = document.querySelector('[x-data*="userDropdownOpen"]');
        const dropdown = dropdownContainer?.querySelector('[x-show]');
        const button = dropdownContainer?.querySelector('button');
        
        if (!dropdown || !button) return;
        
        // Function to check if dropdown is visible
        function isDropdownVisible() {
            const style = getComputedStyle(dropdown);
            return style.display !== 'none' && 
                   style.visibility !== 'hidden' && 
                   dropdown.offsetParent !== null &&
                   !dropdown.hasAttribute('x-cloak');
        }
        
        // Function to fix positioning
        function fixDropdownPosition() {
            if (window.innerWidth <= 768) {
                const isVisible = isDropdownVisible();
                
                if (isVisible) {
                    // Get button position
                    const buttonRect = button.getBoundingClientRect();
                    const viewportWidth = window.innerWidth;
                    const viewportHeight = window.innerHeight;
                    const dropdownWidth = Math.min(280, viewportWidth - 16);
                    
                    // Calculate right position (distance from right edge)
                    const rightPos = Math.max(8, viewportWidth - buttonRect.right);
                    
                    // Calculate top position (below button with small gap)
                    let topPos = buttonRect.bottom + 8;
                    
                    // Set fixed position immediately
                    dropdown.style.position = 'fixed';
                    dropdown.style.right = rightPos + 'px';
                    dropdown.style.left = 'auto';
                    dropdown.style.top = topPos + 'px';
                    dropdown.style.width = dropdownWidth + 'px';
                    dropdown.style.maxWidth = dropdownWidth + 'px';
                    
                    // After a short delay, check height and adjust if needed
                    setTimeout(function() {
                        const dropdownHeight = dropdown.offsetHeight || 400;
                        if (topPos + dropdownHeight > viewportHeight) {
                            // Position above button if it doesn't fit below
                            topPos = buttonRect.top - dropdownHeight - 8;
                            if (topPos < 8) {
                                // If still doesn't fit, position at top with max height
                                topPos = 8;
                                dropdown.style.maxHeight = (viewportHeight - 16) + 'px';
                            } else {
                                dropdown.style.top = topPos + 'px';
                            }
                        } else {
                            dropdown.style.maxHeight = Math.min(400, viewportHeight - topPos - 8) + 'px';
                        }
                    }, 10);
                    
                    // Prevent body horizontal scroll
                    document.body.style.overflowX = 'hidden';
                    document.body.style.position = 'relative';
                    document.documentElement.style.overflowX = 'hidden';
                    document.body.classList.add('dropdown-open');
                    document.documentElement.classList.add('dropdown-open');
                } else {
                    // Restore when closed
                    document.body.style.overflowX = '';
                    document.body.style.position = '';
                    document.documentElement.style.overflowX = '';
                    document.body.classList.remove('dropdown-open');
                    document.documentElement.classList.remove('dropdown-open');
                }
            } else {
                // Desktop: restore normal behavior
                document.body.style.overflowX = '';
                document.body.style.position = '';
                document.documentElement.style.overflowX = '';
                document.body.classList.remove('dropdown-open');
                document.documentElement.classList.remove('dropdown-open');
            }
        }
        
        // Watch for Alpine.js state changes
        if (window.Alpine) {
            // Wait for Alpine to initialize
            setTimeout(function() {
                if (dropdownContainer.__x) {
                    Alpine.effect(function() {
                        const isOpen = dropdownContainer.__x.$data.userDropdownOpen;
                        if (isOpen) {
                            setTimeout(fixDropdownPosition, 50);
                        } else {
                            document.body.style.overflowX = '';
                            document.body.style.position = '';
                            document.documentElement.style.overflowX = '';
                            document.body.classList.remove('dropdown-open');
                            document.documentElement.classList.remove('dropdown-open');
                        }
                    });
                }
            }, 100);
        }
        
        // Also watch for style and class changes
        const observer = new MutationObserver(function() {
            setTimeout(fixDropdownPosition, 10);
        });
        
        observer.observe(dropdown, {
            attributes: true,
            attributeFilter: ['style', 'class'],
            childList: false,
            subtree: false
        });
        
        // Watch button clicks
        button.addEventListener('click', function() {
            setTimeout(fixDropdownPosition, 100);
        });
        
        // Handle window resize
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(fixDropdownPosition, 100);
        });
        
        // Handle scroll (close dropdown on scroll)
        window.addEventListener('scroll', function() {
            if (isDropdownVisible() && window.innerWidth <= 768) {
                fixDropdownPosition();
            }
        });
        
        // Initial check after a short delay
        setTimeout(fixDropdownPosition, 200);
    });
</script>


