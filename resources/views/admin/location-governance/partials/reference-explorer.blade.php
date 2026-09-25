<section class="card border-0 shadow-sm h-100" data-reference-explorer>
    <div class="card-header bg-transparent py-3">
        <h2 class="h5 mb-1">مرورگر جغرافیای مرجع</h2>
        <p class="small text-muted mb-0">نمونهٔ محدود از مکان‌های canonical فعال برای تشخیص ساختار، والد و نوع؛ این بخش فقط خواندنی است.</p>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>مکان</th><th>نوع</th><th>والد</th><th>کشور</th></tr></thead>
                <tbody>
                    @forelse($referenceLocations as $location)
                        <tr>
                            <td>{{ \App\Support\LocationDisplayName::for($location) }}</td>
                            <td>{{ $location->type?->key ?: $location->level ?: '—' }}</td>
                            <td>{{ $location->parent ? \App\Support\LocationDisplayName::for($location->parent) : '—' }}</td>
                            <td>{{ $location->country_code ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">مکان مرجع فعالی برای نمایش وجود ندارد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
