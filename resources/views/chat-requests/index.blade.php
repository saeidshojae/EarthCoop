@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h4 class="mb-0">درخواست‌های چت خصوصی</h4>
        <a href="{{ route('profile.show') }}" class="btn btn-outline-secondary btn-sm">بازگشت به پروفایل</a>
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'pending' ? 'active' : '' }}" href="{{ route('chat-requests.index', ['tab' => 'pending']) }}">
                در انتظار ({{ $counts['pending'] ?? 0 }})
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'accepted' ? 'active' : '' }}" href="{{ route('chat-requests.index', ['tab' => 'accepted']) }}">
                پذیرفته‌شده ({{ $counts['accepted'] ?? 0 }})
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'rejected' ? 'active' : '' }}" href="{{ route('chat-requests.index', ['tab' => 'rejected']) }}">
                ردشده ({{ $counts['rejected'] ?? 0 }})
            </a>
        </li>
    </ul>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">دریافتی</div>
                <div class="card-body">
                    @forelse($received as $requestItem)
                        <div class="border rounded p-3 mb-2">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="fw-bold">{{ $requestItem->sender?->fullName() }}</div>
                                    <small class="text-muted">{{ $requestItem->created_at }}</small>
                                </div>
                                @if($requestItem->status === 'pending')
                                    <div class="d-flex gap-2">
                                        <form action="{{ route('chat-requests.accept', $requestItem->id) }}" method="POST">
                                            @csrf
                                            <button class="btn btn-success btn-sm" type="submit">پذیرفتن</button>
                                        </form>
                                        <form action="{{ route('chat-requests.reject', $requestItem->id) }}" method="POST">
                                            @csrf
                                            <button class="btn btn-danger btn-sm" type="submit">رد کردن</button>
                                        </form>
                                    </div>
                                @elseif($requestItem->status === 'accepted' && $requestItem->private_conversation_id)
                                    <a href="{{ route('private-chats.show', $requestItem->private_conversation_id) }}" class="btn btn-primary btn-sm">ورود به چت</a>
                                @endif
                            </div>
                            @if($requestItem->message)
                                <div class="mt-2">{{ $requestItem->message }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="text-muted">در این بخش درخواست دریافتی وجود ندارد.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">ارسالی</div>
                <div class="card-body">
                    @forelse($sent as $requestItem)
                        <div class="border rounded p-3 mb-2">
                            <div class="fw-bold">{{ $requestItem->receiver?->fullName() }}</div>
                            <small class="text-muted d-block">{{ $requestItem->created_at }}</small>
                            @if($requestItem->message)
                                <div class="mt-2">{{ $requestItem->message }}</div>
                            @endif
                            @if($requestItem->status === 'accepted' && $requestItem->private_conversation_id)
                                <a href="{{ route('private-chats.show', $requestItem->private_conversation_id) }}" class="btn btn-primary btn-sm mt-2">ورود به چت</a>
                            @endif
                        </div>
                    @empty
                        <div class="text-muted">در این بخش درخواست ارسالی وجود ندارد.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
