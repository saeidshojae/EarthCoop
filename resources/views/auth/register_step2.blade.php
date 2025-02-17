@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-lg rounded">
                <div class="card-header bg-primary text-white text-center">
                    <h4 style="font-family: 'Sahel', sans-serif;">{{ __('مرحله ۲: انتخاب حوزه‌های صنعتی و تخصص‌ها') }}</h4>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('register.step2.post') }}">
                        @csrf

                        <div class="row mb-3">
                            <label for="industrial_fields" class="col-md-4 col-form-label text-md-end">{{ __('حوزه‌های صنعتی') }}</label>
                            <div class="col-md-6">
                                <select id="industrial_fields" class="form-control @error('industrial_fields') is-invalid @enderror" name="industrial_fields[]" multiple required dir="rtl" style="color: black !important;">
                                    @foreach($industrialFields as $field)
                                        <optgroup label="{{ $field->title }}">
                                            @foreach($field->children as $child)
                                                <option value="{{ $child->id }}">{{ $child->title }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                @error('industrial_fields')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="specializations" class="col-md-4 col-form-label text-md-end">{{ __('تخصص‌ها') }}</label>
                            <div class="col-md-6">
                                <select id="specializations" class="form-control @error('specializations') is-invalid @enderror" name="specializations[]" multiple required dir="rtl" style="color: black !important;">
                                    @foreach($specializations as $specialization)
                                        <option value="{{ $specialization->id }}">{{ $specialization->title }}</option>
                                    @endforeach
                                </select>
                                @error('specializations')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('بعدی') }}
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
