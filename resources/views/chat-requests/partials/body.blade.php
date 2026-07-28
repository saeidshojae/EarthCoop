<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link js-chat-tab-link {{ $section === 'requests' ? 'active' : '' }}" href="{{ route('chat-requests.index', ['section' => 'requests', 'status' => $status]) }}">
            درخواست‌ها
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link js-chat-tab-link {{ $section === 'conversations' ? 'active' : '' }}" href="{{ route('chat-requests.index', ['section' => 'conversations']) }}">
            گفتگوهای خصوصی
        </a>
    </li>
</ul>

@if($section === 'requests')
    <ul class="nav nav-pills mb-4">
        <li class="nav-item">
            <a class="nav-link js-chat-tab-link {{ $status === 'pending' ? 'active' : '' }}" href="{{ route('chat-requests.index', ['section' => 'requests', 'status' => 'pending']) }}">
                در انتظار ({{ $counts['pending'] ?? 0 }})
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link js-chat-tab-link {{ $status === 'accepted' ? 'active' : '' }}" href="{{ route('chat-requests.index', ['section' => 'requests', 'status' => 'accepted']) }}">
                پذیرفته‌شده ({{ $counts['accepted'] ?? 0 }})
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link js-chat-tab-link {{ $status === 'rejected' ? 'active' : '' }}" href="{{ route('chat-requests.index', ['section' => 'requests', 'status' => 'rejected']) }}">
                ردشده ({{ $counts['rejected'] ?? 0 }})
            </a>
        </li>
    </ul>

    <div id="chat-panel-content-requests">
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">درخواست‌های دریافتی</div>
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
                    <div class="card-header">درخواست‌های ارسالی</div>
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
@else
    <div id="chat-panel-content-conversations">
        <div class="card">
            <div class="card-header">گفتگوهای خصوصی شما</div>
            <div class="card-body">
                @if($conversations->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-comments fa-2x mb-3"></i>
                        <div>هنوز گفتگوی خصوصی‌ای ایجاد نشده است.</div>
                        <div class="mt-2">درخواست‌های چت را بررسی کنید یا با کاربران جدید گفتگو را آغاز کنید.</div>
                    </div>
                @else
                    <div class="list-group">
                        @foreach($conversations as $conversation)
                            @php
                                $otherUser = $conversation->users->firstWhere('id', '!=', auth()->id());
                                $lastMessage = $conversation->messages->first();
                            @endphp
                            <a href="{{ route('private-chats.show', $conversation->id) }}" class="list-group-item list-group-item-action mb-3 text-decoration-none text-reset">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="{{ $otherUser && $otherUser->avatar ? asset('images/users/' . $otherUser->avatar) : asset('images/default-avatar.png') }}" alt="{{ $otherUser?->fullName() }}" class="rounded-circle" width="56" height="56">
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="fw-bold">{{ $otherUser?->fullName() ?? 'گفتگوی خصوصی' }}</div>
                                                <div class="text-muted small">{{ $conversation->status ?? 'فعال' }}</div>
                                            </div>
                                            <div class="text-end text-muted small">
                                                {{ $lastMessage ? $lastMessage->created_at->format('Y/m/d H:i') : 'بدون پیام' }}
                                            </div>
                                        </div>
                                        <div class="text-truncate text-muted mt-2">
                                            {{ $lastMessage ? \Illuminate\Support\Str::limit($lastMessage->message, 80) : 'هنوز پیامی ارسال نشده است.' }}
                                        </div>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
