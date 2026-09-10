@if (config('location-governance.registration_enabled'))
    @include('auth.register_step3_canonical')
@else
    @include('auth.register_step3_legacy')
@endif
