@php
    $userId = $user->id;
    $currentUserId = auth()->id();
    $managerCard = (bool) ($manager_card ?? false);
    $managerInbox = (bool) ($manager_inbox ?? false);
@endphp

@if(auth()->check())
    @if((int) auth()->id() === (int) $user->id)
        <div class="{{ $managerInbox ? 'manager-inbox' : 'card mb-3' }}">
            @unless($managerInbox)
                <div class="card-header">درخواست‌های گفتگو</div>
            @endunless
            <div class="{{ $managerInbox ? 'manager-inbox__body' : 'card-body' }}">
                @if($chatRequests->isNotEmpty())
                    <div class="{{ $managerInbox ? 'manager-inbox__list' : 'list-group' }}">
                        @foreach($chatRequests as $request)
                            <article class="{{ $managerInbox ? 'manager-inbox__item' : 'list-group-item' }}">
                                @if($request->request_to_group !== null)
                                    <label>درخواست به گروه شما</label>
                                @endif
                                <div class="manager-inbox__layout">
                                    <div class="manager-inbox__sender">
                                        <h6 class="mb-1">{{ $request->sender->fullName() }}</h6>
                                        <small class="text-muted">{{ verta($request->created_at)->format('Y-m-d H:i') }}</small>
                                    </div>
                                    <div class="manager-inbox__message">
                                        <span>پیام درخواست</span>
                                        <p>{{ $request->message }}</p>
                                    </div>
                                    <div class="manager-inbox__actions">
                                        @if((int) auth()->id() === (int) $request->receiver_id || ($managerInbox && ($yourRole ?? 0) == 3))
                                            <form action="{{ route('chat-requests.accept', $request->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check"></i> پذیرفتن
                                                </button>
                                            </form>
                                            <form action="{{ route('chat-requests.reject', $request->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-secondary btn-sm">رد کردن</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="manager-inbox__empty">
                        <i class="fas fa-inbox"></i>
                        <strong>درخواست تازه‌ای ندارید</strong>
                        <span>درخواست‌های خطاب‌شده به مدیران این گروه اینجا نمایش داده می‌شوند.</span>
                    </div>
                @endif
            </div>
        </div>
    @elseif((int) auth()->id() !== (int) $user->id)
        @php
            // Manager cards can render this partial many times. Cache the request
            // relationship map once per HTTP request to avoid an N+1 query pattern.
            $requestAttributeKey = 'group_chat.chat_request_counterparty_map';
            $chatRequestCounterpartyMap = request()->attributes->get($requestAttributeKey);

            if (!is_array($chatRequestCounterpartyMap)) {
                $chatRequestCounterpartyMap = [];
                $allCurrentUserRequests = \App\Models\ChatRequest::query()
                    ->where(function ($query) use ($currentUserId) {
                        $query->where('sender_id', $currentUserId)
                            ->orWhere('receiver_id', $currentUserId);
                    })
                    ->orderByDesc('id')
                    ->get();

                foreach ($allCurrentUserRequests as $candidateRequest) {
                    $counterpartyId = (int) ($candidateRequest->sender_id == $currentUserId
                        ? $candidateRequest->receiver_id
                        : $candidateRequest->sender_id);

                    // Keep the latest relationship state for each pair. Rejected
                    // requests are reused by the controller when a user retries.
                    if (!array_key_exists($counterpartyId, $chatRequestCounterpartyMap)) {
                        $chatRequestCounterpartyMap[$counterpartyId] = $candidateRequest;
                    }
                }

                request()->attributes->set($requestAttributeKey, $chatRequestCounterpartyMap);
            }

            $existingRequest = $chatRequestCounterpartyMap[(int) $user->id] ?? null;
        @endphp

        @if(!$managerCard && !isset($request_to_group))
            @include('chat-requests.partials.profile-action', [
                'user' => $user,
                'existingRequest' => $existingRequest,
            ])
        @else
            <div class="chat-request manager-request-card__action">
                @if(!$existingRequest)
                    <form action="{{ route('chat-requests.send', $user->id) }}" method="POST" class="manager-request-form">
                        @csrf
                        @if(isset($request_to_group))
                            <input type="hidden" name="request_to_group" value="{{ $request_to_group }}">
                        @endif
                        <div class="manager-request-form__field">
                            <label for="manager-request-description-{{ $user->id }}-{{ $request_to_group ?? 0 }}">پیام درخواست</label>
                            <textarea id="manager-request-description-{{ $user->id }}-{{ $request_to_group ?? 0 }}"
                                      class="form-control"
                                      placeholder="دلیل یا موضوع گفت‌وگو را کوتاه بنویسید…"
                                      name="description"
                                      rows="2"
                                      maxlength="5000"
                                      required>{{ old('description') }}</textarea>
                            @error('description')
                                <span>{{ $message }}</span>
                            @enderror
                        </div>
                        <button type="submit" class="manager-request-form__submit">
                            <i class="fas fa-comment-dots"></i><span>ارسال درخواست</span>
                        </button>
                    </form>
                @elseif($existingRequest->status === 'pending')
                    @if((int) $existingRequest->receiver_id === (int) auth()->id())
                        <div>
                            <label>توضیحات کاربر:</label>
                            <p>{{ $existingRequest->message }}</p>
                        </div>
                        <div class="d-flex gap-2">
                            <form action="{{ route('chat-requests.accept', $existingRequest->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> پذیرفتن</button>
                            </form>
                            <form action="{{ route('chat-requests.reject', $existingRequest->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary">رد کردن</button>
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
                            <i class="fas fa-comments"></i> ورود به گفتگو
                        </a>
                    @elseif($existingRequest->group_id)
                        <a href="{{ route('groups.chat', $existingRequest->group_id) }}" class="btn btn-success">
                            <i class="fas fa-comments"></i> ورود به گفتگو
                        </a>
                    @endif
                @else
                    <div class="alert alert-secondary mb-0">
                        درخواست قبلی رد شده است. برای درخواست دوباره از پروفایل عضو استفاده کنید.
                    </div>
                @endif
            </div>
        @endif
    @endif
@endif
