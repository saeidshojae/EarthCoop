@extends('layouts.unified')

@section('title', __('navigation.footer_home') . ' - ' . config('app.name', 'EarthCoop'))

@push('styles')



<!-- Swiper -->
<script src="{{ asset("vendor/swiper/swiper-element-bundle.min.js") }}"></script>


<!-- Tailwind & Bootstrap CSS via Vite -->
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

    @media (max-width: 1023px) {
        .home-layout {
            padding-top: 0.75rem;
            gap: 1.25rem;
        }

        .home-sidebar-toggle {
            min-height: 3.5rem;
        }

        .home-sidebar-nav > ul {
            padding: 0.75rem;
        }
    }
</style>
@endpush
@section('content')


<!-- Main Layout: Sidebar first (right in RTL), then Main (left in RTL) -->
<div class="home-layout container mx-auto flex flex-col lg:flex-row gap-8 p-6 md:p-8">


    <!-- Right Sidebar -->
    @include('partials.sidebar-unified')

    <!-- Main Content -->
    <main class="flex-grow bg-white rounded-2xl shadow-lg p-6 md:p-8 border border-gray-200" style="background-color: var(--color-pure-white);">


        <!-- Welcome Section -->
        <div class="mb-8">
            <h1 class="text-3xl md:text-4xl font-bold text-gentle-black mb-4 text-center" style="color: var(--color-gentle-black);">
                {{ \App\Models\Setting::find(1)->home_titre }}
            </h1>
            <p class="text-center text-gray-600">به زمین نو خوش آمدید</p>
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
