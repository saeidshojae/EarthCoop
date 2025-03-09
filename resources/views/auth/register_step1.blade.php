<!DOCTYPE html>
<html lang="fa">
<head>
  <meta charset="UTF-8">
  <title>مرحله ۱: اطلاعات هویتی</title>
  <link href="{{ asset('css/app.css') }}" rel="stylesheet" />
</head>
<body>
  <div class="container mt-5">
    <h1 class="mb-4">مرحله ۱: اطلاعات هویتی</h1>
    <form action="{{ route('register.step1.process') }}" method="POST">
      @csrf
      <div class="form-group">
        <label for="first_name">نام:</label>
        <input type="text" name="first_name" id="first_name" class="form-control" required>
      </div>
      <div class="form-group">
        <label for="last_name">نام خانوادگی:</label>
        <input type="text" name="last_name" id="last_name" class="form-control" required>
      </div>
      <div class="form-group">
        <label for="birth_date">تاریخ تولد:</label>
        <input type="date" name="birth_date" id="birth_date" class="form-control" required>
      </div>
      <div class="form-group">
        <label for="gender">جنسیت:</label>
        <select name="gender" id="gender" class="form-control" required>
          <option value="">انتخاب کنید</option>
          <option value="male">مرد</option>
          <option value="female">زن</option>
        </select>
      </div>
      <div class="form-group">
        <label for="nationality">ملیت:</label>
        <input type="text" name="nationality" id="nationality" class="form-control" required>
      </div>
      <div class="form-group">
        <label for="national_id">کدملی:</label>
        <input type="text" name="national_id" id="national_id" class="form-control" required>
      </div>
      <div class="form-group">
        <label for="phone">شماره تلفن:</label>
        <input type="text" name="phone" id="phone" class="form-control" required>
      </div>
      <div class="form-group">
        <label for="email">ایمیل:</label>
        <input type="email" name="email" id="email" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary mt-3">ادامه</button>
    </form>
  </div>
</body>
</html>
