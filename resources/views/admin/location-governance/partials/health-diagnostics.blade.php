<section class="card border-0 shadow-sm h-100" data-health-diagnostics>
    <div class="card-header bg-transparent py-3">
        <h2 class="h5 mb-1">سلامت مکان و حکمرانی</h2>
        <p class="small text-muted mb-0">شاخص‌های تشخیصی برای صف بازبینی و یکپارچگی داده؛ این بخش فقط خواندنی است.</p>
    </div>
    <div class="card-body">
        @php
            $labels = [
                'open_proposals' => 'پیشنهادهای باز',
                'above_threshold_proposals' => 'پیشنهادهای رسیده به آستانه بازبینی',
                'pending_residence_intents' => 'قصدهای اقامت در انتظار',
                'invalid_pending_residence_intents' => 'قصدهای اقامت نیازمند بررسی',
                'locations_missing_schema_or_type' => 'مکان‌های فاقد schema/type canonical',
                'official_areas_without_location_mapping' => 'حوزه‌های رسمی فعال بدون نگاشت مکان',
            ];
        @endphp
        <div class="row g-2">
            @foreach($labels as $key => $label)
                <div class="col-12 col-md-6">
                    <div class="border rounded-3 p-3 h-100 d-flex align-items-center justify-content-between gap-3">
                        <span class="small">{{ $label }}</span>
                        <strong>{{ number_format((int) ($healthDiagnostics[$key] ?? 0)) }}</strong>
                    </div>
                </div>
            @endforeach
        </div>
        <p class="small text-muted mt-3 mb-0">عدد غیرصفر الزاماً خطا نیست؛ برای برخی شاخص‌ها به معنی وجود آیتم در صف بازبینی انسانی است.</p>
    </div>
</section>
