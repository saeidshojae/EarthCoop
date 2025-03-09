<!DOCTYPE html>
<html lang="fa">
<head>
  <meta charset="UTF-8">
  <title>ثبت‌نام اولیه</title>
  <link href="{{ asset('css/app.css') }}" rel="stylesheet" />
</head>
<body>
  <div class="container mt-5">
    <h1 class="mb-4">ثبت‌نام اولیه</h1>
    <form action="{{ route('register.process') }}" method="POST">
      @csrf
      <div class="form-group">
         <label for="email">ایمیل:</label>
         <input type="email" name="email" id="email" class="form-control" required>
      </div>
      <div class="form-group">
         <label for="phone">شماره تلفن:</label>
         <input type="text" name="phone" id="phone" class="form-control" required>
      </div>
      <div class="form-group">
         <label for="password">رمز عبور:</label>
         <input type="password" name="password" id="password" class="form-control" required>
      </div>
      <div class="form-group">
         <label for="password_confirmation">تایید رمز عبور:</label>
         <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary mt-3">ادامه</button>
    </form>
  </div>
</body>
</html>
