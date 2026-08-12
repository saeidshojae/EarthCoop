<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ get_direction() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="format-detection" content="telephone=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#4c7caf">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon.svg') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/icon.svg') }}">
    <title>{{ __('langWelcome.site_title') }}</title>

    <!-- Tailwind & Bootstrap CSS via Vite -->
    @vite(['resources/js/app.js'])
    <script defer src="{{ asset('vendor/alpinejs/cdn.min.js') }}"></script>

    <!-- Fonts with preconnect for better performance -->
    <link rel="stylesheet" href="{{ asset('Css/fonts-local.css') }}">
    <link rel="stylesheet" href="{{ asset("vendor/fontawesome/css/all.min.css") }}">

    <!-- Dark Mode Styles -->
    <link rel="stylesheet" href="{{ asset('Css/dark-mode.css') }}">
    <link rel="stylesheet" href="{{ asset('Css/dark-mode-enhanced.css') }}">
    <script src="{{ asset('js/dark-mode.js') }}"></script>

    <!-- Modern CSS via Vite (no fallbacks needed) -->

    <style>
        /* Custom Tailwind Configuration - پیکربندی رنگ‌ها و فونت‌ها */
        :root {
            --color-earth-green: #10b981;
            --color-ocean-blue: #3b82f6;
            --color-digital-gold: #f59e0b;
            --color-pure-white: #ffffff;
            --color-light-gray: #f8fafc;
            --color-gentle-black: #1e293b;
            --color-dark-green: #047857;
            --color-dark-blue: #1d4ed8;
            --color-accent-peach: #ff7e5f;
            --color-accent-sky: #6dd5ed;
            --color-purple-700: #6B46C1;
        }

        /* Utility classes for custom colors */
        .bg-earth-green { background-color: var(--color-earth-green); }
        .text-earth-green { color: var(--color-earth-green); }
        .bg-ocean-blue { background-color: var(--color-ocean-blue); }
        .text-ocean-blue { color: var(--color-ocean-blue); }
        .bg-digital-gold { background-color: var(--color-digital-gold); }
        .text-digital-gold { color: var(--color-digital-gold); }
        .bg-pure-white { background-color: var(--color-pure-white); }
        .text-pure-white { color: var(--color-pure-white); }
        .bg-light-gray { background-color: var(--color-light-gray); }
        .text-light-gray { color: var(--color-light-gray); }
        .bg-gentle-black { background-color: var(--color-gentle-black); }
        .text-gentle-black { color: var(--color-gentle-black); }
        .bg-dark-green { background-color: var(--color-dark-green); }
        .bg-dark-blue { background-color: var(--color-dark-blue); }
        .bg-accent-peach { background-color: var(--color-accent-peach); }
        .text-accent-peach { color: var(--color-accent-peach); }
        .bg-accent-sky { background-color: var(--color-accent-sky); }
        .text-accent-sky { color: var(--color-accent-sky); }
        .text-purple-700 { color: var(--color-purple-700); }

        /* Font Families with fallback */
        .font-vazirmatn { 
            font-family: 'Vazirmatn', 'Tahoma', 'Arial', sans-serif; 
            font-display: swap; /* بهبود لود فونت */
        }
        .font-poppins { 
            font-family: 'Poppins', 'Arial', sans-serif; 
            font-display: swap;
        }

        /* Custom animations */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        @keyframes glow {
            0% { box-shadow: 0 0 15px rgba(245, 158, 11, 0.4), 0 0 25px rgba(245, 158, 11, 0.2); }
            50% { box-shadow: 0 0 30px rgba(245, 158, 11, 0.7), 0 0 40px rgba(245, 158, 11, 0.4); }
            100% { box-shadow: 0 0 15px rgba(245, 158, 11, 0.4), 0 0 25px rgba(245, 158, 11, 0.2); }
        }

        @keyframes pulse-light {
            0% { opacity: 1; }
            50% { opacity: 0.8; }
            100% { opacity: 1; }
        }

        @keyframes rotate {
            0% { transform: rotateY(0deg); }
            100% { transform: rotateY(360deg); }
        }

        @keyframes pulse {
            0% { transform: translate(-50%, -50%) translateZ(75px) scale(1); opacity: 1; }
            100% { transform: translate(-50%, -50%) translateZ(75px) scale(1.1); opacity: 0.8; }
        }

        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        @keyframes bounce-custom {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }

        /* Apply animations */
        .animate-glow { animation: glow 3s infinite ease-in-out; }
        .animate-float { animation: float 6s infinite ease-in-out; }
        .animate-pulse-light { animation: pulse-light 2s infinite ease-in-out; }
        .animate-bounce-custom { animation: bounce-custom 3s infinite ease-in-out; }

        /* Fade in animation for success messages */
        @keyframes fade-in {
            0% {
                opacity: 0;
                transform: translate(-50%, -20px);
            }
            100% {
                opacity: 1;
                transform: translate(-50%, 0);
            }
        }

        .animate-fade-in {
            animation: fade-in 0.5s ease-out;
        }

        /* Smooth scroll animations */
        .fade-in-section {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s ease-out, transform 0.8s ease-out;
        }

        .fade-in-section.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Hamburger menu styling */
        .hamburger-menu {
            display: flex;
            flex-direction: column;
            justify-content: space-around;
            width: 30px;
            height: 25px;
            cursor: pointer;
            z-index: 50;
        }

        .hamburger-menu span {
            display: block;
            width: 100%;
            height: 3px;
            background-color: var(--color-gentle-black);
            border-radius: 5px;
            transition: all 0.3s ease-in-out;
        }

        .hamburger-menu.open span:nth-child(1) { transform: translateY(11px) rotate(45deg); }
        .hamburger-menu.open span:nth-child(2) { opacity: 0; }
        .hamburger-menu.open span:nth-child(3) { transform: translateY(-11px) rotate(-45deg); }

        .mobile-nav-menu {
            position: absolute;
            top: 100%;
            right: 0;
            width: 100%;
            background-color: var(--color-pure-white);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            padding: 1rem 0;
            z-index: 40;
        }

        /* Gradient backgrounds */
        .hero-gradient {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(59, 130, 246, 0.15) 100%);
        }

        .feature-card {
            background: linear-gradient(145deg, #ffffff 0%, #f0f4f7 100%);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.08);
            transition: all 0.4s ease;
            border-radius: 18px;
            overflow: hidden;
            position: relative;
            border: 1px solid rgba(220, 220, 220, 0.3);
        }

        .feature-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.15);
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, var(--color-earth-green), var(--color-ocean-blue), var(--color-digital-gold));
        }

        .testimonial-card {
            background: linear-gradient(145deg, #ffffff 0%, #f0f4f7 100%);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.08);
            border-radius: 18px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(220, 220, 220, 0.3);
            transition: all 0.4s ease;
        }

        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }

        .testimonial-card::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, var(--color-earth-green), var(--color-ocean-blue));
        }

        /* How it works steps */
        .how-it-works-step {
            position: relative;
            padding-bottom: 40px;
        }

        .how-it-works-step:not(:last-child)::after {
            content: none;
        }

        @media (max-width: 768px) {
            .how-it-works-step {
                padding-bottom: 60px;
            }
            .how-it-works-step:not(:last-child)::after {
                content: none;
            }
        }

        /* Stats item styling for RTL */
        .stats-item {
            position: relative;
            padding-right: 35px;
            text-align: right;
        }

        [dir="ltr"] .stats-item {
            padding-right: 0;
            padding-left: 35px;
            text-align: left;
        }

        .stats-item::before {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            -webkit-transform: translateY(-50%);
            -ms-transform: translateY(-50%);
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: var(--color-earth-green);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Font Awesome 5 Free', 'Font Awesome 6 Free';
            font-weight: 900;
            color: var(--color-pure-white);
            font-size: 0.8rem;
        }

        [dir="ltr"] .stats-item::before {
            right: auto;
            left: 0;
        }

        .stats-item:nth-child(1)::before { content: '\f007'; background-color: var(--color-earth-green); }
        .stats-item:nth-child(2)::before { content: '\f0ae'; background-color: var(--color-ocean-blue); }
        .stats-item:nth-child(3)::before { content: '\f0ac'; background-color: var(--color-digital-gold); }

        /* RTL specific adjustments for floating cards */
