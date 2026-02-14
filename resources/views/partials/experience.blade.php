<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
<div class="modal fade" id="addExperienceModal" tabindex="-1" aria-labelledby="addExperienceModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content" style="direction: rtl;">
      <div class="modal-header">
        <h5 class="modal-title" id="addExperienceModalLabel">افزودن زمینه تخصصی جدید</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
      </div>
      <div class="modal-body">

        {{-- سطح ۱ --}}
        <div class="mb-3">
          <label for="exp_level1" class="form-label">دسته اصلی (سطح ۱)</label>
          <select id="exp_level1" class="form-control select2">
            <option value="">انتخاب کنید</option>
            <option value="null">دسته اصلی</option>
            @foreach($level1ExperienceFields as $field)
              <option value="{{ $field->id }}">{{ $field->name }}</option>
            @endforeach
          </select>
        </div>

        {{-- سطح ۲ --}}
        <div class="mb-3 d-none" id="exp_level2-wrapper">
          <label for="exp_level2" class="form-label">زیرمجموعه سطح ۲</label>
          <select id="exp_level2" class="form-control select2">
            <option value="">انتخاب کنید</option>
          </select>
        </div>

        {{-- سطح ۳ --}}
        <div class="mb-3 d-none" id="exp_level3-wrapper">
          <label for="exp_level3" class="form-label">زیرمجموعه سطح ۳</label>
          <select id="exp_level3" class="form-control select2">
            <option value="">انتخاب کنید</option>
          </select>
        </div>

        {{-- آی‌دی والد نهایی که به سرور ارسال می‌شه --}}
        <input type="hidden" name="parent_id" id="experience_parent">

        {{-- عنوان --}}
        <div class="mb-3">
          <label for="experience_name" class="form-label">عنوان زمینه تخصصی</label>
          <input type="text" class="form-control" id="experience_name" placeholder="مثلاً: طراحی رابط کاربری">
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
        <button type="button" class="btn btn-primary" id="saveExperienceBtn">ذخیره</button>
      </div>
    </div>
  </div>
</div>
<script>
  $(document).ready(function () {
    // فعال‌سازی Select2
    $('#exp_level1, #exp_level2, #exp_level3').select2({
      dropdownParent: $('#addExperienceModal'),
      width: '100%',
      placeholder: 'انتخاب کنید'
    });

    // سطح ۱ انتخاب شد
    $('#exp_level1').on('change', function () {
      const parentId = $(this).val();
      $('#exp_level3-wrapper').addClass('d-none');
      $('#exp_level3').empty().append('<option value="">انتخاب کنید</option>');
      $('#experience_parent').val(parentId);

      if (parentId) {
        $.get(`/fields/children-ex/${parentId}`, function (res) {
          if (res.length) {
            $('#exp_level2').empty().append('<option value="">انتخاب کنید</option>');
            res.forEach(f => {
              $('#exp_level2').append(`<option value="${f.id}">${f.name}</option>`);
            });
            $('#exp_level2-wrapper').removeClass('d-none');
          } else {
            $('#exp_level2-wrapper').addClass('d-none');
            $('#exp_level2').empty().append('<option value="">انتخاب کنید</option>');
          }
        });
      } else {
        $('#exp_level2-wrapper').addClass('d-none');
        $('#exp_level2').empty().append('<option value="">انتخاب کنید</option>');
      }
    });

    // سطح ۲ انتخاب شد
    $('#exp_level2').on('change', function () {
      const parentId = $(this).val();
      $('#experience_parent').val(parentId);
    });

 
  });
</script>

