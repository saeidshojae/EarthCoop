@extends('layouts.admin')

@section('title', 'ارسال پیام به کاربر - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'ارسال پیام به: ' . $user->fullName())
@section('page-description', 'ارسال ایمیل یا اعلان به کاربر')

@push('styles')
<style>
    .message-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
        max-width: 800px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .message-form-group {
        margin-bottom: 1.5rem;
    }
    
    .message-form-label {
        display: block;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }
    
    .message-form-label .required {
        color: #ef4444;
    }
    
    .message-form-select, .message-form-input, .message-form-textarea {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        background: white;
        color: #1e293b;
    }
    
    .message-form-select:focus, .message-form-input:focus, .message-form-textarea:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .message-form-textarea {
        min-height: 200px;
        resize: vertical;
    }
    
    .message-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        margin-top: 2rem;
    }
    
    .message-btn {
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
    }
    
    .message-btn.submit {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
    }
    
    .message-btn.submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }
    
    .message-btn.cancel {
        background: #6b7280;
        color: white;
    }
    
    .message-btn.cancel:hover {
        background: #4b5563;
    }
    
    .user-info-box {
        background: #f0f9ff;
        border: 1px solid #bae6fd;
        border-radius: 0.75rem;
        padding: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .user-info-box-title {
        font-weight: 600;
        color: #0369a1;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }
    
    .user-info-box-content {
        font-size: 0.875rem;
        color: #0c4a6e;
    }
    
    @media (prefers-color-scheme: dark) {
        .message-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
        }
        
        .message-form-label {
            color: #f1f5f9 !important;
        }
        
        .message-form-select, .message-form-input, .message-form-textarea {
            background: #334155 !important;
            border-color: #475569 !important;
            color: #f1f5f9 !important;
        }
        
        .user-info-box {
            background: #1e3a8a !important;
            border-color: #3b82f6 !important;
        }
        
        .user-info-box-title {
            color: #bfdbfe !important;
        }
        
        .user-info-box-content {
            color: #bfdbfe !important;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <div class="message-card">
        <div class="user-info-box">
            <div class="user-info-box-title">
                <i class="fas fa-user ml-2"></i>
                اطلاعات کاربر
            </div>
            <div class="user-info-box-content">
                <strong>نام:</strong> {{ $user->fullName() }}<br>
                <strong>ایمیل:</strong> {{ $user->email }}<br>
                <strong>شماره تماس:</strong> {{ $user->phone ?? 'تعریف نشده' }}
            </div>
        </div>
        
        <form action="{{ route('admin.users.sendMessage.store', $user->id) }}" method="POST">
            @csrf
            
            <div class="message-form-group">
                <label for="type" class="message-form-label">
                    نوع ارسال <span class="required">*</span>
                </label>
                <select id="type" 
                        name="type" 
                        class="message-form-select @error('type') border-red-500 @enderror" 
                        required
                        onchange="toggleSubject()">
                    <option value="notification">اعلان درون سیستم</option>
                    <option value="email">ایمیل</option>
                </select>
                @error('type')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="message-form-group" id="subjectGroup" style="display: none;">
                <label for="subject" class="message-form-label">
                    موضوع <span class="required">*</span>
                </label>
                <input type="text" 
                       id="subject" 
                       name="subject" 
                       class="message-form-input @error('subject') border-red-500 @enderror" 
                       value="{{ old('subject') }}">
                @error('subject')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="message-form-group">
                <label for="message" class="message-form-label">
                    پیام <span class="required">*</span>
                </label>
                <textarea id="message" 
                          name="message" 
                          class="message-form-textarea @error('message') border-red-500 @enderror" 
                          required
                          placeholder="متن پیام خود را اینجا بنویسید...">{{ old('message') }}</textarea>
                @error('message')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="message-actions">
                <a href="{{ route('admin.users.show', $user->id) }}" class="message-btn cancel">
                    <i class="fas fa-times"></i>
                    انصراف
                </a>
                <button type="submit" class="message-btn submit">
                    <i class="fas fa-paper-plane"></i>
                    ارسال پیام
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleSubject() {
    const type = document.getElementById('type').value;
    const subjectGroup = document.getElementById('subjectGroup');
    const subjectInput = document.getElementById('subject');
    
    if (type === 'email') {
        subjectGroup.style.display = 'block';
        subjectInput.required = true;
    } else {
        subjectGroup.style.display = 'none';
        subjectInput.required = false;
    }
}
</script>
@endsection

