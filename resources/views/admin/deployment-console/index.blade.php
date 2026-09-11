@extends('layouts.admin')

@section('title', 'کنسول استقرار - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'کنسول استقرار محدود')
@section('page-description', 'آماده‌سازی کنترل‌شدهٔ Location/Governance روی cPanel بدون Terminal')

@section('content')
<div class="container-fluid py-3" dir="rtl">
    <div class="alert alert-danger border-0 shadow-sm" role="alert">
        <strong>سطح موقت Production:</strong>
        این صفحه فقط برای استقرار کنترل‌شده است. پیش از هر عملیات نوشتنی از دیتابیس فعلی در cPanel/phpMyAdmin خروجی کامل بگیرید. فعال‌سازی Location/Governance از این صفحه ممکن نیست.
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
        این کنسول هیچ ورودی آزاد برای Shell، SQL یا Artisan ندارد و هیچ دکمه‌ای برای روشن‌کردن rollout flagها ارائه نمی‌کند.
    </div>
</div>
@endsection
