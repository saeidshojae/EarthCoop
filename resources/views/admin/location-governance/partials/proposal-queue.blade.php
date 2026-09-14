<section class="card border-0 shadow-sm mb-4" data-proposal-queue>
    <div class="card-header bg-transparent py-3">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <h2 class="h5 mb-1">پیشنهادهای در انتظار بررسی</h2>
                <p class="small text-muted mb-0">آستانهٔ فعلی راستی‌آزمایی: {{ $verificationThreshold }} کاربر متمایز</p>
            </div>
            <form method="GET" action="{{ route('admin.location-governance.index') }}" class="d-flex align-items-center gap-2">
                <label for="proposal-status-filter" class="small text-muted mb-0">وضعیت</label>
                <select id="proposal-status-filter" class="form-select form-select-sm" name="proposal_status" onchange="this.form.submit()">
                    <option value="" @selected($proposalStatusFilter === null)>همهٔ بازها</option>
                    <option value="pending" @selected($proposalStatusFilter === 'pending')>در انتظار</option>
                    <option value="ready_for_review" @selected($proposalStatusFilter === 'ready_for_review')>آمادهٔ بازبینی</option>
                    <option value="needs_evidence" @selected($proposalStatusFilter === 'needs_evidence')>نیازمند مدرک</option>
                </select>
                <noscript><button class="btn btn-sm btn-outline-secondary" type="submit">اعمال</button></noscript>
            </form>
        </div>
    </div>
    <div class="card-body">
        @forelse($proposals as $proposal)
            @php
                $review = $hodaReviews->get($proposal->id, []);
                $auditEntries = collect($proposal->audit_log ?? []);
                $latestAudit = $auditEntries->last();
            @endphp
            <article class="border rounded-3 p-3 mb-3">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                    <div>
                        <h3 class="h6 mb-1">{{ $proposal->canonical_name }}</h3>
                        <div class="small text-muted">
                            وضعیت: {{ $proposal->status->value ?? $proposal->status }} ·
                            نوع: {{ $proposal->type?->key ?: '—' }} ·
                            شواهد: {{ $proposal->evidence_count }}
                        </div>
                        <div class="small text-muted mt-1">
                            والد: {{ $proposal->parentLocation?->canonical_name ?: $proposal->parentLocation?->name ?: '—' }}
                        </div>
                        @if(is_array($latestAudit))
                            <div class="small mt-2" data-proposal-audit-context>
                                <strong>آخرین سابقهٔ بازبینی:</strong>
                                {{ $latestAudit['reason'] ?? '—' }}
                                @if(!empty($latestAudit['actor_user_id']))
                                    <span class="text-muted">· مدیر #{{ $latestAudit['actor_user_id'] }}</span>
                                @endif
                                @if(!empty($latestAudit['to']))
                                    <span class="text-muted">· {{ $latestAudit['to'] }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="alert alert-warning mb-0 py-2 px-3">
                        <strong>پیشنهاد نجم هدا — نیازمند تایید انسانی:</strong>
                        {{ $review['recommendation'] ?? 'review' }}
                        @if(!empty($review['duplicate_candidate_id']))
                            <span class="d-block small">مکان مشابه: #{{ $review['duplicate_candidate_id'] }}</span>
                        @endif
                    </div>
                </div>

                <p class="small mt-3 mb-2">{{ $review['rationale'] ?? 'بازبینی انسانی لازم است.' }}</p>

                <div class="row g-2 mt-1">
                    <div class="col-12 col-xl-6">
                        <form method="POST" action="{{ route('admin.location-governance.proposals.approve', $proposal) }}" class="d-flex gap-2">
                            @csrf
                            <input class="form-control" name="reason" required minlength="4" maxlength="1000" placeholder="دلیل تأیید انسانی">
                            <button class="btn btn-success" type="submit">تأیید</button>
                        </form>
                    </div>
                    <div class="col-12 col-xl-6">
                        <form method="POST" action="{{ route('admin.location-governance.proposals.reject', $proposal) }}" class="d-flex gap-2">
                            @csrf
                            <input class="form-control" name="reason" required minlength="4" maxlength="1000" placeholder="دلیل رد انسانی">
                            <button class="btn btn-outline-danger" type="submit">رد</button>
                        </form>
                    </div>
                    <div class="col-12 col-xl-6">
                        <form method="POST" action="{{ route('admin.location-governance.proposals.request-evidence', $proposal) }}" class="d-flex gap-2">
                            @csrf
                            <input class="form-control" name="reason" required minlength="4" maxlength="1000" placeholder="مدرک یا توضیح موردنیاز">
                            <button class="btn btn-outline-secondary" type="submit">مدرک بیشتر</button>
                        </form>
                    </div>
                    <div class="col-12 col-xl-6">
                        <form method="POST" action="{{ route('admin.location-governance.proposals.merge', $proposal) }}" class="d-flex gap-2">
                            @csrf
                            <input class="form-control" type="number" min="1" name="existing_location_id" required value="{{ $review['duplicate_candidate_id'] ?? '' }}" placeholder="ID مکان موجود">
                            <input class="form-control" name="reason" required minlength="4" maxlength="1000" placeholder="دلیل ادغام">
                            <button class="btn btn-outline-primary" type="submit">ادغام</button>
                        </form>
                    </div>
                </div>
            </article>
        @empty
            <p class="text-muted mb-0">پیشنهاد بازی برای بازبینی وجود ندارد.</p>
        @endforelse
    </div>
</section>
