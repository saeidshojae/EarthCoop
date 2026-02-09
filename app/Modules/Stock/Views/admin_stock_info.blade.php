@extends('layouts.admin')

@section('title', 'مدیریت سهام استارتاپ - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'مدیریت سهام استارتاپ')
@section('page-description', 'مشاهده و مدیریت اطلاعات پایه سهام')

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
    .stock-management-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .stock-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stock-stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        padding: 1.5rem;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .stock-stat-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    }
    
    .stock-stat-card.success {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
    }
    
    .stock-stat-card.info {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    }
    
    .stock-stat-card.warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
    
    .stock-stat-card.danger {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }
    
    .stock-stat-card.purple {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    }
    
    .stock-stat-icon {
        font-size: 2rem;
        margin-bottom: 0.75rem;
        opacity: 0.9;
        position: relative;
        z-index: 10;
    }
    
    .stock-stat-label {
        font-size: 0.75rem;
        opacity: 0.9;
        margin-bottom: 0.5rem;
        position: relative;
        z-index: 10;
    }
    
    .stock-stat-value {
        font-size: 1.5rem;
        font-weight: 800;
        position: relative;
        z-index: 10;
    }
    
    .stock-chart-container {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .stock-chart-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 1rem;
    }
    
    .alert {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        padding: 1rem 1.5rem;
        margin-bottom: 0.75rem;
    }
    
    .alert-success {
        background: #d1fae5;
        border-color: #10b981;
        color: #065f46;
    }
    
    .alert-warning {
        background: #fef3c7;
        border-color: #f59e0b;
        color: #92400e;
    }
    
    .alert-info {
        background: #dbeafe;
        border-color: #3b82f6;
        color: #1e40af;
    }
    
    .alert-danger {
        background: #fee2e2;
        border-color: #ef4444;
        color: #991b1b;
    }
    
    .stock-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 2px solid #e5e7eb;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .stock-header h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .stock-header-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        align-items: center;
    }
    
    .stock-action-btn {
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
    
    .stock-action-btn.primary {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
    }
    
    .stock-action-btn.primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        color: white;
    }
    
    .stock-action-btn.success {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
        color: white;
    }
    
    .stock-action-btn.success:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        color: white;
    }
    
    .stock-action-btn.info {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
    }
    
    .stock-action-btn.info:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        color: white;
    }
    
    .stock-action-btn.warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
    }
    
    .stock-action-btn.warning:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        color: white;
    }
    
    .stock-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stock-info-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        padding: 1.5rem;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .stock-info-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    }
    
    .stock-info-card.primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .stock-info-card.success {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
    }
    
    .stock-info-card.info {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    }
    
    .stock-info-card.warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
    
    .stock-info-icon {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        opacity: 0.9;
        position: relative;
        z-index: 10;
    }
    
    .stock-info-label {
        font-size: 0.875rem;
        opacity: 0.9;
        margin-bottom: 0.5rem;
        position: relative;
        z-index: 10;
    }
    
    .stock-info-value {
        font-size: 1.5rem;
        font-weight: 800;
        position: relative;
        z-index: 10;
    }
    
    .stock-details-card {
        background: #f9fafb;
        border-radius: 12px;
        padding: 1.5rem;
        margin-top: 2rem;
    }
    
    .stock-details-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 1rem;
    }
    
    .stock-details-content {
        color: #4b5563;
        font-size: 0.875rem;
        line-height: 1.8;
        white-space: pre-wrap;
    }
    
    .stock-empty-state {
        text-align: center;
        padding: 3rem 1rem;
        background: #f9fafb;
        border-radius: 12px;
        border: 2px dashed #e5e7eb;
    }
    
    .stock-empty-icon {
        font-size: 4rem;
        color: #9ca3af;
        margin-bottom: 1rem;
    }
    
    .stock-empty-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 0.5rem;
    }
    
    .stock-empty-description {
        color: #6b7280;
        font-size: 0.875rem;
        margin-bottom: 1.5rem;
    }
    
    @media (prefers-color-scheme: dark) {
        .stock-management-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3) !important;
        }
        
        .stock-header {
            border-bottom-color: #475569 !important;
        }
        
        .stock-header h3 {
            color: #f1f5f9 !important;
        }
        
        .stock-info-card {
            background: linear-gradient(135deg, #4c1d95 0%, #5b21b6 100%) !important;
        }
        
        .stock-info-card.success {
            background: linear-gradient(135deg, #064e3b 0%, #065f46 100%) !important;
        }
        
        .stock-info-card.info {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%) !important;
        }
        
        .stock-info-card.warning {
            background: linear-gradient(135deg, #78350f 0%, #92400e 100%) !important;
        }
        
        .stock-details-card {
            background: #334155 !important;
        }
        
        .stock-details-title {
            color: #f1f5f9 !important;
        }
        
        .stock-details-content {
            color: #cbd5e1 !important;
        }
        
        .stock-empty-state {
            background: #334155 !important;
            border-color: #475569 !important;
        }
        
        .stock-empty-title {
            color: #f1f5f9 !important;
        }
        
        .stock-empty-description {
            color: #94a3b8 !important;
        }
        
        .stock-stat-card {
            background: linear-gradient(135deg, #4c1d95 0%, #5b21b6 100%) !important;
        }
        
        .stock-stat-card.success {
            background: linear-gradient(135deg, #064e3b 0%, #065f46 100%) !important;
        }
        
        .stock-stat-card.info {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%) !important;
        }
        
        .stock-stat-card.warning {
            background: linear-gradient(135deg, #78350f 0%, #92400e 100%) !important;
        }
        
        .stock-stat-card.danger {
            background: linear-gradient(135deg, #7f1d1d 0%, #991b1b 100%) !important;
        }
        
        .stock-stat-card.purple {
            background: linear-gradient(135deg, #581c87 0%, #6b21a8 100%) !important;
        }
        
        .stock-chart-container {
            background: #334155 !important;
        }
        
        .stock-chart-title {
            color: #f1f5f9 !important;
        }
        
        h4 {
            color: #f1f5f9 !important;
        }
        
        .alert {
            background: #334155 !important;
            border-color: #475569 !important;
        }
        
        .alert-success {
            background: #064e3b !important;
            border-color: #10b981 !important;
            color: #a7f3d0 !important;
        }
        
        .alert-warning {
            background: #451a03 !important;
            border-color: #f59e0b !important;
            color: #fed7aa !important;
        }
        
        .alert-info {
            background: #1e3a8a !important;
            border-color: #3b82f6 !important;
            color: #bfdbfe !important;
        }
        
        .alert-danger {
            background: #450a0a !important;
            border-color: #ef4444 !important;
            color: #fecaca !important;
        }
    }
    
    @media (max-width: 768px) {
        .stock-management-card {
            padding: 1rem;
        }
        
        .stock-info-grid {
            grid-template-columns: 1fr;
        }
        
        .stock-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .stock-header-actions {
            width: 100%;
            margin-top: 1rem;
        }
        
        .stock-action-btn {
            flex: 1 1 auto;
            min-width: 140px;
            justify-content: center;
            font-size: 0.875rem;
            padding: 0.625rem 1rem;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <div class="stock-management-card">
        <div class="stock-header">
            <h3>
                <i class="fas fa-chart-line ml-2"></i>
                اطلاعات پایه سهام
            </h3>
            <div class="stock-header-actions">
                <a href="{{ route('admin.stock.gift') }}" class="stock-action-btn success">
                    <i class="fas fa-gift"></i>
                    هدیه دادن سهام
                </a>
                
                <a href="{{ route('admin.stock.shareholders') }}" class="stock-action-btn primary">
                    <i class="fas fa-users"></i>
                    لیست سهامداران
                </a>
                
                <a href="{{ route('admin.auction.create') }}" class="stock-action-btn" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white;">
                    <i class="fas fa-plus-circle"></i>
                    ایجاد حراج جدید
                </a>
                
                <a href="{{ route('admin.auction.index') }}" class="stock-action-btn primary">
                    <i class="fas fa-gavel"></i>
                    مدیریت حراج‌ها
                </a>
                
                <a href="{{ route('admin.stock.create') }}" class="stock-action-btn success">
                    <i class="fas fa-edit"></i>
                    ویرایش اطلاعات
                </a>
                
                <a href="{{ route('admin.stock-reports.auction-performance') }}" class="stock-action-btn info">
                    <i class="fas fa-chart-bar"></i>
                    گزارش‌ها
                </a>
            </div>
        </div>
        
        @if($stock)
        <!-- هشدارها -->
        @if(isset($alerts) && count($alerts) > 0)
        <div style="margin-bottom: 2rem;">
            @foreach($alerts as $alert)
            <div class="alert alert-{{ $alert['type'] }}" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem 1.5rem; border-radius: 0.75rem; margin-bottom: 0.75rem; border-right: 4px solid;">
                <i class="fas {{ $alert['icon'] }}" style="font-size: 1.25rem;"></i>
                <div style="flex: 1;">
                    <strong>{{ $alert['title'] }}</strong>
                    <div style="font-size: 0.875rem; margin-top: 0.25rem;">{{ $alert['message'] }}</div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
        
        <!-- کارت‌های اطلاعات -->
        <div class="stock-info-grid">
            <div class="stock-info-card primary">
                <div class="stock-info-icon">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stock-info-label">ارزش پایه استارتاپ</div>
                <div class="stock-info-value">{{ number_format($stock->startup_valuation * 10) }} <span style="font-size: 0.875rem; opacity: 0.8;">ریال</span></div>
            </div>
            
            <div class="stock-info-card success">
                <div class="stock-info-icon">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="stock-info-label">تعداد کل سهام</div>
                <div class="stock-info-value">{{ number_format($stock->total_shares) }} <span style="font-size: 0.875rem; opacity: 0.8;">سهم</span></div>
            </div>
            
            <div class="stock-info-card info">
                <div class="stock-info-icon">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <div class="stock-info-label">سهام قابل عرضه</div>
                <div class="stock-info-value">{{ number_format($stock->available_shares ?? 0) }} <span style="font-size: 0.875rem; opacity: 0.8;">سهم</span></div>
            </div>
            
            <div class="stock-info-card warning">
                <div class="stock-info-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stock-info-label">ارزش پایه هر سهم</div>
                <div class="stock-info-value">{{ number_format($stock->base_share_price * 10) }} <span style="font-size: 0.875rem; opacity: 0.8;">ریال</span></div>
            </div>
        </div>
        
        @if($stock->info)
        <!-- توضیحات -->
        <div class="stock-details-card">
            <div class="stock-details-title">
                <i class="fas fa-info-circle ml-2"></i>
                توضیحات تکمیلی
            </div>
            <div class="stock-details-content">
                {{ $stock->info }}
            </div>
        </div>
        @endif
        
        @if(isset($stats))
        <!-- آمار تحلیلی -->
        <div style="margin-top: 2rem;">
            <h4 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 1.5rem;">
                <i class="fas fa-chart-bar ml-2"></i>
                آمار و تحلیل‌ها
            </h4>
            
            <div class="stock-stats-grid">
                <div class="stock-stat-card info">
                    <div class="stock-stat-icon">
                        <i class="fas fa-gavel"></i>
                    </div>
                    <div class="stock-stat-label">کل حراج‌ها</div>
                    <div class="stock-stat-value">{{ number_format($stats['total_auctions']) }}</div>
                </div>
                
                <div class="stock-stat-card success">
                    <div class="stock-stat-icon">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="stock-stat-label">حراج‌های فعال</div>
                    <div class="stock-stat-value">{{ number_format($stats['active_auctions']) }}</div>
                </div>
                
                <div class="stock-stat-card warning">
                    <div class="stock-stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stock-stat-label">حراج‌های برنامه‌ریزی شده</div>
                    <div class="stock-stat-value">{{ number_format($stats['scheduled_auctions']) }}</div>
                </div>
                
                <div class="stock-stat-card primary">
                    <div class="stock-stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stock-stat-label">حراج‌های تکمیل شده</div>
                    <div class="stock-stat-value">{{ number_format($stats['completed_auctions']) }}</div>
                </div>
                
                <div class="stock-stat-card purple">
                    <div class="stock-stat-icon">
                        <i class="fas fa-hand-pointer"></i>
                    </div>
                    <div class="stock-stat-label">کل پیشنهادها</div>
                    <div class="stock-stat-value">{{ number_format($stats['total_bids']) }}</div>
                </div>
                
                <div class="stock-stat-card info">
                    <div class="stock-stat-icon">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="stock-stat-label">حجم کل پیشنهادها</div>
                    <div class="stock-stat-value">{{ number_format($stats['total_volume']) }} <span style="font-size: 0.75rem; opacity: 0.8;">سهم</span></div>
                </div>
                
                <div class="stock-stat-card success">
                    <div class="stock-stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stock-stat-label">کل سرمایه جذب شده</div>
                    <div class="stock-stat-value">{{ number_format($stats['total_capital'] * 10) }} <span style="font-size: 0.75rem; opacity: 0.8;">ریال</span></div>
                </div>
                
                <div class="stock-stat-card warning">
                    <div class="stock-stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stock-stat-label">تعداد سرمایه‌گذاران</div>
                    <div class="stock-stat-value">{{ number_format($stats['total_investors']) }}</div>
                </div>
                
                <div class="stock-stat-card primary">
                    <div class="stock-stat-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stock-stat-label">سهام فروخته شده</div>
                    <div class="stock-stat-value">{{ number_format($stats['sold_shares']) }} <span style="font-size: 0.75rem; opacity: 0.8;">سهم</span></div>
                </div>
                
                @if($stats['highest_price'] > 0)
                <div class="stock-stat-card success">
                    <div class="stock-stat-icon">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                    <div class="stock-stat-label">بالاترین قیمت</div>
                    <div class="stock-stat-value">{{ number_format($stats['highest_price'] * 10) }} <span style="font-size: 0.75rem; opacity: 0.8;">ریال</span></div>
                </div>
                
                <div class="stock-stat-card info">
                    <div class="stock-stat-icon">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                    <div class="stock-stat-label">پایین‌ترین قیمت</div>
                    <div class="stock-stat-value">{{ number_format($stats['lowest_price'] * 10) }} <span style="font-size: 0.75rem; opacity: 0.8;">ریال</span></div>
                </div>
                
                <div class="stock-stat-card warning">
                    <div class="stock-stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stock-stat-label">میانگین قیمت</div>
                    <div class="stock-stat-value">{{ number_format($stats['average_price'] * 10) }} <span style="font-size: 0.75rem; opacity: 0.8;">ریال</span></div>
                </div>
                @endif
            </div>
        </div>
        
        @if(isset($stats['chart_data']) && count($stats['chart_data']['labels']) > 0)
        <!-- نمودارها -->
        <div style="margin-top: 2rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem;">
                <!-- نمودار حجم حراج‌ها -->
                <div class="stock-chart-container">
                    <div class="stock-chart-title">
                        <i class="fas fa-chart-area ml-2"></i>
                        روند حجم حراج‌ها (12 ماه گذشته)
                    </div>
                    <canvas id="volumeChart" style="max-height: 300px;"></canvas>
                </div>
                
                <!-- نمودار قیمت‌ها -->
                <div class="stock-chart-container">
                    <div class="stock-chart-title">
                        <i class="fas fa-chart-line ml-2"></i>
                        روند قیمت‌های پیشنهادی (12 ماه گذشته)
                    </div>
                    <canvas id="priceChart" style="max-height: 300px;"></canvas>
                </div>
            </div>
        </div>
        @endif
        @endif
        @else
        <!-- حالت خالی -->
        <div class="stock-empty-state">
            <div class="stock-empty-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stock-empty-title">هنوز اطلاعات سهام ثبت نشده است</div>
            <div class="stock-empty-description">
                برای شروع، ابتدا اطلاعات پایه سهام را ثبت کنید.
            </div>
            <a href="{{ route('admin.stock.create') }}" class="stock-action-btn success">
                <i class="fas fa-plus"></i>
                ثبت اطلاعات سهام
            </a>
        </div>
        @endif
        
        <!-- بخش گزارش‌گیری - همیشه نمایش داده می‌شود -->
        <div style="margin-top: 2rem;">
            <h4 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 1.5rem;">
                <i class="fas fa-file-chart-line ml-2"></i>
                گزارش‌گیری و تحلیل
            </h4>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
                <!-- گزارش عملکرد حراج‌ها -->
                <a href="{{ route('admin.stock-reports.auction-performance') }}" 
                   style="display: block; background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); border-radius: 12px; padding: 1.5rem; color: white; text-decoration: none; transition: transform 0.2s; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);"
                   onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 6px 16px rgba(59, 130, 246, 0.4)';"
                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(59, 130, 246, 0.3)';">
                    <div style="font-size: 2rem; margin-bottom: 0.75rem;">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div style="font-size: 1.125rem; font-weight: 700; margin-bottom: 0.5rem;">گزارش عملکرد حراج‌ها</div>
                    <div style="font-size: 0.875rem; opacity: 0.9;">تحلیل عملکرد حراج‌ها در بازه زمانی مشخص</div>
                </a>
                
                <!-- گزارش سرمایه‌گذاران -->
                <a href="{{ route('admin.stock-reports.investors') }}" 
                   style="display: block; background: linear-gradient(135deg, #10b981 0%, #047857 100%); border-radius: 12px; padding: 1.5rem; color: white; text-decoration: none; transition: transform 0.2s; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);"
                   onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 6px 16px rgba(16, 185, 129, 0.4)';"
                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(16, 185, 129, 0.3)';">
                    <div style="font-size: 2rem; margin-bottom: 0.75rem;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div style="font-size: 1.125rem; font-weight: 700; margin-bottom: 0.5rem;">گزارش سرمایه‌گذاران</div>
                    <div style="font-size: 0.875rem; opacity: 0.9;">لیست کامل سرمایه‌گذاران و میزان سرمایه‌گذاری</div>
                </a>
                
                <!-- گزارش مالی -->
                <a href="{{ route('admin.stock-reports.financial') }}" 
                   style="display: block; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-radius: 12px; padding: 1.5rem; color: white; text-decoration: none; transition: transform 0.2s; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);"
                   onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 6px 16px rgba(245, 158, 11, 0.4)';"
                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(245, 158, 11, 0.3)';">
                    <div style="font-size: 2rem; margin-bottom: 0.75rem;">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div style="font-size: 1.125rem; font-weight: 700; margin-bottom: 0.5rem;">گزارش مالی</div>
                    <div style="font-size: 0.875rem; opacity: 0.9;">تحلیل مالی حراج‌ها و فروش سهام</div>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@if(isset($stats['chart_data']) && count($stats['chart_data']['labels']) > 0)
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // نمودار حجم حراج‌ها
        const volumeCtx = document.getElementById('volumeChart');
        if (volumeCtx) {
            new Chart(volumeCtx, {
                type: 'line',
                data: {
                    labels: [@foreach($stats['chart_data']['labels'] as $label)'{{ $label }}',@endforeach],
                    datasets: [{
                        label: 'حجم حراج‌ها (سهم)',
                        data: [@foreach($stats['chart_data']['volumes'] as $volume){{ $volume }},@endforeach],
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
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
        
        // نمودار قیمت‌ها
        const priceCtx = document.getElementById('priceChart');
        if (priceCtx) {
            new Chart(priceCtx, {
                type: 'line',
                data: {
                    labels: [@foreach($stats['chart_data']['labels'] as $label)'{{ $label }}',@endforeach],
                    datasets: [{
                        label: 'میانگین قیمت پیشنهادی (ریال)',
                        data: [@foreach($stats['chart_data']['prices'] as $price){{ $price * 10 }},@endforeach],
                        borderColor: 'rgb(16, 185, 129)',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
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
    });
</script>
@endif
@endpush
