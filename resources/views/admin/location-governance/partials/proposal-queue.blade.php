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
                $awaitingParentResolution = (bool) ($review['awaiting_parent_resolution'] ?? false);
                $hasOpenChildren = (int) ($proposal->open_child_proposals_count ?? 0) > 0;
            @endphp
            <article class="border rounded-3 p-3 mb-3">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                    <div>
                        <h3 class="h6 mb-1">{{ \App\Support\LocationDisplayName::for($proposal) }}</h3>
                        <div class="small text-muted">
                            وضعیت: {{ $proposal->status->value ?? $proposal->status }} ·
                            نوع: {{ $proposal->type?->key ?: '—' }} ·
                            حمایت: {{ number_format($proposal->evidence_count) }} از {{ number_format($verificationThreshold) }} ·
                            گروه‌های وابسته در انتظار: {{ number_format($proposal->pending_group_requests_count ?? 0) }}
                        </div>
                        <div class="small text-muted mt-1">
                            @if($proposal->parentProposal)
                                والد پیشنهادی: {{ \App\Support\LocationDisplayName::for($proposal->parentProposal) }}
                            @else
                                والد: {{ $proposal->parentLocation ? \App\Support\LocationDisplayName::for($proposal->parentLocation) : '—' }}
                            @endif
                        </div>
                        <div class="small mt-2" data-proposal-path="{{ $proposal->id }}">
                            <span class="text-muted">مسیر کامل پیشنهاد:</span>
                            @foreach($proposalPaths[$proposal->id] ?? [] as $segment)
                                @if(! $loop->first) <span class="text-muted">/</span> @endif
                                <span>{{ $segment['label'] }}@if($segment['pending']) (در انتظار بررسی)@endif</span>
                            @endforeach
                        </div>
                        <form method="POST" action="{{ route('admin.location-governance.proposals.update', $proposal) }}" class="row g-2 mt-2" data-proposal-rename-form="{{ $proposal->id }}">
                            @csrf
                            @method('PUT')
                            <div class="col-12 col-lg-5">
                                <input class="form-control form-control-sm" name="canonical_name" required maxlength="255" value="{{ $proposal->canonical_name }}" aria-label="نام اصلاح‌شده پیشنهاد">
                            </div>
                            <div class="col-12 col-lg-5">
                                <input class="form-control form-control-sm" name="reason" required minlength="4" maxlength="1000" placeholder="دلیل اصلاح نام">
                            </div>
                            <div class="col-12 col-lg-2">
                                <button class="btn btn-sm btn-outline-primary w-100" type="submit">اصلاح نام</button>
                            </div>
                        </form>
                        @if($awaitingParentResolution)
                            <div class="small fw-semibold text-warning-emphasis mt-2">
                                ابتدا پیشنهاد والد را تعیین تکلیف کنید؛ تا آن زمان تأیید یا ادغام این فرزند مجاز نیست.
                            </div>
                        @endif
                        @if($hasOpenChildren)
                            <div class="small fw-semibold text-warning-emphasis mt-2">
                                این پیشنهاد فرزند باز دارد و تا تعیین تکلیف یا بازاتصال فرزندان قابل رد نیست.
                            </div>
                        @endif
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
                    @unless($awaitingParentResolution)
                        <div class="col-12 col-xl-6">
                            <form method="POST" action="{{ route('admin.location-governance.proposals.approve', $proposal) }}" class="d-flex flex-column flex-md-row gap-2">
                                @csrf
                                <input class="form-control" name="reason" required minlength="4" maxlength="1000" placeholder="دلیل تأیید انسانی">
                                <button class="btn btn-success" type="submit">تأیید</button>
                            </form>
                        </div>
                    @endunless
                    @unless($hasOpenChildren)
                        <div class="col-12 col-xl-6">
                            <form method="POST" action="{{ route('admin.location-governance.proposals.reject', $proposal) }}" class="d-flex flex-column flex-md-row gap-2">
                                @csrf
                                <input class="form-control" name="reason" required minlength="4" maxlength="1000" placeholder="دلیل رد انسانی">
                                <button class="btn btn-outline-danger" type="submit">رد</button>
                            </form>
                        </div>
                    @endunless
                    <div class="col-12 col-xl-6">
                        <form method="POST" action="{{ route('admin.location-governance.proposals.request-evidence', $proposal) }}" class="d-flex flex-column flex-md-row gap-2">
                            @csrf
                            <input class="form-control" name="reason" required minlength="4" maxlength="1000" placeholder="مدرک یا توضیح موردنیاز">
                            <button class="btn btn-outline-secondary" type="submit">مدرک بیشتر</button>
                        </form>
                    </div>
                    @unless($awaitingParentResolution)
                        <div class="col-12 col-xl-6">
                            <form method="POST" action="{{ route('admin.location-governance.proposals.merge', $proposal) }}" class="d-flex flex-column flex-md-row gap-2">
                                @csrf
                                <input class="form-control" type="number" min="1" name="existing_location_id" required value="{{ $review['duplicate_candidate_id'] ?? '' }}" placeholder="ID مکان موجود">
                                <input class="form-control" name="reason" required minlength="4" maxlength="1000" placeholder="دلیل ادغام">
                                <button class="btn btn-outline-primary" type="submit">ادغام</button>
                            </form>
                        </div>
                    @endunless
                </div>
            </article>
        @empty
            <p class="text-muted mb-0">پیشنهاد بازی برای بازبینی وجود ندارد.</p>
        @endforelse
    </div>
</section>
