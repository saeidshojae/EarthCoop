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
    position: relative;
    z-index: 2;
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

.chat-session-closed {
        display: flex;
        align-items: flex-start;
        gap: .75rem;
        padding: .9rem 1rem;
        color: #92400e;
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 1rem;
    }
    .chat-session-closed p { margin: .2rem 0 0; font-size: .82rem; line-height: 1.75; color: #a16207; }
    .member-session-permission {
        border: 1px solid #d1d5db;
        background: #fff;
        color: #4b5563;
        border-radius: .65rem;
        padding: .4rem .65rem;
        font-size: .72rem;
        white-space: nowrap;
    }
    .member-session-permission:hover { border-color: #10b981; color: #047857; background: #ecfdf5; }
    .member-session-permission.is-allowed { border-color: #6ee7b7; color: #047857; background: #d1fae5; }
    .session-request-trigger { margin-top:.7rem;border:0;border-radius:.7rem;padding:.55rem .85rem;background:#f59e0b;color:#fff;font-weight:700; }
    .session-participation-badge { margin-right:auto;min-width:1.35rem;height:1.35rem;padding:0 .35rem;border-radius:999px;display:inline-grid;place-items:center;background:#ef4444;color:#fff;font-size:.68rem;font-weight:800;box-shadow:0 3px 10px rgba(239,68,68,.3); }
    .session-participation-badge[hidden] { display:none !important; }
    .session-participation-badge.is-pulsing { animation:session-request-pulse .7s ease-out; }
    @keyframes session-request-pulse { 0%{transform:scale(.75)} 55%{transform:scale(1.2)} 100%{transform:scale(1)} }
    .session-participation-modal[hidden] { display:none !important; }
    .session-participation-modal { position:fixed;inset:0;z-index:11000;display:grid;place-items:center;padding:1rem; }
    .session-participation-modal__backdrop { position:absolute;inset:0;border:0;background:rgba(15,23,42,.54);backdrop-filter:blur(3px); }
    .session-participation-modal__dialog { position:relative;width:min(94vw,520px);max-height:88vh;overflow:auto;background:#fff;border-radius:1.35rem;box-shadow:0 24px 70px rgba(15,23,42,.3); }
    .session-participation-modal__dialog--admin { width:min(94vw,880px); }
    .session-participation-modal__header { display:flex;gap:.8rem;align-items:center;padding:1.1rem 1.2rem;border-bottom:1px solid #e2e8f0; }
    .session-participation-modal__header h3 { margin:0;font-size:1rem;font-weight:800;color:#0f172a; }
    .session-participation-modal__header p { margin:.15rem 0 0;font-size:.78rem;color:#64748b; }
    .session-participation-modal__icon { width:42px;height:42px;border-radius:13px;display:grid;place-items:center;background:#fef3c7;color:#d97706;flex:none; }
    .session-participation-modal__icon.is-admin { background:#d1fae5;color:#047857; }
    .session-participation-modal__close { margin-right:auto;border:0;background:#f1f5f9;color:#64748b;width:34px;height:34px;border-radius:50%;font-size:1.25rem; }
    .session-participation-modal__body { padding:1.1rem 1.2rem; }
    .session-participation-modal__body label { display:block;font-weight:700;color:#334155;margin-bottom:.45rem; }
    .session-participation-modal__body label span { font-weight:400;color:#94a3b8;font-size:.75rem; }
    .session-participation-modal textarea,.session-admin-toolbar input[type=search] { width:100%;border:1px solid #cbd5e1;border-radius:.85rem;padding:.75rem;background:#f8fafc;outline:none; }
    .session-participation-modal textarea:focus,.session-admin-toolbar input:focus { border-color:#10b981;box-shadow:0 0 0 3px rgba(16,185,129,.12); }
    .session-participation-modal__footer { display:flex;gap:.6rem;flex-wrap:wrap;padding:1rem 1.2rem;border-top:1px solid #e2e8f0; }
    .session-primary-btn,.session-secondary-btn,.session-danger-btn { border:0;border-radius:.75rem;padding:.65rem .9rem;font-weight:700; }
    .session-primary-btn { background:#059669;color:#fff; }.session-secondary-btn { background:#f1f5f9;color:#475569; }.session-danger-btn { background:#fee2e2;color:#b91c1c; }
    .session-participation-status { margin:.75rem 1.2rem;padding:.7rem .8rem;border-radius:.75rem;font-size:.82rem; }
    .session-participation-status.is-success { background:#d1fae5;color:#047857; }.session-participation-status.is-error { background:#fee2e2;color:#b91c1c; }
    .session-admin-toolbar { display:flex;align-items:center;gap:1rem;padding:1rem 1.2rem; }.session-admin-toolbar input { flex:1; }.session-admin-toolbar label { white-space:nowrap;font-size:.8rem;color:#475569; }
    .session-admin-columns { display:grid;grid-template-columns:1fr 1fr;gap:1rem;padding:0 1.2rem 1rem; }.session-admin-columns h4 { font-size:.88rem;font-weight:800;color:#334155;display:flex;justify-content:space-between; }
    .session-member-list { display:grid;gap:.5rem;max-height:360px;overflow:auto; }.session-member-card { display:flex;align-items:center;gap:.65rem;padding:.7rem;border:1px solid #e2e8f0;border-radius:.9rem;background:#fff;cursor:pointer; }.session-member-card:hover { border-color:#6ee7b7;background:#f0fdf4; }.session-member-card.is-allowed { background:#ecfdf5; }
    .session-member-avatar { width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#e0f2fe;color:#0369a1;font-weight:800; }.session-member-copy { display:grid;min-width:0;flex:1; }.session-member-copy strong { font-size:.82rem; }.session-member-copy small { color:#64748b;font-size:.7rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
    .session-pending-badge,.session-allowed-badge { font-size:.65rem;border-radius:999px;padding:.25rem .45rem;white-space:nowrap; }.session-pending-badge { background:#fef3c7;color:#b45309; }.session-allowed-badge { background:#d1fae5;color:#047857; }.session-admin-empty { text-align:center;color:#94a3b8;padding:1.5rem;font-size:.82rem; }
    body.session-modal-open { overflow:hidden; }
    @media(max-width:700px){.session-admin-columns{grid-template-columns:1fr}.session-admin-toolbar{align-items:stretch;flex-direction:column}.session-participation-modal__dialog{max-height:94vh}.session-admin-actions>*{flex:1}.session-pending-badge{display:none}}

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

    .chat-composer-shell--restricted {
        position: fixed;
        z-index: 1040;
        right: max(10px, env(safe-area-inset-right));
        left: max(10px, env(safe-area-inset-left));
        bottom: max(10px, env(safe-area-inset-bottom));
        width: auto !important;
        margin: 0 !important;
        pointer-events: none;
    }

    .chat-composer-shell--restricted .chat-session-closed {
        pointer-events: auto;
        align-items: center;
        padding: .65rem .75rem;
        gap: .55rem;
        border-radius: .9rem;
        box-shadow: 0 10px 30px rgba(15, 23, 42, .16);
        background: rgba(255, 251, 235, .97);
        backdrop-filter: blur(8px);
    }

    .chat-composer-shell--restricted .chat-session-closed > i { display: none; }
    .chat-composer-shell--restricted .chat-session-closed > div { display: flex; align-items: center; gap: .55rem; width: 100%; }
    .chat-composer-shell--restricted .chat-session-closed strong { font-size: .78rem; white-space: nowrap; }
    .chat-composer-shell--restricted .chat-session-closed p { display: none; }
    .chat-composer-shell--restricted .session-request-trigger { margin: 0 auto 0 0; padding: .5rem .65rem; font-size: .74rem; white-space: nowrap; }
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
.group-pin-navigator{position:sticky;top:5.25rem;z-index:35;display:flex;align-items:stretch;min-height:72px;border:1px solid rgba(16,185,129,.2);border-radius:18px;background:rgba(255,255,255,.96);box-shadow:0 12px 32px rgba(15,23,42,.09);backdrop-filter:blur(14px);overflow:hidden;direction:rtl}
.group-pin-navigator[hidden]{display:none!important}.group-pin-navigator__main{display:flex;align-items:center;gap:12px;flex:1;min-width:0;padding:11px 14px;border:0;background:transparent;text-align:right;cursor:pointer}.group-pin-navigator__icon{display:grid;place-items:center;width:38px;height:38px;border-radius:12px;color:#047857;background:#d1fae5;transform:rotate(-18deg)}
.group-pin-navigator__copy{display:flex;flex-direction:column;gap:4px;min-width:0}.group-pin-navigator__eyebrow{font-size:12px;color:#059669}.group-pin-navigator__eyebrow b{margin-right:6px;padding:2px 7px;border-radius:999px;background:#ecfdf5;color:#047857}.group-pin-navigator__preview{max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:14px;font-weight:700;color:#1e293b}.group-pin-navigator__controls{display:grid;grid-template-columns:38px 38px;align-content:center;gap:3px;padding:8px;border-right:1px solid #e2e8f0}.group-pin-navigator__controls button{display:grid;place-items:center;width:36px;height:25px;border:0;border-radius:8px;background:#f8fafc;color:#475569;cursor:pointer}.group-pin-navigator__controls button:hover{background:#d1fae5;color:#047857}.group-pin-navigator__count{grid-row:1/3;grid-column:2!important;height:53px!important;border-radius:12px!important;background:#059669!important;color:#fff!important;font-weight:800}
.group-pin-modal{position:fixed;inset:0;z-index:10080;display:grid;place-items:center;padding:18px;direction:rtl}.group-pin-modal[hidden]{display:none!important}.group-pin-modal__backdrop{position:absolute;inset:0;background:rgba(15,23,42,.48);backdrop-filter:blur(4px)}.group-pin-modal__panel{position:relative;width:min(560px,100%);max-height:min(680px,85vh);display:flex;flex-direction:column;border-radius:24px;background:#fff;box-shadow:0 28px 80px rgba(15,23,42,.3);overflow:hidden}.group-pin-modal__header{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;padding:20px;border-bottom:1px solid #e2e8f0}.group-pin-modal__header h3{margin:0 0 4px;font-size:18px;color:#0f172a}.group-pin-modal__header p{margin:0;font-size:12px;color:#64748b}.group-pin-modal__header button{display:grid;place-items:center;width:36px;height:36px;border:0;border-radius:11px;background:#f1f5f9;color:#475569;cursor:pointer}.group-pin-modal__list{padding:10px;overflow:auto}.group-pin-modal__item{display:flex;align-items:stretch;gap:6px;margin:5px 0;border:1px solid #e2e8f0;border-radius:15px;overflow:hidden}.group-pin-modal__item.is-active{border-color:#34d399;background:#ecfdf5}.group-pin-modal__jump{display:flex;flex:1;min-width:0;flex-direction:column;align-items:flex-start;gap:5px;padding:12px;border:0;background:transparent;text-align:right;cursor:pointer}.group-pin-modal__jump strong{max-width:100%;font-size:13px;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.group-pin-modal__jump small{color:#94a3b8}.group-pin-modal__type{font-size:11px;font-weight:800;color:#059669}.group-pin-modal__type i{margin-left:6px}.group-pin-modal__remove{width:48px;border:0;border-right:1px solid #e2e8f0;background:#fff7ed;color:#c2410c;cursor:pointer}.group-pin-modal__empty{padding:36px;text-align:center;color:#64748b}.group-pin-highlight{animation:group-pin-pulse 1.8s ease}@keyframes group-pin-pulse{0%,100%{filter:none}25%,70%{filter:drop-shadow(0 0 12px rgba(16,185,129,.75));transform:scale(1.012)}}body.group-pin-modal-open{overflow:hidden}
@media(max-width:767px){.group-pin-navigator{top:4.65rem;min-height:62px;border-radius:14px}.group-pin-navigator__main{padding:8px 10px;gap:8px}.group-pin-navigator__icon{width:32px;height:32px}.group-pin-navigator__preview{font-size:12px}.group-pin-navigator__controls{grid-template-columns:31px 31px;padding:5px}.group-pin-navigator__controls button{width:30px}.group-pin-navigator__count{width:30px!important}.group-pin-modal{padding:10px;align-items:end}.group-pin-modal__panel{max-height:82vh;border-radius:22px 22px 12px 12px}}

/* Chat canvas: balanced first-item spacing and a calm, readable branded surface. */
#chat-box.chat-body {
    padding: clamp(.9rem, 1.6vw, 1.25rem) 1.25rem 1.25rem !important;
    background-color: #f5fbf8 !important;
    background-image:
        linear-gradient(180deg, rgba(248, 252, 250, .58), rgba(244, 251, 249, .48)),
        url('/images/EarthCoopChatBacgrand.png') !important;
    background-position: center center !important;
    background-repeat: no-repeat !important;
    background-size: cover !important;
}

@media (max-width: 767px) {
    #chat-box.chat-body {
        padding: .75rem .65rem 1rem !important;
        background-position: center center !important;
        background-size: cover !important;
    }
}
</style>
