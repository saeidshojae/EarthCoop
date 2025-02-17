@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-lg rounded">
                <div class="card-header bg-primary text-white text-center">
                    <h4>{{ __('Step 3: Location Information') }}</h4>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('register.step3.post') }}">
                        @csrf

                        @php
                            $fields = [
                                'continent' => $continents,
                                'country' => $countries,
                                'province' => $provinces,
                                'county' => $counties,
                                'district' => $districts,
                                'settlement' => $settlements,
                                'locality' => $localities,
                                'neighborhood' => $neighborhoods,
                                'street' => $streets,
                                'alley' => $alleys
                            ];
                        @endphp

                        @foreach ($fields as $field => $items)
                        <div class="row mb-3">
                            <label for="{{ $field }}" class="col-md-4 col-form-label text-md-end">{{ __(ucwords($field)) }}</label>
                            <div class="col-md-6">
                                <select id="{{ $field }}" class="form-control @error($field) is-invalid @enderror" name="{{ $field }}" required>
                                    @foreach($items as $item)
                                        <option value="{{ $item->id }}" {{ old($field) == $item->id ? 'selected' : '' }}>{{ $item->title }}</option>
                                    @endforeach
                                </select>
                                @error($field)
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        @endforeach

                        <div class="row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Complete Registration') }}
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
