{{-- Widget چت نجم‌هدا --}}
<div id="najm-hoda-widget" class="najm-hoda-widget" style="display: block;">
    {{-- دکمه باز/بسته کردن --}}
    <button id="najm-hoda-toggle" class="najm-hoda-toggle-btn" title="چت با نجم‌هدا">
        <i class="fas fa-robot"></i>
        <span class="najm-hoda-notification-badge" id="najm-hoda-badge" style="display: none;">0</span>
    </button>

    {{-- کانتینر چت - به صورت پیش‌فرض بسته است --}}
    <div id="najm-hoda-chat-container" class="najm-hoda-chat-container" style="display: none !important;">
        {{-- هدر --}}
        <div class="najm-hoda-header">
            <div class="d-flex align-items-center justify-content-between" style="margin-bottom: 12px;">
                <button id="najm-hoda-close" class="btn-close btn-close-white ms-2" title="بستن" style="flex-shrink: 0;"></button>
                <div class="d-flex align-items-center flex-grow-1" style="min-width: 0;">
                    <div class="najm-hoda-avatar" style="flex-shrink: 0;">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="ms-3" style="min-width: 0; overflow: hidden;">
                        <h6 class="mb-1" style="font-size: 16px; font-weight: 700; letter-spacing: -0.3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            نجم‌هدا 🌟
                        </h6>
                        <small class="text-white-50" style="font-size: 12px; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            دستیار هوشمند ارثکوپ
                        </small>
                    </div>
                </div>
            </div>

            {{-- انتخاب عامل --}}
            <div class="najm-hoda-agent-selector">
                @php
                    $user = auth()->user();
                    $isAdmin = $user && ($user->is_admin || $user->hasRole('super-admin'));
                @endphp
                <select id="najm-hoda-agent" class="form-select form-select-sm" style="width: 100%; border: 1px solid rgba(255,255,255,0.3); background-color: rgba(255,255,255,0.1); color: white; border-radius: 10px; padding: 8px 12px; font-size: 13px; font-weight: 500;">
                    @if ($isAdmin)
                        <option value="auto" style="background: #f8f9fa; color: #333;">🤖 تشخیص خودکار</option>
                        <option value="engineer" style="background: #f8f9fa; color: #333;">🔧 مهندس</option>
                        <option value="pilot" style="background: #f8f9fa; color: #333;">✈️ خلبان</option>
                        <option value="steward" style="background: #f8f9fa; color: #333;">👨‍✈️ مهماندار</option>
                        <option value="guide" style="background: #f8f9fa; color: #333;">📖 راهنما</option>
                    @else
                        <option value="steward" style="background: #f8f9fa; color: #333;">👨‍✈️ مهماندار</option>
                    @endif
                </select>
            </div>
        </div>

        {{-- بدنه پیام‌ها --}}
        <div id="najm-hoda-messages" class="najm-hoda-messages">
            {{-- پیام خوش‌آمدگویی --}}
            <div class="najm-hoda-message assistant">
                <div class="najm-hoda-message-avatar">🌟</div>
                <div class="najm-hoda-message-content">
                    @php
                        $user = auth()->user();
                        $isAdmin = $user && ($user->is_admin || $user->hasRole('super-admin'));
                    @endphp
                    @if ($isAdmin)
                        <strong style="font-size: 15px; color: #37c4b4; display: block; margin-bottom: 8px;">سلام! من نجم‌هدا هستم</strong>
                        <p style="margin-bottom: 10px; font-size: 13px;">نرم‌افزار جامع مدیریت هوشمند دنیای ارثکوپ</p>
                        <p style="margin-bottom: 8px; font-size: 13px; font-weight: 500;">تیم متخصص من:</p>
                        <ul style="margin: 0; padding-right: 18px; font-size: 12px; line-height: 1.7;">
                            <li style="margin-bottom: 5px;"><strong>🔧 مهندس</strong>: طراحی و کدنویسی</li>
                            <li style="margin-bottom: 5px;"><strong>✈️ خلبان</strong>: مدیریت پروژه</li>
                            <li style="margin-bottom: 5px;"><strong>👨‍✈️ مهماندار</strong>: پشتیبانی</li>
                            <li><strong>📖 راهنما</strong>: استراتژی</li>
                        </ul>
                        <p style="margin-top: 12px; margin-bottom: 0; font-size: 13px; font-weight: 500; color: #37c4b4;">
                            چطور می‌تونم کمکتون کنم؟
                        </p>
                    @else
                        <strong style="font-size: 15px; color: #37c4b4; display: block; margin-bottom: 8px;">سلام! من مهماندار نجم‌هدا هستم</strong>
                        <p style="margin-bottom: 10px; font-size: 13px;">برای راهنمایی سریع و پشتیبانی اینجا هستم.</p>
                        <p style="margin-bottom: 8px; font-size: 13px; font-weight: 500;">من می‌تونم:</p>
                        <ul style="margin: 0; padding-right: 18px; font-size: 12px; line-height: 1.7;">
                            <li style="margin-bottom: 5px;"><strong>✅ راهنمایی کاربردی</strong> برای استفاده از سامانه</li>
                            <li style="margin-bottom: 5px;"><strong>🧭 مسیر‌یابی</strong> در بخش‌ها و امکانات</li>
                            <li style="margin-bottom: 5px;"><strong>🛠️ رفع مشکل</strong> و ثبت پیگیری</li>
                            <li><strong>📌 معرفی امکانات</strong> مرتبط با نیاز شما</li>
                        </ul>
                        <p style="margin-top: 12px; margin-bottom: 0; font-size: 13px; font-weight: 500; color: #37c4b4;">
                            لطفا سوال یا مشکلتون رو بنویسید تا راهنمایی کنم.
                        </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- اندیکاتور در حال تایپ --}}
        <div id="najm-hoda-typing" class="najm-hoda-typing d-none">
            <div class="najm-hoda-message assistant">
                <div class="najm-hoda-message-avatar">🤖</div>
                <div class="najm-hoda-typing-indicator">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>

        {{-- فوتر (ورودی) --}}
        <div class="najm-hoda-footer">
            <div class="input-group" style="gap: 8px;">
                <input type="text" id="najm-hoda-input" class="form-control" placeholder="پیام خود را بنویسید..." autocomplete="off" style="border: 1.5px solid #dfe8f0; border-radius: 14px; padding: 11px 16px; font-size: 14px; transition: all 0.25s ease; background: #fafbfc;">
                <button id="najm-hoda-send" class="btn btn-primary" type="button" style="border-radius: 14px; padding: 11px 18px; font-weight: 600; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); border: none; background: linear-gradient(135deg, #37c4b4 0%, #459f96 100%); box-shadow: 0 2px 8px rgba(55, 196, 180, 0.25);">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
            <div class="najm-hoda-hints" id="najm-hoda-hints"></div>
        </div>
    </div>
