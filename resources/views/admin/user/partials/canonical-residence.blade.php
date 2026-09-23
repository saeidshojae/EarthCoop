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

            <div
                data-location-selector
                data-location-selector-context="admin-user-residence"
                data-location-current-id="{{ ($pendingResidenceIntent ?? null) ? '' : ($primaryResidence?->location_id ?? '') }}"
                data-location-current-proposal-id="{{ $pendingResidenceIntent?->location_proposal_id ?? '' }}"
                data-location-current-path='@json($residenceHydrationPath ?? [])'
                data-country-code="{{ $user->locationRelationships()->where('relationship_type', 'primary_residence')->whereNull('ended_at')->with('location')->latest('started_at')->first()?->location?->country_code ?: 'IR' }}"
                data-empty-label="یک گزینه را انتخاب کنید"
                data-loading-label="در حال دریافت گزینه‌های مکانی..."
                data-error-label="دریافت گزینه‌های مکانی ممکن نشد. دوباره تلاش کنید."
            >
                <input type="hidden" name="location_id" value="{{ old('location_id') }}" data-location-id>
                <input type="hidden" name="location_proposal_id" value="{{ old('location_proposal_id') }}" data-location-proposal-id>
                @php
                    $persistedStructuralClaimIds = collect(old(
                        'location_structure_claim_ids',
                        data_get($primaryResidence?->metadata, 'structural_claim_ids', [])
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
