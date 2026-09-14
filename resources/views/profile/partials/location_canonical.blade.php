<section class="profile-card profile-card--location">
    <div class="profile-card__header">
        <div>
            <h2 class="profile-card__title">محل سکونت اصلی</h2>
            <p class="profile-card__subtitle">محل تأییدشده مبنای عضویت جغرافیایی و حوزهٔ حکمرانی رسمی شماست. جزئیات دقیق‌ترِ در انتظار بررسی جداگانه نمایش داده می‌شود.</p>
        </div>
    </div>

    @if (isset($primaryResidence) && $primaryResidence?->location)
        <div class="alert alert-light border mb-3">
            <div class="small text-muted mb-1">مکان تأییدشده و مبنای رسمی</div>
            <strong>{{ $primaryResidence->location->canonical_name ?: $primaryResidence->location->name }}</strong>
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
            <div>{{ $proposal->canonical_name }}</div>
            <div class="small mt-2">
                این جزئیات هنوز مکان رسمی محسوب نمی‌شود و تا زمان تأیید یا ادغام، حوزهٔ حکمرانی شما بر اساس مکان تأییدشدهٔ بالا محاسبه می‌شود.
            </div>
            @if ($proposalStatus === 'needs_evidence')
                <div class="small fw-semibold mt-2">برای این پیشنهاد اطلاعات یا مدرک بیشتری درخواست شده است.</div>
            @endif
        </div>
    @endif

    <form method="POST" action="{{ route('profile.update.address') }}" data-location-form>
        @csrf
        @method('PUT')
        <div
            data-location-selector
            data-location-selector-context="profile"
            data-country-code="{{ $primaryResidence?->location?->country_code ?: 'IR' }}"
            data-empty-label="یک گزینه را انتخاب کنید"
            data-loading-label="در حال دریافت گزینه‌های مکانی..."
            data-error-label="دریافت گزینه‌های مکانی ممکن نشد. دوباره تلاش کنید."
        >
            <input type="hidden" name="location_id" value="{{ old('location_id') }}" data-location-id>
            <input type="hidden" name="location_proposal_id" value="{{ old('location_proposal_id') }}" data-location-proposal-id>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3" data-location-geolocation>
                <button type="button" class="btn btn-outline-primary btn-sm" data-location-geolocation-detect>تشخیص موقعیت من</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-location-geolocation-manual>انتخاب دستی</button>
            </div>
            <p class="small text-muted mb-3 d-none" data-location-geolocation-status aria-live="polite"></p>
            <div class="vstack gap-3" data-location-levels></div>
            <p class="small text-muted mt-3 mb-0" data-location-status aria-live="polite">برای تغییر یا دقیق‌تر کردن محل سکونت، مسیر موردنظر را انتخاب کنید.</p>
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
