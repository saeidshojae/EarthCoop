<div id="pollOptionsBox" class="modal-shell" style="display: none;" dir="rtl" onclick="handleModalClick(event, 'pollOptionsBox')">
    <div class="modal-shell__dialog" onclick="event.stopPropagation()">
        <div class="modal-shell__header">
            <h3 class="modal-shell__title">
                <i class="fas fa-chart-pie me-2 text-indigo-500"></i>
                ایجاد نظرسنجی جدید
            </h3>
            <button type="button" class="modal-shell__close" onclick="cancelPollForm()">×</button>
        </div>

        <form id="pollForm" class="modal-shell__form" action="{{ route('groups.poll.store', $group) }}" method="POST">
            @csrf
            <input type="hidden" name="main_type" value="1">

            <div class="modal-field">
                <label for="poll_type" class="modal-label">نوع نظرسنجی</label>
                <select name="type" id="poll_type" class="modal-input" onchange="handlePollTypeChange()">
                    <option value="0">عمومی</option>
                    <option value="1">تخصصی</option>
                </select>
            </div>

            <div id="specialties_box" class="modal-field" style="display: none;">
                <label for="specialties" class="modal-label">تخصص مرتبط</label>
                <select name="skill_id" id="specialties" class="modal-input">
                    @foreach ($specialities as $speciality)
                        <option value="{{ $speciality->id }}">{{ $speciality->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="modal-grid">
                <div class="modal-field">
                    <label for="poll_expires_at" class="modal-label">مدت فعال بودن (روز)</label>
                    <input id="poll_expires_at" type="number" name="expires_at" class="modal-input" min="1" placeholder="مثلاً ۳">
                </div>
                <div class="modal-field">
                    <label for="poll_question" class="modal-label">سوال نظرسنجی</label>
                    <input id="poll_question" type="text" name="question" class="modal-input" placeholder="متن سوال را بنویسید">
                </div>
            </div>

            <div class="modal-field">
                <label class="modal-label d-flex align-items-center justify-content-between">
                    گزینه‌ها
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="addInput()">
                        <i class="fas fa-plus me-1"></i>
                        گزینه جدید
                    </button>
                </label>
                <div id="dynamic-inputs" class="modal-options">
                    <input type="text" name="options[]" placeholder="گزینه ۱" class="modal-input mb-2" />
                </div>
                <p class="modal-hint">برای ایجاد نظرسنجی معتبر حداقل دو گزینه تعریف کنید.</p>
            </div>

            <div class="modal-shell__actions">
                <button type="button" class="btn btn-outline-secondary" onclick="cancelPollForm()">انصراف</button>
                <button type="submit" class="btn btn-primary">انتشار نظرسنجی</button>
            </div>
        </form>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#specialties').select2({
            dropdownParent: $('#pollOptionsBox')
        });
    });

    let optionCount = 1;

    function addInput() {
        optionCount++;
        const container = document.getElementById('dynamic-inputs');
        const wrapper = document.createElement('div');
        wrapper.className = 'modal-option-row';
        wrapper.innerHTML = `
            <input type="text" name="options[]" placeholder="گزینه ${optionCount}" class="modal-input">
            <button type="button" class="modal-option-remove" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        container.appendChild(wrapper);
    }

    function handlePollTypeChange() {
        const type = document.getElementById('poll_type').value;
        const specialtiesBox = document.getElementById('specialties_box');
        specialtiesBox.style.display = (type === '1') ? 'block' : 'none';
    }
</script>
