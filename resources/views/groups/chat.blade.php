@extends('layouts.chat')

@php
$lastReadMessageId = isset($lastReadMessageId) ? $lastReadMessageId : (auth()->user()->last_read_message_id ?? 'null');
@endphp

@section('title', $group->name . ' - گفت‌وگوی گروه')

@section('head-tag')

<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
// مطمئن شو Select2 بعد از jQuery لود شده
if (typeof jQuery !== 'undefined') {
    jQuery.fn.select2.defaults.set('language', {
        noResults: function() {
            return "نتیجه‌ای یافت نشد";
        },
        searching: function() {
            return "در حال جستجو...";
        }
    });
}
</script>

<script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>



<!-- CSRF Token (برای Ajax) -->
<meta name="csrf-token" content="{{ csrf_token() }}">


<link rel="stylesheet" href="{{ asset('Css/group-chat.css') }}">
<script>
document.addEventListener("DOMContentLoaded", function() {
    const csrf = '{{ csrf_token() }}';

    // Event listener برای لینک‌های پروفایل - باید اول اجرا شود
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a.message-sender');
        if (link && link.href && !link.href.includes('#')) {
            // اجازه بده لینک کار کند - هیچ کاری نکن
            // فقط مطمئن شو که event propagation متوقف نمی‌شود
            return true;
        }
    }, true); // استفاده از capture phase برای اجرای زودتر

    // تست: بررسی وجود لینک‌ها در DOM
    setTimeout(function() {
        const links = document.querySelectorAll('a.message-sender');
        console.log('Found profile links:', links.length);
        links.forEach((link, index) => {
            console.log(`Link ${index}:`, link.href, link.textContent);
        });
    }, 500);

    // Event delegation برای لینک‌های پروفایل - ساده و مستقیم
    document.addEventListener('click', function(e) {
        // بررسی اینکه آیا کلیک روی لینک پروفایل است
        const link = e.target.closest('a.message-sender');
        if (link) {
            const href = link.getAttribute('href');
            console.log('Profile link clicked!', href, e.target);
            if (href && href.includes('/profile-member/')) {
                e.stopPropagation();
                e.preventDefault();
                e.stopImmediatePropagation();
                console.log('Navigating to:', href);
                window.location.href = href;
                return false;
            }
        }
    }, true); // استفاده از capture phase برای اجرای زودتر

    // Initialize click handlers for existing profile links
    setTimeout(function() {
        document.querySelectorAll('a.message-sender').forEach(link => {
            console.log('Attaching click handler to link:', link.href);
            link.addEventListener('click', function(e) {
                console.log('Direct click handler fired!', this.href);
                e.stopPropagation();
                e.preventDefault();
                e.stopImmediatePropagation();
                const href = this.getAttribute('href');
                if (href && href.includes('/profile-member/')) {
                    console.log('Navigating to:', href);
                    window.location.href = href;
                }
            }, true); // استفاده از capture phase
        });
    }, 100);

    // --- Helpers: ایجاد/نمایش/مخفی‌کردن overlay ---
    function ensureOverlay() {
        let el = document.getElementById('global-loading');
        if (!el) {
            el = document.createElement('div');
            el.id = 'global-loading';
            el.className = 'loading-overlay';
            el.innerHTML = '<div class="spinner"></div>';
            document.body.appendChild(el);
        }
        return el;
    }
    const overlay = ensureOverlay();
    const showOverlay = () => overlay.classList.add('show');
    const hideOverlay = () => overlay.classList.remove('show');

    // کلیدکردن روی یک دکمه: حالت لودینگ محلی
    function setBtnLoading(btn, on = true) {
        if (!btn) return;
        if (on) {
            btn.classList.add('btn-loading');
            btn.disabled = true;
        } else {
            btn.classList.remove('btn-loading');
            btn.disabled = false;
        }
    }

    // پوشش‌دهنده‌ی عمومی برای fetch با چرخنده
    async function withSpinner(fn, {
        global = true,
        btn = null
    } = {}) {
        try {
            if (global) showOverlay();
            if (btn) setBtnLoading(btn, true);
            return await fn();
        } finally {
            if (global) hideOverlay();
            if (btn) setBtnLoading(btn, false);
        }
    }

    document.querySelectorAll(".message-bubble").forEach(bubble => {
        const id = bubble.dataset.messageId;
        const editUrl = bubble.dataset.editUrl;
        const deleteUrl = bubble.dataset.deleteUrl;
        const reportUrl = bubble.dataset.reportUrl;

        if (!id) {
            console.warn('message id missing', bubble);
            return;
        }


        // حذف
        bubble.querySelector(".btn-delete")?.addEventListener("click", async (e) => {
            const btn = e.currentTarget;
            if (!confirm("آیا از حذف پیام مطمئن هستید؟")) return;

            await withSpinner(async () => {
                const res = await fetch(deleteUrl, {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": csrf,
                        "Accept": "application/json"
                    },
                    credentials: "same-origin"
                });

                let data = {};
                try {
                    data = await res.json();
                } catch (e) {}

                if (res.ok && (data.status === 'success' || !data.status)) {
                    // ✅ FIXED: Remove message from DOM smoothly
                    const msgEl = document.getElementById(`msg-${id}`);
                    if (msgEl) {
                        msgEl.style.transition = 'opacity 0.3s';
                        msgEl.style.opacity = '0';
                        setTimeout(() => msgEl.remove(), 300);
                    }
                    // اگر این پیام reply تارگت بود، reply indicator را پاک کن
                    const parentInput = document.getElementById('parent_id');
                    if (parentInput && parentInput.value == id) {
                        if (typeof cancelReply === 'function') cancelReply();
                    }
                } else {
                    alert(data.message ||
                        `خطا در حذف پیام (status ${res.status})`);
                }
            }, {
                global: true,
                btn
            });
        });

        // گزارش
        bubble.querySelector(".btn-report")?.addEventListener("click", async (e) => {
            const btn = e.currentTarget;
            const reason = prompt("دلیل گزارش را وارد کنید:");
            if (!reason) return;

            await withSpinner(async () => {
                const res = await fetch(reportUrl, {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": csrf,
                        "Accept": "application/json",
                        "Content-Type": "application/json"
                    },
                    credentials: "same-origin",
                    body: JSON.stringify({
                        reason
                    })
                });

                let data = {};
                try {
                    data = await res.json();
                } catch (e) {}
                alert(data.message || (res.ok ? "گزارش ثبت شد" :
                    `خطا در ثبت گزارش (status ${res.status})`));
            }, {
                global: true,
                btn
            });
        });
    });
});
</script>



<script>
const groupId = {{ $group->id }};
window.groupChatTransport = @json(config('group-chat.transport', 'auto'));
const authUserId = {{ auth()->id() }};
const yourRole = {{ $yourRole ?? 0 }};
window.groupId = groupId;
window.authUserId = authUserId;
window.yourRole = yourRole;
const manageCount = {{ $groupSetting ? $groupSetting->manager_count : 0 }};
const inspectorCount = {{ $groupSetting ? $groupSetting->inspector_count : 0 }};
</script>
<script src="{{ asset('js/group-chat.js') }}" defer></script>
<script src="{{ asset('js/chat-features.js') }}" defer></script>
<script src="{{ asset('js/voice-recorder.js') }}" defer></script>
<script>
// ===== Voice Optimistic Override =====
// وقتی دکمه ارسال صوتی ظاهر شد، click رو intercept می‌کنم
// تا بلافاصله modal بسته شه + placeholder نشون داده بشه
(function() {
    const _observeForSendBtn = new MutationObserver(function(mutations) {
        mutations.forEach(function(m) {
            m.addedNodes.forEach(function(node) {
                if (node.nodeType !== 1) return;
                const btn = node.id === 'send-recording-btn' ? node
                          : node.querySelector && node.querySelector('#send-recording-btn');
                if (!btn) return;
                // capture phase: قبل از handler اصلی اجرا می‌شه
                btn.addEventListener('click', function _voiceOptimistic(e) {
                    const blob = window.recordedAudioBlob;
                    if (!blob) return;
                    const blobUrl = URL.createObjectURL(blob);
                    const tempId = 'voice_temp_' + Date.now();
                    // بستن modal از طریق کلیک دکمه لغو (یا مستقیم hide)
                    const modal = document.getElementById('voice-recording-modal');
                    if (modal) {
                        modal.style.opacity = '0';
                        modal.style.transition = 'opacity 0.2s';
                        setTimeout(() => { modal.style.display = 'none'; modal.style.opacity = ''; }, 200);
                    }
                    // نمایش پیام صوتی optimistic
                    if (typeof appendMessage === 'function') {
                        try {
                            appendMessage({
                                id: tempId,
                                user_id: window.authUserId || 0,
                                message: '🎤 پیام صوتی',
                                created_at: new Date().toLocaleTimeString('fa-IR', {hour:'2-digit', minute:'2-digit'}),
                                sender: 'شما',
                                voice_message: blobUrl,
                                file_type: blob.type || 'audio/webm',
                                _isOptimistic: true
                            });
                        } catch(err) { console.warn('voice optimistic err:', err); }
                    }
                    // بعد از موفقیت fetch اصلی، temp را جایگزین کن
                    const _origOnClick = btn._origOnClick;
                    window._voiceTempId = tempId;
                    window._voiceBlobUrl = blobUrl;
                }, true); // capture=true → قبل از onclick اصلی
                _observeForSendBtn.disconnect();
            });
        });
    });
    _observeForSendBtn.observe(document.body, { childList: true, subtree: true });

    // وقتی polling پیام صوتی واقعی رو دید، temp رو حذف کن
    const _origAppend = window.appendMessage;
    document.addEventListener('DOMContentLoaded', function() {
        // override appendMessage تا اگر temp وجود دارد جایگزین کند
        if (typeof appendMessage === 'function') {
            const _origFn = appendMessage;
            window.appendMessage = function(msg) {
                // اگر voice_message داره و temp در DOM هست، temp رو حذف کن
                if (msg && msg.voice_message && msg.id && !String(msg.id).startsWith('voice_temp_') && window._voiceTempId) {
                    const tempEl = document.getElementById('msg-' + window._voiceTempId);
                    if (tempEl) { tempEl.remove(); }
                    try { URL.revokeObjectURL(window._voiceBlobUrl); } catch(e) {}
                    window._voiceTempId = null;
                    window._voiceBlobUrl = null;
                }
                return _origFn(msg);
            };
        }
    });
})();
</script>

<!-- کد حفظ موقعیت scroll به انتهای صفحه منتقل شد -->
<style>
/* Collapsible Group Info Card برای موبایل */
.group-info-card [x-cloak] {
    display: none !important;
}

/* در موبایل: collapse-content مخفی است مگر اینکه expanded باشد */
.group-info-card .collapse-content {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.35s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s ease-out, padding 0.3s ease-out;
    opacity: 0;
    padding-top: 0;
    padding-bottom: 0;
}

/* بهبود ظاهر کارت در موبایل */
@media (max-width: 1023px) {
    .group-info-card {
        box-shadow: 0 2px 12px rgba(16, 185, 129, 0.1);
    }
}

.group-info-card [x-show="expanded"].collapse-content {
    max-height: 5000px;
    opacity: 1;
    padding-top: 1.25rem;
    padding-bottom: 1.25rem;
    transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.4s ease-in, padding 0.3s ease-in;
}

/* در دسکتاپ: collapse-content باید مخفی بماند (نسخه جداگانه داریم) */
@media (min-width: 1024px) {
    .group-info-card .collapse-content {
        display: none !important;
    }
}

.stat-chip {
    display: flex;
    flex-direction: column;
    gap: .35rem;
    padding: 1rem 1.1rem;
    border-radius: 1.5rem;
    background: rgba(236, 253, 245, 0.8);
    border: 1px solid rgba(16, 185, 129, 0.18);
    box-shadow: 0 18px 48px -30px rgba(16, 185, 129, 0.5);
}

.stat-chip__label {
    font-size: .75rem;
    font-weight: 600;
    color: #047857;
}

.stat-chip__value {
    font-size: 1.25rem;
    font-weight: 800;
    color: #0f4c3a;
}

.chat-wrapper {
    position: relative;
    width: 100%;
}

.chat-scroll-btn {
    position: absolute;
    bottom: 1.5rem !important;
    left: 1.5rem !important;
    right: auto !important;
    z-index: 150;
    width: 46px;
    height: 46px;
    border-radius: 50%;
    border: none;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #fff;
    font-size: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    opacity: 0;
    visibility: hidden;
    transform: scale(0.5);
}

.chat-scroll-btn.visible {
    opacity: 1;
    visibility: visible;
    transform: scale(1);
}

.chat-scroll-btn:hover {
    transform: scale(1.1) translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
}

.group-info-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.35);
    backdrop-filter: blur(2px);
    z-index: 900;
    opacity: 0;
    transition: opacity .3s ease;
}

.group-info-backdrop--visible {
    opacity: 1;
}

.menu-item {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    font-size: 0.875rem;
    padding: 4px 8px;
    border: none;
    background: transparent;
    cursor: pointer;
    color: #444;
    transition: color .2s;
}

.menu-item .icon {
    width: 14px;
    height: 14px;
}

.menu-item:hover {
    color: #0d6efd;
}

.menu-item.text-danger {
    color: #dc3545;
}

.menu-item.text-danger:hover {
    color: #bb2d3b;
}

/* --- REFINED SIZING & PROPORTIONS --- */

/* 1. Chat Container Spacing */
#chat-box {
    padding: 1.25rem !important;
    gap: 0.6rem !important;
    display: flex;
    flex-direction: column;
    height: 75vh !important;
    overflow-y: auto !important;
    position: relative;
    scrollbar-width: thin;
    scrollbar-color: #10b981 transparent;
}

.chat-wrapper {
    position: relative !important;
    width: 100%;
}

/* Floating Scroll Button Fix - VISIBLE WHEN SCROLLING DOWN */
.chat-scroll-btn {
    position: fixed !important;
    bottom: 30px !important;
    left: 30px !important;
    right: auto !important;
    z-index: 9999 !important;
    width: 50px !important;
    height: 50px !important;
    border-radius: 50% !important;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
    color: #fff !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3) !important;
    opacity: 0 !important;
    visibility: hidden !important;
    transform: scale(0.5) !important;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
    cursor: pointer !important;
}

.chat-scroll-btn.visible {
    opacity: 1 !important;
    visibility: visible !important;
    transform: scale(1) !important;
}

.chat-scroll-btn:hover {
    transform: scale(1.1) translateY(-3px) !important;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3) !important;
}

.chat-scroll-btn.visible {
    opacity: 1 !important;
    visibility: visible !important;
    transform: scale(1) !important;
}

/* 2. Message Bubbles Sizing */
.message-bubble {
    position: relative;
    max-width: 82% !important;
    /* Increased for better desktop readability */
    min-width: 60px !important;
    /* Reduced from 200px to fit short messages */
    padding: 0.6rem 0.9rem !important;
    margin-bottom: 0.2rem !important;
    border-radius: 12px !important;
    /* Slightly more modern radius */
    font-size: 0.94rem !important;
    line-height: 1.55 !important;
}

.message-bubble.you {
    border-radius: 16px 16px 4px 16px !important;
}

.message-bubble.other {
    border-radius: 16px 16px 16px 4px !important;
}

/* 3. Pinned Messages Layout */
.pinned-messages {
    padding: 0.75rem 1rem !important;
    max-height: 110px !important;
    gap: 8px !important;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08) !important;
}

.pin {
    padding: 0.5rem 0.75rem !important;
    border-radius: 10px !important;
    font-size: 0.9rem !important;
}

