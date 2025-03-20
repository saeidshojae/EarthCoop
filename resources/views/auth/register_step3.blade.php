@extends('layouts.app')

@section('content')
<div class="container mt-5" style="direction: rtl; text-align: right;">
  <h1 class="mb-4 text-center">مرحله ۳: انتخاب مکان</h1>
  <form action="{{ route('register.step3.process') }}" method="POST" novalidate>
    @csrf

    <!-- ردیف اول: قاره و کشور -->
    <div class="row">
      <div class="col-md-6 mb-3">
        <label for="continent_id" class="form-label">قاره:</label>
        <select name="continent_id" id="continent_id" class="form-control" required>
          <option value="">انتخاب کنید</option>
          @foreach($continents as $continent)
            <option value="{{ $continent->id }}">{{ $continent->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-6 mb-3">
        <label for="country_id" class="form-label">کشور:</label>
        <select name="country_id" id="country_id" class="form-control" disabled required>
          <option value="">انتخاب کنید</option>
        </select>
      </div>
    </div>

    <!-- ردیف دوم: استان و شهرستان -->
    <div class="row">
      <div class="col-md-6 mb-3">
        <label for="province_id" class="form-label">استان:</label>
        <select name="province_id" id="province_id" class="form-control" disabled required>
          <option value="">انتخاب کنید</option>
        </select>
      </div>
      <div class="col-md-6 mb-3">
        <label for="county_id" class="form-label">شهرستان:</label>
        <select name="county_id" id="county_id" class="form-control" disabled required>
          <option value="">انتخاب کنید</option>
        </select>
      </div>
    </div>

    <!-- ردیف سوم: بخش و شهر -->
    <div class="row">
      <div class="col-md-6 mb-3">
        <label for="section_id" class="form-label">بخش:</label>
        <select name="section_id" id="section_id" class="form-control" disabled required>
          <option value="">انتخاب کنید</option>
        </select>
      </div>
      <div class="col-md-6 mb-3">
        <label for="city_id" class="form-label">شهر:</label>
        <select name="city_id" id="city_id" class="form-control" disabled required>
          <option value="">انتخاب کنید</option>
        </select>
      </div>
    </div>

    <!-- ردیف چهارم: منطقه و محله -->
    <div class="row">
      <div class="col-md-6 mb-3">
        <label for="region_id" class="form-label">منطقه:</label>
        <select name="region_id" id="region_id" class="form-control" disabled required>
          <option value="">انتخاب کنید</option>
        </select>
      </div>
      <div class="col-md-6 mb-3">
        <label for="neighborhood_id" class="form-label">محله:</label>
        <select name="neighborhood_id" id="neighborhood_id" class="form-control" disabled required>
          <option value="">انتخاب کنید</option>
        </select>
      </div>
    </div>

    <!-- ردیف پنجم: خیابان و کوچه (اختیاری) -->
    <div class="row">
      <div class="col-md-6 mb-3">
        <label for="street_id" class="form-label">خیابان (اختیاری):</label>
        <select name="street_id" id="street_id" class="form-control" disabled>
          <option value="">انتخاب کنید</option>
        </select>
      </div>
      <div class="col-md-6 mb-3">
        <label for="alley_id" class="form-label">کوچه (اختیاری):</label>
        <select name="alley_id" id="alley_id" class="form-control" disabled>
          <option value="">انتخاب کنید</option>
        </select>
      </div>
    </div>

    <div class="text-center">
      <button type="submit" class="btn btn-success mt-3">تکمیل ثبت‌نام</button>
    </div>
  </form>
</div>

<!-- بارگذاری jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
  $(document).ready(function() {
    // تابع عمومی جهت بارگذاری مکان‌ها
    function loadLocation(level, parentId, targetSelectId) {
      var target = $('#' + targetSelectId);
      if (parentId) {
        $.ajax({
          url: '/api/locations',
          type: 'GET',
          dataType: 'json',
          data: { level: level, parent_id: parentId },
          success: function(data) {
            target.empty().append('<option value="">انتخاب کنید</option>');
            if (data && data.length > 0) {
              $.each(data, function(index, item) {
                target.append('<option value="' + item.id + '">' + item.name + '</option>');
              });
              target.prop('disabled', false);
            } else {
              target.prop('disabled', true);
            }
          },
          error: function(xhr) {
            console.error("خطا در دریافت داده برای سطح " + level + ": ", xhr.responseText);
          }
        });
      } else {
        target.empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
      }
    }

    // هر سطح با تغییر، سطوح پایین‌تر ریست و مجدداً لود می‌شود

    // سطح قاره -> کشور
    $('#continent_id').on('change', function(){
      var continentId = $(this).val();
      loadLocation('country', continentId, 'country_id');
      $('#province_id, #county_id, #section_id, #city_id, #region_id, #neighborhood_id, #street_id, #alley_id')
          .empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });

    // سطح کشور -> استان
    $('#country_id').on('change', function(){
      var countryId = $(this).val();
      loadLocation('province', countryId, 'province_id');
      $('#county_id, #section_id, #city_id, #region_id, #neighborhood_id, #street_id, #alley_id')
          .empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });

    // سطح استان -> شهرستان
    $('#province_id').on('change', function(){
      var provinceId = $(this).val();
      loadLocation('county', provinceId, 'county_id');
      $('#section_id, #city_id, #region_id, #neighborhood_id, #street_id, #alley_id')
          .empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });

    // سطح شهرستان -> بخش
    $('#county_id').on('change', function(){
      var countyId = $(this).val();
      loadLocation('section', countyId, 'section_id');
      $('#city_id, #region_id, #neighborhood_id, #street_id, #alley_id')
          .empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });

    // سطح بخش -> شهر
    $('#section_id').on('change', function(){
      var sectionId = $(this).val();
      loadLocation('city', sectionId, 'city_id');
      $('#region_id, #neighborhood_id, #street_id, #alley_id')
          .empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });

    // سطح شهر -> منطقه
    $('#city_id').on('change', function(){
      var cityId = $(this).val();
      loadLocation('region', cityId, 'region_id');
      $('#neighborhood_id, #street_id, #alley_id')
          .empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });

    // سطح منطقه -> محله
    $('#region_id').on('change', function(){
      var regionId = $(this).val();
      loadLocation('neighborhood', regionId, 'neighborhood_id');
      $('#street_id, #alley_id')
          .empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });

    // سطح محله -> خیابان
    $('#neighborhood_id').on('change', function(){
      var neighborhoodId = $(this).val();
      loadLocation('street', neighborhoodId, 'street_id');
      $('#alley_id')
          .empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });

    // سطح خیابان -> کوچه
    $('#street_id').on('change', function(){
      var streetId = $(this).val();
      loadLocation('alley', streetId, 'alley_id');
    });
  });
</script>
@endsection