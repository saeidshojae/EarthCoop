@extends('layouts.app')

@section('content')
<div class="container">
    <h1>مدیریت گروه: {{ $group->name }}</h1>
    <div class="mb-4">
        <h2>ویرایش اطلاعات گروه</h2>
        <form action="{{ route('admin.groups.update', $group) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label for="name">نام گروه:</label>
                <input type="text" name="name" id="name" class="form-control" value="{{ $group->name }}" required>
            </div>
            <div class="form-group">
                <label for="description">توضیحات:</label>
                <textarea name="description" id="description" class="form-control" required>{{ $group->description }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary mt-2">ذخیره تغییرات</button>
        </form>
    </div>
    <div>
        <h2>اعضای گروه</h2>
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
</div>
@endsection