@extends('layouts.app')

@section('content')
<div class="container mt-5" style="direction: rtl; text-align: right;">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header text-center" style="font-family: 'Shabnam', Arial, sans-serif;">
          اساسنامه و قوانین
        </div>
        <div class="card-body">
          <p>لطفاً شرایط و قوانین زیر را مطالعه کنید و در صورت موافقت، تیک را بزنید.</p>
          
          <div class="form-check mb-3">
            <input type="checkbox" id="termsCheck" class="form-check-input">
            <label for="termsCheck" class="form-check-label">
              من با اساسنامه و قوانین موافقم.
            </label>
          </div>

          <div class="text-center">
            <button onclick="acceptTerms()" class="btn btn-success">موافقت و بازگشت</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function acceptTerms() {
    if (document.getElementById('termsCheck').checked) {
        localStorage.setItem('termsAccepted', 'true');
        window.close(); // بستن پنجره
    } else {
        alert('لطفاً ابتدا تیک بزنید.');
    }
}
</script>
@endsection
