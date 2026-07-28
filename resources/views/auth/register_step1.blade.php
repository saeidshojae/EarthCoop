<!DOCTYPE html>

<html lang="fa" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">

    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <meta name="format-detection" content="telephone=no">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>مرحله ۱ - اطلاعات هویتی</title>

    

    <!-- Tailwind CSS -->

    <!-- Vite CSS & JS -->

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    

    <!-- Fonts -->

    <link href="<link rel="stylesheet" href="{{ asset("Css/fonts-local.css") }}">" rel="stylesheet">

    

    <!-- Font Awesome -->

    <link rel="stylesheet" href="{{ asset("vendor/fontawesome/css/all.min.css") }}">

    

    <!-- Persian Datepicker -->

    <script src="{{ asset("vendor/jquery/jquery.min.js") }}"></script>

    <script src="{{ asset("vendor/persian-date/persian-date.min.js") }}"></script>

    <script src="https://unpkg.com/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>

    <link rel="stylesheet" href="https://unpkg.com/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">

    

    <style>

        :root {

            --color-earth-green: #10b981;

            --color-ocean-blue: #3b82f6;

            --color-digital-gold: #f59e0b;

            --color-pure-white: #ffffff;

            --color-gentle-black: #1e293b;

            --color-dark-green: #047857;

            --color-dark-blue: #1d4ed8;

        }

        

        * { font-family: 'Vazirmatn', 'Poppins', sans-serif; }

        

        body { background-color: #e2e8f0; }

        

        @keyframes bounce-custom {

            0%, 100% { transform: translateY(0); }

            50% { transform: translateY(-15px); }

        }

        

        .animate-bounce-custom { animation: bounce-custom 3s infinite ease-in-out; }

        

        .form-card-gradient {

            background: linear-gradient(145deg, var(--color-pure-white) 0%, #f0f4f7 100%);

            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.08);

            border-radius: 18px;

            position: relative;

            border: 1px solid rgba(220, 220, 220, 0.3);

        }

        

        /* Mobile Responsive Styles */

        @media (max-width: 640px) {

            body {

                padding: 0.25rem !important;

            }

            

            .form-card-gradient {

                padding: 1rem !important;

                border-radius: 12px;

                margin: 0.25rem;

            }

            

            .form-card-gradient::before {

                border-radius: 12px 12px 0 0;

            }

            

            /* Error Messages */

            .bg-red-50 {

                padding: 0.75rem !important;

                font-size: 0.8125rem !important;

            }

            

            .bg-red-50 h3 {

                font-size: 0.875rem !important;

                margin-bottom: 0.5rem !important;

            }

            

            .bg-red-50 ul li {

                font-size: 0.8125rem !important;

            }

            

            /* Modal */

            #confirmation-modal > div {

                padding: 1rem !important;

                margin: 0.25rem !important;

                max-height: 98vh !important;

                border-radius: 12px !important;

            }

            

            #confirmation-modal h3 {

                font-size: 1.125rem !important;

            }

            

            #confirmation-modal .grid {

                grid-template-columns: 1fr !important;

                gap: 0.75rem !important;

            }

            

            #confirmation-modal label {

                font-size: 0.75rem !important;

            }

            

            #confirmation-modal p {

                font-size: 0.875rem !important;

            }

        }

        

        .form-card-gradient::before {

            content: '';

            position: absolute;

            top: 0;

            left: 0;

            width: 100%;

            height: 6px;

            background: linear-gradient(90deg, var(--color-earth-green), var(--color-ocean-blue), var(--color-digital-gold));

        }

    </style>

</head>

