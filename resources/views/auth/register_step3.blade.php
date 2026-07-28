<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="format-detection" content="telephone=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>مرحله ۳ - اطلاعات مکانی</title>

    <!-- Vite CSS & JS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Fonts -->
    <link rel="stylesheet" href="{{ asset('Css/fonts-local.css') }}">
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

        /* Location Path */
        .location-path {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            color: white;
            font-weight: 500;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            font-size: 0.875rem;
            line-height: 1.6;
        }

        .location-path span {
            cursor: pointer;
            transition: all 0.2s;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            display: inline-block;
            white-space: nowrap;
        }

        .location-path span:hover:not(.last) {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .location-path span.last {
            cursor: default;
            font-weight: 700;
        }

        .location-path i {
            font-size: 0.875rem;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 640px) {
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                height: 100% !important;
                overflow-x: hidden !important;
            }

            body {
                padding-top: 0.25rem !important;
                padding-bottom: 0.25rem !important;
                align-items: flex-start !important;
            }

            .form-card-gradient {
                padding: 0.75rem !important;
                border-radius: 12px;
                margin: 0.25rem auto !important;
                width: calc(100% - 0.5rem) !important;
                max-width: calc(100% - 0.5rem) !important;
                max-height: calc(100vh - 0.5rem) !important;
                overflow-y: auto !important;
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

            /* Location Path Mobile */
            .location-path {
                padding: 0.625rem 0.75rem !important;
                font-size: 0.75rem !important;
                margin-bottom: 1rem !important;
                line-height: 1.5 !important;
            }

            .location-path span {
                padding: 0.125rem 0.375rem !important;
                font-size: 0.75rem !important;
            }

            .location-path i {
                font-size: 0.75rem !important;
            }

            /* Create Location Button Mobile */
            .create-location-btn {
                font-size: 0.8125rem !important;
                padding: 0.5rem 1rem !important;
            }

            .create-location-btn i {
                font-size: 0.8125rem !important;
            }

            /* Logo and Step Indicator Mobile */
            .form-card-gradient > div:first-child {
                margin-bottom: 0.75rem !important;
            }

            .form-card-gradient > div:nth-child(2) {
                margin-bottom: 0.75rem !important;
            }

            /* Form spacing Mobile */
            #step3Form {
                margin-top: 0.5rem !important;
            }

            /* Level containers spacing */
            .level-container {
                margin-bottom: 0.75rem !important;
            }

            /* Submit button spacing */
            #continueBtn {
                margin-top: 1rem !important;
                margin-bottom: 0.5rem !important;
            }
        }

        /* Level Container Animation */
        .level-container {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .level-input-group {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            align-items: stretch;
        }

        @media (min-width: 640px) {
            .level-input-group {
                flex-direction: row;
                align-items: center;
            }
        }

        .create-location-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            white-space: nowrap;
            border-radius: 9999px;
            font-weight: 700;
            font-size: 0.95rem;
            padding: 0.65rem 1.5rem;
            background: linear-gradient(135deg, var(--color-earth-green), var(--color-dark-green));
            color: white;
            box-shadow: 0 10px 22px rgba(16, 185, 129, 0.35);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .create-location-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(16, 185, 129, 0.45);
        }

        .create-location-btn i {
            font-size: 0.95rem;
        }
    </style>
