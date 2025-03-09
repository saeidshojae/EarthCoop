<!DOCTYPE html>
<html lang="fa">
<head>
  <meta charset="UTF-8">
  <title>مرحله ۳: انتخاب مکان</title>
  <link href="{{ asset('css/app.css') }}" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
</head>
<body>
  <div class="container mt-5">
    <h1 class="mb-4">مرحله ۳: انتخاب مکان</h1>
    <form action="{{ route('register.step3.process') }}" method="POST">
      @csrf
      <div class="form-group">
        <label for="continent_id">قاره:</label>
        <select name="continent_id" id="continent_id" class="form-control" required>
          <option value="">انتخاب کنید</option>
          @foreach($continents as $continent)
            <option value="{{ $continent->id }}">{{ $continent->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="form-group">
        <label for="country_id">کشور:</label>
        <select name="country_id" id="country_id" class="form-control" required>
          <option value="">انتخاب کنید</option>
        </select>
      </div>
      <div class="form-group">
        <label for="province_id">استان:</label>
        <select name="province_id" id="province_id" class="form-control" required>
          <option value="">انتخاب کنید</option>
        </select>
      </div>
      <div class="form-group">
        <label for="county_id">شهرستان:</label>
        <select name="county_id" id="county_id" class="form-control" required>
          <option value="">انتخاب کنید</option>
        </select>
      </div>
      <div class="form-group">
        <label for="section_id">بخش:</label>
        <select name="section_id" id="section_id" class="form-control" required>
          <option value="">انتخاب کنید</option>
        </select>
      </div>
      <div class="form-group">
        <label for="city_id">شهر:</label>
        <select name="city_id" id="city_id" class="form-control" required>
          <option value="">انتخاب کنید</option>
        </select>
      </div>
      <div class="form-group">
        <label for="region_id">منطقه:</label>
        <select name="region_id" id="region_id" class="form-control" required>
          <option value="">انتخاب کنید</option>
        </select>
      </div>
      <div class="form-group">
        <label for="neighborhood_id">محله:</label>
        <select name="neighborhood_id" id="neighborhood_id" class="form-control" required>
          <option value="">انتخاب کنید</option>
        </select>
      </div>
      <div class="form-group">
        <label for="street_id">خیابان (اختیاری):</label>
        <select name="street_id" id="street_id" class="form-control">
          <option value="">انتخاب کنید</option>
        </select>
      </div>
      <div class="form-group">
        <label for="alley_id">کوچه (اختیاری):</label>
        <select name="alley_id" id="alley_id" class="form-control">
          <option value="">انتخاب کنید</option>
        </select>
      </div>
      <button type="submit" class="btn btn-success mt-3">تکمیل ثبت‌نام</button>
    </form>
  </div>

  <script type="text/javascript">
    $(document).ready(function() {
      $('#continent_id').on('change', function() {
        var continentID = $(this).val();
        if(continentID) {
          $.ajax({
            url: '/api/locations',
            type: 'GET',
            data: { level: 'country', parent_id: continentID },
            success: function(data) {
              $('#country_id').empty().append('<option value="">انتخاب کنید</option>');
              $.each(data, function(key, value) {
                $('#country_id').append('<option value="'+ value.id +'">'+ value.name +'</option>');
              });
            }
          });
        } else {
          $('#country_id').empty().append('<option value="">انتخاب کنید</option>');
        }
      });
      
      $('#country_id').on('change', function() {
        var countryID = $(this).val();
        if(countryID) {
          $.ajax({
            url: '/api/locations',
            type: 'GET',
            data: { level: 'province', parent_id: countryID },
            success: function(data) {
              $('#province_id').empty().append('<option value="">انتخاب کنید</option>');
              $.each(data, function(key, value) {
                $('#province_id').append('<option value="'+ value.id +'">'+ value.name +'</option>');
              });
            }
          });
        } else {
          $('#province_id').empty().append('<option value="">انتخاب کنید</option>');
        }
      });

      $('#province_id').on('change', function() {
        var provinceID = $(this).val();
        if(provinceID) {
          $.ajax({
            url: '/api/locations',
            type: 'GET',
            data: { level: 'county', parent_id: provinceID },
            success: function(data) {
              $('#county_id').empty().append('<option value="">انتخاب کنید</option>');
              $.each(data, function(key, value) {
                $('#county_id').append('<option value="'+ value.id +'">'+ value.name +'</option>');
              });
            }
          });
        } else {
          $('#county_id').empty().append('<option value="">انتخاب کنید</option>');
        }
      });

      $('#county_id').on('change', function() {
        var countyID = $(this).val();
        if(countyID) {
          $.ajax({
            url: '/api/locations',
            type: 'GET',
            data: { level: 'section', parent_id: countyID },
            success: function(data) {
              $('#section_id').empty().append('<option value="">انتخاب کنید</option>');
              $.each(data, function(key, value) {
                $('#section_id').append('<option value="'+ value.id +'">'+ value.name +'</option>');
              });
            }
          });
        } else {
          $('#section_id').empty().append('<option value="">انتخاب کنید</option>');
        }
      });

      $('#section_id').on('change', function() {
        var sectionID = $(this).val();
        if(sectionID) {
          $.ajax({
            url: '/api/locations',
            type: 'GET',
            data: { level: 'city', parent_id: sectionID },
            success: function(data) {
              $('#city_id').empty().append('<option value="">انتخاب کنید</option>');
              $.each(data, function(key, value) {
                $('#city_id').append('<option value="'+ value.id +'">'+ value.name +'</option>');
              });
            }
          });
        } else {
          $('#city_id').empty().append('<option value="">انتخاب کنید</option>');
        }
      });

      $('#city_id').on('change', function() {
        var cityID = $(this).val();
        if(cityID) {
          $.ajax({
            url: '/api/locations',
            type: 'GET',
            data: { level: 'region', parent_id: cityID },
            success: function(data) {
              $('#region_id').empty().append('<option value="">انتخاب کنید</option>');
              $.each(data, function(key, value) {
                $('#region_id').append('<option value="'+ value.id +'">'+ value.name +'</option>');
              });
            }
          });
        } else {
          $('#region_id').empty().append('<option value="">انتخاب کنید</option>');
        }
      });

      $('#region_id').on('change', function() {
        var regionID = $(this).val();
        if(regionID) {
          $.ajax({
            url: '/api/locations',
            type: 'GET',
            data: { level: 'neighborhood', parent_id: regionID },
            success: function(data) {
              $('#neighborhood_id').empty().append('<option value="">انتخاب کنید</option>');
              $.each(data, function(key, value) {
                $('#neighborhood_id').append('<option value="'+ value.id +'">'+ value.name +'</option>');
              });
            }
          });
        } else {
          $('#neighborhood_id').empty().append('<option value="">انتخاب کنید</option>');
        }
      });

      $('#neighborhood_id').on('change', function() {
        var neighborhoodID = $(this).val();
        if(neighborhoodID) {
          $.ajax({
            url: '/api/locations',
            type: 'GET',
            data: { level: 'street', parent_id: neighborhoodID },
            success: function(data) {
              $('#street_id').empty().append('<option value="">انتخاب کنید</option>');
              $.each(data, function(key, value) {
                $('#street_id').append('<option value="'+ value.id +'">'+ value.name +'</option>');
              });
            }
          });
        } else {
          $('#street_id').empty().append('<option value="">انتخاب کنید</option>');
        }
      });

      $('#street_id').on('change', function() {
        var streetID = $(this).val();
        if(streetID) {
          $.ajax({
            url: '/api/locations',
            type: 'GET',
            data: { level: 'alley', parent_id: streetID },
            success: function(data) {
              $('#alley_id').empty().append('<option value="">انتخاب کنید</option>');
              $.each(data, function(key, value) {
                $('#alley_id').append('<option value="'+ value.id +'">'+ value.name +'</option>');
              });
            }
          });
        } else {
          $('#alley_id').empty().append('<option value="">انتخاب کنید</option>');
        }
      });
    });
  </script>
</body>
</html>
