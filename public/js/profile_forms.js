const oldOccupational = window.profileData?.oldOccupational || [];
const oldExperience = window.profileData?.oldExperience || [];

document.addEventListener('DOMContentLoaded', function () {

    // حذف آیتم از لیست صنف یا تخصص
    document.querySelectorAll('.remove-selection').forEach(btn => {
        btn.addEventListener('click', function () {
            this.closest('.badge').remove();
        });
    });

    // افزودن ورودی شبکه اجتماعی
    document.getElementById('add-social-btn')?.addEventListener('click', function () {
        const container = document.getElementById('dynamic-inputs');
        const inputGroup = document.createElement('div');
        inputGroup.className = 'input-group mb-2';
        inputGroup.innerHTML = `
            <input type="url" name="options[]" class="form-control" placeholder="لینک را وارد کنید">
            <div class="input-group-append">
                <button type="button" class="btn btn-danger remove-social">✖</button>
            </div>
        `;
        container.appendChild(inputGroup);
    });

    // حذف ورودی شبکه اجتماعی (با delegation)
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-social')) {
            e.target.closest('.input-group').remove();
        }
    });

    // کنترل حداکثر تعداد انتخاب
    function limitSelection(containerId, errorId) {
        const container = document.getElementById(containerId);
        const error = document.getElementById(errorId);
        const badges = container.querySelectorAll('span.badge');
        // error.style.display = (badges.length > 2) ? 'block' : 'none';
    }

    limitSelection('selected_occupational_fields', 'error_occupational');
    limitSelection('selected_experience_fields', 'error_experience');

    // نمایش select مرتبط با سطح
    function showSelect(level) {
        document.querySelectorAll(`select[data-level="${level}"]`).forEach(select => {
            select.classList.remove('d-none');
        });
    }

    // مثال برای نمایش سطح دوم صنف
    const occField = document.getElementById('occupational_fields');
    if (occField) {
        occField.addEventListener('change', function () {
            if (this.value) {
                showSelect(2); // نمایش زیرمجموعه
            } else {
                document.querySelectorAll(`select[data-level="2"], select[data-level="3"]`).forEach(select => {
                    select.classList.add('d-none');
                });
            }
        });
    }

    // به همین صورت می‌تونید برای experience_fields هم همین ساختار رو تکرار کنید

});
