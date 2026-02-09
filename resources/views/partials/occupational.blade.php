<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
<div class="modal fade" id="addOccupationalModal" tabindex="-1" aria-labelledby="addOccupationalModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content" style="direction: rtl;">
      <div class="modal-header">
        <h5 class="modal-title" id="addOccupationalModalLabel">افزودن زمینه صنفی جدید</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
      </div>
      <div class="modal-body">
        
        {{-- سطح ۱ --}}
        <div class="mb-3">
          <label for="level1" class="form-label">دسته اصلی (سطح ۱)</label>
          <select id="level1" class="form-control select2">
            <option value="">انتخاب کنید</option>
            <option value="null">دسته اصلی</option>
            @foreach($level1Fields as $field)
              <option value="{{ $field->id }}">{{ $field->name }}</option>
            @endforeach
          </select>
        </div>

        {{-- سطح ۲ --}}
        <div class="mb-3 d-none" id="level2-wrapper">
          <label for="level2" class="form-label">زیرمجموعه سطح ۲</label>
          <select id="level2" class="form-control select2">
            <option value="">انتخاب کنید</option>
          </select>
        </div>

        {{-- سطح ۳ --}}
        <div class="mb-3 d-none" id="level3-wrapper">
          <label for="level3" class="form-label">زیرمجموعه سطح ۳</label>
          <select id="level3" class="form-control select2">
            <option value="">انتخاب کنید</option>
          </select>
        </div>

        {{-- آی‌دی والد نهایی که به سرور ارسال می‌شه --}}
        <input type="hidden" name="parent_id" id="occupational_parent">

        {{-- عنوان --}}
        <div class="mb-3">
          <label for="occupational_name" class="form-label">عنوان زمینه صنفی</label>
          <input type="text" class="form-control" id="occupational_name" placeholder="مثلاً: خدمات چاپ و تبلیغات">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
        <button type="button" class="btn btn-primary" id="saveOccupationalBtn">ذخیره</button>
      </div>
    </div>
  </div>
</div>


<script>
  $(document).ready(function () {
  $('.select2').select2({
    dropdownParent: $('#addOccupationalModal'),
    width: '100%',
    placeholder: 'انتخاب کنید'
  });

  // وقتی سطح ۱ انتخاب می‌شه
  $('#level1').on('change', function () {
    const parentId = $(this).val();
    $('#level3-wrapper').addClass('d-none');
    $('#level3').empty().append('<option value="">انتخاب کنید</option>');
    $('#occupational_parent').val(parentId); // انتخاب سطح ۱ به عنوان والد موقت

    if (parentId) {
      $.get(`/fields/children/${parentId}`, function (res) {
        if (res.length) {
          $('#level2').empty().append('<option value="">انتخاب کنید</option>');
          res.forEach(f => {
            $('#level2').append(`<option value="${f.id}">${f.name}</option>`);
          });
          $('#level2-wrapper').removeClass('d-none');
        } else {
          $('#level2-wrapper').addClass('d-none');
          $('#level2').empty().append('<option value="">انتخاب کنید</option>');
        }
      });
    } else {
      $('#level2-wrapper').addClass('d-none');
      $('#level2').empty().append('<option value="">انتخاب کنید</option>');
    }
  });

  // وقتی سطح ۲ انتخاب می‌شه
  $('#level2').on('change', function () {
    const parentId = $(this).val();
    $('#occupational_parent').val(parentId);

  });

});

</script>