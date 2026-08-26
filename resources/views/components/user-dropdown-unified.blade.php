{{-- User Dropdown Component - بر اساس طراحی Home --}}

<div class="relative user-dropdown-root" 
     x-data="userDropdownData()"
     @click.away="userDropdownOpen = false"
     x-init="init()">
    
    <script>
        function userDropdownData() {
            return {
                userDropdownOpen: false,
                pendingChatRequests: 0,
                fetchChatRequests() {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    fetch('{{ route('chat-requests.pending-count') }}', {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        credentials: 'same-origin'
                    })
                    .then(response => {
                        const contentType = response.headers.get('content-type') || '';
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}`);
                        }
                        if (!contentType.includes('application/json')) {
                            throw new Error('Non-JSON response received');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.pending_count !== undefined) {
                            this.pendingChatRequests = data.pending_count;
                        }
                    })
                    .catch(error => {
                        console.error('درخواست چت بار نشد:', error);
                    });
                },
                init() {
                    this.fetchChatRequests();
                }
            };
        }
    </script>

    <button @click.stop="userDropdownOpen = !userDropdownOpen" 
            class="px-4 py-2 md:px-4 md:py-2 rounded-full md:rounded-full shadow-md transition duration-300 font-medium transform hover:scale-105 active:scale-100 flex items-center justify-center ripple user-dropdown-btn" 
            style="background-color: var(--color-earth-green); color: var(--color-pure-white);"
            :style="userDropdownOpen ? 'background-color: var(--color-earth-green) !important; color: var(--color-pure-white) !important;' : 'background-color: var(--color-earth-green) !important; color: var(--color-pure-white) !important;'"
            aria-haspopup="true"
            :aria-expanded="userDropdownOpen ? 'true' : 'false'"
            aria-controls="user-dropdown-menu">
        @php
            $user = Auth::user();
            $hasAvatar = $user && $user->avatar;
        @endphp
        
        @if($hasAvatar)
            <img src="{{ asset('images/users/avatars/' . $user->avatar) }}" 
                 alt="{{ $user->fullName() }}"
                 class="w-10 h-10 md:w-8 md:h-8 rounded-full object-cover border-2 border-white/30 md:ml-2 user-avatar-img"
                 style="display: block;">
        @else
            <div class="w-10 h-10 md:w-8 md:h-8 bg-white/20 rounded-full flex items-center justify-center text-white text-lg md:ml-2 user-avatar-icon" style="flex-shrink: 0;">
                <i class="fas fa-user"></i>
            </div>
        @endif
        
        <span class="hidden md:inline">{{ $user ? $user->fullName() : '' }}</span>
        
        <i class="fas fa-chevron-down hidden md:block mr-2 text-sm transition-transform duration-300" 
           :class="{ 'rotate-180': userDropdownOpen }"></i>
    </button>
    
    <div id="user-dropdown-menu"
         x-show="userDropdownOpen" 
         @click.stop
         x-transition:enter="transition ease-out duration-200" 
         x-transition:enter-start="opacity-0 scale-95" 
         x-transition:enter-end="opacity-100 scale-100" 
         x-transition:leave="transition ease-in duration-150" 
         x-transition:leave-start="opacity-100 scale-100" 
         x-transition:leave-end="opacity-0 scale-95" 
         class="absolute right-0 mt-2 w-72 md:w-80 bg-white rounded-lg shadow-xl py-2 z-[9999] text-right origin-top-right max-h-[calc(100vh-120px)] md:max-h-[80vh] overflow-y-auto chat-user-dropdown" 
         x-cloak
         role="menu"
         aria-label="منوی کاربر"
         style="display: none; background-color: var(--color-pure-white); scrollbar-width: thin; scrollbar-color: #10b981 #f3f4f6; right: 0 !important; left: auto !important; transform-origin: top right !important;"
         x-ref="dropdown">
        
        <a href="{{ route('profile.show') }}" 
           class="block px-4 py-2 hover:bg-gray-50 transition duration-200 flex items-center gap-3" 
           style="color: var(--color-earth-green);">
            <i class="fas fa-user-circle"></i>
            <span class="text-right">{{ __('navigation.profile') }}</span>
        </a>
        
        <hr class="my-1 border-gray-200">

        <!-- Chat Requests with Badge -->
        <a href="{{ route('chat-requests.index') }}"
           class="block px-4 py-2 hover:bg-gray-50 transition duration-200 flex items-center gap-3 relative"
           style="color: var(--color-gentle-black);">
            <i class="fas fa-comment-dots"></i>
            <span class="text-right">درخواست چت</span>
            <span x-show="pendingChatRequests > 0"
                  x-text="pendingChatRequests"
                  class="ltr:ml-auto rtl:mr-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
            </span>
        </a>

        <hr class="my-1 border-gray-200">

        @php
            $currentGroupForSecretariat = request()->route('group');
            if (! $currentGroupForSecretariat instanceof \App\Models\Group) {
                $currentGroupForSecretariat = null;
            }
        @endphp

        <h6 class="px-4 py-2 text-sm font-bold text-right" style="color: var(--color-ocean-blue); text-align: right !important;">دبیرخانه</h6>

        <a href="{{ route('secretariat.directory') }}"
           class="block px-4 py-2 hover:bg-gray-50 transition duration-200 flex items-center gap-3"
           style="color: var(--color-gentle-black);">
            <i class="fas fa-box-archive"></i>
            <span class="text-right">دبیرخانه‌های من</span>
        </a>

        @if($currentGroupForSecretariat)
            <a href="{{ route('secretariat.group', $currentGroupForSecretariat) }}"
               class="block px-4 py-2 hover:bg-gray-50 transition duration-200 flex items-center gap-3"
               style="color: var(--color-gentle-black);">
                <i class="fas fa-people-group"></i>
                <span class="text-right">دبیرخانه گروه</span>
            </a>
        @endif

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
            <a href="{{ route('secretariat.central') }}"
               class="block px-4 py-2 hover:bg-gray-50 transition duration-200 flex items-center gap-3"
               style="color: var(--color-gentle-black);">
                <i class="fas fa-building-columns"></i>
                <span class="text-right">دبیرخانه مرکزی</span>
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
        
        <a href="{{ route('najm-bahar.agreement') }}" 
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
    // Keep dropdown anchored to its own button in both desktop and mobile views.
    document.addEventListener('DOMContentLoaded', function() {
        const roots = document.querySelectorAll('.user-dropdown-root');
        if (!roots.length) return;

        roots.forEach(function(root) {
            const dropdown = root.querySelector('.chat-user-dropdown');
            const button = root.querySelector('.user-dropdown-btn');
            if (!dropdown || !button) return;
            let positionFrame = null;

            function isDropdownVisible() {
                const style = getComputedStyle(dropdown);
                return style.display !== 'none' && style.visibility !== 'hidden' && !dropdown.hasAttribute('x-cloak');
            }

            function applyDesktopPosition() {
                dropdown.style.position = 'absolute';
                dropdown.style.removeProperty('left');
                dropdown.style.setProperty('right', '0', 'important');
                dropdown.style.top = '';
                dropdown.style.width = '';
                dropdown.style.maxWidth = '';
                dropdown.style.maxHeight = '80vh';
            }

            function applyMobilePosition() {
                const buttonRect = button.getBoundingClientRect();
                const viewportWidth = window.innerWidth;
                const viewportHeight = window.innerHeight;
                const dropdownWidth = Math.min(280, viewportWidth - 16);
                const dropdownLeft = Math.max(
                    8,
                    Math.min(buttonRect.right - dropdownWidth, viewportWidth - dropdownWidth - 8)
                );
                let topPos = buttonRect.bottom + 8;

                dropdown.style.position = 'fixed';
                dropdown.style.setProperty('left', dropdownLeft + 'px', 'important');
                dropdown.style.setProperty('right', 'auto', 'important');
                dropdown.style.top = topPos + 'px';
                dropdown.style.width = dropdownWidth + 'px';
                dropdown.style.maxWidth = dropdownWidth + 'px';

                const dropdownHeight = dropdown.offsetHeight || 400;
                if (topPos + dropdownHeight > viewportHeight) {
                    topPos = buttonRect.top - dropdownHeight - 8;
                    if (topPos < 8) {
                        topPos = 8;
                        dropdown.style.maxHeight = (viewportHeight - 16) + 'px';
                    } else {
                        dropdown.style.maxHeight = Math.min(400, viewportHeight - topPos - 8) + 'px';
                    }
                    dropdown.style.top = topPos + 'px';
                } else {
                    dropdown.style.maxHeight = Math.min(400, viewportHeight - topPos - 8) + 'px';
                }

            }

            function updatePosition() {
                if (!isDropdownVisible()) return;

                if (window.innerWidth <= 768) {
                    applyMobilePosition();
                } else {
                    applyDesktopPosition();
                }
            }

            function schedulePositionUpdate() {
                if (positionFrame !== null) {
                    cancelAnimationFrame(positionFrame);
                }

                positionFrame = requestAnimationFrame(function() {
                    positionFrame = null;
                    updatePosition();
                });
            }

            button.addEventListener('click', function() {
                schedulePositionUpdate();
            });

            window.addEventListener('resize', function() {
                if (isDropdownVisible()) schedulePositionUpdate();
            });

            window.addEventListener('scroll', function() {
                if (window.innerWidth <= 768 && isDropdownVisible()) {
                    schedulePositionUpdate();
                }
            }, { passive: true });
        });
    });
</script>
