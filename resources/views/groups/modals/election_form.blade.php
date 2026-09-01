<div id="electionOptionsBox" class="modal-shell election-admin-modal" style="display: none;" dir="rtl"
    role="dialog" aria-modal="true" aria-labelledby="electionAdminModalTitle" aria-hidden="true">
    <div class="modal-shell__backdrop" data-group-chat-action="close-election-admin"></div>
    <div class="modal-shell__dialog">
        <div class="modal-shell__header">
            <h3 id="electionAdminModalTitle" class="modal-shell__title">
                <i class="fas fa-list-check me-2 text-amber-500"></i>
                ایجاد رأی‌گیری انتخابی
            </h3>
            <button type="button" class="modal-shell__close" data-group-chat-action="close-election-admin">×</button>
        </div>

        <form id="electionFormModal" class="modal-shell__form" action="{{ route('groups.poll.store', $group) }}" method="POST">
            @csrf
            <input type="hidden" name="main_type" value="0">

            <div class="alert alert-info small">
                این فرم یک <strong>رأی‌گیری موردی در سامانه نظرسنجی</strong> ایجاد می‌کند و هیچ سمت رسمی، مدیر، بازرس یا نمایندگی انتخاباتی گروه را ایجاد یا تغییر نمی‌دهد. انتخابات رسمی مسئولان گروه فقط از مسیر «انتخابات سیستمی» انجام می‌شود.
            </div>

            <div class="modal-field">
                <label for="election_question" class="modal-label">عنوان رأی‌گیری</label>
                <input id="election_question" type="text" name="question" class="modal-input" placeholder="مثلاً انتخاب گزینه مناسب برای یک تصمیم گروهی">
            </div>

            <div class="modal-field">
                <label for="poll_election_type" class="modal-label">نوع رأی‌گیری</label>
                <select name="type" id="poll_election_type" class="modal-input">
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
                    <label for="election_expires_at" class="modal-label">مدت رأی‌گیری (روز)</label>
                    <input id="election_expires_at" type="number" name="expires_at" class="modal-input" min="1" placeholder="مثلاً ۵">
                </div>
            </div>

            <div class="modal-field">
                <label class="modal-label d-flex align-items-center justify-content-between">
                    گزینه‌ها
                    <button type="button" class="btn btn-sm btn-outline-success" data-group-chat-action="add-election-candidate">
                        <i class="fas fa-plus me-1"></i>
                        افزودن گزینه
                    </button>
                </label>
                <div id="el_dynamic-inputs" class="modal-options">
                    <input type="text" name="options[]" placeholder="گزینه ۱" class="modal-input mb-2" />
                </div>
                <p class="modal-hint">برای شروع رأی‌گیری حداقل دو گزینه وارد کنید. این گزینه‌ها «نامزد رسمی انتخابات سیستمی» محسوب نمی‌شوند.</p>
            </div>

            <div class="modal-shell__actions">
                <button type="button" class="btn btn-outline-secondary" data-group-chat-action="close-election-admin">انصراف</button>
                <button type="submit" class="btn btn-primary">انتشار رأی‌گیری</button>
            </div>
        </form>
    </div>
</div>

<script type="module">
$(document).ready(function() {
    $('#specialties2').select2({
        dropdownParent: $('#electionOptionsBox')
    });
});
</script>
