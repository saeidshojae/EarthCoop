{{-- Modal مدیریت اعضا --}}
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
