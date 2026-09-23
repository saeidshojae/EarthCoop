<section class="profile-card profile-card--location location-residence-surface">
    <div class="profile-card__header">
        <div>
            <h2 class="profile-card__title">محل سکونت اصلی</h2>
            <p class="profile-card__subtitle">محل تأییدشده مبنای عضویت جغرافیایی و حوزهٔ حکمرانی رسمی شماست. جزئیات دقیق‌ترِ در انتظار بررسی جداگانه نمایش داده می‌شود.</p>
        </div>
    </div>

    @if (isset($primaryResidence) && $primaryResidence?->location)
        <div class="alert alert-light border mb-3">
            <div class="small text-muted mb-1">مکان تأییدشده و مبنای رسمی</div>
            <strong>{{ \App\Support\LocationDisplayName::typed($primaryResidence->location) }}</strong>
        </div>
    @endif

    @if (isset($pendingResidenceIntent) && $pendingResidenceIntent?->locationProposal)
        @php
            $proposal = $pendingResidenceIntent->locationProposal;
            $proposalStatus = $proposal->status instanceof \App\Enums\LocationGovernance\LocationProposalStatus
                ? $proposal->status->value
                : (string) $proposal->status;
        @endphp
        <div class="alert alert-warning border mb-3" data-pending-residence-state>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <span class="fw-semibold">مکان دقیق پیشنهادی</span>
                <span class="badge text-bg-warning">در انتظار بررسی</span>
            </div>
            <div>{{ \App\Support\LocationDisplayName::typed($proposal) }}</div>
            <div class="small mt-2">این جزئیات هنوز مکان رسمی محسوب نمی‌شود و تا زمان تأیید یا ادغام، حوزهٔ حکمرانی شما بر اساس مکان تأییدشدهٔ بالا محاسبه می‌شود.</div>
            @if ($proposalStatus === 'needs_evidence')
                <div class="small fw-semibold mt-2">برای این پیشنهاد اطلاعات یا مدرک بیشتری درخواست شده است.</div>
            @endif
        </div>
    @endif

    @if (isset($pendingResidenceIntent) && $pendingResidenceIntent?->referenceSettlementResidenceClaim?->settlement)
        @php
            $settlementClaim = $pendingResidenceIntent->referenceSettlementResidenceClaim;
            $settlement = $settlementClaim->settlement;
        @endphp
        <div class="alert alert-warning border mb-3" data-pending-reference-settlement-state>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <span class="fw-semibold">آبادی انتخابی در انتظار بررسی</span>
                <span class="badge text-bg-warning">در انتظار تأیید</span>
            </div>
            <div>{{ $settlement->name_fa ?: $settlement->external_id }}</div>
            <div class="small mt-2">
                محل دقیق شما با شناسهٔ مرجع ثبت شده است، اما هنوز Location رسمی یا حوزهٔ حکمرانی مستقل ایجاد نکرده است.
                مبنای رسمی فعلی همان مکان canonical بالاست.
            </div>
        </div>
    @endif

    @php
        $persistedProposalId = old('location_proposal_id', $pendingResidenceIntent?->location_proposal_id);
        $persistedLocationId = $persistedProposalId
            ? old('location_id')
            : old('location_id', $primaryResidence?->location_id);
        $persistedStructuralClaimIds = collect(old(
            'location_structure_claim_ids',
            data_get($primaryResidence?->metadata, 'structural_claim_ids', [])
        ))->map(fn ($id) => (int) $id)->filter()->unique()->values();
    @endphp

    <form method="POST" action="{{ route('profile.update.address') }}" data-location-form>
        @csrf
        @method('PUT')
        <div
            data-location-selector
            data-location-selector-context="profile"
            data-location-current-id="{{ $persistedProposalId ? '' : $primaryResidence?->location_id }}"
            data-location-current-proposal-id="{{ $persistedProposalId }}"
            data-location-current-path='@json($residenceHydrationPath ?? [])'
            data-empty-label="یک گزینه را انتخاب کنید"
            data-loading-label="در حال دریافت گزینه‌های مکانی..."
            data-error-label="دریافت گزینه‌های مکانی ممکن نشد. دوباره تلاش کنید."
        >
            <input type="hidden" name="location_id" value="{{ $persistedLocationId }}" data-location-id>
            <input type="hidden" name="location_proposal_id" value="{{ $persistedProposalId }}" data-location-proposal-id>

            @foreach ($persistedStructuralClaimIds as $claimId)
                <input type="hidden" name="location_structure_claim_ids[]" value="{{ $claimId }}" data-location-structure-claim-id>
            @endforeach

            <div class="location-geolocation-actions d-flex flex-wrap align-items-center gap-2 mb-3" data-location-geolocation>
                <button type="button" class="btn btn-outline-primary btn-sm" data-location-geolocation-detect>تشخیص موقعیت من</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-location-geolocation-manual>انتخاب دستی</button>
            </div>
            <p class="small text-muted mb-3 d-none" data-location-geolocation-status aria-live="polite"></p>

            <div class="rounded-3 border bg-light-subtle px-3 py-2 mb-3" data-location-path aria-live="polite">
                @if ($primaryResidence?->location)
                    <span class="small text-muted">مکان فعلی: </span>
                    <strong class="small">{{ \App\Support\LocationDisplayName::for($primaryResidence->location) }}</strong>
                @else
                    <span class="small text-muted">هنوز محل سکونت اصلی ثبت نشده است.</span>
                @endif
            </div>

            <div class="vstack gap-3" data-location-levels></div>
            <p class="small text-muted mt-3 mb-0" data-location-status aria-live="polite">برای تغییر یا دقیق‌تر کردن محل سکونت، مسیر موردنظر را انتخاب کنید.</p>
            <p class="location-proposal-help small text-muted mt-2 mb-0">اگر در سطوح محلی مجاز، مکان دقیق شما در فهرست نبود، گزینهٔ «مکان من در فهرست نیست» نمایش داده می‌شود و می‌توانید آن را برای بررسی پیشنهاد کنید.</p>
        </div>

        @error('location_id')
            <div class="text-danger small mt-2">{{ $message }}</div>
        @enderror
        @error('location_proposal_id')
            <div class="text-danger small mt-2">{{ $message }}</div>
        @enderror

        <div class="mt-3">
            <button type="submit" class="btn btn-primary" data-location-submit disabled>ذخیره محل سکونت</button>
        </div>
    </form>
</section>
