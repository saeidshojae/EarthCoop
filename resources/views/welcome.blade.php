@extends('layouts.app')

@section('content')
<div class="container mt-5" style="direction: rtl; text-align: right;">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header text-center" style="font-family: 'Shabnam', Arial, sans-serif;">
          به برنامه ثبت‌نام چندمرحله‌ای خوش آمدید
        </div>
        <div class="card-body">
          <form action="{{ route('register.accept') }}" method="POST" novalidate>
            @csrf
            <div class="mb-3">
              <label for="fingerprint_id" class="form-label">اثر انگشت (شناسه):</label>
              <input type="text" name="fingerprint_id" id="fingerprint_id" 
                     class="form-control" readonly required>
              <button type="button" onclick="captureFingerprint()" 
                      class="btn btn-secondary mt-2">جمع‌آوری اثر انگشت</button>
            </div>
            <div class="form-check mb-3">
              <input type="checkbox" name="agreement" id="agreement" 
                     class="form-check-input" required>
              <label for="agreement" class="form-check-label">
                موافقت با قوانین و مقررات
              </label>
            </div>
            <div class="text-center">
              <button type="submit" class="btn btn-primary">تایید و ادامه</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
