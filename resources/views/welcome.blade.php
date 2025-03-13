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
          <form id="registrationForm" action="{{ route('register.accept') }}" method="POST" novalidate>
            @csrf
            <div class="form-check mb-3">
              <input type="checkbox" name="agreement" id="agreement" 
                     class="form-check-input">
              <label for="agreement" class="form-check-label">
                موافقت با <a href="{{ route('terms') }}" target="_blank" onclick="openTerms()">اساسنامه و قوانین</a>
              </label>
              <div id="agreementError" class="text-danger" style="display: none;">
                لطفاً با اساسنامه و قوانین موافقت کنید.
              </div>
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

<script>
document.addEventListener("DOMContentLoaded", function() {
    // بررسی مقدار localStorage فقط اگر قبلاً تیک زده شده باشد
    if (localStorage.getItem('termsAccepted') === 'true') {
        document.getElementById('agreement').checked = true;
    }

    // گوش دادن به تغییرات localStorage
    window.addEventListener("storage", function(event) {
        if (event.key === "termsAccepted" && event.newValue === 'true') {
            document.getElementById('agreement').checked = true;
        }
    });

    document.getElementById('registrationForm').addEventListener('submit', function(event) {
        var agreementCheckbox = document.getElementById('agreement');
        if (!agreementCheckbox.checked) {
            event.preventDefault();
            document.getElementById('agreementError').style.display = 'block';
        } else {
            document.getElementById('agreementError').style.display = 'none';
        }
    });
});

// باز کردن صفحه اساسنامه در یک تب جدید و گوش دادن به تغییرات
function openTerms() {
    var termsWindow = window.open("{{ route('terms') }}", "_blank");
    var checkInterval = setInterval(function() {
        if (termsWindow.closed) {
            if (localStorage.getItem('termsAccepted') === 'true') {
                document.getElementById('agreement').checked = true;
            }
            clearInterval(checkInterval);
        }
    }, 500);
}
</script>
@endsection
