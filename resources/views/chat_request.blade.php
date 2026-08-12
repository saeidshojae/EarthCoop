@php
    $userId = $user->id; // ID کاربر مورد نظر
    $currentUserId = auth()->user()->id; // ID کاربر فعلی
    $managerCard = (bool) ($manager_card ?? false);
    $managerInbox = (bool) ($manager_inbox ?? false);
@endphp

@once
    @push('styles')
    <style>
        .chat-request {
            margin: 10px 0;
        }

        .pending-request {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
        }

        .list-group-item {
            border: 1px solid rgba(0,0,0,.125);
            margin-bottom: 5px;
            border-radius: 4px;
        }

        .list-group-item:last-child {
            margin-bottom: 0;
        }
    </style>
    @endpush
@endonce

@if(auth()->check())
    <!-- نمایش درخواست‌های در انتظار -->
    @if(auth()->user()->id === $user->id)
        <div class="{{ $managerInbox ? 'manager-inbox' : 'card mb-3' }}">
            @unless($managerInbox)<div class="card-header bg-primary text-white">درخواست‌های چت</div>@endunless
            <div class="{{ $managerInbox ? 'manager-inbox__body' : 'card-body' }}">
                @if($chatRequests->isNotEmpty())
                    <div class="{{ $managerInbox ? 'manager-inbox__list' : 'list-group' }}">
                        @foreach($chatRequests as $request)
                            <article class="{{ $managerInbox ? 'manager-inbox__item' : 'list-group-item' }}">
                                @if($request->request_to_group != null)
                                    <label>درخواست به گروه شما</label>
                                @endif
                                <div class="manager-inbox__layout">
                                    <div class="manager-inbox__sender">
                                        <h6 class="mb-1">{{ $request->sender->fullName() }}</h6>
                                        <small class="text-muted">{{ verta($request->created_at)->format('Y-m-d H:i') }}</small>
                                    </div>
                                    <div class="manager-inbox__message">
                                        <span>پیام درخواست</span><p>{{ $request->message }}</p>
                                    </div>
                                    <div class="manager-inbox__actions">
                                        @if(auth()->user()->id == $request->receiver_id || ($managerInbox && ($yourRole ?? 0) == 3))
                                            <form action="{{ route('chat-requests.accept', $request->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check"></i> پذیرفتن
                                                </button>
                                            </form>
                                            <form action="{{ route('chat-requests.reject', $request->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-times"></i> رد کردن
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="manager-inbox__empty">
                        <i class="fas fa-inbox"></i><strong>درخواست تازه‌ای ندارید</strong><span>درخواست‌های خطاب‌شده به مدیران این گروه اینجا نمایش داده می‌شوند.</span>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- دکمه ارسال درخواست چت -->
    @if(auth()->user()->id !== $user->id)
        @php
            $existingRequest = \App\Models\ChatRequest::where(function($query) use ($user) {
                $query->where('sender_id', auth()->user()->id)
                      ->where('receiver_id', $user->id);
            })->orWhere(function($query) use ($user) {
                $query->where('sender_id', $user->id)
                      ->where('receiver_id', auth()->user()->id);
            })->first();
        @endphp

        <div class="chat-request {{ $managerCard ? 'manager-request-card__action' : 'mb-3' }}">
            @if(!$existingRequest)
                <form action="{{ route('chat-requests.send', $user->id) }}" method="POST" class="manager-request-form">
                    @csrf
                    @if(isset($request_to_group))
                        <input type="hidden" name="request_to_group" value="{{ $request_to_group }}">
                    @endif
                    <div class="manager-request-form__field">
                        <label for="manager-request-description-{{ $user->id }}-{{ $request_to_group ?? 0 }}">پیام درخواست</label>
                        <textarea id="manager-request-description-{{ $user->id }}-{{ $request_to_group ?? 0 }}" class="form-control" placeholder="دلیل یا موضوع گفت‌وگو را کوتاه بنویسید…" name="description" rows="2">{{ old('description') }}</textarea>
                        @error('description')
                            <span>{{ $message }}</span>
                        @enderror
                    </div>
                    <button type="submit" class="manager-request-form__submit">
                        <i class="fas fa-comment-dots"></i><span>ارسال درخواست</span>
                    </button>
                </form>
            @elseif($existingRequest->status === 'pending')
                @if($existingRequest->receiver_id === auth()->user()->id)
                    <div>
                        <label>توضیحات کاربر: </label>
                        <p>{{ $existingRequest->message }}</p>
                    </div>
                    <div class="d-flex gap-2">
                        <form action="{{ route('chat-requests.accept', $existingRequest->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check"></i> پذیرفتن
                            </button>
                        </form>
                        <form action="{{ route('chat-requests.reject', $existingRequest->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-times"></i> رد کردن
                            </button>
                        </form>
                    </div>
                @else
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-clock"></i> درخواست شما در حال انتظار است
                    </div>
                @endif
            @elseif($existingRequest->status === 'accepted')
                @if($existingRequest->private_conversation_id)
                    <a href="{{ route('private-chats.show', $existingRequest->private_conversation_id) }}" class="btn btn-success">
                        <i class="fas fa-comments"></i> ورود به چت
                    </a>
                @elseif($existingRequest->group_id)
                    <a href="{{ route('groups.chat', $existingRequest->group_id) }}" class="btn btn-success">
                        <i class="fas fa-comments"></i> ورود به چت
                    </a>
                @endif
            @else
                <div class="alert alert-danger mb-0">
                    <i class="fas fa-ban"></i> درخواست چت رد شده است
                </div>
            @endif
        </div>
    @endif

@endif