<body class="font-vazirmatn leading-relaxed flex items-center justify-center min-h-screen p-2 sm:p-4">



    <!-- Main Form Content -->

    <div class="form-card-gradient w-full max-w-2xl mx-auto p-4 sm:p-6 md:p-8 lg:p-10">

        <!-- Logo -->

        <div class="flex items-center justify-center space-x-2 sm:space-x-3 rtl:space-x-reverse mb-4 sm:mb-6 md:mb-8">

            <svg width="40" height="40" class="sm:w-12 sm:h-12 md:w-14 md:h-14 lg:w-16 lg:h-16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="animate-bounce-custom">

                <path d="M12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2Z" fill="#10b981" opacity="0.8"/>

                <path d="M12 2C10.5 4 8 6 8 9C8 12 12 14 12 14C12 14 16 12 16 9C16 6 13.5 4 12 2ZM12 14C12 14 10 16 10 18C10 20 12 22 12 22" fill="#047857"/>

            </svg>

            <span class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-extrabold text-gentle-black" style="color: var(--color-gentle-black);">EarthCoop</span>

        </div>

        

        <!-- Step Indicator -->

        <div class="text-center mb-4 sm:mb-6 md:mb-8">

            <div class="flex items-center justify-center gap-2 sm:gap-3 md:gap-4 mb-2 sm:mb-4 step-indicator-container">

                <div class="flex items-center flex-col sm:flex-row">

                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center font-bold text-white text-sm sm:text-base" style="background-color: var(--color-earth-green);">۱</div>

                    <span class="mr-0 sm:mr-2 mt-1 sm:mt-0 text-xs sm:text-sm font-bold" style="color: var(--color-earth-green);">هویتی</span>

                </div>

                <div class="w-4 h-1 sm:w-6 md:w-8 bg-gray-300 hidden sm:block"></div>

                <div class="flex items-center flex-col sm:flex-row">

                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center font-bold text-gray-400 bg-gray-200 text-sm sm:text-base">۲</div>

                    <span class="mr-0 sm:mr-2 mt-1 sm:mt-0 text-xs sm:text-sm text-gray-400 hidden sm:inline">صنفی</span>

                </div>

                <div class="w-4 h-1 sm:w-6 md:w-8 bg-gray-300 hidden sm:block"></div>

                <div class="flex items-center flex-col sm:flex-row">

                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center font-bold text-gray-400 bg-gray-200 text-sm sm:text-base">۳</div>

                    <span class="mr-0 sm:mr-2 mt-1 sm:mt-0 text-xs sm:text-sm text-gray-400 hidden sm:inline">مکانی</span>

                </div>

            </div>

        </div>

        

        <!-- Form -->

        <div class="text-right">

            <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-extrabold text-gentle-black mb-4 sm:mb-6" style="color: var(--color-gentle-black);">

                مرحله ۱: اطلاعات هویتی

            </h2>

            

            @if ($errors->any() || session('error'))

            <div class="bg-red-50 border-r-4 border-red-500 text-red-800 px-3 sm:px-4 md:px-6 py-3 sm:py-4 rounded-lg mb-4 sm:mb-6 shadow-md">

                <div class="flex items-start">

                    <div class="flex-shrink-0">

                        <i class="fas fa-exclamation-circle text-red-500 text-lg sm:text-xl"></i>

                    </div>

                    <div class="mr-2 sm:mr-3 flex-1">

                        <h3 class="text-base sm:text-lg font-bold mb-2 text-red-800">لطفاً خطاهای زیر را اصلاح کنید:</h3>

                        <ul class="list-none space-y-2">

                            @foreach ($errors->all() as $error)

                                <li class="flex items-start">

                                    <i class="fas fa-times-circle text-red-500 ml-2 mt-1 text-sm"></i>

                                    <span class="text-red-700">{{ $error }}</span>

                                </li>

                            @endforeach

                            @if (session('error') && !$errors->any())

                                <li class="flex items-start">

                                    <i class="fas fa-times-circle text-red-500 ml-2 mt-1 text-sm"></i>

                                    <span class="text-red-700 font-medium">{{ session('error') }}</span>

                                </li>

                            @endif

                        </ul>

                    </div>

                </div>

            </div>

            @endif

            

            <form id="step1-form" action="{{ route('register.step1.process') }}" method="POST" class="space-y-4 sm:space-y-6">

                @csrf

                

                <!-- Name -->

                <div>

                    <label for="first_name" class="block text-sm sm:text-base md:text-lg font-bold text-gray-800 mb-2 sm:mb-3">

                        نام: <span class="text-red-500">*</span>

                    </label>

                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" required

                           class="w-full px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base border rounded-lg shadow-sm focus:ring-2 focus:ring-ocean-blue focus:border-ocean-blue transition @error('first_name') border-red-500 bg-red-50 @else border-gray-300 @enderror"

                           placeholder="نام خود را به فارسی وارد کنید">

                    @error('first_name')

                        <div class="mt-2 flex items-center text-red-600 text-sm">

                            <i class="fas fa-exclamation-triangle ml-2"></i>

                            <span>{{ $message }}</span>

                        </div>

                    @enderror

                </div>

                

                <!-- Last Name -->

                <div>

                    <label for="last_name" class="block text-sm sm:text-base md:text-lg font-bold text-gray-800 mb-2 sm:mb-3">

                        نام خانوادگی: <span class="text-red-500">*</span>

                    </label>

                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" required

                           class="w-full px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base border rounded-lg shadow-sm focus:ring-2 focus:ring-ocean-blue focus:border-ocean-blue transition @error('last_name') border-red-500 bg-red-50 @else border-gray-300 @enderror"

                           placeholder="نام خانوادگی خود را به فارسی وارد کنید">

                    @error('last_name')

                        <div class="mt-2 flex items-center text-red-600 text-sm">

                            <i class="fas fa-exclamation-triangle ml-2"></i>

                            <span>{{ $message }}</span>

                        </div>

                    @enderror

                </div>

                

                <!-- Birth Date -->

                <div>

                    <label class="block text-sm sm:text-base md:text-lg font-bold text-gray-800 mb-2 sm:mb-3">

                        تاریخ تولد: <span class="text-red-500">*</span>

                    </label>

                    <div class="grid grid-cols-3 gap-2 sm:gap-3 md:gap-4">

                        <select name="birth_date[]" required class="px-2 sm:px-3 md:px-4 py-2 sm:py-3 text-sm sm:text-base border rounded-lg focus:ring-2 focus:ring-ocean-blue transition @error('birth_date') border-red-500 bg-red-50 @else border-gray-300 @enderror">

                            <option value="">روز</option>

                            @for ($i = 1; $i <= 31; $i++)

                                <option value="{{ $i }}" {{ old('birth_date.0') == $i ? 'selected' : '' }}>{{ $i }}</option>

                            @endfor

                        </select>

                        

                        <select name="birth_date[]" required class="px-2 sm:px-3 md:px-4 py-2 sm:py-3 text-sm sm:text-base border rounded-lg focus:ring-2 focus:ring-ocean-blue transition @error('birth_date') border-red-500 bg-red-50 @else border-gray-300 @enderror">

                            <option value="">ماه</option>

                            @php $months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند']; @endphp

                            @foreach($months as $index => $month)

                                <option value="{{ $index + 1 }}" {{ old('birth_date.1') == ($index + 1) ? 'selected' : '' }}>{{ $month }}</option>

                            @endforeach

                        </select>

                        

                        <select name="birth_date[]" required class="px-2 sm:px-3 md:px-4 py-2 sm:py-3 text-sm sm:text-base border rounded-lg focus:ring-2 focus:ring-ocean-blue transition @error('birth_date') border-red-500 bg-red-50 @else border-gray-300 @enderror">

                            <option value="">سال</option>

                            @php

                                use Morilog\Jalali\Jalalian;

                                $currentYear = Jalalian::now()->getYear() - 15;

                                $startYear = $currentYear - 135;

                            @endphp

                            @for ($i = $currentYear; $i >= $startYear; $i--)

                                <option value="{{ $i }}" {{ old('birth_date.2') == $i ? 'selected' : '' }}>{{ $i }}</option>

                            @endfor

                        </select>

                    </div>

                    @error('birth_date')

                        <div class="mt-2 flex items-center text-red-600 text-sm">

                            <i class="fas fa-exclamation-triangle ml-2"></i>

                            <span>{{ $message }}</span>

                        </div>

                    @enderror

                </div>

                

                <!-- Gender -->

                <div>

                    <label class="block text-sm sm:text-base md:text-lg font-bold text-gray-800 mb-2 sm:mb-3">

                        جنسیت: <span class="text-red-500">*</span>

                    </label>

                    <div class="flex flex-wrap gap-3 sm:gap-4">

                        <label class="inline-flex items-center cursor-pointer">

                            <input type="radio" name="gender" value="male" {{ old('gender') == 'male' ? 'checked' : '' }} class="form-radio h-5 w-5" style="color: var(--color-earth-green);">

                            <span class="mr-2 text-gray-700">مرد</span>

                        </label>

                        <label class="inline-flex items-center cursor-pointer">

                            <input type="radio" name="gender" value="female" {{ old('gender') == 'female' ? 'checked' : '' }} class="form-radio h-5 w-5" style="color: var(--color-earth-green);">

                            <span class="mr-2 text-gray-700">زن</span>

                        </label>

                    </div>

                    @error('gender')

                        <div class="mt-2 flex items-center text-red-600 text-sm">

                            <i class="fas fa-exclamation-triangle ml-2"></i>

                            <span>{{ $message }}</span>

                        </div>

                    @enderror

                </div>

                

                <!-- Nationality -->

                <div>

                    <label for="nationality" class="block text-sm sm:text-base md:text-lg font-bold text-gray-800 mb-2 sm:mb-3">

                        ملیت: <span class="text-red-500">*</span>

                    </label>

                    <select name="nationality" id="nationality" required class="w-full px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base border rounded-lg focus:ring-2 focus:ring-ocean-blue transition @error('nationality') border-red-500 bg-red-50 @else border-gray-300 @enderror">

                        <option value="ایرانی" {{ old('nationality') == 'ایرانی' ? 'selected' : '' }}>ایرانی</option>

                        <option value="مهاجر" {{ old('nationality') == 'مهاجر' ? 'selected' : '' }}>مهاجر</option>

                    </select>

                    @error('nationality')

                        <div class="mt-2 flex items-center text-red-600 text-sm">

                            <i class="fas fa-exclamation-triangle ml-2"></i>

                            <span>{{ $message }}</span>

                        </div>

                    @enderror

                </div>

                

                <!-- National ID -->

                <div>

                    <label for="national_id" class="block text-sm sm:text-base md:text-lg font-bold text-gray-800 mb-2 sm:mb-3">

                        کدملی: <span class="text-red-500">*</span>

                    </label>

                    <input type="text" id="national_id" name="national_id" value="{{ old('national_id') }}" required

                           class="w-full px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base border rounded-lg focus:ring-2 focus:ring-ocean-blue font-poppins text-right transition @error('national_id') border-red-500 bg-red-50 @else border-gray-300 @enderror"

                           placeholder="کدملی 10 رقمی" dir="ltr" style="direction: ltr; text-align: center;">

                    @error('national_id')

                        <div class="mt-2 flex items-center text-red-600 text-sm">

                            <i class="fas fa-exclamation-triangle ml-2"></i>

                            <span>{{ $message }}</span>

                        </div>

                    @enderror

                </div>

                

                @include('partials.countries-list')

                

                <!-- Password (if not set) -->

                @if (auth()->user()->password == null)

                <div class="relative">

                    <label for="password" class="block text-sm sm:text-base md:text-lg font-bold text-gray-800 mb-2 sm:mb-3">

                        رمز عبور: <span class="text-red-500">*</span>

                    </label>

                    <input type="password" id="password" name="password" required

                           class="w-full px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base border rounded-lg focus:ring-2 focus:ring-ocean-blue pl-10 sm:pl-12 transition @error('password') border-red-500 bg-red-50 @else border-gray-300 @enderror">

                    <i class="fas fa-eye absolute left-3 sm:left-4 top-11 sm:top-14 cursor-pointer text-gray-500 toggle-password text-sm sm:text-base" data-target="password"></i>

                    @error('password')

                        <div class="mt-2 flex items-center text-red-600 text-sm">

                            <i class="fas fa-exclamation-triangle ml-2"></i>

                            <span>{{ $message }}</span>

                        </div>

                    @enderror

                </div>

                

                <div class="relative">

                    <label for="password_confirmation" class="block text-sm sm:text-base md:text-lg font-bold text-gray-800 mb-2 sm:mb-3">

                        تأیید رمز عبور: <span class="text-red-500">*</span>

                    </label>

                    <input type="password" id="password_confirmation" name="password_confirmation" required

                           class="w-full px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-ocean-blue pl-10 sm:pl-12 transition">

                    <i class="fas fa-eye absolute left-3 sm:left-4 top-11 sm:top-14 cursor-pointer text-gray-500 toggle-password text-sm sm:text-base" data-target="password_confirmation"></i>

                </div>

                @endif

                

                <!-- Email (disabled) -->

                <div>

                    <label for="email" class="block text-sm sm:text-base md:text-lg font-bold text-gray-800 mb-2 sm:mb-3">ایمیل:</label>

                    <input type="email" id="email" value="{{ old('email', auth()->user()->email) }}" disabled

                           class="w-full px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base border border-gray-300 rounded-lg bg-gray-100 text-gray-600">

                </div>

                

                <!-- Submit Button -->

                <button type="button" id="submit-btn"

                        class="w-full px-4 sm:px-6 py-3 sm:py-4 rounded-full text-white font-bold text-sm sm:text-base md:text-lg shadow-lg hover:shadow-xl transform hover:scale-105 transition duration-300"

                        style="background-color: var(--color-earth-green);">

                    <i class="fas fa-arrow-left ml-2"></i>

                    ادامه

                </button>

            </form>

            

            <!-- Confirmation Modal -->

            <div id="confirmation-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-2 sm:p-4 hidden">

                <div class="bg-white rounded-xl sm:rounded-2xl shadow-2xl max-w-2xl w-full mx-auto p-4 sm:p-6 md:p-8" style="max-height: 95vh; overflow-y: auto;">

                    <!-- Header -->

                    <div class="flex items-center justify-between mb-4 sm:mb-6">

                        <h3 class="text-lg sm:text-xl md:text-2xl font-extrabold text-gentle-black" style="color: var(--color-gentle-black);">

                            <i class="fas fa-check-circle ml-2" style="color: var(--color-earth-green);"></i>

                            بررسی و تأیید اطلاعات

                        </h3>

                        <button id="close-modal" class="text-gray-400 hover:text-gray-600 text-xl sm:text-2xl">

                            <i class="fas fa-times"></i>

                        </button>

                    </div>

                    

                    <!-- Content -->

                    <div class="space-y-3 sm:space-y-4 mb-4 sm:mb-6">

                        <div class="bg-gray-50 rounded-lg p-3 sm:p-4">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4">

                                <div>

                                    <label class="text-sm font-bold text-gray-600 block mb-1">نام:</label>

                                    <p class="text-lg text-gray-800" id="modal-first-name">-</p>

                                </div>

                                <div>

                                    <label class="text-sm font-bold text-gray-600 block mb-1">نام خانوادگی:</label>

                                    <p class="text-lg text-gray-800" id="modal-last-name">-</p>

                                </div>

                                <div>

                                    <label class="text-sm font-bold text-gray-600 block mb-1">تاریخ تولد:</label>

                                    <p class="text-lg text-gray-800" id="modal-birth-date">-</p>

                                </div>

                                <div>

                                    <label class="text-sm font-bold text-gray-600 block mb-1">جنسیت:</label>

                                    <p class="text-lg text-gray-800" id="modal-gender">-</p>

                                </div>

                                <div>

                                    <label class="text-sm font-bold text-gray-600 block mb-1">ملیت:</label>

                                    <p class="text-lg text-gray-800" id="modal-nationality">-</p>

                                </div>

                                <div>

                                    <label class="text-sm font-bold text-gray-600 block mb-1">کد ملی:</label>

                                    <p class="text-lg text-gray-800 font-mono" id="modal-national-id" dir="ltr">-</p>

                                </div>

                                <div>

                                    <label class="text-sm font-bold text-gray-600 block mb-1">شماره تلفن:</label>

                                    <p class="text-lg text-gray-800" id="modal-phone" dir="ltr">-</p>

                                </div>

                                <div>

                                    <label class="text-sm font-bold text-gray-600 block mb-1">ایمیل:</label>

                                    <p class="text-lg text-gray-800" id="modal-email">-</p>

                                </div>

                            </div>

                        </div>

                        

                        <div class="bg-blue-50 border-r-4 border-blue-500 p-3 sm:p-4 rounded-lg">

                            <p class="text-xs sm:text-sm text-blue-800">

                                <i class="fas fa-info-circle ml-2"></i>

                                لطفاً اطلاعات بالا را بررسی کنید. در صورت صحت، روی دکمه "تأیید و ادامه" کلیک کنید.

                            </p>

                        </div>

                    </div>

                    

                    <!-- Actions -->

                    <div class="flex flex-col sm:flex-row gap-2 sm:gap-4">

                        <button id="edit-btn" type="button"

                                class="flex-1 px-4 sm:px-6 py-2 sm:py-3 rounded-full text-gray-700 font-bold text-sm sm:text-base border-2 border-gray-300 hover:bg-gray-50 transition duration-300">

                            <i class="fas fa-edit ml-2"></i>

                            ویرایش

                        </button>

                        <button id="confirm-btn" type="button"

                                class="flex-1 px-4 sm:px-6 py-2 sm:py-3 rounded-full text-white font-bold text-sm sm:text-base shadow-lg hover:shadow-xl transform hover:scale-105 transition duration-300"

                                style="background-color: var(--color-earth-green);">

                            <i class="fas fa-check ml-2"></i>

                            تأیید و ادامه

                        </button>

                    </div>

                </div>

            </div>

        </div>

    </div>

    

    <script>

        // Toggle Password Visibility

        document.querySelectorAll('.toggle-password').forEach(toggle => {

            toggle.addEventListener('click', () => {

                const targetId = toggle.getAttribute('data-target');

                const input = document.getElementById(targetId);

                if (input.type === 'password') {

                    input.type = 'text';

                    toggle.classList.replace('fa-eye', 'fa-eye-slash');

                } else {

                    input.type = 'password';

                    toggle.classList.replace('fa-eye-slash', 'fa-eye');

                }

            });

        });

        

        // Enter key navigation

        const focusable = document.querySelectorAll('form input, form select, form textarea');

        focusable.forEach((field, index) => {

            field.addEventListener('keydown', function(e) {

                if (e.key === 'Enter') {

                    e.preventDefault();

                    const next = focusable[index + 1];

                    if (next) next.focus();

                }

            });

        });

        

        // Form Submission with Modal

        document.getElementById('submit-btn').addEventListener('click', function(e) {

            e.preventDefault();

            

            const form = document.getElementById('step1-form');

            const formData = new FormData(form);

            const submitBtn = document.getElementById('submit-btn');

            const originalText = submitBtn.innerHTML;

            

            // Disable button and show loading

            submitBtn.disabled = true;

            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin ml-2"></i> در حال بررسی...';

            

            // Validate data via AJAX

            fetch('{{ route("register.step1.validate") }}', {

                method: 'POST',

                body: formData,

                headers: {

                    'X-Requested-With': 'XMLHttpRequest',

                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')

                }

            })

            .then(async (response) => {

                const data = await response.json().catch(() => ({}));

                if (!response.ok) {

                    return Promise.resolve({ success: false, errors: data.errors || data });

                }

                return data;

            })

            .then(data => {

                submitBtn.disabled = false;

                submitBtn.innerHTML = originalText;

                

                if (data.success) {

                    // Fill modal with data

                    document.getElementById('modal-first-name').textContent = data.data.first_name;

                    document.getElementById('modal-last-name').textContent = data.data.last_name;

                    document.getElementById('modal-birth-date').textContent = data.data.birth_date;

                    document.getElementById('modal-gender').textContent = data.data.gender;

                    document.getElementById('modal-nationality').textContent = data.data.nationality;

                    document.getElementById('modal-national-id').textContent = data.data.national_id;

                    document.getElementById('modal-phone').textContent = data.data.phone;

                    document.getElementById('modal-email').textContent = data.data.email;

                    

                    // Show modal

                    document.getElementById('confirmation-modal').classList.remove('hidden');

                } else {

                    // Show validation errors

                    if (data.errors) {

                        let errorHtml = '<div class="bg-red-50 border-r-4 border-red-500 text-red-800 px-6 py-4 rounded-lg mb-6 shadow-md"><ul class="list-none space-y-2">';

                        for (const [field, messages] of Object.entries(data.errors)) {

                            messages.forEach(message => {

                                errorHtml += '<li class="flex items-start"><i class="fas fa-times-circle text-red-500 ml-2 mt-1 text-sm"></i><span class="text-red-700">' + message + '</span></li>';

                            });

                        }

                        errorHtml += '</ul></div>';

                        

                        // Remove existing error box

                        const existingError = document.querySelector('.error-box-container');

                        if (existingError) {

                            existingError.remove();

                        }

                        

                        // Add new error box

                        const form = document.getElementById('step1-form');

                        const errorDiv = document.createElement('div');

                        errorDiv.className = 'error-box-container';

                        errorDiv.innerHTML = errorHtml;

                        if (form && form.parentElement) {

                            form.parentElement.insertBefore(errorDiv, form);

                        }

                        

                        // Scroll to top

                        window.scrollTo({ top: 0, behavior: 'smooth' });

                    }

                }

            })

            .catch(error => {

                submitBtn.disabled = false;

                submitBtn.innerHTML = originalText;

                console.error('Error:', error);

                alert('خطایی رخ داد. لطفاً دوباره تلاش کنید.');

            });

        });

        

        // Close modal

        document.getElementById('close-modal').addEventListener('click', function() {

            document.getElementById('confirmation-modal').classList.add('hidden');

        });

        

        // Edit button - close modal

        document.getElementById('edit-btn').addEventListener('click', function() {

            document.getElementById('confirmation-modal').classList.add('hidden');

        });

        

        // Confirm button - submit form

        document.getElementById('confirm-btn').addEventListener('click', function() {

            const confirmBtn = document.getElementById('confirm-btn');

            const originalText = confirmBtn.innerHTML;

            

            confirmBtn.disabled = true;

            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin ml-2"></i> در حال ثبت...';

            

            // Submit the actual form

            document.getElementById('step1-form').submit();

        });

        

        // Close modal on outside click

        document.getElementById('confirmation-modal').addEventListener('click', function(e) {

            if (e.target === this) {

                this.classList.add('hidden');

            }

        });

    </script>

    

    <!-- Congratulations Modal -->

    @if(session('congratulations'))

    <div id="congratulations-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">

        <div class="bg-white rounded-2xl shadow-2xl max-w-md mx-auto p-8 text-center" style="animation: fadeIn 0.5s ease-out;">

            <!-- Success Icon -->

            <div class="mb-6">

                <div class="w-24 h-24 mx-auto rounded-full flex items-center justify-center" style="background-color: rgba(16, 185, 129, 0.1);">

                    <i class="fas fa-check-circle text-6xl" style="color: var(--color-earth-green);"></i>

                </div>

            </div>

            

            <!-- Message -->

            <h2 class="text-3xl font-extrabold text-gentle-black mb-4" style="color: var(--color-gentle-black);">

                تبریک می‌گوییم! 🎉

            </h2>

            

            <p class="text-lg text-gray-700 mb-6 leading-relaxed">

                سهامدار عزیز، شما با موفقیت به <span class="font-bold" style="color: var(--color-earth-green);">EarthCoop</span> پیوستید. 

                دنیای همکاری‌های بزرگ در انتظار شماست.

            </p>

            

            <p class="text-base text-gray-600 mb-6 leading-relaxed">

                لطفا دقایقی وقت بگذارید و در <span class="font-bold">سه مرحله</span> اطلاعات هویتی، صنفی و مکانی خود را وارد کنید.

            </p>

            

            <!-- Close Button -->

            <button onclick="document.getElementById('congratulations-modal').style.display='none'" 

                    class="w-full px-8 py-4 rounded-full text-white font-bold text-lg shadow-lg hover:shadow-xl transform hover:scale-105 transition duration-300"

                    style="background-color: var(--color-ocean-blue);">

                <i class="fas fa-arrow-left ml-2"></i>

                باشه، بزن بریم!

            </button>

        </div>

    </div>

    

    <style>

        @keyframes fadeIn {

            from { opacity: 0; transform: scale(0.9); }

            to { opacity: 1; transform: scale(1); }

        }

    </style>

    @endif



</body>

</html>

