<section class="card border-0 shadow-sm mb-4" data-structure-claim-queue>
    <div class="card-header bg-transparent">
        <h2 class="h5 mb-1">ادعاهای ساختاری مکان</h2>
        <p class="small text-muted mb-0">حمایت کاربران فقط پرونده را آمادهٔ بررسی می‌کند؛ تأیید یا رد همیشه با تصمیم صریح مدیر انجام می‌شود.</p>
    </div>
    <div class="card-body">
        @forelse($structureClaims as $claim)
            @php
                $locationTypeLabels = [
                    'city' => 'شهر',
                    'urban_region' => 'منطقه',
                    'village' => 'روستا',
                ];
                $locationType = $claim->location?->type?->key;
                $locationContext = $locationTypeLabels[$locationType] ?? null;
                $labels = [
                    'single_urban_region' => 'شهر تک‌منطقه',
                    'no_urban_region' => 'شهر بدون منطقه',
                    'single_neighborhood' => $locationContext ? $locationContext.' تک‌محله' : 'تک‌محله',
                    'no_neighborhood' => $locationContext ? $locationContext.' بدون محله' : 'بدون محله',
                ];
                $statusLabels = [
                    'pending' => 'در انتظار حمایت/بررسی',
                    'ready_for_review' => 'آماده بررسی مدیر',
                    'needs_evidence' => 'نیازمند مدرک بیشتر',
                ];
            @endphp
            <article class="border rounded-3 p-3 mb-3">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <strong>{{ $labels[$claim->claim_type] ?? $claim->claim_type }}</strong>
                        <span class="badge text-bg-light ms-1">{{ $statusLabels[$claim->status] ?? $claim->status }}</span>
                    </div>
                    <div class="small text-muted">
                        حمایت ثبت‌شده: {{ number_format($claim->evidence_count) }} از {{ number_format($structureClaimVerificationThreshold) }}
                        · گروه‌های وابسته در انتظار: {{ number_format($claim->pending_group_requests_count ?? 0) }}
                    </div>
                </div>

                <div class="mt-2 small">
                    <span class="text-muted">مسیر مکان:</span>
                    @foreach($structureClaimPaths[$claim->id] ?? [] as $segment)
                        @if(! $loop->first) <span class="text-muted">/</span> @endif
                        <span>{{ $segment }}</span>
                    @endforeach
                </div>

                <div class="row g-2 mt-2">
                    @foreach([
                        ['route' => 'admin.location-governance.structure-claims.approve', 'label' => 'تأیید'],
                        ['route' => 'admin.location-governance.structure-claims.reject', 'label' => 'رد'],
                        ['route' => 'admin.location-governance.structure-claims.request-evidence', 'label' => 'مدرک بیشتر'],
                    ] as $action)
                        <div class="col-12 col-lg-4">
                            <form method="POST" action="{{ route($action['route'], $claim) }}" class="d-flex flex-column flex-md-row gap-2">
                                @csrf
                                <input name="reason" required minlength="4" maxlength="1000" class="form-control form-control-sm" placeholder="دلیل تصمیم">
                                <button class="btn btn-sm btn-outline-secondary text-nowrap">{{ $action['label'] }}</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </article>
        @empty
            <p class="text-muted mb-0">ادعای ساختاری بازی برای بررسی وجود ندارد.</p>
        @endforelse
    </div>
</section>