</div>

<style>
/* استایل‌های نجم‌هدا */
.najm-hoda-widget {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
    font-family: IRANSans, Tahoma, Arial;
}

.najm-hoda-toggle-btn {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #37c4b4 0%, #459f96 100%);
    border: none;
    color: white;
    font-size: 28px;
    box-shadow: 0 6px 16px rgba(55, 196, 180, 0.5);
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.najm-hoda-toggle-btn:hover {
    transform: scale(1.12) translateY(-3px);
    box-shadow: 0 8px 24px rgba(55, 196, 180, 0.7);
}

.najm-hoda-toggle-btn:active {
    transform: scale(0.95);
}

.najm-hoda-notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ff4444;
    color: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
}

.najm-hoda-chat-container {
    position: absolute;
    bottom: 80px;
    right: 0;
    width: 420px;
    max-width: calc(100vw - 40px);
    height: 620px;
    max-height: calc(100vh - 120px);
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12);
    display: none;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid rgba(55, 196, 180, 0.1);
}

.najm-hoda-chat-container:not(.d-none) {
    display: flex !important;
}

.najm-hoda-header {
    background: linear-gradient(135deg, #37c4b4 0%, #459f96 100%);
    color: white;
    padding: 22px 18px;
    border-radius: 20px 20px 0 0;
    box-shadow: 0 4px 12px rgba(55, 196, 180, 0.2);
}

.najm-hoda-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.12);
    flex-shrink: 0;
}

.najm-hoda-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    background: linear-gradient(180deg, #f8f9fa 0%, #f5f8fa 100%);
    direction: rtl;
    display: flex;
    flex-direction: column;
}

