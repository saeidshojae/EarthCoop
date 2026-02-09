@extends('layouts.admin')

@section('title', 'مدیریت دسترسی‌ها - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'مدیریت دسترسی‌ها')
@section('page-description', 'ایجاد و مدیریت دسترسی‌های سیستم')

@push('styles')
<style>
    .permission-management-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .permission-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .permission-header h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .permission-create-btn {
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
    
    .permission-create-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        color: white;
    }
    
    .permission-module-section {
        margin-bottom: 2rem;
    }
    
    .permission-module-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .permission-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .permission-table thead {
        background: #f9fafb;
    }
    
    .permission-table th {
        padding: 1rem;
        text-align: right;
        font-weight: 700;
        color: #1e293b;
        border-bottom: 2px solid #e5e7eb;
        font-size: 0.875rem;
    }
    
    .permission-table td {
        padding: 0.75rem 1rem;
        text-align: right;
        color: #4b5563;
        border-bottom: 1px solid #e5e7eb;
        font-size: 0.875rem;
    }
    
    .permission-table tr:hover {
        background-color: #f9fafb;
    }
    
    .permission-slug {
        font-family: monospace;
        color: #64748b;
        font-size: 0.8rem;
    }
    
    .permission-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .permission-action-btn {
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
    
    .permission-action-btn.edit {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
    }
    
    .permission-action-btn.edit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        color: white;
    }
    
    .permission-action-btn.delete {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
    }
    
    .permission-action-btn.delete:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        color: white;
    }
    
    @media (prefers-color-scheme: dark) {
        .permission-management-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
        }
        
        .permission-header h3 {
            color: #f1f5f9 !important;
        }
        
        .permission-module-title {
            color: #f1f5f9 !important;
            border-bottom-color: #475569 !important;
        }
        
        .permission-table thead {
            background: #334155 !important;
        }
        
        .permission-table th {
            color: #f1f5f9 !important;
            border-bottom-color: #475569 !important;
        }
        
        .permission-table td {
            color: #cbd5e1 !important;
            border-bottom-color: #334155 !important;
        }
        
        .permission-table tr:hover {
            background-color: #334155 !important;
        }
        
        .permission-slug {
            color: #94a3b8 !important;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <div class="permission-management-card">
        <div class="permission-header">
            <h3>
                <i class="fas fa-key ml-2"></i>
                مدیریت دسترسی‌ها
            </h3>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <a href="{{ route('admin.roles.index') }}" class="permission-create-btn" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                    <i class="fas fa-user-shield"></i>
                    مدیریت نقش‌ها
                </a>
                <a href="{{ route('admin.permissions.create') }}" class="permission-create-btn">
                    <i class="fas fa-plus"></i>
                    ایجاد دسترسی جدید
                </a>
            </div>
        </div>
        
        @foreach($permissions as $module => $modulePermissions)
        <div class="permission-module-section">
            <div class="permission-module-title">
                {{ $module ? ucfirst($module) : 'سایر' }} ({{ $modulePermissions->count() }})
            </div>
            <table class="permission-table">
                <thead>
                    <tr>
                        <th>نام</th>
                        <th>Slug</th>
                        <th>توضیحات</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($modulePermissions as $permission)
                    <tr>
                        <td>
                            <strong>{{ $permission->name }}</strong>
                        </td>
                        <td>
                            <span class="permission-slug">{{ $permission->slug }}</span>
                        </td>
                        <td>
                            {{ $permission->description ?? '-' }}
                        </td>
                        <td>
                            <div class="permission-actions">
                                <a href="{{ route('admin.permissions.edit', $permission->id) }}" class="permission-action-btn edit">
                                    <i class="fas fa-edit"></i>
                                    ویرایش
                                </a>
                                <form action="{{ route('admin.permissions.destroy', $permission->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این دسترسی را حذف کنید؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="permission-action-btn delete">
                                        <i class="fas fa-trash"></i>
                                        حذف
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endforeach
    </div>
</div>
@endsection

