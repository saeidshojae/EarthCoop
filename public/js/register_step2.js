$(document).ready(function() {
  $('#occupational_fields').select2();
  $('#experience_fields').select2();
  
});

$(document).ready(function () {
  // راه‌اندازی select2
  $('#occupational_fields').select2();
  $('#experience_fields').select2();

  // فراخوانی AJAX برای بارگذاری زیر زمینه‌ها
  function loadChildren(parentId, fieldType, level) {
    $.ajax({
      url: '/get-children',
      method: 'POST',
      dataType: 'json',
      data: {
        parent_id: parentId,
        field_type: fieldType,
        _token: document.querySelector('meta[name="csrf-token"]').content,
      },
      success: function (response) {
        // بررسی که آیا المنت وجود دارد یا نه
        let subfieldSelect = document.querySelector('#occupational_subfields');
        if (subfieldSelect) {
          console.log('منتخب #occupational_subfields پیدا شد:', subfieldSelect);
        } else {
          console.log('منتخب #occupational_subfields پیدا نشد.');
        }

        if (level === 2) {
          $(`#${fieldType}_finalfields`).addClass('d-none').empty();
        }

        let nextSelectId = level === 2 ? `${fieldType}_subfields` : `${fieldType}_finalfields`;
        let nextSelect = $(`#${nextSelectId}`);

        nextSelect.empty().append('<option value="">انتخاب کنید</option>');
        if (response.data && response.data.length > 0) {
          response.data.forEach(child => {
            nextSelect.append(`<option value="${child.id}">${child.name}</option>`);
          });

          nextSelect.removeClass('d-none');

          nextSelect.select2({
            width: '100%',
            placeholder: "انتخاب کنید",
            dir: "rtl"
          });
        } else {
          nextSelect.addClass('d-none');
        }
      }
    });
  }





  //---------------------------------------------------
  // 2) ساختن badge و افزودن به DOM
  //---------------------------------------------------
  function addSelection(select, container, inputName, errorContainer, duplicateErrorContainer) {
    let id = select.val();
    let name = select.find(':selected').text();
    if (!id) return;

    if (container.find(`[data-id="${id}"]`).length > 0) {
      $(duplicateErrorContainer).show();
      return;
    } else {
      $(duplicateErrorContainer).hide();
    }

    let selectedItems = container.find('.badge').length;
    if (selectedItems >= 2) {
      $(errorContainer).show();
      return;
    }
    $(errorContainer).hide();

    container.append(`
      <span class="badge bg-primary text-white mx-1 my-1" data-id="${id}">
        ${name}
        <button type="button" class="btn btn-sm btn-danger remove-selection">&times;</button>
        <input type="hidden" name="${inputName}[]" value="${id}">
      </span>
    `);
  }

  //---------------------------------------------------
  // 3) تغییر سطح 1 => لود سطح 2
  //---------------------------------------------------
  $('select[data-level="1"]').change(function () {
    let type = this.id.split('_')[0]; // occupational یا experience
    let parentId = $(this).val();
    if (!parentId) {
      $(`#${type}_subfields`).addClass('d-none').empty();
      $(`#${type}_finalfields`).addClass('d-none').empty();
      return;
    }
    loadChildren(parentId, type, 2);
  });

  //---------------------------------------------------
  // 4) تغییر سطح 2 => لود سطح 3
  //---------------------------------------------------
  $('select[data-level="2"]').change(function () {
    let type = this.id.split('_')[0];
    let parentId = $(this).val();
    if (!parentId) {
      $(`#${type}_finalfields`).addClass('d-none').empty();
      return;
    }
    loadChildren(parentId, type, 3);
  });

  //---------------------------------------------------
  // 5) تغییر سطح 3 => ساخت badge
  //---------------------------------------------------
  $('select[data-level="3"]').change(function () {
    let type = this.id.split('_')[0];
    let container = $(`#selected_${type}_fields`);
    addSelection(
      $(this),
      container,
      `${type}_fields`,
      `#error_${type}`,
      `#duplicate_error_${type}`
    );
    $(this).val('');
  });

  //---------------------------------------------------
  // 6) حذف badge با کلیک روی دکمه ضربدر
  //---------------------------------------------------
  $(document).on('click', '.remove-selection', function () {
    let parentContainer = $(this).closest('div');
    $(this).parent().remove();
    if (parentContainer.find('.badge').length < 2) {
      parentContainer.siblings('.error-message').hide();
    }
  });

  //---------------------------------------------------
  // 7) بازگردانی انتخاب‌های قبلی (old) در صورت خطای ولیدیشن
  //---------------------------------------------------
  function rebuildBadge(fieldId, fieldType) {
    if (!fieldId) return;
    $.ajax({
      url: '/get-field-info',
      method: 'POST',
      data: {
        _token: document.querySelector('meta[name="csrf-token"]').content,
        id: fieldId,
        field_type: fieldType
      },
      success: function(response) {
        let container = $(`#selected_${fieldType}_fields`);
        if (container.find(`[data-id="${response.id}"]`).length === 0) {
          container.append(`
            <span class="badge bg-primary text-white mx-1 my-1" data-id="${response.id}">
              ${response.name}
              <button type="button" class="btn btn-sm btn-danger remove-selection">&times;</button>
              <input type="hidden" name="${fieldType}_fields[]" value="${response.id}">
            </span>
          `);
        }
      }
    });
  }


  oldOccupational.forEach(function(fieldId){
    rebuildBadge(fieldId, 'occupational');
  });

  oldExperience.forEach(function(fieldId){
    rebuildBadge(fieldId, 'experience');
  });

  //---------------------------------------------------
  // 8) دکمهٔ ذخیره در مودال "زمینه صنفی جدید" => Ajax
  //---------------------------------------------------
  $('#saveOccupationalBtn').click(function(){
    let newName = $('#occupational_name').val().trim();
    let parentId = $('#occupational_parent').val();  // ممکن است خالی باشد
    if(!newName) {
      alert('لطفاً نام زمینه صنفی را وارد کنید');
      return;
    }
$.ajax({
  url: '/add-field',
  method: 'POST',
  data: {
    _token: document.querySelector('meta[name="csrf-token"]').content,
    name: newName,
    parent_id: parentId,
    type: 'occupational'
  },
  success: function(resp) {
    if (resp.status === 'ok') {
      alert('آیتم با موفقیت اضافه شد');

      // ساخت گزینه جدید
      const newOption = new Option(resp.name, resp.id, true, true); // selected = true
      const newOption2 = new Option(resp.name, resp.id, true, true); // selected = true

      if (!parentId) {
        // سطح ۱: والد ندارد
        $('#occupational_fields').append(newOption).trigger('change');
      } else {
        // سطح ۲ یا ۳ بسته به انتخاب فعلی
        const parentInLevel1 = $('#occupational_fields').val() == parentId;
        const parentInLevel2 = $('#occupational_subfields').val() == parentId;

        if (parentInLevel1) {
          $('#occupational_subfields')
            .removeClass('d-none')
            .append(newOption)
            .trigger('change');
        } else if (parentInLevel2) {
          $('#occupational_finalfields')
            .removeClass('d-none')
            .append(newOption)
            .trigger('change');
        } else {
          // اگر معلوم نبود، بچه‌ها رو دوباره بارگذاری کن
          loadChildren(parentId, 'occupational', 2);
        }
      }

      // پاکسازی و بستن مودال
      $('#occupational_name').val('');
      $('#occupational_parent').val('');
      $('#addOccupationalModal').modal('hide');
      $('.modal-backdrop').hide();
      window.location.reload();
    } else {
      alert('خطا در ذخیره آیتم');
    }


  },
  error: function() {
    alert('مشکلی در ارتباط با سرور به‌وجود آمد.');
  }
});

  }); 

  //---------------------------------------------------
  // 9) دکمهٔ ذخیره در مودال "زمینه تخصصی جدید" => Ajax
  //---------------------------------------------------
  $('#saveExperienceBtn').click(function(){
    let newName = $('#experience_name').val().trim();
    let parentId = $('#experience_parent').val();
    if(!newName) {
      alert('لطفاً نام زمینه تخصصی را وارد کنید');
      return;
    }
    $.ajax({
      url: '/add-field',
      method: 'POST',
      data: {
        _token: document.querySelector('meta[name="csrf-token"]').content,
        name: newName,
        parent_id: parentId,
        type: 'experience'
      },
      success: function(resp) {
        if(resp.status === 'ok') {
          alert('آیتم با موفقیت اضافه شد');
          if(!parentId) {
            // سطح 1
            // $('#experience_fields').append(`<option value="${resp.id}">${resp.name}</option>`);
          } else {
            // زیرمجموعه
            loadChildren(parentId, 'experience', 2);
          }
          // پاکسازی
          $('#experience_name').val('');
          $('#experience_parent').val('');
          $('#addExperienceModal').modal('hide');
          $('.modal-backdrop').hide();
          window.location.reload();


        } else {
          alert('خطا در ذخیره آیتم');
        }
      },
      error: function() {
        alert('مشکلی در ارتباط با سرور به‌وجود آمد.');
      }
    });
  });

});

function loadSubfields(parentId, level, isExp) {
  const url = isExp
    ? `/api/experience-fields/${parentId}/children`
    : `/api/occupational-fields/${parentId}/children`;

  const selectId = isExp
    ? (level === 2 ? '#experience_subfields' : '#experience_finalfields')
    : (level === 2 ? '#occupational_subfields' : '#occupational_finalfields');
  const $select = $(selectId);
  const container = isExp ? '#experience_container' : '#occupational_container';

  $.get(url, function (data) {
    $select.empty().append('<option value="">زیر دسته این دسته را انتخاب کنید</option>');
    
    // Add options if there are any
    if (data && data.length > 0) {
      data.forEach(field => {
        $select.append(`<option value="${field.id}">${field.name}</option>`);
      });
      $select.append('<option value="create_new">+ ایجاد کنید</option>');
      $select.select2();
      $select.removeClass('d-none'); // Show the select if it has options
    } else {
      $select.addClass('d-none'); // Hide the select if it has no options
    }

    // Hide all selects in the container
    $(`${container} select`).each(function () {
      const thisLevel = parseInt($(this).data('level'));
      if (thisLevel > level) {
        $(this).addClass('d-none');
      }
    });
  });
}