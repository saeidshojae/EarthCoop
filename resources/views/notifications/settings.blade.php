@extends('layouts.unified')

@section('title', 'تنظیمات اعلان‌ها - ' . config('app.name', 'EarthCoop'))
<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
@push('styles')
<style>
    .settings-container {
        direction: rtl;
    }
    
    .settings-card {
        background: var(--color-pure-white);
        border-radius: 1rem;
        padding: 2rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        margin-bottom: 1.5rem;
    }
    
    .settings-card-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-gentle-black);
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-family: 'Vazirmatn', sans-serif;
    }
    
    .setting-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.25rem;
        border-bottom: 1px solid #e2e8f0;
        transition: background-color 0.2s ease;
    }
    
    .setting-item:hover {
        background-color: #f8fafc;
    }
    
    .setting-item:last-child {
        border-bottom: none;
    }
    
    .setting-info {
        flex: 1;
    }
    
    .setting-label {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--color-gentle-black);
        margin-bottom: 0.5rem;
        font-family: 'Vazirmatn', sans-serif;
    }
    
    .setting-description {
        font-size: 0.9rem;
        color: #64748b;
        font-family: 'Vazirmatn', sans-serif;
    }
    
    .setting-control {
        margin-right: 1.5rem;
    }
    
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 34px;
    }
    
    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 34px;
    }
    
    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 26px;
        width: 26px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    
    input:checked + .toggle-slider {
        background-color: var(--color-earth-green);
    }
    
    input:checked + .toggle-slider:before {
        transform: translateX(26px);
    }
    
    @media (max-width: 768px) {
        .setting-item {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .setting-control {
            margin-right: 0;
            margin-top: 1rem;
            width: 100%;
        }
    }
</style>
@endpush

@section('content')
<div class="container mx-auto flex flex-col lg:flex-row gap-8 p-6 md:p-8">
    @include('partials.sidebar-unified')
    
    <main class="flex-grow fade-in-section">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-3xl md:text-4xl font-extrabold text-gentle-black mb-2 font-vazirmatn">
                <i class="fas fa-cog ml-3" style="color: var(--color-earth-green);"></i>
                تنظیمات اعلان‌ها
            </h1>
            <p class="text-slate-600 font-vazirmatn">کنترل کامل بر اعلان‌های دریافتی</p>
        </div>

        @if(session('success'))
            <div class="bg-green-50 border-r-4 border-green-500 text-green-700 px-4 py-3 rounded-lg mb-6" role="alert">
                <div class="flex items-center">
                    <i class="fas fa-check-circle ml-3"></i>
                    <div class="font-vazirmatn">{{ session('success') }}</div>
                </div>
            </div>
        @endif

        <form action="{{ route('notifications.settings.update') }}" method="POST">
            @csrf
            @method('PUT')

            <!-- تنظیمات گروه‌ها -->
            <div class="settings-card">
                <h2 class="settings-card-title">
                    <i class="fas fa-users" style="color: var(--color-earth-green);"></i>
                    اعلان‌های گروه‌ها
                </h2>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">پست جدید در گروه</div>
                        <div class="setting-description">دریافت اعلان هنگام ارسال پست جدید در گروه‌هایی که عضو آن هستید</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="group_post" value="1" {{ $settings->group_post ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">نظرسنجی جدید</div>
                        <div class="setting-description">دریافت اعلان هنگام ایجاد نظرسنجی جدید در گروه</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="group_poll" value="1" {{ $settings->group_poll ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">کامنت جدید روی پست</div>
                        <div class="setting-description">دریافت اعلان هنگام کامنت گذاشتن روی پست‌های شما</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="group_comment_new" value="1" {{ $settings->group_comment_new ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">پاسخ به کامنت</div>
                        <div class="setting-description">دریافت اعلان هنگام پاسخ دادن به کامنت‌های شما</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="group_comment_reply" value="1" {{ $settings->group_comment_reply ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">دعوت به گروه</div>
                        <div class="setting-description">دریافت اعلان هنگام دعوت شدن به یک گروه</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="group_invitation" value="1" {{ $settings->group_invitation ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">گزارش پیام (مدیران و بازرسان)</div>
                        <div class="setting-description">دریافت اعلان هنگام گزارش شدن یک پیام در گروه‌هایی که مدیر یا بازرس هستید</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="group_report_message" value="1" {{ $settings->group_report_message ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">درخواست چت به گروه (مدیران)</div>
                        <div class="setting-description">دریافت اعلان هنگام ارسال درخواست چت برای گروه‌هایی که مدیر آن هستید</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="group_chat_request" value="1" {{ $settings->group_chat_request ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- تنظیمات انتخابات -->
            <div class="settings-card">
                <h2 class="settings-card-title">
                    <i class="fas fa-vote-yea" style="color: var(--color-ocean-blue);"></i>
                    اعلان‌های انتخابات
                </h2>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">شروع انتخابات</div>
                        <div class="setting-description">دریافت اعلان هنگام شروع انتخابات در گروه</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="election_started" value="1" {{ $settings->election_started ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">پایان انتخابات</div>
                        <div class="setting-description">دریافت اعلان هنگام پایان انتخابات</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="election_finished" value="1" {{ $settings->election_finished ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">انتخاب شدن در انتخابات</div>
                        <div class="setting-description">دریافت اعلان هنگام انتخاب شدن شما در انتخابات</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="election_elected" value="1" {{ $settings->election_elected ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">قبول مسئولیت</div>
                        <div class="setting-description">دریافت اعلان هنگام قبول مسئولیت توسط منتخبین</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="election_accepted" value="1" {{ $settings->election_accepted ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">یادآوری شرکت در انتخابات</div>
                        <div class="setting-description">دریافت اعلان یادآوری برای شرکت در انتخابات</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="election_reminder" value="1" {{ $settings->election_reminder ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- تنظیمات چت -->
            <div class="settings-card">
                <h2 class="settings-card-title">
                    <i class="fas fa-comments" style="color: var(--color-digital-gold);"></i>
                    اعلان‌های چت
                </h2>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">پیام جدید</div>
                        <div class="setting-description">دریافت اعلان هنگام ارسال پیام جدید در گروه</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="chat_message" value="1" {{ $settings->chat_message ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">پاسخ به پیام</div>
                        <div class="setting-description">دریافت اعلان هنگام پاسخ دادن به پیام‌های شما</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="chat_reply" value="1" {{ $settings->chat_reply ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">Mention شدن</div>
                        <div class="setting-description">دریافت اعلان هنگام mention شدن در پیام</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="chat_mention" value="1" {{ $settings->chat_mention ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- تنظیمات حراج -->
            <div class="settings-card">
                <h2 class="settings-card-title">
                    <i class="fas fa-gavel" style="color: #8b5cf6;"></i>
                    اعلان‌های حراج
                </h2>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">شروع حراج</div>
                        <div class="setting-description">دریافت اعلان هنگام شروع حراج جدید</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="auction_started" value="1" {{ $settings->auction_started ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">پایان حراج</div>
                        <div class="setting-description">دریافت اعلان هنگام پایان حراجی که در آن شرکت کرده‌اید</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="auction_ended" value="1" {{ $settings->auction_ended ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">برنده شدن در حراج</div>
                        <div class="setting-description">دریافت اعلان هنگام برنده شدن در حراج</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="auction_won" value="1" {{ $settings->auction_won ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">پیشنهاد بالاتر از شما</div>
                        <div class="setting-description">دریافت اعلان هنگام ثبت پیشنهاد بالاتر از پیشنهاد شما در حراج</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="auction_outbid" value="1" {{ $settings->auction_outbid ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">رد شدن پیشنهاد</div>
                        <div class="setting-description">دریافت اعلان هنگام رد شدن پیشنهاد شما در حراج</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="auction_lost" value="1" {{ $settings->auction_lost ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">لغو پیشنهاد</div>
                        <div class="setting-description">دریافت اعلان هنگام لغو پیشنهاد شما</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="auction_cancelled" value="1" {{ $settings->auction_cancelled ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">یادآوری نزدیک شدن به پایان حراج</div>
                        <div class="setting-description">دریافت یادآوری برای حراج‌های در حال پایان</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="auction_reminder" value="1" {{ $settings->auction_reminder ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- تنظیمات کیف پول -->
            <div class="settings-card">
                <h2 class="settings-card-title">
                    <i class="fas fa-wallet" style="color: #10b981;"></i>
                    اعلان‌های کیف پول
                </h2>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">تسویه کیف پول</div>
                        <div class="setting-description">دریافت اعلان هنگام کسر مبلغ از کیف پول برای خرید سهام</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="wallet_settled" value="1" {{ $settings->wallet_settled ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">آزادسازی مبلغ</div>
                        <div class="setting-description">دریافت اعلان هنگام آزاد شدن مبلغ مسدود شده</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="wallet_released" value="1" {{ $settings->wallet_released ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">مسدودسازی مبلغ</div>
                        <div class="setting-description">دریافت اعلان هنگام مسدود شدن مبلغ برای پیشنهاد</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="wallet_held" value="1" {{ $settings->wallet_held ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">شارژ کیف پول توسط ادمین</div>
                        <div class="setting-description">دریافت اعلان هنگام شارژ کیف پول توسط ادمین</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="wallet_credited" value="1" {{ $settings->wallet_credited ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">کسر از کیف پول توسط ادمین</div>
                        <div class="setting-description">دریافت اعلان هنگام کسر مبلغ از کیف پول توسط ادمین</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="wallet_debited" value="1" {{ $settings->wallet_debited ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- تنظیمات سهام -->
            <div class="settings-card">
                <h2 class="settings-card-title">
                    <i class="fas fa-chart-line" style="color: #3b82f6;"></i>
                    اعلان‌های سهام
                </h2>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">دریافت سهام</div>
                        <div class="setting-description">دریافت اعلان هنگام دریافت سهام (از حراج یا هدیه)</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="shares_received" value="1" {{ $settings->shares_received ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">هدیه سهام</div>
                        <div class="setting-description">دریافت اعلان هنگام دریافت سهام به عنوان هدیه</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="shares_gifted" value="1" {{ $settings->shares_gifted ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">تغییر قیمت سهام</div>
                        <div class="setting-description">دریافت اعلان هنگام تغییر قیمت پایه سهام</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="stock_price_changed" value="1" {{ $settings->stock_price_changed ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- تنظیمات نجم بهار -->
            <div class="settings-card">
                <h2 class="settings-card-title">
                    <i class="fas fa-coins" style="color: #f59e0b;"></i>
                    اعلان‌های نجم بهار
                </h2>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">اعلان تراکنش‌ها</div>
                        <div class="setting-description">دریافت اعلان برای تراکنش‌های ورودی و خروجی</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="najm_bahar_transaction" value="1" {{ $settings->najm_bahar_transaction ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">اعلان موجودی کم</div>
                        <div class="setting-description">دریافت اعلان هنگام کم شدن موجودی حساب از آستانه تعیین شده</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="najm_bahar_low_balance" value="1" {{ $settings->najm_bahar_low_balance ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="mt-2">
                        <label class="text-sm text-gray-600">آستانه موجودی کم (بهار):</label>
                        <input type="number" name="najm_bahar_low_balance_threshold" 
                               value="{{ $settings->najm_bahar_low_balance_threshold ?? 1000 }}" 
                               min="0" step="1"
                               class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-earth-green focus:border-transparent">
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">اعلان تراکنش بزرگ</div>
                        <div class="setting-description">دریافت اعلان برای تراکنش‌های بزرگ (بالای آستانه تعیین شده)</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="najm_bahar_large_transaction" value="1" {{ $settings->najm_bahar_large_transaction ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="mt-2">
                        <label class="text-sm text-gray-600">آستانه تراکنش بزرگ (بهار):</label>
                        <input type="number" name="najm_bahar_large_transaction_threshold" 
                               value="{{ $settings->najm_bahar_large_transaction_threshold ?? 100000 }}" 
                               min="0" step="1"
                               class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-earth-green focus:border-transparent">
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">اعلان تراکنش زمان‌بندی شده</div>
                        <div class="setting-description">دریافت اعلان هنگام اجرای تراکنش‌های زمان‌بندی شده</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="najm_bahar_scheduled_transaction" value="1" {{ $settings->najm_bahar_scheduled_transaction ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- تنظیمات سیستم -->
            <div class="settings-card">
                <h2 class="settings-card-title">
                    <i class="fas fa-bell" style="color: #6b7280;"></i>
                    اعلان‌های سیستم
                </h2>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">پیام از ادمین</div>
                        <div class="setting-description">دریافت اعلان هنگام ارسال پیام از طرف ادمین</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="admin_message" value="1" {{ $settings->admin_message ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- تنظیمات عمومی -->
            <div class="settings-card">
                <h2 class="settings-card-title">
                    <i class="fas fa-sliders-h" style="color: var(--color-earth-green);"></i>
                    تنظیمات عمومی
                </h2>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">اعلان‌های ایمیل</div>
                        <div class="setting-description">دریافت اعلان‌ها از طریق ایمیل (علاوه بر اعلان‌های درون‌برنامه)</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="email_notifications" value="1" {{ $settings->email_notifications ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">اعلان‌های Push</div>
                        <div class="setting-description">دریافت اعلان‌ها به صورت real-time</div>
                    </div>
                    <div class="setting-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="push_notifications" value="1" {{ $settings->push_notifications ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- دکمه‌های عملیات -->
            <div class="flex items-center justify-between gap-4">
                <a href="{{ route('notifications.index') }}" 
                   class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-medium font-vazirmatn">
                    <i class="fas fa-arrow-right ml-2"></i>
                    بازگشت
                </a>
                <button type="submit" 
                        class="px-6 py-3 bg-earth-green text-white rounded-lg hover:bg-opacity-90 transition-colors font-medium font-vazirmatn">
                    <i class="fas fa-save ml-2"></i>
                    ذخیره تنظیمات
                </button>
            </div>
        </form>
    </main>
</div>
@endsection

