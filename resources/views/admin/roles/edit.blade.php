@extends('layouts.admin')

@section('title', 'ویرایش نقش - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'ویرایش نقش: ' . $role->name)
@section('page-description', 'ویرایش اطلاعات و دسترسی‌های نقش')

@push('styles')
<link rel="stylesheet" href="{{ asset('Css/admin-styles.css') }}">
<style>
    .role-form-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .role-form-group {
        margin-bottom: 1.5rem;
    }
    
    .role-form-label {
        display: block;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }
    
    .role-form-label .required {
        color: #ef4444;
    }
    
    .role-form-input, .role-form-textarea, .role-form-select {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        background: white;
        color: #1e293b;
    }
    
    .role-form-input:focus, .role-form-textarea:focus, .role-form-select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .role-form-textarea {
        min-height: 100px;
        resize: vertical;
    }
    
    .role-permissions-section {
        background: #f9fafb;
        border-radius: 0.75rem;
        padding: 1.5rem;
        margin-top: 1.5rem;
    }
    
    .role-permissions-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 1rem;
    }
    
    .role-permissions-module {
        margin-bottom: 1.5rem;
    }
    
    .role-permissions-module-title {
        font-size: 1rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.75rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .role-permissions-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 0.75rem;
    }
    
    .role-permission-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .role-permission-checkbox {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    
    .role-permission-label {
        font-size: 0.875rem;
        color: #4b5563;
        cursor: pointer;
        user-select: none;
    }
    
    .role-form-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        margin-top: 2rem;
    }
    
    .role-form-btn {
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
    
    .role-form-btn.submit {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
    }
    
    .role-form-btn.submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }
    
    .role-form-btn.cancel {
        background: #6b7280;
        color: white;
    }
    
    .role-form-btn.cancel:hover {
        background: #4b5563;
    }
    
    @media (prefers-color-scheme: dark) {
        .role-form-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
        }
        
        .role-form-label {
            color: #f1f5f9 !important;
        }
        
        .role-form-input, .role-form-textarea, .role-form-select {
            background: #334155 !important;
            border-color: #475569 !important;
            color: #f1f5f9 !important;
        }
        
        .role-permissions-section {
            background: #334155 !important;
        }
        
        .role-permissions-title {
            color: #f1f5f9 !important;
        }
        
        .role-permissions-module-title {
            color: #cbd5e1 !important;
            border-bottom-color: #475569 !important;
        }
        
        .role-permission-label {
            color: #cbd5e1 !important;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <div class="role-form-card">
        <form action="{{ route('admin.roles.update', $role->id) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="role-form-group">
                <label for="name" class="role-form-label">
                    نام نقش <span class="required">*</span>
                </label>
                <input type="text" 
                       id="name" 
                       name="name" 
                       class="role-form-input @error('name') border-red-500 @enderror" 
                       value="{{ old('name', $role->name) }}" 
                       required>
                @error('name')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="role-form-group">
                <label for="slug" class="role-form-label">
                    Slug <span class="required">*</span>
                </label>
                <input type="text" 
                       id="slug" 
                       name="slug" 
                       class="role-form-input @error('slug') border-red-500 @enderror" 
                       value="{{ old('slug', $role->slug) }}" 
                       required>
                @error('slug')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="role-form-group">
                <label for="description" class="role-form-label">
                    توضیحات
                </label>
                <textarea id="description" 
                          name="description" 
                          class="role-form-textarea @error('description') border-red-500 @enderror">{{ old('description', $role->description) }}</textarea>
                @error('description')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="role-form-group">
                <label for="order" class="role-form-label">
                    ترتیب نمایش
                </label>
                <input type="number" 
                       id="order" 
                       name="order" 
                       class="role-form-input @error('order') border-red-500 @enderror" 
                       value="{{ old('order', $role->order) }}" 
                       min="0">
                @error('order')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="role-permissions-section">
                <div class="role-permissions-title">
                    <i class="fas fa-key ml-2"></i>
                    دسترسی‌ها
                </div>
                
                @php
                    $rolePermissionIds = $role->permissions->pluck('id')->toArray();
                @endphp
                
                @foreach($permissions as $module => $modulePermissions)
                <div class="role-permissions-module">
                    <div class="role-permissions-module-title">
                        {{ $module ? ucfirst($module) : 'سایر' }}
                    </div>
                    <div class="role-permissions-list">
                        @foreach($modulePermissions as $permission)
                        <div class="role-permission-item">
                            <input type="checkbox" 
                                   id="permission_{{ $permission->id }}" 
                                   name="permissions[]" 
                                   value="{{ $permission->id }}"
                                   {{ in_array($permission->id, $rolePermissionIds) ? 'checked' : '' }}
                                   class="role-permission-checkbox">
                            <label for="permission_{{ $permission->id }}" class="role-permission-label">
                                {{ $permission->name }}
                                @if($permission->description)
                                <span class="text-gray-400 text-xs block">({{ $permission->description }})</span>
                                @endif
                            </label>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
            
            <div class="role-form-actions">
                <a href="{{ route('admin.roles.index') }}" class="role-form-btn cancel">
                    <i class="fas fa-times"></i>
                    انصراف
                </a>
                <button type="submit" class="role-form-btn submit">
                    <i class="fas fa-save"></i>
                    ذخیره تغییرات
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

