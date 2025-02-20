@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-lg rounded">
                <div class="card-header bg-primary text-white text-center">
                    <h4>{{ __('Step 3: Location Information') }}</h4>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('register.step3.post') }}">
                        @csrf

                        @php
                            $fields = [
                                'continent' => $continents,
                                'country' => $countries,
                                'province' => $provinces,
                                'county' => $counties,
                                'district' => $districts,
                                'settlement' => $settlements,
                                'locality' => $localities,
                                'neighborhood' => $neighborhoods,
                                'street' => $streets,
                                'alley' => $alleys
                            ];
                        @endphp

                        @foreach ($fields as $field => $items)
                        <div class="row mb-3">
                            <label for="{{ $field }}" class="col-md-4 col-form-label text-md-end">{{ __(ucwords($field)) }}</label>
                            <div class="col-md-6">
                                <select id="{{ $field }}" class="form-control @error($field) is-invalid @enderror" name="{{ $field }}" required>
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach($items as $item)
                                        <option value="{{ $item->id }}" {{ old($field) == $item->id ? 'selected' : '' }}>
                                            {{ $item->name_en ?? $item->name_local ?? $item->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error($field)
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        @endforeach

                        <div class="row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Complete Registration') }}
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
<script>
    $(document).ready(function() {
        // برای هر dropdown که به هم مرتبط هستند، منوهای بعدی را مخفی می‌کنیم
        const fields = [
            '#country', '#province', '#county', '#district', '#settlement', '#locality', '#neighborhood', '#street', '#alley'
        ];

        // پنهان کردن تمام منوها به جز اولین منو
        fields.forEach(function(field, index) {
            if (index > 0) {
                $(field).parent().parent().hide();
            }
        });

        // تابع بارگذاری گزینه‌ها به طور دینامیک
        function loadOptions(url, targetId) {
            console.log('Request URL:', url);  // اضافه کردن log برای آدرس درخواست
            $.ajax({
                url: url,
                type: "GET",
                dataType: "json",
                success: function(data) {
                    console.log('Response Data:', data);  // اضافه کردن log برای داده‌های دریافتی
                    let target = $(targetId);
                    target.empty();
                    target.append('<option value="">{{ __('Select') }}</option>');
                    if (data && data.length > 0) {
                        $.each(data, function(key, value) {
                            target.append('<option value="' + value.id + '">' + value.name + '</option>');
                        });
                        $(targetId).parent().parent().show();  // نمایان کردن منو
                    } else {
                        target.append('<option value="">{{ __('No data available') }}</option>');
                    }
                },
                error: function() {
                    $(targetId).empty().append('<option value="">{{ __('Error loading data') }}</option>');
                    console.log('Error loading data');  // اضافه کردن log برای خطا
                }
            });
        }

        // تغییرات در انتخاب هر dropdown را با استفاده از این تابع کنترل می‌کنیم
        $('#continent').change(function() {
            var continentID = $(this).val();
            console.log('Selected Continent ID:', continentID);  // اضافه کردن log برای شناسه انتخاب شده
            if (continentID) {
                loadOptions('/countries/' + continentID, '#country');
            } else {
                $('#country').empty().append('<option value="">{{ __('Select') }}</option>');
                $(fields[1]).parent().parent().hide();
            }
        });

        $('#country').change(function() {
            var countryID = $(this).val();
            console.log('Selected Country ID:', countryID);  // اضافه کردن log برای شناسه انتخاب شده
            if (countryID) {
                loadOptions('/provinces/' + countryID, '#province');
            } else {
                $('#province').empty().append('<option value="">{{ __('Select') }}</option>');
                $(fields[2]).parent().parent().hide();
            }
        });

        $('#province').change(function() {
            var provinceID = $(this).val();
            console.log('Selected Province ID:', provinceID);  // اضافه کردن log برای شناسه انتخاب شده
            if (provinceID) {
                loadOptions('/counties/' + provinceID, '#county');
            } else {
                $('#county').empty().append('<option value="">{{ __('Select') }}</option>');
                $(fields[3]).parent().parent().hide();
            }
        });

        $('#county').change(function() {
            var countyID = $(this).val();
            console.log('Selected County ID:', countyID);  // اضافه کردن log برای شناسه انتخاب شده
            if (countyID) {
                loadOptions('/districts/' + countyID, '#district');
            } else {
                $('#district').empty().append('<option value="">{{ __('Select') }}</option>');
                $(fields[4]).parent().parent().hide();
            }
        });

        $('#district').change(function() {
            var districtID = $(this).val();
            console.log('Selected District ID:', districtID);  // اضافه کردن log برای شناسه انتخاب شده
            if (districtID) {
                loadOptions('/settlements/' + districtID, '#settlement');
            } else {
                $('#settlement').empty().append('<option value="">{{ __('Select') }}</option>');
                $(fields[5]).parent().parent().hide();
            }
        });

        $('#settlement').change(function() {
            var settlementID = $(this).val();
            console.log('Selected Settlement ID:', settlementID);  // اضافه کردن log برای شناسه انتخاب شده
            if (settlementID) {
                loadOptions('/localities/' + settlementID, '#locality');
            } else {
                $('#locality').empty().append('<option value="">{{ __('Select') }}</option>');
                $(fields[6]).parent().parent().hide();
            }
        });

        $('#locality').change(function() {
            var localityID = $(this).val();
            console.log('Selected Locality ID:', localityID);  // اضافه کردن log برای شناسه انتخاب شده
            if (localityID) {
                loadOptions('/neighborhoods/' + localityID, '#neighborhood');
            } else {
                $('#neighborhood').empty().append('<option value="">{{ __('Select') }}</option>');
                $(fields[7]).parent().parent().hide();
            }
        });

        $('#neighborhood').change(function() {
            var neighborhoodID = $(this).val();
            console.log('Selected Neighborhood ID:', neighborhoodID);  // اضافه کردن log برای شناسه انتخاب شده
            if (neighborhoodID) {
                loadOptions('/streets/' + neighborhoodID, '#street');
            } else {
                $('#street').empty().append('<option value="">{{ __('Select') }}</option>');
                $(fields[8]).parent().parent().hide();
            }
        });

        $('#street').change(function() {
            var streetID = $(this).val();
            console.log('Selected Street ID:', streetID);  // اضافه کردن log برای شناسه انتخاب شده
            if (streetID) {
                loadOptions('/alleys/' + streetID, '#alley');
            } else {
                $('#alley').empty().append('<option value="">{{ __('Select') }}</option>');
                $(fields[9]).parent().parent().hide();
            }
        });
    });
</script>
@endsection
