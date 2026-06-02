@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">چت خصوصی</h4>
        <a href="{{ route('chat-requests.index') }}" class="btn btn-outline-secondary btn-sm">بازگشت</a>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <strong>شرکت‌کنندگان:</strong>
            @foreach($conversation->users as $u)
                <span class="me-2">{{ trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) }}</span>
            @endforeach
        </div>
        <div class="card-body" style="max-height: 420px; overflow-y: auto;">
            @forelse($conversation->messages as $message)
                <div class="mb-2 p-2 border rounded">
                    <div class="small text-muted mb-1">
                        {{ trim(($message->sender->first_name ?? '') . ' ' . ($message->sender->last_name ?? '')) }}
                        <span class="ms-2">{{ $message->created_at }}</span>
                    </div>
                    <div>{{ $message->message }}</div>
                </div>
            @empty
                <div class="text-muted">پیامی ثبت نشده است.</div>
            @endforelse
        </div>
    </div>

    <form action="{{ route('private-chats.send', $conversation->id) }}" method="POST">
        @csrf
        <div class="mb-2">
            <textarea name="message" class="form-control" rows="3" placeholder="پیام خود را بنویسید...">{{ old('message') }}</textarea>
            @error('message')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>
        <button type="submit" class="btn btn-primary">ارسال</button>
    </form>
</div>
@endsection
