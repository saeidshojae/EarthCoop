<section id="group-pin-navigator" class="group-pin-navigator" hidden aria-label="محتواهای سنجاق‌شده">
    <button type="button" class="group-pin-navigator__main" data-chat-page-action="open-pin-list">
        <span class="group-pin-navigator__icon"><i class="fas fa-thumbtack"></i></span>
        <span class="group-pin-navigator__copy">
            <span class="group-pin-navigator__eyebrow"><span data-pin-label>سنجاق‌شده</span> <b data-pin-position></b></span>
            <span class="group-pin-navigator__preview" data-pin-preview></span>
        </span>
    </button>
    <div class="group-pin-navigator__controls">
        <button type="button" data-chat-page-action="previous-pin" aria-label="مورد سنجاق‌شده قبلی"><i class="fas fa-chevron-up"></i></button>
        <button type="button" data-chat-page-action="next-pin" aria-label="مورد سنجاق‌شده بعدی"><i class="fas fa-chevron-down"></i></button>
        <button type="button" class="group-pin-navigator__count" data-chat-page-action="open-pin-list" aria-label="نمایش همه سنجاق‌شده‌ها"><span data-pin-count>0</span></button>
    </div>
</section>

<div id="group-pin-list-modal" class="group-pin-modal" hidden>
    <div class="group-pin-modal__backdrop" data-chat-page-action="close-pin-list"></div>
    <section class="group-pin-modal__panel" role="dialog" aria-modal="true" aria-labelledby="group-pin-list-title">
        <header class="group-pin-modal__header">
            <div><h3 id="group-pin-list-title">سنجاق‌شده‌های گروه</h3><p>برای رفتن مستقیم به هر محتوا، آن را انتخاب کنید.</p></div>
            <button type="button" data-chat-page-action="close-pin-list" aria-label="بستن"><i class="fas fa-times"></i></button>
        </header>
        <div class="group-pin-modal__list" data-pin-list></div>
        <p class="group-pin-modal__empty" data-pin-empty hidden>هنوز محتوایی سنجاق نشده است.</p>
    </section>
</div>
