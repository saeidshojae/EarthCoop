@extends('layouts.app')

@section('content')
<div class="container mt-5" style="direction: rtl; text-align: right;">
    <h1 class="mb-4 text-center">ویرایش اطلاعات پروفایل</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ route('profile.update.modifiable') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <!-- بخش انتخاب صنف (زمینه فعالیت صنفی) با سه سطح -->
        <div class="mb-4">
            <label class="form-label">انتخاب صنف:</label>
            <div>
                <!-- سطح ۱ برای صنف -->
                <select id="occupational_level1" class="form-control mb-2">
                    <option value="">انتخاب کنید (سطح ۱)</option>
                    @foreach($occupationalFields as $field)
                        @if(is_null($field->parent_id))
                            <option value="{{ $field->id }}">{{ $field->name }}</option>
                        @endif
                    @endforeach
                </select>
                <!-- سطح ۲ برای صنف -->
                <select id="occupational_level2" class="form-control mb-2" disabled>
                    <option value="">انتخاب کنید (سطح ۲)</option>
                </select>
                <!-- سطح ۳ برای صنف -->
                <select id="occupational_level3" class="form-control mb-2" disabled>
                    <option value="">انتخاب کنید (سطح ۳)</option>
                </select>
                <!-- نمایش انتخاب‌های نهایی صنف -->
                <div id="selected_occupational" class="mt-3">
                    @foreach($user->occupationalFields as $selectedField)
                        <span class="badge bg-primary me-1 my-1" data-id="{{ $selectedField->id }}">
                            {{ $selectedField->name }}
                            <button type="button" class="btn btn-sm btn-danger remove-selection" data-id="{{ $selectedField->id }}">&times;</button>
                            <input type="hidden" name="occupational_fields[]" value="{{ $selectedField->id }}">
                        </span>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- بخش انتخاب تخصص (زمینه تجربی و تخصصی) با سه سطح -->
        <div class="mb-4">
            <label class="form-label">انتخاب تخصص:</label>
            <div>
                <!-- سطح ۱ برای تخصص -->
                <select id="experience_level1" class="form-control mb-2">
                    <option value="">انتخاب کنید (سطح ۱)</option>
                    @foreach($experienceFields as $field)
                        @if(is_null($field->parent_id))
                            <option value="{{ $field->id }}">{{ $field->name }}</option>
                        @endif
                    @endforeach
                </select>
                <!-- سطح ۲ برای تخصص -->
                <select id="experience_level2" class="form-control mb-2" disabled>
                    <option value="">انتخاب کنید (سطح ۲)</option>
                </select>
                <!-- سطح ۳ برای تخصص -->
                <select id="experience_level3" class="form-control mb-2" disabled>
                    <option value="">انتخاب کنید (سطح ۳)</option>
                </select>
                <!-- نمایش انتخاب‌های نهایی تخصص -->
                <div id="selected_experience" class="mt-3">
                    @foreach($user->experienceFields as $selectedField)
                        <span class="badge bg-success me-1 my-1" data-id="{{ $selectedField->id }}">
                            {{ $selectedField->name }}
                            <button type="button" class="btn btn-sm btn-danger remove-selection" data-id="{{ $selectedField->id }}">&times;</button>
                            <input type="hidden" name="experience_fields[]" value="{{ $selectedField->id }}">
                        </span>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- بخش انتخاب مکان: از قاره تا کوچه -->
        <h4 class="mt-4">انتخاب مکان</h4>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="continent_id" class="form-label">قاره:</label>
                <select name="continent_id" id="continent_id" class="form-control" required>
                    <option value="">انتخاب کنید (قاره)</option>
                    @foreach($continents as $continent)
                        <option value="{{ $continent->id }}"
                            @if($user->locations->contains('id', $continent->id)) selected @endif>
                            {{ $continent->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="country_id" class="form-label">کشور:</label>
                <select name="country_id" id="country_id" class="form-control" required>
                    <option value="">انتخاب کنید (کشور)</option>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="province_id" class="form-label">استان:</label>
                <select name="province_id" id="province_id" class="form-control" required>
                    <option value="">انتخاب کنید (استان)</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="county_id" class="form-label">شهرستان:</label>
                <select name="county_id" id="county_id" class="form-control" required>
                    <option value="">انتخاب کنید (شهرستان)</option>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="section_id" class="form-label">بخش:</label>
                <select name="section_id" id="section_id" class="form-control" required>
                    <option value="">انتخاب کنید (بخش)</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="city_id" class="form-label">شهر:</label>
                <select name="city_id" id="city_id" class="form-control" required>
                    <option value="">انتخاب کنید (شهر)</option>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="region_id" class="form-label">منطقه:</label>
                <select name="region_id" id="region_id" class="form-control" required>
                    <option value="">انتخاب کنید (منطقه)</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="neighborhood_id" class="form-label">محله:</label>
                <select name="neighborhood_id" id="neighborhood_id" class="form-control" required>
                    <option value="">انتخاب کنید (محله)</option>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="street_id" class="form-label">خیابان (اختیاری):</label>
                <select name="street_id" id="street_id" class="form-control">
                    <option value="">انتخاب کنید (خیابان)</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="alley_id" class="form-label">کوچه (اختیاری):</label>
                <select name="alley_id" id="alley_id" class="form-control">
                    <option value="">انتخاب کنید (کوچه)</option>
                </select>
            </div>
        </div>

        <!-- آپلود عکس پروفایل -->
        <div class="mb-4">
            <label for="avatar" class="form-label">عکس پروفایل:</label>
            <input type="file" name="avatar" id="avatar" class="form-control">
            @if($user->avatar)
                <img src="{{ asset($user->avatar) }}" alt="تصویر پروفایل" class="rounded-circle mt-3" width="150">
            @endif
        </div>

        <!-- دکمه‌های ارسال فرم -->
        <div class="text-center mt-4">
            <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
            <a href="{{ route('profile.show') }}" class="btn btn-secondary">لغو</a>
        </div>
    </form>
</div>

<!-- jQuery is already loaded above in page? But we load again just in case -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // تابع برای دریافت گزینه‌های حوزه (صنف/تخصص) از API
    function fetchFieldOptions(parentId, targetSelectId) {
        $.ajax({
            url: '/api/fields',
            type: 'GET',
            data: { parent_id: parentId },
            success: function(data) {
                let target = $('#' + targetSelectId);
                target.empty().append('<option value="">انتخاب کنید</option>');
                if (data && data.length > 0) {
                    $.each(data, function(index, item) {
                        target.append(`<option value="${item.id}">${item.name}</option>`);
                    });
                    target.prop('disabled', false);
                } else {
                    target.prop('disabled', true);
                }
            },
            error: function() {
                console.error("مشکل دریافت گزینه‌های حوزه.");
            }
        });
    }

    // تابع برای مدیریت وابستگی سلسله‌مراتبی حوزه (صنف و تخصص)
    function handleFieldDependency(level1Selector, level2Selector, level3Selector, containerSelector, inputName) {
        $(level1Selector).on('change', function() {
            let parentId = $(this).val();
            if (parentId) {
                fetchFieldOptions(parentId, level2Selector.substring(1)); // حذف '#' از شناسه
            }
            $(level3Selector).empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
        });
        $(level2Selector).on('change', function() {
            let parentId = $(this).val();
            if (parentId) {
                fetchFieldOptions(parentId, level3Selector.substring(1));
            }
        });
        $(level3Selector).on('change', function() {
            let selectedId = $(this).val();
            let selectedText = $(this).find('option:selected').text();
            if (selectedId) {
                let container = $(containerSelector);
                if (container.find(`[data-id="${selectedId}"]`).length === 0) {
                    container.append(`
                        <span class="badge bg-secondary me-1 my-1" data-id="${selectedId}">
                            ${selectedText}
                            <button type="button" class="btn btn-sm btn-danger remove-selection" data-id="${selectedId}">&times;</button>
                            <input type="hidden" name="${inputName}[]" value="${selectedId}">
                        </span>
                    `);
                }
            }
        });
        $(document).on('click', containerSelector + " .remove-selection", function() {
            $(this).parent().remove();
        });
    }

    // مدیریت حوزه صنف
    handleFieldDependency('#occupational_level1', '#occupational_level2', '#occupational_level3', '#selected_occupational', 'occupational_fields');
    // مدیریت حوزه تخصص
    handleFieldDependency('#experience_level1', '#experience_level2', '#experience_level3', '#selected_experience', 'experience_fields');

    // تابع برای دریافت گزینه‌های مکان از API
    function fetchLocationOptions(level, parentId, targetSelectId) {
        $.ajax({
            url: '/api/locations',
            type: 'GET',
            data: { level: level, parent_id: parentId },
            success: function(data) {
                let select = $('#' + targetSelectId);
                select.empty().append('<option value="">انتخاب کنید</option>');
                if (data && data.length > 0) {
                    $.each(data, function(index, item) {
                        select.append(`<option value="${item.id}">${item.name}</option>`);
                    });
                    select.prop('disabled', false);
                } else {
                    select.prop('disabled', true);
                }
            },
            error: function() {
                console.error("مشکل دریافت گزینه‌های مکان برای سطح " + level);
            }
        });
    }

    // تنظیم وابستگی‌های سلسله‌مراتبی مکان
    $('#continent_id').on('change', function() {
        let continentId = $(this).val();
        if(continentId) {
            fetchLocationOptions('country', continentId, 'country_id');
        }
        $('#province_id, #county_id, #section_id, #city_id, #region_id, #neighborhood_id, #street_id, #alley_id')
            .empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });

    $('#country_id').on('change', function() {
        let countryId = $(this).val();
        if(countryId) {
            fetchLocationOptions('province', countryId, 'province_id');
        }
        $('#county_id, #section_id, #city_id, #region_id, #neighborhood_id, #street_id, #alley_id')
            .empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });

    $('#province_id').on('change', function() {
        let provinceId = $(this).val();
        if(provinceId) {
            fetchLocationOptions('county', provinceId, 'county_id');
        }
        $('#section_id, #city_id, #region_id, #neighborhood_id, #street_id, #alley_id')
            .empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });

    $('#county_id').on('change', function() {
        let countyId = $(this).val();
        if(countyId) {
            fetchLocationOptions('section', countyId, 'section_id');
        }
        $('#city_id, #region_id, #neighborhood_id, #street_id, #alley_id')
            .empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });

    $('#section_id').on('change', function() {
        let sectionId = $(this).val();
        if(sectionId) {
            fetchLocationOptions('city', sectionId, 'city_id');
        }
        $('#region_id, #neighborhood_id, #street_id, #alley_id')
            .empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });

    $('#city_id').on('change', function() {
        let cityId = $(this).val();
        if(cityId) {
            fetchLocationOptions('region', cityId, 'region_id');
        }
        $('#neighborhood_id, #street_id, #alley_id')
            .empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });

    $('#region_id').on('change', function() {
        let regionId = $(this).val();
        if(regionId) {
            fetchLocationOptions('neighborhood', regionId, 'neighborhood_id');
        }
        $('#street_id, #alley_id')
            .empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });

    $('#neighborhood_id').on('change', function() {
        let neighborhoodId = $(this).val();
        if(neighborhoodId) {
            fetchLocationOptions('street', neighborhoodId, 'street_id');
            fetchLocationOptions('alley', neighborhoodId, 'alley_id');
        }
    });
});
</script>
@endsection