.najm-hoda-message {
    display: flex;
    margin-bottom: 18px;
    animation: fadeInUp 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.najm-hoda-message.user {
    justify-content: flex-end;
}

.najm-hoda-message.user .najm-hoda-message-content {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: white;
    border-radius: 20px 6px 20px 20px;
    max-width: 75%;
    box-shadow: 0 3px 12px rgba(0, 123, 255, 0.35);
    line-height: 1.5;
}

.najm-hoda-message.assistant .najm-hoda-message-content {
    background: white;
    border-radius: 20px 20px 6px 20px;
    max-width: 85%;
    border: 1px solid #e3ecf5;
    box-shadow: 0 3px 12px rgba(55, 196, 180, 0.12);
    line-height: 1.5;
    color: #2c3e50;
}

.najm-hoda-message-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
    background: linear-gradient(135deg, #f0f8ff 0%, #e8f4f9 100%);
}

.najm-hoda-message.user .najm-hoda-message-avatar {
    order: 2;
    margin-left: 8px;
}

.najm-hoda-message.assistant .najm-hoda-message-avatar {
    margin-right: 8px;
}

.najm-hoda-message-content {
    padding: 14px 18px;
    border-radius: 18px;
    font-size: 14px;
    word-break: break-word;
}

.najm-hoda-message-content p:last-child {
    margin-bottom: 0;
}

.najm-hoda-typing {
    padding: 0 20px;
    display: flex;
}

.najm-hoda-typing-indicator {
    background: white;
    padding: 14px 18px;
    border-radius: 20px 20px 6px 20px;
    display: inline-flex;
    gap: 6px;
    border: 1px solid #e3ecf5;
    box-shadow: 0 3px 12px rgba(55, 196, 180, 0.12);
    align-items: center;
}

.najm-hoda-typing-indicator span {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #37c4b4;
    animation: typing 1.4s infinite;
}

.najm-hoda-typing-indicator span:nth-child(2) {
    animation-delay: 0.2s;
}

.najm-hoda-typing-indicator span:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes typing {
    0%, 60%, 100% {
        transform: translateY(0);
        opacity: 0.7;
    }
    30% {
        transform: translateY(-10px);
        opacity: 1;
    }
}

.najm-hoda-footer {
    padding: 18px;
    background: white;
    border-top: 1px solid #e3ecf5;
    box-shadow: 0 -3px 12px rgba(0, 0, 0, 0.055);
}

.najm-hoda-hints {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
    margin-top: 12px;
}

.najm-hoda-hint {
    font-size: 12px;
    padding: 7px 14px;
    background: linear-gradient(135deg, #f5fafb 0%, #ecf4f7 100%);
    border-radius: 16px;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid #d8e6ed;
    color: #37c4b4;
    font-weight: 600;
    display: inline-block;
    white-space: nowrap;
}

.najm-hoda-hint:hover {
    background: linear-gradient(135deg, #37c4b4 0%, #459f96 100%);
    color: white;
    border-color: #37c4b4;
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(55, 196, 180, 0.35);
}

.najm-hoda-hint:active {
    transform: translateY(-1px);
}

/* Responsive */
@media (max-width: 768px) {
    .najm-hoda-widget {
        bottom: 15px;
        right: 15px;
    }
    .najm-hoda-toggle-btn {
        width: 56px;
        height: 56px;
        font-size: 26px;
    }
    .najm-hoda-chat-container {
        width: calc(100vw - 30px) !important;
        height: calc(100vh - 100px) !important;
        bottom: 70px;
        right: 15px;
        border-radius: 16px;
        max-height: calc(100vh - 100px);
    }
    .najm-hoda-header {
        padding: 16px 14px;
        border-radius: 16px 16px 0 0;
    }
    .najm-hoda-avatar {
        width: 40px;
        height: 40px;
        font-size: 20px;
    }
    .najm-hoda-header h6 {
        font-size: 15px !important;
    }
    .najm-hoda-header small {
        font-size: 11px !important;
    }
    .najm-hoda-messages {
        padding: 16px;
    }
    .najm-hoda-message {
        margin-bottom: 14px;
    }
    .najm-hoda-message.user .najm-hoda-message-content {
        max-width: 85%;
        border-radius: 18px 4px 18px 18px;
        font-size: 13px;
        padding: 12px 14px;
    }
    .najm-hoda-message.assistant .najm-hoda-message-content {
        max-width: 90%;
        border-radius: 18px 18px 4px 18px;
        font-size: 13px;
        padding: 12px 14px;
    }
    .najm-hoda-message-content {
        padding: 12px 14px;
        font-size: 13px;
    }
    .najm-hoda-message-avatar {
        width: 30px;
        height: 30px;
        font-size: 16px;
    }
    .najm-hoda-footer {
        padding: 14px;
    }
    .najm-hoda-footer .form-control {
        padding: 10px 14px !important;
        font-size: 13px !important;
    }
    .najm-hoda-footer .btn-primary {
        padding: 10px 14px !important;
        min-width: 40px !important;
    }
    .najm-hoda-hint {
        font-size: 11px;
        padding: 6px 10px;
    }
    .najm-hoda-hints {
        gap: 6px;
        margin-top: 10px;
    }
    .najm-hoda-typing-indicator {
        padding: 12px 14px;
    }
    .najm-hoda-agent-selector select {
        font-size: 12px !important;
        padding: 7px 10px !important;
    }
    .najm-hoda-message-content strong {
        font-size: 14px !important;
    }
    .najm-hoda-message-content p {
        font-size: 12px !important;
        margin-bottom: 8px !important;
    }
    .najm-hoda-message-content ul {
        font-size: 11px !important;
        padding-right: 14px !important;
    }
    .najm-hoda-message-content li {
        margin-bottom: 3px !important;
    }
    .najm-hoda-notification-badge {
        width: 22px;
        height: 22px;
        font-size: 10px;
    }
    .ms-3 {
        margin-left: 10px !important;
    }
    .ms-2 {
        margin-left: 6px !important;
    }
}

@media (max-width: 480px) {
    .najm-hoda-widget {
        bottom: 10px;
        right: 10px;
    }
    .najm-hoda-toggle-btn {
        width: 52px;
        height: 52px;
        font-size: 24px;
    }
    .najm-hoda-chat-container {
        width: calc(100vw - 20px) !important;
        height: calc(100vh - 80px) !important;
        bottom: 60px;
        right: 10px;
        border-radius: 14px;
    }
    .najm-hoda-header {
        padding: 14px 12px;
        border-radius: 14px 14px 0 0;
    }
    .najm-hoda-avatar {
        width: 38px;
        height: 38px;
        font-size: 18px;
    }
    .najm-hoda-header h6 {
        font-size: 14px !important;
        margin-bottom: 2px !important;
    }
    .najm-hoda-header small {
        font-size: 10px !important;
    }
    .najm-hoda-messages {
        padding: 14px;
    }
    .najm-hoda-message {
        margin-bottom: 12px;
    }
    .najm-hoda-message.user .najm-hoda-message-content,
    .najm-hoda-message.assistant .najm-hoda-message-content {
        max-width: 95%;
        font-size: 12px;
        padding: 10px 12px;
    }
    .najm-hoda-message-avatar {
        width: 28px;
        height: 28px;
        font-size: 14px;
    }
    .najm-hoda-footer {
        padding: 12px;
    }
    .najm-hoda-footer .input-group {
        gap: 6px !important;
    }
    .najm-hoda-footer .form-control {
        padding: 9px 12px !important;
        font-size: 12px !important;
        border-radius: 12px !important;
    }
    .najm-hoda-footer .btn-primary {
        padding: 9px 12px !important;
        border-radius: 12px !important;
        min-width: 38px !important;
    }
    .najm-hoda-hint {
        font-size: 10px;
        padding: 5px 9px;
        border-radius: 12px;
    }
    .najm-hoda-hints {
        gap: 5px;
        margin-top: 8px;
    }
    .najm-hoda-agent-selector select {
        font-size: 11px !important;
        padding: 6px 9px !important;
        border-radius: 8px !important;
    }
    .najm-hoda-typing-indicator {
        padding: 10px 12px;
        border-radius: 16px 16px 4px 16px;
    }
    .najm-hoda-typing-indicator span {
        width: 7px;
        height: 7px;
    }
    .najm-hoda-message-content {
        padding: 10px 12px;
        font-size: 12px;
    }
    .najm-hoda-message-content strong {
        font-size: 13px !important;
    }
    .najm-hoda-message-content p {
        font-size: 11px !important;
        margin-bottom: 6px !important;
    }
    .najm-hoda-message-content ul {
        font-size: 10px !important;
        padding-right: 12px !important;
        line-height: 1.6 !important;
    }
    .najm-hoda-message-content li {
        margin-bottom: 3px !important;
    }
    .najm-hoda-notification-badge {
        width: 20px;
        height: 20px;
        font-size: 9px;
        top: -3px;
        right: -3px;
    }
    .ms-3 {
        margin-left: 10px !important;
    }
    .ms-2 {
        margin-left: 6px !important;
    }
}

/* Landscape Mode */
@media (max-height: 600px) and (orientation: landscape) {
    .najm-hoda-chat-container {
        height: calc(100vh - 60px) !important;
    }
    .najm-hoda-messages {
        padding: 12px;
    }
    .najm-hoda-message {
        margin-bottom: 10px;
    }
    .najm-hoda-header {
        padding: 12px 14px;
    }
    .najm-hoda-footer {
        padding: 10px 12px;
    }
    .najm-hoda-message-content {
        padding: 10px 12px;
        font-size: 12px;
    }
}

/* Tablet */
@media (min-width: 769px) and (max-width: 1024px) {
    .najm-hoda-chat-container {
        width: 380px;
        height: 600px;
    }
}

/* Scrollbar */
.najm-hoda-messages::-webkit-scrollbar {
    width: 6px;
}
.najm-hoda-messages::-webkit-scrollbar-track {
    background: transparent;
}
.najm-hoda-messages::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, #37c4b4 0%, #459f96 100%);
    border-radius: 3px;
    transition: all 0.25s ease;
}
.najm-hoda-messages::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(180deg, #2ba89a 0%, #3d8a86 100%);
    width: 8px;
}

/* Input & Button Styles */
.najm-hoda-footer .form-control {
    border: 1.5px solid #dfe8f0 !important;
    border-radius: 14px !important;
    padding: 11px 16px !important;
    font-size: 14px !important;
    transition: all 0.25s ease !important;
    background: #fafbfc !important;
    color: #2c3e50 !important;
}
.najm-hoda-footer .form-control:focus {
    border-color: #37c4b4 !important;
    background: white !important;
    box-shadow: 0 0 0 3px rgba(55, 196, 180, 0.1) !important;
    outline: none !important;
}
.najm-hoda-footer .form-control::placeholder {
    color: #a0adb5 !important;
}
.najm-hoda-footer .btn-primary {
    background: linear-gradient(135deg, #37c4b4 0%, #459f96 100%) !important;
    border: none !important;
    border-radius: 14px !important;
    padding: 11px 18px !important;
    font-weight: 600 !important;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
    box-shadow: 0 2px 8px rgba(55, 196, 180, 0.25) !important;
    color: white !important;
    min-width: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.najm-hoda-footer .btn-primary:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 16px rgba(55, 196, 180, 0.35) !important;
}
.najm-hoda-footer .btn-primary:active {
    transform: translateY(0) !important;
    box-shadow: 0 2px 8px rgba(55, 196, 180, 0.25) !important;
}

/* Close Button */
.najm-hoda-header .btn-close {
    opacity: 0.8;
    transition: all 0.25s ease;
}
.najm-hoda-header .btn-close:hover {
    opacity: 1;
    transform: scale(1.15);
}

/* Agent Selector Style */
.najm-hoda-agent-selector {
    width: 100%;
}
.najm-hoda-agent-selector select {
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
    background-color: rgba(255, 255, 255, 0.1) !important;
    color: white !important;
    border-radius: 10px !important;
    padding: 8px 12px !important;
    font-size: 13px !important;
    font-weight: 500 !important;
    transition: all 0.25s ease !important;
    cursor: pointer;
    width: 100%;
}
.najm-hoda-agent-selector select:hover {
    background-color: rgba(255, 255, 255, 0.15) !important;
    border-color: rgba(255, 255, 255, 0.5) !important;
}
.najm-hoda-agent-selector select:focus {
    background-color: rgba(255, 255, 255, 0.2) !important;
    border-color: rgba(255, 255, 255, 0.6) !important;
}

/* Input Group */
.najm-hoda-footer .input-group {
    display: flex;
    gap: 8px;
    align-items: center;
    width: 100%;
}
.najm-hoda-footer .form-control {
    flex: 1;
    min-width: 0;
}

@media (max-width: 768px) {
    .najm-hoda-widget {
        bottom: 78px !important;
        right: 14px !important;
    }
}

@media (max-width: 480px) {
    .najm-hoda-widget {
        bottom: 86px !important;
        right: 12px !important;
    }
}
</style>

<script>
// اسکریپت نجم‌هدا
(function() {
    'use strict';

    const NajmHoda = {
        conversationId: null,
        isTyping: false,

        init() {
            this.showWidget();
            this.ensureChatClosed();
            this.bindEvents();
            this.loadWelcome();
        },

        showWidget() {
            const widget = document.getElementById('najm-hoda-widget');
            if (widget) {
                widget.style.display = 'block';
            }
        },

        ensureChatClosed() {
            const container = document.getElementById('najm-hoda-chat-container');
            if (container) {
                container.style.display = 'none';
                container.classList.add('d-none');
            }
        },

        bindEvents() {
            const toggleBtn = document.getElementById('najm-hoda-toggle');
            const closeBtn = document.getElementById('najm-hoda-close');
            const sendBtn = document.getElementById('najm-hoda-send');
            const input = document.getElementById('najm-hoda-input');

            if (toggleBtn) {
                toggleBtn.addEventListener('click', () => this.toggleChat());
            }
            if (closeBtn) {
                closeBtn.addEventListener('click', () => this.closeChat());
            }
            if (sendBtn) {
                sendBtn.addEventListener('click', () => this.sendMessage());
            }
            if (input) {
                input.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') this.sendMessage();
                });
            }
        },

        toggleChat() {
            const container = document.getElementById('najm-hoda-chat-container');
            if (!container) return;

            const isVisible = container.style.display !== 'none' && !container.classList.contains('d-none');

            if (isVisible) {
                this.closeChat();
            } else {
                container.style.display = 'flex';
                container.classList.remove('d-none');
                const input = document.getElementById('najm-hoda-input');
                if (input) {
                    setTimeout(() => input.focus(), 100);
                }
            }
        },

        closeChat() {
            const container = document.getElementById('najm-hoda-chat-container');
            if (container) {
                container.style.display = 'none';
                container.classList.add('d-none');
            }
        },

        async loadWelcome() {
            try {
                const response = await fetch('/api/najm-hoda/welcome');
                const data = await response.json();
                if (data.stats) {
                    console.log('نجم‌هدا آماده است:', data.stats);
                }
            } catch (error) {
                console.error('خطا در بارگذاری نجم‌هدا:', error);
            }
        },

        async sendMessage() {
            const input = document.getElementById('najm-hoda-input');
            const message = input.value.trim();

            if (!message || this.isTyping) return;

            this.addMessage(message, 'user', '👤');
            input.value = '';

            this.showTyping();

            try {
                const agent = document.getElementById('najm-hoda-agent').value;

                const response = await fetch('/api/najm-hoda/chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Authorization': 'Bearer ' + (localStorage.getItem('api_token') || '')
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        message: message,
                        agent: agent,
                        conversation_id: this.conversationId,
                    })
                });

                const data = await response.json();
                this.hideTyping();

                if (data.success) {
                    this.conversationId = data.conversation_id;
                    this.addMessage(data.message, 'assistant', data.agent_icon || '🤖');
                    if (data.suggestions && data.suggestions.length > 0) {
                        this.showSuggestions(data.suggestions);
                    }
                } else {
                    this.addMessage(data.message || 'خطایی رخ داد', 'assistant', '⚠️');
                }
            } catch (error) {
                this.hideTyping();
                this.addMessage('متأسفانه مشکلی پیش آمد. لطفاً دوباره تلاش کنید.', 'assistant', '❌');
                console.error('خطا در ارسال پیام:', error);
            }
        },

        addMessage(content, role, icon) {
            const messagesDiv = document.getElementById('najm-hoda-messages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `najm-hoda-message ${role}`;

            messageDiv.innerHTML = `
                <div class="najm-hoda-message-avatar">${icon}</div>
                <div class="najm-hoda-message-content">${this.formatMessage(content)}</div>
            `;

            messagesDiv.appendChild(messageDiv);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        },

        formatMessage(content) {
            return content
                .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                .replace(/\*([^*]+)\*/g, '<em>$1</em>')
                .replace(/\n/g, '<br>');
        },

        showTyping() {
            this.isTyping = true;
            document.getElementById('najm-hoda-typing').classList.remove('d-none');
            const messagesDiv = document.getElementById('najm-hoda-messages');
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        },

        hideTyping() {
            this.isTyping = false;
            document.getElementById('najm-hoda-typing').classList.add('d-none');
        },

        showSuggestions(suggestions) {
            const hintsDiv = document.getElementById('najm-hoda-hints');
            hintsDiv.innerHTML = '';

            suggestions.slice(0, 3).forEach(suggestion => {
                const hint = document.createElement('span');
                hint.className = 'najm-hoda-hint';
                hint.textContent = suggestion;
                hint.onclick = () => {
                    document.getElementById('najm-hoda-input').value = suggestion;
                    this.sendMessage();
                };
                hintsDiv.appendChild(hint);
            });
        }
    };

    // شروع خودکار
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => NajmHoda.init());
    } else {
        NajmHoda.init();
    }

    // در دسترس قرار دادن برای استفاده خارجی
    window.NajmHoda = NajmHoda;
})();
</script>