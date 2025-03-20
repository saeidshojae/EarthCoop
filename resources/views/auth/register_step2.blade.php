@extends('layouts.app')

@section('content')
<div class="container mt-5" style="direction: rtl; text-align: right;">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header text-center" style="font-family: 'Shabnam', Arial, sans-serif;">
          مرحله دوم: انتخاب زمینه‌های صنفی و تخصصی
        </div>
        <div class="card-body">
          <form action="{{ route('register.step2.process') }}" method="POST" novalidate>
            @csrf

            <!-- زمینه فعالیت صنفی -->
            <div class="mb-3">
              <label for="occupational_fields" class="form-label">زمینه فعالیت صنفی:</label>
              <select id="occupational_fields" class="form-control" data-level="1">
                <option value="">انتخاب کنید</option>
                @foreach($occupationalFields as $field)
                  <option value="{{ $field->id }}">{{ $field->name }}</option>
                @endforeach
              </select>
              <select id="occupational_subfields" class="form-control mt-2 d-none" data-level="2">
                <option value="">انتخاب کنید</option>
              </select>
              <select id="occupational_finalfields" class="form-control mt-2 d-none" data-level="3">
                <option value="">انتخاب کنید</option>
              </select>
              <div id="selected_occupational_fields" class="mt-3">
                <!-- انتخاب‌های کاربر نمایش داده می‌شوند -->
              </div>
            </div>

            <!-- زمینه تجربی و تخصصی -->
            <div class="mb-3">
              <label for="experience_fields" class="form-label">زمینه تجربی و تخصصی:</label>
              <select id="experience_fields" class="form-control" data-level="1">
                <option value="">انتخاب کنید</option>
                @foreach($experienceFields as $field)
                  <option value="{{ $field->id }}">{{ $field->name }}</option>
                @endforeach
              </select>
              <select id="experience_subfields" class="form-control mt-2 d-none" data-level="2">
                <option value="">انتخاب کنید</option>
              </select>
              <select id="experience_finalfields" class="form-control mt-2 d-none" data-level="3">
                <option value="">انتخاب کنید</option>
              </select>
              <div id="selected_experience_fields" class="mt-3">
                <!-- انتخاب‌های کاربر نمایش داده می‌شوند -->
              </div>
            </div>

            <div class="text-center">
              <button type="submit" class="btn btn-primary">ادامه</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
  $(document).ready(function() {
      function loadChildren(selectElement, parentId, fieldType, nextLevel) {
          $.ajax({
              url: '/get-children',
              type: 'POST',
              dataType: 'json',
              data: {
                  parent_id: parentId,
                  field_type: fieldType,
                  _token: $('meta[name="csrf-token"]').attr('content')
              },
              success: function(response) {
                  let nextSelect = $(`#${fieldType}_subfields`);
                  let finalSelect = $(`#${fieldType}_finalfields`);

                  // ریست کردن گزینه‌های سطح پایین‌تر
                  if (nextLevel === 2) {
                      nextSelect.empty().append('<option value="">انتخاب کنید</option>').removeClass('d-none').prop('disabled', false);
                      finalSelect.addClass('d-none').empty().append('<option value="">انتخاب کنید</option>');
                  } else if (nextLevel === 3) {
                      finalSelect.empty().append('<option value="">انتخاب کنید</option>').removeClass('d-none').prop('disabled', false);
                  }

                  if (response.data.length > 0) {
                      response.data.forEach(function(child) {
                          let targetSelect = nextLevel === 2 ? nextSelect : finalSelect;
                          targetSelect.append(`<option value="${child.id}">${child.name}</option>`);
                      });
                  } else {
                      if (nextLevel === 2) nextSelect.addClass('d-none');
                      if (nextLevel === 3) finalSelect.addClass('d-none');
                  }
              },
              error: function(xhr) {
                  console.log(xhr.responseText); // بررسی خطا
              }
          });
      }

      function addSelection(selectElement, containerId, fieldType) {
          let selectedOption = $(selectElement).find('option:selected');
          let selectedId = selectedOption.val();
          let selectedName = selectedOption.text();

          if (selectedId) {
              let container = $(containerId);
              let exists = container.find(`[data-id="${selectedId}"]`).length > 0;

              if (!exists) {
                  container.append(`
                      <span class="badge bg-primary text-white mx-1 my-1" data-id="${selectedId}">
                          ${selectedName}
                          <button type="button" class="btn btn-sm btn-danger remove-selection" data-id="${selectedId}">&times;</button>
                          <input type="hidden" name="${fieldType}[]" value="${selectedId}">
                      </span>
                  `);
              }
          }
      }

      function handleRemoval(containerId) {
          $(document).on('click', `${containerId} .remove-selection`, function() {
              let id = $(this).data('id');
              $(this).parent().remove();
          });
      }

      // مدیریت انتخاب‌ها
      $('#occupational_fields').change(function() {
          let parentId = $(this).val();
          if (parentId) loadChildren(this, parentId, 'occupational', 2);
      });

      $('#occupational_subfields').change(function() {
          let parentId = $(this).val();
          if (parentId) loadChildren(this, parentId, 'occupational', 3);
      });

      $('#occupational_finalfields').change(function() {
          addSelection(this, '#selected_occupational_fields', 'occupational_fields');
          $(this).val('');
      });

      $('#experience_fields').change(function() {
          let parentId = $(this).val();
          if (parentId) loadChildren(this, parentId, 'experience', 2);
      });

      $('#experience_subfields').change(function() {
          let parentId = $(this).val();
          if (parentId) loadChildren(this, parentId, 'experience', 3);
      });

      $('#experience_finalfields').change(function() {
          addSelection(this, '#selected_experience_fields', 'experience_fields');
          $(this).val('');
      });

      handleRemoval('#selected_occupational_fields');
      handleRemoval('#selected_experience_fields');
  });
</script>
@endsection