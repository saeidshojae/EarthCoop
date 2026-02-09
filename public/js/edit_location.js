$(document).ready(function() {
  $('#province_id').select2();
  $('#county_id').select2();
  $('#section_id').select2();
  $('#city_id').select2();
  $('#region_id').select2();
  $('#neighborhood_id').select2();
  $('#alley_id').select2();
  $('#street_id').select2();
  $('#continent_id').select2();
  $('#country_id').select2();
});

$(document).ready(function() {
  //-------------------------------------------------
  // تابع عمومی بارگذاری مکان‌ها (Ajax)
  //-------------------------------------------------
  function loadLocation(level, parentId, targetSelectId, callback) {
    var target = $('#' + targetSelectId);
    var addContainer = $('#add-' + level + '-container');

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

        // نمایش یا عدم نمایش دکمه‌ی افزودن
        addContainer.show(); // اگر می‌خواهید همیشه نمایش بدهد
        // یا اگر می‌خواهید فقط وقتی خالی بود نمایش بدهید: 
        // if(data.length == 0) addContainer.show(); else addContainer.hide();

        if (typeof callback === 'function') {
          callback();
        }
      },
      error: function(xhr) {
        console.error("خطا در دریافت داده برای سطح " + level + ": ", xhr.responseText);
        if (typeof callback === 'function') {
          callback(); // فراخوانی callback حتی درصورت خطا تا اختلالی در زنجیره ایجاد نشود
        }
      }
    });
  }

  //-------------------------------------------------
  // رویداد تغییر برای Select های اولیه
  //-------------------------------------------------

  // قاره -> کشور
  $('#continent_id').on('change', function(){
    var continentId = $(this).val();
    loadLocation('country', continentId, 'country_id', function(){
      $('#province_id, #county_id, #section_id, #city_id, #region_id, #neighborhood_id, #street_id, #alley_id')
          .empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });
  });

  // کشور -> استان
  $('#country_id').on('change', function(){
    var countryId = $(this).val();
    loadLocation('province', countryId, 'province_id', function(){
      $('#county_id, #section_id, #city_id, #region_id, #neighborhood_id, #street_id, #alley_id')
          .empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });
  });

  // استان -> شهرستان
  $('#province_id').on('change', function(){
    var provinceId = $(this).val();
    loadLocation('county', provinceId, 'county_id', function(){
      $('#section_id, #city_id, #region_id, #neighborhood_id, #street_id, #alley_id')
          .empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });
  });

  // شهرستان -> بخش
  $('#county_id').on('change', function(){
    var countyId = $(this).val();
    loadLocation('section', countyId, 'section_id', function(){
      $('#city_id, #region_id, #neighborhood_id, #street_id, #alley_id')
          .empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });
  });

  // بخش -> شهر
  $('#section_id').on('change', function(){
    var sectionId = $(this).val();
    loadLocation('city', sectionId, 'city_id', function(){
      $('#region_id, #neighborhood_id, #street_id, #alley_id')
          .empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });
  });

  // شهر -> منطقه
  $('#city_id').on('change', function(){
    var cityId = $(this).val();
    loadLocation('region', cityId, 'region_id', function(){
      $('#neighborhood_id, #street_id, #alley_id')
          .empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });
  });

  // منطقه -> محله
  $('#region_id').on('change', function(){
    var regionId = $(this).val();
    loadLocation('neighborhood', regionId, 'neighborhood_id', function(){
      $('#street_id, #alley_id')
          .empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });
  });

  // محله -> خیابان
  $('#neighborhood_id').on('change', function(){
    var neighborhoodId = $(this).val();
    loadLocation('street', neighborhoodId, 'street_id', function(){
      $('#alley_id')
          .empty().append('<option value="">انتخاب کنید</option>').prop('disabled', true);
    });
  });

  // خیابان -> کوچه
  $('#street_id').on('change', function(){
    var streetId = $(this).val();
    loadLocation('alley', streetId, 'alley_id');
  });


  //-------------------------------------------------
  // اگر کاربر با خطای ولیدیشن برگشته، 
  // باید مقدار old(...) را دوباره ست کنیم
  //-------------------------------------------------



  // ما فرض کرده‌ایم کاربر قاره، کشور و استان را از قبل دارد (چون select ثابت هستند).
  // حالا اگر province_id داشته باشد، باید شهرستان‌ها را لود کنیم و بعد مقدارش را ست کنیم...
  
  // Define all the "old" variables at the start
  let oldProvince = window.userAddress.province_id;
  let oldCounty = window.userAddress.county_id;
  let oldSection = window.userAddress.section_id;
  let oldCity = window.userAddress.city_id;
  let oldRegion = window.userAddress.region_id;
  let oldNeighborhood = window.userAddress.neighborhood_id;
  let oldStreet = window.userAddress.street_id;
  let oldAlley = window.userAddress.alley_id; 

  if(oldProvince) {
    // چون در Blade ما استان را تگ <option selected> کردیم، حالا باید Ajax شهرستان را بگیریم.
    loadLocation('county', oldProvince, 'county_id', function(){
      if(oldCounty) {
        // وقتی لود شد، مقدار شهرستان را ست کن
        $('#county_id').val(oldCounty).prop('disabled', false);

        // سپس بخش (section)
        loadLocation('section', oldCounty, 'section_id', function(){
          if(oldSection){
            $('#section_id').val(oldSection).prop('disabled', false);

            // بعد شهر
            loadLocation('city', oldSection, 'city_id', function(){
              if(oldCity) {
                $('#city_id').val(oldCity).prop('disabled', false);

                // منطقه
                loadLocation('region', oldCity, 'region_id', function(){
                  if(oldRegion){
                    $('#region_id').val(oldRegion).prop('disabled', false);

                    // محله
                    loadLocation('neighborhood', oldRegion, 'neighborhood_id', function(){
                      if(oldNeighborhood){
                        $('#neighborhood_id').val(oldNeighborhood).prop('disabled', false);

                        // خیابان
                        loadLocation('street', oldNeighborhood, 'street_id', function(){
                          if(oldStreet) {
                            $('#street_id').val(oldStreet).prop('disabled', false);

                            // کوچه
                            loadLocation('alley', oldStreet, 'alley_id', function(){
                              if(oldAlley) {
                                $('#alley_id').val(oldAlley).prop('disabled', false);
                              }
                            });
                          }
                        });
                      }
                    });
                  }
                });
              }
            });
          }
        });
      }
    });
  }

  //-------------------------------------------------
  // بخش افزودن مکان جدید (با کلیک روی «از اینجا بسازید»)
  //-------------------------------------------------
  // مثال: در فایل جداگانه partials.add_location_modal
  // مشابه کد قبلی که داشتید؛ 
  // همراه با متد AJAX برای ثبت مکان جدید و بستن مودال.
  // ...
  $('.modal').on('show.bs.modal', function (event) {
  let modal = $(this);
  let type = modal.find('.add-location-form').data('type');
  let parentInput = modal.find('input[name="parent_id"]');

  let parentSelectId = {
    region: '#city_id',
    neighborhood: '#region_id',
    street: '#neighborhood_id',
    alley: '#street_id'
  }[type];

  if (parentSelectId && $(parentSelectId).val()) {
    parentInput.val($(parentSelectId).val());
  } else {
    parentInput.val('');
  }
});


  $('.add-location-form .btn').on('click', function (e) {
  e.preventDefault();

  let form = $(this).closest('form');
  let type = form.data('type'); // region, neighborhood, etc.
  let name = form.find('input[type="text"]').val();
  let parentId = form.find('.parent-id').val();

  if (!name) {
    alert('لطفاً نام را وارد کنید.');
    return;
  }

  $.ajax({
    url: `/api/add-${type}`,  // ⬅️ بر اساس Route شما
    type: 'POST',
    data: {
      name: name,
      parent_id: parentId,
      _token: '{{ csrf_token() }}'
    },
    success: function (response) {
      alert(`${type} جدید با موفقیت افزوده شد.`);

      let selectId = `#${type}_id`;

      $(selectId).append(`<option value="${response.id}" selected>${response.name}</option>`);
      level = '';
      if(type == 'region'){
        level = 'neighborhood';
      }else if(type == 'neighborhood'){
        level = 'street';
      }else if(type == 'street'){
        level = 'alley';
      }

      var addContainer = $('#add-' + level + '-container');
      addContainer.show(); 
      $(selectId).prop('disabled', false);

      // بستن مودال
      $(`#add${type.charAt(0).toUpperCase() + type.slice(1)}Modal`).modal('hide');
      form.find('input[type="text"]').val('');
      $('.modal-backdrop').hide();

    },
    error: function (xhr) {
      console.error("خطا در افزودن:", xhr.responseText);
      alert('خطا در افزودن مکان جدید');
    }
  });
});
});
