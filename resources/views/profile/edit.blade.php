@extends('layouts.app')

@section('title', 'ویرایش پروفایل')

@section('content')
<div class="container mt-5" style="direction: rtl; text-align: right;">
    <h1 class="mb-4 text-center">ویرایش اطلاعات پروفایل</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ route('profile.update.modifiable') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <!-- بخش انتخاب صنف (زمینه فعالیت) با سه سطح سلسله‌مراتبی -->
        <div class="mb-4">
            <label class="form-label">انتخاب صنف:</label>
            <div>
                <!-- سطح 1 -->
                <select id="occupational_level1" class="form-control mb-2">
                    <option value="">انتخاب کنید (سطح ۱)</option>
                    @foreach($occupationalFields as $field)
                        @if(is_null($field->parent_id))
                            <option value="{{ $field->id }}">{{ $field->name }}</option>
                        @endif
                    @endforeach
                </select>
                <!-- سطح 2 -->
                <select id="occupational_level2" class="form-control mb-2" disabled>
                    <option value="">انتخاب کنید (سطح ۲)</option>
                </select>
                <!-- سطح 3 -->
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

        <!-- بخش انتخاب تخصص (زمینه تجربی و تخصصی) با سه سطح سلسله‌مراتبی -->
        <div class="mb-4">
            <label class="form-label">انتخاب تخصص:</label>
            <div>
                <!-- سطح 1 -->
                <select id="experience_level1" class="form-control mb-2">
                    <option value="">انتخاب کنید (سطح ۱)</option>
                    @foreach($experienceFields as $field)
                        @if(is_null($field->parent_id))
                            <option value="{{ $field->id }}">{{ $field->name }}</option>
                        @endif
                    @endforeach
                </select>
                <!-- سطح 2 -->
                <select id="experience_level2" class="form-control mb-2" disabled>
                    <option value="">انتخاب کنید (سطح ۲)</option>
                </select>
                <!-- سطح 3 -->
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
        <!-- ردیف 1: قاره - کشور -->
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
        <!-- ردیف 2: استان - شهرستان -->
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
        <!-- ردیف 3: بخش - شهر -->
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
        <!-- ردیف 4: منطقه - محله -->
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
        <!-- ردیف 5: خیابان - کوچه (اختیاری) -->
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
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    console.log('jQuery version:', $.fn.jquery); // چاپ نسخه jQuery

    // تنظیم CSRF token برای تمام درخواست‌های AJAX
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // تست اتصال به API
    $.ajax({
        url: '/api/test',
        type: 'GET',
        success: function(response) {
            console.log('API Test:', response);
        },
        error: function(xhr, status, error) {
            console.error('API Test Error:', error);
            console.log('Status:', xhr.status);
            console.log('Response:', xhr.responseText);
            console.log('Full URL:', window.location.origin + '/api/test');
        }
    });

    // تابع دریافت گزینه‌های حوزه صنف از API
    function fetchOccupationalFields(parentId, targetSelectId) {
        if (!parentId) {
            console.error('شناسه والد برای صنف ارسال نشده است');
            return;
        }

        console.log('Fetching occupational fields for parent ID:', parentId);

        $.ajax({
            url: window.location.origin + '/api/occupational-fields',
            type: 'GET',
            data: { parent_id: parentId },
            success: function(data) {
                console.log('Received occupational fields:', data);
                let targetSelect = $('#' + targetSelectId);
                targetSelect.empty().append('<option value="">انتخاب کنید</option>');
                if (data && data.length > 0) {
                    $.each(data, function(index, item) {
                        targetSelect.append(`<option value="${item.id}">${item.name}</option>`);
                    });
                    targetSelect.prop('disabled', false);
                } else {
                    targetSelect.prop('disabled', true);
                }
            },
            error: function(xhr, status, error) {
                console.error('مشکل دریافت گزینه‌های صنف:', error);
                console.log('Status:', xhr.status);
                console.log('Response:', xhr.responseText);
                console.log('Full URL:', window.location.origin + '/api/occupational-fields');
                alert('خطا در دریافت اطلاعات صنف. لطفاً صفحه را رفرش کنید.');
            }
        });
    }

    // تابع دریافت گزینه‌های حوزه تخصص از API
    function fetchExperienceFields(parentId, targetSelectId) {
        if (!parentId) {
            console.error('شناسه والد برای تخصص ارسال نشده است');
            return;
        }

        console.log('Fetching experience fields for parent ID:', parentId);

        $.ajax({
            url: window.location.origin + '/api/experience-fields',
            type: 'GET',
            data: { parent_id: parentId },
            success: function(data) {
                console.log('Received experience fields:', data);
                let targetSelect = $('#' + targetSelectId);
                targetSelect.empty().append('<option value="">انتخاب کنید</option>');
                if (data && data.length > 0) {
                    $.each(data, function(index, item) {
                        targetSelect.append(`<option value="${item.id}">${item.name}</option>`);
                    });
                    targetSelect.prop('disabled', false);
                } else {
                    targetSelect.prop('disabled', true);
                }
            },
            error: function(xhr, status, error) {
                console.error('مشکل دریافت گزینه‌های تخصص:', error);
                console.log('Status:', xhr.status);
                console.log('Response:', xhr.responseText);
                console.log('Full URL:', window.location.origin + '/api/experience-fields');
                alert('خطا در دریافت اطلاعات تخصص. لطفاً صفحه را رفرش کنید.');
            }
        });
    }

    // تابع دریافت گزینه‌های مکان از API
    function fetchLocationOptions(parentId, targetSelectId) {
        if (!parentId) {
            console.error('شناسه والد برای مکان ارسال نشده است');
            return;
        }

        console.log('Fetching location options for parent ID:', parentId);

        $.ajax({
            url: window.location.origin + '/api/locations',
            type: 'GET',
            data: { parent_id: parentId },
            success: function(data) {
                console.log('Received location options:', data);
                let targetSelect = $('#' + targetSelectId);
                targetSelect.empty().append('<option value="">انتخاب کنید</option>');
                if (data && data.length > 0) {
                    $.each(data, function(index, item) {
                        let selected = '';
                        if (targetSelect.data('selected') == item.id) {
                            selected = 'selected';
                        }
                        targetSelect.append(`<option value="${item.id}" ${selected}>${item.name}</option>`);
                    });
                    targetSelect.prop('disabled', false);
                } else {
                    targetSelect.prop('disabled', true);
                }
            },
            error: function(xhr, status, error) {
                console.error('مشکل دریافت گزینه‌های مکان:', error);
                console.log('Status:', xhr.status);
                console.log('Response:', xhr.responseText);
                console.log('Full URL:', window.location.origin + '/api/locations');
                alert('خطا در دریافت اطلاعات مکان. لطفاً صفحه را رفرش کنید.');
            }
        });
    }

    // مدیریت وابستگی صنف
    $('#occupational_level1').on('change', function() {
        let parentId = $(this).val();
        if (parentId) {
            fetchOccupationalFields(parentId, 'occupational_level2');
        }
        $('#occupational_level2').empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
        $('#occupational_level3').empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });

    $('#occupational_level2').on('change', function() {
        let parentId = $(this).val();
        if (parentId) {
            fetchOccupationalFields(parentId, 'occupational_level3');
        }
        $('#occupational_level3').empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });

    $('#occupational_level3').on('change', function() {
        let selectedId = $(this).val();
        let selectedText = $(this).find('option:selected').text();
        if (selectedId) {
            let container = $('#selected_occupational');
            if (container.find(`[data-id="${selectedId}"]`).length === 0) {
                container.append(`
                    <span class="badge bg-primary me-1 my-1" data-id="${selectedId}">
                        ${selectedText}
                        <button type="button" class="btn btn-sm btn-danger remove-selection" data-id="${selectedId}">&times;</button>
                        <input type="hidden" name="occupational_fields[]" value="${selectedId}">
                    </span>
                `);
            }
        }
    });

    // مدیریت وابستگی تخصص
    $('#experience_level1').on('change', function() {
        let parentId = $(this).val();
        if (parentId) {
            fetchExperienceFields(parentId, 'experience_level2');
        }
        $('#experience_level2').empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
        $('#experience_level3').empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });

    $('#experience_level2').on('change', function() {
        let parentId = $(this).val();
        if (parentId) {
            fetchExperienceFields(parentId, 'experience_level3');
        }
        $('#experience_level3').empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });

    $('#experience_level3').on('change', function() {
        let selectedId = $(this).val();
        let selectedText = $(this).find('option:selected').text();
        if (selectedId) {
            let container = $('#selected_experience');
            if (container.find(`[data-id="${selectedId}"]`).length === 0) {
                container.append(`
                    <span class="badge bg-success me-1 my-1" data-id="${selectedId}">
                        ${selectedText}
                        <button type="button" class="btn btn-sm btn-danger remove-selection" data-id="${selectedId}">&times;</button>
                        <input type="hidden" name="experience_fields[]" value="${selectedId}">
                    </span>
                `);
            }
        }
    });

    // حذف انتخاب‌ها
    $(document).on('click', '.remove-selection', function() {
        $(this).parent().remove();
    });

    // مدیریت وابستگی مکان‌ها
    $('#continent_id').on('change', function() {
        let parentId = $(this).val();
        if (parentId) {
            fetchLocationOptions(parentId, 'country_id');
        }
        $('#country_id, #province_id, #county_id, #section_id, #city_id, #region_id, #neighborhood_id, #street_id, #alley_id')
            .empty()
            .append('<option value="">انتخاب کنید</option>')
            .prop('disabled', true);
    });

    $('#country_id').on('change', function() {
        let parentId = $(this).val();
        if (parentId) {
            fetchLocationOptions(parentId, 'province_id');
        }
        $('#province_id, #county_id, #section_id, #city_id, #region_id, #neighborhood_id, #street_id, #alley_id')
            .empty()
            .append('<option value="">انتخاب کنید</option>')
            .prop('disabled', true);
    });

    $('#province_id').on('change', function() {
        let parentId = $(this).val();
        if (parentId) {
            fetchLocationOptions(parentId, 'county_id');
        }
        $('#county_id, #section_id, #city_id, #region_id, #neighborhood_id, #street_id, #alley_id')
            .empty()
            .append('<option value="">انتخاب کنید</option>')
            .prop('disabled', true);
    });

    $('#county_id').on('change', function() {
        let parentId = $(this).val();
        if (parentId) {
            fetchLocationOptions(parentId, 'section_id');
        }
        $('#section_id, #city_id, #region_id, #neighborhood_id, #street_id, #alley_id')
            .empty()
            .append('<option value="">انتخاب کنید</option>')
            .prop('disabled', true);
    });

    $('#section_id').on('change', function() {
        let parentId = $(this).val();
        if (parentId) {
            fetchLocationOptions(parentId, 'city_id');
        }
        $('#city_id, #region_id, #neighborhood_id, #street_id, #alley_id')
            .empty()
            .append('<option value="">انتخاب کنید</option>')
            .prop('disabled', true);
    });

    $('#city_id').on('change', function() {
        let parentId = $(this).val();
        if (parentId) {
            fetchLocationOptions(parentId, 'region_id');
        }
        $('#region_id, #neighborhood_id, #street_id, #alley_id')
            .empty()
            .append('<option value="">انتخاب کنید</option>')
            .prop('disabled', true);
    });

    $('#region_id').on('change', function() {
        let parentId = $(this).val();
        if (parentId) {
            fetchLocationOptions(parentId, 'neighborhood_id');
        }
        $('#neighborhood_id, #street_id, #alley_id')
            .empty()
            .append('<option value="">انتخاب کنید</option>')
            .prop('disabled', true);
    });

    $('#neighborhood_id').on('change', function() {
        let parentId = $(this).val();
        if (parentId) {
            fetchLocationOptions(parentId, 'street_id');
        }
        $('#street_id, #alley_id')
            .empty()
            .append('<option value="">انتخاب کنید</option>')
            .prop('disabled', true);
    });

    $('#street_id').on('change', function() {
        let parentId = $(this).val();
        if (parentId) {
            fetchLocationOptions(parentId, 'alley_id');
        }
        $('#alley_id')
            .empty()
            .append('<option value="">انتخاب کنید</option>')
            .prop('disabled', true);
    });

    // لود کردن مقادیر پیش‌فرض برای مکان‌ها
    if ($('#continent_id').val()) {
        let continentId = $('#continent_id').val();
        fetchLocationOptions(continentId, 'country_id');
    }
});
</script>
@endpush


