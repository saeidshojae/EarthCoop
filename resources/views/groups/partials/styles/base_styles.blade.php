<style>
/* Collapsible Group Info Card برای موبایل */
.group-info-card [data-group-hero-content][hidden] {
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

.group-info-card .collapse-content.is-expanded {
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
    padding: 0 1.25rem 1.25rem !important;
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
    bottom: 96px !important;
    right: 24px !important;
    left: auto !important;
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

/* Voice recorder controls: isolate icon metrics from generic RTL button rules. */
#voice-recording-modal #recording-controls button,
#voice-recording-modal #cancel-recording-btn,
#voice-recording-modal #send-recording-btn {
    display: inline-flex;
    flex-direction: row;
    align-items: center !important;
    justify-content: center !important;
    gap: 7px !important;
    min-height: 44px;
    line-height: 1 !important;
    white-space: nowrap;
}

#voice-recording-modal button > i {
    position: static !important;
    display: inline-flex !important;
    flex: 0 0 1em;
    width: 1em !important;
    height: 1em !important;
    margin: 0 !important;
    padding: 0 !important;
    align-items: center;
    justify-content: center;
    line-height: 1 !important;
    vertical-align: middle;
    transform-origin: 50% 50% !important;
}

#voice-recording-modal #send-recording-btn > .fa-spinner {
    animation: voice-recorder-spin .8s linear infinite !important;
    will-change: transform;
}

