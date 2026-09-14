<section class="card border-0 shadow-sm h-100" data-import-diagnostics>
    <div class="card-header bg-transparent py-3">
        <h2 class="h5 mb-1">واردسازی و تشخیص</h2>
        <p class="small text-muted mb-0">آخرین اجرای واردسازی جغرافیای مرجع و شمار تغییرات/تعارض‌ها.</p>
    </div>
    <div class="card-body">
        @forelse($importRuns as $run)
            <div class="border-bottom py-2 small">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <strong>{{ $run->country_code }}</strong>
                    <span>{{ $run->source }}</span>
                    <span>{{ $run->dataset_version }}</span>
                    <span class="badge text-bg-light">{{ $run->status }}</span>
                </div>
                <div class="text-muted mt-1">
                    ایجاد {{ $run->creates }} · بروزرسانی {{ $run->updates }} · غیرفعال‌سازی {{ $run->deactivates }} · تعارض {{ $run->conflicts }} · بدون تغییر {{ $run->unchanged }}
                </div>
            </div>
        @empty
            <p class="text-muted mb-0">هنوز اجرای واردسازی ثبت نشده است.</p>
        @endforelse
    </div>
</section>
