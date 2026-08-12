<div id="sessionScheduleModal" class="session-schedule-modal" hidden aria-hidden="true">
    <button type="button" class="session-schedule-modal__backdrop" data-session-schedule-close aria-label="بستن"></button>
    <section class="session-schedule-modal__panel" role="dialog" aria-modal="true" aria-labelledby="sessionScheduleTitle">
        <header>
            <div><small>مدیریت نشست</small><h3 id="sessionScheduleTitle">برگزاری جلسه جدید</h3></div>
            <button type="button" data-session-schedule-close aria-label="بستن"><i class="fas fa-times"></i></button>
        </header>
        <form id="sessionScheduleForm">
            <label>نام جلسه<input name="title" maxlength="160" required placeholder="مثلاً جلسه بررسی برنامه ماهانه"></label>
            <label>موضوع<input name="subject" maxlength="1000" placeholder="موضوع اصلی جلسه"></label>
            <label>دستور جلسه<textarea name="agenda" maxlength="3000" rows="4" placeholder="محورهای گفتگو را هر کدام در یک سطر بنویسید"></textarea></label>
            <label>زمان آغاز<input name="starts_at" type="datetime-local"><small>اگر خالی بماند، جلسه همین حالا آغاز می‌شود.</small></label>
            <div id="sessionScheduleStatus" class="session-schedule-status" hidden></div>
            <footer><button type="button" class="btn-cancel" data-session-schedule-close>انصراف</button><button type="submit" class="btn-submit"><i class="fas fa-calendar-check"></i> ثبت جلسه</button></footer>
        </form>
    </section>
</div>
