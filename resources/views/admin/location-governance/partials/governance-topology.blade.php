<section class="card border-0 shadow-sm h-100" data-governance-topology>
    <div class="card-header bg-transparent py-3">
        <h2 class="h5 mb-1">توپولوژی و نگاشت حکمرانی رسمی</h2>
        <p class="small text-muted mb-0">حوزه‌های رسمی GovernanceArea و نگاشت‌های فعلی آن‌ها؛ این نما مسیر تغییر مستقیم توپولوژی ایجاد نمی‌کند.</p>
    </div>
    <div class="card-body">
        @forelse($officialTopology as $area)
            <div class="border-bottom py-2">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <strong>{{ \App\Support\GovernanceAreaDisplayName::for($area) }}</strong>
                    <span class="badge text-bg-light border">{{ $area->governance_type }}</span>
                </div>
                <div class="small text-muted mt-1">
                    والد: {{ \App\Support\GovernanceAreaDisplayName::for($area->parent) }} · نگاشت مکان: {{ $area->locations->count() }}
                </div>
            </div>
        @empty
            <p class="text-muted mb-0">حوزه رسمی برای نمایش وجود ندارد.</p>
        @endforelse
    </div>
</section>
