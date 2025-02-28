@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-lg rounded">
                <div class="card-header bg-primary text-white text-center">
                    <h4>{{ __('Register') }}</h4>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('register') }}">
                        @csrf

                        @foreach([
                            ['first_name', 'First Name', 'text'],
                            ['last_name', 'Last Name', 'text'],
                            ['birth_date', 'Birth Date', 'date'],
                            ['phone', 'Phone', 'text'],
                            ['nationality', 'Nationality', 'text'],
                            ['national_id', 'National ID', 'text'],
                        ] as $field)
                            <div class="row mb-3">
                                <label for="{{ $field[0] }}" class="col-md-4 col-form-label text-md-end">{{ __($field[1]) }}</label>
                                <div class="col-md-6">
                                    <input id="{{ $field[0] }}" type="{{ $field[2] }}" class="form-control @error($field[0]) is-invalid @enderror" name="{{ $field[0] }}" value="{{ old($field[0]) }}" required autocomplete="{{ $field[0] }}">
                                    @error($field[0])
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        @endforeach

                        @foreach([
                            ['gender', 'Gender', ['male' => 'Male', 'female' => 'Female', 'other' => 'Other']],
                            ['job_fields', 'Job Fields', $jobFields],
                            ['specializations', 'Specializations', $specializations],
                            ['continent', 'Continent', $continents],
                            ['country', 'Country', $countries],
                            ['province', 'Province', $provinces],
                            ['county', 'County', $counties],
                            ['district', 'District', $districts],
                            ['settlement', 'Settlement', $settlements],
                            ['locality', 'Locality', $localities],
                            ['neighborhood', 'Neighborhood', $neighborhoods],
                            ['street', 'Street', $streets],
                            ['alley', 'Alley', $alleys],
                        ] as $field)
                            <div class="row mb-3">
                                <label for="{{ $field[0] }}" class="col-md-4 col-form-label text-md-end">{{ __($field[1]) }}</label>
                                <div class="col-md-6">
                                    @if(is_array($field[2]))
                                        <select id="{{ $field[0] }}" class="form-control @error($field[0]) is-invalid @enderror" name="{{ $field[0] }}" required>
                                            @foreach($field[2] as $key => $value)
                                                <option value="{{ is_array($value) ? $value->id : $key }}" {{ old($field[0]) == (is_array($value) ? $value->id : $key) ? 'selected' : '' }}>{{ is_array($value) ? $value->title : $value }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <select id="{{ $field[0] }}" class="form-control @error($field[0]) is-invalid @enderror" name="{{ $field[0] }}[]" multiple required>
                                            @foreach($field[2] as $item)
                                                <option value="{{ $item->id }}" {{ in_array($item->id, old($field[0], [])) ? 'selected' : '' }}>{{ $item->title }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    @error($field[0])
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        @endforeach

                        <div class="row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    {{ __('Register') }}
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
