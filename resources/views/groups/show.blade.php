@extends('layouts.app')

@section('content')
<div class="container mt-4" style="direction: rtl;">
    <!-- نمایش پیام خطا -->
    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="row">
        <!-- بخش اصلی چت -->
        <div class="col-md-9">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ $group->name }}</h5>
                    <small>{{ $group->description }}</small>
                </div>
                <div class="card-body" style="height: 500px; overflow-y: auto;" id="messages-container">
                    <div class="messages">
                        <!-- پیام‌ها اینجا به صورت پویا لود می‌شوند -->
                    </div>
                </div>
                <div class="card-footer">
                    <form id="message-form">
                        @csrf
                        <div class="input-group">
                            <input type="text" name="message" id="message" class="form-control" placeholder="پیام خود را بنویسید..." required>
                            <button type="submit" class="btn btn-primary">ارسال</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- لیست اعضای گروه -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    اعضای گروه ({{ $members->count() }})
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @foreach($members as $member)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <img src="{{ $member->avatar ? asset($member->avatar) : asset('images/default-profile.png') }}" 
                                         class="rounded-circle me-2" 
                                         width="30" 
                                         height="30"
                                         alt="{{ $member->full_name }}">
                                    {{ $member->full_name }}
                                </div>
                                <span class="badge bg-{{ $member->role === 'admin' ? 'danger' : 'primary' }}">
                                    {{ $member->role === 'admin' ? 'مدیر' : 'عضو' }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const messagesContainer = document.getElementById('messages-container');
    const messagesList = document.querySelector('.messages');
    const messageForm = document.getElementById('message-form');
    const messageInput = document.getElementById('message');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    // تابع نمایش پیام
    function displayMessage(message) {
        const isCurrentUser = message.is_current_user || message.user.id == {{ Auth::id() }};
        const messageElement = document.createElement('div');
        messageElement.className = `message mb-3 ${isCurrentUser ? 'text-end' : 'text-start'}`;
        
        messageElement.innerHTML = `
            <div class="d-inline-block p-2 rounded ${isCurrentUser ? 'bg-primary text-white' : 'bg-light'}" style="max-width: 70%;">
                <div class="message-header mb-1">
                    <small class="fw-bold">${message.user.name}</small>
                    <small class="text-muted ms-2">${message.created_at}</small>
                </div>
                <div class="message-content">
                    ${message.message}
                </div>
            </div>
        `;
        
        messagesList.appendChild(messageElement);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // دریافت پیام‌های قبلی
    fetch(`/groups/{{ $group->id }}/messages`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(messages => {
            console.log('Received messages:', messages);
            messagesList.innerHTML = '';
            messages.forEach(displayMessage);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        })
        .catch(error => {
            console.error('Error fetching messages:', error);
            messagesList.innerHTML = '<div class="alert alert-danger">خطا در دریافت پیام‌ها. لطفاً صفحه را رفرش کنید.</div>';
        });

    // ارسال پیام جدید
    messageForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!messageInput.value.trim()) {
            return;
        }

        fetch(`/groups/{{ $group->id }}/messages`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                message: messageInput.value,
                _token: csrfToken
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Message sent:', data);
            messageInput.value = '';
            displayMessage({
                ...data,
                is_current_user: true
            });
        })
        .catch(error => {
            console.error('Error sending message:', error);
            alert('خطا در ارسال پیام. لطفاً دوباره تلاش کنید.');
        });
    });

    // گوش دادن به رویدادهای Pusher
    Echo.private(`group.{{ $group->id }}`)
        .listen('MessageSent', (e) => {
            console.log('Received message from Pusher:', e);
            if (e.message.user_id != {{ Auth::id() }}) {
                displayMessage({
                    ...e.message,
                    is_current_user: false
                });
            }
        });
});
</script>
@endpush

@push('styles')
<style>
.message {
    margin-bottom: 1rem;
}
.message-header {
    font-size: 0.8rem;
}
.messages {
    padding: 1rem;
}
</style>
@endpush
@endsection