@keyframes voice-recorder-spin {
    to { transform: rotate(360deg); }
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

.save-edit.btn-loading {
    padding-inline-start: 2rem !important;
}

.save-edit.btn-loading::after {
    inset-inline-start: .65rem;
    right: auto;
    top: 50%;
    box-sizing: border-box;
    transform: translateY(-50%) rotate(0deg);
    transform-origin: 50% 50%;
    animation: message-edit-button-spin .8s linear infinite;
}

@keyframes message-edit-button-spin {
    from { transform: translateY(-50%) rotate(0deg); }
    to { transform: translateY(-50%) rotate(360deg); }
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

.menu-meta-time__item--edited {
    margin-top: 6px;
    padding-top: 6px;
    border-top: 1px solid rgba(0, 0, 0, .08);
}

.content-meta-line {
    display: grid !important;
    grid-template-columns: max-content max-content minmax(0, 1fr) max-content;
    align-items: center;
    column-gap: 6px;
    row-gap: 4px;
    direction: rtl !important;
    width: 100%;
    min-width: 0;
}

.message-timestamp.content-meta-line {
    box-sizing: border-box;
    width: calc(100% + 20px);
    margin-inline: -10px !important;
    padding-inline: 2px !important;
}

.content-meta-line .message-primary-meta,
.content-meta-line > .content-meta-time {
    grid-column: 1;
    grid-row: 1;
    margin: 0 !important;
    white-space: nowrap;
}
.content-meta-line .message-edit-status,
.content-meta-line > .content-edit-status {
    grid-column: 2;
    grid-row: 1;
    white-space: nowrap;
    opacity: .72;
    font-size: .66rem;
}
.content-meta-line .message-reactions-slot,
.content-meta-line > .content-reactions-slot,
.content-meta-line > .reaction-buttons {
    grid-column: 3;
    grid-row: 1;
    min-width: 0;
    margin: 0 !important;
    display: flex !important;
    justify-content: center;
    overflow-x: auto;
    overflow-y: hidden;
    scrollbar-width: none;
}
.content-meta-line .message-reactions-slot::-webkit-scrollbar,
.content-meta-line > .content-reactions-slot::-webkit-scrollbar,
.content-meta-line > .reaction-buttons::-webkit-scrollbar { display: none; }
.content-meta-line .message-reactions {
    display: flex !important;
    flex-wrap: nowrap !important;
    align-items: center;
    gap: 3px !important;
    justify-content: center;
    min-width: 0;
    margin: 0 !important;
}
.content-meta-line > .read-receipt,
.content-meta-line > .content-read-receipt {
    grid-column: 4;
    grid-row: 1;
    justify-self: start;
    margin: 0 !important;
    white-space: nowrap;
    text-align: left !important;
}
.content-meta-line > .post-card__comments,
.content-meta-line > .poll-card__status {
    grid-column: 1 / -1;
    grid-row: 2;
}

.post-edit-modal__close {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    width: 32px !important;
    height: 32px !important;
    padding: 0 !important;
    border-radius: 50%;
    background: rgba(100, 116, 139, .10) !important;
    color: #64748b !important;
    font-size: 1.35rem;
    font-weight: 500;
    line-height: 1;
    opacity: 1 !important;
    position: absolute !important;
    left: 16px !important;
    right: auto !important;
    top: 50%;
    margin: 0 !important;
    z-index: 2;
    transform: translateY(-50%);
    transition: color .18s ease, background-color .18s ease, transform .18s ease;
}

.post-edit-modal__header {
    position: relative;
    padding-left: 58px !important;
}

.post-edit-modal__close:hover,
.post-edit-modal__close:focus-visible {
    background: rgba(51, 65, 85, .18) !important;
    color: #1f2937 !important;
    transform: translateY(-50%) scale(1.05);
}

.post-edit-modal__dismiss {
    border: 1px solid #cbd5e1 !important;
    background: #f1f5f9 !important;
    color: #64748b !important;
    opacity: 1 !important;
}

.post-edit-modal__dismiss:hover,
.post-edit-modal__dismiss:focus-visible {
    border-color: #94a3b8 !important;
    background: #e2e8f0 !important;
    color: #334155 !important;
}

.content-meta-line .reaction-badge {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    min-width: 27px;
    min-height: 20px;
    padding: 1px 5px !important;
    line-height: 1;
    white-space: nowrap;
}

.message-timestamp {
    font-variant-numeric: tabular-nums;
}

@media (max-width: 768px) {
    .message-bubble,
    .message-row .message-bubble,
    .message-row.you .message-bubble,
    .message-row.other .message-bubble {
        min-width: min(245px, calc(100vw - 92px)) !important;
    }

    .content-meta-line {
        column-gap: 4px;
    }

    .content-meta-line .message-edit-status,
    .content-meta-line > .content-edit-status {
        font-size: .62rem;
    }
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




.category-browser__overlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 1100;
    background: rgba(15, 23, 42, .48);
    backdrop-filter: blur(3px);
}

.category-browser {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 1110;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.category-browser__panel {
    width: min(700px, 100%);
    max-height: min(80vh, 640px);
    overflow: hidden;
    direction: rtl;
    background: #fff;
    border: 1px solid rgba(15, 23, 42, .08);
    border-radius: 14px;
    box-shadow: 0 18px 55px rgba(15, 23, 42, .24);
}

.category-browser__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 52px;
    padding: .75rem 1rem;
    background: #f8fafc;
    border-bottom: 1px solid #e5e7eb;
}

.category-browser__close {
    display: inline-grid;
    width: 2rem;
    height: 2rem;
    place-items: center;
    border: 0;
    border-radius: 999px;
    color: #475569;
    background: transparent;
    font-size: 1.4rem;
    line-height: 1;
    cursor: pointer;
}

.category-browser__close:hover { background: #e2e8f0; color: #0f172a; }
.category-browser__body { max-height: calc(min(80vh, 640px) - 52px); padding: .35rem 1rem; overflow: auto; }
.category-browser__list { display: none; margin: 0; padding: 0; list-style: none; }
.category-browser__status { display: none; padding: 1.5rem 1rem; color: #64748b; text-align: center; }
.category-browser__row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .8rem .25rem; border-bottom: 1px solid #e5e7eb; }
.category-browser__row:last-child { border-bottom: 0; }
.category-browser__details { display: flex; min-width: 0; flex-direction: column; gap: .2rem; }
.category-browser__title { overflow: hidden; color: #6d28d9; font-weight: 700; text-decoration: none; text-overflow: ellipsis; white-space: nowrap; }
.category-browser__title:hover { text-decoration: underline; }
.category-browser__date { color: #64748b; font-size: .75rem; }
.category-browser__view { flex: 0 0 auto; padding: .38rem .7rem; border: 1px solid #d1d5db; border-radius: 8px; color: #334155; background: #fff; text-decoration: none; }
.category-browser__view:hover { border-color: #8b5cf6; color: #6d28d9; background: #f5f3ff; }

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

/* Mobile-only layout guard: keep bubbles/cards inside viewport without touching chat logic */
@media (max-width: 768px) {
    #chat-box,
    .chat-body {
        overflow-x: hidden !important;
    }

    .message-row,
    .post-wrapper,
    .poll-wrapper {
        width: 100% !important;
        max-width: 100% !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        box-sizing: border-box !important;
    }

    .message-bubble,
    .message-row .message-bubble,
    .message-row.you .message-bubble,
    .message-row.other .message-bubble {
        min-width: min(245px, calc(100vw - 92px)) !important;
        max-width: calc(100vw - 92px) !important;
        width: auto !important;
        box-sizing: border-box !important;
    }

    .message-content {
        width: auto !important;
        max-width: 100% !important;
        display: block !important;
    }

    .message-bubble.message-bubble--voice,
    .message-row.you .message-bubble.message-bubble--voice,
    .message-row.other .message-bubble.message-bubble--voice {
        min-width: min(240px, calc(100vw - 92px)) !important;
    }

    .message-row.you .voice-message-content,
    .message-row.other .voice-message-content {
        min-width: 165px !important;
    }

    .message-row.you .voice-player,
    .message-row.other .voice-player {
        min-width: 130px !important;
    }

    .post-card,
    .poll-card,
    .election-card {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
    }
}
</style>
