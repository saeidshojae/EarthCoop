<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>مرحله ۳: اطلاعات مکانی - EarthCoop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    @vite(['resources/js/app.js', 'resources/js/registration-location-ux.js'])
    <style>
        body { min-height:100vh; background:linear-gradient(135deg,#f8f9fa 0%,#e9ecef 100%); font-family:Tahoma,Arial,sans-serif; }
        .registration-container { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .registration-card { background:#fff; border-radius:20px; box-shadow:0 10px 30px rgba(0,0,0,.1); overflow:hidden; width:100%; max-width:760px; }
        .form-card-gradient { background:linear-gradient(135deg,#6f42c1 0%,#5a32a3 100%); color:#fff; padding:28px 32px; text-align:center; }
        .logo-container img { width:76px; height:76px; object-fit:contain; margin-bottom:12px; }
        .progress-steps { display:flex; justify-content:center; gap:8px; margin-top:18px; }
        .step { width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,.25); font-weight:700; }
        .step.active { background:#fff; color:#6f42c1; }
        .registration-body { padding:30px 34px 34px; }
        .location-path { background:#f8f5ff; border:1px solid #e3d8ff; border-radius:12px; padding:12px 14px; min-height:50px; display:flex; flex-wrap:wrap; align-items:center; gap:6px; margin-bottom:18px; }
        .location-path .badge { font-size:.82rem; padding:.55em .7em; }
        [data-location-levels] { display:grid; gap:14px; }
        [data-location-levels] .form-select { min-height:46px; border-radius:10px; }
        .location-actions { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:12px; }
        .submit-btn { min-height:50px; border:0; border-radius:12px; background:linear-gradient(135deg,#6f42c1,#8e5cff); color:#fff; font-weight:700; font-size:1.05rem; }
        .submit-btn:disabled { opacity:.55; }
        @media(max-width:640px){ .registration-container{padding:10px}.registration-body{padding:22px 16px 26px}.form-card-gradient{padding:22px 16px}.registration-card{border-radius:16px}.location-actions>*{flex:1 1 140px;min-height:44px} }
    </style>
</head>
<body>
<div class="registration-container">
    <div class="registration-card">
        <header class="form-card-gradient">
            <div class="logo-container">
                <img src="{{ asset('assets/images/logo.png') }}" alt="EarthCoop" onerror="this.style.display='none'">
            </div>
            <h1 class="h4 mb-2">مرحله ۳: اطلاعات مکانی</h1>
            <p class="mb-0 opacity-75">محل سکونت اصلی خود را مشخص کنید</p>
            <div class="progress-steps" aria-label="مراحل ثبت‌نام">
                <div class="step">۱</div><div class="step">۲</div><div class="step active">۳</div>
            </div>
        </header>

        <main class="registration-body">
            <h2 class="h5 mb-2">محل سکونت اصلی</h2>
            <p class="text-secondary small mb-4">ساختار مکانی هر کشور پویاست؛ مسیر را تا دقیق‌ترین محل معتبر خود ادامه دهید. عضویت در حوزه‌های حکمرانی بالادستی از محل سکونت اصلی شما به‌صورت سیستمی تعیین می‌شود.</p>

            @if ($errors->any())
                <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <form method="POST" action="{{ route('register.step3.process') }}" data-location-form>
                @csrf
                <div data-location-selector data-location-selector-context="registration" data-empty-label="یک گزینه را انتخاب کنید" data-loading-label="در حال دریافت گزینه‌های مکانی..." data-error-label="دریافت گزینه‌های مکانی ممکن نشد. دوباره تلاش کنید.">
                    <input type="hidden" name="location_id" value="{{ old('location_id') }}" data-location-id>
                    <input type="hidden" name="location_proposal_id" value="{{ old('location_proposal_id') }}" data-location-proposal-id>

                    <div class="location-actions" data-location-geolocation>
                        <button type="button" class="btn btn-outline-primary" data-location-geolocation-detect>تشخیص خودکار موقعیت من</button>
                        <button type="button" class="btn btn-outline-secondary" data-location-geolocation-manual>انتخاب دستی</button>
                    </div>
                    <p class="small text-secondary mb-3 d-none" data-location-geolocation-status aria-live="polite"></p>

                    <div class="mb-2 fw-semibold small">مسیر انتخابی شما</div>
                    <div class="location-path text-muted" data-location-path aria-live="polite">مسیر انتخاب نشده</div>

                    <div data-location-levels></div>
                    <div class="small text-secondary mt-3" data-location-status aria-live="polite">برای ادامه، یک محل معتبر برای سکونت اصلی انتخاب کنید.</div>
                </div>

                <div class="d-grid mt-4"><button type="submit" class="submit-btn" data-location-submit disabled>ثبت محل سکونت و ادامه</button></div>
            </form>
        </main>
    </div>
</div>
</body>
</html>
