@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Edit Profile') }}</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        @method('PUT')

                        <!-- سایر فیلدها -->

                        <div class="row mb-3">
                            <label for="continent" class="col-md-4 col-form-label text-md-end">{{ __('Continent') }}</label>
                            <div class="col-md-6">
                                <select id="continent" class="form-control @error('continent') is-invalid @enderror" name="continent" required>
                                    @foreach($continents as $continent)
                                        <option value="{{ $continent->id }}" {{ old('continent', $user->continent_id) == $continent->id ? 'selected' : '' }}>{{ $continent->title }}</option>
                                    @endforeach
                                </select>
                                @error('continent')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="country" class="col-md-4 col-form-label text-md-end">{{ __('Country') }}</label>
                            <div class="col-md-6">
                                <select id="country" class="form-control @error('country') is-invalid @enderror" name="country" required>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}" {{ old('country', $user->country_id) == $country->id ? 'selected' : '' }}>{{ $country->title }}</option>
                                    @endforeach
                                </select>
                                @error('country')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <!-- سایر فیلدهای مکان -->

                        <div class="row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Update Profile') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection