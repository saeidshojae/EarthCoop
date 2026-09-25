<section class="card border-0 shadow-sm mb-4" data-settlement-review-queue>
    <div class="card-header bg-transparent d-flex flex-column flex-md-row justify-content-between gap-2">
        <div>
            <h2 class="h5 mb-1">درخواست‌های سکونت در آبادی‌های مرجع</h2>
            <div class="small text-muted">
                {{ number_format($settlementClaimReviewThreshold) }} کاربر فقط پرونده را در اولویت بررسی قرار می‌دهند؛ تأیید سکونت یا حکمرانی خودکار نیست.
            </div>
        </div>
        <span class="badge bg-secondary align-self-start">{{ number_format($settlementReviewQueue->count()) }} آبادی در صف</span>
    </div>
    <div class="card-body">
        @if($settlementReviewQueue->isEmpty())
            <div class="text-muted">درخواست بازی برای آبادی‌های مرجع وجود ندارد.</div>
        @else
            <div class="vstack gap-3">
                @foreach($settlementReviewQueue as $settlement)
                    @php($priority = $settlement->open_residence_claims_count >= $settlementClaimReviewThreshold)
                    <article class="border rounded p-3">
                        <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                            <div>
                                <h3 class="h6 mb-1">{{ $settlement->name_fa }}</h3>
                                <div class="small text-muted">{{ $settlement->external_id }} · والد: {{ $settlement->parent_external_id }}</div>
                            </div>
                            <div class="d-flex gap-2 align-items-start">
                                <span class="badge {{ $priority ? 'bg-warning text-dark' : 'bg-light text-dark border' }}">
                                    {{ number_format($settlement->open_residence_claims_count) }} درخواست یکتا
                                </span>
                                @if($priority)
                                    <span class="badge bg-warning text-dark">اولویت بررسی</span>
                                @endif
                            </div>
                        </div>

                        <div class="alert alert-light border small py-2">
                            وضعیت فعلی: <strong>{{ $settlement->classification }}</strong> /
                            صلاحیت سکونت: <strong>{{ $settlement->residential_eligibility }}</strong>.
                            این فرم هیچ حوزهٔ حکمرانی، گروه یا حق رأی ایجاد نمی‌کند.
                        </div>

                        <form method="POST" action="{{ route('admin.location-governance.reference-settlements.review', $settlement) }}" class="row g-2">
                            @csrf
                            <div class="col-12 col-md-4">
                                <label class="form-label">تصمیم انسانی</label>
                                <select name="decision" class="form-select" required>
                                    <option value="needs_evidence">مدرک بیشتری لازم است</option>
                                    <option value="verified_residential_village">شواهد سکونت روستایی تأیید شد</option>
                                    <option value="verified_nonresidential_place">مکان غیرمسکونی تأیید شد</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-8">
                                <label class="form-label">دلیل تصمیم</label>
                                <input name="reason" class="form-control" minlength="4" maxlength="1000" required>
                            </div>
                            <div class="col-12 col-lg-4">
                                <label class="form-label">مرجع مدرک</label>
                                <input name="evidence_source" class="form-control" maxlength="255" placeholder="نام سازمان/سامانه رسمی">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label">تاریخ مدرک</label>
                                <input name="evidence_date" type="date" class="form-control">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-5">
                                <label class="form-label">شناسه یا پیوند مدرک</label>
                                <input name="evidence_reference" class="form-control" maxlength="1000">
                            </div>
                            <div class="col-12">
                                <div class="form-text mb-2">
                                    برای طبقه‌بندی مسکونی یا غیرمسکونی، هر سه مشخصهٔ مدرک الزامی‌اند. گزینهٔ «مدرک بیشتر» وضعیت را به «نیازمند بررسی» می‌برد، اما ماهیت مسکونی یا غیرمسکونی را تأیید نمی‌کند.
                                </div>
                                <button class="btn btn-outline-primary">ثبت بازبینی ممیزی‌شده</button>
                            </div>
                        </form>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