/* 4. Chat Footer & Input Sizing */
.telegram-input-container {
    padding: 5px 10px !important;
    gap: 10px !important;
    border-radius: 26px !important;
    min-height: 46px !important;
    align-items: center !important;
    /* Better vertical alignment */
}

.telegram-send-btn,
#voice-record-btn {
    width: 38px !important;
    height: 38px !important;
    min-width: 38px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-radius: 50% !important;
}

.telegram-textarea {
    padding: 8px 5px !important;
    font-size: 0.95rem !important;
    max-height: 150px !important;
}

/* 5. Reply Preview Proportions */
.reply-preview {
    padding: 6px 10px !important;
    border-radius: 8px !important;
    margin-bottom: 6px !important;
    font-size: 0.85rem !important;
}

/* 6. Sender Name Sizing */
.message-sender {
    font-size: 0.88rem !important;
    margin-bottom: 3px !important;
}

/* --- END REFINED SIZING --- */

/* 7. Search & Dropdown Refinements */
.gc-searchbar {
    padding: 0.35rem 0.75rem !important;
    border-radius: 12px !important;
}

.gc-search-item {
    padding: 0.5rem 0.75rem !important;
    gap: 0.75rem !important;
}

.menu-dropdown {
    padding: 5px !important;
    border-radius: 12px !important;
    min-width: 180px !important;
}

.menu-item {
    padding: 7px 10px !important;
    font-size: 0.88rem !important;
}

.message-header {
    display: flex;
    align-items: center;
    gap: 8px
}

.message-sender {
    font-size: .85rem;
    font-weight: 600;
    color: #2b5278;
    padding: 0;
    margin-bottom: 2px;
    margin-left: 0;
}

.message-content {
    margin: .25rem 0 .15rem;
    line-height: 1.4;
    font-size: 0.95rem;
}

.message-menu {
    margin-inline-start: auto;
    position: relative
}

.menu-trigger {
    cursor: pointer;
    list-style: none;
    border: none;
    background: transparent;
    font-size: 20px;
    line-height: 1
}

.message-menu>summary::-webkit-details-marker {
    display: none
}

.menu-dropdown {
    position: absolute;
    inset-inline-end: 0;
    top: 22px;
    background: #fff;
    border: 1px solid #e4e4e7;
    border-radius: 10px;
    min-width: 190px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, .08);
    padding: 6px;
    z-index: 10
}

/* تمام‌صفحه */
.loading-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .25);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.loading-overlay.show {
    display: flex;
}

.spinner {
    width: 48px;
    height: 48px;
    border: 4px solid #fff;
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin .9s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* حالت دکمه درحال پردازش */
.btn-loading {
    position: relative;
    pointer-events: none;
    opacity: .7;
}

.btn-loading::after {
    content: "";
    position: absolute;
    right: .5rem;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    border: 2px solid currentColor;
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin .8s linear infinite;
}

.menu-item {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 8px;
    text-align: inherit;
    background: transparent;
    border: 0;
    padding: 8px 10px;
    border-radius: 8px;
    cursor: pointer;
    font-size: .92rem
}

.menu-item:hover {
    background: #f5f5f5
}

.menu-item svg {
    flex: 0 0 auto
}

.menu-meta-time {
    margin-top: 6px;
    padding: 8px 10px;
    font-size: .75rem;
    color: #666;
    border-top: 1px solid #eee;
    direction: rtl;
}

.menu-meta-time__item {
    display: flex;
    align-items: center;
    gap: 4px;
    line-height: 1.5;
}

.menu-meta-time__label {
    font-weight: 600;
    color: #555;
    font-size: 0.75rem;
}

.menu-meta-time__value {
    color: #777;
    font-size: 0.75rem;
    font-family: 'Courier New', monospace;
}

.message-row {
    display: flex;
    align-items: flex-start;
    margin: 0;
    gap: 8px;
    padding: 0;
    width: 100%;
}

.message-row.you {
    flex-direction: row;
    justify-content: flex-end;
}

.message-row.other {
    flex-direction: row;
    justify-content: flex-start;
}

.avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #d1d5db;
    color: #333;
    font-weight: 600;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 2px;
}

.message-bubble {
    position: relative;
    max-width: 75% !important;
    min-width: 200px !important;
    background: #ffffff;
    padding: 6px 10px 4px 10px !important;
    border-radius: 7px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    border: 1px solid #e5e5e5;
    display: inline-block;
}

.message-bubble.you {
    background: #dcf8c6 !important;
    border-radius: 7px 7px 7px 0 !important;
    border: none !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    margin-left: auto !important;
    margin-right: 0 !important;
}

.message-bubble.other {
    background: #ffffff !important;
    border-radius: 7px 7px 0 7px !important;
    border: 1px solid #e5e5e5 !important;
    margin-right: auto !important;
    margin-left: 0 !important;
}

.message-row.you .message-bubble {
    background: #dcf8c6 !important;
    border-radius: 7px 7px 7px 0 !important;
    border: none !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    margin-left: auto !important;
    margin-right: 0 !important;
}

.message-row.other .message-bubble {
    background: #ffffff !important;
    border-radius: 7px 7px 0 7px !important;
    border: 1px solid #e5e5e5 !important;
    margin-right: auto !important;
    margin-left: 0 !important;
}

.message-sender {
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 2px;
    display: block;
    color: #2b5278;
}

.message-content {
    margin: 0;
    font-size: 0.95rem;
    line-height: 1.3;
    word-wrap: break-word;
    display: inline-block;
    width: 100%;
}

/* تاریخ ارسال/ویرایش - شبیه تلگرام */
.message-timestamp {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
    margin-top: 2px;
    padding-top: 0;
    font-size: 0.7rem;
    color: #999;
    direction: ltr;
    text-align: right;
    float: right;
    clear: both;
}

.message-row.you .message-timestamp {
    justify-content: flex-end;
    color: #667781;
    float: right;
}

.message-row.other .message-timestamp {
    justify-content: flex-end;
    color: #999;
    float: right;
}

.message-time {
    font-size: 0.7rem;
    opacity: 0.8;
}

.message-edited {
    font-size: 0.65rem;
    opacity: 0.7;
    margin-right: 2px;
}

.message-edited i {
    font-size: 0.7rem;
}

/* ریپلای شبیه تلگرام */
.reply-preview {
    border-left: 3px solid #3b82f6;
    /* رنگ آبی تلگرامی */
    padding-left: 6px;
    margin-bottom: 4px;
    font-size: 0.8rem;
    background: rgba(59, 130, 246, 0.08);
    border-radius: 4px;
    padding: .2rem .4rem;
}

.reply-sender {
    font-weight: 600;
    color: #1e40af;
    font-size: 0.78rem;
}

.reply-text {
    color: #333;
    font-size: 0.75rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* منوی سه‌نقطه */
.menu-wrapper {
    position: absolute;
    top: 4px;
}

.menu-wrapper.right {
    left: .3rem;
}

/* برای دیگران */
.menu-wrapper.left {
    right: .2rem;
}

/* برای خودم */

.menu-trigger {
    border: none;
    background: transparent;
    font-size: 16px;
    cursor: pointer;
    padding: 0;
    line-height: 1;
}

.menu-wrapper>summary::-webkit-details-marker {
    display: none;
}

.menu-dropdown {
    position: absolute;
    top: 20px;
    left: 0;
    background: #fff;
    border: 1px solid #e4e4e7;
    border-radius: 8px;
    min-width: 120px;
    padding: 4px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    z-index: 10;
}

.menu-item {
    display: block;
    width: 100%;
    padding: 6px 8px;
    background: transparent;
    border: none;
    text-align: right;
    font-size: 0.85rem;
    cursor: pointer;
}

.menu-item:hover {
    background: #f5f5f5;
}

.menu-meta-time {
    margin-top: 4px;
    padding: 6px 8px;
    font-size: 0.7rem;
    color: #777;
    border-top: 1px solid #eee;
}




#categoryBlogsModal {
    display: flex;
    justify-content: center;
}

.main-section {
    padding: 0 !important;
    margin: 0 !important;
    max-width: 100% !important;
}

/* با layout جدید (chat layout) header اصلی حذف شده و header مینی کوچک است */
/* padding-top در inline style تنظیم شده است */


@media screen and (min-width: 768px) {
    .pinned-messages {
        width: calc(100% - 400px) !important;
    }
}

#cke_1_top {
    display: none;
}

#cke_2_top {
    display: none;
}

.cke_top {
    display: none !important;
}

.cke_bottom {
    display: none !important;

}

#cke_2_bottom {
    display: none;

}

#cke_3_bottom {
    display: none;

}

.cke_notification {
    display: none !important;

}

#cke_post_editor {
    overflow: auto;
    height: 7rem;
    margin-bottom: .5rem;
}

#cke_message_editor {
    overflow: auto;
    height: 2rem !important;
    width: 100%;
}

.cke_contents {
    overflow: auto;
    height: 5rem !important;
    width: 100%;
}

.cke_editable {
    margin: .4rem !important;
}

#postFormBox {
    height: auto;
}

/* سرچ‌باکس */
.gc-searchbar {
    position: relative;
    display: flex;
    align-items: center;
    gap: .5rem;
    background: #fff;
    border: 1px solid #e4e4e7;
    border-radius: 10px;
    padding: .4rem .6rem;
    width: min(520px, 98%);
    direction: rtl;
}

.gc-searchbar>i {
    opacity: .6;
}

#gc-search-input {
    flex: 1;
    border: 0;
    outline: 0;
    font: inherit;
    background: transparent;
    direction: rtl;
}

#gc-search-clear {
    border: 0;
    background: transparent;
    cursor: pointer;
    opacity: .6;
}

.gc-search-dropdown {
    position: absolute;
    inset-inline-start: 0;
    top: 110%;
    width: 100%;
    background: #fff;
    border: 1px solid #e4e4e7;
    border-radius: 10px;
    box-shadow: 0 10px 24px rgba(0, 0, 0, .08);
    padding: .3rem;
    z-index: 30;
}

.gc-search-status {
    font-size: .85rem;
    color: #666;
    padding: .4rem .5rem;
}

.gc-search-list {
    list-style: none;
    margin: 0;
    padding: 0;
    max-height: 50vh;
    overflow: auto;
}

.gc-search-item {
    display: flex;
    gap: .6rem;
    padding: .55rem .6rem;
    border-radius: 8px;
    cursor: pointer;
    direction: rtl;
}

.gc-search-item:hover,
.gc-search-item.active {
    background: #f6f7fb;
}

.gc-search-item .type {
    font-size: .75rem;
    opacity: .7;
    min-width: 70px;
    text-align: center;
    padding: .1rem .3rem;
    border: 1px solid #eee;
    border-radius: 999px;
}

.gc-search-item .meta {
    display: flex;
    flex-direction: column;
    gap: .15rem;
    min-width: 0;
}

.gc-search-item .title {
    font-weight: 600;
    font-size: .9rem;
    color: #1f2937;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.gc-search-item .snip {
    font-size: .85rem;
    color: #374151;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
}

.gc-search-item mark {
    background: #ffe58f;
}

.gc-search-more {
    width: 100%;
    padding: .45rem .7rem;
    border: 1px solid #e5e7eb;
    background: #f9fafb;
    border-radius: 8px;
    cursor: pointer;
    margin-top: .4rem;
}

@media (max-width: 768px) {
    .gc-searchbar {
        width: 100%;
    }
}

/* آیکن جستجو در هدر */
.btn-chat-icon {
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: transparent;
    cursor: pointer;
}

.btn-chat-icon:hover {
    background: #f3f4f6;
}

.btn-chat-icon.searching i {
    position: relative;
}

.btn-chat-icon.searching i::after {
    content: "";
    position: absolute;
    inset: auto auto -2px -2px;
    width: 14px;
    height: 14px;
    border: 2px solid currentColor;
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin .8s linear infinite;
}

/* کانتینر سرچ زیر هدر */
.chat-header {
    position: relative;
}

.gc-search-wrap {
    position: absolute;
    inset-inline-end: 0;
    top: 100%;
    margin-top: 8px;
    z-index: 50;
    right: 1rem !important;
    width: min(560px, 92vw);
}

/* خود سرچ‌بار و دراپ‌داون (از کد قبلی‌ات) */
.gc-searchbar {
    position: relative;
    display: flex;
    align-items: center;
    gap: .5rem;
    background: #fff;
    border: 1px solid #e4e4e7;
    border-radius: 10px;
    padding: .4rem .6rem;
    direction: rtl;
    box-shadow: 0 10px 24px rgba(0, 0, 0, .08);
}

.gc-searchbar>i {
    opacity: .6;
}

#gc-search-input {
    flex: 1;
    border: 0;
    outline: 0;
    font: inherit;
    background: transparent;
    direction: rtl;
}

#gc-search-clear {
    border: 0;
    background: transparent;
    cursor: pointer;
    opacity: .6;
}

.gc-search-dropdown {
    position: absolute;
    inset-inline-start: 0;
    top: 110%;
    width: 100%;
    background: #fff;
    border: 1px solid #e4e4e7;
    border-radius: 10px;
    box-shadow: 0 10px 24px rgba(0, 0, 0, .08);
    padding: .3rem;
    z-index: 30;
}

.gc-search-status {
    font-size: .85rem;
    color: #666;
    padding: .4rem .5rem;
    display: none;
    align-items: center;
    gap: .4rem;
}

.gc-spin {
    width: 16px;
    height: 16px;
    border: 2px solid #bbb;
    border-top-color: transparent;
    border-radius: 50%;
    display: inline-block;
    animation: spin .8s linear infinite;
    vertical-align: middle;
}

.gc-search-list {
    list-style: none;
    margin: 0;
    padding: 0;
    max-height: 50vh;
    overflow: auto;
}

.gc-search-item {
    display: flex;
    gap: .6rem;
    padding: .55rem .6rem;
    border-radius: 8px;
    cursor: pointer;
    direction: rtl;
}

.gc-search-item:hover,
.gc-search-item.active {
    background: #f6f7fb;
}

.gc-search-item .type {
    font-size: .75rem;
    opacity: .7;
    min-width: 70px;
    text-align: center;
    padding: .1rem .3rem;
    border: 1px solid #eee;
    border-radius: 999px;
}

.gc-search-item .meta {
    display: flex;
    flex-direction: column;
    gap: .15rem;
    min-width: 0;
}

.gc-search-item .title {
    font-weight: 600;
    font-size: .9rem;
    color: #1f2937;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.gc-search-item .snip {
    font-size: .85rem;
    color: #374151;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
}

.gc-search-item mark {
    background: #ffe58f;
}

