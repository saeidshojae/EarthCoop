@extends('layouts.admin')

@section('title', 'مدیریت دسته‌بندی‌ها - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'مدیریت دسته‌بندی‌ها')
@section('page-description', 'ایجاد، ویرایش و مدیریت دسته‌بندی‌های وبلاگ')

@push('styles')
<style>
    .blog-categories-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .blog-categories-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .blog-categories-header h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .blog-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .blog-table thead {
        background: #f9fafb;
    }
    
    .blog-table th {
        padding: 1rem;
        text-align: right;
        font-weight: 700;
        color: #1e293b;
        border-bottom: 2px solid #e5e7eb;
        font-size: 0.875rem;
    }
    
    .blog-table td {
        padding: 1rem;
        text-align: right;
        color: #4b5563;
        border-bottom: 1px solid #e5e7eb;
        font-size: 0.875rem;
    }
    
    .blog-table tr:hover {
        background-color: #f9fafb;
    }
    
    .blog-category-image {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 0.5rem;
    }
    
    .blog-category-image-placeholder {
        width: 50px;
        height: 50px;
        background: #e5e7eb;
        border-radius: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9ca3af;
    }
    
    .blog-actions {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }
    
    .blog-action-btn {
        padding: 0.5rem;
        border: none;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        text-decoration: none;
    }
    
    .blog-action-btn.edit {
        background: #fef3c7;
        color: #92400e;
    }
    
    .blog-action-btn.edit:hover {
        background: #fde68a;
    }
    
    .blog-action-btn.delete {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .blog-action-btn.delete:hover {
        background: #fecaca;
    }
    
    .blog-create-btn {
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
    
    .blog-create-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        color: white;
    }
    
    .empty-state {
        text-align: center;
        padding: 4rem 0;
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
    
    .blog-badge {
        padding: 0.375rem 0.75rem;
        border-radius: 0.5rem;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
    }
    
    .blog-badge.success {
        background-color: #d1fae5;
        color: #065f46;
    }
    
    .blog-badge.warning {
        background-color: #fef3c7;
        color: #92400e;
    }
    
    .blog-badge.secondary {
        background-color: #e5e7eb;
        color: #374151;
    }
    
    .blog-badge.primary {
        background-color: #dbeafe;
        color: #1e40af;
    }
    
    .blog-badge.info {
        background-color: #dbeafe;
        color: #1e40af;
    }
    
    .blog-badge.danger {
        background-color: #fee2e2;
        color: #991b1b;
    }
    
    @media (prefers-color-scheme: dark) {
        .blog-categories-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3) !important;
        }
        
        .blog-categories-header h3 {
            color: #f1f5f9 !important;
        }
        
        .blog-table thead {
            background: #334155 !important;
        }
        
        .blog-table th {
            color: #f1f5f9 !important;
            border-bottom-color: #475569 !important;
        }
        
        .blog-table td {
            color: #cbd5e1 !important;
            border-bottom-color: #334155 !important;
        }
        
        .blog-table tr:hover {
            background-color: #334155 !important;
        }
        
        .blog-category-image-placeholder {
            background: #334155 !important;
            color: #64748b !important;
        }
        
        .empty-state-title {
            color: #f1f5f9 !important;
        }
        
        .empty-state-text {
            color: #94a3b8 !important;
        }
        
        .blog-badge.success {
            background-color: #064e3b !important;
            color: #a7f3d0 !important;
        }
        
        .blog-badge.warning {
            background-color: #451a03 !important;
            color: #fed7aa !important;
        }
        
        .blog-badge.secondary {
            background-color: #334155 !important;
            color: #cbd5e1 !important;
        }
        
        .blog-badge.primary,
        .blog-badge.info {
            background-color: #1e3a8a !important;
            color: #bfdbfe !important;
        }
        
        .blog-badge.danger {
            background-color: #450a0a !important;
            color: #fecaca !important;
        }
    }
    
    @media (max-width: 768px) {
        .blog-categories-card {
            padding: 1rem;
        }
        
        .blog-categories-header {
            flex-direction: column;
            align-items: stretch;
        }
        
        .blog-table {
            font-size: 0.75rem;
        }
        
        .blog-table th, .blog-table td {
            padding: 0.5rem;
        }
        
        .blog-actions {
            flex-direction: column;
            gap: 0.25rem;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <!-- Header -->
    <div class="blog-categories-card">
        <div class="blog-categories-header">
            <h3>
                <i class="fas fa-folder ml-2"></i>
                مدیریت دسته‌بندی‌ها
            </h3>
            <a href="{{ route('admin.blog.categories.create') }}" class="blog-create-btn">
                <i class="fas fa-plus"></i>
                دسته‌بندی جدید
            </a>
        </div>
    </div>

    <!-- Categories Table -->
    <div class="blog-categories-card">
        <div class="overflow-x-auto">
            <table class="blog-table">
                <thead>
                        <tr>
                            <th width="50">#</th>
                        <th width="70">تصویر</th>
                            <th>نام</th>
                            <th>Slug</th>
                            <th>توضیحات</th>
                            <th>ترتیب</th>
                            <th>تعداد مقالات</th>
                            <th>وضعیت</th>
                        <th width="100">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $category)
                        <tr>
                            <td>{{ $category->id }}</td>
                            <td>
                                @if($category->image)
                                <img src="{{ asset('images/blog/categories/' . $category->image) }}" 
                                     alt="{{ $category->name }}"
                                 class="blog-category-image">
                                @else
                            <div class="blog-category-image-placeholder">
                                <i class="fas fa-image"></i>
                            </div>
                                @endif
                            </td>
                        <td>
                            <strong class="text-gray-800 dark:text-gray-200">{{ $category->name }}</strong>
                        </td>
                        <td>
                            <code class="text-sm bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">{{ $category->slug }}</code>
                        </td>
                        <td>
                            <div class="text-sm text-gray-600 dark:text-gray-400">{{ Str::limit($category->description, 40) }}</div>
                        </td>
                        <td>
                            <span class="blog-badge secondary">{{ $category->order }}</span>
                            </td>
                            <td>
                            <span class="blog-badge info">{{ $category->posts_count }} مقاله</span>
                            </td>
                            <td>
                                @if($category->is_active)
                            <span class="blog-badge success">فعال</span>
                                @else
                            <span class="blog-badge danger">غیرفعال</span>
                                @endif
                            </td>
                            <td>
                            <div class="blog-actions">
                                <a href="{{ route('admin.blog.categories.edit', $category) }}" 
                                   class="blog-action-btn edit"
                                   title="ویرایش">
                                        <i class="fas fa-edit"></i>
                                </a>
                                    <form action="{{ route('admin.blog.categories.delete', $category) }}" 
                                          method="POST" 
                                          class="d-inline"
                                      onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این دسته‌بندی را حذف کنید؟')">
                                        @csrf
                                        @method('DELETE')
                                    <button type="submit" class="blog-action-btn delete" title="حذف">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                        <td colspan="9" class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-folder"></i>
                            </div>
                            <div class="empty-state-title">دسته‌بندی‌ای یافت نشد</div>
                            <div class="empty-state-text">هنوز هیچ دسته‌بندی‌ای ایجاد نشده است.</div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
