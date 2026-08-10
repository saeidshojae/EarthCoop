@extends('layouts.chat')

@php
$lastReadMessageId = $lastReadMessageId ?? null;
@endphp

@section('title', $group->name . ' - گفت‌وگوی گروه')

@section('head-tag')

<!-- Tailwind & Bootstrap CSS via Vite -->
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

<script src="{{ asset('vendor/ckeditor/ckeditor.js') }}"></script>



<!-- CSRF Token (برای Ajax) -->
<meta name="csrf-token" content="{{ csrf_token() }}">


<link rel="stylesheet" href="{{ asset('Css/group-chat.css') }}">



@include('groups.partials.chat_runtime')
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
        min-width: 0 !important;
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
    class="container mx-auto max-w-7xl px-4 md:px-8 pt-0 pb-8 space-y-6 md:space-y-10 group-chat-container"
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
                        data-chat-page-action="open-group-info">
                        <i class="fas fa-layer-group"></i>
                        پنل گروه
                    </button>
                    @if(($yourRole ?? 0) !== 5)
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-emerald-500 text-white shadow-sm hover:bg-emerald-600 transition"
                        data-chat-page-action="open-blog">
                        <i class="far fa-pen-to-square"></i>
                        ایجاد پست
                    </button>
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-emerald-100 text-emerald-600 hover:bg-emerald-200 transition"
                        data-chat-page-action="open-poll">
                        <i class="fas fa-chart-simple"></i>
                        ساخت نظرسنجی
                    </button>
                    @endif
                    @if($electionAvailable)
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl {{ $canParticipateElection ? 'bg-indigo-500 text-white shadow-sm hover:bg-indigo-600 transition' : 'bg-slate-100 text-slate-500 cursor-not-allowed' }}"
                        @if($canParticipateElection) data-chat-page-action="open-election" @else disabled @endif>
                        <i class="fas fa-vote-yea"></i>
                        {{ $canParticipateElection ? 'شرکت در انتخابات' : 'انتخابات فعال' }}
                    </button>
                    @endif
                    @if(in_array($yourRole ?? 0, [2,3]))
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition"
                        data-chat-page-action="open-election-admin">
                        <i class="fas fa-ballot-check text-emerald-500"></i>
                        افزودن انتخابات
                    </button>
                    @endif
                    @if(($yourRole ?? 0) == 3)
                    <button type="button" id="manage-members-btn"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-blue-200 text-blue-600 hover:bg-blue-50 transition"
                        data-chat-page-action="manage-members">
                        <i class="fas fa-users-cog"></i>
                        مدیریت اعضا
                    </button>
                    <button type="button" id="manage-reports-btn"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-orange-200 text-orange-600 hover:bg-orange-50 transition relative"
                        data-chat-page-action="manage-reports">
                        <i class="fas fa-flag"></i>
                        گزارش‌ها
                        <span id="reports-badge"
                            class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"
                            style="display: none;">0</span>
                    </button>
                    @endif
                    <button type="button" id="group-settings-btn"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-emerald-200 text-emerald-600 hover:bg-emerald-50 transition"
                        data-chat-page-action="group-settings">
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
                        data-chat-page-action="open-group-info">
                        <i class="fas fa-layer-group"></i>
                        پنل گروه
                    </button>
                    @if(($yourRole ?? 0) !== 5)
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-emerald-500 text-white shadow-sm hover:bg-emerald-600 transition"
                        data-chat-page-action="open-blog">
                        <i class="far fa-pen-to-square"></i>
                        ایجاد پست
                    </button>
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-emerald-100 text-emerald-600 hover:bg-emerald-200 transition"
                        data-chat-page-action="open-poll">
                        <i class="fas fa-chart-simple"></i>
                        ساخت نظرسنجی
                    </button>
                    @endif
                    @if($electionAvailable)
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl {{ $canParticipateElection ? 'bg-indigo-500 text-white shadow-sm hover:bg-indigo-600 transition' : 'bg-slate-100 text-slate-500 cursor-not-allowed' }}"
                        @if($canParticipateElection) data-chat-page-action="open-election" @else disabled @endif>
                        <i class="fas fa-vote-yea"></i>
                        {{ $canParticipateElection ? 'شرکت در انتخابات' : 'انتخابات فعال' }}
                    </button>
                    @endif
                    @if(in_array($yourRole ?? 0, [2,3]))
                    <button type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition"
                        data-chat-page-action="open-election-admin">
                        <i class="fas fa-ballot-check text-emerald-500"></i>
                        افزودن انتخابات
                    </button>
                    @endif
                    @if(($yourRole ?? 0) == 3)
                    <button type="button" id="manage-members-btn"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-blue-200 text-blue-600 hover:bg-blue-50 transition"
                        data-chat-page-action="manage-members">
                        <i class="fas fa-users-cog"></i>
                        مدیریت اعضا
                    </button>
                    <button type="button" id="manage-reports-btn"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-orange-200 text-orange-600 hover:bg-orange-50 transition relative"
                        data-chat-page-action="manage-reports">
                        <i class="fas fa-flag"></i>
                        گزارش‌ها
                        <span id="reports-badge"
                            class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"
                            style="display: none;">0</span>
                    </button>
                    @endif
                    <button type="button" id="group-settings-btn"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl border border-emerald-200 text-emerald-600 hover:bg-emerald-50 transition"
                        data-chat-page-action="group-settings">
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
                        <button type="button" data-chat-page-action="unpin" data-message-id="{{ $pinnedMessage->message->id }}"
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

            <div class="chat-composer-shell bg-white border border-emerald-100 rounded-3xl shadow-sm p-5 w-full">
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
    async function confirmDelete(event, url) {
        event.preventDefault();
        if (await window.groupChatConfirm('آیا مطمئن هستید که می‌خواهید این آیتم را حذف کنید؟', { confirmText: 'حذف' })) {
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

    @include('groups.partials.message_edit_runtime')
    @include('groups.partials.legacy_message_runtime')
    @include('groups.partials.composer_actions_runtime')
</div>
</div>
@include('groups.modals.election_form', compact('group'))
@include('groups.modals.post_form', compact('group', 'categories'))
@include('groups.partials.post_submission_runtime')
@include('groups.modals.poll_form', compact('group'))

@if($electionAvailable && isset($election) && $election)
<div id="electionVotingOverlay" class="election-voting-overlay" style="display: none;">
    <div class="election-voting-overlay__backdrop" data-chat-page-action="close-election"></div>
    @include('groups.modals.election_modal', compact('group', 'election', 'selectedVotesInspector',
    'selectedVotesManager', 'managersSorted', 'inspectorsSorted', 'managerCounts', 'inspectorCounts', 'groupSetting'))
</div>
@endif

@include('groups.partials.page_chrome_runtime')

@push('scripts')
@include('groups.partials.ckeditor_runtime')
@endpush

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

    /* Composer shell is useful on desktop, but on mobile chat form is fixed and this wrapper becomes an empty white block. */
    .chat-composer-shell {
        background: transparent !important;
        border: 0 !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        padding: 0 !important;
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
@include('groups.partials.chat_search_runtime')

@include('groups.partials.pin_runtime')

@include('groups.partials.action_menu_dismissal')

@include('groups.partials.management_modals')

</div>

<!-- مدیریت حرفه‌ای اسکرول چت: ورود اول از ابتدا، ورودهای بعدی از اولین پیام نخوانده -->
@include('groups.partials.scroll_unread_runtime')

@endsection
