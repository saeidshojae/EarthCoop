@extends('layouts.app')

@section('content')
<div class="container">
    <h1>مدیریت کدهای دعوت</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ route('admin.invitation_codes.store') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label for="code" class="form-label">کد دعوت:</label>
            <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror" required>
            @error('code')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <button type="submit" class="btn btn-primary">ایجاد کد دعوت</button>
    </form>

    <h2 class="mt-5">لیست کدهای دعوت</h2>
    <table class="table">
        <thead>
            <tr>
                <th>کد</th>
                <th>وضعیت</th>
                <th>تاریخ ایجاد</th>
            </tr>
        </thead>
        <tbody>
            @foreach($codes as $code)
                <tr>
                    <td>{{ $code->code }}</td>
                    <td>{{ $code->used ? 'استفاده شده' : 'استفاده نشده' }}</td>
                    <td>{{ $code->created_at }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection