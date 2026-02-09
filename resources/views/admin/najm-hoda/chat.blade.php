@extends('layouts.admin')

@section('title', 'چت با نجم‌هدا - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'چت با نجم‌هدا')
@section('page-description', 'دستیار هوشمند نجم‌هدا')

@push('styles')
<style>
    .chat-container {
        display: flex;
        flex-direction: column;
        height: calc(100vh - 250px);
        min-height: 600px;
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        overflow: hidden;
    }
    
    .chat-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
    }
    
    .chat-header h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .chat-header select {
        background: white;
        border: none;
        border-radius: 8px;
        padding: 0.5rem 1rem;
        color: #1e293b;
        font-weight: 600;
        cursor: pointer;
    }
    
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 1.5rem;
        background: #f8fafc;
        direction: rtl;
    }
    
    .chat-messages::-webkit-scrollbar {
        width: 8px;
    }
    
    .chat-messages::-webkit-scrollbar-track {
        background: #e5e7eb;
        border-radius: 4px;
    }
    
    .chat-messages::-webkit-scrollbar-thumb {
        background: #9ca3af;
        border-radius: 4px;
    }
    
    .message {
        margin-bottom: 1.5rem;
        animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .message-bot {
        display: flex;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .message-user {
        display: flex;
        flex-direction: row-reverse;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .message-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }
    
    .message-bot .message-avatar {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .message-user .message-avatar {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
        color: white;
    }
    
    .message-content {
        flex: 1;
        max-width: 70%;
    }
    
    .message-bubble {
        padding: 1rem 1.25rem;
        border-radius: 16px;
        line-height: 1.6;
        word-wrap: break-word;
    }
    
    .message-bot .message-bubble {
        background: white;
        color: #1e293b;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border-top-right-radius: 4px;
    }
    
    .message-user .message-bubble {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
        border-top-left-radius: 4px;
    }
    
    .message-name {
        font-weight: 700;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }
    
    .message-text {
        font-size: 0.9375rem;
    }
    
    .message-time {
        font-size: 0.75rem;
        color: #9ca3af;
        margin-top: 0.5rem;
        text-align: left;
    }
    
    .message-user .message-time {
        text-align: right;
        color: rgba(255, 255, 255, 0.8);
    }
    
    .typing-indicator {
        display: inline-flex;
        gap: 0.5rem;
        align-items: center;
        padding: 1rem 1.25rem;
        background: white;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    .typing-indicator span {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #9ca3af;
        animation: typing 1.4s infinite;
    }
    
    .typing-indicator span:nth-child(2) {
        animation-delay: 0.2s;
    }
    
    .typing-indicator span:nth-child(3) {
        animation-delay: 0.4s;
    }
    
    @keyframes typing {
        0%, 60%, 100% {
            transform: translateY(0);
            opacity: 0.7;
        }
        30% {
            transform: translateY(-10px);
            opacity: 1;
        }
    }
    
    .chat-footer {
        padding: 1.5rem;
        background: white;
        border-top: 1px solid #e5e7eb;
        flex-shrink: 0;
    }
    
    .chat-input-form {
        display: flex;
        gap: 1rem;
        align-items: flex-end;
    }
    
    .chat-input {
        flex: 1;
        padding: 0.875rem 1.25rem;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-size: 0.9375rem;
        transition: all 0.3s ease;
        resize: none;
        min-height: 50px;
        max-height: 150px;
        direction: rtl;
    }
    
    .chat-input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .chat-send-btn {
        padding: 0.875rem 2rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-shrink: 0;
    }
    
    .chat-send-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    .chat-send-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }
    
    .suggestions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 1rem;
    }
    
    .suggestion-chip {
        padding: 0.5rem 1rem;
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        border-radius: 20px;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.3s ease;
        color: #1e293b;
    }
    
    .suggestion-chip:hover {
        background: #667eea;
        color: white;
        border-color: #667eea;
        transform: translateY(-2px);
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
    }
    
    .welcome-message {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        border: 2px solid rgba(102, 126, 234, 0.2);
        border-radius: 16px;
        padding: 1.5rem;
    }
    
    .welcome-message ul {
        margin-top: 1rem;
        padding-right: 1.5rem;
    }
    
    .welcome-message li {
        margin-bottom: 0.5rem;
        color: #4b5563;
    }
</style>
@endpush

@section('content')
<div class="chat-container" style="direction: rtl;">
    <!-- Chat Header -->
    <div class="chat-header">
        <h3>
            <i class="fas fa-robot"></i>
            چت با نجم‌هدا (ادمین)
        </h3>
        <select id="agent-selector" class="form-select form-select-sm">
            <option value="">انتخاب خودکار عامل</option>
            @foreach($agents as $key => $agent)
            <option value="{{ $key }}">{{ $agent['icon'] }} {{ $agent['name'] }}</option>
            @endforeach
        </select>
    </div>
    
    <!-- Chat Messages -->
    <div class="chat-messages" id="chat-messages">
        <!-- Welcome Message -->
        <div class="message message-bot">
            <div class="message-avatar">🌟</div>
            <div class="message-content">
                <div class="message-bubble welcome-message">
                    <div class="message-name">نجم‌هدا:</div>
                    <div class="message-text">
                        سلام! من نجم‌هدا هستم، دستیار هوشمند شما. 🌟<br><br>
                        
                        چون شما ادمین هستید، می‌تونید:
                        <ul>
                            <li>🔧 از مهندس بخواید کد بنویسد یا بررسی کند</li>
                            <li>✈️ از خلبان گزارش پروژه بگیرید</li>
                            <li>👨‍✈️ از مهماندار راهنما بخواید</li>
                            <li>📖 از راهنما استراتژی بخواید</li>
                            <li>🏗️ از معمار بخواید عامل جدید بسازد</li>
                        </ul>
                    </div>
                    <div class="message-time">الان</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Chat Footer -->
    <div class="chat-footer">
        <form id="chat-form" class="chat-input-form">
            @csrf
            <textarea id="message-input" 
                      class="chat-input" 
                      placeholder="پیام خود را بنویسید..."
                      rows="1"
                      autocomplete="off"></textarea>
            <button type="submit" class="chat-send-btn" id="send-btn">
                <i class="fas fa-paper-plane"></i>
                ارسال
            </button>
        </form>
        <div id="suggestions" class="suggestions"></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const chatMessages = document.getElementById('chat-messages');
const chatForm = document.getElementById('chat-form');
const messageInput = document.getElementById('message-input');
const sendBtn = document.getElementById('send-btn');
const agentSelector = document.getElementById('agent-selector');
const suggestionsDiv = document.getElementById('suggestions');

// Auto-resize textarea
messageInput.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 150) + 'px';
});

