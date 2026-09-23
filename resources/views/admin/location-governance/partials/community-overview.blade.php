<section class="card border-0 shadow-sm h-100" data-community-overview>
    <div class="card-header bg-transparent py-3">
        <h2 class="h5 mb-1">نمای Community</h2>
        <p class="small text-muted mb-0">Community اختیاری است و به‌طور پیش‌فرض سطح رسمی حکمرانی یا انتخابات سیستماتیک ایجاد نمی‌کند.</p>
    </div>
    <div class="card-body">
        @forelse($communityAreas as $area)
            <div class="border-bottom py-2">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <strong>{{ \App\Support\GovernanceAreaDisplayName::for($area) }}</strong>
                    <span class="badge text-bg-light border">Community</span>
                </div>
                <div class="small text-muted mt-1">
                    والد رسمی: {{ \App\Support\GovernanceAreaDisplayName::for($area->parent) }} · مکان‌های متصل: {{ $area->locations->count() }}
                </div>
            </div>
        @empty
            <p class="text-muted mb-0">Community فعالی ثبت نشده است.</p>
        @endforelse
    </div>
</section>
