@extends('layouts.admin')

@section('title', 'کنسول استقرار - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'کنسول استقرار محدود')
@section('page-description', 'عملیات کنترل‌شدهٔ استقرار روی cPanel بدون Terminal')

@section('content')
<div class="container-fluid py-3" dir="rtl">
    <div class="alert alert-danger border-0 shadow-sm" role="alert">
        <strong>سطح حساس Production:</strong>
        این صفحه فقط برای عملیات از پیش تعریف‌شدهٔ استقرار است. هیچ ورودی آزاد برای Shell، SQL، Composer یا Artisan وجود ندارد.
    </div>

    @if(session('deployment_console_result'))
        @php($result = session('deployment_console_result'))
        <section class="alert {{ $result['success'] ? 'alert-success' : 'alert-danger' }} shadow-sm" aria-live="polite">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                <strong>نتیجهٔ {{ $result['operation'] }}</strong>
                <span>Exit code: {{ $result['exit_code'] }}</span>
            </div>
            <pre class="mb-0 p-2 bg-body-tertiary border rounded small text-start" dir="ltr" style="white-space: pre-wrap;">{{ $result['output'] !== '' ? $result['output'] : 'No output.' }}</pre>
        </section>
    @endif

    <section class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center gap-2">
            <h2 class="h6 mb-0">وضعیت بستهٔ وابستگی‌های Production</h2>
            <span class="badge {{ ($vendorPackageStatus['zip_supported'] ?? false) ? 'text-bg-success' : 'text-bg-danger' }}">
                ZIP {{ ($vendorPackageStatus['zip_supported'] ?? false) ? 'READY' : 'UNAVAILABLE' }}
            </span>
        </div>
        <div class="card-body">
            @php($pending = $vendorPackageStatus['pending'] ?? ['available' => false])
            @php($installed = $vendorPackageStatus['installed'] ?? ['available' => false])
            <div class="row g-3">
                <div class="col-12 col-xl-6">
                    <div class="border rounded p-3 h-100">
                        <div class="d-flex justify-content-between mb-2">
                            <strong>بستهٔ آمادهٔ نصب</strong>
                            <span class="badge {{ ($pending['available'] ?? false) ? 'text-bg-warning' : 'text-bg-secondary' }}">
                                {{ ($pending['available'] ?? false) ? 'Pending' : 'None' }}
                            </span>
                        </div>
                        @if($pending['available'] ?? false)
                            <dl class="row small mb-0" dir="ltr">
                                <dt class="col-4">Source SHA</dt><dd class="col-8 text-break"><code>{{ $pending['source_git_sha'] ?? '-' }}</code></dd>
                                <dt class="col-4">Lock SHA-256</dt><dd class="col-8 text-break"><code>{{ $pending['composer_lock_sha256'] ?? '-' }}</code></dd>
                                <dt class="col-4">Package SHA-256</dt><dd class="col-8 text-break"><code>{{ $pending['package_sha256'] ?? '-' }}</code></dd>
                                <dt class="col-4">Created</dt><dd class="col-8"><code>{{ $pending['created_at'] ?? '-' }}</code></dd>
                            </dl>
                            <div class="mt-2 small">
                                تطبیق با composer.lock فعلی:
                                <strong>{{ ($vendorPackageStatus['current_lock_matches_pending'] ?? false) ? 'بله' : 'خیر' }}</strong>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="col-12 col-xl-6">
                    <div class="border rounded p-3 h-100">
                        <div class="d-flex justify-content-between mb-2">
                            <strong>آخرین بستهٔ نصب‌شده</strong>
                            <span class="badge {{ ($installed['available'] ?? false) ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ ($installed['available'] ?? false) ? 'Installed' : 'Unknown' }}
                            </span>
                        </div>
                        @if($installed['available'] ?? false)
                            <dl class="row small mb-0" dir="ltr">
                                <dt class="col-4">Source SHA</dt><dd class="col-8 text-break"><code>{{ $installed['source_git_sha'] ?? '-' }}</code></dd>
                                <dt class="col-4">Lock SHA-256</dt><dd class="col-8 text-break"><code>{{ $installed['composer_lock_sha256'] ?? '-' }}</code></dd>
                                <dt class="col-4">Installed</dt><dd class="col-8"><code>{{ $installed['installed_at'] ?? '-' }}</code></dd>
                            </dl>
                            <div class="mt-2 small">
                                تطبیق با composer.lock فعلی:
                                <strong>{{ ($vendorPackageStatus['current_lock_matches_installed'] ?? false) ? 'بله' : 'خیر' }}</strong>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <p class="small text-muted mb-0 mt-3">هیچ مسیر فایل یا secret در این صفحه نمایش داده نمی‌شود. نصب فقط از بستهٔ canonical و با تأیید ثابت انجام می‌شود.</p>
        </div>
    </section>

    <section class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent"><h2 class="h6 mb-0">وضعیت فعلی rollout flagها — فقط خواندنی</h2></div>
        <div class="card-body">
            <div class="row g-2">
                @foreach($flags as $flag => $enabled)
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="border rounded p-2 d-flex justify-content-between gap-2">
                            <code>{{ $flag }}</code>
                            <span class="badge {{ $enabled ? 'text-bg-warning' : 'text-bg-success' }}">{{ $enabled ? 'ON' : 'OFF' }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="small text-muted mb-0 mt-3">این صفحه هیچ کنترل نوشتنی برای تغییر flagها ندارد.</p>
        </div>
    </section>

    <div class="row g-3">
        @foreach($operations as $key => $operation)
            <div class="col-12 col-lg-6">
                <section class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div>
                                <h2 class="h6 mb-1">{{ $key }}</h2>
                                <div class="small text-muted">{{ $operation['write'] ? 'عملیات نوشتنی' : 'فقط‌خواندنی' }}</div>
                            </div>
                            <span class="badge {{ $operation['write'] ? 'text-bg-warning' : 'text-bg-light' }}">
                                {{ $operation['write'] ? 'نیازمند تأیید' : 'Read only' }}
                            </span>
                        </div>

                        <form method="POST" action="{{ route('admin.deployment-console.run', ['operation' => $key]) }}" autocomplete="off">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label" for="deployment-secret-{{ $key }}">کلید موقت استقرار</label>
                                <input id="deployment-secret-{{ $key }}" class="form-control" type="password" name="deployment_secret" required autocomplete="new-password">
                            </div>

                            @if($operation['confirmation'])
                                <div class="mb-3">
                                    <label class="form-label" for="confirmation-{{ $key }}">برای تأیید دقیقاً بنویسید: <code>{{ $operation['confirmation'] }}</code></label>
                                    <input id="confirmation-{{ $key }}" class="form-control" type="text" name="confirmation" required autocomplete="off">
                                </div>
                            @endif

                            <button class="btn {{ $operation['write'] ? 'btn-outline-warning' : 'btn-outline-primary' }}" type="submit">
                                {{ $operation['write'] ? 'اجرای عملیات تأییدشده' : 'اجرا' }}
                            </button>
                        </form>
                    </div>
                </section>
            </div>
        @endforeach
    </div>

    <div class="alert alert-secondary mt-4 mb-0 small">
        این کنسول هیچ ورودی آزاد برای Shell، SQL، Composer یا Artisan ندارد و هیچ دکمه‌ای برای روشن‌کردن rollout flagها ارائه نمی‌کند.
    </div>
</div>
@endsection
