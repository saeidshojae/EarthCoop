@php
    $userId = $user->id; // ID کاربر مورد نظر
    $currentUserId = auth()->user()->id; // ID کاربر فعلی
@endphp
<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
@if(auth()->check())
    <!-- نمایش درخواست‌های در انتظار -->
    @if(auth()->user()->id === $user->id)
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                درخواست‌های چت
            </div>
            <div class="card-body">
                @if($chatRequests->isNotEmpty())
                    <div class="list-group">
                        @foreach($chatRequests as $request)
                            <div class="list-group-item">
                                @if($request->request_to_group != null)
                                    <label>درخواست به گروه شما</label>
                                @endif
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">{{ $request->sender->fullName() }}</h6>
                                        <small class="text-muted">{{ verta($request->created_at)->format('Y-m-d H:i') }}</small>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <label>توضیحات کاربر: </label>
                                        <p>{{ $request->message }}</p>
                                        
                                        @if(auth()->user()->id == $request->receiver_id)
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
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle"></i> درخواست چت جدیدی وجود ندارد
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

        <div class="chat-request mb-3" style='    display: flex;
    justify-content: center;    flex-direction: column;
    align-items: center;'>
            @if(!$existingRequest)
                <form action="{{ route('chat-requests.send', $user->id) }}" method="POST" class="d-inline" style='width: 100%'>
                    @csrf
                    
                    @if(isset($request_to_group))
                        <input type='hidden' name='request_to_group' value='{{ $request_to_group }}'>
                    @endif
                    <div class='form-grpoup'>
                        <label>توضیحات خود را وارد کنید</label>
                        <textarea class='form-control' placeholder='توضیحات' name='description'>{{ old('description') }}</textarea>
                        @error('description')
                        <span>{{ $message }}</span>
                        @enderror
                    </div><br>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-comment"></i> درخواست چت
                    </button>
                </form>
            @elseif($existingRequest->status === 'pending')
                @if($existingRequest->receiver_id === auth()->user()->id)
                  <div><label>توضیحات کاربر: </label>
                                        <p>{{ $existingRequest->message }}</p></div>
                                        
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
                <a href="{{ route('groups.chat', $existingRequest->group_id) }}" class="btn btn-success">
                    <i class="fas fa-comments"></i> ورود به چت
                </a>
            @else
                <div class="alert alert-danger mb-0">
                    <i class="fas fa-ban"></i> درخواست چت رد شده است
                </div>
            @endif
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif
@endif

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