</head>
<body class="font-vazirmatn leading-relaxed flex items-center justify-center min-h-screen p-0 sm:p-2 md:p-4">

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
            <div class="flex items-center justify-center flex-wrap gap-2 sm:gap-3 md:gap-4 mb-2 sm:mb-4">
                <div class="flex items-center flex-col sm:flex-row opacity-50">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center font-bold text-white bg-gray-400 text-sm sm:text-base">
                        <i class="fas fa-check text-xs sm:text-sm"></i>
                    </div>
                    <span class="mr-0 sm:mr-2 mt-1 sm:mt-0 text-xs sm:text-sm text-gray-500 hidden sm:inline">هویتی</span>
                </div>
                <div class="w-4 h-1 sm:w-6 md:w-8 bg-gray-300 hidden sm:block"></div>
                <div class="flex items-center flex-col sm:flex-row opacity-50">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center font-bold text-white bg-gray-400 text-sm sm:text-base">
                        <i class="fas fa-check text-xs sm:text-sm"></i>
                    </div>
                    <span class="mr-0 sm:mr-2 mt-1 sm:mt-0 text-xs sm:text-sm text-gray-500 hidden sm:inline">صنفی</span>
                </div>
                <div class="w-4 h-1 sm:w-6 md:w-8 bg-gray-300 hidden sm:block"></div>
                <div class="flex items-center flex-col sm:flex-row">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center font-bold text-white text-sm sm:text-base" style="background-color: var(--color-ocean-blue);">۳</div>
                    <span class="mr-0 sm:mr-2 mt-1 sm:mt-0 text-xs sm:text-sm font-bold hidden sm:inline" style="color: var(--color-ocean-blue);">مکانی</span>
                </div>
            </div>
        </div>

        <!-- Form -->
        <div class="text-right">
            <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-extrabold text-gentle-black mb-3 sm:mb-4" style="color: var(--color-gentle-black);">
                مرحله ۳: اطلاعات مکانی
            </h2>
            <p class="text-gray-600 mb-4 sm:mb-6 text-xs sm:text-sm md:text-base">
                لطفا مکان خود را با دقت انتخاب نمایید. از این اطلاعات برای گروه‌بندی مکانی شما استفاده می‌شود.
                <span class="text-red-600 font-bold">وارد کردن اطلاعات تا سطح محله الزامی است.</span>
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

            @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-3 sm:px-4 py-2 sm:py-3 rounded-lg mb-4 sm:mb-6 text-sm sm:text-base">
                {{ session('error') }}
            </div>
            @endif

            <!-- Location Path Display -->
            <div class="location-path text-center" id="location_path_display">
                <i class="fas fa-map-marker-alt ml-2"></i>
                <span>مسیر انتخاب نشده</span>
            </div>

            <form action="{{ route('register.step3.process') }}" method="POST" id="step3Form" class="space-y-3 sm:space-y-4">
                @csrf

                <div id="location-selects">
                    <!-- قاره (سطح ۱) -->
                    <div class="level-container">
                        <label class="block text-sm sm:text-base md:text-lg font-bold text-gray-800 mb-2 sm:mb-3">
                            <i class="fas fa-globe ml-2" style="color: var(--color-ocean-blue);"></i>
                            قاره:
                        </label>
                        <div class="level-input-group">
                            <select id="continent_select" name="continent_id" class="location-select w-full px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base border border-gray-300 rounded-lg" data-level="1">
                                <option value="">انتخاب کنید</option>
                                @foreach($continents as $continent)
                                    <option value="{{ $continent->id }}" {{ $continent->id == 1 ? 'selected' : '' }}>
                                        {{ $continent->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" id="continueBtn" disabled
                        class="w-full px-4 sm:px-6 py-3 sm:py-4 rounded-full text-white font-bold text-sm sm:text-base md:text-lg shadow-lg transition duration-300 disabled:opacity-50 disabled:cursor-not-allowed mt-6 sm:mt-8"
                        style="background-color: var(--color-earth-green);">
                    <i class="fas fa-check-circle ml-2"></i>
                    تکمیل ثبت نام
                </button>
            </form>
        </div>
    </div>

    <!-- Modal: Create New Location -->
    <div id="createLocationModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-2 sm:p-4">
        <div class="bg-white rounded-xl sm:rounded-2xl shadow-2xl max-w-md mx-auto p-4 sm:p-6 md:p-8 w-full">
            <h3 class="text-lg sm:text-xl md:text-2xl font-bold text-gentle-black mb-4 sm:mb-6" style="color: var(--color-gentle-black);">
                ایجاد <span id="modal_location_type"></span> جدید
            </h3>

            <form id="createLocationForm">
                <input type="hidden" id="location_level">
                <input type="hidden" id="location_parent_id">
                <input type="hidden" id="location_type">

                <div class="mb-4 sm:mb-6">
                    <label for="location_name" class="block text-sm sm:text-base md:text-lg font-bold text-gray-800 mb-2 sm:mb-3">
                        نام <span id="modal_location_type_label"></span>:
                    </label>
                    <input type="text" id="location_name" required
                           class="w-full px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-ocean-blue"
                           placeholder="نام را وارد کنید">
                </div>

                <div id="create_location_error" class="hidden bg-red-100 text-red-700 px-3 sm:px-4 py-2 rounded-lg mb-3 sm:mb-4 text-xs sm:text-sm">
                    خطا در ثبت اطلاعات. لطفاً دوباره تلاش کنید.
                </div>

                <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                    <button type="submit"
                            class="flex-1 px-4 sm:px-6 py-2 sm:py-3 rounded-full text-white font-bold text-sm sm:text-base shadow-lg hover:shadow-xl transition duration-300"
                            style="background-color: var(--color-earth-green);">
                        ذخیره
                    </button>
                    <button type="button" onclick="closeCreateLocationModal()"
                            class="flex-1 px-4 sm:px-6 py-2 sm:py-3 rounded-full text-gray-700 font-bold text-sm sm:text-base border-2 border-gray-300 hover:bg-gray-100 transition duration-300">
                        لغو
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Global Variables
        const levelLabels = ['قاره', 'کشور', 'استان', 'شهرستان', 'بخش', 'شهر / دهستان', 'منطقه/ روستا', 'محله', 'خیابان', 'کوچه'];
        const levelKeys = ['continent', 'country', 'province', 'county', 'section', 'city', 'region', 'neighborhood', 'street', 'alley'];
        const nameKeys = ['continent_id', 'country_id', 'province_id', 'county_id', 'section_id', 'city_id', 'region_id', 'neighborhood_id', 'street_id', 'alley_id'];
        const allowAddModal = ['region', 'neighborhood', 'street', 'alley'];
        const optionalLevels = [9, 10]; // خیابان و کوچه اختیاری هستند
        const defaultContinentId = '4'; // آسیا
        const defaultCountryId = '74'; // ایران

        let pathParts = [];
        let pathValues = [];
        let currentCreateLevel = null;
        let currentCreateType = null;
        let pendingDefaultCountry = true;
        const levelRequestTokens = {};

        $(document).ready(function() {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            $('.location-select').select2({
                dir: 'rtl',
                language: 'fa',
                placeholder: 'انتخاب کنید',
                allowClear: true
            });

            $(document).on('change', '.location-select', function() {
                const level = parseInt($(this).data('level'));
                const value = $(this).val();

                if (value) {
                    const text = $(this).find('option:selected').text();
                    pathParts[level - 1] = text;
                    pathValues[level - 1] = value;
                    pathParts = pathParts.slice(0, level);
                    pathValues = pathValues.slice(0, level);

                    removeLevelContainersFrom(level + 1);

                    if (level === 1) {
                        $(this).closest('.level-container').hide();
                    }

                    if (level < levelKeys.length) {
                        loadNextLevel(value, level + 1);
                    }
                } else {
                    removeLevelContainersFrom(level + 1);
                    pathParts = pathParts.slice(0, level - 1);
                    pathValues = pathValues.slice(0, level - 1);
                    showContainersUpTo(level - 1);
                }

                updatePathDisplay();
            });

            $(document).on('click', '#location_path_display span', function() {
                const level = parseInt($(this).data('level'));
                const value = $(this).data('value');

                if ($(this).hasClass('last')) return;

                if (level === 0) {
                    resetToContinent();
                    return;
                }

                showContainersUpTo(level);
                removeLevelContainersFrom(level + 1);

                pathParts = pathParts.slice(0, level);
                pathValues = pathValues.slice(0, level);

                updatePathDisplay();

                if (level < levelKeys.length) {
                    loadNextLevel(value, level + 1);
                }
            });

            $('#createLocationForm').on('submit', function(e) {
                e.preventDefault();
                const name = $('#location_name').val();
                const parentId = $('#location_parent_id').val();
                const type = $('#location_type').val();
                const level = parseInt($('#location_level').val());

                const url = `/api/add-${type}`;

                $.post(url, {
                    name: name,
                    parent_id: parentId
                }, function(response) {
                    const $select = $(`select[data-level="${level}"]`);
                    if (!$select.length) {
                        closeCreateLocationModal();
                        return;
                    }

                    const newOption = new Option(name, response.id, true, true);
                    $select.append(newOption).trigger('change');

                    closeCreateLocationModal();
                }).fail(function() {
                    $('#create_location_error').removeClass('hidden');
                });
            });

            $(document).on('click', '.create-location-btn', function() {
                const level = parseInt($(this).data('level'));
                const type = $(this).data('type');
                const parentId = $(this).data('parentId');

                if (!parentId) {
                    alert('لطفاً ابتدا سطح قبلی را انتخاب کنید، سپس گزینه جدید را ایجاد نمایید.');
                    return;
                }

                openCreateLocationModal(level, type, parentId);
            });

            initializeDefaultPath();
        });

        function initializeDefaultPath() {
            const $continent = $('#continent_select');
            if (!$continent.length) return;

            pathParts = [];
            pathValues = [];
            pendingDefaultCountry = true;

            const continentText = $continent.find(`option[value="${defaultContinentId}"]`).text().trim();
            const resolvedContinent = continentText || 'آسیا';
            pathParts[0] = resolvedContinent;
            pathValues[0] = defaultContinentId;
            updatePathDisplay();

            $continent.closest('.level-container').show();
            $continent.val(defaultContinentId).trigger('change');
        }

        function removeLevelContainersFrom(startLevel) {
            for (let i = startLevel; i <= levelKeys.length; i++) {
                const $container = $(`select[data-level="${i}"]`).closest('.level-container');
                if ($container.length) {
                    $container.remove();
                }
            }
        }

        function showContainersUpTo(level) {
            for (let i = 1; i <= level; i++) {
                const $container = $(`select[data-level="${i}"]`).closest('.level-container');
                if ($container.length) {
                    $container.show();
                }
            }
        }

        function hideContainersBefore(level) {
            for (let i = 1; i < level; i++) {
                const $container = $(`select[data-level="${i}"]`).closest('.level-container');
                if ($container.length) {
                    $container.hide();
                }
            }
        }

        function loadNextLevel(parentId, level) {
            if (level > levelKeys.length) return;

            const key = levelKeys[level - 1];
            const label = levelLabels[level - 1];
            const name = nameKeys[level - 1];
            const isOptional = optionalLevels.includes(level);
            const hasAdd = allowAddModal.includes(key);
            const showLabel = isOptional ? `${label} (اختیاری)` : label;
            const requestToken = Date.now();

            levelRequestTokens[level] = requestToken;

            hideContainersBefore(level);

            $.get(`/api/locations?level=${key}&parent_id=${parentId}`, function(data) {
                if (levelRequestTokens[level] !== requestToken) {
                    return;
                }

                removeLevelContainersFrom(level);

                let optionsHtml = `<option value="">انتخاب ${showLabel}</option>`;
                if (Array.isArray(data)) {
                    data.forEach(item => {
                        const value = item && item.id !== undefined ? String(item.id) : '';
                        optionsHtml += `<option value="${value}">${item.name}</option>`;
                    });
                }

                const icon = getIconForLevel(level);
                const buttonHtml = hasAdd ? `
                    <button type="button"
                            class="create-location-btn"
                            data-level="${level}"
                            data-type="${key}"
                            data-parent-id="${parentId}">
                        <i class="fas fa-plus-circle"></i>
                        ایجاد ${label}
                    </button>
                ` : '';

                const $container = $(`
                    <div class="level-container">
                        <label class="block text-sm sm:text-base md:text-lg font-bold text-gray-800 mb-2 sm:mb-3">
                            <i class="${icon} ml-2" style="color: var(--color-ocean-blue);"></i>
                            ${showLabel}:
                        </label>
                        <div class="level-input-group">
                            <select name="${name}" class="location-select w-full px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base border border-gray-300 rounded-lg" data-level="${level}">
                                ${optionsHtml}
                            </select>
                            ${buttonHtml}
                        </div>
                    </div>
                `);

                $('#location-selects').append($container);
                const $select = $container.find('select');
                $select.select2({
                    dir: 'rtl',
                    language: 'fa',
                    placeholder: 'انتخاب کنید',
                    allowClear: true
                });

                if (pendingDefaultCountry && level === 2 && parentId === defaultContinentId) {
                    const $defaultOption = $select.find(`option[value="${defaultCountryId}"]`);
                    if ($defaultOption.length) {
                        pendingDefaultCountry = false;
                        $select.val(defaultCountryId).trigger('change');
                    }
                }
            }).fail(function(error) {
                console.error(`Failed to load ${key}:`, error);
            });
        }

        function getIconForLevel(level) {
            const icons = [
                'fas fa-globe',          // قاره
                'fas fa-flag',           // کشور
                'fas fa-map',            // استان
                'fas fa-city',           // شهرستان
                'fas fa-building',       // بخش
                'fas fa-home',           // شهر
                'fas fa-map-marked-alt', // منطقه
                'fas fa-map-pin',        // محله
                'fas fa-road',           // خیابان
                'fas fa-route'           // کوچه
            ];
            return icons[level - 1] || 'fas fa-map-marker-alt';
        }

        function updatePathDisplay() {
            const display = [
                `<span data-level="0" data-value="world"><i class="fas fa-globe-asia ml-1"></i>زمین</span>`,
                ...pathParts
                    .filter(part => part !== null)
                    .map((part, index, arr) => {
                        const level = index + 1;
                        const isLast = index === arr.length - 1;
                        const extraClass = isLast ? 'last' : '';
                        return `<span data-level="${level}" data-value="${pathValues[index]}" class="${extraClass}">${part}</span>`;
                    })
            ].join(' <i class="fas fa-angle-left mx-1 text-sm"></i> ');

            $('#location_path_display').html(display || '<i class="fas fa-map-marker-alt ml-2"></i><span>مسیر انتخاب نشده</span>');
            updateSubmitButtonState();
        }

        function updateSubmitButtonState() {
            const neighborhoodId = pathValues[7]; // محله = سطح ۸
            const $btn = $('#continueBtn');

            if (neighborhoodId) {
                $btn.prop('disabled', false);
            } else {
                $btn.prop('disabled', true);
            }
        }

        function openCreateLocationModal(level, type, parentId) {
            currentCreateLevel = level;
            currentCreateType = type;

            $('#location_level').val(level);
            $('#location_parent_id').val(parentId || '');
            $('#location_type').val(type);
            $('#location_name').val('');
            $('#modal_location_type').text(levelLabels[level - 1]);
            $('#modal_location_type_label').text(levelLabels[level - 1]);
            $('#create_location_error').addClass('hidden');
            $('#createLocationModal').removeClass('hidden');
        }

        function closeCreateLocationModal() {
            $('#createLocationModal').addClass('hidden');
        }

        function resetToContinent() {
            // پاک کردن مسیر
            pathParts = [];
            pathValues = [];

            // حذف تمام منوهای دیگر
            $('#location-selects .level-container').not(':first').remove();

            const $continentContainer = $('#continent_select').closest('.level-container');
            $continentContainer.show();
            $('#continent_select').val('').trigger('change');
            pendingDefaultCountry = true;

            updatePathDisplay();
            updateSubmitButtonState();
        }
    </script>

</body>
</html>