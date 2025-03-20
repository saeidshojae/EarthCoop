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
                        <img src="{{ Auth::user()->avatar ? asset(Auth::user()->avatar) : asset('images/default-profile.png') }}" 
                             alt="تصویر پروفایل" 
                             class="rounded-circle" 
                             width="150" 
                             height="150"
                             style="object-fit: cover;">
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
                            <td>{{ Auth::user()->created_at ? Auth::user()->created_at->format('Y/m/d') : 'نامشخص' }}</td>
                        </tr>
                        <tr>
                            <th>آخرین ورود:</th>
                            <td>{{ Auth::user()->last_login_at ?? 'نامشخص' }}</td>
                        </tr>
                        <!-- نمایش صنف‌ها -->
                        <tr>
                            <th>صنف‌ها:</th>
                            <td>
                                @forelse(Auth::user()->occupationalFields as $field)
                                    <span class="badge bg-primary me-1">{{ $field->name }}</span>
                                @empty
                                    <span class="text-muted">هیچ صنفی انتخاب نشده است</span>
                                @endforelse
                            </td>
                        </tr>
                        <!-- نمایش تخصص‌ها -->
                        <tr>
                            <th>تخصص‌ها:</th>
                            <td>
                                @forelse(Auth::user()->experienceFields as $field)
                                    <span class="badge bg-success me-1">{{ $field->name }}</span>
                                @empty
                                    <span class="text-muted">هیچ تخصصی انتخاب نشده است</span>
                                @endforelse
                            </td>
                        </tr>
                        <!-- نمایش مکان -->
                        <tr>
                            <th>مکان:</th>
                            <td>
                                @forelse(Auth::user()->locations as $location)
                                    <span class="badge bg-info me-1">{{ $location->name }}</span>
                                @empty
                                    <span class="text-muted">هیچ مکانی انتخاب نشده است</span>
                                @endforelse
                            </td>
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

            <!-- کارت ارسال کد دعوت -->
            <div class="card mt-4">
                <div class="card-header bg-success text-white">
                    ارسال کد دعوت
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    <form action="{{ route('profile.send.invitation') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="invite_email" class="form-label">ایمیل دعوت:</label>
                            <input type="email" name="invite_email" id="invite_email" class="form-control @error('invite_email') is-invalid @enderror" required>
                            @error('invite_email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary">ارسال دعوت</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection