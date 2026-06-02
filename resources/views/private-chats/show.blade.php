@extends('layouts.unified')

@section('title', 'چت خصوصی - ' . config('app.name', 'EarthCoop'))

@vite(['resources/css/app.css', 'resources/js/app.js'])

@push('styles')
<style>
    .chat-container {
        max-width: 800px;
        margin: 0 auto;
        direction: rtl;
    }
    .chat-messages {
        max-height: 500px;
        overflow-y: auto;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 12px;
    }
    .message-bubble {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 1rem;
        animation: fadeInUp 0.3s ease;
    }
    .message-bubble.sent {
        flex-direction: row-reverse;
    }
    .message-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }
    .message-content {
        max-width: 70%;
    }
    .message-bubble.sent .message-content {
        text-align: left;
    }
    .message-bubble.received .message-content {
        background: white;
        border: 1px solid #e5e7eb;
    }
    .message-bubble.sent .message-content {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
    }
    .message-text {
        padding: 0.75rem 1rem;
        border-radius: 12px;
        font-size: 0.95rem;
        line-height: 1.5;
    }
    .message-bubble.sent .message-text {
        border-bottom-right-radius: 4px;
    }
    .message-bubble.received .message-text {
        border-bottom-left-radius: 4px;
    }
    .message-meta {
        font-size: 0.75rem;
        margin-top: 0.25rem;
        opacity: 0.7;
    }
    
    /* Message Reactions */
    .message-reactions-bar {
        display: flex;
        gap: 4px;
        margin-top: 4px;
        flex-wrap: wrap;
    }
    .message-reaction-chip {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid transparent;
        background: #f0f0f0;
    }
    .message-reaction-chip:hover {
        border-color: #10b981;
        background: #e8f5e9;
    }
    .message-reaction-chip.active {
        border-color: #10b981;
        background: #e8f5e9;
    }
    .message-reaction-chip .reaction-count {
        font-size: 0.7rem;
        color: #666;
    }
    .message-reactions-trigger {
        position: relative;
    }
    .reaction-picker {
        display: none;
        position: absolute;
        bottom: 100%;
        right: 0;
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 6px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        z-index: 100;
        gap: 2px;
    }
    .reaction-picker.show {
        display: flex;
    }
    .reaction-picker-btn {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 1.2rem;
        border: none;
        background: transparent;
    }
    .reaction-picker-btn:hover {
        background: #f0f0f0;
        transform: scale(1.2);
    }
    .message-actions-hover {
        display: none;
        position: absolute;
        top: 0;
        left: 0;
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 4px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        z-index: 50;
    }
    .message-bubble:hover .message-actions-hover {
        display: flex;
    }
    .message-bubble.sent:hover .message-actions-hover {
        right: 0;
        left: auto;
    }
    .message-action-btn {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        border: none;
        background: transparent;
        font-size: 0.9rem;
    }
    .message-action-btn:hover {
        background: #f0f0f0;
    }
    .message-reactions-summary {
        display: flex;
        gap: 4px;
        margin-top: 4px;
        flex-wrap: wrap;
    }
    .message-reaction-summary-chip {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid #e5e7eb;
        background: #f8f9fa;
    }
    .message-reaction-summary-chip:hover {
        border-color: #10b981;
        background: #e8f5e9;
    }
    .message-reaction-summary-chip.active {
        border-color: #10b981;
        background: #e8f5e9;
    }
    .message-sender {
        font-weight: 600;
        margin-bottom: 0.25rem;
        font-size: 0.85rem;
    }
    .chat-input-area {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 1rem;
        background: white;
    }
    .chat-input-area textarea {
        border: none;
        resize: none;
        outline: none;
    }
    .chat-input-area textarea:focus {
        box-shadow: none;
    }
    .send-btn {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border: none;
        border-radius: 50%;
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .send-btn:hover {
        transform: scale(1.1);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }
    .send-btn:disabled {
        opacity: 0.5;
        transform: none;
    }
    .typing-indicator {
        display: none;
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
        color: #6b7280;
    }
    .typing-indicator.active {
        display: block;
    }
    .empty-chat {
        text-align: center;
        padding: 3rem 1rem;
        color: #9ca3af;
    }
    .empty-chat i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .new-message-indicator {
        display: none;
        position: absolute;
        bottom: 80px;
        left: 50%;
        transform: translateX(-50%);
        background: #10b981;
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        z-index: 10;
    }
    .new-message-indicator.active {
        display: block;
        animation: bounce 0.5s ease;
    }
    @keyframes bounce {
        0%, 100% { transform: translateX(-50%) translateY(0); }
        50% { transform: translateX(-50%) translateY(-5px); }
    }
    .chat-header-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .chat-header-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
    }
    .chat-header-name {
        font-weight: 600;
        font-size: 1.1rem;
    }
    .chat-header-status {
        font-size: 0.85rem;
        color: #6b7280;
    }
    .chat-header-status.online {
        color: #10b981;
    }
    @media (max-width: 768px) {
        .message-content {
            max-width: 85%;
        }
        .chat-messages {
            max-height: 400px;
        }
    }