.gc-search-more {
    width: 100%;
    padding: .45rem .7rem;
    border: 1px solid #e5e7eb;
    background: #f9fafb;
    border-radius: 8px;
    cursor: pointer;
    margin-top: .4rem;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.chat-header {
    position: relative;
}

.gc-search-wrap {
    position: absolute;
    inset-inline-end: 0;
    top: 100%;
    margin-top: 8px;
    width: min(560px, 92vw);
    z-index: 2000;
    /* از dropdown بالاتر باشه */
}

.btn-chat-icon {
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: transparent;
    cursor: pointer;
}

.btn-chat-icon:hover {
    background: #f3f4f6;
}

.gc-searchbar {
    position: relative;
    display: flex;
    align-items: center;
    gap: .5rem;
    background: #fff;
    border: 1px solid #e4e4e7;
    border-radius: 10px;
    padding: .4rem .6rem;
    direction: rtl;
    box-shadow: 0 10px 24px rgba(0, 0, 0, .08);
}

.gc-search-dropdown {
    position: absolute;
    inset-inline-start: 0;
    top: 110%;
    width: 100%;
    background: #fff;
    border: 1px solid #e4e4e7;
    border-radius: 10px;
    box-shadow: 0 10px 24px rgba(0, 0, 0, .08);
    padding: .3rem;
    z-index: 2100;
}

.gc-search-status {
    display: none;
    align-items: center;
    gap: .4rem;
}

.gc-spin {
    width: 16px;
    height: 16px;
    border: 2px solid #bbb;
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin .8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
</style>

@endsection

@section('content')
@php
$memberCount = $group->userCount();
$guestCount = $group->guestsCount();
$blogCount = \App\Models\Blog::where('group_id', $group->id)->count();
$pollCount = $group->polls()->count();
// از $yourRole که controller محاسبه کرده استفاده می‌کنیم
// دیگر نیازی به کوئری users() در blade نیست
$pivotUser = \App\Models\GroupUser::where('group_id', $group->id)->where('user_id', auth()->id())->first();
$roleValue = $yourRole;

$roleTitle = match($roleValue) {
0 => 'ناظر',
1 => 'فعال',
2 => 'بازرس',
3 => 'مدیر',
4 => 'مهمان',
5 => 'فعال ۲',
default => 'عضو'
};
$membershipStatusLabel = (int)($pivotUser?->status ?? 0) === 1 ? 'فعال' : 'غیرفعال';
$checkBlockElection = \App\Models\Block::where('user_id', auth()->id())->where('position', 'election')->first();
$electionAvailable = ($election ?? null) && optional($groupSetting)->election_status == 1;
$canParticipateElection = $electionAvailable && !$checkBlockElection && optional(auth()->user())->status == 1;
@endphp
<div id="group-chat-main-container"
    class="container mx-auto max-w-7xl px-4 md:px-8 pt-3 md:pt-4 pb-8 space-y-6 md:space-y-10 group-chat-container"
    style="direction: rtl;">
    <section
        class="bg-white border border-emerald-100 rounded-2xl md:rounded-3xl shadow-md relative overflow-hidden group-info-card"
        x-data="{ expanded: false }">
        <div
            class="absolute inset-0 pointer-events-none bg-gradient-to-l from-emerald-50/50 via-transparent to-transparent">
        </div>

        <!-- نسخه خلاصه برای موبایل -->
        <button @click="expanded = !expanded"
            class="lg:hidden w-full relative z-10 flex items-center justify-between gap-3 px-5 py-4 hover:bg-emerald-50/50 active:bg-emerald-50 transition-colors">
            <div class="flex items-center gap-4 flex-1 min-w-0">
                <div
                    class="w-14 h-14 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-xl font-black shadow-md flex-shrink-0 border border-emerald-200/60">
                    @if($group->avatar)
                    <img src="{{ asset('images/groups/' . $group->avatar) }}" alt="{{ $group->name }}"
                        class="w-full h-full object-cover rounded-2xl">
                    @else
                    {{ Str::upper(Str::substr($group->name, 0, 2)) }}
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <h1 class="text-lg font-bold text-slate-900 truncate leading-tight mb-1.5">{{ $group->name }}</h1>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <span
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-700 text-xs font-semibold">
                            <i class="fas fa-user-shield text-[10px]"></i>{{ $roleTitle }}
                        </span>
                        <span class="text-xs text-slate-500 font-medium">{{ $memberCount }} عضو</span>
                    </div>
                </div>
            </div>
            <div
                class="flex-shrink-0 w-9 h-9 flex items-center justify-center rounded-xl bg-emerald-50 hover:bg-emerald-100 active:bg-emerald-200 transition-colors ml-2">
                <i class="fas fa-chevron-down text-emerald-600 text-xs transition-transform duration-300"
                    :class="{ 'rotate-180': expanded }"></i>
            </div>
        </button>

        <!-- محتوای کامل - در موبایل با expand/collapse -->
        <div class="relative z-10 px-5 py-5 collapse-content lg:hidden border-t border-emerald-100/60" x-show="expanded"
            x-cloak style="display: none;">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-4">
                    <div
                        class="w-16 h-16 rounded-3xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-2xl font-black shadow-inner hidden lg:flex">
                        @if($group->avatar)
                        <img src="{{ asset('images/groups/' . $group->avatar) }}" alt="{{ $group->name }}"
                            class="w-full h-full object-cover rounded-3xl">
                        @else
                        {{ Str::upper(Str::substr($group->name, 0, 2)) }}
                        @endif
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center gap-3 flex-wrap">
                            <h1 class="text-xl lg:text-3xl font-black text-slate-900">{{ $group->name }}</h1>
                            <span
                                class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-100 text-emerald-600 text-sm font-semibold">
                                <i class="fas fa-user-shield"></i>{{ $roleTitle }}
                            </span>
                            <span
                                class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-sm">
                                <i class="fas fa-wave-square"></i>{{ $membershipStatusLabel }}
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-sm text-slate-600">
                            <span class="inline-flex items-center gap-2">
                                <i class="fas fa-users text-emerald-500"></i>{{ $memberCount }} عضو
                            </span>
                            @if($guestCount > 0)
                            <span class="inline-flex items-center gap-2">
                                <i class="fas fa-user-clock text-emerald-500"></i>{{ $guestCount }} مهمان
                            </span>
                            @endif
                            @if($group->location_level)
                            <span class="inline-flex items-center gap-2">
                                <i class="fas fa-map-marker-alt text-emerald-500"></i>{{ $group->location_level }}
                            </span>
                            @endif
                            <span class="inline-flex items-center gap-2">
                                <i
                                    class="fas fa-calendar-check text-emerald-500"></i>{{ verta($group->created_at)->format('Y/m/d') }}
                            </span>
                        </div>
                        @if(!empty($group->description))
                        <p class="text-sm text-slate-500 leading-relaxed max-w-2xl">
                            {{ Str::limit(strip_tags($group->description), 180) }}
                        </p>
                        @endif
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3 justify-start lg:justify-end">
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-emerald-200 text-emerald-600 hover:bg-emerald-50 transition lg:hidden"
                        onclick="openGroupInfo()">
                        <i class="fas fa-layer-group"></i>
                        پنل گروه
                    </button>
                    @if(($yourRole ?? 0) !== 5)
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-emerald-500 text-white shadow-sm hover:bg-emerald-600 transition"
                        onclick="openBlogBox()">
                        <i class="far fa-pen-to-square"></i>
                        ایجاد پست
                    </button>
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-emerald-100 text-emerald-600 hover:bg-emerald-200 transition"
                        onclick="openPollBox()">
                        <i class="fas fa-chart-simple"></i>
                        ساخت نظرسنجی
                    </button>
                    @endif
                    @if($electionAvailable)
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl {{ $canParticipateElection ? 'bg-indigo-500 text-white shadow-sm hover:bg-indigo-600 transition' : 'bg-slate-100 text-slate-500 cursor-not-allowed' }}"
                        @if($canParticipateElection) onclick="openElectionBox()" @else disabled @endif>
                        <i class="fas fa-vote-yea"></i>
                        {{ $canParticipateElection ? 'شرکت در انتخابات' : 'انتخابات فعال' }}
                    </button>
                    @endif
                    @if(in_array($yourRole ?? 0, [2,3]))
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition"
                        onclick="openElection2Box()">
                        <i class="fas fa-ballot-check text-emerald-500"></i>
                        افزودن انتخابات
                    </button>
                    @endif
                    @if(($yourRole ?? 0) == 3)
                    <button type="button" id="manage-members-btn"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-blue-200 text-blue-600 hover:bg-blue-50 transition"
                        onclick="if(typeof window.showManageMembersModal === 'function') { window.showManageMembersModal(); } else { console.error('showManageMembersModal not found'); alert('تابع مدیریت اعضا یافت نشد. لطفاً صفحه را رفرش کنید.'); }">
                        <i class="fas fa-users-cog"></i>
                        مدیریت اعضا
                    </button>
                    <button type="button" id="manage-reports-btn"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-orange-200 text-orange-600 hover:bg-orange-50 transition relative"
                        onclick="if(typeof window.showManageReportsModal === 'function') { window.showManageReportsModal(); } else { console.error('showManageReportsModal not found'); alert('تابع مدیریت گزارش‌ها یافت نشد. لطفاً صفحه را رفرش کنید.'); }">
                        <i class="fas fa-flag"></i>
                        گزارش‌ها
                        <span id="reports-badge"
                            class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"
                            style="display: none;">0</span>
                    </button>
                    @endif
                    <button type="button" id="group-settings-btn"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-emerald-200 text-emerald-600 hover:bg-emerald-50 transition"
                        onclick="showGroupSettingsModal()">
                        <i class="fas fa-cog"></i>
                        تنظیمات
                    </button>
                    <a href="{{ route('groups.logout', $group->id) }}"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-red-100 text-red-500 hover:bg-red-50 transition">
                        <i class="fas fa-door-open"></i>
                        خروج از گروه
                    </a>
                </div>
            </div>
            <div class="relative z-10 mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="stat-chip">
                    <span class="stat-chip__label">پیام‌های سنجاق‌شده</span>
                    <span class="stat-chip__value">{{ $pinnedMessages->count() }}</span>
                </div>
                <div class="stat-chip">
                    <span class="stat-chip__label">پست‌ها</span>
                    <span class="stat-chip__value">{{ $blogCount }}</span>
                </div>
                <div class="stat-chip">
                    <span class="stat-chip__label">نظرسنجی‌ها</span>
                    <span class="stat-chip__value">{{ $pollCount }}</span>
                </div>
                <div class="stat-chip">
                    <span class="stat-chip__label">آخرین فعالیت</span>
                    <span class="stat-chip__value">{{ verta($group->updated_at)->formatDifference() }}</span>
                </div>
            </div>
        </div>

        <!-- نسخه دسکتاپ - همیشه باز -->
        <div class="hidden lg:block relative z-10 px-5 py-6">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-5">
                    <div
                        class="w-16 h-16 rounded-3xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-2xl font-black shadow-inner">
                        @if($group->avatar)
                        <img src="{{ asset('images/groups/' . $group->avatar) }}" alt="{{ $group->name }}"
                            class="w-full h-full object-cover rounded-3xl">
                        @else
                        {{ Str::upper(Str::substr($group->name, 0, 2)) }}
                        @endif
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center gap-3 flex-wrap">
                            <h1 class="text-2xl lg:text-3xl font-black text-slate-900">{{ $group->name }}</h1>
                            <span
                                class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-100 text-emerald-600 text-sm font-semibold">
                                <i class="fas fa-user-shield"></i>{{ $roleTitle }}
                            </span>
                            <span
                                class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-sm">
                                <i class="fas fa-wave-square"></i>{{ $membershipStatusLabel }}
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-sm text-slate-600">
                            <span class="inline-flex items-center gap-2">
                                <i class="fas fa-users text-emerald-500"></i>{{ $memberCount }} عضو
                            </span>
                            @if($guestCount > 0)
                            <span class="inline-flex items-center gap-2">
                                <i class="fas fa-user-clock text-emerald-500"></i>{{ $guestCount }} مهمان
                            </span>
                            @endif
                            @if($group->location_level)
                            <span class="inline-flex items-center gap-2">
                                <i class="fas fa-map-marker-alt text-emerald-500"></i>{{ $group->location_level }}
                            </span>
                            @endif
                            <span class="inline-flex items-center gap-2">
                                <i
                                    class="fas fa-calendar-check text-emerald-500"></i>{{ verta($group->created_at)->format('Y/m/d') }}
                            </span>
                        </div>
                        @if(!empty($group->description))
                        <p class="text-sm text-slate-500 leading-relaxed max-w-2xl">
                            {{ Str::limit(strip_tags($group->description), 180) }}
                        </p>
                        @endif
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3 justify-start lg:justify-end">
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-emerald-200 text-emerald-600 hover:bg-emerald-50 transition lg:hidden"
                        onclick="openGroupInfo()">
                        <i class="fas fa-layer-group"></i>
                        پنل گروه
                    </button>
                    @if(($yourRole ?? 0) !== 5)
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-emerald-500 text-white shadow-sm hover:bg-emerald-600 transition"
                        onclick="openBlogBox()">
                        <i class="far fa-pen-to-square"></i>
                        ایجاد پست
                    </button>
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-emerald-100 text-emerald-600 hover:bg-emerald-200 transition"
                        onclick="openPollBox()">
                        <i class="fas fa-chart-simple"></i>
                        ساخت نظرسنجی
                    </button>
                    @endif
                    @if($electionAvailable)
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl {{ $canParticipateElection ? 'bg-indigo-500 text-white shadow-sm hover:bg-indigo-600 transition' : 'bg-slate-100 text-slate-500 cursor-not-allowed' }}"
                        @if($canParticipateElection) onclick="openElectionBox()" @else disabled @endif>
                        <i class="fas fa-vote-yea"></i>
                        {{ $canParticipateElection ? 'شرکت در انتخابات' : 'انتخابات فعال' }}
                    </button>
                    @endif
                    @if(in_array($yourRole ?? 0, [2,3]))
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition"
                        onclick="openElection2Box()">
                        <i class="fas fa-ballot-check text-emerald-500"></i>
                        افزودن انتخابات
                    </button>
                    @endif
                    @if(($yourRole ?? 0) == 3)
                    <button type="button" id="manage-members-btn"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-blue-200 text-blue-600 hover:bg-blue-50 transition"
                        onclick="if(typeof window.showManageMembersModal === 'function') { window.showManageMembersModal(); } else { console.error('showManageMembersModal not found'); alert('تابع مدیریت اعضا یافت نشد. لطفاً صفحه را رفرش کنید.'); }">
                        <i class="fas fa-users-cog"></i>
                        مدیریت اعضا
                    </button>
                    <button type="button" id="manage-reports-btn"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-orange-200 text-orange-600 hover:bg-orange-50 transition relative"
                        onclick="if(typeof window.showManageReportsModal === 'function') { window.showManageReportsModal(); } else { console.error('showManageReportsModal not found'); alert('تابع مدیریت گزارش‌ها یافت نشد. لطفاً صفحه را رفرش کنید.'); }">
                        <i class="fas fa-flag"></i>
                        گزارش‌ها
                        <span id="reports-badge"
                            class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"
                            style="display: none;">0</span>
                    </button>
                    @endif
                    <button type="button" id="group-settings-btn"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-emerald-200 text-emerald-600 hover:bg-emerald-50 transition"
                        onclick="showGroupSettingsModal()">
                        <i class="fas fa-cog"></i>
                        تنظیمات
                    </button>
                    <a href="{{ route('groups.logout', $group->id) }}"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-red-100 text-red-500 hover:bg-red-50 transition">
                        <i class="fas fa-door-open"></i>
                        خروج از گروه
                    </a>
                </div>
            </div>
            <div class="relative z-10 mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="stat-chip">
                    <span class="stat-chip__label">پیام‌های سنجاق‌شده</span>
                    <span class="stat-chip__value">{{ $pinnedMessages->count() }}</span>
                </div>
                <div class="stat-chip">
                    <span class="stat-chip__label">پست‌ها</span>
                    <span class="stat-chip__value">{{ $blogCount }}</span>
                </div>
                <div class="stat-chip">
                    <span class="stat-chip__label">نظرسنجی‌ها</span>
                    <span class="stat-chip__value">{{ $pollCount }}</span>
                </div>
                <div class="stat-chip">
                    <span class="stat-chip__label">آخرین فعالیت</span>
                    <span class="stat-chip__value">{{ verta($group->updated_at)->formatDifference() }}</span>
                </div>
            </div>
        </div>
    </section>

    @include('groups.modals.group_edit_form', compact('group'))
    @php use Illuminate\Support\Str; @endphp
    <div class="loading-overlay" id="global-loading">
        <div class="spinner"></div>
    </div>


    <div class="grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)] items-start">
        <div class="space-y-6">
            @if ($pinnedMessages->count() > 0)
            <div class="bg-white border border-emerald-100 rounded-3xl shadow-sm mb-6">
                <div class="pinned-messages"
                    style="border-radius: 1.5rem; overflow: hidden; background: #fff; border: none;">
                    @foreach($pinnedMessages as $pinnedMessage)
                    <div class="pin-wrapper"
                        style="position: relative; border-bottom: 1px solid #f1f5f9; padding: 4px;">
                        <a class="pin" href="#msg-{{ $pinnedMessage->message->id }}"
                            style="flex: 1; padding-left: 45px; box-shadow: none; border-radius: 0.75rem;">
                            <div>
                                <b style="color: #10b981; font-size: 0.85rem;">پیام سنجاق‌شده</b>
                                <p style="font-size: 0.9rem; color: #475569;">{!!
                                    Str::limit(strip_tags($pinnedMessage->message->message), 120, '...') !!}</p>
                            </div>
                            <i class="fas fa-thumbtack" style="font-size: 1.1rem; color: #10b981; opacity: 0.5;"></i>
                        </a>
                        @if($roleValue === 3 || $pinnedMessage->message->user_id === auth()->id())
                        <button onclick="event.preventDefault(); unpinMessage('{{ $pinnedMessage->message->id }}')"
                            class="unpin-btn"
                            style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); border: none; background: #f1f5f9; cursor: pointer; color: #94a3b8; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s;"
                            title="حذف از حالت سنجاق">
                            <i class="fas fa-times" style="font-size: 0.9rem; color: inherit !important;"></i>
                        </button>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <div class="chat-wrapper">
                <div class="chat-body" id="chat-box">
                    @foreach($combined as $item)
                    @include('groups.partials.' . $item->type, compact('item', 'group', 'userVote', 'postGroupUsersMap'))
                    @endforeach
                </div>
            </div>

            <button id="scroll-toggle-btn" class="chat-scroll-btn">
                <i class="fas fa-arrow-up"></i>
            </button>

            @php
            $checkBlockMessage = \App\Models\Block::where('user_id', auth()->user()->id)->where('position',
            'message')->first();
            $checkBlockPost = \App\Models\Block::where('user_id', auth()->user()->id)->where('position',
            'post')->first();
            $checkBlockPoll = \App\Models\Block::where('user_id', auth()->user()->id)->where('position',
            'poll')->first();
            @endphp

            <div class="bg-white border border-emerald-100 rounded-3xl shadow-sm p-5 w-full">
                @if ($yourRole === 0 && $group->is_open == 0)
                <p class="text-red-500">
                    شما مجاز به ارسال پیام در گروه نیستید.
                </p>
                @elseif (auth()->user()->status == 0 || auth()->user()->first_name == null || auth()->user()->last_name
                == null)
                <p class="text-amber-600">
                    به دلیل کامل نبودن اطلاعات کاربری امکان ارسال پیام را ندارید، از
                    <a href='{{ route('profile.edit') }}' class="text-emerald-600 underline">این قسمت</a>
                    اقدام به وارد کردن اطلاعات کنید.
                </p>
                @else
                <form id="chatForm" class="chat-input telegram-style-input" method="POST"
                    action="{{ route('groups.messages.store') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="group_id" value="{{ $group->id }}">
                    <input type="hidden" name="parent_id" id="parent_id" value="">
                    <input type="file" name="voice_message" id="voice-file-input" accept="audio/*" class="d-none">

                    @if ($checkBlockMessage != null)
                    <div
                        class="chat-block-message text-danger-emphasis bg-danger-subtle border border-danger-subtle rounded-4 px-3 py-3">
                        شما از جانب مدیریت برای عملیات ارسال پیام مسدود شده‌اید، جهت رفع مسدودیت با مدیریت در ارتباط
                        باشید.
                    </div>
                    @else
                    <!-- Reply Indicator Container - شبیه تلگرام -->
                    <div id="reply-indicator-container" class="telegram-reply-indicator" style="display: none;"></div>

                    <!-- Input Container -->
                    <div class="telegram-input-container">
                        <!-- Attachment Button -->
                        @if($yourRole != 5)
                        <div class="position-relative telegram-attach-btn-wrapper">
                            <button type="button" id="chatCreateToggle" class="telegram-attach-btn">
                                <i class="fas fa-paperclip"></i>
                            </button>
                            <div id="createMenu" style="display: none;" class="chat-tool-menu telegram-attach-menu">
                                @if ($checkBlockPost != null)
                                <span class="chat-tool-menu__item text-danger">شما برای عملیات ایجاد پست مسدود
                                    شده‌اید</span>
                                @else
                                <button type="button" class="chat-tool-menu__item" id="create-post-btn">
                                    <i class="far fa-edit text-success"></i>
                                    ایجاد پست
                                </button>
                                @endif

                                @if ($checkBlockPoll != null)
                                <span class="chat-tool-menu__item text-danger">شما برای عملیات ایجاد نظرسنجی مسدود
                                    شده‌اید</span>
                                @else
                                <button type="button" class="chat-tool-menu__item" id="create-poll-btn">
                                    <i class="fas fa-chart-simple text-success"></i>
                                    ایجاد نظرسنجی
                                </button>
                                @endif

                                <button type="button" id="audio-upload-trigger" class="chat-tool-menu__item">
                                    <i class="fas fa-file-audio text-success"></i>
                                    ارسال فایل صوتی
                                </button>
                            </div>
                        </div>
                        @endif

                        <!-- Text Input -->
                        <div class="telegram-input-wrapper">
                            <textarea class="telegram-textarea" name="message" placeholder="پیام خود را بنویسید..."
                                id="message_editor" rows="1"></textarea>
                        </div>

                        <!-- Send Button -->
                        <div class="telegram-action-buttons">
                            <button type="button" id="voice-record-btn" class="telegram-action-btn telegram-voice-btn"
                                title="ضبط صدا">
                                <i class="fas fa-microphone"></i>
                            </button>
                            <button type="submit" id="telegram-send-btn" class="telegram-action-btn telegram-send-btn"
                                title="ارسال">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Voice File Preview -->
                    <div id="voice-file-preview" class="voice-file-preview telegram-voice-preview"
                        style="display: none !important;">
                        <i class="fas fa-file-audio"></i>
                        <div class="voice-file-info">
                            <div id="voice-file-name"></div>
                            <small id="voice-file-size"></small>
                        </div>
                        <button type="button" class="voice-file-remove-btn" id="voice-file-remove">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    @endif
                </form>
                @endif
            </div>
        </div>

        <aside class="space-y-6 lg:pl-2">
            @include('groups.partials.group_info_panel', compact('group'))
        </aside>
    </div>

    <div id="groupInfoBackdrop" class="group-info-backdrop hidden"></div>
    <div id="categoryBlogsOverlay"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1100;"></div>

    <div id="categoryBlogsModal" style="
  display:none; position:fixed; inset:0; z-index:1110;
  align-items:center; justify-content:center;">
        <div style="
    width: min(700px, 92vw);
    max-height: 80vh;
    background:#fff; border-radius:12px; overflow:hidden;
    direction: rtl; box-shadow:0 10px 30px rgba(0,0,0,.2);
  ">
            <div
                style="display:flex; align-items:center; justify-content:space-between; padding: .8rem 1rem; background:#f6f6f6;">
                <strong id="catModalTitle" style="font-size:1rem">لیست پست‌ها</strong>
                <button id="closeCatModal"
                    style="border:none; background:transparent; font-size:1.2rem; line-height:1;">✖</button>
            </div>
            <div id="catModalBody" style="padding: .6rem 1rem; overflow:auto; max-height: calc(80vh - 52px);">
                <div id="catLoading" style="padding:1rem; text-align:center;">در حال بارگذاری...</div>
                <ul id="catList" style="list-style:none; margin:0; padding:0; display:none;"></ul>
                <div id="catEmpty" style="display:none; text-align:center; padding:1rem;">پستی در این دسته یافت نشد.
                </div>
            </div>
        </div>
    </div>
    <script>
    function togglePollMenu(pollId) {
        const menu = document.getElementById('poll-menu-' + pollId);
        if (!menu) return;
        if (menu.style.display === 'none' || menu.style.display === '') {
            menu.style.display = 'block';
        } else {
            menu.style.display = 'none';
        }
    }
    </script>
    <script>
    function showEditPollBox(pollId) {
        // مثال: نمایش یک باکس ویرایش
        const editBox = document.getElementById('edit-poll-box-' + pollId);
        if (!editBox) return;
        editBox.style.display = editBox.style.display === 'none' || editBox.style.display === '' ? 'block' : 'none';
    }
    </script>
    <script>
    function confirmDelete(event, url) {
        event.preventDefault();
        if (confirm('آیا مطمئن هستید که می‌خواهید این آیتم را حذف کنید؟')) {
            window.location.href = url; // یا با AJAX حذف کن
        }
    }
    </script>
    <!-- Edit Modal -->
    <div id="editModal" class="edit-modal hidden" aria-hidden="true">
        <div class="edit-modal__backdrop"></div>
        <div class="edit-modal__panel" role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
            <div class="edit-modal__header">
                <h3 id="editModalTitle">ویرایش پیام</h3>
                <button type="button" class="edit-close" aria-label="بستن">×</button>
            </div>
            <div class="edit-modal__body">
                <textarea id="editText" rows="6" class="edit-textarea" placeholder="متن پیام..."></textarea>
            </div>
            <div class="edit-modal__footer">
                <button type="button" class="btn btn-primary save-edit">ذخیره</button>
                <button type="button" class="btn cancel-edit "
                    style='    background-color: #c24545 !important;'>لغو</button>
            </div>
        </div>
    </div>

    <style>
    .edit-modal.hidden {
        display: none;
    }

    .edit-modal {
        position: fixed;
        inset: 0;
        z-index: 9999;
    }

    .edit-modal__backdrop {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, .35);
    }

    .edit-modal__panel {
        direction: rtl;
        position: relative;
        margin: 5vh auto 0;
        max-width: 640px;
        width: clamp(320px, 90vw, 640px);
        max-height: 90vh;
        background: #fff;
        border-radius: 12px;
        padding: 1rem;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .15);
        display: flex;
        flex-direction: column;
    }

    .edit-modal__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
    }

    .edit-modal__body {
        margin-top: .5rem;
        flex: 1;
        overflow-y: auto;
    }

    .edit-textarea {
        width: 100%;
        min-height: 120px;
        padding: .75rem;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        font: inherit;
        line-height: 1.5;
        resize: vertical;
    }

    .edit-modal__footer {
        display: flex;
        justify-content: flex-end;
        gap: .5rem;
        margin-top: .75rem;
    }

    .btn {
        padding: .5rem .9rem;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        background: #f8fafc;
        cursor: pointer;
    }

    .btn-primary {
        background: #2563eb;
        color: #fff;
        border-color: #2563eb;
    }

    .edit-close {
        background: transparent;
        border: none;
        font-size: 1.25rem;
        cursor: pointer;
        line-height: 1;
    }

    @media (max-width: 480px) {
        .edit-modal__panel {
            margin: 2vh auto 0;
            width: 94vw;
            padding: .75rem;
        }
    }
    </style>

    <script>
    (function() {
        // اگر CSRF را در <meta name="csrf-token" content="..."> داری:
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
            '{{ csrf_token() }}';

        const modal = document.getElementById('editModal');
        const textarea = document.getElementById('editText');
        const btnSave = modal.querySelector('.save-edit');
        const btnCancel = modal.querySelector('.cancel-edit');
        const btnClose = modal.querySelector('.edit-close');
        const backdrop = modal.querySelector('.edit-modal__backdrop');

        // متغیرهای وضعیت جاری ویرایش
        let currentBubble = null; // عنصر .message-bubble
        let currentUrl = null; // آدرس PATCH
        let currentId = null; // message-id

        // هندلر کلیک روی "ویرایش"
        document.addEventListener('click', function(e) {
            const editBtn = e.target.closest('.btn-edit');
            if (!editBtn) return;

            const bubble = editBtn.closest('.message-bubble');
            if (!bubble) return;

            // بستن منوی عملیات قبل از باز کردن مدال
            const actionMenu = bubble.closest('.message-head')?.querySelector('[data-action-menu]');
            if (actionMenu) {
                actionMenu.classList.remove('is-open');
                actionMenu.querySelector('.action-menu__toggle')?.setAttribute('aria-expanded', 'false');
            }

            currentBubble = bubble;
            currentUrl = bubble.dataset.editUrl;
            currentId = bubble.dataset.messageId;

            // متن فعلی برای پر کردن باکس:
            // اول تلاش می‌کنیم از DOM (message-content) بخوانیم و HTML (مثل <br>) را به متن ساده با line break تبدیل کنیم
            const contentEl = bubble.querySelector('.message-content');
            let raw = '';
            if (contentEl) {
                const messageHtml = contentEl.innerHTML || "";
                raw = htmlToPlain(messageHtml);
            }

            // اگر به هر دلیلی متن خالی بود، از data-content-raw استفاده کن (fallback)
            if (!raw && bubble.dataset.contentRaw) {
                raw = bubble.dataset.contentRaw;
            }

            textarea.value = raw || '';
            openModal();
        });

        // ذخیره (ارسال PATCH)
        btnSave.addEventListener('click', async function() {
            const newText = textarea.value.trim();

            if (!currentBubble || !currentUrl) {
                closeModal();
                return;
            }

            // چرخنده و دکمه
            const overlay = document.getElementById('global-loading');
            const showOverlay = () => overlay && overlay.classList.add('show');
            const hideOverlay = () => overlay && overlay.classList.remove('show');
            const setBtnLoading = (on = true) => {
                btnSave.disabled = on;
                btnSave.classList.toggle('btn-loading', on);
            };

            try {
                showOverlay();
                setBtnLoading(true);

                // اگر روتت دقیقاً POST می‌پذیره (بدون شبیه‌سازی PATCH)، همین کافیه:
                const res = await fetch(currentUrl, {
                    method: 'POST', // مطابق کنترلرت
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        content: newText
                    })
                });

                // خواندن response (یک بار)
                let responseData = null;
                try {
                    const contentType = res.headers.get('content-type') || '';
                    console.log('Response status:', res.status, res.ok);
                    console.log('Content-Type:', contentType);

                    if (contentType.includes('application/json')) {
                        responseData = await res.json();
                    } else {
                        const text = await res.text();
                        try {
                            responseData = JSON.parse(text);
                        } catch {
                            responseData = {
                                message: text
                            };
                        }
                    }
                    console.log('Response data:', responseData);
                } catch (parseError) {
                    console.error('Error parsing response:', parseError);
                    alert('خطا در خواندن پاسخ سرور');
                    return;
                }

                // بررسی status response
                if (!res.ok) {
                    // خطا در response
                    const errorMsg = responseData?.message || responseData?.error ||
                        'خطا در ذخیره‌سازی.';
                    console.error('Response error:', errorMsg);
                    alert(errorMsg);
                    return;
                }

                // بررسی وجود currentBubble
                if (!currentBubble) {
                    console.error('currentBubble is null');
                    alert('خطا: عنصر پیام پیدا نشد. لطفا صفحه را رفرش کنید.');
                    // ✅ FIXED: No reload - let user refresh manually if needed
                    closeModal();
                    return;
                }

                console.log('Current bubble found:', currentBubble);

                // ذخیره کردن currentBubble قبل از بستن مودال (چون closeModal ممکن است آن را null کند)
                const bubbleToUpdate = currentBubble;

                // بستن منوی عملیات (اگر bubble هنوز در DOM است)
                if (bubbleToUpdate && bubbleToUpdate.isConnected) {
                    try {
                        const actionMenu = bubbleToUpdate.closest('.message-head')?.querySelector(
                            '[data-action-menu]');
                        if (actionMenu) {
                            actionMenu.classList.remove('is-open');
                            actionMenu.querySelector('.action-menu__toggle')?.setAttribute(
                                'aria-expanded', 'false');
                            console.log('Action menu closed');
                        }
                        // همچنین بستن details menu در صورت وجود (برای سازگاری)
                        const details = bubbleToUpdate.closest('details.menu-wrapper[open]');
                        if (details) {
                            details.removeAttribute('open');
                            console.log('Details menu closed');
                        }
                    } catch (e) {
                        console.warn('Error closing menu:', e);
                        // ادامه بده، این خطای جدی نیست
                    }
                }

                // بستن مودال
                closeModal();

                // استفاده از bubbleToUpdate برای به‌روزرسانی (نه currentBubble که ممکن است null شده باشد)
                const finalBubble = bubbleToUpdate;

                // بررسی format response و به‌روزرسانی محتوا
                let contentToUpdate = null;
                if (responseData && responseData.content) {
                    contentToUpdate = responseData.content;
                    console.log('Using responseData.content:', contentToUpdate);
                } else if (responseData && responseData.message && typeof responseData.message ===
                    'object' && responseData.message.content) {
                    // اگر message یک object است و content دارد
                    contentToUpdate = responseData.message.content;
                    console.log('Using responseData.message.content:', contentToUpdate);
                } else if (responseData && responseData.message && typeof responseData.message ===
                    'string' && responseData.message !== 'پیام با موفقیت ویرایش شد') {
                    // اگر message یک string است (نه object) و پیام موفقیت نیست
                    contentToUpdate = responseData.message;
                    console.log('Using responseData.message (string):', contentToUpdate);
                }

                console.log('Content to update:', contentToUpdate);

                if (contentToUpdate) {
                    try {
                        console.log('Calling updateMessageContent with finalBubble...');
                        if (!finalBubble || !finalBubble.isConnected) {
                            console.error('finalBubble is null or not in DOM');
                            throw new Error('Message bubble not found in DOM');
                        }
                        updateMessageContent(finalBubble, contentToUpdate, true);
                        console.log('Message updated successfully!');
                        // موفق: هیچ reload لازم نیست
                    } catch (updateError) {
                        console.error('Error in updateMessageContent:', updateError);
                        console.error('Error stack:', updateError.stack);
                        // ✅ FIXED: No reload - show error and close modal
                        if (updateError.message && (updateError.message.includes('null') || updateError
                                .message.includes('not found') || updateError.message.includes(
                                    'not in DOM'))) {
                            console.warn('Critical error in updateMessageContent');
                            alert('خطا در به‌روزرسانی پیام. لطفا دوباره تلاش کنید.');
                            closeModal();
                        } else {
                            // برای خطاهای دیگر، فقط alert بده
                            console.warn('Non-critical error in updateMessageContent, not reloading');
                            alert('خطا در به‌روزرسانی پیام: ' + updateError.message);
                        }
                    }
                } else {
                    console.warn('Unexpected response format:', responseData);
                    console.warn('Response keys:', Object.keys(responseData || {}));
                    // اگر response درست نبود، سعی کن از message استفاده کن
                    if (responseData && responseData.message && typeof responseData.message ===
                        'string' && responseData.message !== 'پیام با موفقیت ویرایش شد') {
                        // اگر message یک string است و پیام موفقیت نیست، از آن استفاده کن
                        console.log('Trying to use responseData.message as content');
                        try {
                            if (!finalBubble || !finalBubble.isConnected) {
                                throw new Error('Message bubble not found in DOM');
                            }
                            updateMessageContent(finalBubble, responseData.message, true);
                            console.log('Message updated using responseData.message');
                        } catch (e) {
                            console.error('Failed to update using message:', e);
                            // ✅ FIXED: No reload - show error message
                            alert('خطا در به‌روزرسانی پیام. لطفا دوباره تلاش کنید.');
                            closeModal();
                        }
                    } else {
                        // ✅ FIXED: No reload - show error message
                        console.error('No valid content found in response');
                        alert('خطا در دریافت محتوای پیام. لطفا دوباره تلاش کنید.');
                        closeModal();
                    }
                }

            } catch (err) {
                console.error('Error in edit handler:', err);
                console.error('Error stack:', err.stack);
                console.error('Error name:', err.name);
                console.error('Error message:', err.message);
                // ✅ FIXED: No location.reload() - handle network errors gracefully
                if (err.name === 'TypeError' && (err.message.includes('fetch') || err.message.includes(
                        'network') || err.message.includes('Failed to fetch'))) {
                    console.warn('Network error detected');
                    alert('خطا در اتصال به سرور. لطفاً اتصال اینترنت خود را بررسی کنید.');
                    closeModal();
                    return;
                }
                // برای سایر خطاها، فقط alert بده و مودال را ببند
                alert('خطا در ویرایش پیام: ' + (err.message || 'خطای نامشخص'));
                closeModal();

            } finally {
                hideOverlay();
                setBtnLoading(false);
            }
        });


        // بستن مودال
        [btnCancel, btnClose, backdrop].forEach(el => el.addEventListener('click', closeModal));
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
        });

        function openModal() {
            modal.classList.remove('hidden');
            textarea.focus();
            // مکان‌نما آخر متن:
            const val = textarea.value;
            textarea.setSelectionRange(val.length, val.length);
        }

        function closeModal() {
            modal.classList.add('hidden');
            textarea.value = '';
            currentBubble = null;
            currentUrl = null;
            currentId = null;
        }

        function htmlToPlain(html) {
            const tmp = document.createElement('div');
            tmp.innerHTML = html;
            return (tmp.textContent || tmp.innerText || '').trim();
        }
    })();
    </script>

    <script>
    const chatBox = document.getElementById('chat-box');
    const STORAGE_KEY = 'chatScroll_{{ $group->id }}';
    const GROUP_ID = {{ $group->id }};
    const LAST_READ_MESSAGE_ID = {{ $lastReadMessageId ?? 'null' }};
    const UPDATE_LAST_READ_URL = '{{ route("groups.messages.updateLastRead", $group->id) }}';
    const CURRENT_USER_ROLE = {{ $roleValue ?? 0 }};
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
        '{{ csrf_token() }}';

    // جلوگیری از رفتار پیش‌فرض مرورگر
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }

    // تابع برای به‌روزرسانی last_read_message_id
    let lastReadUpdateTimeout = null;

    function updateLastReadMessage(messageId) {
        if (!messageId || messageId === LAST_READ_MESSAGE_ID) return;

        // Debounce: فقط آخرین پیام visible را به‌روزرسانی کن
        clearTimeout(lastReadUpdateTimeout);
        lastReadUpdateTimeout = setTimeout(() => {
            fetch(UPDATE_LAST_READ_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    message_id: messageId
                })
            }).catch(err => console.error('Error updating last read message:', err));
        }, 500); // 500ms debounce
    }

    // تابع برای پیدا کردن آخرین پیام visible در viewport
    function getLastVisibleMessageId() {
        const messages = chatBox.querySelectorAll('[data-message-id]');
        let lastVisibleId = null;

        for (let i = messages.length - 1; i >= 0; i--) {
            const msg = messages[i];
            const rect = msg.getBoundingClientRect();
            const chatBoxRect = chatBox.getBoundingClientRect();

            // بررسی اینکه آیا پیام در viewport قرار دارد
            if (rect.top >= chatBoxRect.top && rect.bottom <= chatBoxRect.bottom) {
                const messageId = parseInt(msg.getAttribute('data-message-id'));
                if (messageId && !isNaN(messageId)) {
                    lastVisibleId = messageId;
                    break;
                }
            }
        }

        return lastVisibleId;
    }

    // تابع برای ذخیره موقعیت قبل از submit فرم
    function saveScrollPositionBeforeSubmit() {
        const lastVisibleId = getLastVisibleMessageId();
        if (lastVisibleId) {
            updateLastReadMessage(lastVisibleId);
        }
        sessionStorage.setItem(STORAGE_KEY, chatBox.scrollTop);
    }

    // Event listener برای submit فرم در group-chat.js تعریف شده است
    // این کد حذف شد تا از تداخل event listenerها جلوگیری شود

    // تابع برای اضافه کردن پیام جدید به chat بدون reload
    function addMessageToChat(messageData) {
        try {
            const chatBox = document.getElementById('chat-box');
            if (!chatBox || !messageData) {
                console.error('Chat box or message data not found');
                return;
            }

            // بررسی کن که پیام معتبر باشد و خالی نباشد
            if (!messageData.id || !messageData.message || (typeof messageData.message === 'string' && messageData
                    .message.trim() === '')) {
                console.warn('Invalid or empty message data:', messageData);
                return;
            }

            // بررسی کن که آیا این پیام قبلاً اضافه شده یا نه
            const existingMessage = document.getElementById(`msg-${messageData.id}`);
            if (existingMessage) {
                console.warn('Message already exists:', messageData.id);
                return;
            }

            const isMine = messageData.user_id == {{ auth()->id() }};
            const messageRow = document.createElement('div');
            messageRow.className = `message-row ${isMine ? 'you' : 'other'}`;
            messageRow.setAttribute('data-message-id', messageData.id);
            messageRow.id = `msg-${messageData.id}`;

            // ساخت HTML پیام
            const senderName = messageData.sender || 'کاربر';
            const initials = senderName.split(' ').map(n => n.charAt(0)).join(' ').trim() || '؟';
            const messageContent = messageData.message || '';
            const formattedTime = messageData.created_at || new Date().toLocaleTimeString('fa-IR', {
                hour: '2-digit',
                minute: '2-digit'
            });

            let messageHTML = '';

            // آواتار (فقط برای پیام‌های دیگران)
            if (!isMine) {
                messageHTML +=
                    `<a href="/profile/member/${messageData.user_id}" class="avatar-link"><span class="avatar"><span>${initials}</span></span></a>`;
            }

            // Reply preview
            let replyPreviewHTML = '';
            if (messageData.parent_id && messageData.parent_sender && messageData.parent_content) {
                replyPreviewHTML =
                    `<div class="reply-preview"><div class="reply-sender">${escapeHtml(messageData.parent_sender)}</div><div class="reply-text">${escapeHtml(messageData.parent_content.substring(0, 80))}</div></div>`;
            }

            // Voice message
            let voiceMessageHTML = '';
            if (messageData.voice_message) {
                // Convert relative path to full URL if needed
                let voiceUrl = messageData.voice_message;
                if (!voiceUrl.startsWith('http://') && !voiceUrl.startsWith('https://')) {
                    // Remove leading slash if exists
                    voiceUrl = voiceUrl.startsWith('/') ? voiceUrl.substring(1) : voiceUrl;
                    // Build full URL - encode each part separately to handle spaces
                    const pathParts = voiceUrl.split('/');
                    const encodedParts = pathParts.map(part => encodeURIComponent(part));
                    voiceUrl = window.location.origin + '/storage/' + encodedParts.join('/');
                }
                const voiceType = messageData.file_type || 'audio/webm';
                voiceMessageHTML =
                    `<div class="voice-message-container" style="margin-top: 12px; padding: 12px; background: ${isMine ? '#e3f2fd' : '#f5f5f5'}; border-radius: 12px; border: 1px solid ${isMine ? '#90caf9' : '#e0e0e0'}; direction: ltr;"><div style="display: flex; align-items: center; gap: 12px;"><div style="width: 40px; height: 40px; border-radius: 50%; background: ${isMine ? '#2196f3' : '#757575'}; display: flex; align-items: center; justify-content: center; color: white;"><i class="fas fa-microphone"></i></div><div style="flex: 1;"><div style="font-size: 12px; color: #666; margin-bottom: 4px;"><i class="fas fa-headphones"></i> پیام صوتی</div><audio controls style="width: 100%; height: 40px;" preload="metadata"><source src="${voiceUrl}" type="${voiceType}"><source src="${voiceUrl}" type="audio/webm"><source src="${voiceUrl}" type="audio/ogg"><source src="${voiceUrl}" type="audio/mpeg">مرورگر شما از پخش صدا پشتیبانی نمی‌کند.</audio></div></div></div>`;
            }

            messageHTML += `
            <div class="message-bubble ${isMine ? 'you' : 'other'}" data-message-id="${messageData.id}" data-user-id="${messageData.user_id}" data-edit-url="/messages/${messageData.id}/edit" data-delete-url="/messages/${messageData.id}/delete" data-report-url="/messages/${messageData.id}/report" data-content-raw="${escapeHtml(stripHtml(messageContent))}">
                <div class="message-head">
                    ${isMine ? 
                        // برای پیام‌های خود کاربر: سه نقطه در سمت چپ، نام در سمت راست
                        `<div class="action-menu message-action" data-action-menu>
                            <button type="button" class="action-menu__toggle"><i class="fas fa-ellipsis-v"></i></button>
                            <div class="action-menu__list">
                                <button type="button" onclick="replyToMessage('${messageData.id}', '${escapeHtml(senderName)}', '${escapeHtml(messageContent.substring(0, 50))}')" class="action-menu__item btn-rep"><i class="fas fa-reply"></i> پاسخ</button>
                                <button type="button" class="action-menu__item btn-reaction"><i class="fas fa-smile"></i> واکنش</button>
                                ${([2,3].includes(CURRENT_USER_ROLE)) ? `<button type="button" class="action-menu__item btn-pin" onclick="pinMessage('${messageData.id}')"><i class="fas fa-thumbtack"></i> سنجاق کردن</button>` : ''}
                                <button type="button" class="action-menu__item btn-edit"><i class="fas fa-edit"></i> ویرایش</button>
                                <button type="button" class="action-menu__item action-menu__item--danger btn-delete"><i class="fas fa-trash"></i> حذف</button>
                                <div class="menu-meta-time"><div class="menu-meta-time__item"><i class="fas fa-paper-plane" style="font-size: 0.7rem; opacity: 0.6; margin-left: 4px;"></i><span class="menu-meta-time__label">ارسال شده:</span><span class="menu-meta-time__value">${formattedTime}</span></div></div>
                            </div>
                        </div>
                        <div class="message-head__info">
                            <span class="message-sender message-sender--self">شما</span>
                        </div>` :
                        // برای پیام‌های دیگران: نام کاربر در سمت چپ، سه نقطه در سمت راست
                        `<div class="message-head__info">
                            <a href="/profile-member/${messageData.user_id}" class="message-sender" onclick="event.stopPropagation(); window.location.href='/profile-member/${messageData.user_id}'; return false;">${escapeHtml(senderName)}</a>
                        </div>
                        <div class="action-menu message-action" data-action-menu>
                            <button type="button" class="action-menu__toggle"><i class="fas fa-ellipsis-v"></i></button>
                            <div class="action-menu__list">
                                <button type="button" onclick="replyToMessage('${messageData.id}', '${escapeHtml(senderName)}', '${escapeHtml(messageContent.substring(0, 50))}')" class="action-menu__item btn-rep"><i class="fas fa-reply"></i> پاسخ</button>
                                <button type="button" class="action-menu__item btn-reaction"><i class="fas fa-smile"></i> واکنش</button>
                                ${([2,3].includes(CURRENT_USER_ROLE)) ? `<button type="button" class="action-menu__item btn-pin" onclick="pinMessage('${messageData.id}')"><i class="fas fa-thumbtack"></i> سنجاق کردن</button>` : ''}
                                <button type="button" class="action-menu__item btn-report"><i class="fas fa-flag"></i> گزارش</button>
                                <div class="menu-meta-time"><div class="menu-meta-time__item"><i class="fas fa-paper-plane" style="font-size: 0.7rem; opacity: 0.6; margin-left: 4px;"></i><span class="menu-meta-time__label">ارسال شده:</span><span class="menu-meta-time__value">${formattedTime}</span></div></div>
                            </div>
                        </div>`
                    }
                </div>
                ${replyPreviewHTML}
                <p class="message-content">${messageContent}</p>
                <div class="message-timestamp"><span class="message-time">${formattedTime}</span></div>
                ${voiceMessageHTML}
                ${isMine ? '<div class="read-receipt" style="font-size: 10px; margin-top: 4px; text-align: left; direction: ltr;"><span style="color: #9ca3af;"><i class="fas fa-check"></i> ارسال شده</span></div>' : ''}
            </div>
        `;

            messageRow.innerHTML = messageHTML;
            chatBox.appendChild(messageRow);

            // اضافه کردن Thread button
            const newMsgBubble = messageRow.querySelector('[data-message-id]');
            if (newMsgBubble && typeof window.addThreadButton === 'function') {
                window.addThreadButton(newMsgBubble);
            }

            // Initialize click handler for profile link
            const profileLink = messageRow.querySelector('a.message-sender');
            if (profileLink) {
                profileLink.addEventListener('click', function(e) {
                    // اجازه بده لینک کار کند
                    e.stopPropagation();
                    e.preventDefault();
                    // اگر href وجود دارد، به آن برو
                    const href = this.getAttribute('href');
                    if (href && !href.includes('#')) {
                        window.location.href = href;
                    }
                });
            }

            // Initialize action menu handlers for the new message
            initializeMessageActions(messageRow);

            // Initialize action menu toggle (like in group-chat.js)
            const menu = messageRow.querySelector('[data-action-menu]');
            if (menu) {
                const toggle = menu.querySelector('.action-menu__toggle');
                const list = menu.querySelector('.action-menu__list');

                if (toggle) {
                    toggle.addEventListener('click', function(event) {
                        event.preventDefault();
                        event.stopPropagation();
                        const isOpen = menu.classList.contains('is-open');
                        // Close all other menus
                        document.querySelectorAll('[data-action-menu]').forEach(m => {
                            if (m !== menu) {
                                m.classList.remove('is-open');
                                m.querySelector('.action-menu__toggle')?.setAttribute('aria-expanded',
                                    'false');
                            }
                        });
                        if (!isOpen) {
                            menu.classList.add('is-open');
                            toggle.setAttribute('aria-expanded', 'true');
                        } else {
                            menu.classList.remove('is-open');
                            toggle.setAttribute('aria-expanded', 'false');
                        }
                    });
                }

                if (list) {
                    list.querySelectorAll('button, a').forEach(item => {
                        // برای دکمه واکنش، event listener جداگانه اضافه نمی‌کنیم
                        // چون addReactionButton خودش event handler اضافه می‌کند
                        if (item.classList.contains('btn-reaction')) {
                            return;
                        }

                        item.addEventListener('click', function(e) {
                            menu.classList.remove('is-open');
                            toggle?.setAttribute('aria-expanded', 'false');
                        });
                    });
                }
            }
        } catch (error) {
            console.error('Error in addMessageToChat:', error);
            // ✅ FIXED: No location.reload() - gracefully handle error without disrupting user
            console.warn('Could not add message to chat, but continuing without reload');
            // Don't reload - let the polling or next action recover the state
        }
    }

    // Initialize message action handlers
    function initializeMessageActions(messageRow) {
        const bubble = messageRow.querySelector('.message-bubble');
        if (!bubble) return;

        const id = bubble.dataset.messageId;
        const deleteUrl = bubble.dataset.deleteUrl;
        const reportUrl = bubble.dataset.reportUrl;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

        if (!id) return;

        // حذف
        bubble.querySelector(".btn-delete")?.addEventListener("click", async (e) => {
            // بستن منوی عملیات
            const actionMenu = bubble.closest('.message-head')?.querySelector('[data-action-menu]');
            if (actionMenu) {
                actionMenu.classList.remove('is-open');
                actionMenu.querySelector('.action-menu__toggle')?.setAttribute('aria-expanded', 'false');
            }

            const btn = e.currentTarget;
            if (!confirm("آیا از حذف پیام مطمئن هستید؟")) return;

            try {
                const res = await fetch(deleteUrl, {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": csrf,
                        "Accept": "application/json"
                    },
                    credentials: "same-origin"
                });

                let data = {};
                try {
                    data = await res.json();
                } catch (e) {}

                if (res.ok && (data.status === 'success' || !data.status)) {
                    // ✅ FIXED: No location.reload() - remove message from DOM smoothly
                    const messageRow = bubble.closest('.message-row');
                    if (messageRow) {
                        messageRow.style.transition = 'opacity 0.3s ease-out';
                        messageRow.style.opacity = '0';
                        setTimeout(() => {
                            messageRow.remove();
                        }, 300);
                    }
                    // اگر این پیام reply تارگت بود، reply indicator را پاک کن
                    const parentInput = document.getElementById('parent_id');
                    if (parentInput && parentInput.value == id) {
                        if (typeof cancelReply === 'function') cancelReply();
                    }
                } else {
                    alert(data.message || `خطا در حذف پیام (status ${res.status})`);
                }
            } catch (error) {
                console.error('Error deleting message:', error);
                alert('خطا در حذف پیام');
            }
        });

        // گزارش
        bubble.querySelector(".btn-report")?.addEventListener("click", async (e) => {
            // بستن منوی عملیات
            const actionMenu = bubble.closest('.message-head')?.querySelector('[data-action-menu]');
            if (actionMenu) {
                actionMenu.classList.remove('is-open');
                actionMenu.querySelector('.action-menu__toggle')?.setAttribute('aria-expanded', 'false');
            }

            const btn = e.currentTarget;
            const reason = prompt("دلیل گزارش را وارد کنید:");
            if (!reason) return;

            try {
                const res = await fetch(reportUrl, {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": csrf,
                        "Accept": "application/json",
                        "Content-Type": "application/json"
                    },
                    credentials: "same-origin",
                    body: JSON.stringify({
                        reason
                    })
                });

                let data = {};
                try {
                    data = await res.json();
                } catch (e) {}
                alert(data.message || (res.ok ? "گزارش ثبت شد" :
                    `خطا در ثبت گزارش (status ${res.status})`));
            } catch (error) {
                console.error('Error reporting message:', error);
                alert('خطا در ثبت گزارش');
            }
        });
    }

    // تابع برای به‌روزرسانی محتوای پیام بعد از ویرایش
    function updateMessageContent(messageBubble, newContent, isEdited) {
        try {
            if (!messageBubble) {
                console.error('updateMessageContent: messageBubble is null');
                throw new Error('messageBubble is null');
            }

            const contentElement = messageBubble.querySelector('.message-content');
            if (!contentElement) {
                console.error('updateMessageContent: .message-content element not found');
                console.error('Message bubble:', messageBubble);
                console.error('Message bubble HTML:', messageBubble.outerHTML);
                throw new Error('.message-content element not found');
            }

            // پاک کردن محتوای قبلی (برای جلوگیری از تکرار)
            // ابتدا همه child elements را حذف کن
            while (contentElement.firstChild) {
                contentElement.removeChild(contentElement.firstChild);
            }
            contentElement.innerHTML = '';

            // تبدیل محتوا به HTML با حفظ line breaks
            // محتوا از backend به صورت plain text می‌آید (با \n برای line breaks)
            // باید line breaks را به <br> تبدیل کنیم و HTML را escape کنیم
            let htmlContent = '';

            // بررسی اینکه آیا محتوا HTML است یا نه
            // اگر شامل تگ‌های HTML معتبر باشد (نه فقط < یا &)، HTML است
            const hasHtmlTags = /<[a-z][\s\S]*>/i.test(newContent);

            if (hasHtmlTags) {
                // محتوا HTML است، مستقیماً استفاده کن (اما باید اطمینان حاصل کنیم که safe است)
                htmlContent = newContent;
            } else {
                // محتوا plain text است، escape کن و line breaks را به <br> تبدیل کن
                htmlContent = nl2br(newContent);
            }

            // به‌روزرسانی محتوا
            contentElement.innerHTML = htmlContent;
            console.log('Content updated in DOM');

            // به‌روزرسانی data-content-raw (بدون HTML)
            const rawContent = stripHtml(htmlContent);
            messageBubble.setAttribute('data-content-raw', escapeHtml(rawContent));
            console.log('data-content-raw updated');

            // اضافه کردن آیکون ویرایش شده
            if (isEdited) {
                const timestampElement = messageBubble.querySelector('.message-timestamp');
                if (timestampElement) {
                    // اگر آیکون ویرایش وجود ندارد، اضافه کن
                    let editedIcon = timestampElement.querySelector('.message-edited');
                    if (!editedIcon) {
                        editedIcon = document.createElement('span');
                        editedIcon.className = 'message-edited';
                        editedIcon.innerHTML = '<i class="fas fa-edit"></i>';
                        timestampElement.appendChild(editedIcon);
                        console.log('Edited icon added');
                    } else {
                        console.log('Edited icon already exists');
                    }
                } else {
                    console.warn('Timestamp element not found, cannot add edited icon');
                }
            }

            console.log('Message content updated successfully');
        } catch (error) {
            console.error('Error in updateMessageContent:', error);
            console.error('Error stack:', error.stack);
            // خطا را throw کن تا caller بتواند آن را handle کند
            throw error;
        }
    }

    // Helper functions
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function stripHtml(html) {
        if (!html) return '';
        const tmp = document.createElement('DIV');
        tmp.innerHTML = html;
        return tmp.textContent || tmp.innerText || '';
    }

    // تبدیل line breaks به <br> برای نمایش درست
    function nl2br(text) {
        if (!text) return '';
        // Escape HTML first
        const escaped = escapeHtml(text);
        // Convert \n to <br>
        return escaped.replace(/\n/g, '<br>');
    }

    // ذخیره اسکرول هنگام ترک صفحه
    window.addEventListener('beforeunload', () => {
        const lastVisibleId = getLastVisibleMessageId();
        if (lastVisibleId) {
            updateLastReadMessage(lastVisibleId);
        }
        sessionStorage.setItem(STORAGE_KEY, chatBox.scrollTop);
    });

    // به‌روزرسانی last_read_message_id هنگام scroll
    let scrollTimeout = null;
    let scrollSaveTimeout = null; // Timeout برای ذخیره موقعیت scroll

    chatBox.addEventListener('scroll', () => {
        // ذخیره موقعیت scroll با debounce برای بهبود performance
        clearTimeout(scrollSaveTimeout);
        scrollSaveTimeout = setTimeout(() => {
            sessionStorage.setItem(STORAGE_KEY, chatBox.scrollTop);
        }, 500); // 500ms debounce برای ذخیره موقعیت scroll

        // به‌روزرسانی last_read_message_id با debounce
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
            const lastVisibleId = getLastVisibleMessageId();
            if (lastVisibleId) {
                updateLastReadMessage(lastVisibleId);
            }
        }, 300); // 300ms debounce برای scroll
    });

    // استفاده از IntersectionObserver برای به‌روزرسانی دقیق‌تر
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const messageId = entry.target.getAttribute('data-message-id');
                    if (messageId) {
                        const msgId = parseInt(messageId);
                        if (!isNaN(msgId)) {
                            // فقط پیام‌های دیگران را به‌روزرسانی کن
                            const userId = entry.target.querySelector('.message-bubble')?.getAttribute(
                                'data-user-id');
                            if (userId && userId != '{{ auth()->id() }}') {
                                updateLastReadMessage(msgId);
                            }
                        }
                    }
                }
            });
        }, {
            root: chatBox,
            rootMargin: '0px',
            threshold: 0.5 // وقتی 50% پیام visible شد
        });

        // Observe همه پیام‌ها بعد از لود صفحه
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                const messages = chatBox.querySelectorAll('[data-message-id]');
                messages.forEach(msg => observer.observe(msg));
            }, 200);
        });
    }


    (function() {
        function openCatModal() {
            $('#categoryBlogsOverlay').fadeIn(120);
            $('#categoryBlogsModal').fadeIn(120);
            $('body').css('overflow', 'hidden');
        }

        function closeCatModal() {
            $('#categoryBlogsModal').fadeOut(120);
            $('#categoryBlogsOverlay').fadeOut(120, function() {
                $('body').css('overflow', '');
            });
        }

        // اول بسته باشه - اطمینان از اینکه modal مخفی است
        $('#categoryBlogsModal').hide();
        $('#categoryBlogsOverlay').hide();

        // بستن با کلیک یا Esc
        $(document).on('click', '#closeCatModal, #categoryBlogsOverlay', closeCatModal);
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') closeCatModal();
        });

        // باز کردن مدال
        document.querySelectorAll('.open-category-blogs').forEach(openCategory => {
            openCategory.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const ajaxUrl = $(this).data('url');
                const groupId = $(this).data('group-id') || '';

                if (!ajaxUrl) return;

                // ریست UI
                $('#catList').empty().hide();
                $('#catEmpty').hide();
                $('#catLoading').show();
                $('#catModalTitle').text('در حال بارگذاری...');
                openCatModal();

                $.ajax({
                        url: ajaxUrl,
                        method: 'GET',
                        data: {
                            group_id: groupId
                        },
                        dataType: 'json',
                        headers: {
                            'Accept': 'application/json'
                        },
                        cache: false,
                        timeout: 15000 // 15s
                    })
                    .done(function(res) {
                        try {
                            $('#catModalTitle').text(
                                'دسته: ' +
                                (res?.category?.name || '—') +
                                ' (' +
                                (res?.count ?? 0) +
                                ')'
                            );

                            const items = Array.isArray(res?.items) ? res.items : [];
                            $('#catLoading').hide();

                            if (!items.length) {
                                $('#catEmpty').show();
                                return;
                            }

                            const $list = $('#catList').show();

                            items.forEach(function(item) {
                                const $li = $('<li/>').css({
                                    padding: '.75rem .5rem',
                                    borderBottom: '1px solid #eee',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    gap: '.5rem'
                                });

                                const $left = $('<div/>').css({
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: '.25rem'
                                });

                                const $title = $('<a/>', {
                                        href: item.url,
                                        text: item.title,
                                        title: item.title
                                    })
                                    .css({
                                        color: '#0d6efd',
                                        textDecoration: 'none',
                                        fontWeight: '600'
                                    })
                                    .hover(
                                        function() {
                                            $(this).css('text-decoration', 'underline');
                                        },
                                        function() {
                                            $(this).css('text-decoration', 'none');
                                        }
                                    );

                                const $date = $('<small/>', {
                                    text: item.date
                                }).css({
                                    color: '#666'
                                });

                                $left.append($title, $date);

                                const $go = $('<a/>', {
                                    href: item.url,
                                    text: 'مشاهده'
                                }).css({
                                    padding: '.35rem .6rem',
                                    borderRadius: '8px',
                                    border: '1px solid #ddd',
                                    textDecoration: 'none'
                                });

                                $li.append($left, $go);
                                $list.append($li);
                            });
                        } catch (err) {
                            console.error('Parse/render error:', err);
                            $('#catLoading').hide();
                            $('#catEmpty').show().text('خطا در پردازش داده‌ها.');
                        }
                    })
                    .fail(function(xhr, status, err) {
                        console.error('AJAX fail:', status, err, xhr?.status, xhr
                            ?.responseText);
                        $('#catLoading').hide();
                        $('#catEmpty').show().text('خطا در دریافت لیست پست‌ها.');
                    })
                    .always(function() {
                        if ($('#catLoading').is(':visible')) {
                            $('#catLoading').hide();
                            $('#catEmpty').show().text('عدم دریافت پاسخ از سرور.');
                        }
                    });
            });
        });
    })();
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const plusButton = document.getElementById('chatCreateToggle');
        const menu = document.getElementById('createMenu');
        const triggerWrapper = plusButton?.closest('.telegram-attach-btn-wrapper');
        const audioUploadTrigger = document.getElementById('audio-upload-trigger');

        // Auto-resize textarea
        const textarea = document.getElementById('message_editor');
        if (textarea && !textarea.classList.contains('ckeditor-initialized')) {
            function autoResize() {
                if (textarea.scrollHeight > 0) {
                    textarea.style.height = 'auto';
                    textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
                }
            }
            textarea.addEventListener('input', autoResize);
            textarea.classList.add('ckeditor-initialized');
        }

        const voiceFileInput = document.getElementById('voice-file-input');

        const toggleMenu = (visible) => {
            if (!menu) {
                return;
            }
            const shouldShow = typeof visible === 'boolean' ?
                visible :
                (menu.style.display === 'none' || menu.style.display === '');
            menu.style.display = shouldShow ? 'block' : 'none';
        };

        plusButton?.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleMenu();
        });

        document.addEventListener('click', function(e) {
            if (!menu) {
                return;
            }
            const clickedInsideTrigger = triggerWrapper?.contains(e.target);
            const clickedInsideMenu = menu.contains(e.target);
            if (!clickedInsideTrigger && !clickedInsideMenu) {
                toggleMenu(false);
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                toggleMenu(false);
            }
        });

        menu?.querySelectorAll('button').forEach(function(actionButton) {
            // اگر دکمه onclick دارد (مثل openBlogBox, openPollBox)، منو را ببند اما event را متوقف نکن
            if (actionButton.onclick || actionButton.getAttribute('onclick')) {
                actionButton.addEventListener('click', function(e) {
                    // فقط منو را ببند، event را متوقف نکن تا onclick اجرا شود
                    toggleMenu(false);
                });
            } else {
                // برای دکمه‌های دیگر (مثل audio-upload-trigger) که event handler جداگانه دارند
                actionButton.addEventListener('click', function() {
                    toggleMenu(false);
                });
            }
        });

        audioUploadTrigger?.addEventListener('click', function(e) {
            e.preventDefault();
            toggleMenu(false);
            voiceFileInput?.click();
        });

        // Handle create post button
        const createPostBtn = document.getElementById('create-post-btn');
        if (createPostBtn) {
            createPostBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleMenu(false);
                // کمی تأخیر برای بستن منو قبل از باز کردن modal
                setTimeout(function() {
                    // بررسی وجود تابع در scope global
                    if (typeof window.openBlogBox === 'function') {
                        window.openBlogBox();
                    } else if (typeof openBlogBox === 'function') {
                        openBlogBox();
                    } else {
                        console.error('openBlogBox function not found. Available functions:',
                            Object.keys(window).filter(k => k.includes('Blog') || k
                                .includes('Poll')));
                    }
                }, 150);
            });
        }

        // Handle create poll button
        const createPollBtn = document.getElementById('create-poll-btn');
        if (createPollBtn) {
            createPollBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleMenu(false);
                // کمی تأخیر برای بستن منو قبل از باز کردن modal
                setTimeout(function() {
                    // بررسی وجود تابع در scope global
                    if (typeof window.openPollBox === 'function') {
                        window.openPollBox();
                    } else if (typeof openPollBox === 'function') {
                        openPollBox();
                    } else {
                        console.error('openPollBox function not found. Available functions:',
                            Object.keys(window).filter(k => k.includes('Blog') || k
                                .includes('Poll')));
                    }
                }, 150);
            });
        }

    });
    </script>
