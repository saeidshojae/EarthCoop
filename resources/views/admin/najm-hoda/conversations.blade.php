@extends('layouts.admin')

@section('title', 'مکالمات نجم‌هدا - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'مکالمات نجم‌هدا')
@section('page-description', 'مدیریت و مشاهده مکالمات کاربران با نجم‌هدا')

@push('styles')
<style>
    .conversations-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .conversations-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 12px 12px 0 0;
        margin: -2rem -2rem 1.5rem -2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .conversations-header h3 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .conversations-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .conversations-table thead {
        background: #f9fafb;
    }
    
    .conversations-table th {
        padding: 1rem;
        text-align: right;
        font-weight: 700;
        color: #1e293b;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .conversations-table td {
        padding: 1rem;
        text-align: right;
        color: #4b5563;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .conversations-table tr:hover {
        background-color: #f9fafb;
    }
    
    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        margin-left: 0.75rem;
    }
    
    .user-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .user-name {
        font-weight: 600;
        color: #1e293b;
    }
    
    .conversation-title {
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.25rem;
    }
    
    .conversation-preview {
        font-size: 0.875rem;
        color: #64748b;
        margin-top: 0.25rem;
    }
    
    .badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .badge.info {
        background: #06b6d4;
        color: white;
    }
    
    .badge.success {
        background: #10b981;
        color: white;
    }
    
    .badge.warning {
        background: #f59e0b;
        color: white;
    }
    
    .badge.danger {
        background: #ef4444;
        color: white;
    }
    
    .btn-view {
        padding: 0.5rem 1rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
    }
    
    .btn-view:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        color: white;
    }
    
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
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
    
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.5rem;
        margin-top: 2rem;
    }
    
    .pagination-link {
        padding: 0.5rem 1rem;
        background: white;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        color: #1e293b;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .pagination-link:hover {
        border-color: #667eea;
        color: #667eea;
    }
    
    .pagination-link.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-color: transparent;
    }
    
    .pagination-link.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .pagination-link.disabled:hover {
        border-color: #e5e7eb;
        color: #1e293b;
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-3xl font-bold text-gray-800 mb-2">💬 مکالمات نجم‌هدا</h2>
            <p class="text-gray-600">مدیریت و مشاهده مکالمات کاربران با نجم‌هدا</p>
        </div>
        <a href="{{ route('admin.najm-hoda.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors font-semibold flex items-center gap-2">
            <i class="fas fa-arrow-right"></i>
            بازگشت
        </a>
    </div>

    <!-- Conversations Card -->
    <div class="conversations-card">
        <div class="conversations-header">
            <h3>
                <i class="fas fa-comments"></i>
                📋 لیست مکالمات
            </h3>
            <div class="text-white text-sm">
                مجموع: {{ $conversations->total() }} مکالمه
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="conversations-table">
                <thead>
                    <tr>
                        <th>کاربر</th>
                        <th>عنوان</th>
                        <th>تعداد پیام</th>
                        <th>آخرین فعالیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($conversations as $conversation)
                    <tr>
                        <td>
                            <div class="user-info">
                                <img src="{{ $conversation->user->avatar ?? '/images/default-avatar.png' }}" 
                                     class="user-avatar" 
                                     alt="{{ $conversation->user->name }}">
                                <span class="user-name">{{ $conversation->user->name }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="conversation-title">{{ Str::limit($conversation->title, 50) }}</div>
                            @if($conversation->messages_count > 0 && $conversation->messages->isNotEmpty())
                            <div class="conversation-preview">
                                {{ Str::limit($conversation->messages->last()->message ?? '', 30) }}
                            </div>
                            @endif
                        </td>
                        <td>
                            <span class="badge info">{{ $conversation->messages_count ?? 0 }}</span>
                        </td>
                        <td>
                            <div class="text-sm text-gray-600">
                                {{ $conversation->updated_at->diffForHumans() }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ $conversation->updated_at->format('Y/m/d H:i') }}
                            </div>
                        </td>
                        <td>
                            <a href="{{ route('admin.najm-hoda.conversations.show', $conversation) }}" 
                               class="btn-view">
                                <i class="fas fa-eye"></i>
                                مشاهده
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-inbox"></i>
                            </div>
                            <div class="empty-state-title">هنوز مکالمه‌ای ثبت نشده است</div>
                            <div class="empty-state-text">هیچ مکالمه‌ای در سیستم وجود ندارد.</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        @if($conversations->hasPages())
        <div class="pagination">
            @if($conversations->onFirstPage())
            <span class="pagination-link disabled">
                <i class="fas fa-chevron-right"></i>
                قبلی
            </span>
            @else
            <a href="{{ $conversations->previousPageUrl() }}" class="pagination-link">
                <i class="fas fa-chevron-right"></i>
                قبلی
            </a>
            @endif
            
            @foreach($conversations->getUrlRange(1, $conversations->lastPage()) as $page => $url)
            @if($page == $conversations->currentPage())
            <span class="pagination-link active">{{ $page }}</span>
            @else
            <a href="{{ $url }}" class="pagination-link">{{ $page }}</a>
            @endif
            @endforeach
            
            @if($conversations->hasMorePages())
            <a href="{{ $conversations->nextPageUrl() }}" class="pagination-link">
                بعدی
                <i class="fas fa-chevron-left"></i>
            </a>
            @else
            <span class="pagination-link disabled">
                بعدی
                <i class="fas fa-chevron-left"></i>
            </span>
            @endif
        </div>
        @endif
    </div>

</div>
@endsection

@push('scripts')
<script>
// Add any additional JavaScript if needed
</script>
@endpush

