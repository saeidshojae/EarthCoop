<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="format-detection" content="telephone=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>مرحله ۳ - اطلاعات مکانی</title>
    @vite(['resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('Css/fonts-local.css') }}">
    <link rel="stylesheet" href="{{ asset("vendor/fontawesome/css/all.min.css") }}">
    <style>
        :root { --color-earth-green: #10b981; --color-ocean-blue: #3b82f6; --color-digital-gold: #f59e0b; --color-pure-white: #ffffff; --color-gentle-black: #1e293b; --color-dark-green: #047857; --color-dark-blue: #1d4ed8; }
        * { font-family: 'Vazirmatn', 'Poppins', sans-serif; }
        body { background-color: #e2e8f0; }
        @keyframes bounce-custom { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-15px)} }
        .animate-bounce-custom { animation: bounce-custom 3s infinite ease-in-out; }
        .form-card-gradient { background: linear-gradient(145deg, var(--color-pure-white) 0%, #f0f4f7 100%); box-shadow:0 12px 35px rgba(0,0,0,.08); border-radius:18px; position:relative; border:1px solid rgba(220,220,220,.3); overflow:hidden; }
        .form-card-gradient::before { content:''; position:absolute; top:0; left:0; width:100%; height:6px; background: linear-gradient(90deg, var(--color-earth-green), var(--color-ocean-blue), var(--color-digital-gold)); }
        .location-path { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding:.75rem 1rem; border-radius:.75rem; color:white; font-weight:500; margin-bottom:1.5rem; box-shadow:0 4px 15px rgba(102,126,234,.3); font-size:.875rem; line-height:1.6; min-height:3rem; display:flex; flex-wrap:wrap; align-items:center; justify-content:center; gap:.35rem; }
        .location-path .badge,.location-path span { color:white!important; background:rgba(255,255,255,.14)!important; border:0; font-weight:600; padding:.3rem .55rem; border-radius:.35rem; white-space:nowrap; }
        [data-location-levels] { display:grid; gap:.9rem; }
        [data-location-levels] .form-select { min-height:3rem; border:1px solid #d1d5db; border-radius:.5rem; color:var(--color-gentle-black); background-color:white; }
        .location-actions { display:flex; flex-wrap:wrap; gap:.75rem; margin-bottom:1rem; }
        .location-action-btn { min-height:44px; border-radius:.6rem; padding:.65rem 1rem; font-weight:700; transition:transform .2s ease,box-shadow .2s ease; }
        .location-action-btn:hover { transform:translateY(-1px); }
        .location-detect-btn { border:1px solid var(--color-ocean-blue); color:var(--color-dark-blue); background:white; }
        .location-manual-btn { border:1px solid #94a3b8; color:#475569; background:white; }
        .create-location-btn,[data-location-proposal-toggle] { display:inline-flex; align-items:center; justify-content:center; gap:.5rem; white-space:nowrap; border-radius:9999px!important; border:0!important; font-weight:700; font-size:.95rem; padding:.65rem 1.5rem; background:linear-gradient(135deg,var(--color-earth-green),var(--color-dark-green))!important; color:white!important; box-shadow:0 10px 22px rgba(16,185,129,.35); transition:transform .2s ease,box-shadow .2s ease; }
        .create-location-btn:hover,[data-location-proposal-toggle]:hover { transform:translateY(-2px); box-shadow:0 12px 26px rgba(16,185,129,.45); }
        [data-location-proposal-shell] { margin-top:.75rem; }
        .submit-btn { width:100%; min-height:3rem; border:0; border-radius:.65rem; background:linear-gradient(135deg,var(--color-ocean-blue),var(--color-dark-blue)); color:white; font-weight:800; box-shadow:0 8px 18px rgba(59,130,246,.24); }
        .submit-btn:disabled { opacity:.5; cursor:not-allowed; box-shadow:none; }
        @media(max-width:640px){ html,body{margin:0!important;padding:0!important;width:100%!important;min-height:100%!important;overflow-x:hidden!important} body{padding-top:.25rem!important;padding-bottom:.25rem!important;align-items:flex-start!important}.form-card-gradient{padding:.75rem!important;border-radius:12px;margin:.25rem auto!important;width:calc(100% - .5rem)!important;max-width:calc(100% - .5rem)!important}.form-card-gradient::before{border-radius:12px 12px 0 0}.location-path{padding:.625rem .75rem!important;font-size:.75rem!important;margin-bottom:1rem!important;line-height:1.5!important}.create-location-btn{font-size:.8125rem!important;padding:.5rem 1rem!important;min-height:44px}[data-location-proposal-toggle]{font-size:.8125rem!important;padding:.5rem .25rem!important;min-height:40px;background:transparent!important;color:#087f5b!important;box-shadow:none!important}.location-actions>*{flex:1 1 140px;min-height:44px}[data-location-levels] .form-select{min-height:44px;font-size:.875rem} }
    </style>
</head>
<body class="font-vazirmatn leading-relaxed flex items-center justify-center min-h-screen p-0 sm:p-2 md:p-4">
<div class="form-card-gradient w-full max-w-3xl mx-auto p-4 sm:p-6 md:p-8 lg:p-10">
    <div class="flex items-center justify-center space-x-2 sm:space-x-3 rtl:space-x-reverse mb-4 sm:mb-6 md:mb-8">
        <svg width="40" height="40" class="animate-bounce-custom sm:w-12 sm:h-12 md:w-14 md:h-14 lg:w-16 lg:h-16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2Z" fill="#10b981" opacity="0.8"/><path d="M12 2C10.5 4 8 6 8 9C8 12 12 14 12 14C12 14 16 12 16 9C16 6 13.5 4 12 2ZM12 14C12 14 10 16 10 18C10 20 12 22 12 22" fill="#047857"/></svg>
        <span class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-extrabold" style="color:var(--color-gentle-black);">EarthCoop</span>
    </div>
    <div class="text-center mb-4 sm:mb-6 md:mb-8"><div class="flex items-center justify-center flex-wrap gap-2 sm:gap-3 md:gap-4 mb-2 sm:mb-4" aria-label="مراحل ثبت‌نام">
        <div class="flex items-center flex-col sm:flex-row opacity-50"><div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center font-bold text-white bg-gray-400 text-sm sm:text-base"><i class="fas fa-check text-xs sm:text-sm"></i></div><span class="mr-0 sm:mr-2 mt-1 sm:mt-0 text-xs sm:text-sm text-gray-500 hidden sm:inline">هویتی</span></div>
        <div class="w-4 h-1 sm:w-6 md:w-8 bg-gray-300 hidden sm:block"></div>
        <div class="flex items-center flex-col sm:flex-row opacity-50"><div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center font-bold text-white bg-gray-400 text-sm sm:text-base"><i class="fas fa-check text-xs sm:text-sm"></i></div><span class="mr-0 sm:mr-2 mt-1 sm:mt-0 text-xs sm:text-sm text-gray-500 hidden sm:inline">صنفی</span></div>
        <div class="w-4 h-1 sm:w-6 md:w-8 bg-gray-300 hidden sm:block"></div>
        <div class="flex items-center flex-col sm:flex-row"><div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center font-bold text-white text-sm sm:text-base" style="background-color:var(--color-ocean-blue);">۳</div><span class="mr-0 sm:mr-2 mt-1 sm:mt-0 text-xs sm:text-sm font-bold hidden sm:inline" style="color:var(--color-ocean-blue);">مکانی</span></div>
    </div></div>
    <div class="text-right">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-extrabold mb-3 sm:mb-4" style="color:var(--color-gentle-black);">مرحله ۳: اطلاعات مکانی</h2>
        <p class="text-gray-600 mb-2 text-xs sm:text-sm md:text-base">لطفاً محل سکونت اصلی خود را با دقت انتخاب کنید. برای ثبت‌نام، مسیر مکانی را تا سطح محله یا نزدیک‌ترین سطح پایهٔ موجود در ساختار محل سکونت خود تکمیل کنید. عضویت در حوزه‌های حکمرانی بالادستی از محل سکونت اصلی شما به‌صورت سیستمی تعیین می‌شود.</p>
        <p class="text-xs sm:text-sm text-gray-500 mb-4 sm:mb-6"><i class="fas fa-circle-info ml-1" aria-hidden="true"></i>ثبت‌نام در همین سطح پایه پایان می‌یابد و در این مرحله نیازی به وارد کردن خیابان، کوچه، مجتمع یا ساختمان نیست. پس از ورود می‌توانید از بخش «مکان و حکمرانی من» نشانی محلی خود را دقیق‌تر کنید و در صورت وجود، به اجتماعات محلی مربوط بپیوندید.</p>
        @if ($errors->any())<div class="bg-red-100 border border-red-400 text-red-700 px-3 sm:px-4 py-2 sm:py-3 rounded-lg mb-4 sm:mb-6 text-sm sm:text-base" role="alert"><ul class="list-disc list-inside mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if(session('error'))<div class="bg-red-100 border border-red-400 text-red-700 px-3 sm:px-4 py-2 sm:py-3 rounded-lg mb-4 sm:mb-6 text-sm sm:text-base" role="alert">{{ session('error') }}</div>@endif
        <form method="POST" action="{{ route('register.step3.process') }}" data-location-form id="step3Form">@csrf
            @foreach (collect(old('location_structure_claim_ids', []))->map(fn ($id) => (int) $id)->filter()->unique() as $claimId)
                <input type="hidden" name="location_structure_claim_ids[]" value="{{ $claimId }}" data-location-structure-claim-id>
            @endforeach
            <div data-location-selector data-location-selector-context="registration" data-empty-label="یک گزینه را انتخاب کنید" data-loading-label="در حال دریافت گزینه‌های مکانی..." data-error-label="دریافت گزینه‌های مکانی ممکن نشد. دوباره تلاش کنید.">
                <input type="hidden" name="location_id" value="{{ old('location_id') }}" data-location-id><input type="hidden" name="location_proposal_id" value="{{ old('location_proposal_id') }}" data-location-proposal-id><input type="hidden" name="reference_settlement_external_id" value="{{ old('reference_settlement_external_id') }}" data-reference-settlement-external-id>
                <div class="location-actions" data-location-geolocation><button type="button" class="location-action-btn location-detect-btn" data-location-geolocation-detect><i class="fas fa-location-crosshairs ml-1"></i>تشخیص موقعیت من</button><button type="button" class="location-action-btn location-manual-btn" data-location-geolocation-manual><i class="fas fa-list ml-1"></i>انتخاب دستی</button></div>
                <p class="text-xs sm:text-sm text-gray-500 mb-3 hidden" data-location-geolocation-status aria-live="polite"></p>
                <div class="location-path text-center" id="location_path_display" data-location-path aria-live="polite"><i class="fas fa-map-marker-alt ml-2"></i><span>مسیر انتخاب نشده</span></div>
                <div data-location-levels></div><div class="text-xs sm:text-sm text-gray-500 mt-3" data-location-status aria-live="polite">برای ادامه، یک محل معتبر برای سکونت اصلی انتخاب کنید.</div>
            </div>
            @if(config('iran_settlement_catalog.enabled') && config('iran_settlement_catalog.claims_enabled'))
                <section class="vstack gap-3" data-reference-settlement-picker hidden>
                    <h3 class="font-bold text-sm sm:text-base mb-1">جستجو در بانک آبادی‌های ۱۴۰۴</h3>
                    <p class="text-xs sm:text-sm text-gray-500 mb-3">نام آبادی را جستجو و گزینه درست را انتخاب کنید. انتخاب از بانک مرجع به‌معنای تأیید خودکار سکونت یا حکمرانی نیست.</p>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <input type="search" minlength="2" maxlength="60" class="form-control flex-1" placeholder="نام آبادی" data-reference-settlement-query>
                        <button type="button" class="location-action-btn location-manual-btn" data-reference-settlement-search>جست‌وجوی آبادی</button>
                    </div>
                    <div class="mt-3 text-xs sm:text-sm text-gray-500" data-reference-settlement-status aria-live="polite"></div>
                    <div class="mt-2 grid gap-2" data-reference-settlement-results></div>
                </section>
            @endif
            <button type="submit" id="continueBtn" class="submit-btn mt-5" data-location-submit disabled>ثبت محل سکونت و ادامه</button>
        </form>
    </div>
</div>
</body>
</html>
