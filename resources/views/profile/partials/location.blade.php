@if (config('location-governance.registration_enabled'))
    @include('profile.partials.location_canonical')
@else
    @include('profile.partials.location_legacy')
@endif
