@extends('layouts.app')

@section('content')
<div class="container mt-5" style="direction: rtl; text-align: right;">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header text-center" style="font-family: 'Shabnam', Arial, sans-serif;">
          ثبت‌نام اولیه
        </div>
        <div class="card-body">
          <form action="{{ route('register.process') }}" method="POST" novalidate>
            @csrf

            <div class="mb-3">
              <label for="email" class="form-label">ایمیل:</label>
              <input type="email" name="email" id="email" 
                     value="{{ old('email') }}" 
                     class="form-control @error('email') is-invalid @enderror" required>
              @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="phone" class="form-label">شماره تلفن:</label>
              <input type="text" name="phone" id="phone" 
                     value="{{ old('phone') }}" 
                     class="form-control @error('phone') is-invalid @enderror" required>
              @error('phone')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="password" class="form-label">رمز عبور:</label>
              <input type="password" name="password" id="password" 
                     class="form-control @error('password') is-invalid @enderror" required>
              @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="password_confirmation" class="form-label">تایید رمز عبور:</label>
              <input type="password" name="password_confirmation" 
                     id="password_confirmation" class="form-control" required>
            </div>

            <div class="text-center">
              <button type="submit" class="btn btn-primary">ادامه</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