/* ======================================== */
/* کارت‌های شناور روی تصویر - نسخه اصلاح‌شده */
/* ======================================== */

.hero-image-card-right {
    position: absolute;
    bottom: -6px;
    left: -6px;
    background-color: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    padding: 0.75rem;
    border-radius: 1rem;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
    display: flex;
    align-items: center;
    transform: rotate(-6deg);
    transition: transform 0.5s;
    -webkit-transform: rotate(-6deg);
    -ms-transform: rotate(-6deg);
    max-width: 200px;
    z-index: 10;
}

.hero-image-card-right:hover {
    transform: rotate(0deg);
    -webkit-transform: rotate(0deg);
    -ms-transform: rotate(0deg);
}

[dir="rtl"] .hero-image-card-right {
    left: auto;
    right: -6px;
}

.hero-image-card-left {
    position: absolute;
    top: -6px;
    right: -6px;
    background-color: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    padding: 0.75rem;
    border-radius: 1rem;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
    display: flex;
    align-items: center;
    transform: rotate(6deg);
    transition: transform 0.5s;
    -webkit-transform: rotate(6deg);
    -ms-transform: rotate(6deg);
    max-width: 200px;
    z-index: 10;
}

.hero-image-card-left:hover {
    transform: rotate(0deg);
    -webkit-transform: rotate(0deg);
    -ms-transform: rotate(0deg);
}

