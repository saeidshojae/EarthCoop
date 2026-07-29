@extends('layouts.unified')

@section('title', 'چت‌های خصوصی - ' . config('app.name', 'EarthCoop'))

@push('styles')
<style>
    .private-chat-list {
        max-width: 940px;
        margin: 0 auto;
    }
    .private-chat-card {
        border-radius: 16px;
        border: 1px solid rgba(0, 0, 0, 0.08);
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .private-chat-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
    }
    .private-chat-item {
        display: grid;
        grid-template-columns: auto 1fr auto;
        gap: 1rem;
        align-items: center;
    }
    .private-chat-avatar {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #e5e7eb;
    }
    .private-chat-meta {
        min-width: 0;
    }
    .private-chat-name {
        margin-bottom: 0.25rem;
        font-size: 1rem;
        font-weight: 600;
    }
    .private-chat-message {
        color: #6b7280;
        font-size: 0.94rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .private-chat-footer {
        text-align: right;
    }
    .private-chat-time {
        font-size: 0.82rem;
        color: #9ca3af;
    }
    .private-chat-empty {
        text-align: center;
        padding: 4rem 1rem;
        color: #6b7280;
    }
</style>
@endpush

@section('content')
<div class="private-chat-list">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-1">چت‌های خصوصی</h1>
            <p class="text-muted mb-0">تمام گفتگوهای خصوصی شما در یک مکان.</p>
        </div>
        <a href="{{ route('chat-requests.index') }}" class="btn btn-outline-primary btn-sm">
            درخواست‌های جدید
        </a>
    </div>

    @if($conversations->isEmpty())
        <div class="card private-chat-card p-5 private-chat-empty">
            <i class="fas fa-comments fa-3x mb-3" style="color: #a5b4fc;"></i>
            <h2 class="h5">هنوز گفتگو خصوصی‌ای ندارید</h2>
            <p class="mb-4">برای شروع گفتگو، می‌توانید درخواست چت جدید ارسال کنید یا از فهرست درخواست‌های دریافت‌شده استفاده کنید.</p>
            <a href="{{ route('chat-requests.index') }}" class="btn btn-primary">
                مشاهده درخواست‌ها
            </a>
        </div>
    @else
        <div class="list-group">
            @foreach($conversations as $conversation)
                @php
                    $otherUser = $conversation->users->firstWhere('id', '!=', auth()->id());
                    $lastMessage = $conversation->messages->first();
                @endphp
                <a href="{{ route('private-chats.show', $conversation->id) }}" class="list-group-item list-group-item-action private-chat-card mb-3 text-decoration-none text-reset">
                    <div class="private-chat-item p-3">
                        <img src="{{ $otherUser && $otherUser->avatar ? asset('images/users/' . $otherUser->avatar) : asset('images/default-avatar.png') }}" alt="{{ $otherUser?->fullName() }}" class="private-chat-avatar">
                        <div class="private-chat-meta">
                            <div class="private-chat-name">{{ $otherUser?->fullName() ?? 'گفتگوی خصوصی' }}</div>
                            <div class="private-chat-message">
                                {{ $lastMessage ? \Illuminate\Support\Str::limit($lastMessage->message, 80) : 'هنوز پیامی ارسال نشده است.' }}
                            </div>
                        </div>
                        <div class="private-chat-footer">
                            @if($lastMessage)
                                <div class="private-chat-time">{{ $lastMessage->created_at->format('Y/m/d H:i') }}</div>
                            @else
                                <div class="private-chat-time">در انتظار پیام</div>
                            @endif
                            <span class="badge bg-light text-dark mt-2">{{ $conversation->status ?? 'فعال' }}</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
