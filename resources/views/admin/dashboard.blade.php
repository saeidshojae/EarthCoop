@extends('layouts.app')

@section('content')
<div class="container">
    <h1>پنل مدیریت</h1>
    <ul class="list-group">
        <li class="list-group-item">
            <a href="{{ route('admin.groups.index') }}">مدیریت گروه‌ها</a>
        </li>
        <li class="list-group-item">
            <a href="{{ route('admin.invitation_codes.index') }}">مدیریت کدهای دعوت</a>
        </li>
        <!-- لینک‌های دیگر مدیریت -->
    </ul>
</div>
@endsection