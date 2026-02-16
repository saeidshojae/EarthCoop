@extends('layouts.admin')

@section('title', 'داشبورد نجم‌هدا - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'داشبورد نجم‌هدا')
@section('page-description', 'نرم افزار جامع مدیریت هوشمند دنیای ارثکوپ')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css">
<style>
    .najm-stats-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 1.5rem;
        transition: all 0.3s ease;
        border-top: 4px solid;
        height: 100%;
    }
    
    .najm-stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    }
    
    .najm-stats-card.primary {
        border-top-color: #3b82f6;
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
    }
    
    .najm-stats-card.success {
        border-top-color: #10b981;
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
        color: white;
    }
    
    .najm-stats-card.warning {
        border-top-color: #f59e0b;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
    }
    
    .najm-stats-card.info {
        border-top-color: #06b6d4;
        background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
        color: white;
    }
    
    .najm-stats-icon {
        font-size: 2.5rem;
        opacity: 0.8;
        margin-bottom: 1rem;
    }
    
    .najm-stats-value {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }
    
    .najm-stats-label {
        font-size: 0.875rem;
        opacity: 0.9;
        margin-bottom: 0.5rem;
    }
    
    .najm-stats-subtext {
        font-size: 0.75rem;
        opacity: 0.7;
    }
    
    .agent-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        text-align: center;
        transition: all 0.3s ease;
        border: 2px solid #e5e7eb;
        height: 100%;
    }
    
    .agent-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        border-color: #667eea;
    }
    
    .agent-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
    }
    
    .agent-name {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 0.5rem;
    }
    
    .agent-description {
        font-size: 0.875rem;
        color: #64748b;
        margin-bottom: 1rem;
    }
    
    .agent-usage {
        display: inline-block;
        padding: 0.5rem 1rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .quick-link-card {
        background: white;
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 0.75rem;
        transition: all 0.3s ease;
        border: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: space-between;
        text-decoration: none;
        color: inherit;
    }
    
    .quick-link-card:hover {
        transform: translateX(-5px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        border-color: #667eea;
        color: inherit;
    }
    
    .quick-link-icon {
        font-size: 1.5rem;
        margin-left: 1rem;
        color: #667eea;
    }
    
    .quick-link-text {
        flex: 1;
        font-weight: 600;
        color: #1e293b;
    }
    
    .quick-link-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .quick-link-badge.success {
        background: #10b981;
        color: white;
    }
    
    .quick-link-badge.danger {
        background: #ef4444;
        color: white;
    }
    
    .conversation-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .conversation-table thead {
        background: #f9fafb;
    }
    
    .conversation-table th {
        padding: 1rem;
        text-align: right;
        font-weight: 700;
        color: #1e293b;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .conversation-table td {
        padding: 1rem;
        text-align: right;
        color: #4b5563;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .conversation-table tr:hover {
        background-color: #f9fafb;
    }
    
    .user-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    
    <!-- Header Actions -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div>
            <h2 class="text-3xl font-bold text-gray-800 mb-2">🌟 نجم‌هدا - دستیار هوش مصنوعی</h2>
            <p class="text-gray-600">نرم افزار جامع مدیریت هوشمند دنیای ارثکوپ</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('admin.najm-hoda.chat') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-semibold flex items-center gap-2">
                <i class="fas fa-comment"></i>
                چت با نجم‌هدا
            </a>
            <a href="{{ route('admin.najm-hoda.create-agent') }}" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-semibold flex items-center gap-2">
                <i class="fas fa-plus"></i>
                ساخت عامل جدید
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="najm-stats-card primary">
            <div class="najm-stats-icon">
                <i class="fas fa-comments"></i>
            </div>
            <div class="najm-stats-value">{{ number_format($stats['total_conversations']) }}</div>
            <div class="najm-stats-label">مکالمات</div>
            <div class="najm-stats-subtext">{{ $stats['active_users'] }} کاربر فعال</div>
        </div>
        
        <div class="najm-stats-card success">
            <div class="najm-stats-icon">
                <i class="fas fa-robot"></i>
            </div>
            <div class="najm-stats-value">{{ number_format($stats['total_interactions']) }}</div>
            <div class="najm-stats-label">تعاملات AI</div>
            <div class="najm-stats-subtext">امروز: {{ $todayInteractions }}</div>
        </div>
        
        <div class="najm-stats-card warning">
            <div class="najm-stats-icon">
                <i class="fas fa-star"></i>
            </div>
            <div class="najm-stats-value">{{ number_format($stats['avg_rating'], 1) }}/5</div>
            <div class="najm-stats-label">میانگین رضایت</div>
            <div class="najm-stats-subtext">{{ $stats['total_feedbacks'] }} بازخورد</div>
        </div>
        
        <div class="najm-stats-card info">
            <div class="najm-stats-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="najm-stats-value">${{ number_format($stats['total_cost'], 2) }}</div>
            <div class="najm-stats-label">هزینه کل</div>
            <div class="najm-stats-subtext">{{ number_format($stats['total_tokens']) }} توکن</div>
        </div>
    </div>

    <!-- Agents Section -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h3 class="text-xl font-bold text-gray-800 mb-4">🤖 عوامل نجم‌هدا</h3>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="agent-card">
                <div class="agent-icon">🔧</div>
                <div class="agent-name">مهندس</div>
                <div class="agent-description">طراحی و کدنویسی</div>
                <span class="agent-usage">{{ $agentUsage['engineer'] ?? 0 }} استفاده</span>
            </div>
            <div class="agent-card">
                <div class="agent-icon">✈️</div>
                <div class="agent-name">خلبان</div>
                <div class="agent-description">مدیریت پروژه</div>
                <span class="agent-usage">{{ $agentUsage['pilot'] ?? 0 }} استفاده</span>
            </div>
            <div class="agent-card">
                <div class="agent-icon">👨‍✈️</div>
                <div class="agent-name">مهماندار</div>
                <div class="agent-description">پشتیبانی کاربران</div>
                <span class="agent-usage">{{ $agentUsage['steward'] ?? 0 }} استفاده</span>
            </div>
            <div class="agent-card">
                <div class="agent-icon">📖</div>
                <div class="agent-name">راهنما</div>
                <div class="agent-description">استراتژی</div>
                <span class="agent-usage">{{ $agentUsage['guide'] ?? 0 }} استفاده</span>
            </div>
            <div class="agent-card">
                <div class="agent-icon">🏗️</div>
                <div class="agent-name">معمار</div>
                <div class="agent-description">ساخت عوامل</div>
                <span class="agent-usage">{{ $agentUsage['architect'] ?? 0 }} استفاده</span>
            </div>
        </div>
    </div>

    <!-- Charts and Quick Links -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Weekly Usage Chart -->
        <div class="lg:col-span-2 bg-white rounded-lg shadow-md p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">📊 استفاده هفتگی</h3>
            <canvas id="weeklyUsageChart" height="100"></canvas>
        </div>
        
        <!-- Quick Links -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">🎯 دسترسی سریع</h3>
            <div class="space-y-2">
                <a href="{{ route('admin.najm-hoda.code-scanner') }}" class="quick-link-card">
                    <i class="fas fa-search quick-link-icon"></i>
                    <span class="quick-link-text">اسکنر کد</span>
                    <span class="quick-link-badge success">پکیج 2</span>
                </a>
                <a href="{{ route('admin.najm-hoda.auto-fixer-settings') }}" class="quick-link-card">
                    <i class="fas fa-magic quick-link-icon"></i>
                    <span class="quick-link-text">کمک خلبان هوشمند</span>
                    <span class="quick-link-badge danger">پکیج 3</span>
                </a>
                <a href="{{ route('admin.najm-hoda.conversations') }}" class="quick-link-card">
                    <i class="fas fa-comments quick-link-icon"></i>
                    <span class="quick-link-text">مکالمات</span>
                </a>
                <a href="{{ route('admin.najm-hoda.analytics') }}" class="quick-link-card">
                    <i class="fas fa-chart-line quick-link-icon"></i>
                    <span class="quick-link-text">تحلیل‌ها</span>
                </a>
                <a href="{{ route('admin.najm-hoda.feedbacks') }}" class="quick-link-card">
                    <i class="fas fa-star quick-link-icon"></i>
                    <span class="quick-link-text">بازخوردها</span>
                </a>
                <a href="{{ route('admin.najm-hoda.group-action-items') }}" class="quick-link-card">
                    <i class="fas fa-list-check quick-link-icon"></i>
                    <span class="quick-link-text">برد مصوبات گروهی</span>
                </a>
                <a href="{{ route('admin.najm-hoda.settings') }}" class="quick-link-card">
                    <i class="fas fa-cog quick-link-icon"></i>
                    <span class="quick-link-text">تنظیمات</span>
                </a>
                <a href="{{ route('admin.najm-hoda.logs') }}" class="quick-link-card">
                    <i class="fas fa-file-alt quick-link-icon"></i>
                    <span class="quick-link-text">لاگ‌ها</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Conversations -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">💬 مکالمات اخیر</h3>
            <a href="{{ route('admin.najm-hoda.conversations') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-semibold">
                مشاهده همه
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="conversation-table">
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
                    @forelse($recentConversations as $conversation)
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <img src="{{ $conversation->user->avatar ?? '/images/default-avatar.png' }}" 
                                     class="user-avatar" 
                                     alt="{{ $conversation->user->name }}">
                                <span>{{ $conversation->user->name }}</span>
                            </div>
                        </td>
                        <td>{{ Str::limit($conversation->title, 50) }}</td>
                        <td>
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-semibold">
                                {{ $conversation->messages_count ?? 0 }}
                            </span>
                        </td>
                        <td>{{ $conversation->updated_at->diffForHumans() }}</td>
                        <td>
                            <a href="{{ route('admin.najm-hoda.conversations.show', $conversation) }}" 
                               class="px-3 py-1 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-semibold">
                                <i class="fas fa-eye ml-1"></i>
                                مشاهده
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-gray-500 py-8">
                            <i class="fas fa-inbox text-4xl mb-2 opacity-50"></i>
                            <p>هنوز مکالمه‌ای ثبت نشده است</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// نمودار استفاده هفتگی
const ctx = document.getElementById('weeklyUsageChart');
if (ctx) {
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'],
            datasets: [{
                label: 'تعاملات',
                data: [{{ implode(',', $weekInteractions) }}],
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
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
                    beginAtZero: true
                }
            }
        }
    });
}
</script>
@endpush
