{{-- Modal مدیریت اعضا --}}
<div id="sessionRequestModal" class="session-participation-modal" hidden dir="rtl" aria-hidden="true">
    <button type="button" class="session-participation-modal__backdrop" data-session-modal-close aria-label="بستن"></button>
    <section class="session-participation-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sessionRequestTitle">
        <header class="session-participation-modal__header">
            <div class="session-participation-modal__icon"><i class="fas fa-hand-paper"></i></div>
            <div><h3 id="sessionRequestTitle">درخواست مشارکت در نشست</h3><p>مدیران و بازرسان درخواست شما را بررسی می‌کنند.</p></div>
            <button type="button" class="session-participation-modal__close" data-session-modal-close aria-label="بستن">×</button>
        </header>
        <div class="session-participation-modal__body">
            <label for="sessionRequestMessage">توضیح کوتاه <span>اختیاری</span></label>
            <textarea id="sessionRequestMessage" maxlength="300" rows="3" placeholder="برای چه موضوعی می‌خواهید مشارکت کنید؟"></textarea>
            <div id="sessionRequestStatus" class="session-participation-status" hidden></div>
        </div>
        <footer class="session-participation-modal__footer">
            <button type="button" class="session-primary-btn" id="submitSessionRequest"><i class="fas fa-hand-paper"></i> دست بلند کردن</button>
            <button type="button" class="session-secondary-btn" data-session-modal-close>فعلاً نه</button>
        </footer>
    </section>
</div>

@if(in_array((int)($yourRole ?? 0), [2,3], true))
<div id="sessionAdminModal" class="session-participation-modal" hidden dir="rtl" aria-hidden="true">
    <button type="button" class="session-participation-modal__backdrop" data-session-admin-close aria-label="بستن"></button>
    <section class="session-participation-modal__dialog session-participation-modal__dialog--admin" role="dialog" aria-modal="true" aria-labelledby="sessionAdminTitle">
        <header class="session-participation-modal__header">
            <div class="session-participation-modal__icon is-admin"><i class="fas fa-users-cog"></i></div>
            <div><h3 id="sessionAdminTitle">مدیریت مشارکت نشست</h3><p>درخواست‌ها را بررسی کنید یا چند عضو را هم‌زمان انتخاب کنید.</p></div>
            <button type="button" class="session-participation-modal__close" data-session-admin-close aria-label="بستن">×</button>
        </header>
        <div class="session-admin-toolbar">
            <input type="search" id="sessionMemberSearch" placeholder="جستجوی نام یا ایمیل...">
            <label><input type="checkbox" id="sessionSelectAll"> انتخاب همه نتایج</label>
        </div>
        <div id="sessionAdminStatus" class="session-participation-status" hidden></div>
        <div id="sessionAdminLoading" class="session-admin-empty"><i class="fas fa-spinner fa-spin"></i> در حال دریافت اعضا...</div>
        <div class="session-admin-columns" id="sessionAdminContent" hidden>
            <section><h4>درخواست‌های در انتظار <span id="sessionPendingCount">۰</span></h4><div id="sessionPendingList" class="session-member-list"></div></section>
            <section><h4>همه اعضا</h4><div id="sessionMembersList" class="session-member-list"></div></section>
        </div>
        <footer class="session-participation-modal__footer session-admin-actions">
            <button type="button" class="session-primary-btn" data-session-bulk-action="grant"><i class="fas fa-user-check"></i> اعطای مجوز</button>
            <button type="button" class="session-secondary-btn" data-session-bulk-action="revoke"><i class="fas fa-user-lock"></i> لغو مجوز</button>
            <button type="button" class="session-danger-btn" data-session-bulk-action="reject">رد درخواست</button>
        </footer>
    </section>
</div>
@endif

<div id="manageMembersModal" class="modal-shell" style="display: none;" dir="rtl"
    data-chat-page-action="modal-backdrop" data-modal-id="manageMembersModal">
    <div class="modal-shell__dialog">
        <div class="modal-shell__header">
            <h3 class="modal-shell__title"><i class="fas fa-users-cog me-2 text-blue-500"></i> مدیریت اعضای گروه</h3>
            <button type="button" class="modal-shell__close" data-chat-page-action="close-modal" data-modal-id="manageMembersModal">×</button>
        </div>
        <div class="modal-shell__form">
            <div id="members-loading" class="text-center py-8" style="display: none;">
                <i class="fas fa-spinner fa-spin text-2xl text-blue-500"></i>
                <p class="mt-2 text-slate-600">در حال بارگذاری...</p>
            </div>
            <div id="members-error" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-4" style="display: none;">
                <i class="fas fa-exclamation-circle ml-2"></i><span id="members-error-text"></span>
            </div>
            <div id="members-list" style="max-height: 400px; overflow-y: auto; padding: 0.5rem 0; min-height: 100px; display: block; visibility: visible;"></div>
        </div>
    </div>
</div>

{{-- Modal مدیریت گزارش‌ها --}}
@if(($yourRole ?? 0) == 3)
<div id="manageReportsModal" class="modal-shell" style="display: none;" dir="rtl"
    data-chat-page-action="modal-backdrop" data-modal-id="manageReportsModal">
    <div class="modal-shell__dialog" style="max-width: 900px; width: 90vw;">
        <div class="modal-shell__header">
            <h3 class="modal-shell__title"><i class="fas fa-flag me-2 text-orange-500"></i> مدیریت گزارش‌های پیام</h3>
            <button type="button" class="modal-shell__close" data-chat-page-action="close-modal" data-modal-id="manageReportsModal">×</button>
        </div>
        <div class="modal-shell__form">
            <div id="reports-loading" class="text-center py-8" style="display: none;">
                <i class="fas fa-spinner fa-spin text-2xl text-orange-500"></i>
                <p class="mt-2 text-slate-600">در حال بارگذاری...</p>
            </div>
            <div id="reports-error" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-4" style="display: none;">
                <i class="fas fa-exclamation-circle ml-2"></i><span id="reports-error-text"></span>
            </div>
            <div id="reports-list" class="space-y-3 max-h-96 overflow-y-auto"></div>
        </div>
    </div>
</div>
@endif
