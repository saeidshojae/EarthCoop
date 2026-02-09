@extends('layouts.unified')

@section('title', __('navigation.footer_home') . ' - ' . config('app.name', 'EarthCoop'))

@push('styles')
<!-- Swiper -->
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-element-bundle.min.js"></script>
<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
<style>
    /* Sidebar animations */
    .sidebar-menu-item:nth-child(1) .sidebar-menu-link { animation: fadeInUp 0.6s ease-out 0.1s both; }
    .sidebar-menu-item:nth-child(2) .sidebar-menu-link { animation: fadeInUp 0.6s ease-out 0.2s both; }
    .sidebar-menu-item:nth-child(3) .sidebar-menu-link { animation: fadeInUp 0.6s ease-out 0.3s both; }
    .sidebar-menu-item:nth-child(4) .sidebar-menu-link { animation: fadeInUp 0.6s ease-out 0.4s both; }
    .sidebar-menu-item:nth-child(5) .sidebar-menu-link { animation: fadeInUp 0.6s ease-out 0.5s both; }
    .sidebar-menu-item:nth-child(6) .sidebar-menu-link { animation: fadeInUp 0.6s ease-out 0.6s both; }
    .sidebar-menu-item:nth-child(7) .sidebar-menu-link { animation: fadeInUp 0.6s ease-out 0.7s both; }
    .sidebar-menu-item:nth-child(8) .sidebar-menu-link { animation: fadeInUp 0.6s ease-out 0.8s both; }
    .sidebar-menu-item:nth-child(9) .sidebar-menu-link { animation: fadeInUp 0.6s ease-out 0.9s both; }

    /* Hover effects */
    .sidebar-menu-link:hover {
        background-color: var(--color-light-gray);
        color: var(--color-earth-green);
        transform: translateX(-5px);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    /* Swiper Slider */
    swiper-container {
        width: 100%;
        height: auto;
    }
    
    swiper-slide {
        text-align: center;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    
    swiper-slide img {
        display: block;
        width: 100%;
        height: auto;
        object-fit: cover;
        border-radius: 1rem;
    }

    /* Content Card */
    .content-card {
        transition: all 0.3s ease;
    }
    
    .content-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    }

    /* Dark Mode Support for Sidebar */
    body.dark-mode .sidebar-menu-link {
        color: #e0e0e0;
    }

    body.dark-mode .sidebar-menu-link:hover {
        background-color: #3a3a3a;
        color: var(--color-earth-green);
    }

    body.dark-mode aside {
        background-color: #2d2d2d !important;
        border-color: #404040 !important;
    }
</style>
@endpush

@section('content')
<!-- Main Layout: Sidebar first (right in RTL), then Main (left in RTL) -->
<div class="container mx-auto flex flex-col lg:flex-row gap-8 p-6 md:p-8">
    
    <!-- Right Sidebar - Always visible, no toggle -->
    <aside class="w-full lg:w-80 bg-white rounded-2xl shadow-lg p-6 flex-shrink-0 lg:sticky lg:top-24 h-fit border border-gray-200 transition-all duration-300 hover:shadow-xl"
           style="background-color: var(--color-pure-white);">
        
        <div class="pb-4 mb-4 border-b border-gray-200">
            <h2 class="text-2xl font-bold text-gentle-black flex items-center justify-between" style="color: var(--color-gentle-black);">
                <i class="fas fa-bars" style="color: var(--color-earth-green);"></i>
                <span class="mr-3">منو</span>
            </h2>
        </div>
        
        <nav>
            <ul class="space-y-2">
                <!-- Notifications -->
                <li class="sidebar-menu-item">
                    <a href="{{ route('notifications.index') }}" class="sidebar-menu-link block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                        <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                        <div class="flex items-center gap-3">
                            <i class="fas fa-bell" style="color: var(--color-ocean-blue);"></i>
                            <span class="text-right">اعلان‌ها</span>
                        </div>
                        @if(auth()->user()->unreadNotifications->count() > 0)
                        <span class="badge text-white text-xs px-2 py-1 rounded-full font-bold badge-pulse" style="background-color: var(--color-red-tomato);">{{ auth()->user()->unreadNotifications->count() }}</span>
                        @endif
                    </a>
                </li>
                
                <!-- Groups -->
                <li class="sidebar-menu-item">
                    <a href="{{ route('groups.index') }}" class="sidebar-menu-link block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                        <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                        <div class="flex items-center gap-3">
                            <i class="fas fa-users" style="color: var(--color-ocean-blue);"></i>
                            <span class="text-right">{{ __('navigation.footer_my_groups') }}</span>
                        </div>
                        @if($groups->count() > 0)
                        <span class="badge text-xs px-2 py-1 rounded-full font-bold" style="background-color: var(--color-digital-gold); color: var(--color-pure-white);">{{ $groups->count() }}</span>
                        @endif
                    </a>
                </li>
                
                <!-- Collaborations -->
                <li class="sidebar-menu-item">
                    <a href="{{ route('history.index') }}" class="sidebar-menu-link block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                        <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                        <div class="flex items-center gap-3">
                            <i class="fas fa-handshake" style="color: var(--color-digital-gold);"></i>
                            <span class="text-right">مشارکت‌های من</span>
                        </div>
                    </a>
                </li>
                
                <!-- Elections -->
                <li class="sidebar-menu-item">
                    <a href="{{ route('history.election') }}" class="sidebar-menu-link block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                        <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                        <div class="flex items-center gap-3">
                            <i class="fas fa-vote-yea" style="color: var(--color-earth-green);"></i>
                            <span class="text-right">انتخابات جاری</span>
                        </div>
                    </a>
                </li>
                
                <!-- Polls -->
                <li class="sidebar-menu-item">
                    <a href="{{ route('history.poll') }}" class="sidebar-menu-link block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                        <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                        <div class="flex items-center gap-3">
                            <i class="fas fa-chart-pie" style="color: var(--color-ocean-blue);"></i>
                            <span class="text-right">نظرسنجی‌های جاری</span>
                        </div>
                    </a>
                </li>
                
                <!-- Spring Account -->
                @php
                    $accountService = app(\App\Modules\NajmBahar\Services\AccountService::class);
                    $needsNajmAgreement = !$accountService->hasMainAccount(auth()->id());
                @endphp
                <li class="sidebar-menu-item">
                    <a href="{{ route('najm-bahar.dashboard') }}" class="sidebar-menu-link block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group {{ $needsNajmAgreement ? 'blinking-item' : '' }}" style="color: var(--color-gentle-black);">
                        <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                        <div class="flex items-center gap-3">
                            <i class="fas fa-wallet" style="color: var(--color-digital-gold);"></i>
                            <span class="text-right">حساب مالی نجم بهار</span>
                        </div>
                    </a>
                </li>
                
                <!-- Invite Friends -->
                <li class="sidebar-menu-item">
                    <a href="{{ route('my-invation-code') }}" class="sidebar-menu-link block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                        <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                        <div class="flex items-center gap-3">
                            <i class="fas fa-user-plus" style="color: var(--color-earth-green);"></i>
                            <span class="text-right">دعوت از دوستان</span>
                        </div>
                    </a>
                </li>
                
                <!-- Edit Profile -->
                <li class="sidebar-menu-item">
                    <a href="{{ route('profile.edit') }}" class="sidebar-menu-link block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                        <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                        <div class="flex items-center gap-3">
                            <i class="fas fa-cog" style="color: var(--color-ocean-blue);"></i>
                            <span class="text-right">ویرایش حساب کاربری</span>
                        </div>
                    </a>
                </li>
                
                <!-- Support -->
                <li class="sidebar-menu-item" x-data="{ open: false }">
                    <button @click="open = !open" class="sidebar-menu-link w-full block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                        <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                        <div class="flex items-center gap-3">
                            <i class="fas fa-headset" style="color: var(--color-ocean-blue);"></i>
                            <span class="text-right">پشتیبانی</span>
                        </div>
                        <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                    </button>
                    <ul x-show="open"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 transform -translate-y-2"
                        x-transition:enter-end="opacity-100 transform translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 transform translate-y-0"
                        x-transition:leave-end="opacity-0 transform -translate-y-2"
                        class="mr-8 mt-2 space-y-1">
                        <li>
                            <a href="{{ route('support.kb.index') }}" class="sidebar-menu-link block px-4 py-2 rounded-xl text-gentle-black transition duration-200 flex items-center relative group" style="color: var(--color-gentle-black);">
                                <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                                <i class="fas fa-book text-sm" style="color: var(--color-ocean-blue);"></i>
                                <span class="text-right mr-2">پایگاه دانش</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('user.tickets.create') }}" class="sidebar-menu-link block px-4 py-2 rounded-xl text-gentle-black transition duration-200 flex items-center relative group" style="color: var(--color-gentle-black);">
                                <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                                <i class="fas fa-plus-circle text-sm" style="color: var(--color-ocean-blue);"></i>
                                <span class="text-right mr-2">ارسال تیکت</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('user.tickets.index') }}" class="sidebar-menu-link block px-4 py-2 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                                <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-ticket-alt text-sm" style="color: var(--color-ocean-blue);"></i>
                                    <span class="text-right">تیکت‌ها</span>
                                </div>
                                @php
                                    $openTicketsCount = \App\Models\Ticket::where(function($q) {
                                        $q->where('user_id', auth()->id())
                                          ->orWhere('email', auth()->user()->email);
                                    })->whereIn('status', ['open', 'in-progress'])->count();
                                @endphp
                                @if($openTicketsCount > 0)
                                    <span class="badge text-white text-xs px-2 py-1 rounded-full font-bold" style="background-color: var(--color-red-tomato);">{{ $openTicketsCount }}</span>
                                @endif
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('user.support-chat.index') }}" class="sidebar-menu-link block px-4 py-2 rounded-xl text-gentle-black transition duration-200 flex items-center relative group" style="color: var(--color-gentle-black);">
                                <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                                <i class="fas fa-comments text-sm" style="color: var(--color-ocean-blue);"></i>
                                <span class="text-right mr-2">چت پشتیبانی</span>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Admin Panel (if admin) -->
                @if (auth()->user()->is_admin == 1)
                <li class="sidebar-menu-item">
                    <a href="{{ route('admin.dashboard') }}" class="sidebar-menu-link block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                        <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                        <div class="flex items-center gap-3">
                            <i class="fas fa-user-shield" style="color: #9333ea;"></i>
                            <span class="text-right">{{ __('navigation.admin_dashboard') }}</span>
                        </div>
                    </a>
                </li>
                @endif
                
                <!-- Logout -->
                <li class="sidebar-menu-item">
                    <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form-home').submit();" class="sidebar-menu-link block px-4 py-3 rounded-xl text-gentle-black transition duration-200 flex items-center justify-between relative group" style="color: var(--color-gentle-black);">
                        <span class="absolute left-0 top-0 h-full w-1 rounded-l-lg opacity-0 group-hover:opacity-100 transition-all duration-200" style="background-color: var(--color-earth-green);"></span>
                        <div class="flex items-center gap-3">
                            <i class="fas fa-sign-out-alt" style="color: var(--color-digital-gold);"></i>
                            <span class="text-right">{{ __('navigation.logout') }}</span>
                        </div>
                    </a>
                    <form id="logout-form-home" action="{{ route('logout') }}" method="POST" class="hidden">
                        @csrf
                    </form>
                </li>
            </ul>
        </nav>
        
        <div class="mt-6 pt-4 border-t border-gray-200 text-center text-sm text-gray-500">
            نسخه ۲.۱.۰ - EarthCoop
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-grow bg-white rounded-2xl shadow-lg p-6 md:p-8 border border-gray-200" style="background-color: var(--color-pure-white);">
        
        <!-- Welcome Section -->
        <div class="mb-8">
            <h1 class="text-3xl md:text-4xl font-bold text-gentle-black mb-4 text-center" style="color: var(--color-gentle-black);">
                {{ \App\Models\Setting::find(1)->home_titre }}
            </h1>
            <p class="text-center text-gray-600">به سیستم کوپراتیو زمین نو خوش آمدید</p>
        </div>

        <!-- Image Slider -->
        @if(\App\Models\Slider::where('position', 1)->count() > 0)
        <div class="relative w-full mb-8 rounded-xl shadow-md overflow-hidden border border-gray-200 group">
            <swiper-container class="mySwiper" pagination="true" loop="true" autoplay-delay="6000" style="--swiper-pagination-color: var(--color-earth-green); --swiper-pagination-bullet-inactive-color: #d1d5db;">
                @foreach(\App\Models\Slider::where('position', 1)->get() as $slider)
                <swiper-slide>
                    <img src="{{ asset('images/sliders/' . $slider->src) }}" class="w-full h-auto object-cover transition-transform duration-500 group-hover:scale-105" alt="اسلایدر {{ $loop->iteration }}">
                </swiper-slide>
                @endforeach
            </swiper-container>
        </div>
        @endif

        <!-- Home Content -->
        <div class="mb-8 prose prose-lg max-w-none text-gray-700">
            {!! \App\Models\Setting::find(1)->home_content !!}
        </div>

        <!-- Groups Statistics -->
        @if($groups->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            
            <!-- General Groups -->
            <div class="bg-white rounded-xl shadow-md p-6 border border-gray-200 transition-all duration-300 hover:shadow-lg hover:-translate-y-1 content-card" style="background-color: var(--color-pure-white);">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-gentle-black" style="color: var(--color-gentle-black);">گروه‌های عمومی</h3>
                    <div class="w-12 h-12 rounded-full flex items-center justify-center text-2xl" style="background-color: rgba(16, 185, 129, 0.15); color: var(--color-earth-green);">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="text-5xl font-extrabold text-gentle-black font-poppins mb-4" style="color: var(--color-gentle-black);">{{ $generalGroups->count() }}</div>
                <div class="flex items-center text-sm text-gray-600 border-t border-gray-200 pt-4">
                    <i class="fas fa-arrow-up ml-2" style="color: var(--color-earth-green);"></i>
                    <span>فعال و در حال رشد</span>
                </div>
            </div>

            <!-- Specialized Groups -->
            <div class="bg-white rounded-xl shadow-md p-6 border border-gray-200 transition-all duration-300 hover:shadow-lg hover:-translate-y-1 content-card" style="background-color: var(--color-pure-white);">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-gentle-black" style="color: var(--color-gentle-black);">گروه‌های تخصصی</h3>
                    <div class="w-12 h-12 rounded-full flex items-center justify-center text-2xl" style="background-color: rgba(59, 130, 246, 0.15); color: var(--color-ocean-blue);">
                        <i class="fas fa-briefcase"></i>
                    </div>
                </div>
                <div class="text-5xl font-extrabold text-gentle-black font-poppins mb-4" style="color: var(--color-gentle-black);">{{ $specializedGroups->count() }}</div>
                <div class="flex items-center text-sm text-gray-600 border-t border-gray-200 pt-4">
                    <i class="fas fa-arrow-up ml-2" style="color: var(--color-ocean-blue);"></i>
                    <span>تخصصی و پیشرفته</span>
                </div>
            </div>

            <!-- Exclusive Groups -->
            <div class="bg-white rounded-xl shadow-md p-6 border border-gray-200 transition-all duration-300 hover:shadow-lg hover:-translate-y-1 content-card" style="background-color: var(--color-pure-white);">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-gentle-black" style="color: var(--color-gentle-black);">گروه‌های اختصاصی</h3>
                    <div class="w-12 h-12 rounded-full flex items-center justify-center text-2xl" style="background-color: rgba(147, 51, 234, 0.15); color: #9333ea;">
                        <i class="fas fa-star"></i>
                    </div>
                </div>
                <div class="text-5xl font-extrabold text-gentle-black font-poppins mb-4" style="color: var(--color-gentle-black);">{{ $exclusiveGroups->count() }}</div>
                <div class="flex items-center text-sm text-gray-600 border-t border-gray-200 pt-4">
                    <i class="fas fa-arrow-up ml-2" style="color: #9333ea;"></i>
                    <span>ویژه و انحصاری</span>
                </div>
            </div>
        </div>
        @endif

        <!-- Active Auctions -->
        @if(isset($activeAuctions) && $activeAuctions->count() > 0)
        <div class="mt-8 p-6 bg-white rounded-xl shadow-md border border-gray-200" style="background-color: var(--color-pure-white);">
            <h2 class="text-2xl font-bold text-gentle-black mb-6 flex items-center gap-3" style="color: var(--color-gentle-black);">
                <i class="fas fa-gavel" style="color: var(--color-earth-green);"></i>
                {{ __('navigation.auctions') }}
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach($activeAuctions as $auction)
                <div class="border border-gray-200 rounded-xl p-6 hover:shadow-lg transition-all content-card">
                    <h3 class="font-semibold text-gentle-black mb-2" style="color: var(--color-gentle-black);">{{ $auction->stock->name ?? 'حراج' }}</h3>
                    <p class="text-sm text-gray-600 mb-4">پایان: {{ $auction->ends_at->diffForHumans() }}</p>
                    <a href="#" class="inline-block px-4 py-2 rounded-lg text-white transition-all" style="background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);">
                        مشاهده جزئیات
                    </a>
                </div>
                @endforeach
            </div>
        </div>
        @endif

    </main>
</div>

<!-- Success/Error Alerts -->
@if(session('success'))
<div x-data="{ show: true }" 
     x-show="show" 
     x-init="setTimeout(() => show = false, 5000)"
     class="fixed bottom-4 left-4 bg-white rounded-xl shadow-2xl p-4 z-50 max-w-md"
     style="display: none; background-color: var(--color-pure-white);"
     x-cloak>
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: var(--color-earth-green);">
            <i class="fas fa-check text-white"></i>
        </div>
        <div class="flex-1">
            <p class="font-semibold text-gentle-black" style="color: var(--color-gentle-black);">موفقیت</p>
            <p class="text-sm text-gray-600">{{ session('success') }}</p>
        </div>
        <button @click="show = false" class="text-gray-400 hover:text-gray-600">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>
@endif

@if(session('error'))
<div x-data="{ show: true }" 
     x-show="show" 
     x-init="setTimeout(() => show = false, 5000)"
     class="fixed bottom-4 left-4 bg-white rounded-xl shadow-2xl p-4 z-50 max-w-md"
     style="display: none; background-color: var(--color-pure-white);"
     x-cloak>
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: var(--color-red-tomato);">
            <i class="fas fa-exclamation text-white"></i>
        </div>
        <div class="flex-1">
            <p class="font-semibold text-gentle-black" style="color: var(--color-gentle-black);">خطا</p>
            <p class="text-sm text-gray-600">{{ session('error') }}</p>
        </div>
        <button @click="show = false" class="text-gray-400 hover:text-gray-600">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>
@endif
@endsection