</div>
</div>
@include('groups.modals.election_form', compact('group'))
@include('groups.modals.post_form', compact('group', 'categories'))
<script>
(function() {
    function interceptPostForm() {
        var postForm = document.getElementById('postForm');
        if (!postForm || postForm._ajaxIntercepted) return;
        postForm._ajaxIntercepted = true;
        postForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            var submitBtn = postForm.querySelector('button[type="submit"]');
            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'در حال ارسال...'; }
            // Sync CKEditor to textarea BEFORE capturing FormData
            if (window.CKEDITOR) {
                Object.values(CKEDITOR.instances).forEach(function(ed) {
                    try { ed.updateElement(); } catch(ex) {}
                });
            }
            var formData = new FormData(postForm);
            var action = postForm.getAttribute('action');
            try {
                var csrfToken = (typeof getCsrfToken === 'function') ? getCsrfToken() :
                    (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                var resp = await fetch(action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
                });
                var data = await resp.json();
                if (data.status === 'success' && data.post && data.post.html) {
                    // reset form and button FIRST
                    postForm.reset();
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'انتشار پست'; }
                    // reset CKEditor instances inside the form
                    if (window.CKEDITOR) {
                        Object.values(CKEDITOR.instances).forEach(function(ed) {
                            try { ed.setData(''); } catch(ex) {}
                        });
                    }
                    // close modal
                    if (typeof cancelPostForm === 'function') cancelPostForm();
                    // inject new post into chat
                    var chatBox = document.getElementById('chat-box');
                    if (chatBox) {
                        var tmp = document.createElement('div');
                        tmp.innerHTML = data.post.html;
                        var postEl = tmp.firstElementChild;
                        if (postEl) {
                            chatBox.appendChild(postEl);
                            // init action menus for this new post
                            if (typeof window._initPostMenus === 'function') window._initPostMenus(postEl);
                            if (typeof window._initReactionButtons === 'function') window._initReactionButtons(postEl);
                            chatBox.scrollTop = chatBox.scrollHeight;
                            // update post polling lastPostId so we don't get duplicate from poll
                            if (data.post.id) { window._lastKnownPostId = Math.max(window._lastKnownPostId || 0, data.post.id); }
                        }
                    }
                } else {
                    alert(data.message || 'خطا در ارسال پست');
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'انتشار پست'; }
                }
            } catch(err) {
                console.error('Post submit error:', err);
                alert('خطا در ارتباط با سرور');
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'انتشار پست'; }
            }
        });
    }
    // Try immediately and also on DOMContentLoaded (form may load late)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', interceptPostForm);
    } else {
        interceptPostForm();
    }
    // Also hook into openBlogBox to ensure form is intercepted when modal opens
    var _origOpenBlogBox = window.openBlogBox;
    Object.defineProperty(window, 'openBlogBox', {
        configurable: true,
        get: function() { return _origOpenBlogBox; },
        set: function(fn) {
            _origOpenBlogBox = function() {
                fn.apply(this, arguments);
                setTimeout(interceptPostForm, 50);
            };
        }
    });
    setTimeout(interceptPostForm, 500);
})();
</script>
@include('groups.modals.poll_form', compact('group'))

