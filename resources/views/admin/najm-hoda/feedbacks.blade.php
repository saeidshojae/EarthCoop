@extends('layouts.admin')

@section('title', 'بازخوردها - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'بازخوردها')
@section('page-description', 'نظرات و رتبه‌بندی کاربران از نجم‌هدا')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css">
<style>
    .feedbacks-stats-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
        text-align: center;
    }
    
    .feedbacks-stats-card.success {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
        color: white;
    }
    
    .feedbacks-stats-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.9;
    }
    
    .feedbacks-stats-value {
        font-size: 3rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }
    
    .feedbacks-stats-label {
        font-size: 1rem;
        opacity: 0.9;
    }
    
    .rating-chart-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .rating-chart-header {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .feedbacks-list-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .feedbacks-list-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 12px 12px 0 0;
        margin: -2rem -2rem 1.5rem -2rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .feedbacks-list-header h3 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
    }
    
    .feedback-item {
        background: #f9fafb;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        border-right: 4px solid;
        transition: all 0.3s ease;
    }
    
    .feedback-item:hover {
        transform: translateX(-5px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    
    .feedback-item.rating-5 {
        border-right-color: #10b981;
        background: #f0fdf4;
    }
    
    .feedback-item.rating-4 {
        border-right-color: #06b6d4;
        background: #ecfeff;
    }
    
    .feedback-item.rating-3 {
        border-right-color: #f59e0b;
        background: #fffbeb;
    }
    
    .feedback-item.rating-2 {
        border-right-color: #f59e0b;
        background: #fef3c7;
    }
    
    .feedback-item.rating-1 {
        border-right-color: #ef4444;
        background: #fef2f2;
    }
    
    .feedback-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }
    
    .feedback-user {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .feedback-user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
    }
    
    .feedback-user-info {
        flex: 1;
    }
    
    .feedback-user-name {
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 0.25rem;
    }
    
    .feedback-date {
        font-size: 0.875rem;
        color: #64748b;
    }
    
    .feedback-rating {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .feedback-rating-stars {
        display: flex;
        gap: 0.25rem;
    }
    
    .feedback-rating-star {
        font-size: 1.25rem;
        color: #fbbf24;
    }
    
    .feedback-rating-star.empty {
        color: #d1d5db;
    }
    
    .feedback-rating-value {
        font-weight: 700;
        font-size: 1.25rem;
        color: #1e293b;
    }
    
    .feedback-content {
        color: #4b5563;
        line-height: 1.6;
        margin-bottom: 0.75rem;
    }
    
    .feedback-interaction {
        font-size: 0.875rem;
        color: #64748b;
        padding-top: 0.75rem;
        border-top: 1px solid #e5e7eb;
    }
    
    .feedback-interaction-link {
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.3s ease;
    }
    
    .feedback-interaction-link:hover {
        color: #764ba2;
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
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-3xl font-bold text-gray-800 mb-2">⭐ بازخوردها</h2>
            <p class="text-gray-600">نظرات و رتبه‌بندی کاربران از نجم‌هدا</p>
        </div>
        <a href="{{ route('admin.najm-hoda.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors font-semibold flex items-center gap-2">
            <i class="fas fa-arrow-right"></i>
            بازگشت
        </a>
    </div>

    <!-- Average Rating Card -->
    <div class="feedbacks-stats-card success">
        <div class="feedbacks-stats-icon">
            <i class="fas fa-star"></i>
        </div>
        <div class="feedbacks-stats-value">{{ number_format($avgRating ?? 0, 1) }}/5</div>
        <div class="feedbacks-stats-label">میانگین رتبه‌بندی</div>
        <div class="text-sm opacity-75 mt-2">
            از {{ $feedbacks->total() }} بازخورد
        </div>
    </div>

    <!-- Rating Distribution Chart -->
    <div class="rating-chart-card">
        <div class="rating-chart-header">
            <i class="fas fa-chart-bar"></i>
            📊 توزیع رتبه‌بندی
        </div>
        <canvas id="ratingChart" height="80"></canvas>
    </div>

    <!-- Feedbacks List -->
    <div class="feedbacks-list-card">
        <div class="feedbacks-list-header">
            <i class="fas fa-comments"></i>
            <h3>💬 لیست بازخوردها</h3>
        </div>
        
        <div class="space-y-4">
            @forelse($feedbacks as $feedback)
            <div class="feedback-item rating-{{ $feedback->rating }}">
                <div class="feedback-header">
                    <div class="feedback-user">
                        <img src="{{ $feedback->user->avatar ?? '/images/default-avatar.png' }}" 
                             class="feedback-user-avatar" 
                             alt="{{ $feedback->user->name }}">
                        <div class="feedback-user-info">
                            <div class="feedback-user-name">{{ $feedback->user->name }}</div>
                            <div class="feedback-date">{{ $feedback->created_at->diffForHumans() }} - {{ $feedback->created_at->format('Y/m/d H:i') }}</div>
                        </div>
                    </div>
                    <div class="feedback-rating">
                        <div class="feedback-rating-stars">
                            @for($i = 1; $i <= 5; $i++)
                            <span class="feedback-rating-star {{ $i <= $feedback->rating ? '' : 'empty' }}">
                                <i class="fas fa-star"></i>
                            </span>
                            @endfor
                        </div>
                        <span class="feedback-rating-value">{{ $feedback->rating }}/5</span>
                    </div>
                </div>
                
                @if($feedback->comment)
                <div class="feedback-content">
                    {{ $feedback->comment }}
                </div>
                @endif
                
                @if($feedback->interaction)
                <div class="feedback-interaction">
                    <a href="#" class="feedback-interaction-link">
                        <i class="fas fa-link ml-1"></i>
                        مشاهده تعامل مرتبط
                    </a>
                </div>
                @endif
            </div>
            @empty
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-inbox"></i>
                </div>
                <div class="empty-state-title">هنوز بازخوردی ثبت نشده است</div>
                <div class="empty-state-text">هیچ بازخوردی در سیستم وجود ندارد.</div>
            </div>
            @endforelse
        </div>
        
        <!-- Pagination -->
        @if($feedbacks->hasPages())
        <div class="pagination">
            @if($feedbacks->onFirstPage())
            <span class="pagination-link disabled">
                <i class="fas fa-chevron-right"></i>
                قبلی
            </span>
            @else
            <a href="{{ $feedbacks->previousPageUrl() }}" class="pagination-link">
                <i class="fas fa-chevron-right"></i>
                قبلی
            </a>
            @endif
            
            @foreach($feedbacks->getUrlRange(1, $feedbacks->lastPage()) as $page => $url)
            @if($page == $feedbacks->currentPage())
            <span class="pagination-link active">{{ $page }}</span>
            @else
            <a href="{{ $url }}" class="pagination-link">{{ $page }}</a>
            @endif
            @endforeach
            
            @if($feedbacks->hasMorePages())
            <a href="{{ $feedbacks->nextPageUrl() }}" class="pagination-link">
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
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// نمودار توزیع رتبه‌بندی
const ratingChartElement = document.getElementById('ratingChart');
if (ratingChartElement) {
    const ratingData = {
        labels: ['5 ستاره', '4 ستاره', '3 ستاره', '2 ستاره', '1 ستاره'],
        datasets: [{
            label: 'تعداد بازخوردها',
            data: [
                {{ $ratingDistribution[5] ?? 0 }},
                {{ $ratingDistribution[4] ?? 0 }},
                {{ $ratingDistribution[3] ?? 0 }},
                {{ $ratingDistribution[2] ?? 0 }},
                {{ $ratingDistribution[1] ?? 0 }}
            ],
            backgroundColor: [
                'rgba(16, 185, 129, 0.8)',
                'rgba(6, 182, 212, 0.8)',
                'rgba(245, 158, 11, 0.8)',
                'rgba(245, 158, 11, 0.8)',
                'rgba(239, 68, 68, 0.8)'
            ],
            borderColor: [
                '#10b981',
                '#06b6d4',
                '#f59e0b',
                '#f59e0b',
                '#ef4444'
            ],
            borderWidth: 2
        }]
    };
    
    new Chart(ratingChartElement, {
        type: 'bar',
        data: ratingData,
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}
</script>
@endpush

