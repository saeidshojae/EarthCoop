<!DOCTYPE html>

<html lang="fa" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">

    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <meta name="format-detection" content="telephone=no">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>مرحله ۲ - زمینه‌های صنفی و تخصصی</title>

    

    <!-- Tailwind CSS -->

    <!-- Vite CSS & JS -->

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    

    <!-- Fonts -->

    <link href="<link rel="stylesheet" href="{{ asset("Css/fonts-local.css") }}">" rel="stylesheet">

    

    <!-- Font Awesome -->

    <link rel="stylesheet" href="{{ asset("vendor/fontawesome/css/all.min.css") }}">

    

    <!-- jQuery -->

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    

    <!-- Select2 -->

    <link href="{{ asset("vendor/select2/css/select2.min.css") }}" rel="stylesheet" />

    <script src="{{ asset("vendor/select2/js/select2.min.js") }}"></script>

    

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

        

        .form-card-gradient::before {

            content: '';

            position: absolute;

            top: 0;

            left: 0;

            width: 100%;

            height: 6px;

            background: linear-gradient(90deg, var(--color-earth-green), var(--color-ocean-blue), var(--color-digital-gold));

        }

        

        /* Select2 Custom Styles */

        .select2-container--default .select2-selection--single {

            border: 1px solid #d1d5db;

            border-radius: 0.5rem;

            height: 3rem;

            padding: 0.5rem 1rem;

        }

        

        .select2-container--default .select2-selection--single .select2-selection__rendered {

            line-height: 2rem;

            color: var(--color-gentle-black);

        }

        

        .select2-container--default .select2-selection--single .select2-selection__arrow {

            height: 3rem;

        }

        

        .select2-container {

            width: 100% !important;

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

            

            /* Select2 Mobile */

            .select2-container--default .select2-selection--single {

                height: 2.5rem !important;

                padding: 0.375rem 0.75rem !important;

                font-size: 0.875rem !important;

            }

            

            .select2-container--default .select2-selection--single .select2-selection__rendered {

                line-height: 1.75rem !important;

                font-size: 0.875rem !important;

                padding-right: 0.5rem !important;

            }

            

            .select2-container--default .select2-selection--single .select2-selection__arrow {

                height: 2.5rem !important;

                right: 0.5rem !important;

            }

            

            .select2-dropdown {

                font-size: 0.875rem !important;

            }

            

            .select2-results__option {

                padding: 0.5rem 0.75rem !important;

                font-size: 0.875rem !important;

            }

            

            .select2-search--dropdown .select2-search__field {

                padding: 0.5rem !important;

                font-size: 0.875rem !important;

            }

            

            /* Badge Mobile */

            .field-badge {

                padding: 0.375rem 0.75rem !important;

                font-size: 0.8125rem !important;

                margin: 0.125rem !important;

            }

            

            .remove-badge {

                font-size: 1rem !important;

            }

        }

        

        /* Badge Styles */

        .field-badge {

            display: inline-flex;

            align-items: center;

            gap: 0.5rem;

            padding: 0.5rem 1rem;

            border-radius: 9999px;

            font-weight: 600;

            margin: 0.25rem;

            background-color: var(--color-ocean-blue);

            color: white;

        }

        

        .remove-badge {

            cursor: pointer;

            font-size: 1.25rem;

            transition: transform 0.2s;

        }

        

        .remove-badge:hover {

            transform: scale(1.2);

        }

    </style>

</head>