@if($electionAvailable && isset($election) && $election)
<div id="electionVotingOverlay" class="election-voting-overlay" style="display: none;">
    <div class="election-voting-overlay__backdrop" onclick="closeElectionBox()"></div>
    @include('groups.modals.election_modal', compact('group', 'election', 'selectedVotesInspector',
    'selectedVotesManager', 'managersSorted', 'inspectorsSorted', 'managerCounts', 'inspectorCounts', 'groupSetting'))
</div>
@endif

@if (session()->has('success'))
<script>
alert('{{ session()->get('
    success ') }}')
</script>
@endif


@push('scripts')
<script>
function installCkeditorChatConfig() {
    if (!window.CKEDITOR || CKEDITOR.__chatWarnFilterInstalled) {
        return false;
    }

    CKEDITOR.__chatWarnFilterInstalled = true;
    CKEDITOR.config.versionCheck = false;

    const currentRemove = (CKEDITOR.config.removePlugins || '')
        .split(',')
        .map(p => p.trim())
        .filter(Boolean);
    if (!currentRemove.includes('uploadimage')) {
        currentRemove.push('uploadimage');
    }
    CKEDITOR.config.removePlugins = currentRemove.join(',');

    const originalWarn = CKEDITOR.warn;
    CKEDITOR.warn = function(message, data) {
        const text = String(message || '');
        if (text.includes('clipboard-image-handling-disabled')) {
            return;
        }
        if (text.includes('version is not secure')) {
            return;
        }
        return originalWarn.call(this, message, data);
    };

    return true;
}

