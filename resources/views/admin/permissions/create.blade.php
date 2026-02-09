@extends('layouts.admin')

@section('title', 'ایجاد دسترسی جدید - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'ایجاد دسترسی جدید')
@section('page-description', 'ایجاد دسترسی جدید برای سیستم')

@push('styles')
<style>
    .permission-form-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
        max-width: 700px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .permission-form-group {
        margin-bottom: 1.5rem;
    }
    
    .permission-form-label {
        display: block;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }
    
    .permission-form-label .required {
        color: #ef4444;
    }
    
    .permission-form-input, .permission-form-textarea, .permission-form-select {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        background: white;
        color: #1e293b;
    }
    
    .permission-form-input:focus, .permission-form-textarea:focus, .permission-form-select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .permission-form-textarea {
        min-height: 100px;
        resize: vertical;
    }
    
    .permission-form-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        margin-top: 2rem;
    }
    
    .permission-form-btn {
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
    
    .permission-form-btn.submit {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
        color: white;
    }
    
    .permission-form-btn.submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    .permission-form-btn.cancel {
        background: #6b7280;
        color: white;
    }
    
    .permission-form-btn.cancel:hover {
        background: #4b5563;
    }
    
    @media (prefers-color-scheme: dark) {
        .permission-form-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
        }
        
        .permission-form-label {
            color: #f1f5f9 !important;
        }
        
        .permission-form-input, .permission-form-textarea, .permission-form-select {
            background: #334155 !important;
            border-color: #475569 !important;
            color: #f1f5f9 !important;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <div class="permission-form-card">
        <form action="{{ route('admin.permissions.store') }}" method="POST">
            @csrf
            
            <div class="permission-form-group">
                <label for="name" class="permission-form-label">
                    نام دسترسی <span class="required">*</span>
                </label>
                <input type="text" 
                       id="name" 
                       name="name" 
                       class="permission-form-input @error('name') border-red-500 @enderror" 
                       value="{{ old('name') }}" 
                       required
                       placeholder="مثل: مشاهده کاربران">
                @error('name')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="permission-form-group">
                <label for="slug" class="permission-form-label">
                    Slug <span class="required">*</span>
                </label>
                <input type="text" 
                       id="slug" 
                       name="slug" 
                       class="permission-form-input @error('slug') border-red-500 @enderror" 
                       value="{{ old('slug') }}" 
                       required
                       placeholder="مثل: users.view">
                <div class="text-gray-500 text-sm mt-1">فقط حروف انگلیسی، اعداد، نقطه و خط تیره</div>
                @error('slug')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="permission-form-group">
                <label for="description" class="permission-form-label">
                    توضیحات
                </label>
                <textarea id="description" 
                          name="description" 
                          class="permission-form-textarea @error('description') border-red-500 @enderror" 
                          placeholder="توضیحات دسترسی...">{{ old('description') }}</textarea>
                @error('description')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="permission-form-group">
                <label for="module" class="permission-form-label">
                    ماژول
                </label>
                <input type="text" 
                       id="module" 
                       name="module" 
                       class="permission-form-input @error('module') border-red-500 @enderror" 
                       value="{{ old('module') }}" 
                       placeholder="مثل: users, blog, groups">
                <div class="text-gray-500 text-sm mt-1">برای دسته‌بندی دسترسی‌ها</div>
                @error('module')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="permission-form-group">
                <label for="order" class="permission-form-label">
                    ترتیب نمایش
                </label>
                <input type="number" 
                       id="order" 
                       name="order" 
                       class="permission-form-input @error('order') border-red-500 @enderror" 
                       value="{{ old('order', 0) }}" 
                       min="0">
                @error('order')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="permission-form-actions">
                <a href="{{ route('admin.permissions.index') }}" class="permission-form-btn cancel">
                    <i class="fas fa-times"></i>
                    انصراف
                </a>
                <button type="submit" class="permission-form-btn submit">
                    <i class="fas fa-save"></i>
                    ایجاد دسترسی
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

