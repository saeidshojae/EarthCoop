@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center align-items-center g-4">
        <div class="col-md-8">
            <div class="card shadow-lg rounded">
                <div class="card-header bg-primary text-white text-center py-3">
                    <h4 class="mb-0">{{ __('مرحله ۳: اطلاعات مکانی') }}</h4>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('register.step3.post') }}" id="locationForm">
                        @csrf

                        <div class="row mb-3">
                            <label for="location" class="col-md-4 col-form-label text-md-end">{{ __('Location') }}</label>
                            <div class="col-md-6">
                                <select id="location" class="form-select @error('location') is-invalid @enderror" name="location" required>
                                    <option value="">{{ __('Select Continent') }}</option>
                                    @foreach($continents as $continent)
                                        <option value="continent-{{ $continent->id }}">{{ $continent->name_en ?? $continent->name_local ?? $continent->name }}</option>
                                    @endforeach
                                </select>
                                @error('location')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-0 mt-4">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary w-100 py-2" id="submitBtn" disabled>
                                    {{ __('تکمیل ثبت نام') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
console.log('JavaScript file loaded');

$(document).ready(function() {
    console.log('Document ready');

    $('#location').change(function() {
        const selectedValue = $(this).val();
        let url = '';
        let nextLabel = '';

        console.log('Selected value:', selectedValue);

        // بررسی انتخاب قاره
        if (selectedValue.startsWith('continent-')) {
            const continentID = selectedValue.split('-')[1];
            url = '/countries/' + continentID;
            nextLabel = '{{ __("Select Country") }}';
        } else if (selectedValue.startsWith('country-')) {
            const countryID = selectedValue.split('-')[1];
            url = '/provinces/' + countryID;
            nextLabel = '{{ __("Select Province") }}';
        } else if (selectedValue.startsWith('province-')) {
            const provinceID = selectedValue.split('-')[1];
            url = '/counties/' + provinceID;
            nextLabel = '{{ __("Select County") }}';
        } else if (selectedValue.startsWith('county-')) {
            const countyID = selectedValue.split('-')[1];
            url = '/districts/' + countyID;
            nextLabel = '{{ __("Select District") }}';
        } else if (selectedValue.startsWith('district-')) {
            const districtID = selectedValue.split('-')[1];
            url = '/settlements/' + districtID;
            nextLabel = '{{ __("Select Settlement") }}';
        } else if (selectedValue.startsWith('settlement-')) {
            const settlementID = selectedValue.split('-')[1];
            url = '/localities/' + settlementID;
            nextLabel = '{{ __("Select Locality") }}';
        } else if (selectedValue.startsWith('locality-')) {
            const localityID = selectedValue.split('-')[1];
            url = '/neighborhoods/' + localityID;
            nextLabel = '{{ __("Select Neighborhood") }}';
        } else if (selectedValue.startsWith('neighborhood-')) {
            const neighborhoodID = selectedValue.split('-')[1];
            url = '/streets/' + neighborhoodID;
            nextLabel = '{{ __("Select Street") }}';
        } else if (selectedValue.startsWith('street-')) {
            const streetID = selectedValue.split('-')[1];
            url = '/alleys/' + streetID;
            nextLabel = '{{ __("Select Alley") }}';
        }

        // ارسال درخواست AJAX
        if (url) {
            console.log('Sending AJAX request to:', url);
            $.ajax({
                url: url,
                type: "GET",
                dataType: "json",
                success: function(data) {
                    console.log('Received response:', data);
                    $('#location').empty();  // پاکسازی منو
                    const prefix = selectedValue.split('-')[0] + '-';
                    
                    // اضافه کردن گزینه‌های جدید به منو
                    $('#location').append(`
                        <option value="">${nextLabel}</option>
                    `);

                    $.each(data, function(key, value) {
                        $('#location').append(`
                            <option value="${prefix}${value.id}">
                                ${value.name_en || value.name_local || 'نامشخص'}
                            </option>
                        `);
                    });
                    
                    $('#submitBtn').prop('disabled', false);  // فعال کردن دکمه ارسال
                },
                error: function(xhr, status, error) {
                    console.error('AJAX request failed:', status, error);
                    $('#location').empty().append(`
                        <option value="">{{ __('خطا در دریافت داده') }}</option>
                    `);
                    $('#submitBtn').prop('disabled', true);
                }
            });
        } else {
            console.log('No URL to send AJAX request to.');
            $('#submitBtn').prop('disabled', true);  // غیرفعال کردن دکمه ارسال
        }
    });
});
</script>
@endsection
