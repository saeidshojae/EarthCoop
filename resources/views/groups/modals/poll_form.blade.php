<div id="pollOptionsBox" class="modal-shell" style="display: none;" dir="rtl" data-composer-modal="poll">
    <div class="modal-shell__dialog">
        <div class="modal-shell__header">
            <h3 class="modal-shell__title">
                <i class="fas fa-chart-pie me-2 text-indigo-500"></i>
                ایجاد نظرسنجی جدید
            </h3>
            <button type="button" class="modal-shell__close" data-group-chat-action="close-poll-modal">×</button>
        </div>

        <form id="pollForm" class="modal-shell__form" action="{{ route('groups.poll.store', $group) }}" method="POST">
            @csrf
            <input type="hidden" name="main_type" value="1">

            <div class="modal-field">
                <label for="poll_type" class="modal-label">نوع نظرسنجی</label>
                <select name="type" id="poll_type" class="modal-input">
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
                    <label for="poll_question" class="modal-label">سؤال نظرسنجی</label>
                    <input id="poll_question" type="text" name="question" class="modal-input" placeholder="متن سؤال را بنویسید">
                </div>
            </div>

            <div class="modal-field">
                <label class="modal-label d-flex align-items-center justify-content-between">
                    گزینه‌ها
                    <button type="button" class="btn btn-sm btn-outline-success" data-group-chat-action="add-poll-option">
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
                <button type="button" class="btn btn-outline-secondary" data-group-chat-action="close-poll-modal">انصراف</button>
                <button type="submit" class="btn btn-primary">انتشار نظرسنجی</button>
            </div>
        </form>
    </div>
</div>