<body class="font-vazirmatn leading-relaxed flex items-center justify-center min-h-screen p-2 sm:p-4">



    <div class="form-card-gradient w-full max-w-3xl mx-auto p-4 sm:p-6 md:p-8 lg:p-10">

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

            <div class="flex items-center justify-center gap-2 sm:gap-3 md:gap-4 mb-2 sm:mb-4">

                <div class="flex items-center flex-col sm:flex-row opacity-50">

                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center font-bold text-white bg-gray-400 text-sm sm:text-base">

                        <i class="fas fa-check text-xs sm:text-sm"></i>

                    </div>

                    <span class="mr-0 sm:mr-2 mt-1 sm:mt-0 text-xs sm:text-sm text-gray-500 hidden sm:inline">هویتی</span>

                </div>

                <div class="w-4 h-1 sm:w-6 md:w-8 bg-gray-300 hidden sm:block"></div>

                <div class="flex items-center flex-col sm:flex-row">

                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center font-bold text-white text-sm sm:text-base" style="background-color: var(--color-ocean-blue);">۲</div>

                    <span class="mr-0 sm:mr-2 mt-1 sm:mt-0 text-xs sm:text-sm font-bold hidden sm:inline" style="color: var(--color-ocean-blue);">صنفی</span>

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

            <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-extrabold text-gentle-black mb-3 sm:mb-4" style="color: var(--color-gentle-black);">

                مرحله ۲: زمینه‌های صنفی و تخصصی

            </h2>

            <p class="text-sm sm:text-base text-gray-600 mb-4 sm:mb-6 md:mb-8">

                لطفا زمینه‌های صنفی و تخصصی خود را تا سه سطح وارد نمایید، از این اطلاعات برای ایجاد گروه‌های صنفی و تخصصی شما استفاده می‌شود.

            </p>

            

            @if ($errors->any())

            <div class="bg-red-100 border border-red-400 text-red-700 px-3 sm:px-4 py-2 sm:py-3 rounded-lg mb-4 sm:mb-6 text-sm sm:text-base">

                <ul class="list-disc list-inside">

                    @foreach ($errors->all() as $error)

                        <li>{{ $error }}</li>

                    @endforeach

                </ul>

            </div>

            @endif

            

            <form action="{{ route('register.step2.process') }}" method="POST" id="step2Form" class="space-y-6 sm:space-y-8">

                @csrf

                

                <!-- زمینه صنفی -->

                <div id="occupational_container">

                    <label class="block text-sm sm:text-base md:text-lg font-bold text-gray-800 mb-2 sm:mb-3">

                        <i class="fas fa-briefcase ml-2" style="color: var(--color-earth-green);"></i>

                        زمینه فعالیت صنفی:

                    </label>

                    

                    <!-- Selected Fields Display -->

                    <div id="selected_occupational_fields" class="mb-3 sm:mb-4 min-h-[2.5rem] sm:min-h-[3rem] p-2 sm:p-3 border-2 border-dashed border-gray-300 rounded-lg">

                        <span class="text-gray-400 text-xs sm:text-sm" id="occ_placeholder">انتخاب‌های شما اینجا نمایش داده می‌شود (حداکثر 2)</span>

                    </div>

                    

                    <!-- Error Messages -->

                    <div id="error_occupational" class="hidden bg-red-100 text-red-700 px-3 sm:px-4 py-2 rounded-lg mb-2 sm:mb-3 text-xs sm:text-sm">

                        شما فقط می‌توانید حداکثر 2 مورد انتخاب کنید.

                    </div>

                    <div id="duplicate_error_occupational" class="hidden bg-yellow-100 text-yellow-700 px-3 sm:px-4 py-2 rounded-lg mb-2 sm:mb-3 text-xs sm:text-sm">

                        این مورد قبلاً انتخاب شده است.

                    </div>

                    

                    <!-- Level 1 Select with Create Button -->

                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3 mb-2 sm:mb-3">

                        <select id="occupational_fields" class="flex-grow px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base border border-gray-300 rounded-lg" data-level="1">

                            <option value="">انتخاب کنید</option>

                            @foreach($occupationalFields as $field)

                                <option value="{{ $field->id }}">{{ $field->name }}</option>

                            @endforeach

                        </select>

                        <button type="button" class="create-field-btn bg-earth-green text-white px-3 sm:px-4 md:px-5 py-2 rounded-full shadow-md hover:bg-green-700 transition duration-300 font-bold whitespace-nowrap text-xs sm:text-sm md:text-base" 

                                data-level="1" data-parent-id="0" data-is-exp="false" style="background-color: var(--color-earth-green);">

                            + ایجاد کنید

                        </button>

                    </div>

                    

                    <!-- Level 2 Select with Create Button (hidden initially) -->

                    <div id="occ_level2_container" class="hidden mb-2 sm:mb-3">

                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3">

                            <select id="occupational_subfields" class="flex-grow px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base border border-gray-300 rounded-lg" data-level="2">

                                <option value="">زیردسته را انتخاب کنید</option>

                            </select>

                            <button type="button" class="create-field-btn bg-earth-green text-white px-3 sm:px-4 md:px-5 py-2 rounded-full shadow-md hover:bg-green-700 transition duration-300 font-bold whitespace-nowrap text-xs sm:text-sm md:text-base" 

                                    data-level="2" data-parent-id="" data-is-exp="false" style="background-color: var(--color-earth-green);">

                                + ایجاد کنید

                            </button>

                        </div>

                    </div>

                    

                    <!-- Level 3 Select with Create Button (hidden initially) -->

                    <div id="occ_level3_container" class="hidden mb-2 sm:mb-3">

                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3">

                            <select id="occupational_finalfields" class="flex-grow px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base border border-gray-300 rounded-lg" data-level="3">

                                <option value="">سطح سوم را انتخاب کنید</option>

                            </select>

                            <button type="button" class="create-field-btn bg-earth-green text-white px-3 sm:px-4 md:px-5 py-2 rounded-full shadow-md hover:bg-green-700 transition duration-300 font-bold whitespace-nowrap text-xs sm:text-sm md:text-base" 

                                    data-level="3" data-parent-id="" data-is-exp="false" style="background-color: var(--color-earth-green);">

                                + ایجاد کنید

                            </button>

                        </div>

                    </div>

                </div>

                

                <!-- زمینه تخصصی -->

                <div id="experience_container">

                    <label class="block text-sm sm:text-base md:text-lg font-bold text-gray-800 mb-2 sm:mb-3">

                        <i class="fas fa-graduation-cap ml-2" style="color: var(--color-digital-gold);"></i>

                        زمینه فعالیت تخصصی:

                    </label>

                    

                    <!-- Selected Fields Display -->

                    <div id="selected_experience_fields" class="mb-3 sm:mb-4 min-h-[2.5rem] sm:min-h-[3rem] p-2 sm:p-3 border-2 border-dashed border-gray-300 rounded-lg">

                        <span class="text-gray-400 text-xs sm:text-sm" id="exp_placeholder">انتخاب‌های شما اینجا نمایش داده می‌شود (حداکثر 2)</span>

                    </div>

                    

                    <!-- Error Messages -->

                    <div id="error_experience" class="hidden bg-red-100 text-red-700 px-3 sm:px-4 py-2 rounded-lg mb-2 sm:mb-3 text-xs sm:text-sm">

                        شما فقط می‌توانید حداکثر 2 مورد انتخاب کنید.

                    </div>

                    <div id="duplicate_error_experience" class="hidden bg-yellow-100 text-yellow-700 px-3 sm:px-4 py-2 rounded-lg mb-2 sm:mb-3 text-xs sm:text-sm">

                        این مورد قبلاً انتخاب شده است.

                    </div>

                    

                    <!-- Level 1 Select with Create Button -->

                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3 mb-2 sm:mb-3">

                        <select id="experience_fields" class="flex-grow px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base border border-gray-300 rounded-lg" data-level="1">

                            <option value="">انتخاب کنید</option>

                            @foreach($experienceFields as $field)

                                <option value="{{ $field->id }}">{{ $field->name }}</option>

                            @endforeach

                        </select>

                        <button type="button" class="create-field-btn bg-digital-gold text-white px-3 sm:px-4 md:px-5 py-2 rounded-full shadow-md hover:bg-yellow-600 transition duration-300 font-bold whitespace-nowrap text-xs sm:text-sm md:text-base" 

                                data-level="1" data-parent-id="0" data-is-exp="true" style="background-color: var(--color-digital-gold);">

                            + ایجاد کنید

                        </button>

                    </div>

                    

                    <!-- Level 2 Select with Create Button (hidden initially) -->

                    <div id="exp_level2_container" class="hidden mb-2 sm:mb-3">

                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3">

                            <select id="experience_subfields" class="flex-grow px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base border border-gray-300 rounded-lg" data-level="2">

                                <option value="">زیردسته را انتخاب کنید</option>

                            </select>

                            <button type="button" class="create-field-btn bg-digital-gold text-white px-3 sm:px-4 md:px-5 py-2 rounded-full shadow-md hover:bg-yellow-600 transition duration-300 font-bold whitespace-nowrap text-xs sm:text-sm md:text-base" 

                                    data-level="2" data-parent-id="" data-is-exp="true" style="background-color: var(--color-digital-gold);">

                                + ایجاد کنید

                            </button>

                        </div>

                    </div>

                    

                    <!-- Level 3 Select with Create Button (hidden initially) -->

                    <div id="exp_level3_container" class="hidden mb-2 sm:mb-3">

                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3">

                            <select id="experience_finalfields" class="flex-grow px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base border border-gray-300 rounded-lg" data-level="3">

                                <option value="">سطح سوم را انتخاب کنید</option>

                            </select>

                            <button type="button" class="create-field-btn bg-digital-gold text-white px-3 sm:px-4 md:px-5 py-2 rounded-full shadow-md hover:bg-yellow-600 transition duration-300 font-bold whitespace-nowrap text-xs sm:text-sm md:text-base" 

                                    data-level="3" data-parent-id="" data-is-exp="true" style="background-color: var(--color-digital-gold);">

                                + ایجاد کنید

                            </button>

                        </div>

                    </div>

                </div>

                

                <!-- Submit Button -->

                <button type="submit" id="continueBtn" disabled

                        class="w-full px-4 sm:px-6 py-3 sm:py-4 rounded-full text-white font-bold text-sm sm:text-base md:text-lg shadow-lg transition duration-300 disabled:opacity-50 disabled:cursor-not-allowed"

                        style="background-color: var(--color-earth-green);">

                    <i class="fas fa-arrow-left ml-2"></i>

                    ادامه

                </button>

            </form>

        </div>

    </div>

    

    <!-- Modal: Create New Field -->

    <div id="createFieldModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-2 sm:p-4">

        <div class="bg-white rounded-xl sm:rounded-2xl shadow-2xl max-w-md w-full mx-auto p-4 sm:p-6 md:p-8">

            <h3 class="text-lg sm:text-xl md:text-2xl font-bold text-gentle-black mb-4 sm:mb-6" style="color: var(--color-gentle-black);">

                ایجاد زمینه فعالیت جدید

            </h3>

            

            <form id="createFieldForm">

                <input type="hidden" id="field_level">

                <input type="hidden" id="parent_id">

                

                <div class="mb-4 sm:mb-6">

                    <label for="field_name" class="block text-sm sm:text-base md:text-lg font-bold text-gray-800 mb-2 sm:mb-3">نام زمینه فعالیت:</label>

                    <input type="text" id="field_name" required

                           class="w-full px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-ocean-blue"

                           placeholder="نام را وارد کنید">

                </div>

                

                <div id="create_field_error" class="hidden bg-red-100 text-red-700 px-3 sm:px-4 py-2 rounded-lg mb-3 sm:mb-4 text-xs sm:text-sm">

                    خطا در ثبت اطلاعات. لطفاً دوباره تلاش کنید.

                </div>

                

                <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">

                    <button type="submit"

                            class="flex-1 px-4 sm:px-6 py-2 sm:py-3 rounded-full text-white font-bold text-sm sm:text-base shadow-lg hover:shadow-xl transition duration-300"

                            style="background-color: var(--color-earth-green);">

                        ذخیره

                    </button>

                    <button type="button" onclick="closeCreateModal()"

                            class="flex-1 px-4 sm:px-6 py-2 sm:py-3 rounded-full text-gray-700 font-bold text-sm sm:text-base border-2 border-gray-300 hover:bg-gray-100 transition duration-300">

                        لغو

                    </button>

                </div>

            </form>

        </div>

    </div>



    <script>

        // Global Variables

        let selectedOccupationalFields = [];

        let selectedExperienceFields = [];

        let currentLevel = 1;

        let currentParentId = null;

        let isExperienceMode = false;

        

        $(document).ready(function() {

            // Initialize Select2

            $('#occupational_fields, #experience_fields').select2({

                dir: 'rtl',

                language: 'fa'

            });

            

            // Setup CSRF Token

            $.ajaxSetup({

                headers: {

                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')

                }

            });

            

            // Occupational Fields Change Handler

            $('#occupational_fields').on('change', function() {

                const value = $(this).val();

                const text = $(this).find('option:selected').text();

                

                if (value) {

                    loadSubfields(value, 2, false);

                    // Update create button for level 2

                    $('#occ_level2_container .create-field-btn').attr('data-parent-id', value);

                }

            });

            

            $('#occupational_subfields').on('change', function() {

                const value = $(this).val();

                const text = $(this).find('option:selected').text();

                

                if (value) {

                    loadSubfields(value, 3, false);

                    // Update create button for level 3

                    $('#occ_level3_container .create-field-btn').attr('data-parent-id', value);

                }

            });

            

            $('#occupational_finalfields').on('change', function() {

                const value = $(this).val();

                const text = $(this).find('option:selected').text();

                

                if (value) {

                    addSelectedField(value, text, false);

                }

            });

            

            // Experience Fields Change Handlers

            $('#experience_fields').on('change', function() {

                const value = $(this).val();

                const text = $(this).find('option:selected').text();

                

                if (value) {

                    loadSubfields(value, 2, true);

                    // Update create button for level 2

                    $('#exp_level2_container .create-field-btn').attr('data-parent-id', value);

                }

            });

            

            $('#experience_subfields').on('change', function() {

                const value = $(this).val();

                const text = $(this).find('option:selected').text();

                

                if (value) {

                    loadSubfields(value, 3, true);

                    // Update create button for level 3

                    $('#exp_level3_container .create-field-btn').attr('data-parent-id', value);

                }

            });

            

            $('#experience_finalfields').on('change', function() {

                const value = $(this).val();

                const text = $(this).find('option:selected').text();

                

                if (value) {

                    addSelectedField(value, text, true);

                }

            });

            

            // Create Field Button Click Handler

            $(document).on('click', '.create-field-btn', function() {

                const level = parseInt($(this).attr('data-level'));

                const parentId = $(this).attr('data-parent-id');

                const isExp = $(this).attr('data-is-exp') === 'true';

                openCreateFieldModal(level, parentId === '0' ? null : parentId, isExp);

            });

            

            // Form Submit Handler

            $('#step2Form').on('submit', function() {

                // Remove any previous hidden inputs

                $('input[name="occupational_fields[]"], input[name="experience_fields[]"]').remove();

                

                // Add selected fields as hidden inputs

                selectedOccupationalFields.forEach(id => {

                    $('<input>').attr({

                        type: 'hidden',

                        name: 'occupational_fields[]',

                        value: id

                    }).appendTo('#step2Form');

                });

                

                selectedExperienceFields.forEach(id => {

                    $('<input>').attr({

                        type: 'hidden',

                        name: 'experience_fields[]',

                        value: id

                    }).appendTo('#step2Form');

                });

            });

            

            // Create Field Form Submit

            $('#createFieldForm').on('submit', function(e) {

                e.preventDefault();

                const name = $('#field_name').val();

                const parentId = $('#parent_id').val() || null;

                const url = isExperienceMode 

                    ? '/api/experience-fields'

                    : '/api/occupational-fields';

                

                $.post(url, {

                    name: name,

                    parent_id: parentId

                }, function(newField) {

                    const selectId = getCurrentSelectId();

                    const $select = $(selectId);

                    

                    // Add new field to dropdown

                    $select.append(`<option value="${newField.id}">${newField.name}</option>`);

                    $select.val(newField.id).trigger('change');

                    

                    closeCreateModal();

                }).fail(function() {

                    $('#create_field_error').removeClass('hidden');

                });

            });

        });

        

        function loadSubfields(parentId, level, isExp) {

            const url = `/api/${isExp ? 'experience' : 'occupational'}-fields/${parentId}/children`;

            const selectId = isExp 

                ? (level === 2 ? '#experience_subfields' : '#experience_finalfields')

                : (level === 2 ? '#occupational_subfields' : '#occupational_finalfields');

            const $select = $(selectId);

            const containerId = isExp 

                ? (level === 2 ? '#exp_level2_container' : '#exp_level3_container')

                : (level === 2 ? '#occ_level2_container' : '#occ_level3_container');

            

            $.get(url, function(data) {

                $select.empty().append('<option value="">زیردسته را انتخاب کنید</option>');

                data.forEach(field => {

                    $select.append(`<option value="${field.id}">${field.name}</option>`);

                });

                $select.select2({dir: 'rtl', language: 'fa'});

                

                // Show current level container, hide next level

                $(containerId).removeClass('hidden');

                if (level === 2) {

                    const nextLevel = isExp ? '#exp_level3_container' : '#occ_level3_container';

                    $(nextLevel).addClass('hidden');

                }

            });

        }

        

        function addSelectedField(id, name, isExp) {

            const selected = isExp ? selectedExperienceFields : selectedOccupationalFields;

            const maxErrorId = isExp ? '#error_experience' : '#error_occupational';

            const dupErrorId = isExp ? '#duplicate_error_experience' : '#duplicate_error_occupational';

            const wrapperId = isExp ? '#selected_experience_fields' : '#selected_occupational_fields';

            const placeholderId = isExp ? '#exp_placeholder' : '#occ_placeholder';

            

            // Hide errors first

            $(maxErrorId + ', ' + dupErrorId).addClass('hidden');

            

            // Check duplicate

            if (selected.includes(id)) {

                $(dupErrorId).removeClass('hidden');

                return;

            }

            

            // Check max limit

            if (selected.length >= 2) {

                $(maxErrorId).removeClass('hidden');

                return;

            }

            

            // Add to selected

            selected.push(id);

            $(placeholderId).hide();

            

            // Add badge

            const badge = $(`

                <span class="field-badge">

                    ${name}

                    <span class="remove-badge" onclick="removeField('${id}', this, ${isExp})">&times;</span>

                </span>

            `);

            $(wrapperId).append(badge);

            

            updateContinueButtonState();

        }

        

        function removeField(id, el, isExp) {

            if (isExp) {

                selectedExperienceFields = selectedExperienceFields.filter(x => x !== id);

                $('#error_experience, #duplicate_error_experience').addClass('hidden');

                if (selectedExperienceFields.length === 0) {

                    $('#exp_placeholder').show();

                }

            } else {

                selectedOccupationalFields = selectedOccupationalFields.filter(x => x !== id);

                $('#error_occupational, #duplicate_error_occupational').addClass('hidden');

                if (selectedOccupationalFields.length === 0) {

                    $('#occ_placeholder').show();

                }

            }

            $(el).parent().remove();

            updateContinueButtonState();

        }

        

        function updateContinueButtonState() {

            const hasOccupational = selectedOccupationalFields.length > 0;

            const hasExperience = selectedExperienceFields.length > 0;

            

            $('#continueBtn').prop('disabled', !(hasOccupational && hasExperience));

        }

        

        function openCreateFieldModal(level, parentId, isExp) {

            currentLevel = level;

            currentParentId = parentId;

            isExperienceMode = isExp;

            

            $('#field_level').val(level);

            $('#parent_id').val(parentId || '');

            $('#field_name').val('');

            $('#create_field_error').addClass('hidden');

            $('#createFieldModal').removeClass('hidden');

        }

        

        function closeCreateModal() {

            $('#createFieldModal').addClass('hidden');

            isExperienceMode = false;

        }

        

        function getCurrentSelectId() {

            if (isExperienceMode) {

                return currentLevel === 1 ? '#experience_fields' : 

                       currentLevel === 2 ? '#experience_subfields' : 

                       '#experience_finalfields';

            } else {

                return currentLevel === 1 ? '#occupational_fields' : 

                       currentLevel === 2 ? '#occupational_subfields' : 

                       '#occupational_finalfields';

            }

        }

    </script>



</body>

</html>

