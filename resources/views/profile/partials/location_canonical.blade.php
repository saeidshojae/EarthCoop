<section class="profile-card profile-card--location">
    <div class="profile-card__header">
        <div>
            <h2 class="profile-card__title">محل سکونت اصلی</h2>
            <p class="profile-card__subtitle">محل سکونت رسمی شما مبنای عضویت جغرافیایی و حوزهٔ حکمرانی رسمی است.</p>
        </div>
    </div>

    @if (isset($primaryResidence) && $primaryResidence?->location)
        <div class="alert alert-light border mb-3">
            محل فعلی: <strong>{{ $primaryResidence->location->canonical_name ?: $primaryResidence->location->name }}</strong>
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
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3" data-location-geolocation>
                <button type="button" class="btn btn-outline-primary btn-sm" data-location-geolocation-detect>تشخیص موقعیت من</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-location-geolocation-manual>انتخاب دستی</button>
            </div>
            <p class="small text-muted mb-3 d-none" data-location-geolocation-status aria-live="polite"></p>
            <div class="vstack gap-3" data-location-levels></div>
            <p class="small text-muted mt-3 mb-0" data-location-status aria-live="polite">برای تغییر محل سکونت، مسیر جدید را تا یک نقطهٔ معتبر انتخاب کنید.</p>
        </div>

        @error('location_id')
            <div class="text-danger small mt-2">{{ $message }}</div>
        @enderror

        <div class="mt-3">
            <button type="submit" class="btn btn-primary" data-location-submit disabled>ذخیره محل سکونت</button>
        </div>
    </form>
</section>
