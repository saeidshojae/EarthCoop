<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تکمیل محل سکونت | EarthCoop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    @vite(['resources/js/app.js'])
</head>
<body class="bg-light">
<div class="container py-4 py-md-5" style="max-width:760px">
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4 p-md-5">
            <div class="mb-4">
                <h1 class="h4 mb-2">محل سکونت اصلی</h1>
                <p class="text-secondary mb-0">مسیر محل سکونت را مرحله‌به‌مرحله انتخاب کنید. سامانه ساختار مکانی هر کشور را پویا نمایش می‌دهد و لازم نیست همهٔ مسیرها عمق یکسانی داشته باشند.</p>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('register.step3.process') }}" data-location-form>
                @csrf
                <div
                    data-location-selector
                    data-location-selector-context="registration"
                    data-country-code="IR"
                    data-empty-label="یک گزینه را انتخاب کنید"
                    data-loading-label="در حال دریافت گزینه‌های مکانی..."
                    data-error-label="دریافت گزینه‌های مکانی ممکن نشد. دوباره تلاش کنید."
                >
                    <input type="hidden" name="location_id" value="{{ old('location_id') }}" data-location-id>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3" data-location-geolocation>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-location-geolocation-detect>تشخیص موقعیت من</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-location-geolocation-manual>انتخاب دستی</button>
                    </div>
                    <p class="small text-secondary mb-3 d-none" data-location-geolocation-status aria-live="polite"></p>
                    <div class="vstack gap-3" data-location-levels></div>
                    <div class="small text-secondary mt-3" data-location-status aria-live="polite">برای ادامه، یک محل معتبر برای سکونت اصلی انتخاب کنید.</div>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg rounded-3" data-location-submit disabled>ثبت محل سکونت و ادامه</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
