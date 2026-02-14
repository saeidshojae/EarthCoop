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
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Fonts with preconnect for better performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;800;900&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
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

        /* Coin flip animation */
        @keyframes flip-coin {
            0% { transform: rotateY(0deg); }
            100% { transform: rotateY(360deg); }
        }

        .animate-flip-coin {
            animation: flip-coin 4s infinite ease-in-out;
        }

        .coin-flip-wrapper {
            position: relative;
            transform-style: preserve-3d;
        }

        .coin-face {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background-size: cover;
            background-position: center;
            backface-visibility: hidden;
            box-shadow: 0 15px 40px rgba(245, 158, 11, 0.4), inset 0 0 15px rgba(255, 255, 255, 0.5);
            border: 4px solid var(--color-digital-gold);
        }

        .coin-back {
            transform: rotateY(180deg);
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

        /* Globe and its elements */
        .globe-container {
            position: relative;
            width: 100%;
            height: 200px;
            display: flex;
            justify-content: center;
            align-items: center;
            perspective: 1000px;
        }

        .globe {
            width: 150px;
            height: 150px;
            background: linear-gradient(135deg, #10b981 0%, #3b82f6 50%, #059669 100%);
            border-radius: 50%;
            box-shadow: 0 0 60px rgba(59, 130, 246, 0.4), inset 0 0 20px rgba(255, 255, 255, 0.3);
            position: relative;
            animation: rotate 60s linear infinite;
            transform-style: preserve-3d;
        }

        /* Fallback for browsers that don't support preserve-3d */
        @supports not (transform-style: preserve-3d) {
            .globe {
                transform-style: flat;
            }
            .globe-dots {
                transform-style: flat;
            }
        }

        .globe::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 30% 30%, transparent 50%, rgba(16, 185, 129, 0.15) 100%);
            border-radius: 50%;
            z-index: 1;
        }

        .globe-dots {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            transform-style: preserve-3d;
            z-index: 2;
        }

        .globe-dot {
            position: absolute;
            width: 10px;
            height: 10px;
            background-color: var(--color-earth-green);
            border-radius: 50%;
            transform: translate(-50%, -50%) translateZ(75px);
            box-shadow: 0 0 10px var(--color-earth-green), 0 0 15px rgba(255, 255, 255, 0.5);
            animation: pulse 1.5s infinite alternate ease-in-out;
        }

        .globe-dot:nth-child(1) { top: 20%; left: 30%; background-color: var(--color-earth-green); }
        .globe-dot:nth-child(2) { top: 40%; left: 70%; background-color: var(--color-ocean-blue); animation-delay: 0.3s; }
        .globe-dot:nth-child(3) { top: 60%; left: 20%; background-color: var(--color-digital-gold); animation-delay: 0.6s; }
        .globe-dot:nth-child(4) { top: 75%; left: 60%; background-color: var(--color-earth-green); animation-delay: 0.9s; }
        .globe-dot:nth-child(5) { top: 30%; left: 50%; background-color: var(--color-ocean-blue); animation-delay: 1.2s; }
        .globe-dot:nth-child(6) { top: 55%; left: 90%; background-color: var(--color-digital-gold); animation-delay: 1.5s; }
        .globe-dot:nth-child(7) { top: 10%; left: 60%; background-color: var(--color-earth-green); animation-delay: 1.8s; }

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
        .hero-image-card-right {
            position: absolute;
            bottom: -8px;
            left: -8px;
            background-color: white;
            padding: 1.25rem;
            border-radius: 1.5rem;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            transform: rotate(-6deg);
            transition: transform 0.5s;
            -webkit-transform: rotate(-6deg);
            -ms-transform: rotate(-6deg);
        }

        .hero-image-card-right:hover {
            transform: rotate(0deg);
            -webkit-transform: rotate(0deg);
            -ms-transform: rotate(0deg);
        }

        [dir="rtl"] .hero-image-card-right {
            left: auto;
            right: -8px;
        }

        .hero-image-card-left {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: white;
            padding: 1.25rem;
            border-radius: 1.5rem;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            transform: rotate(6deg);
            transition: transform 0.5s;
            -webkit-transform: rotate(6deg);
            -ms-transform: rotate(6deg);
        }

        .hero-image-card-left:hover {
            transform: rotate(0deg);
            -webkit-transform: rotate(0deg);
            -ms-transform: rotate(0deg);
        }

        [dir="rtl"] .hero-image-card-left {
            right: auto;
            left: -8px;
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

        /* فوری: تنظیم اندازه پایه برای Edge */
        .edge-browser body,
        .tailwind-fallback body {
            font-size: 16px !important;
            line-height: 1.5 !important;
        }

        .edge-browser .container,
        .tailwind-fallback .container {
            max-width: 1280px !important;
            margin-left: auto !important;
            margin-right: auto !important;
            padding-left: 1rem !important;
            padding-right: 1rem !important;
        }

        /* تنظیم اندازه فونت‌ها برای Edge */
        .edge-browser h1,
        .tailwind-fallback h1 {
            font-size: 2.25rem !important;
            line-height: 1.2 !important;
        }

        @media (min-width: 768px) {
            .edge-browser h1,
            .tailwind-fallback h1 {
                font-size: 3.75rem !important;
            }
        }

        @media (min-width: 1024px) {
            .edge-browser h1,
            .tailwind-fallback h1 {
                font-size: 4.5rem !important;
            }
        }

        .edge-browser p,
        .tailwind-fallback p {
            font-size: 1rem !important;
            line-height: 1.5 !important;
        }

        /* جلوگیری از بزرگنمایی در Edge */
        .edge-browser img,
        .tailwind-fallback img {
            max-width: 100% !important;
            height: auto !important;
        }

        /* Fix for RTL direction in Edge */
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

<header class="bg-pure-white shadow-md py-4 px-6 md:px-8 sticky top-0 z-50">
    <div class="container mx-auto flex justify-between items-center">
        <div class="flex items-center space-x-3 md:space-x-reverse md:space-x-3 rtl:space-x-reverse rtl:space-x-3">
            <a href="{{ route('welcome') }}" class="flex items-center space-x-3 md:space-x-reverse md:space-x-3 rtl:space-x-reverse rtl:space-x-3 hover:opacity-80 transition-opacity">
                <svg width="45" height="45" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="animate-bounce-custom">
                    <path d="M12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2Z" fill="#10b981" opacity="0.8"/>
                    <path d="M12 2C10.5 4 8 6 8 9C8 12 12 14 12 14C12 14 16 12 16 9C16 6 13.5 4 12 2ZM12 14C12 14 10 16 10 18C10 20 12 22 12 22" fill="#047857"/>
                </svg>
                <span class="text-2xl md:text-3xl font-extrabold text-gentle-black font-vazirmatn">EarthCoop</span>
                <span class="text-sm text-gray-500 hidden md:flex flex-col border-r-2 border-gray-200 pr-4 mr-4 font-vazirmatn leading-tight">
                    <span>{{ $taglineParts[0] ?? $tagline }}</span>
                    <span>{{ $taglineParts[1] ?? '' }}</span>
                </span>
            </a>
        </div>

        <nav class="hidden md:flex items-center gap-6 font-vazirmatn text-gentle-black">
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

    <div class="hidden md:flex items-center space-x-4 rtl:space-x-reverse rtl:space-x-4">
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

        <div class="md:hidden flex items-center">
            <button id="mobile-menu-button" class="hamburger-menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </div>
    <div id="mobile-menu" class="mobile-nav-menu hidden md:hidden">
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

<main>
    @include('partials.hero-section')
    @include('partials.mission-section')
    @include('partials.features-section')
    @include('partials.governance-section')
    @include('partials.network-section')
    @include('partials.how-it-works-section')
    @include('partials.bahar-economy-section')
    @include('partials.projects-section')
    @include('partials.invite-section')
    @include('partials.testimonials-section')
    @include('partials.cta-section')
</main>

@include('partials.footer')

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
                mobileMenu.classList.toggle('hidden');
                mobileMenuButton.classList.toggle('open');
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

        // بررسی و اصلاح اندازه‌ها برای Edge
        (function() {
            var isEdge = /Edg/.test(navigator.userAgent) || /Edge/.test(navigator.userAgent);
            
            if (isEdge) {
                // بررسی اینکه آیا Tailwind به درستی کار می‌کند
                setTimeout(function() {
                    var testElement = document.createElement('div');
                    testElement.className = 'hidden';
                    testElement.style.display = 'none';
                    document.body.appendChild(testElement);
                    
                    var computedStyle = window.getComputedStyle(testElement);
                    var tailwindWorks = computedStyle.display === 'none';
                    
                    document.body.removeChild(testElement);
                    
                    if (!tailwindWorks || document.documentElement.classList.contains('tailwind-fallback')) {
                        // اعمال فوری استایل‌های fallback
                        var style = document.createElement('style');
                        style.id = 'edge-emergency-styles';
                        style.textContent = `
                            body { font-size: 16px !important; }
                            .container { max-width: 1280px !important; margin: 0 auto !important; padding: 0 1rem !important; }
                            h1 { font-size: 2.25rem !important; }
                            @media (min-width: 768px) { h1 { font-size: 3.75rem !important; } }
                            @media (min-width: 1024px) { h1 { font-size: 4.5rem !important; } }
                            img { max-width: 100% !important; height: auto !important; }
                        `;
                        document.head.appendChild(style);
                        // فقط در development لاگ کن
                        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                            console.log('Edge: Emergency styles applied');
                        }
                    }
                }, 500);
            }
        })();

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
</body>
</html>

