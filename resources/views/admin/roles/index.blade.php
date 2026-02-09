@extends('layouts.admin')

@section('title', 'مدیریت نقش‌ها - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'مدیریت نقش‌ها')
@section('page-description', 'ایجاد و مدیریت نقش‌های دسترسی')

@push('styles')
<style>
    .role-management-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .role-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .role-header h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .role-create-btn {
        padding: 0.75rem 1.5rem;
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
        color: white;
        border: none;
        border-radius: 0.75rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
    }
    
    .role-create-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        color: white;
    }
    
    .role-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
    }
    
    .role-card {
        background: #f9fafb;
        border-radius: 12px;
        padding: 1.5rem;
        border: 2px solid #e5e7eb;
        transition: all 0.2s ease;
    }
    
    .role-card:hover {
        border-color: #667eea;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
    }
    
    .role-card-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
    }
    
    .role-card-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 0.25rem;
    }
    
    .role-card-slug {
        font-size: 0.875rem;
        color: #64748b;
        font-family: monospace;
    }
    
    .role-card-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 0.5rem;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .role-card-badge.system {
        background: #fef3c7;
        color: #92400e;
    }
    
    .role-card-description {
        color: #64748b;
        font-size: 0.875rem;
        margin-bottom: 1rem;
        line-height: 1.6;
    }
    
    .role-card-permissions {
        margin-bottom: 1rem;
    }
    
    .role-card-permissions-title {
        font-size: 0.875rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.5rem;
    }
    
    .role-card-permissions-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .role-permission-badge {
        padding: 0.25rem 0.5rem;
        background: #e0e7ff;
        color: #4338ca;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .role-card-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .role-action-btn {
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 0.5rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        text-decoration: none;
    }
    
    .role-action-btn.view {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        color: white;
    }
    
    .role-action-btn.view:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        color: white;
    }
    
    .role-action-btn.edit {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
    }
    
    .role-action-btn.edit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        color: white;
    }
    
    .role-action-btn.delete {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
    }
    
    .role-action-btn.delete:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        color: white;
    }
    
    @media (prefers-color-scheme: dark) {
        .role-management-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3) !important;
        }
        
        .role-header h3 {
            color: #f1f5f9 !important;
        }
        
        .role-card {
            background: #334155 !important;
            border-color: #475569 !important;
        }
        
        .role-card:hover {
            border-color: #667eea !important;
        }
        
        .role-card-title {
            color: #f1f5f9 !important;
        }
        
        .role-card-slug {
            color: #94a3b8 !important;
        }
        
        .role-card-description {
            color: #cbd5e1 !important;
        }
        
        .role-card-permissions-title {
            color: #cbd5e1 !important;
        }
        
        .role-permission-badge {
            background: #1e3a8a !important;
            color: #bfdbfe !important;
        }
        
        .role-card-badge.system {
            background: #451a03 !important;
            color: #fed7aa !important;
        }
    }
    
    @media (max-width: 768px) {
        .role-management-card {
            padding: 1rem;
        }
        
        .role-grid {
            grid-template-columns: 1fr;
        }
        
        .role-card-actions {
            width: 100%;
        }
        
        .role-action-btn {
            flex: 1;
            justify-content: center;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <!-- Header -->
    <div class="role-management-card">
        <div class="role-header">
            <h3>
                <i class="fas fa-user-shield ml-2"></i>
                مدیریت نقش‌ها
            </h3>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <a href="{{ route('admin.permissions.index') }}" class="role-create-btn" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                    <i class="fas fa-key"></i>
                    مدیریت دسترسی‌ها
                </a>
                <a href="{{ route('admin.roles.create') }}" class="role-create-btn">
                    <i class="fas fa-plus"></i>
                    ایجاد نقش جدید
                </a>
            </div>
        </div>
        
        <!-- Roles Grid -->
        <div class="role-grid">
            @foreach($roles as $role)
            <div class="role-card">
                <div class="role-card-header">
                    <div>
                        <div class="role-card-title">{{ $role->name }}</div>
                        <div class="role-card-slug">{{ $role->slug }}</div>
                    </div>
                    @if($role->is_system)
                    <span class="role-card-badge system">سیستمی</span>
                    @endif
                </div>
                
                @if($role->description)
                <div class="role-card-description">
                    {{ $role->description }}
                </div>
                @endif
                
                <div class="role-card-permissions">
                    <div class="role-card-permissions-title">
                        <i class="fas fa-key ml-2"></i>
                        دسترسی‌ها ({{ $role->permissions->count() }})
                    </div>
                    <div class="role-card-permissions-list">
                        @foreach($role->permissions->take(5) as $permission)
                        <span class="role-permission-badge">{{ $permission->name }}</span>
                        @endforeach
                        @if($role->permissions->count() > 5)
                        <span class="role-permission-badge">+{{ $role->permissions->count() - 5 }} بیشتر</span>
                        @endif
                    </div>
                </div>
                
                <div class="role-card-actions">
                    <a href="{{ route('admin.roles.show', $role->id) }}" class="role-action-btn view">
                        <i class="fas fa-eye"></i>
                        جزئیات
                    </a>
                    @if(!$role->is_system)
                    <a href="{{ route('admin.roles.edit', $role->id) }}" class="role-action-btn edit">
                        <i class="fas fa-edit"></i>
                        ویرایش
                    </a>
                    <form action="{{ route('admin.roles.destroy', $role->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این نقش را حذف کنید؟')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="role-action-btn delete">
                            <i class="fas fa-trash"></i>
                            حذف
                        </button>
                    </form>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

