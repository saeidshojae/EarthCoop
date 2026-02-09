@extends('layouts.admin')

@section('title', 'مدیریت نظرات - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'مدیریت نظرات')
@section('page-description', 'تایید، رد و مدیریت نظرات کاربران')

@push('styles')
<style>
    .blog-comments-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .blog-comments-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .blog-comments-header h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .blog-comments-filter {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }
    
    .blog-comments-filter-select {
        padding: 0.75rem 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        background: white;
        color: #1e293b;
        min-width: 200px;
    }
    
    .blog-comments-filter-select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .blog-comment-item {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: all 0.2s ease;
    }
    
    .blog-comment-item:hover {
        background: #f3f4f6;
        border-color: #d1d5db;
        transform: translateY(-2px);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    .blog-comment-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .blog-comment-user {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .blog-comment-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        flex-shrink: 0;
    }
    
    .blog-comment-user-info {
        flex: 1;
    }
    
    .blog-comment-user-name {
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.25rem;
    }
    
    .blog-comment-meta {
        font-size: 0.75rem;
        color: #64748b;
    }
    
    .blog-comment-post {
        font-size: 0.875rem;
        color: #3b82f6;
        text-decoration: none;
        transition: color 0.2s ease;
    }
    
    .blog-comment-post:hover {
        color: #2563eb;
    }
    
    .blog-comment-content {
        font-size: 0.875rem;
        color: #4b5563;
        line-height: 1.6;
        margin-bottom: 1rem;
        padding: 1rem;
        background: white;
        border-radius: 0.5rem;
        border-right: 3px solid #3b82f6;
    }
    
    .blog-comment-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .blog-comment-action-btn {
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
    
    .blog-comment-action-btn.approve {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
        color: white;
    }
    
    .blog-comment-action-btn.approve:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    .blog-comment-action-btn.reject {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
    }
    
    .blog-comment-action-btn.reject:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    }
    
    .blog-comment-action-btn.delete {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
    }
    
    .blog-comment-action-btn.delete:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
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
    
    .blog-badge.danger {
        background-color: #fee2e2;
        color: #991b1b;
    }
    
    .blog-error-alert {
        background: #fee2e2;
        border: 1px solid #fecaca;
        color: #991b1b;
        padding: 1rem;
        border-radius: 0.75rem;
        margin-bottom: 2rem;
    }
    
    .blog-error-alert ul {
        margin: 0;
        padding-right: 1.5rem;
    }
    
    .blog-error-alert li {
        margin-bottom: 0.5rem;
    }
    
    @media (prefers-color-scheme: dark) {
        .blog-comments-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3) !important;
        }
        
        .blog-comments-header h3 {
            color: #f1f5f9 !important;
        }
        
        .blog-comments-filter-select {
            background: #334155 !important;
            border-color: #475569 !important;
            color: #f1f5f9 !important;
        }
        
        .blog-comments-filter-select:focus {
            border-color: #3b82f6 !important;
        }
        
        .blog-comment-item {
            background: #334155 !important;
            border-color: #475569 !important;
        }
        
        .blog-comment-item:hover {
            background: #475569 !important;
            border-color: #64748b !important;
        }
        
        .blog-comment-user-name {
            color: #f1f5f9 !important;
        }
        
        .blog-comment-meta {
            color: #94a3b8 !important;
        }
        
        .blog-comment-content {
            background: #1e293b !important;
            color: #cbd5e1 !important;
            border-right-color: #3b82f6 !important;
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
        
        .blog-badge.danger {
            background-color: #450a0a !important;
            color: #fecaca !important;
        }
        
        .blog-error-alert {
            background: #450a0a !important;
            border-color: #b91c1c !important;
            color: #fecaca !important;
        }
    }
    
    @media (max-width: 768px) {
        .blog-comments-card {
            padding: 1rem;
        }
        
        .blog-comments-header {
            flex-direction: column;
            align-items: stretch;
        }
        
        .blog-comments-filter {
            flex-direction: column;
        }
        
        .blog-comments-filter-select {
            width: 100%;
        }
        
        .blog-comment-header {
            flex-direction: column;
        }
        
        .blog-comment-actions {
            flex-direction: column;
        }
        
        .blog-comment-action-btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <!-- Header -->
    <div class="blog-comments-card">
        <div class="blog-comments-header">
            <h3>
                <i class="fas fa-comments ml-2"></i>
                مدیریت نظرات
            </h3>
        </div>
        
        <!-- Filter -->
        <form action="{{ route('admin.blog.comments') }}" method="GET" class="blog-comments-filter">
            <select name="status" class="blog-comments-filter-select" onchange="this.form.submit()">
                <option value="">همه وضعیت‌ها</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>در انتظار</option>
                <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>تایید شده</option>
                <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>رد شده</option>
            </select>
        </form>
    </div>
    
    <!-- Comments List -->
    <div class="blog-comments-card">
        @forelse($comments as $comment)
        <div class="blog-comment-item">
            <div class="blog-comment-header">
                <div class="blog-comment-user">
                    <div class="blog-comment-avatar">
                        {{ mb_substr($comment->user->name, 0, 1) }}
                    </div>
                    <div class="blog-comment-user-info">
                        <div class="blog-comment-user-name">{{ $comment->user->name }}</div>
                        <div class="blog-comment-meta">
                            {{ $comment->created_at->diffForHumans() }}
                            @if($comment->post)
                            • در مقاله: 
                            <a href="{{ route('blog.show', $comment->post->slug) }}" class="blog-comment-post" target="_blank">
                                {{ Str::limit($comment->post->title, 40) }}
                            </a>
                            @endif
                        </div>
                    </div>
                </div>
                <div>
                    @if($comment->status == 'pending')
                    <span class="blog-badge warning">در انتظار</span>
                    @elseif($comment->status == 'approved')
                    <span class="blog-badge success">تایید شده</span>
                    @else
                    <span class="blog-badge danger">رد شده</span>
                    @endif
                </div>
            </div>
            
            <div class="blog-comment-content">
                {{ $comment->content }}
            </div>
            
            <div class="blog-comment-actions">
                @if($comment->status != 'approved')
                <form action="{{ route('admin.blog.comments.approve', $comment) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="blog-comment-action-btn approve">
                        <i class="fas fa-check"></i>
                        تایید
                    </button>
                </form>
                @endif
                
                @if($comment->status != 'rejected')
                <form action="{{ route('admin.blog.comments.reject', $comment) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="blog-comment-action-btn reject">
                        <i class="fas fa-times"></i>
                        رد
                    </button>
                </form>
                @endif
                
                <form action="{{ route('admin.blog.comments.delete', $comment) }}" 
                      method="POST" 
                      class="d-inline"
                      onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این نظر را حذف کنید؟')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="blog-comment-action-btn delete">
                        <i class="fas fa-trash"></i>
                        حذف
                    </button>
                </form>
            </div>
        </div>
        @empty
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-comments"></i>
            </div>
            <div class="empty-state-title">نظری یافت نشد</div>
            <div class="empty-state-text">هنوز هیچ نظری ثبت نشده است.</div>
        </div>
        @endforelse
        
        @if($comments->hasPages())
        <div class="mt-4">
            {{ $comments->links('pagination::tailwind') }}
        </div>
        @endif
    </div>
</div>
@endsection