[dir="rtl"] .hero-image-card-left {
    right: auto;
    left: -6px;
}

/* Media Queries برای کارت‌ها در موبایل */
@media (max-width: 640px) {
    .hero-image-card-right,
    .hero-image-card-left {
        padding: 0.5rem;
        max-width: 150px;
        border-radius: 0.75rem;
    }
    
    .hero-image-card-right .w-10,
    .hero-image-card-left .w-10 {
        width: 1.75rem;
        height: 1.75rem;
        font-size: 0.7rem;
    }
    
    .hero-image-card-right .text-sm,
    .hero-image-card-left .text-sm {
        font-size: 0.65rem;
    }
    
    .hero-image-card-right .text-xs,
    .hero-image-card-left .text-xs {
        font-size: 0.55rem;
    }
}

        /* Custom styling for new sections */
        .section-separator {
            width: 100px;
            height: 5px;
            background: linear-gradient(90deg, var(--color-earth-green), var(--color-ocean-blue), var(--color-digital-gold));
            border-radius: 5px;
            margin: 0 auto 2.5rem auto;
        }

        /* Modal Animations */
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        #modalContent {
            animation: modalFadeIn 0.3s ease-out;
        }

        /* Modern CSS via Vite (no fallbacks needed) */
        body {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }
        /* Direction handling */
        [dir="rtl"] {
            direction: rtl;
            unicode-bidi: embed;
        }

        [dir="ltr"] {
            direction: ltr;
            unicode-bidi: embed;
        }
    </style>
</head>
<body class="font-poppins text-gentle-black leading-relaxed bg-light-gray">

@php
    $locales = [
        'fa' => ['label' => 'فارسی', 'abbr' => 'FA'],
        'en' => ['label' => 'English', 'abbr' => 'EN'],
        'ar' => ['label' => 'العربية', 'abbr' => 'AR'],
    ];
    $tagline = __('langWelcome.tagline');
    $taglineParts = preg_split('/[؛;]+/', $tagline);
@endphp

@if(session('success'))
    <div id="successMessage" class="fixed top-4 left-1/2 transform -translate-x-1/2 z-[9999] max-w-md w-full mx-4">
        <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg shadow-lg p-4 flex items-start gap-3 animate-fade-in">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-xl"></i>
            </div>
            <div class="flex-1">
                <p class="text-green-800 dark:text-green-200 font-vazirmatn text-sm md:text-base leading-relaxed">
                    {{ session('success') }}
                </p>
            </div>
            <button onclick="document.getElementById('successMessage').remove()" class="flex-shrink-0 text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-200 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <script>
        // Auto-hide after 5 seconds
        setTimeout(function() {
            const msg = document.getElementById('successMessage');
            if (msg) {
                msg.style.transition = 'opacity 0.5s ease-out, transform 0.5s ease-out';
                msg.style.opacity = '0';
                msg.style.transform = 'translate(-50%, -20px)';
                setTimeout(() => msg.remove(), 500);
            }
        }, 5000);
    </script>
@endif

