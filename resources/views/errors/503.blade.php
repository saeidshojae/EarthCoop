<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>زمان اجرا به پایان رسید - EarthCoop</title>
    <!-- Tailwind CSS -->
    <!-- Vite CSS & JS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <!-- FontAwesome -->
    <link rel="stylesheet" href="{{ asset("vendor/fontawesome/css/all.min.css") }}">
    <!-- Fonts -->
    <link rel="stylesheet" href="{{ asset('Css/fonts-local.css') }}">
    <style>
        * {
            font-family: 'Vazirmatn', sans-serif;
        }
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8f5e9 100%);
            min-height: 100vh;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        @keyframes bounce-custom {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }
        .logo-animated {
            animation: bounce-custom 3s infinite ease-in-out;
        }
        .pulse-animation {
            animation: pulse 2s infinite ease-in-out;
        }
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }
    </style>
</head>
<body class="font-vazirmatn leading-relaxed min-h-screen flex flex-col">
    <!-- Header -->
    <header class="bg-white shadow-md py-4 px-6 sticky top-0 z-50">
        <div class="container mx-auto flex items-center justify-between">
            <a href="{{ route('home') }}" class="flex items-center space-x-3 hover:opacity-80 transition-opacity">
                <svg width="45" height="45" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="logo-animated">
                    <path d="M12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2Z" fill="#10b981" opacity="0.8"/>
                    <path d="M12 2C10.5 4 8 6 8 9C8 12 12 14 12 14C12 14 16 12 16 9C16 6 13.5 4 12 2ZM12 14C12 14 10 16 10 18C10 20 12 22 12 22" fill="#047857"/>
                </svg>
                <span class="text-2xl font-extrabold" style="color: #1e293b;">EarthCoop</span>
            </a>
        </div>
    </header>
    <!-- Main Content -->
    <main class="flex-grow flex items-center justify-center py-12 px-4">
        <div class="container mx-auto max-w-2xl">
            <div class="bg-white rounded-2xl shadow-2xl p-8 md:p-12 text-center fade-in">
                <!-- Icon -->
                <div class="mb-6 flex justify-center">
                    <div class="w-24 h-24 rounded-full flex items-center justify-center pulse-animation" style="background-color: #fef3c7;">
                        <i class="fas fa-clock text-5xl" style="color: #f59e0b;"></i>
                    </div>
                </div>
                <!-- Title -->
                <h1 class="text-3xl md:text-4xl font-extrabold mb-4" style="color: #1e293b;">
                    زمان اجرا به پایان رسید
                </h1>
                <!-- Description -->
                <p class="text-lg md:text-xl text-gray-600 mb-8 leading-relaxed">
                    متأسفانه درخواست شما بیش از حد انتظار طول کشید و زمان اجرا به پایان رسید. این مشکل معمولاً موقتی است و با انجام اقدامات زیر می‌توانید مشکل را برطرف کنید.
                </p>
                <!-- Solutions -->
                <div class="bg-gray-50 rounded-xl p-6 mb-8 text-right">
                    <h2 class="text-xl font-bold mb-4" style="color: #1e293b;">
                        <i class="fas fa-lightbulb ml-2" style="color: #10b981;"></i>
                        راه‌حل‌های پیشنهادی:
                    </h2>
                    <ul class="space-y-3 text-gray-700">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle mt-1 ml-3" style="color: #10b981;"></i>
                            <span>صفحه را <strong>رفرش</strong> کنید (F5 یا Ctrl+R)</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle mt-1 ml-3" style="color: #10b981;"></i>
                            <span>چند لحظه <strong>صبر</strong> کنید و دوباره تلاش کنید</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle mt-1 ml-3" style="color: #10b981;"></i>
                            <span>اگر مشکل ادامه داشت، به <strong>صفحه اصلی</strong> برگردید</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle mt-1 ml-3" style="color: #10b981;"></i>
                            <span>در صورت نیاز با <strong>پشتیبانی</strong> تماس بگیرید</span>
                        </li>
                    </ul>
                </div>
                <!-- Actions -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('home') }}"
                       class="px-6 py-3 rounded-full text-white font-bold shadow-lg hover:shadow-xl transition duration-300 transform hover:scale-105"
                       style="background-color: #10b981;">
                        <i class="fas fa-home ml-2"></i>
                        بازگشت به صفحه اصلی
                    </a>
                    <button onclick="window.location.reload()"
                            class="px-6 py-3 rounded-full text-white font-bold shadow-lg hover:shadow-xl transition duration-300 transform hover:scale-105"
                            style="background-color: #3b82f6;">
                        <i class="fas fa-redo ml-2"></i>
                        تلاش مجدد
                    </button>
                    @auth
                    @php
                        $supportRoute = null;
                        if (Route::has('user.tickets.create')) {
                            $supportRoute = route('user.tickets.create');
                        } elseif (Route::has('support.kb.index')) {
                            $supportRoute = route('support.kb.index');
                        } else {
                            $supportRoute = route('home');
                        }
                    @endphp
                    <a href="{{ $supportRoute }}"
                       class="px-6 py-3 rounded-full border-2 font-bold shadow-lg hover:shadow-xl transition duration-300 transform hover:scale-105"
                       style="border-color: #f59e0b; color: #f59e0b;">
                        <i class="fas fa-headset ml-2"></i>
                        تماس با پشتیبانی
                    </a>
                    @endauth
                </div>
                <!-- Additional Info -->
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <p class="text-sm text-gray-500">
                        <i class="fas fa-info-circle ml-1"></i>
                        اگر این مشکل به طور مکرر رخ می‌دهد، لطفاً با تیم پشتیبانی تماس بگیرید.
                    </p>
                </div>
            </div>
        </div>
    </main>
    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 py-6 mt-auto">
        <div class="container mx-auto text-center text-gray-600">
            <p>&copy; {{ date('Y') }} EarthCoop. تمام حقوق محفوظ است.</p>
        </div>
    </footer>
</body>
</html>