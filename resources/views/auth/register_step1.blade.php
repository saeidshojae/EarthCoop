@extends('layouts.app')

@section('content')
<div class="container mt-5" style="direction: rtl; text-align: right;">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header text-center" style="font-family: 'Shabnam', Arial, sans-serif;">
          مرحله ۱: اطلاعات هویتی
        </div>
        <div class="card-body">
          <form action="{{ route('register.step1.process') }}" method="POST" novalidate>
            @csrf

            <div class="mb-3">
              <label for="first_name" class="form-label">نام:</label>
              <input type="text" name="first_name" id="first_name" 
                     value="{{ old('first_name') }}"
                     class="form-control @error('first_name') is-invalid @enderror" required>
              @error('first_name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="last_name" class="form-label">نام خانوادگی:</label>
              <input type="text" name="last_name" id="last_name" 
                     value="{{ old('last_name') }}"
                     class="form-control @error('last_name') is-invalid @enderror" required>
              @error('last_name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="birth_date" class="form-label">تاریخ تولد:</label>
              <input type="date" name="birth_date" id="birth_date" 
                     value="{{ old('birth_date') }}"
                     class="form-control @error('birth_date') is-invalid @enderror" required>
              @error('birth_date')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="gender" class="form-label">جنسیت:</label>
              <select name="gender" id="gender" 
                      class="form-control @error('gender') is-invalid @enderror" required>
                <option value="">انتخاب کنید</option>
                <option value="male" {{ old('gender') == 'male' ? 'selected' : '' }}>مرد</option>
                <option value="female" {{ old('gender') == 'female' ? 'selected' : '' }}>زن</option>
              </select>
              @error('gender')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="nationality" class="form-label">ملیت:</label>
              <input type="text" name="nationality" id="nationality" 
                     value="{{ old('nationality') }}"
                     class="form-control @error('nationality') is-invalid @enderror" required>
              @error('nationality')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="national_id" class="form-label">کدملی:</label>
              <input type="text" name="national_id" id="national_id" 
                     value="{{ old('national_id') }}"
                     class="form-control @error('national_id') is-invalid @enderror" required>
              @error('national_id')
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
              <label for="email" class="form-label">ایمیل:</label>
              <input type="email" name="email" id="email" 
                     value="{{ old('email') }}"
                     class="form-control @error('email') is-invalid @enderror" required>
              @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
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
