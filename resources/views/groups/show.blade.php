@extends('layouts.app')

@section('content')
<div class="container">
    <h1>{{ $group->name }}</h1>
    <div class="messages">
        @foreach($messages as $message)
            <div class="message">
                <strong>{{ $message->user->name }}:</strong> {{ $message->message }}
            </div>
        @endforeach
    </div>
    <form action="{{ route('groups.messages.store', $group) }}" method="POST">
        @csrf
        <div class="form-group">
            <label for="message">پیام:</label>
            <input type="text" name="message" id="message" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">ارسال</button>
    </form>
</div>
@endsection