if (!installCkeditorChatConfig()) {
    const ckeditorWait = setInterval(() => {
        if (installCkeditorChatConfig()) {
            clearInterval(ckeditorWait);
        }
    }, 50);
}

CKEDITOR.replace('post_editor', {
    filebrowserUploadUrl: "{{ route('admin.pages.upload') }}?_token={{ csrf_token() }}",
    filebrowserUploadMethod: 'form',
    language: 'fa',
    height: 400,
    removePlugins: 'uploadimage',
    removeButtons: '',
    toolbarGroups: [{
            name: 'basicstyles',
            groups: ['basicstyles', 'cleanup']
        },
        {
            name: 'paragraph',
            groups: ['list', 'indent', 'blocks', 'align']
        },
        {
            name: 'styles'
        },
        {
            name: 'colors'
        },
        {
            name: 'insert'
        },
        {
            name: 'tools'
        },
        {
            name: 'editing'
        },
        {
            name: 'document',
            groups: ['mode', 'document']
        },
        {
            name: 'clipboard',
            groups: ['clipboard', 'undo']
        },
        {
            name: 'links'
        }
    ]
});



// قبلاً اینجا CKEDITOR برای message_editor فعال می‌شد.
// برای ساده‌سازی و حذف CKEditor از چت، این بخش غیرفعال شده است
// و از textarea ساده استفاده می‌کنیم.
</script>
@endpush

