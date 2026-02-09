<div id="electionOptionsBox" class="modal-shell" style="display: none;" dir="rtl">
    <div class="modal-shell__backdrop" onclick="cancelelectionForm()"></div>
    <div class="modal-shell__dialog">
        <div class="modal-shell__header">
            <h3 class="modal-shell__title">
                <i class="fas fa-vote-yea me-2 text-amber-500"></i>
                ایجاد انتخابات جدید
            </h3>
            <button type="button" class="modal-shell__close" onclick="cancelelectionForm()">×</button>
        </div>

        <form id="electionFormModal" class="modal-shell__form" action="{{ route('groups.poll.store', $group) }}" method="POST">
            @csrf
            <input type="hidden" name="main_type" value="0">

            <div class="modal-field">
                <label for="election_question" class="modal-label">عنوان انتخابات</label>
                <input id="election_question" type="text" name="question" class="modal-input" placeholder="مثلاً انتخابات هیات‌مدیره">
            </div>

            <div class="modal-field">
                <label for="poll_election_type" class="modal-label">نوع انتخابات</label>
                <select name="type" id="poll_election_type" class="modal-input" onchange="handlePollTypeChange2()">
                    <option value="0">عمومی</option>
                    <option value="1">تخصصی</option>
                </select>
            </div>

            <div id="el_specialties_box" class="modal-field" style="display: none;">
                <label for="specialties2" class="modal-label">تخصص مرتبط</label>
                <select name="skill_id" id="specialties2" class="modal-input">
                    @foreach ($specialities as $speciality)
                        <option value="{{ $speciality->id }}">{{ $speciality->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="modal-grid">
                <div class="modal-field">
                    <label for="election_expires_at" class="modal-label">مدت رای‌گیری (روز)</label>
                    <input id="election_expires_at" type="number" name="expires_at" class="modal-input" min="1" placeholder="مثلاً ۵">
                </div>
            </div>

            <div class="modal-field">
                <label class="modal-label d-flex align-items-center justify-content-between">
                    نامزدها
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="addInput2()">
                        <i class="fas fa-user-plus me-1"></i>
                        افزودن نامزد
                    </button>
                </label>
                <div id="el_dynamic-inputs" class="modal-options">
                    <input type="text" name="options[]" placeholder="نامزد ۱" class="modal-input mb-2" />
                </div>
                <p class="modal-hint">برای شروع انتخابات حداقل دو نامزد معرفی کنید. ترتیب نمایش نامزدها قابل تغییر است.</p>
            </div>

            <div class="modal-shell__actions">
                <button type="button" class="btn btn-outline-secondary" onclick="cancelelectionForm()">انصراف</button>
                <button type="submit" class="btn btn-primary">انتشار انتخابات</button>
            </div>
        </form>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#specialties2').select2({
            dropdownParent: $('#electionOptionsBox')
        });
    });

    let optionCount2 = 1;

    function addInput2() {
        optionCount2++;
        const container = document.getElementById('el_dynamic-inputs');
        const wrapper = document.createElement('div');
        wrapper.className = 'modal-option-row';
        wrapper.innerHTML = `
            <input type="text" name="options[]" placeholder="نامزد ${optionCount2}" class="modal-input">
            <button type="button" class="modal-option-remove" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        container.appendChild(wrapper);
    }

    function handlePollTypeChange2() {
        const type = document.getElementById('poll_election_type').value;
        const specialtiesBox = document.getElementById('el_specialties_box');
        specialtiesBox.style.display = (type === '1') ? 'block' : 'none';
    }
</script>