// ارسال پیام
chatForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const message = messageInput.value.trim();
    if (!message) return;
    
    const agent = agentSelector.value;
    
    // اضافه کردن پیام کاربر
    addMessage(message, 'user');
    messageInput.value = '';
    messageInput.style.height = 'auto';
    suggestionsDiv.innerHTML = '';
    
    // نمایش typing indicator
    showTyping();
    sendBtn.disabled = true;
    
    try {
        const response = await fetch('{{ route('admin.najm-hoda.chat.send') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ 
                message: message,
                agent: agent || null
            })
        });
        
        const data = await response.json();
        
        // حذف typing indicator
        removeTyping();
        sendBtn.disabled = false;
        
        if (data.success) {
            // اضافه کردن پاسخ بات
            addMessage(data.response, 'bot', data.agent);
            
            // نمایش پیشنهادات
            if (data.suggestions && data.suggestions.length > 0) {
                showSuggestions(data.suggestions);
            }
        } else {
            addMessage(data.message || 'متأسفانه خطایی رخ داد. لطفاً دوباره تلاش کنید.', 'bot', 'error');
        }
        
    } catch (error) {
        console.error('خطای JavaScript:', error);
        removeTyping();
        sendBtn.disabled = false;
        addMessage('خطا در ارتباط با سرور. لطفاً اتصال اینترنت خود را بررسی کنید.', 'bot', 'error');
    }
});

// اضافه کردن پیام به چت
function addMessage(text, sender, agent = null) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message message-${sender}`;
    
    const agentIcon = getAgentIcon(agent);
    const agentName = getAgentName(agent);
    
    if (sender === 'user') {
        messageDiv.innerHTML = `
            <div class="message-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="message-content">
                <div class="message-bubble">
                    <div class="message-text">${escapeHtml(text)}</div>
                    <div class="message-time">الان</div>
                </div>
            </div>
        `;
    } else {
        messageDiv.innerHTML = `
            <div class="message-avatar">${agentIcon}</div>
            <div class="message-content">
                <div class="message-bubble">
                    <div class="message-name">${agentName}:</div>
                    <div class="message-text">${escapeHtml(text).replace(/\n/g, '<br>')}</div>
                    <div class="message-time">الان</div>
                </div>
            </div>
        `;
    }
    
    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// نمایش typing indicator
function showTyping() {
    const typingDiv = document.createElement('div');
    typingDiv.id = 'typing-indicator';
    typingDiv.className = 'message message-bot';
    typingDiv.innerHTML = `
        <div class="message-avatar">🌟</div>
        <div class="message-content">
            <div class="typing-indicator">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    `;
    chatMessages.appendChild(typingDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// حذف typing indicator
function removeTyping() {
    const typingDiv = document.getElementById('typing-indicator');
    if (typingDiv) {
        typingDiv.remove();
    }
}

// نمایش پیشنهادات
function showSuggestions(suggestions) {
    suggestionsDiv.innerHTML = '';
    suggestions.forEach(suggestion => {
        const chip = document.createElement('span');
        chip.className = 'suggestion-chip';
        chip.textContent = suggestion;
        chip.onclick = () => {
            messageInput.value = suggestion;
            messageInput.focus();
            messageInput.style.height = 'auto';
            messageInput.style.height = Math.min(messageInput.scrollHeight, 150) + 'px';
        };
        suggestionsDiv.appendChild(chip);
    });
}

// دریافت آیکون عامل
function getAgentIcon(agent) {
    const icons = {
        'engineer': '🔧',
        'pilot': '✈️',
        'steward': '👨‍✈️',
        'guide': '📖',
        'architect': '🏗️',
    };
    return icons[agent] || '🌟';
}

// دریافت نام عامل
function getAgentName(agent) {
    const names = {
        'engineer': 'مهندس',
        'pilot': 'خلبان',
        'steward': 'مهماندار',
        'guide': 'راهنما',
        'architect': 'معمار',
    };
    return names[agent] || 'نجم‌هدا';
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// فوکوس بر روی input
messageInput.focus();

// Enter برای ارسال، Shift+Enter برای خط جدید
messageInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        chatForm.dispatchEvent(new Event('submit'));
    }
});
</script>
@endpush