<script>
const elementPin = document.querySelector(".pinned-messages");
if (elementPin) elementPin.scrollTop = elementPin.scrollHeight;

function openGroupEdit() {
    document.getElementById('groupEditFormBox').style.display = 'block';
    document.getElementById('back').style.display = 'block';
}

function cancelGroupEdit() {
    document.getElementById('groupEditFormBox').style.display = 'none';
    document.getElementById('back').style.display = 'none';
}

function closeAllModals() {
    document.getElementById('groupEditFormBox').style.display = 'none';
    document.getElementById('back').style.display = 'none';
    // ... existing modal close code ...
}
</script>

<style>
.recording-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #dc3545;
}

.recording-dot {
    width: 8px;
    height: 8px;
    background-color: #dc3545;
    border-radius: 50%;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% {
        transform: scale(1);
        opacity: 1;
    }

    50% {
        transform: scale(1.2);
        opacity: 0.7;
    }

    100% {
        transform: scale(1);
        opacity: 1;
    }
}

/* Voice Recorder Styles */
#voice-record-btn {
    transition: all 0.3s ease;
}

#voice-record-btn:hover {
    transform: scale(1.05);
}

#voice-record-btn:active {
    transform: scale(0.95);
}

#voice-recording-modal {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }

    to {
        opacity: 1;
    }
}

#waveform-canvas {
    image-rendering: pixelated;
}

.pinned-messages {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.5rem;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.pin {
    direction: rtl;
    background-color: #fff;
    display: flex;
    align-items: start;
    justify-content: space-between;
    padding: .5rem;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    text-decoration: none;
    color: inherit;
}

.pin:hover {
    background-color: #f8f9fa;
}

.pin p {
    margin: 0;
    color: #666;
}

.pin i {
    font-size: 1.5rem;
    opacity: .7;
    color: #dc3545;
}

.unpin-btn:hover {
    background: #fee2e2 !important;
    color: #ef4444 !important;
}

/* Telegram Style Input Container */
.telegram-style-input {
    background: #ffffff;
    border-top: 1px solid #e5e5e5;
    padding: 8px 12px;
    direction: rtl;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    margin: 0;
}

.telegram-reply-indicator {
    background: #f0f0f0;
    border-left: 3px solid #3390ec;
    padding: 10px 14px;
    margin: 0 0 10px 0;
    border-radius: 0 8px 8px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    direction: rtl;
    width: 100%;
    box-sizing: border-box;
    position: relative;
}

/* حذف استایل‌های تکراری - reply-indicator دیگر لازم نیست */
.telegram-reply-indicator>.reply-indicator {
    display: none;
}

/* استایل مستقیم برای محتوای داخل telegram-reply-indicator */
.telegram-reply-indicator .reply-info {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    min-width: 0;
}

.telegram-reply-indicator .reply-info {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    min-width: 0;
}

.telegram-reply-indicator .reply-arrow {
    width: 3px;
    height: 100%;
    min-height: 40px;
    background: #3390ec;
    border-radius: 2px;
    flex-shrink: 0;
}

.telegram-reply-indicator .reply-sender-name {
    font-size: 0.9rem;
    font-weight: 600;
    color: #3390ec;
    margin-bottom: 2px;
    line-height: 1.2;
}

.telegram-reply-indicator .reply-content {
    font-size: 0.85rem;
    color: #666;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    line-height: 1.3;
}

.telegram-reply-indicator .btn-cancel-reply {
    background: transparent;
    border: none;
    color: #999;
    cursor: pointer;
    padding: 6px;
    border-radius: 50%;
    transition: all 0.2s;
    flex-shrink: 0;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

.telegram-reply-indicator .btn-cancel-reply:hover {
    background: rgba(0, 0, 0, 0.05);
    color: #333;
}

.telegram-input-container {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    background: #ffffff;
    border: 1px solid #e5e5e5;
    border-radius: 24px;
    padding: 6px 8px;
    min-height: 44px;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    margin: 0;
}

.telegram-attach-btn-wrapper {
    position: relative;
    flex-shrink: 0;
}

.telegram-attach-btn {
    width: 36px;
    height: 36px;
    border: none;
    background: transparent;
    color: #707579;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.2s;
    font-size: 1.1rem;
}

.telegram-attach-btn:hover {
    background: #f0f0f0;
}

.telegram-input-wrapper {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
}

.telegram-textarea {
    width: 100%;
    border: none;
    outline: none;
    background: transparent;
    resize: none;
    font-size: 0.95rem;
    line-height: 1.5;
    padding: 8px 4px;
    max-height: 120px;
    overflow-y: auto;
    direction: rtl;
    font-family: inherit;
}

.telegram-textarea::placeholder {
    color: #999;
}

.telegram-action-buttons {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}

.telegram-action-btn {
    width: 36px;
    height: 36px;
    border: none;
    background: transparent;
    color: #707579;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s;
    font-size: 1.1rem;
}

.telegram-action-btn:hover {
    background: #f0f0f0;
}

.telegram-send-btn {
    background: #3390ec;
    color: #ffffff;
}

.telegram-send-btn:hover {
    background: #2a7fd4;
    color: #ffffff;
}

.telegram-voice-btn {
    color: #707579;
}

.telegram-voice-btn:hover {
    background: #f0f0f0;
    color: #3390ec;
}

.telegram-attach-menu {
    position: absolute;
    bottom: calc(100% + 8px);
    right: 0;
    min-width: 200px;
    background: #ffffff;
    border: 1px solid #e5e5e5;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    padding: 4px;
    z-index: 1000;
    direction: rtl;
}

.telegram-voice-preview {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    background: #f0f0f0;
    border-radius: 12px;
    margin-top: 8px;
    direction: rtl;
}

.telegram-voice-preview i {
    font-size: 1.2rem;
    color: #3390ec;
}

.voice-file-info {
    flex: 1;
    min-width: 0;
}

.voice-file-info div {
    font-size: 0.9rem;
    font-weight: 600;
    color: #333;
}

.voice-file-info small {
    font-size: 0.75rem;
    color: #666;
}

.voice-file-remove-btn {
    background: transparent;
    border: none;
    color: #999;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
    transition: background 0.2s;
    font-size: 0.9rem;
}

.voice-file-remove-btn:hover {
    background: #e5e5e5;
    color: #333;
}

/* حذف wrapper اضافی - محتوا مستقیماً در telegram-reply-indicator قرار می‌گیرد */
#electionRedirect {
    width: 100% !important;
    background-color: #fffce9;

}

@media (max-width: 767px) {
    .telegram-reply-indicator {
        padding: 8px 12px;
        margin-bottom: 8px;
    }

    .telegram-reply-indicator .reply-sender-name {
        font-size: 0.85rem;
    }

    .telegram-reply-indicator .reply-content {
        font-size: 0.8rem;
    }
}

/* بهبود responsive */
@media (max-width: 767px) {
    .telegram-input-container {
        padding: 4px 6px;
        min-height: 40px;
    }

    .telegram-attach-btn,
    .telegram-action-btn {
        width: 32px;
        height: 32px;
        font-size: 1rem;
    }

    .telegram-textarea {
        font-size: 0.9rem;
        padding: 6px 4px;
    }
}

/* Auto-resize textarea */
.telegram-textarea {
    overflow: hidden;
}

.chat-footer {
    width: 100%
}

@media (min-width: 767px) {
    .chat-footer {
        width: calc(100% - 25rem);
    }
}

.election-card {
    width: calc(90% - 400px) !important;
    left: 5%;
}
}

