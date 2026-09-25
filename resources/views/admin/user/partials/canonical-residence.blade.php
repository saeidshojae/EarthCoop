@if ((bool) config('location-governance.registration_enabled'))
    <section class="user-form-card" data-admin-user-residence>
        <div class="user-form-header">
            <div>
                <h3 class="mb-1">
                    <i class="fas fa-map-marker-alt ml-2"></i>
                    محل سکونت اصلی و حوزه حکمرانی
                </h3>
                <p class="user-form-help mb-0">
                    این تغییر مستقل از اطلاعات هویتی است و باید با دلیل روشن ثبت شود. مکان پیشنهادی تا زمان تأیید، حوزه حکمرانی رسمی ایجاد نمی‌کند.
                </p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.users.residence.update', $user) }}" data-location-form>
            @csrf
            @method('PUT')
            @php
                $currentReferenceSettlement = $pendingResidenceIntent?->referenceSettlementResidenceClaim?->settlement;
                $referenceSettlementProposalPath = collect($referenceSettlementProposalPath ?? [])
                    ->map(fn ($id) => (int) $id)->filter()->values();
                $currentReferenceNeighborhoodProposalId = $currentReferenceSettlement
                    ? (string) ($referenceSettlementProposalPath->first() ?? '')
                    : '';
                $persistedReferenceSettlementExternalId = old(
                    'reference_settlement_external_id',
                    $currentReferenceSettlement?->external_id
                );
            @endphp

            <div
                data-location-selector
                data-location-selector-context="admin-user-residence"
                data-location-current-id="{{ ($pendingResidenceIntent ?? null) ? '' : ($primaryResidence?->location_id ?? '') }}"
                data-location-current-proposal-id="{{ $currentReferenceSettlement ? '' : ($pendingResidenceIntent?->location_proposal_id ?? '') }}"
                data-location-current-path='@json($residenceHydrationPath ?? [])'
                data-country-code="{{ $user->locationRelationships()->where('relationship_type', 'primary_residence')->whereNull('ended_at')->with('location')->latest('started_at')->first()?->location?->country_code ?: 'IR' }}"
                data-empty-label="یک گزینه را انتخاب کنید"
                data-loading-label="در حال دریافت گزینه‌های مکانی..."
                data-error-label="دریافت گزینه‌های مکانی ممکن نشد. دوباره تلاش کنید."
            >
                <input type="hidden" name="location_id" value="{{ old('location_id') }}" data-location-id>
                <input type="hidden" name="location_proposal_id" value="{{ old('location_proposal_id') }}" data-location-proposal-id>
                <input type="hidden" name="reference_settlement_external_id" value="{{ $persistedReferenceSettlementExternalId }}" data-reference-settlement-external-id>
                <div class="border rounded-3 p-2 mb-3 small" data-location-path aria-live="polite">مسیر انتخاب نشده</div>
                @php
                    $defaultStructuralClaimIds = collect(data_get($primaryResidence?->metadata, 'structural_claim_ids', []))
                        ->merge(data_get($pendingResidenceIntent?->metadata, 'structural_claim_ids', []))
                        ->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
                    $persistedStructuralClaimIds = collect(old(
                        'location_structure_claim_ids',
                        $defaultStructuralClaimIds
                    ))->map(fn ($id) => (int) $id)->filter()->unique()->values();
                @endphp
                @foreach ($persistedStructuralClaimIds as $claimId)
                    <input type="hidden" name="location_structure_claim_ids[]" value="{{ $claimId }}" data-location-structure-claim-id>
                @endforeach
                <div class="vstack gap-3" data-location-levels></div>
                <p class="user-form-help mt-3 mb-0" data-location-status aria-live="polite">
                    محل جدید یا جزئیات دقیق‌تر محل سکونت را انتخاب کنید.
                </p>
            </div>

            @if(config('iran_settlement_catalog.enabled') && config('iran_settlement_catalog.claims_enabled'))
                <section
                    class="vstack gap-3"
                    data-reference-settlement-picker
                    data-reference-settlement-current-external-id="{{ $persistedReferenceSettlementExternalId }}"
                    data-reference-settlement-current-name="{{ $currentReferenceSettlement?->name_fa }}"
                    data-reference-settlement-current-neighborhood-proposal-id="{{ $currentReferenceNeighborhoodProposalId }}"
                    data-reference-settlement-current-proposal-path='@json($referenceSettlementProposalPath->all())'
                    hidden
                >
                    <div class="fw-bold mb-1">جستجو در بانک آبادی‌های ۱۴۰۴</div>
                    <p class="user-form-help mb-3">نام آبادی را جستجو و گزینه درست را انتخاب کنید. انتخاب از بانک مرجع به‌معنای تأیید خودکار سکونت یا حکمرانی نیست.</p>
                    <div class="d-flex flex-column flex-md-row gap-2">
                        <input type="search" minlength="2" maxlength="60" class="user-form-input flex-grow-1" placeholder="نام آبادی" data-reference-settlement-query>
                        <button type="button" class="btn btn-outline-secondary" data-reference-settlement-search>جست‌وجوی آبادی</button>
                    </div>
                    <div class="mt-3 small text-muted" data-reference-settlement-status aria-live="polite"></div>
                    <div class="mt-2 d-grid gap-2" data-reference-settlement-results></div>
                </section>
            @endif

            @error('reference_settlement_external_id')
                <div class="user-form-error mt-2">{{ $message }}</div>
            @enderror

            <div class="user-form-group mt-4">
                <label for="residence_reason" class="user-form-label">
                    دلیل تغییر <span class="required">*</span>
                </label>
                <textarea
                    id="residence_reason"
                    name="reason"
                    class="user-form-input @error('reason') error @enderror"
                    rows="3"
                    required
                    maxlength="1000"
                    placeholder="دلیل اصلاح یا تغییر محل سکونت را ثبت کنید"
                >{{ old('reason') }}</textarea>
                @error('reason')
                    <div class="user-form-error">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="user-form-submit" data-location-submit disabled>
                <i class="fas fa-map-marked-alt"></i>
                ثبت تغییر محل سکونت
            </button>
        </form>
    </section>
@endif
