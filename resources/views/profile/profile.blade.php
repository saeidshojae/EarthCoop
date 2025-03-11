@extends('layouts.app')

@section('content')
<div class="container mt-5" style="direction: rtl; text-align: right;">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <!-- کارت اطلاعات کاربر -->
            <div class="card">
                <div class="card-header text-center bg-primary text-white">
                    پروفایل کاربر
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <!-- تصویر پروفایل -->
                        <img src="{{ asset('images/default-profile.png') }}" alt="تصویر پروفایل" class="rounded-circle" width="150" height="150">
                    </div>
                    <table class="table table-bordered">
                        <tr>
                            <th>نام کاربری:</th>
                            <td>{{ Auth::user()->name }}</td>
                        </tr>
                        <tr>
                            <th>ایمیل:</th>
                            <td>{{ Auth::user()->email }}</td>
                        </tr>
                        <tr>
                            <th>تاریخ ثبت‌نام:</th>
                            <td>{{ Auth::user()->created_at->format('Y/m/d') }}</td>
                        </tr>
                        <tr>
                            <th>آخرین ورود:</th>
                            <td>{{ Auth::user()->last_login_at ?? 'نامشخص' }}</td>
                        </tr>
                    </table>
                    <div class="text-center mt-4">
                        <a href="{{ route('profile.edit') }}" class="btn btn-warning">ویرایش اطلاعات</a>
                        <a href="{{ route('home') }}" class="btn btn-secondary">بازگشت به صفحه اصلی</a>
                    </div>
                </div>
            </div>

            <!-- کارت فعالیت‌های کاربر -->
            <div class="card mt-4">
                <div class="card-header bg-secondary text-white">
                    فعالیت‌های شما
                </div>
                <div class="card-body">
                    <p>تعداد گروه‌های شما: {{ Auth::user()->groups->count() }}</p>
                    <p>آخرین فعالیت: {{ Auth::user()->last_activity_at ?? 'نامشخص' }}</p>
                    <p>اطلاعیه‌ها: (در صورت نیاز این بخش قابل سفارشی‌سازی است)</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