.election-card {
    width: 100%;
    background-color: #fff;
}

.reply-info {
    display: flex;
    align-items: center;
    gap: 10px;
    direction: rtl;
}

.reply-content {
    color: #666;
    font-size: 0.9em;
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    direction: rtl;
}

.btn-cancel-reply {
    background: none;
    border: none;
    color: #dc3545;
    cursor: pointer;
    padding: 5px;
    margin-left: 10px;
}

.btn-cancel-reply:hover {
    color: #c82333;
}

.reply-box {
    direction: rtl;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    padding: 4px 8px;
    background: rgba(0, 0, 0, 0.05);
    border-radius: 4px;
    cursor: pointer;
}

.reply-box:hover {
    background: rgba(0, 0, 0, 0.1);
}

.reply-box .group-avatar {
    flex-shrink: 0;
}

.reply-box .group-avatar span {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
}

.reply-box .reply-content {
    flex-grow: 1;
    overflow: hidden;
}

.reply-box .reply-sender {
    font-weight: bold;
    font-size: 0.9rem;
    margin-bottom: 2px;
}

.reply-box .reply-text {
    font-size: 0.8rem;
    color: #666;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- باز/بسته‌شدن پنل ---
    const wrap = document.getElementById('gc-search-wrap');
    const input = document.getElementById('gc-search-input');
    const btn = document.getElementById('btn-chat-search');
    const dd = document.getElementById('gc-search-dd');
    const statusEl = dd?.querySelector('.gc-search-status');

    if (!wrap || !input || !btn || !dd) {
        // Search DOM elements may not exist on all pages - this is expected
        // console.warn('Search DOM missing', {wrap, input, btn, dd});
        return;
    }

    function openSearch() {
        wrap.hidden = false;
        btn.setAttribute('aria-expanded', 'true');
        setTimeout(() => input.focus(), 10);
    }

    function closeSearch() {
        wrap.hidden = true;
        btn.setAttribute('aria-expanded', 'false');
    }

    function toggleSearch() {
        wrap.hidden ? openSearch() : closeSearch();
    }

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleSearch();
    });
    document.addEventListener('click', (e) => {
        const inside = e.target.closest('#gc-search-wrap') || e.target.closest('#btn-chat-search');
        if (!inside) closeSearch();
    });
    wrap.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeSearch();
            btn.focus();
        }
    });

    // هوک برای اسپینر آیکن/استاتوس
    window.__setSearching = function(on) {
        statusEl.style.display = on ? 'flex' : 'none';
        btn.classList.toggle('searching', !!on);
    };
    window.__ensureSearchOpen = function() {
        if (wrap.hidden) openSearch();
    };

    // --- سرچ AJAX ---
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
    const groupId = {{ $group->id }};
    const listEl = dd.querySelector('.gc-search-list');
    const moreBtn = dd.querySelector('.gc-search-more');
    const clearBtn = document.getElementById('gc-search-clear');

    let page = 1,
        loading = false,
        lastQuery = '',
        items = [],
        activeIndex = -1,
        hasMore = false;
    const openDD = () => dd.hidden = false;
    const closeDD = () => {
        dd.hidden = true;
        activeIndex = -1;
        updateActive();
    };

    function debounce(fn, ms = 300) {
        let t;
        return (...a) => {
            clearTimeout(t);
            t = setTimeout(() => fn(...a), ms);
        }
    }

    function setStatus(txt) {
        statusEl.textContent = txt;
        statusEl.style.display = txt ? 'flex' : 'none';
    }

    function setMore(vis) {
        moreBtn.hidden = !vis;
    }

    function renderList() {
        listEl.innerHTML = '';
        items.forEach((it, idx) => {
            const li = document.createElement('li');
            li.className = 'gc-search-item' + (idx === activeIndex ? ' active' : '');
            li.dataset.index = idx;

            const type = document.createElement('div');
            type.className = 'type';
            type.textContent = it.type === 'message' ? 'پیام' : it.type === 'post' ? 'پست' : 'نظرسنجی';

            const meta = document.createElement('div');
            meta.className = 'meta';
            const title = document.createElement('div');
            title.className = 'title';
            title.textContent = it.title || (it.type === 'post' ? 'پست' : it.type === 'poll' ?
                'نظرسنجی' : 'کاربر');
            const snip = document.createElement('div');
            snip.className = 'snip';
            snip.innerHTML = it.snippet || '';
            const date = document.createElement('small');
            date.style.color = '#6b7280';
            date.textContent = it.date || '';
            meta.appendChild(title);
            meta.appendChild(snip);
            meta.appendChild(date);
            li.appendChild(type);
            li.appendChild(meta);
            li.addEventListener('click', () => goTo(it));
            listEl.appendChild(li);
        });
    }

    function updateActive() {
        listEl.querySelectorAll('.gc-search-item').forEach((el, i) => el.classList.toggle('active', i ===
            activeIndex));
    }

    function goTo(it) {
        closeDD();
        if (!it?.url) return;
        location.hash = it.url;
    }

    async function fetchPage(reset = false) {
        if (loading) return;
        loading = true;
        window.__setSearching(true);
        setStatus('در حال جستجو…');
        if (reset) {
            items = [];
            page = 1;
            listEl.innerHTML = '';
            setMore(false);
        }

        try {
            const url = new URL(`{{ url('/groups') }}/${groupId}/search`, window.location.origin);
            url.searchParams.set('q', lastQuery);
            url.searchParams.set('page', page);
            url.searchParams.set('limit', 20);

            const res = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                credentials: 'same-origin'
            });

            if (!res.ok) {
                // برای کمک به دیباگ
                const txt = await res.text();
                console.error('Search HTTP Error', res.status, txt);
                throw new Error('HTTP ' + res.status);
            }

            const data = await res.json();
            const newItems = Array.isArray(data?.items) ? data.items : [];
            hasMore = !!data?.has_more;

            items = items.concat(newItems);
            renderList();
            setStatus(newItems.length ? '' : (page === 1 ? 'چیزی پیدا نشد.' : ''));
            setMore(hasMore);
            openDD();
            window.__ensureSearchOpen();
        } catch (e) {
            console.error('Search fetch error', e);
            setStatus('خطا در دریافت نتایج');
            openDD();
            window.__ensureSearchOpen();
        } finally {
            loading = false;
            window.__setSearching(false);
        }
    }

    const onInput = debounce(() => {
        const q = input.value.trim();
        if (!q) {
            closeDD();
            return;
        }
        lastQuery = q;
        fetchPage(true);
    }, 300);

    input.addEventListener('input', onInput);
    input.addEventListener('focus', () => {
        if (items.length) openDD();
        else if (input.value.trim()) {
            lastQuery = input.value.trim();
            fetchPage(true);
        }
    });
    clearBtn.addEventListener('click', () => {
        input.value = '';
        closeDD();
        items = [];
        listEl.innerHTML = '';
    });
    moreBtn.addEventListener('click', () => {
        if (!hasMore) return;
        page += 1;
        fetchPage(false);
    });

    // بستن dropdown با کلیک بیرون از سرچ‌بار
    document.addEventListener('click', (e) => {
        const box = e.target.closest('.gc-searchbar');
        if (!box) closeDD();
    });

    // ناوبری کیبورد
    input.addEventListener('keydown', (e) => {
        if (dd.hidden) return;
        const max = items.length - 1;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIndex = activeIndex < max ? activeIndex + 1 : 0;
            updateActive();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIndex = activeIndex > 0 ? activeIndex - 1 : max;
            updateActive();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeIndex >= 0) goTo(items[activeIndex]);
        } else if (e.key === 'Escape') {
            closeDD();
        }
    });
});
</script>

<script>
// Add pin/unpin functionality
function pinMessage(messageId) {
    if (!confirm('آیا مایل به سنجاق کردن این پیام هستید؟')) return;

    fetch(`/groups/messages/${messageId}/pin`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // ✅ FIXED: No location.reload() - update DOM smoothly
                const messageEl = document.getElementById(`msg-${id}`);
                if (messageEl && !messageEl.querySelector('.pinned-badge')) {
                    const badge = document.createElement('span');
                    badge.className = 'pinned-badge';
                    badge.textContent = '📌 سنجاق شده';
                    messageEl.appendChild(badge);
                }
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'موفقیت‌آمیز',
                        text: 'پیام با موفقیت سنجاق شد.',
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    alert('پیام با موفقیت سنجاق شد.');
                }
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطا',
                        text: data.message
                    });
                } else {
                    alert(data.message);
                }
            }
        });
}

function unpinMessage(messageId) {
    if (!confirm('آیا مایل به برداشتن این پیام از حالت سنجاق هستید؟')) return;

    fetch(`/groups/messages/${messageId}/unpin`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // ✅ FIXED: No location.reload() - update DOM smoothly
                const messageEl = document.getElementById(`msg-${id}`);
                if (messageEl) {
                    const pinnedBadge = messageEl.querySelector('.pinned-badge');
                    if (pinnedBadge) pinnedBadge.remove();
                }
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'موفقیت‌آمیز',
                        text: 'پیام از حالت سنجاق خارج شد.',
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    alert('پیام از حالت سنجاق خارج شد.');
                }
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطا',
                        text: data.message
                    });
                } else {
                    alert(data.message);
                }
            }
        });
}
</script>

<script>
document.addEventListener('click', function(e) {
    // همه‌ی منوهای باز
    document.querySelectorAll('details.menu-wrapper[open]').forEach(function(d) {
        // اگر کلیک بیرون از همین منو بوده، ببند
        if (!d.contains(e.target)) d.removeAttribute('open');
    });
});

// جلوگیری از بسته‌شدن هنگام کلیک داخل منو
document.addEventListener('click', function(e) {
    const dropdown = e.target.closest('.menu-dropdown');
    if (dropdown) {
        e.stopPropagation();
    }
});

// با ESC هم ببند
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('details.menu-wrapper[open]').forEach(d => d.removeAttribute('open'));
    }
});
</script>

{{-- Modal مدیریت اعضا - همیشه در DOM باشد اما فقط برای مدیران قابل مشاهده --}}
<div id="manageMembersModal" class="modal-shell" style="display: none;" dir="rtl"
    onclick="handleModalClick(event, 'manageMembersModal')">
    <div class="modal-shell__dialog" onclick="event.stopPropagation()">
        <div class="modal-shell__header">
            <h3 class="modal-shell__title">
                <i class="fas fa-users-cog me-2 text-blue-500"></i>
                مدیریت اعضای گروه
            </h3>
            <button type="button" class="modal-shell__close" onclick="closeManageMembersModal()">×</button>
        </div>

        <div class="modal-shell__form">
            <div id="members-loading" class="text-center py-8" style="display: none;">
                <i class="fas fa-spinner fa-spin text-2xl text-blue-500"></i>
                <p class="mt-2 text-slate-600">در حال بارگذاری...</p>
            </div>

            <div id="members-error" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-4"
                style="display: none;">
                <i class="fas fa-exclamation-circle ml-2"></i>
                <span id="members-error-text"></span>
            </div>

            <div id="members-list"
                style="max-height: 400px; overflow-y: auto; padding: 0.5rem 0; min-height: 100px; display: block; visibility: visible;">
                <!-- لیست اعضا اینجا نمایش داده می‌شود -->
            </div>
        </div>
    </div>
</div>

{{-- Modal مدیریت گزارش‌ها --}}
@if(($yourRole ?? 0) == 3)
<div id="manageReportsModal" class="modal-shell" style="display: none;" dir="rtl"
    onclick="handleModalClick(event, 'manageReportsModal')">
    <div class="modal-shell__dialog" onclick="event.stopPropagation()" style="max-width: 900px; width: 90vw;">
        <div class="modal-shell__header">
            <h3 class="modal-shell__title">
                <i class="fas fa-flag me-2 text-orange-500"></i>
                مدیریت گزارش‌های پیام
            </h3>
            <button type="button" class="modal-shell__close" onclick="closeManageReportsModal()">×</button>
        </div>

        <div class="modal-shell__form">
            <div id="reports-loading" class="text-center py-8" style="display: none;">
                <i class="fas fa-spinner fa-spin text-2xl text-orange-500"></i>
                <p class="mt-2 text-slate-600">در حال بارگذاری...</p>
            </div>

            <div id="reports-error" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-4"
                style="display: none;">
                <i class="fas fa-exclamation-circle ml-2"></i>
                <span id="reports-error-text"></span>
            </div>

            <div id="reports-list" class="space-y-3 max-h-96 overflow-y-auto">
                <!-- لیست گزارش‌ها اینجا نمایش داده می‌شود -->
            </div>
        </div>
    </div>
</div>
@endif

</div>

<!-- کد نهایی حفظ و بازیابی موقعیت اسکرول و مدیریت دکمه شناور -->
<script>
(function() {
    'use strict';

    const STORAGE_KEY = 'pageScroll_{{ $group->id }}';
    const restorationDelays = [0, 250, 700, 1400, 2600, 4200];
    window.scrollPositionRestored = false;

    function initUnifiedScroll() {
        // Get button - check after DOM is ready
        let btn = document.getElementById('scroll-toggle-btn');
        if (!btn) {
            setTimeout(initUnifiedScroll, 100);
            return;
        }

        let restored = false;

        function tryRestore() {
            if (restored) return;

            const stored = parseInt(sessionStorage.getItem(STORAGE_KEY) || '0', 10);
            if (stored > 0) {
                window.scrollTo(0, stored);
            }

            if (stored <= 0 || Math.abs(window.scrollY - stored) <= 5) {
                restored = true;
                window.scrollPositionRestored = true;
            }
        }

        restorationDelays.forEach(delay => setTimeout(tryRestore, delay));

        window.addEventListener('scroll', function() {
            if (btn) {
                btn.classList.toggle('visible', window.scrollY > 80);
            }

            if (window.scrollPositionRestored) {
                clearTimeout(window.scTimer);
                window.scTimer = setTimeout(() => {
                    sessionStorage.setItem(STORAGE_KEY, window.scrollY);
                }, 200);
            }
        });

        if (btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        }
    }

    if (document.readyState === 'complete') {
        initUnifiedScroll();
    } else {
        window.addEventListener('load', initUnifiedScroll);
    }

    if (typeof addReactionButton === 'function') {
        document.querySelectorAll('.message-bubble').forEach(b => {
            if (b.dataset.messageId) addReactionButton(b);
        });
    }
})();
</script>

@endsection
