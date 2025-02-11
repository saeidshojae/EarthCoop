// filepath: /c:/Users/saeed/EarthCoop/EarthCoop/resources/views/welcome.blade.php
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Welcome to EarthCoop') }}</div>

                <div class="card-body">
                    <p>Welcome to EarthCoop, a global cooperative platform for Earth's residents. Please read and accept our terms and conditions to proceed with the registration.</p>
                    <form method="GET" action="{{ route('register') }}">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="acceptTerms" required>
                            <label class="form-check-label" for="acceptTerms">
                                I accept the terms and conditions
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">Start Registration</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection