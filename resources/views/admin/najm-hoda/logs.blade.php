@extends('layouts.admin')

@section('title', 'لاگ‌ها - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'لاگ‌های سیستم نجم‌هدا')
@section('page-description', 'مشاهده و مدیریت لاگ‌های سیستم')

@push('styles')
<style>
    .logs-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .logs-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 12px 12px 0 0;
        margin: -2rem -2rem 1.5rem -2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .logs-header h3 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .logs-actions {
        display: flex;
        gap: 0.75rem;
    }
    
    .btn-modern {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border: none;
        text-decoration: none;
    }
    
    .btn-modern.primary {
        background: white;
        color: #667eea;
    }
    
    .btn-modern.primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(255, 255, 255, 0.3);
        color: #667eea;
    }
    
    .btn-modern.danger {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 2px solid white;
    }
    
    .btn-modern.danger:hover {
        background: white;
        color: #ef4444;
        transform: translateY(-2px);
    }
    
    .logs-content {
        background: #1e293b;
        color: #10b981;
        border-radius: 12px;
        padding: 1.5rem;
        font-family: 'Courier New', monospace;
        font-size: 0.875rem;
        line-height: 1.6;
        max-height: 600px;
        overflow-y: auto;
        direction: ltr;
        text-align: left;
    }
    
    .logs-content::-webkit-scrollbar {
        width: 8px;
    }
    
    .logs-content::-webkit-scrollbar-track {
        background: #0f172a;
        border-radius: 4px;
    }
    
    .logs-content::-webkit-scrollbar-thumb {
        background: #475569;
        border-radius: 4px;
    }
    
    .logs-content::-webkit-scrollbar-thumb:hover {
        background: #64748b;
    }
    
    .log-line {
        margin-bottom: 0.5rem;
        padding: 0.25rem 0;
        border-bottom: 1px solid rgba(16, 185, 129, 0.1);
    }
    
    .log-line:last-child {
        border-bottom: none;
    }
    
    .log-line.error {
        color: #ef4444;
    }
    
    .log-line.warning {
        color: #f59e0b;
    }
    
    .log-line.info {
        color: #06b6d4;
    }
    
    .log-line.success {
        color: #10b981;
    }
    
    .log-timestamp {
        color: #64748b;
        margin-left: 0.5rem;
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
    
    .logs-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .log-stat-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .log-stat-icon {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        opacity: 0.8;
    }
    
    .log-stat-value {
        font-size: 1.5rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 0.25rem;
    }
    
    .log-stat-label {
        font-size: 0.875rem;
        color: #64748b;
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-3xl font-bold text-gray-800 mb-2">📜 لاگ‌های سیستم نجم‌هدا</h2>
            <p class="text-gray-600">مشاهده و مدیریت لاگ‌های سیستم</p>
        </div>
        <a href="{{ route('admin.najm-hoda.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors font-semibold flex items-center gap-2">
            <i class="fas fa-arrow-right"></i>
            بازگشت
        </a>
    </div>

    <!-- Logs Stats -->
    <div class="logs-stats">
        <div class="log-stat-card">
            <div class="log-stat-icon">📄</div>
            <div class="log-stat-value">{{ count($logs ?? []) }}</div>
            <div class="log-stat-label">تعداد لاگ‌ها</div>
        </div>
        <div class="log-stat-card">
            <div class="log-stat-icon">⚠️</div>
            <div class="log-stat-value">
                @php
                $errorCount = 0;
                if (isset($logs) && is_array($logs)) {
                    $errorCount = count(array_filter($logs, function($log) { 
                        return is_string($log) && (stripos($log, 'error') !== false || stripos($log, 'exception') !== false); 
                    }));
                }
                @endphp
                {{ $errorCount }}
            </div>
            <div class="log-stat-label">خطاها</div>
        </div>
        <div class="log-stat-card">
            <div class="log-stat-icon">ℹ️</div>
            <div class="log-stat-value">
                @php
                $infoCount = 0;
                if (isset($logs) && is_array($logs)) {
                    $infoCount = count(array_filter($logs, function($log) { 
                        return is_string($log) && stripos($log, 'info') !== false; 
                    }));
                }
                @endphp
                {{ $infoCount }}
            </div>
            <div class="log-stat-label">اطلاعات</div>
        </div>
    </div>

    <!-- Logs Card -->
    <div class="logs-card">
        <div class="logs-header">
            <h3>
                <i class="fas fa-file-alt"></i>
                📜 لاگ‌های سیستم
            </h3>
            <div class="logs-actions">
                <form action="{{ route('admin.najm-hoda.logs.clear') }}" method="POST" style="display: inline;" onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید همه لاگ‌ها را پاک کنید؟');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-modern danger">
                        <i class="fas fa-trash"></i>
                        پاک کردن لاگ‌ها
                    </button>
                </form>
                <button onclick="location.reload()" class="btn-modern primary">
                    <i class="fas fa-sync"></i>
                    به‌روزرسانی
                </button>
            </div>
        </div>
        
        @if(isset($logs) && is_array($logs) && count($logs) > 0)
        <div class="logs-content">
            @foreach($logs as $log)
            @if(is_string($log))
            <div class="log-line {{ 
                stripos($log, 'error') !== false || stripos($log, 'exception') !== false ? 'error' : 
                (stripos($log, 'warning') !== false ? 'warning' : 
                (stripos($log, 'info') !== false ? 'info' : 
                (stripos($log, 'success') !== false ? 'success' : ''))) 
            }}">
                {{ $log }}
            </div>
            @endif
            @endforeach
        </div>
        @else
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-inbox"></i>
            </div>
            <div class="empty-state-title">لاگی یافت نشد</div>
            <div class="empty-state-text">هیچ لاگی در سیستم وجود ندارد.</div>
        </div>
        @endif
    </div>

</div>
@endsection

@push('scripts')
<script>
// Auto-refresh logs every 30 seconds
setInterval(function() {
    // Only refresh if user is still on the page
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 30000);
</script>
@endpush

