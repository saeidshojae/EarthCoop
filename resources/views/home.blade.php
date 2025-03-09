@extends('layouts.app')

@section('content')
<!DOCTYPE html>
<html lang="fa">
<head>
  <meta charset="UTF-8">
  <title>صفحه اصلی</title>
  <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
  <h1 class="mb-4">گروه‌های عضویت شما</h1>
  @if($groups->isNotEmpty())
    <ul class="list-group">
      @foreach($groups as $group)
        <li class="list-group-item">{{ $group->name }}</li>
      @endforeach
    </ul>
  @else
    <p>گروهی یافت نشد.</p>
  @endif
</div>
</body>
</html>

@endsection
