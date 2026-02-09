@extends('layouts.admin')

@section('title', 'جزئیات نقش - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'جزئیات نقش: ' . $role->name)
@section('page-description', 'مشاهده اطلاعات و دسترسی‌های نقش')

@push('styles')
<style>
    .role-detail-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .role-detail-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 2rem;
        padding-bottom: 2rem;
        border-bottom: 2px solid #e5e7eb;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .role-detail-info h2 {
        font-size: 2rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 0.5rem;
    }
    
    .role-detail-slug {
        font-size: 1rem;
        color: #64748b;
        font-family: monospace;
        margin-bottom: 0.5rem;
    }
    
    .role-detail-description {
        color: #64748b;
        font-size: 0.875rem;
        line-height: 1.6;
    }
    
    .role-detail-badge {
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .role-detail-badge.system {
        background: #fef3c7;
        color: #92400e;
    }
    
    .role-detail-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    
    .role-detail-btn {
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 0.75rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
    }
    
    .role-detail-btn.edit {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
    }
    
    .role-detail-btn.edit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        color: white;
    }
    
    .role-permissions-section {
        margin-top: 2rem;
    }
    
    .role-permissions-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #e5e7eb;
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
    
    .role-permissions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 0.75rem;
    }
    
    .role-permission-item {
        background: #f9fafb;
        padding: 0.75rem;
        border-radius: 0.5rem;
        border: 1px solid #e5e7eb;
    }
    
    .role-permission-name {
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.25rem;
    }
    
    .role-permission-slug {
        font-size: 0.75rem;
        color: #64748b;
        font-family: monospace;
    }
    
    .role-permission-description {
        font-size: 0.75rem;
        color: #6b7280;
        margin-top: 0.25rem;
    }
    
    @media (prefers-color-scheme: dark) {
        .role-detail-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
        }
        
        .role-detail-header {
            border-bottom-color: #475569 !important;
        }
        
        .role-detail-info h2 {
            color: #f1f5f9 !important;
        }
        
        .role-detail-slug {
            color: #94a3b8 !important;
        }
        
        .role-detail-description {
            color: #cbd5e1 !important;
        }
        
        .role-permissions-title {
            color: #f1f5f9 !important;
            border-bottom-color: #475569 !important;
        }
        
        .role-permissions-module-title {
            color: #cbd5e1 !important;
            border-bottom-color: #475569 !important;
        }
        
        .role-permission-item {
            background: #334155 !important;
            border-color: #475569 !important;
        }
        
        .role-permission-name {
            color: #f1f5f9 !important;
        }
        
        .role-permission-slug {
            color: #94a3b8 !important;
        }
        
        .role-permission-description {
            color: #9ca3af !important;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <div class="role-detail-card">
        <div class="role-detail-header">
            <div class="role-detail-info">
                <h2>{{ $role->name }}</h2>
                <div class="role-detail-slug">{{ $role->slug }}</div>
                @if($role->description)
                <div class="role-detail-description">{{ $role->description }}</div>
                @endif
            </div>
            <div>
                @if($role->is_system)
                <span class="role-detail-badge system">نقش سیستمی</span>
                @endif
                <div class="role-detail-actions" style="margin-top: 1rem;">
                    @if(!$role->is_system)
                    <a href="{{ route('admin.roles.edit', $role->id) }}" class="role-detail-btn edit">
                        <i class="fas fa-edit"></i>
                        ویرایش
                    </a>
                    @endif
                    <a href="{{ route('admin.roles.index') }}" class="role-detail-btn" style="background: #6b7280; color: white;">
                        <i class="fas fa-arrow-right"></i>
                        بازگشت
                    </a>
                </div>
            </div>
        </div>
        
        <div class="role-permissions-section">
            <div class="role-permissions-title">
                <i class="fas fa-key ml-2"></i>
                دسترسی‌ها ({{ $role->permissions->count() }})
            </div>
            
            @php
                $groupedPermissions = $allPermissions->groupBy('module');
            @endphp
            
            @foreach($groupedPermissions as $module => $modulePermissions)
            @php
                $moduleRolePermissions = $role->permissions->where('module', $module);
            @endphp
            @if($moduleRolePermissions->count() > 0)
            <div class="role-permissions-module">
                <div class="role-permissions-module-title">
                    {{ $module ? ucfirst($module) : 'سایر' }} ({{ $moduleRolePermissions->count() }})
                </div>
                <div class="role-permissions-grid">
                    @foreach($moduleRolePermissions as $permission)
                    <div class="role-permission-item">
                        <div class="role-permission-name">{{ $permission->name }}</div>
                        <div class="role-permission-slug">{{ $permission->slug }}</div>
                        @if($permission->description)
                        <div class="role-permission-description">{{ $permission->description }}</div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
            @endforeach
        </div>
    </div>
</div>
@endsection

