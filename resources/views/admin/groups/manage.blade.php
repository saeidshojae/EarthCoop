@extends('layouts.app')

@section('content')
<div class="container">
    <h1>مدیریت گروه: {{ $group->name }}</h1>
    <table class="table">
        <thead>
            <tr>
                <th>نام کاربر</th>
                <th>نقش</th>
                <th>عملیات</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->pivot->role }}</td>
                    <td>
                        <form action="{{ route('admin.groups.updateRole', [$group, $user]) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <select name="role" class="form-select">
                                <option value="active" {{ $user->pivot->role == 'active' ? 'selected' : '' }}>فعال</option>
                                <option value="observer" {{ $user->pivot->role == 'observer' ? 'selected' : '' }}>ناظر</option>
                            </select>
                            <button type="submit" class="btn btn-primary mt-2">به‌روزرسانی</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection