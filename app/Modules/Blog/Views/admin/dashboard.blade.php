@extends('layouts.admin')

@section('title', 'داشبورد وبلاگ - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'داشبورد وبلاگ')
@section('page-description', 'مدیریت و نظارت بر وبلاگ و محتوای آن')

@push('styles')
<style>
    .blog-stats-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 1.5rem;
        transition: all 0.3s ease;
        border-top: 4px solid;
        height: 100%;
        position: relative;
        overflow: hidden;
    }
    
    .blog-stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    }
    
    .blog-stats-card.primary { border-top-color: #3b82f6; }
    .blog-stats-card.success { border-top-color: #10b981; }
    .blog-stats-card.warning { border-top-color: #f59e0b; }
    .blog-stats-card.info { border-top-color: #06b6d4; }
    .blog-stats-card.purple { border-top-color: #8b5cf6; }
    .blog-stats-card.pink { border-top-color: #ec4899; }
    
    .blog-stats-icon {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        opacity: 0.8;
    }
    
    .blog-stats-value {
        font-size: 2rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 0.25rem;
    }
    
    .blog-stats-label {
        font-size: 0.875rem;
        color: #64748b;
        font-weight: 600;
        margin-bottom: 0.75rem;
    }
    
    .blog-stats-link {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: #3b82f6;
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
        transition: color 0.2s ease;
    }
    
    .blog-stats-link:hover {
        color: #2563eb;
    }
    
    .blog-table-card, .blog-list-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .blog-table-header, .blog-list-header {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
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
    }
    
    .blog-table td {
        padding: 1rem;
        text-align: right;
        color: #4b5563;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .blog-table tr:hover {
        background-color: #f9fafb;
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
    
    .blog-comment-item {
        padding: 1rem;
        border-bottom: 1px solid #e5e7eb;
        transition: background-color 0.2s ease;
    }
    
    .blog-comment-item:hover {
        background-color: #f9fafb;
    }
    
    .blog-comment-item:last-child {
        border-bottom: none;
    }
    
    .blog-comment-user {
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.5rem;
    }
    
    .blog-comment-content {
        font-size: 0.875rem;
        color: #4b5563;
        margin-bottom: 0.5rem;
        line-height: 1.6;
    }
    
    .blog-comment-meta {
        font-size: 0.75rem;
        color: #64748b;
    }
    
    @media (prefers-color-scheme: dark) {
        .blog-stats-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3) !important;
        }
        
        .blog-stats-value {
            color: #f1f5f9 !important;
        }
        
        .blog-stats-label {
            color: #cbd5e1 !important;
        }
        
        .blog-stats-link {
            color: #60a5fa !important;
        }
        
        .blog-stats-link:hover {
            color: #3b82f6 !important;
        }
        
        .blog-table-card, .blog-list-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3) !important;
        }
        
        .blog-table-header, .blog-list-header {
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
        
        .blog-comment-item {
            border-bottom-color: #334155 !important;
        }
        
        .blog-comment-item:hover {
            background-color: #334155 !important;
        }
        
        .blog-comment-user {
            color: #f1f5f9 !important;
        }
        
        .blog-comment-content {
            color: #cbd5e1 !important;
        }
        
        .blog-comment-meta {
            color: #94a3b8 !important;
        }
    }
    
    @media (max-width: 768px) {
        .blog-stats-card {
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .blog-stats-icon {
            font-size: 2rem;
        }
        
        .blog-stats-value {
            font-size: 1.5rem;
        }
        
        .blog-table-card, .blog-list-card {
            padding: 1rem;
        }
        
        .blog-table {
            font-size: 0.875rem;
        }
        
        .blog-table th, .blog-table td {
            padding: 0.5rem;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="blog-stats-card primary">
            <div class="blog-stats-icon text-blue-600">
                <i class="fas fa-newspaper"></i>
            </div>
            <div class="blog-stats-value">{{ number_format($totalPosts) }}</div>
            <div class="blog-stats-label">کل مقالات</div>
            <a href="{{ route('admin.blog.posts') }}" class="blog-stats-link">
                مشاهده همه <i class="fas fa-arrow-left"></i>
            </a>
        </div>
        
        <div class="blog-stats-card success">
            <div class="blog-stats-icon text-green-600">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="blog-stats-value">{{ number_format($publishedPosts) }}</div>
            <div class="blog-stats-label">منتشر شده</div>
            <a href="{{ route('admin.blog.posts', ['status' => 'published']) }}" class="blog-stats-link">
                مشاهده <i class="fas fa-arrow-left"></i>
            </a>
        </div>
        
        <div class="blog-stats-card warning">
            <div class="blog-stats-icon text-yellow-600">
                <i class="fas fa-edit"></i>
            </div>
            <div class="blog-stats-value">{{ number_format($draftPosts) }}</div>
            <div class="blog-stats-label">پیش‌نویس</div>
            <a href="{{ route('admin.blog.posts', ['status' => 'draft']) }}" class="blog-stats-link">
                مشاهده <i class="fas fa-arrow-left"></i>
            </a>
        </div>
        
        <div class="blog-stats-card info">
            <div class="blog-stats-icon text-cyan-600">
                <i class="fas fa-comments"></i>
            </div>
            <div class="blog-stats-value">{{ number_format($pendingComments) }}</div>
            <div class="blog-stats-label">نظرات در انتظار</div>
            <a href="{{ route('admin.blog.comments', ['status' => 'pending']) }}" class="blog-stats-link">
                مشاهده <i class="fas fa-arrow-left"></i>
            </a>
        </div>
    </div>
    
    <!-- Secondary Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="blog-stats-card purple">
            <div class="blog-stats-icon text-purple-600">
                <i class="fas fa-folder"></i>
            </div>
            <div class="blog-stats-value">{{ number_format($totalCategories) }}</div>
            <div class="blog-stats-label">دسته‌بندی‌ها</div>
            <a href="{{ route('admin.blog.categories') }}" class="blog-stats-link">
                مدیریت دسته‌بندی‌ها <i class="fas fa-arrow-left"></i>
            </a>
        </div>
        
        <div class="blog-stats-card pink">
            <div class="blog-stats-icon text-pink-600">
                <i class="fas fa-tags"></i>
            </div>
            <div class="blog-stats-value">{{ number_format($totalTags) }}</div>
            <div class="blog-stats-label">برچسب‌ها</div>
            <a href="{{ route('admin.blog.tags') }}" class="blog-stats-link">
                مدیریت برچسب‌ها <i class="fas fa-arrow-left"></i>
            </a>
        </div>
        
        <div class="blog-stats-card info">
            <div class="blog-stats-icon text-cyan-600">
                <i class="fas fa-comment-dots"></i>
            </div>
            <div class="blog-stats-value">{{ number_format($totalComments) }}</div>
            <div class="blog-stats-label">کل نظرات</div>
            <a href="{{ route('admin.blog.comments') }}" class="blog-stats-link">
                مدیریت نظرات <i class="fas fa-arrow-left"></i>
            </a>
        </div>
    </div>
    
    <!-- Recent Posts & Comments -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="blog-table-card">
                <h3 class="blog-table-header">
                    <i class="fas fa-newspaper"></i>
                    آخرین مقالات
                </h3>
                <div class="overflow-x-auto">
                    <table class="blog-table">
                        <thead>
                            <tr>
                                <th>عنوان</th>
                                <th>دسته‌بندی</th>
                                <th>وضعیت</th>
                                <th>تاریخ</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentPosts as $post)
                            <tr>
                                <td>{{ Str::limit($post->title, 40) }}</td>
                                <td>
                                    <span class="blog-badge primary">{{ $post->category->name }}</span>
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
                                <td>{{ $post->created_at->format('Y/m/d') }}</td>
                                <td>
                                    <a href="{{ route('admin.blog.posts.edit', $post) }}" 
                                       class="text-blue-600 hover:text-blue-800 transition-colors">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-gray-500 py-4">مقاله‌ای یافت نشد.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="lg:col-span-1">
            <div class="blog-list-card">
                <h3 class="blog-list-header">
                    <i class="fas fa-comments"></i>
                    آخرین نظرات
                </h3>
                <div class="space-y-0">
                    @forelse($recentComments as $comment)
                    <div class="blog-comment-item">
                        <div class="flex justify-between items-start mb-2">
                            <strong class="blog-comment-user">{{ $comment->user->name }}</strong>
                            @if($comment->status == 'pending')
                            <span class="blog-badge warning">در انتظار</span>
                            @elseif($comment->status == 'approved')
                            <span class="blog-badge success">تایید شده</span>
                            @else
                            <span class="blog-badge secondary">رد شده</span>
                            @endif
                        </div>
                        <p class="blog-comment-content">{{ Str::limit($comment->content, 60) }}</p>
                        <small class="blog-comment-meta">{{ $comment->created_at->diffForHumans() }}</small>
                    </div>
                    @empty
                    <div class="text-center text-gray-500 py-4">نظری یافت نشد.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