@if(false)
<header class="bg-pure-white shadow-md py-4 px-6 2xl:px-8 sticky top-0 z-50">
    <div class="container mx-auto flex justify-between items-center">
        <div class="flex items-center space-x-3 2xl:space-x-reverse 2xl:space-x-3 rtl:space-x-reverse rtl:space-x-3">
            <a href="{{ route('welcome') }}" class="flex items-center space-x-3 2xl:space-x-reverse 2xl:space-x-3 rtl:space-x-reverse rtl:space-x-3 hover:opacity-80 transition-opacity">
                <svg width="45" height="45" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="animate-bounce-custom brand-logo-animated">
                    <path d="M12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2Z" fill="#10b981" opacity="0.8"/>
                    <path d="M12 2C10.5 4 8 6 8 9C8 12 12 14 12 14C12 14 16 12 16 9C16 6 13.5 4 12 2ZM12 14C12 14 10 16 10 18C10 20 12 22 12 22" fill="#047857"/>
                </svg>
                <span class="text-2xl 2xl:text-3xl font-extrabold text-gentle-black font-vazirmatn">EarthCoop</span>
                <span class="text-sm text-gray-500 hidden 2xl:flex flex-col border-r-2 border-gray-200 pr-4 mr-4 font-vazirmatn leading-tight">
                    <span>{{ $taglineParts[0] ?? $tagline }}</span>
                    <span>{{ $taglineParts[1] ?? '' }}</span>
                </span>
            </a>
        </div>

        <nav class="hidden 2xl:flex items-center gap-4 2xl:gap-5 font-vazirmatn text-gentle-black">
            <a href="{{ route('blog.index') }}" class="relative hover:text-earth-green transition duration-300 font-medium pb-1 group flex items-center gap-2">
                <i class="fas fa-blog text-earth-green"></i>
                <span>{{ __('navigation.blog') }}</span>
                <span class="absolute bottom-0 right-0 w-0 h-0.5 bg-earth-green group-hover:w-full transition-all duration-300"></span>
            </a>
            <a href="#about" class="relative hover:text-earth-green transition duration-300 font-medium pb-1 group flex items-center gap-2">
                <i class="fas fa-info-circle text-earth-green"></i>
                <span>{{ __('langWelcome.nav_about') }}</span>
                <span class="absolute bottom-0 right-0 w-0 h-0.5 bg-earth-green group-hover:w-full transition-all duration-300"></span>
            </a>
            <a href="#how-it-works" class="relative hover:text-earth-green transition duration-300 font-medium pb-1 group flex items-center gap-2">
                <i class="fas fa-question-circle text-earth-green"></i>
                <span>{{ __('langWelcome.nav_guide') }}</span>
                <span class="absolute bottom-0 right-0 w-0 h-0.5 bg-earth-green group-hover:w-full transition-all duration-300"></span>
            </a>
            <a href="#projects" class="relative hover:text-earth-green transition duration-300 font-medium pb-1 group flex items-center gap-2">
                <i class="fas fa-seedling text-earth-green"></i>
                <span>{{ __('langWelcome.nav_projects') }}</span>
                <span class="absolute bottom-0 right-0 w-0 h-0.5 bg-earth-green group-hover:w-full transition-all duration-300"></span>
            </a>
            <a href="#testimonials" class="relative hover:text-earth-green transition duration-300 font-medium pb-1 group flex items-center gap-2">
                <i class="fas fa-users text-earth-green"></i>
                <span>{{ __('langWelcome.nav_stories') }}</span>
                <span class="absolute bottom-0 right-0 w-0 h-0.5 bg-earth-green group-hover:w-full transition-all duration-300"></span>
            </a>
        </nav>

    <div class="hidden 2xl:flex items-center space-x-4 rtl:space-x-reverse rtl:space-x-4">
            <!-- Theme Toggle Button -->
            <div class="theme-toggle" onclick="toggleTheme()" title="{{ __('langWelcome.theme_toggle_title') }}" style="margin: 0 0.5rem;">
                <span class="theme-toggle-icon sun">☀️</span>
                <span class="theme-toggle-icon moon">🌙</span>
                <div class="theme-toggle-slider"></div>
            </div>
            <div class="relative">
                <button id="locale-toggle-button" type="button" class="flex items-center bg-light-gray rounded-full px-3 py-1 shadow-sm border border-gray-200 gap-2 transition hover:bg-white">
                    <span class="text-xs font-semibold tracking-wider">{{ $locales[app()->getLocale()]['abbr'] ?? strtoupper(app()->getLocale()) }}</span>
                    <svg class="w-3 h-3 text-gentle-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="locale-dropdown" class="absolute mt-1 w-32 bg-white border border-gray-200 rounded-lg shadow-lg py-2 hidden">
                    @foreach($locales as $code => $meta)
                        @if(app()->getLocale() !== $code)
                            <a href="{{ route('locale.change', $code) }}" class="flex items-center px-3 py-2 text-xs font-vazirmatn text-gentle-black hover:bg-light-gray transition">
                                <span class="font-semibold tracking-wider">{{ $meta['abbr'] }}</span>
                                <span class="ltr:ml-2 rtl:mr-2 text-[11px] text-gray-500">{{ $meta['label'] }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
            <button onclick="openModal()" class="bg-earth-green text-pure-white px-6 py-2 rounded-full shadow-md hover:bg-dark-green transition duration-300 font-vazirmatn font-medium transform hover:scale-105 cursor-pointer">{{ __('langWelcome.btn_join') }}</button>
            <a href="{{ route('login') }}" class="bg-ocean-blue text-pure-white px-6 py-2 rounded-full shadow-md hover:bg-dark-blue transition duration-300 font-vazirmatn font-medium transform hover:scale-105">{{ __('langWelcome.btn_login') }}</a>
            <a href="{{ route('invite') }}" class="bg-digital-gold text-pure-white px-6 py-2 rounded-full shadow-md hover:bg-opacity-90 transition duration-300 font-vazirmatn font-medium transform hover:scale-105">{{ __('langWelcome.btn_invite') }}</a>
        </div>

        <div class="2xl:hidden flex items-center">
            <button id="mobile-menu-button" class="hamburger-menu" type="button" aria-label="{{ __('navigation.open_menu') }}" aria-controls="mobile-menu" aria-expanded="false">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </div>
    <div id="mobile-menu" class="mobile-nav-menu hidden 2xl:hidden" aria-hidden="true">
        <nav class="flex flex-col items-center space-y-4 font-vazirmatn text-gentle-black py-4">
            <a href="{{ route('blog.index') }}" class="flex items-center gap-2 hover:text-earth-green transition duration-300 font-medium">
                <i class="fas fa-blog text-earth-green"></i>
                <span>{{ __('navigation.blog') }}</span>
            </a>
            <a href="#about" class="flex items-center gap-2 hover:text-earth-green transition duration-300 font-medium">
                <i class="fas fa-info-circle text-earth-green"></i>
                <span>{{ __('langWelcome.nav_about') }}</span>
            </a>
            <a href="#how-it-works" class="flex items-center gap-2 hover:text-earth-green transition duration-300 font-medium">
                <i class="fas fa-question-circle text-earth-green"></i>
                <span>{{ __('langWelcome.nav_guide') }}</span>
            </a>
            <a href="#projects" class="flex items-center gap-2 hover:text-earth-green transition duration-300 font-medium">
                <i class="fas fa-seedling text-earth-green"></i>
                <span>{{ __('langWelcome.nav_projects') }}</span>
            </a>
            <a href="#testimonials" class="flex items-center gap-2 hover:text-earth-green transition duration-300 font-medium">
                <i class="fas fa-users text-earth-green"></i>
                <span>{{ __('langWelcome.nav_stories') }}</span>
            </a>
            <hr class="w-full border-t border-light-gray my-2">
            <!-- Theme Toggle Button for Mobile -->
            <div class="theme-toggle" onclick="toggleTheme()" title="{{ __('langWelcome.theme_toggle_title') }}">
                <span class="theme-toggle-icon sun">☀️</span>
                <span class="theme-toggle-icon moon">🌙</span>
                <div class="theme-toggle-slider"></div>
            </div>
            <button onclick="openModal()" class="bg-earth-green text-pure-white px-5 py-2 rounded-full shadow-md w-3/4 text-center hover:bg-dark-green transition duration-300 font-vazirmatn font-medium cursor-pointer">{{ __('langWelcome.btn_join') }}</button>
            <a href="{{ route('invite') }}" class="bg-digital-gold text-pure-white px-5 py-2 rounded-full shadow-md w-3/4 text-center hover:bg-opacity-90 transition duration-300 font-vazirmatn font-medium">{{ __('langWelcome.btn_invite') }}</a>
            <a href="{{ route('login') }}" class="bg-ocean-blue text-pure-white px-5 py-2 rounded-full shadow-md w-3/4 text-center hover:bg-dark-blue transition duration-300 font-vazirmatn font-medium">{{ __('langWelcome.btn_login') }}</a>
            <div class="flex items-center justify-center space-x-2 rtl:space-x-reverse rtl:space-x-2">
                @foreach($locales as $code => $meta)
                    <a href="{{ route('locale.change', $code) }}" class="flex items-center text-sm font-vazirmatn px-3 py-1 rounded-full transition {{ app()->getLocale() === $code ? 'bg-earth-green text-white' : 'bg-light-gray text-gentle-black hover:bg-white' }}">
                        <span class="font-semibold tracking-wider">{{ $meta['abbr'] }}</span>
                        <span class="ltr:ml-1 rtl:mr-1">{{ $meta['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </nav>
    </div>
</header>
@endif

@include('components.header-unified', ['headerContext' => 'welcome'])

<main>
    @include('partials.hero-section')
    @include('partials.mission-section')
    @include('partials.features-section')
    @include('partials.governance-section')
    @include('partials.network-section')
    @include('partials.how-it-works-section')
    @include('partials.docs-section')
    @include('partials.bahar-economy-section')
    @include('partials.projects-section')
    @include('partials.invite-section')
    @include('partials.testimonials-section')
    @include('partials.cta-section')
</main>

@include('components.footer-unified', ['footerContext' => 'welcome'])

<style>
    #welcome-scroll-assistant {
        position: fixed; left: 1.5rem; bottom: 1.5rem; z-index: 900;
        width: 3.5rem; height: 3.5rem; border: 0; border-radius: 999px;
        display: flex; align-items: center; justify-content: center;
        color: #fff; background: linear-gradient(135deg, var(--color-earth-green), var(--color-ocean-blue));
        box-shadow: 0 14px 35px rgba(16, 185, 129, .35);
        opacity: .28; transform: translateY(0) scale(.94);
        transition: opacity .4s ease, transform .4s ease, box-shadow .4s ease;
        cursor: pointer;
    }
    #welcome-scroll-assistant.is-visible {
        opacity: 1; transform: translateY(0) scale(1);
        box-shadow: 0 18px 42px rgba(16, 185, 129, .48);
    }
    #welcome-scroll-assistant:hover,
    #welcome-scroll-assistant:focus-visible { opacity: 1; transform: scale(1.08); outline: none; }
    @media (max-width: 640px) {
        #welcome-scroll-assistant { left: 1rem; bottom: 1rem; width: 3.25rem; height: 3.25rem; }
    }
</style>
<button id="welcome-scroll-assistant" type="button"
        aria-label="{{ app()->getLocale() === 'en' ? 'Scroll down' : 'حرکت به پایین صفحه' }}"
        title="{{ app()->getLocale() === 'en' ? 'Scroll down' : 'حرکت به پایین صفحه' }}">
    <i class="fas fa-arrow-down text-xl transition-transform duration-300" aria-hidden="true"></i>
</button>

<!-- Registration Modal - مودال ثبت‌نام -->
<div id="registrationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-[100] flex items-center justify-center p-4" style="backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); background-color: rgba(0, 0, 0, 0.5);">
    <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full transform transition-all duration-300 scale-95 opacity-0" id="modalContent">
        <!-- Modal Header -->
        <div class="relative bg-gradient-to-br from-earth-green to-ocean-blue text-white p-6 rounded-t-3xl">
            <button onclick="closeModal()" class="absolute top-4 left-4 text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
            <div class="text-center">
                <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg">
                    <i class="fas fa-user-plus text-earth-green text-3xl"></i>
                </div>
                <h2 class="text-2xl font-bold font-vazirmatn">خوش آمدید به EarthCoop</h2>
                <p class="text-sm mt-2 opacity-90 font-vazirmatn">برای شروع عضویت، لطفاً اطلاعات زیر را تکمیل کنید</p>
            </div>
        </div>

        <!-- Modal Body -->
        <form id="registrationForm" action="{{ route('register.accept') }}" method="POST" class="p-6">
            @csrf
            
            @php
                $setting = \App\Models\Setting::find(1);
            @endphp
            
            @if($setting->invation_status == 1)
                <!-- Invitation Code Section -->
                <div class="mb-6">
                    <div class="bg-blue-50 border-r-4 border-ocean-blue p-4 rounded-lg mb-4">
                        <p class="text-sm text-gray-700 font-vazirmatn">
                            <i class="fas fa-info-circle text-ocean-blue ml-2"></i>
                            در حال حاضر ثبت‌نام فقط با کد دعوت امکان‌پذیر است
                        </p>
                    </div>
                    
                    <label for="invite_code" class="block text-sm font-semibold text-gray-700 mb-2 font-vazirmatn">
                        <i class="fas fa-ticket-alt ml-2 text-digital-gold"></i>
                        کد دعوت:
                    </label>
                    <input 
                        type="text" 
                        name="invite_code" 
                        id="invite_code" 
                        class="w-full px-4 py-3 border-2 @error('invite_code') border-red-500 @else border-gray-300 @enderror rounded-xl focus:border-earth-green focus:ring-2 focus:ring-earth-green focus:outline-none transition font-vazirmatn text-center tracking-widest text-lg"
                        placeholder="مثال: ABC123"
                        value="{{ old('invite_code', isset($_GET['code']) ? $_GET['code'] : '') }}"
                        required
                    >
                    @error('invite_code')
                        <div class="text-red-500 text-sm mt-2 font-vazirmatn bg-red-50 p-2 rounded-lg">
                            <i class="fas fa-exclamation-circle ml-1"></i>
                            {{ $message }}
                        </div>
                    @enderror
                    
                    <div class="mt-3 text-center">
                        <p class="text-sm text-gray-600 font-vazirmatn">
                            کد دعوت ندارید؟ 
                            <a href="{{ route('invite') }}" class="text-digital-gold hover:text-orange-600 font-semibold transition">
                                یکی درخواست کنید
                                <i class="fas fa-arrow-left mr-1"></i>
                            </a>
                        </p>
                    </div>
                </div>
            @endif

            <!-- Terms Agreement -->
            <div class="mb-6">
                <div class="bg-gradient-to-r from-purple-50 to-pink-50 border-2 border-purple-200 rounded-xl p-4">
                    <label class="flex items-start cursor-pointer">
                        <input 
                            type="checkbox" 
                            id="agreement"
                            name="terms"
                            value="1"
                            class="mt-1 w-5 h-5 text-earth-green border-gray-300 rounded focus:ring-earth-green focus:ring-2"
                            required
                        >
                        <span class="mr-3 text-sm text-gray-700 font-vazirmatn leading-relaxed">
                            با 
                            <a href="javascript:void(0)" onclick="openTerms()" class="text-ocean-blue hover:text-blue-700 font-semibold underline">
                                اساسنامه و شرایط استفاده
                            </a> 
                            موافقم
                        </span>
                    </label>
                    @error('terms')
                        <div class="text-red-500 text-sm mt-2 font-vazirmatn bg-red-50 p-2 rounded-lg">
                            <i class="fas fa-exclamation-triangle ml-1"></i>
                            {{ $message }}
                        </div>
                    @else
                        <div id="agreementError" class="text-red-500 text-sm mt-2 font-vazirmatn hidden">
                            <i class="fas fa-exclamation-triangle ml-1"></i>
                            برای ادامه باید شرایط و اساسنامه را بپذیرید
                        </div>
                    @enderror
                </div>
            </div>

            <!-- Submit Button -->
            <button 
                type="submit" 
                class="w-full py-4 rounded-xl font-bold text-lg shadow-lg hover:shadow-xl transform hover:scale-105 transition duration-300 font-vazirmatn text-white"
                style="background: linear-gradient(to right, #10b981, #059669);"
            >
                <i class="fas fa-arrow-left ml-2"></i>
                شروع ثبت‌نام
            </button>

            <!-- Login Link -->
            <div class="mt-4 text-center">
                <p class="text-sm text-gray-600 font-vazirmatn">
                    قبلاً ثبت‌نام کرده‌اید؟
                    <a href="{{ route('login') }}" class="text-ocean-blue hover:text-blue-700 font-semibold transition">
                        ورود به سامانه
                        <i class="fas fa-sign-in-alt mr-1"></i>
                    </a>
                </p>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const scrollAssistant = document.getElementById('welcome-scroll-assistant');
        if (!scrollAssistant) return;

        const icon = scrollAssistant.querySelector('i');
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let hideTimer;

        const labels = {
            down: @json(app()->getLocale() === 'en' ? 'Scroll down' : 'حرکت به پایین صفحه'),
            up: @json(app()->getLocale() === 'en' ? 'Back to top' : 'بازگشت به بالای صفحه')
        };

        function isNearTop() {
            return window.scrollY < Math.max(180, window.innerHeight * 0.35);
        }

        function syncScrollAssistant() {
            const goDown = isNearTop();
            icon.classList.toggle('fa-arrow-down', goDown);
            icon.classList.toggle('fa-arrow-up', !goDown);
            scrollAssistant.setAttribute('aria-label', goDown ? labels.down : labels.up);
            scrollAssistant.setAttribute('title', goDown ? labels.down : labels.up);
        }

        function revealScrollAssistant() {
            syncScrollAssistant();
            scrollAssistant.classList.add('is-visible');
            clearTimeout(hideTimer);
            hideTimer = setTimeout(function () {
                scrollAssistant.classList.remove('is-visible');
            }, 2600);
        }

        scrollAssistant.addEventListener('click', function () {
            const targetTop = isNearTop()
                ? Math.min(document.documentElement.scrollHeight - window.innerHeight, window.scrollY + window.innerHeight * 0.85)
                : 0;
            window.scrollTo({ top: targetTop, behavior: reduceMotion ? 'auto' : 'smooth' });
            revealScrollAssistant();
        });

        window.addEventListener('scroll', revealScrollAssistant, { passive: true });
        window.addEventListener('mousemove', revealScrollAssistant, { passive: true });
        window.addEventListener('touchstart', revealScrollAssistant, { passive: true });
        revealScrollAssistant();
    });

    // Modal Functions
    function openModal() {
        const modal = document.getElementById('registrationModal');
        const modalContent = document.getElementById('modalContent');
        modal.classList.remove('hidden');
        setTimeout(() => {
            modalContent.classList.remove('scale-95', 'opacity-0');
            modalContent.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    function closeModal() {
        const modal = document.getElementById('registrationModal');
        const modalContent = document.getElementById('modalContent');
        modalContent.classList.remove('scale-100', 'opacity-100');
        modalContent.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    // Close modal on outside click
    document.getElementById('registrationModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // Close modal on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

    // Terms acceptance handling
    function openTerms() {
        var termsWindow = window.open("{{ route('terms') }}", "_blank");
        var checkInterval = setInterval(function() {
            if (termsWindow.closed) {
                if (localStorage.getItem('termsAccepted') === 'true') {
                    document.getElementById('agreement').checked = true;
                }
                clearInterval(checkInterval);
            }
        }, 500);
    }

    // Smooth scroll animation logic
    document.addEventListener('DOMContentLoaded', () => {
        const sections = document.querySelectorAll('.fade-in-section');

        // بررسی وجود IntersectionObserver
        if (!window.IntersectionObserver) {
            // Fallback برای مرورگرهای قدیمی
            sections.forEach(section => {
                if (section) {
                    section.classList.add('is-visible');
                }
            });
            return;
        }

        // بررسی اینکه sections یک NodeList معتبر است
        if (!sections || sections.length === 0) {
            return;
        }

        const observerOptions = {
            root: null,
            rootMargin: '0px',
            threshold: 0.1
        };

        const observer = new IntersectionObserver((entries, observer) => {
            // بررسی اینکه entries یک آرایه است
            if (!entries || !Array.isArray(entries) && typeof entries.forEach !== 'function') {
                return;
            }
            
            entries.forEach(entry => {
                if (entry && entry.isIntersecting && entry.target) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        // بررسی هر section قبل از observe
        sections.forEach(section => {
            if (section && section.nodeType === 1) {
                try {
                    observer.observe(section);
                } catch (e) {
                    // اگر observe خطا داد، فقط class را اضافه کن
                    section.classList.add('is-visible');
                }
            }
        });

        // Mobile menu toggle
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');

        if (mobileMenuButton && mobileMenu) {
            mobileMenuButton.addEventListener('click', () => {
                const isOpen = mobileMenu.classList.toggle('hidden') === false;
                mobileMenuButton.classList.toggle('open', isOpen);
                mobileMenuButton.setAttribute('aria-expanded', String(isOpen));
                mobileMenu.setAttribute('aria-hidden', String(!isOpen));
            });
            mobileMenu.querySelectorAll('a').forEach((link) => {
                link.addEventListener('click', () => {
                    mobileMenu.classList.add('hidden');
                    mobileMenuButton.classList.remove('open');
                    mobileMenuButton.setAttribute('aria-expanded', 'false');
                    mobileMenu.setAttribute('aria-hidden', 'true');
                });
            });

            window.addEventListener('resize', () => {
                if (window.innerWidth >= 768) {
                    mobileMenu.classList.add('hidden');
                    mobileMenuButton.classList.remove('open');
                    mobileMenuButton.setAttribute('aria-expanded', 'false');
                    mobileMenu.setAttribute('aria-hidden', 'true');
                }
            });
        }

        // Locale dropdown toggle
        const localeToggleButton = document.getElementById('locale-toggle-button');
        const localeDropdown = document.getElementById('locale-dropdown');

        if (localeToggleButton && localeDropdown) {
            localeToggleButton.addEventListener('click', (event) => {
                event.stopPropagation();
                localeDropdown.classList.toggle('hidden');
            });

            document.addEventListener('click', (event) => {
                const clickedInsideDropdown = localeDropdown.contains(event.target);
                const clickedToggle = localeToggleButton.contains(event.target);

                if (!clickedInsideDropdown && !clickedToggle) {
                    localeDropdown.classList.add('hidden');
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    localeDropdown.classList.add('hidden');
                }
            });
        }

        // Auto-open modal if validation errors exist
        @if($errors->any())
            openModal();
        @endif

        // Form validation
        const registrationForm = document.getElementById('registrationForm');
        if (registrationForm) {
            // Check if terms were previously accepted
            if (localStorage.getItem('termsAccepted') === 'true') {
                document.getElementById('agreement').checked = true;
            }

            // Listen for storage changes
            window.addEventListener("storage", function(event) {
                if (event.key === "termsAccepted" && event.newValue === 'true') {
                    document.getElementById('agreement').checked = true;
                }
            });

            // Form submit validation
            registrationForm.addEventListener('submit', function(event) {
                const agreementCheckbox = document.getElementById('agreement');
                const errorDiv = document.getElementById('agreementError');
                
                if (!agreementCheckbox.checked) {
                    event.preventDefault();
                    errorDiv.classList.remove('hidden');
                    agreementCheckbox.parentElement.parentElement.classList.add('ring-2', 'ring-red-500');
                    setTimeout(() => {
                        agreementCheckbox.parentElement.parentElement.classList.remove('ring-2', 'ring-red-500');
                    }, 2000);
                } else {
                    errorDiv.classList.add('hidden');
                }
            });
        }
    });
</script>

    @include('components.pwa-install-prompt')
</body>
</html>