</style>
@endpush

@section('content')
<div class="chat-container">
    <!-- Chat Header -->
    <div class="card mb-3" style="border-radius: 12px; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div class="chat-header-info">
                    @foreach($conversation->users as $user)
                        @if($user->id !== auth()->id())
                            <img src="{{ $user->avatar ? asset('images/users/' . $user->avatar) : asset('images/default-avatar.png') }}" 
                                 alt="{{ $user->fullName() }}" 
                                 class="chat-header-avatar">
                            <div>
                                <div class="chat-header-name">{{ $user->fullName() }}</div>
                                <div class="chat-header-status" id="user-status">
                                    <i class="fas fa-circle text-muted" style="font-size: 8px;"></i>
                                    <span class="ms-1">در حال بررسی...</span>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
                <a href="{{ route('chat-requests.index') }}" class="btn btn-light btn-sm" style="border-radius: 8px;">
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Messages Area -->
    <div class="card mb-3" style="border-radius: 12px; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.08); position: relative;">
        <div class="chat-messages" id="chat-messages">
            @forelse($conversation->messages as $message)
                <div class="message-bubble {{ $message->sender_id === auth()->id() ? 'sent' : 'received' }}" 
                     data-message-id="{{ $message->id }}"
                     data-created-at="{{ $message->created_at->timestamp }}">
                    @if($message->sender_id !== auth()->id())
                        <img src="{{ $message->sender->avatar ? asset('images/users/' . $message->sender->avatar) : asset('images/default-avatar.png') }}" 
                             alt="{{ $message->sender->fullName() }}" 
                             class="message-avatar">
                    @endif
                    <div class="message-content">
                        @if($message->sender_id !== auth()->id())
                            <div class="message-sender">{{ $message->sender->fullName() }}</div>
                        @endif
                        <div class="message-text">{{ $message->message }}</div>
                        
                        <!-- Reactions Bar -->
                        <div class="message-reactions-summary" data-message-reactions="{{ $message->id }}">
                            @php
                                $reactionSummary = $message->reactions->groupBy('reaction_type')->map(function($group) {
                                    return [
                                        'count' => $group->count(),
                                        'users' => $group->pluck('user.full_name')->unique()->values()->toArray()
                                    ];
                                });
                            @endphp
                            @foreach($reactionSummary as $reactionType => $data)
                                @if($data['count'] > 0)
                                    <button class="message-reaction-summary-chip reaction-chip" 
                                            data-message-id="{{ $message->id }}"
                                            data-reaction="{{ $reactionType }}"
                                            title="{{ implode(', ', $data['users']) }}">
                                        <span class="reaction-emoji">{{ $reactionType }}</span>
                                        <span class="reaction-count">{{ $data['count'] }}</span>
                                    </button>
                                @endif
                            @endforeach
                        </div>
                        
                        <div class="message-meta d-flex align-items-center gap-2">
                            {{ $message->created_at->format('H:i') }}
                            <!-- Reaction Picker Trigger -->
                            <div class="message-reactions-trigger">
                                <button class="message-action-btn reaction-trigger-btn" 
                                        data-message-id="{{ $message->id }}"
                                        title="ری‌اکت">
                                    <i class="far fa-smile"></i>
                                </button>
                                <div class="reaction-picker" data-picker-for="{{ $message->id }}">
                                    @foreach(['👍', '❤️', '😂', '😮', '😢', '🔥', '👎'] as $reaction)
                                        <button class="reaction-picker-btn" 
                                                data-message-id="{{ $message->id }}"
                                                data-reaction="{{ $reaction }}"
                                                title="{{ $reaction }}">
                                            {{ $reaction }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    @if($message->sender_id === auth()->id())
                        <img src="{{ auth()->user()->avatar ? asset('images/users/' . auth()->user()->avatar) : asset('images/default-avatar.png') }}" 
                             alt="{{ auth()->user()->fullName() }}" 
                             class="message-avatar">
                    @endif
                </div>
            @empty
                <div class="empty-chat">
                    <i class="fas fa-comments"></i>
                    <p>هنوز پیامی ارسال نشده است</p>
                    <small>اولین پیام را ارسال کنید!</small>
                </div>
            @endforelse
        </div>
        
        <!-- New Message Indicator -->
        <div class="new-message-indicator" id="new-message-indicator" onclick="scrollToBottom()">
            <i class="fas fa-arrow-down"></i>
            <span id="new-message-count">0</span> پیام جدید
        </div>
    </div>

    <!-- Typing Indicator -->
    <div class="typing-indicator" id="typing-indicator">
        <i class="fas fa-spinner fa-spin"></i>
        <span class="ms-1">در حال نوشتن...</span>
    </div>

    <!-- Chat Input Area -->
    <div class="chat-input-area">
        <form id="chat-form" action="{{ route('private-chats.send', $conversation->id) }}" method="POST">
            @csrf
            <div class="d-flex gap-2 align-items-end">
                <div class="flex-grow-1">
                    <textarea name="message" 
                              id="message-input"
                              class="form-control" 
                              rows="2" 
                              placeholder="پیام خود را بنویسید..."
                              required>{{ old('message') }}</textarea>
                    @error('message')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
                <button type="submit" class="send-btn" id="send-btn">
                    <i class="fas fa-paper-plane" style="color: white;"></i>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatMessages = document.getElementById('chat-messages');
    const chatForm = document.getElementById('chat-form');
    const messageInput = document.getElementById('message-input');
    const sendBtn = document.getElementById('send-btn');
    const typingIndicator = document.getElementById('typing-indicator');
    const newMessageIndicator = document.getElementById('new-message-indicator');
    const newMessageCount = document.getElementById('new-message-count');
    
    const conversationId = {{ $conversation->id }};
    const currentUserId = {{ auth()->id() }};
    
    let lastMessageId = {{ $conversation->messages->max('id') ?? 0 }};
    let newMessagesCount = 0;
    let isAtBottom = true;
    let pollingInterval = null;
    
    // Scroll to bottom on load
    scrollToBottom();
    
    // Check if user is at bottom
    chatMessages.addEventListener('scroll', function() {
        const threshold = 100;
        isAtBottom = (chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight) < threshold;
        
        if (isAtBottom && newMessagesCount > 0) {
            newMessageIndicator.classList.remove('active');
            newMessagesCount = 0;
        }
    });
    
    // Form submission with AJAX
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const message = messageInput.value.trim();
        if (!message) return;
        
        sendBtn.disabled = true;
        
        fetch('{{ route('private-chats.send', $conversation->id) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ message: message })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Add message to chat
                addMessageToChat(data.message, true);
                
                // Clear input
                messageInput.value = '';
                
                // Scroll to bottom
                scrollToBottom();
                
                // Reset last message ID
                lastMessageId = data.message.id;
            }
        })
        .catch(error => {
            console.error('Error sending message:', error);
            alert('خطا در ارسال پیام. لطفاً دوباره تلاش کنید.');
        })
        .finally(() => {
            sendBtn.disabled = false;
            messageInput.focus();
        });
    });
    
    // Add message to chat UI
    function addMessageToChat(messageData, isSent) {
        // Remove empty chat message
        const emptyChat = chatMessages.querySelector('.empty-chat');
        if (emptyChat) {
            emptyChat.remove();
        }
        
        const messageEl = document.createElement('div');
        messageEl.className = `message-bubble ${isSent ? 'sent' : 'received'}`;
        messageEl.dataset.messageId = messageData.id;
        messageEl.dataset.createdAt = new Date(messageData.created_at).getTime() / 1000;
        
        const avatarUrl = messageData.sender.avatar 
            ? `{{ asset('images/users') }}/${messageData.sender.avatar}` 
            : '{{ asset('images/default-avatar.png') }}';
        
        messageEl.innerHTML = `
            ${!isSent ? `<img src="${avatarUrl}" alt="${messageData.sender.name}" class="message-avatar">` : ''}
            <div class="message-content">
                ${!isSent ? `<div class="message-sender">${messageData.sender.name}</div>` : ''}
                <div class="message-text">${escapeHtml(messageData.message)}</div>
                <div class="message-meta">${formatTime(messageData.created_at)}</div>
            </div>
            ${isSent ? `<img src="${avatarUrl}" alt="${messageData.sender.name}" class="message-avatar">` : ''}
        `;
        
        chatMessages.appendChild(messageEl);
        
        // Auto scroll if at bottom
        if (isAtBottom) {
            scrollToBottom();
        } else {
            newMessagesCount++;
            newMessageCount.textContent = newMessagesCount;
            newMessageIndicator.classList.add('active');
        }
    }
    
    // Poll for new messages
    function startPolling() {
        pollingInterval = setInterval(function() {
            fetch(`{{ route('private-chats.messages', $conversation->id) }}?after_id=${lastMessageId}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(function(msg) {
                        if (msg.id > lastMessageId) {
                            lastMessageId = msg.id;
                        }
                        addMessageToChat(msg, msg.sender.id === currentUserId);
                    });
                }
            })
            .catch(error => {
                console.error('Error polling messages:', error);
            });
        }, 3000); // Poll every 3 seconds
    }
    
    // Start polling
    startPolling();
    
    // Stop polling when leaving page
    window.addEventListener('beforeunload', function() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
        }
    });
    
    // Helper functions
    function scrollToBottom() {
        chatMessages.scrollTo({
            top: chatMessages.scrollHeight,
            behavior: 'smooth'
        });
        newMessageIndicator.classList.remove('active');
        newMessagesCount = 0;
    }
    
    function formatTime(timestamp) {
        const date = new Date(timestamp);
        return date.getHours().toString().padStart(2, '0') + ':' + 
               date.getMinutes().toString().padStart(2, '0');
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Auto-resize textarea
    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
    
    // Enter key to send (Shift+Enter for new line)
    messageInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            chatForm.dispatchEvent(new Event('submit'));
        }
    });
    
    // ========== Message Reactions ==========
    const REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🔥', '👎'];
    
    // Toggle reaction picker visibility
    document.addEventListener('click', function(e) {
        // Reaction picker toggle
        const triggerBtn = e.target.closest('.reaction-trigger-btn');
        if (triggerBtn) {
            e.stopPropagation();
            const messageId = triggerBtn.dataset.messageId;
            const picker = document.querySelector(`.reaction-picker[data-picker-for="${messageId}"]`);
            
            // Close all other pickers
            document.querySelectorAll('.reaction-picker.show').forEach(p => {
                if (p !== picker) p.classList.remove('show');
            });
            
            picker.classList.toggle('show');
            return;
        }
        
        // Close picker when clicking outside
        if (!e.target.closest('.reaction-picker') && !e.target.closest('.reaction-trigger-btn')) {
            document.querySelectorAll('.reaction-picker.show').forEach(p => p.classList.remove('show'));
        }
    });
    
    // Reaction picker button click
    document.addEventListener('click', function(e) {
        const reactionBtn = e.target.closest('.reaction-picker-btn');
        if (reactionBtn) {
            e.stopPropagation();
            const messageId = reactionBtn.dataset.messageId;
            const reactionType = reactionBtn.dataset.reaction;
            toggleReaction(messageId, reactionType);
            
            // Close picker
            document.querySelectorAll('.reaction-picker.show').forEach(p => p.classList.remove('show'));
            return;
        }
        
        // Existing reaction chip click (toggle)
        const chipBtn = e.target.closest('.reaction-chip');
        if (chipBtn) {
            const messageId = chipBtn.dataset.messageId;
            const reactionType = chipBtn.dataset.reaction;
            toggleReaction(messageId, reactionType);
        }
    });
    
    // Toggle reaction on message
    function toggleReaction(messageId, reactionType) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        
        // Check if user already has this reaction
        const existingChip = document.querySelector(`.reaction-chip[data-message-id="${messageId}"][data-reaction="${reactionType}"]`);
        const userHasReaction = existingChip !== null;
        
        fetch('{{ route('messages.reactions.store') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                message_id: messageId,
                reaction_type: reactionType
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateReactionUI(messageId, data.reactions);
            }
        })
        .catch(error => {
            console.error('Error toggling reaction:', error);
        });
    }
    
    // Update reaction UI for a message
    function updateReactionUI(messageId, reactions) {
        const container = document.querySelector(`.message-reactions-summary[data-message-reactions="${messageId}"]`);
        if (!container) return;
        
        container.innerHTML = '';
        
        if (Object.keys(reactions).length === 0) return;
        
        Object.entries(reactions).forEach(([reactionType, data]) => {
            if (data.count > 0) {
                const chip = document.createElement('button');
                chip.className = 'message-reaction-summary-chip reaction-chip';
                chip.dataset.messageId = messageId;
                chip.dataset.reaction = reactionType;
                chip.title = data.users.join(', ');
                chip.innerHTML = `
                    <span class="reaction-emoji">${reactionType}</span>
                    <span class="reaction-count">${data.count}</span>
                `;
                container.appendChild(chip);
            }
        });
    }
    
    // Update reaction UI for dynamically added messages
    window.updateMessageReactions = function(messageEl, reactions) {
        const messageId = messageEl.dataset.messageId;
        let container = messageEl.querySelector('.message-reactions-summary');
        
        if (!container) {
            // Create container if it doesn't exist (for AJAX added messages)
            const metaEl = messageEl.querySelector('.message-meta');
            container = document.createElement('div');
            container.className = 'message-reactions-summary';
            container.dataset.messageReactions = messageId;
            metaEl.parentNode.insertBefore(container, metaEl);
        }
        
        updateReactionUI(messageId, reactions || {});
    };
});
</script>
@endpush