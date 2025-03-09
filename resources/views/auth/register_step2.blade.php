<!DOCTYPE html>
<html lang="fa">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>مرحله ۲: زمینه‌های صنفی و تخصصی</title>
  <link href="{{ asset('css/app.css') }}" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
  <div class="container mt-5">
    <h1 class="mb-4">مرحله ۲: زمینه‌های صنفی و تخصصی</h1>
    <form action="{{ route('register.step2.process') }}" method="POST">
      @csrf

      <div class="form-group">
        <label for="occupational_fields_1">زمینه فعالیت صنفی:</label>
        <select name="occupational_fields[]" id="occupational_fields_1" class="form-control" required>
          <option value="">انتخاب کنید</option>
          @foreach ($occupationalFields as $field)
            <option value="{{ $field->id }}" data-parent="{{ $field->parent_id }}">{{ $field->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="form-group mt-3">
        <label for="experience_fields_1">زمینه تجربی و تخصص:</label>
        <select name="experience_fields[]" id="experience_fields_1" class="form-control" required>
          <option value="">انتخاب کنید</option>
          @foreach ($experienceFields as $field)
            <option value="{{ $field->id }}" data-parent="{{ $field->parent_id }}">{{ $field->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="form-group mt-3">
        <label for="occupational_fields_2">زمینه فعالیت صنفی (سطح ۲):</label>
        <select name="occupational_fields[]" id="occupational_fields_2" class="form-control" disabled>
          <option value="">انتخاب کنید</option>
        </select>
      </div>

      <div class="form-group mt-3">
        <label for="experience_fields_2">زمینه تجربی و تخصص (سطح ۲):</label>
        <select name="experience_fields[]" id="experience_fields_2" class="form-control" disabled>
          <option value="">انتخاب کنید</option>
        </select>
      </div>

      <div class="form-group mt-3">
        <label for="occupational_fields_3">زمینه فعالیت صنفی (سطح ۳):</label>
        <select name="occupational_fields[]" id="occupational_fields_3" class="form-control" disabled>
          <option value="">انتخاب کنید</option>
        </select>
      </div>

      <div class="form-group mt-3">
        <label for="experience_fields_3">زمینه تجربی و تخصص (سطح ۳):</label>
        <select name="experience_fields[]" id="experience_fields_3" class="form-control" disabled>
          <option value="">انتخاب کنید</option>
        </select>
      </div>

      <button type="submit" class="btn btn-primary mt-3">ادامه</button>
    </form>
  </div>

  <script>
    $(document).ready(function() {
        function loadChildren(selectElement, parentId, fieldType) {
            $.ajax({
                url: '/get-children',
                type: 'POST',
                dataType: 'json',
                data: {
                    parent_id: parentId,
                    field_type: fieldType,  // تعیین نوع فیلد
                    _token: $('meta[name="csrf-token"]').attr('content')  // ارسال CSRF Token
                },
                success: function(response) {
                    selectElement.empty().append('<option value="">انتخاب کنید</option>');

                    if (response.data.length > 0) {
                        response.data.forEach(function(child) {
                           selectElement.append('<option value="' + child.id + '">' + child.name + '</option>');
                        });
                        selectElement.prop('disabled', false);
                    } else {
                        selectElement.prop('disabled', true);
                    }
                },
                error: function(xhr) {
                    console.log(xhr.responseText); // بررسی لاگ خطا
                }
            });
        }

        function handleChange(parentSelect, childSelect, fieldType) {
            $(parentSelect).change(function() {
                var selectedId = $(this).val();
                if (selectedId) {
                    loadChildren($(childSelect), selectedId, fieldType);
                } else {
                    $(childSelect).prop('disabled', true).empty().append('<option value="">انتخاب کنید</option>');
                }
            });
        }

        // مقداردهی صحیح با نوع داده
        handleChange('#occupational_fields_1', '#occupational_fields_2', 'occupational');
        handleChange('#occupational_fields_2', '#occupational_fields_3', 'occupational');
        handleChange('#experience_fields_1', '#experience_fields_2', 'experience');
        handleChange('#experience_fields_2', '#experience_fields_3', 'experience');
    });

  </script>
</body>
</html>
