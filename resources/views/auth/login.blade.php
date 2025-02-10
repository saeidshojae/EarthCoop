@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">{{ __('ورود به سیستم') }}</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <!-- ایمیل -->
                        <div class="mb-3">
                            <label for="email" class="form-label">{{ __('آدرس ایمیل') }}</label>
                            <input 
                                id="email" 
                                type="email" 
                                class="form-control @error('email') is-invalid @enderror" 
                                name="email" 
                                value="{{ old('email') }}" 
                                required 
                                autocomplete="email" 
                                autofocus
                            >
                            @error('email')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <!-- رمز عبور -->
                        <div class="mb-3">
                            <label for="password" class="form-label">{{ __('رمز عبور') }}</label>
                            <input 
                                id="password" 
                                type="password" 
                                class="form-control @error('password') is-invalid @enderror" 
                                name="password" 
                                required 
                                autocomplete="current-password"
                            >
                            @error('password')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <!-- یادآوری رمز -->
                        <div class="mb-3 form-check">
                            <input 
                                class="form-check-input" 
                                type="checkbox" 
                                name="remember" 
                                id="remember" 
                                {{ old('remember') ? 'checked' : '' }}
                            >
                            <label class="form-check-label" for="remember">
                                {{ __('مرا به خاطر بسپار') }}
                            </label>
                        </div>

                        <!-- دکمه ارسال -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                {{ __('ورود') }}
                            </button>

                            @if (Route::has('password.request'))
                                <a class="btn btn-link" href="{{ route('password.request') }}">
                                    {{ __('رمز عبور را فراموش کرده‌اید؟') }}
                                </a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection