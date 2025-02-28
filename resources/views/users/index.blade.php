// filepath: /c:/Users/saeed/EarthCoop/EarthCoop/resources/views/users/index.blade.php
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('لیست کاربران') }}</div>

                <div class="card-body">
                    <a href="{{ route('users.create') }}" class="btn btn-primary mb-3">ایجاد کاربر جدید</a>
                    
                    <!-- نمایش پیام‌های موفقیت یا خطا -->
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <!-- جدول نمایش کاربران -->
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>نام</th>
                                <th>ایمیل</th>
                                <th>تلفن</th>
                                <th>رسته صنفی</th>
                                <th>تخصص</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $user)
                                <tr>
                                    <td>{{ $user->first_name }} {{ $user->last_name }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>{{ $user->phone }}</td>
                                    <td>{{ $user->jobFields->pluck('title')->join(', ') }}</td>
                                    <td>{{ $user->specializations->pluck('title')->join(', ') }}</td>
                                    <td>
                                        <a href="{{ route('users.edit', $user->id) }}" class="btn btn-warning btn-sm">ویرایش</a>
                                        <form action="{{ route('users.destroy', $user->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('آیا مطمئن هستید؟')">حذف</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center">هیچ کاربری یافت نشد.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection