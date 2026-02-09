@extends('layouts.admin')

@section('title', 'مدیریت مقالات - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'مدیریت مقالات')
@section('page-description', 'ایجاد، ویرایش و مدیریت مقالات وبلاگ')

@push('styles')
<style>
    .blog-posts-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .blog-posts-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .blog-posts-header h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .blog-filter-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .blog-filter-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        align-items: end;
    }
    
    .blog-filter-input, .blog-filter-select {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        background: white;
        color: #1e293b;
    }
    
    .blog-filter-input:focus, .blog-filter-select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .blog-filter-btn {
        padding: 0.75rem 1.5rem;
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
        border: none;
        border-radius: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        justify-content: center;
    }
    
    .blog-filter-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
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
    
    .blog-post-image {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 0.5rem;
    }
    
    .blog-post-image-placeholder {
        width: 50px;
        height: 50px;
        background: #e5e7eb;
        border-radius: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9ca3af;
    }
    
    .blog-post-title {
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.25rem;
    }
    
    .blog-post-meta {
        font-size: 0.75rem;
        color: #64748b;
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
    }
    
    .blog-action-btn.view {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .blog-action-btn.view:hover {
        background: #bfdbfe;
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
    
    @media (prefers-color-scheme: dark) {
        .blog-posts-card, .blog-filter-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3) !important;
        }
        
        .blog-posts-header h3 {
            color: #f1f5f9 !important;
        }
        
        .blog-filter-input, .blog-filter-select {
            background: #334155 !important;
            border-color: #475569 !important;
            color: #f1f5f9 !important;
        }
        
        .blog-filter-input:focus, .blog-filter-select:focus {
            border-color: #3b82f6 !important;
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
        
        .blog-post-image-placeholder {
            background: #334155 !important;
            color: #64748b !important;
        }
        
        .blog-post-title {
            color: #f1f5f9 !important;
        }
        
        .blog-post-meta {
            color: #94a3b8 !important;
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
    }
    
    @media (max-width: 768px) {
        .blog-posts-card, .blog-filter-card {
            padding: 1rem;
        }
        
        .blog-posts-header {
            flex-direction: column;
            align-items: stretch;
        }
        
        .blog-filter-form {
            grid-template-columns: 1fr;
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
    <div class="blog-posts-card">
        <div class="blog-posts-header">
            <h3>
                <i class="fas fa-newspaper ml-2"></i>
                مدیریت مقالات
            </h3>
            <a href="{{ route('admin.blog.posts.create') }}" class="blog-create-btn">
                <i class="fas fa-plus"></i>
                مقاله جدید
            </a>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="blog-filter-card">
        <form action="{{ route('admin.blog.posts') }}" method="GET" class="blog-filter-form">
            <div>
                <input type="text" 
                       name="search" 
                       class="blog-filter-input" 
                       placeholder="جستجو در عنوان..." 
                       value="{{ request('search') }}">
            </div>
            <div>
                <select name="status" class="blog-filter-select">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="published" {{ request('status') == 'published' ? 'selected' : '' }}>منتشر شده</option>
                    <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>پیش‌نویس</option>
                    <option value="archived" {{ request('status') == 'archived' ? 'selected' : '' }}>بایگانی</option>
                </select>
            </div>
            <div>
                <select name="category" class="blog-filter-select">
                    <option value="">همه دسته‌بندی‌ها</option>
                    @foreach($categories as $category)
                    <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }}>
                        {{ $category->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit" class="blog-filter-btn">
                    <i class="fas fa-search"></i>
                    جستجو
                </button>
            </div>
        </form>
    </div>
    
    <!-- Posts Table -->
    <div class="blog-posts-card">
        <div class="overflow-x-auto">
            <table class="blog-table">
                <thead>
                    <tr>
                        <th width="50">#</th>
                        <th width="70">تصویر</th>
                        <th>عنوان</th>
                        <th>دسته‌بندی</th>
                        <th>نویسنده</th>
                        <th>وضعیت</th>
                        <th>بازدید</th>
                        <th>تاریخ</th>
                        <th width="120">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($posts as $post)
                    <tr>
                        <td>{{ $post->id }}</td>
                        <td>
                            @if($post->featured_image)
                            <img src="{{ asset('images/blog/posts/' . $post->featured_image) }}" 
                                 alt="{{ $post->title }}"
                                 class="blog-post-image">
                            @else
                            <div class="blog-post-image-placeholder">
                                <i class="fas fa-image"></i>
                            </div>
                            @endif
                        </td>
                        <td>
                            <div class="blog-post-title">{{ Str::limit($post->title, 40) }}</div>
                            @if($post->is_featured)
                            <span class="blog-badge warning" style="font-size: 0.625rem; padding: 0.25rem 0.5rem;">ویژه</span>
                            @endif
                        </td>
                        <td>
                            <span class="blog-badge primary">{{ $post->category->name }}</span>
                        </td>
                        <td>
                            <div class="blog-post-meta">{{ $post->author->name }}</div>
                        </td>
                        <td>
                            @if($post->status == 'published')
                            <span class="blog-badge success">منتشر شده</span>
                            @elseif($post->status == 'draft')
                            <span class="blog-badge warning">پیش‌نویس</span>
                            @else
                            <span class="blog-badge secondary">بایگانی</span>
                            @endif
                        </td>
                        <td>
                            <div class="blog-post-meta">
                                <i class="far fa-eye ml-1"></i>
                                {{ number_format($post->views_count ?? 0) }}
                            </div>
                        </td>
                        <td>
                            <div class="blog-post-meta">{{ $post->created_at->format('Y/m/d') }}</div>
                        </td>
                        <td>
                            <div class="blog-actions">
                                <a href="{{ route('blog.show', $post->slug) }}" 
                                   class="blog-action-btn view"
                                   target="_blank"
                                   title="نمایش">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('admin.blog.posts.edit', $post) }}" 
                                   class="blog-action-btn edit"
                                   title="ویرایش">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('admin.blog.posts.delete', $post) }}" 
                                      method="POST" 
                                      class="d-inline"
                                      onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این مقاله را حذف کنید؟')">
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
                                <i class="fas fa-newspaper"></i>
                            </div>
                            <div class="empty-state-title">مقاله‌ای یافت نشد</div>
                            <div class="empty-state-text">هنوز هیچ مقاله‌ای ایجاد نشده است.</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($posts->hasPages())
        <div class="mt-4">
            {{ $posts->links('pagination::tailwind') }}
        </div>
        @endif
    </div>
</div>
@endsection
