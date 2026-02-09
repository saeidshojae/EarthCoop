@extends('layouts.admin')

@section('title', 'جزئیات مکالمه - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'جزئیات مکالمه')
@section('page-description', 'مشاهده کامل مکالمه کاربر با نجم‌هدا')

@push('styles')
<style>
    .conversation-detail-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .conversation-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 12px 12px 0 0;
        margin: -2rem -2rem 1.5rem -2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .conversation-header h3 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .conversation-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
        padding: 1.5rem;
        background: #f9fafb;
        border-radius: 12px;
    }
    
    .conversation-user-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
    }
    
    .conversation-user-info {
        flex: 1;
    }
    
    .conversation-user-name {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 0.25rem;
    }
    
    .conversation-user-email {
        font-size: 0.875rem;
        color: #64748b;
    }
    
    .conversation-meta {
        text-align: left;
    }
    
    .conversation-meta-item {
        font-size: 0.875rem;
        color: #64748b;
        margin-bottom: 0.25rem;
    }
    
    .conversation-meta-label {
        font-weight: 600;
        color: #1e293b;
    }
    
    .messages-container {
        max-height: 600px;
        overflow-y: auto;
        padding: 1.5rem;
        background: #f8fafc;
        border-radius: 12px;
        direction: rtl;
    }
    
    .messages-container::-webkit-scrollbar {
        width: 8px;
    }
    
    .messages-container::-webkit-scrollbar-track {
        background: #e5e7eb;
        border-radius: 4px;
    }
    
    .messages-container::-webkit-scrollbar-thumb {
        background: #9ca3af;
        border-radius: 4px;
    }
    
    .message-item {
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
        white-space: pre-wrap;
        word-wrap: break-word;
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
    
    .message-agent {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        background: rgba(102, 126, 234, 0.1);
        color: #667eea;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }
    
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: #64748b;
    }
    
    .empty-state-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    .empty-state-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 0.5rem;
    }
    
    .empty-state-text {
        font-size: 1rem;
        color: #64748b;
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-3xl font-bold text-gray-800 mb-2">💬 جزئیات مکالمه</h2>
            <p class="text-gray-600">مشاهده کامل مکالمه کاربر با نجم‌هدا</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('admin.najm-hoda.conversations') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors font-semibold flex items-center gap-2">
                <i class="fas fa-arrow-right"></i>
                بازگشت به لیست
            </a>
            <a href="{{ route('admin.najm-hoda.index') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-semibold flex items-center gap-2">
                <i class="fas fa-home"></i>
                داشبورد
            </a>
        </div>
    </div>

    <!-- Conversation Info -->
    <div class="conversation-detail-card">
        <div class="conversation-header">
            <h3>
                <i class="fas fa-comment-dots"></i>
                📋 اطلاعات مکالمه
            </h3>
        </div>
        
        <div class="conversation-info">
            <img src="{{ $conversation->user->avatar ?? '/images/default-avatar.png' }}" 
                 class="conversation-user-avatar" 
                 alt="{{ $conversation->user->name }}">
            <div class="conversation-user-info">
                <div class="conversation-user-name">{{ $conversation->user->name }}</div>
                <div class="conversation-user-email">{{ $conversation->user->email ?? 'ایمیل موجود نیست' }}</div>
            </div>
            <div class="conversation-meta">
                <div class="conversation-meta-item">
                    <span class="conversation-meta-label">عنوان:</span>
                    {{ $conversation->title }}
                </div>
                <div class="conversation-meta-item">
                    <span class="conversation-meta-label">تعداد پیام:</span>
                    {{ $conversation->messages->count() }}
                </div>
                <div class="conversation-meta-item">
                    <span class="conversation-meta-label">شروع:</span>
                    {{ $conversation->created_at->format('Y/m/d H:i') }}
                </div>
                <div class="conversation-meta-item">
                    <span class="conversation-meta-label">آخرین فعالیت:</span>
                    {{ $conversation->updated_at->format('Y/m/d H:i') }}
                </div>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <div class="conversation-detail-card">
        <div class="conversation-header">
            <h3>
                <i class="fas fa-comments"></i>
                💬 پیام‌های مکالمه
            </h3>
            <div class="text-white text-sm">
                {{ $conversation->messages->count() }} پیام
            </div>
        </div>
        
        <div class="messages-container" id="messages-container">
            @forelse($conversation->messages as $message)
            <div class="message-item message-{{ $message->sender_type === 'user' ? 'user' : 'bot' }}">
                <div class="message-avatar">
                    @if($message->sender_type === 'user')
                        <i class="fas fa-user"></i>
                    @else
                        @if(isset($message->agent_role))
                            @if($message->agent_role === 'engineer')
                                🔧
                            @elseif($message->agent_role === 'pilot')
                                ✈️
                            @elseif($message->agent_role === 'steward')
                                👨‍✈️
                            @elseif($message->agent_role === 'guide')
                                📖
                            @elseif($message->agent_role === 'architect')
                                🏗️
                            @else
                                🌟
                            @endif
                        @else
                            🌟
                        @endif
                    @endif
                </div>
                <div class="message-content">
                    <div class="message-bubble">
                        @if($message->sender_type === 'bot' && isset($message->agent_role))
                        <div class="message-agent">
                            @if($message->agent_role === 'engineer')
                                🔧 مهندس
                            @elseif($message->agent_role === 'pilot')
                                ✈️ خلبان
                            @elseif($message->agent_role === 'steward')
                                👨‍✈️ مهماندار
                            @elseif($message->agent_role === 'guide')
                                📖 راهنما
                            @elseif($message->agent_role === 'architect')
                                🏗️ معمار
                            @else
                                🌟 نجم‌هدا
                            @endif
                        </div>
                        @endif
                        <div class="message-text">{{ $message->message }}</div>
                        <div class="message-time">
                            {{ $message->created_at->format('H:i') }} - {{ $message->created_at->diffForHumans() }}
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-inbox"></i>
                </div>
                <div class="empty-state-title">هنوز پیامی ثبت نشده است</div>
                <div class="empty-state-text">هیچ پیامی در این مکالمه وجود ندارد.</div>
            </div>
            @endforelse
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
// Auto-scroll to bottom on load
document.addEventListener('DOMContentLoaded', function() {
    const messagesContainer = document.getElementById('messages-container');
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
});
</script>
@endpush

