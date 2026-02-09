@extends('layouts.admin')

@section('title', 'جزئیات حراج #' . $auction->id . ' - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'جزئیات حراج #' . $auction->id)
@section('page-description', 'مشاهده جزئیات کامل حراج و صف خرید')

@push('styles')
<style>
    .auction-detail-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .auction-detail-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 2px solid #e5e7eb;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .auction-detail-header h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .auction-detail-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    
    .auction-action-btn {
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
    
    .auction-action-btn.secondary {
        background: #6b7280;
        color: white;
    }
    
    .auction-action-btn.secondary:hover {
        background: #4b5563;
        color: white;
    }
    
    .auction-action-btn.primary {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
    }
    
    .auction-action-btn.primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        color: white;
    }
    
    .auction-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .auction-info-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        padding: 1.5rem;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .auction-info-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    }
    
    .auction-info-card.success {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
    }
    
    .auction-info-card.info {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    }
    
    .auction-info-card.warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
    
    .auction-info-icon {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        opacity: 0.9;
        position: relative;
        z-index: 10;
    }
    
    .auction-info-label {
        font-size: 0.875rem;
        opacity: 0.9;
        margin-bottom: 0.5rem;
        position: relative;
        z-index: 10;
    }
    
    .auction-info-value {
        font-size: 1.5rem;
        font-weight: 800;
        position: relative;
        z-index: 10;
    }
    
    .auction-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 999px;
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .auction-status-badge.running {
        background: #d1fae5;
        color: #065f46;
    }
    
    .auction-status-badge.scheduled {
        background: #fef3c7;
        color: #92400e;
    }
    
    .auction-status-badge.settled {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .auction-status-badge.canceled {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .order-book-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1.5rem;
    }
    
    .order-book-table thead {
        background: #f9fafb;
    }
    
    .order-book-table th {
        padding: 1rem;
        text-align: right;
        font-weight: 600;
        color: #1e293b;
        font-size: 0.875rem;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .order-book-table td {
        padding: 1rem;
        text-align: right;
        color: #4b5563;
        font-size: 0.875rem;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .order-book-table tbody tr:hover {
        background: #f9fafb;
    }
    
    .order-book-empty {
        text-align: center;
        padding: 3rem 1rem;
        background: #f9fafb;
        border-radius: 12px;
        border: 2px dashed #e5e7eb;
    }
    
    .order-book-empty-icon {
        font-size: 4rem;
        color: #9ca3af;
        margin-bottom: 1rem;
    }
    
    .order-book-empty-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 0.5rem;
    }
    
    @media (prefers-color-scheme: dark) {
        .auction-detail-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
        }
        
        .auction-detail-header {
            border-bottom-color: #475569 !important;
        }
        
        .auction-detail-header h3 {
            color: #f1f5f9 !important;
        }
        
        .auction-info-card {
            background: linear-gradient(135deg, #4c1d95 0%, #5b21b6 100%) !important;
        }
        
        .auction-info-card.success {
            background: linear-gradient(135deg, #064e3b 0%, #065f46 100%) !important;
        }
        
        .auction-info-card.info {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%) !important;
        }
        
        .auction-info-card.warning {
            background: linear-gradient(135deg, #78350f 0%, #92400e 100%) !important;
        }
        
        .auction-status-badge.running {
            background: #064e3b !important;
            color: #a7f3d0 !important;
        }
        
        .auction-status-badge.scheduled {
            background: #451a03 !important;
            color: #fed7aa !important;
        }
        
        .auction-status-badge.settled {
            background: #1e3a8a !important;
            color: #bfdbfe !important;
        }
        
        .auction-status-badge.canceled {
            background: #450a0a !important;
            color: #fecaca !important;
        }
        
        .order-book-table thead {
            background: #334155 !important;
        }
        
        .order-book-table th {
            color: #f1f5f9 !important;
            border-bottom-color: #475569 !important;
        }
        
        .order-book-table td {
            color: #cbd5e1 !important;
            border-bottom-color: #475569 !important;
        }
        
        .order-book-table tbody tr:hover {
            background: #334155 !important;
        }
        
        .order-book-empty {
            background: #334155 !important;
            border-color: #475569 !important;
        }
        
        .order-book-empty-title {
            color: #f1f5f9 !important;
        }
    }
    
    @media (max-width: 768px) {
        .auction-detail-card {
            padding: 1rem;
        }
        
        .auction-info-grid {
            grid-template-columns: 1fr;
        }
        
        .order-book-table {
            font-size: 0.75rem;
        }
        
        .order-book-table th,
        .order-book-table td {
            padding: 0.75rem 0.5rem;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <div class="auction-detail-card">
        <div class="auction-detail-header">
            <h3>
                <i class="fas fa-gavel ml-2"></i>
                جزئیات حراج #{{ $auction->id }}
            </h3>
            <div class="auction-detail-actions">
                <a href="{{ route('admin.auction.index') }}" class="auction-action-btn secondary">
                    <i class="fas fa-arrow-right"></i>
                    بازگشت
                </a>
                @if($auction->status === 'settling' && $auction->settlement_mode === 'manual')
                <form action="{{ route('admin.auction.manual-settle', $auction) }}" method="POST" style="display: inline-block;" onsubmit="return confirm('آیا از تسویه این حراج مطمئن هستید؟ این عمل قابل بازگشت نیست.');">
                    @csrf
                    <button type="submit" class="auction-action-btn primary" style="background: linear-gradient(135deg, #10b981 0%, #047857 100%);">
                        <i class="fas fa-check-circle"></i>
                        تایید و تسویه حراج
                    </button>
                </form>
                @endif
                <a href="{{ route('admin.auction.export', $auction) }}" class="auction-action-btn primary">
                    <i class="fas fa-download"></i>
                    خروجی CSV
                </a>
            </div>
        </div>
        
        <!-- کارت‌های اطلاعات -->
        <div class="auction-info-grid">
            <div class="auction-info-card">
                <div class="auction-info-icon">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="auction-info-label">تعداد سهام</div>
                <div class="auction-info-value">{{ number_format($auction->shares_count) }} <span style="font-size: 0.875rem; opacity: 0.8;">سهم</span></div>
            </div>
            
            <div class="auction-info-card success">
                <div class="auction-info-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="auction-info-label">قیمت پایه هر سهم</div>
                <div class="auction-info-value">{{ number_format($auction->base_price) }} <span style="font-size: 0.875rem; opacity: 0.8;">ریال</span></div>
            </div>
            
            <div class="auction-info-card info">
                <div class="auction-info-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="auction-info-label">زمان شروع</div>
                <div class="auction-info-value" style="font-size: 1.125rem;">
                    {{ $auction->start_time ? \Morilog\Jalali\Jalalian::fromCarbon($auction->start_time)->format('Y/m/d H:i') : '—' }}
                </div>
            </div>
            
            <div class="auction-info-card warning">
                <div class="auction-info-icon">
                    <i class="fas fa-stop-circle"></i>
                </div>
                <div class="auction-info-label">زمان پایان</div>
                <div class="auction-info-value" style="font-size: 1.125rem;">
                    {{ $auction->ends_at ? \Morilog\Jalali\Jalalian::fromCarbon($auction->ends_at)->format('Y/m/d H:i') : '—' }}
                </div>
            </div>
        </div>
        
        <!-- اطلاعات تکمیلی -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <span style="font-size: 0.875rem; color: #6b7280; display: block; margin-bottom: 0.5rem;">سهام</span>
                <strong style="color: #1e293b; font-size: 1rem;">{{ $auction->stock->id ?? '—' }}</strong>
            </div>
            <div>
                <span style="font-size: 0.875rem; color: #6b7280; display: block; margin-bottom: 0.5rem;">وضعیت</span>
                <span class="auction-status-badge {{ $auction->status }}">
                    @if($auction->status === 'running')
                        <i class="fas fa-play-circle"></i>
                        فعال
                    @elseif($auction->status === 'scheduled')
                        <i class="fas fa-calendar-check"></i>
                        برنامه‌ریزی شده
                    @elseif($auction->status === 'settled')
                        <i class="fas fa-check-circle"></i>
                        تسویه شده
                    @elseif($auction->status === 'canceled' || $auction->status === 'cancelled')
                        <i class="fas fa-times-circle"></i>
                        لغو شده
                    @else
                        {{ $auction->status }}
                    @endif
                </span>
            </div>
            <div>
                <span style="font-size: 0.875rem; color: #6b7280; display: block; margin-bottom: 0.5rem;">نوع حراج</span>
                <strong style="color: #1e293b; font-size: 1rem;">
                    @if($auction->type === 'single_winner')
                        تک برنده
                    @elseif($auction->type === 'uniform_price')
                        قیمت یکسان
                    @elseif($auction->type === 'pay_as_bid')
                        پرداخت به قیمت پیشنهادی
                    @else
                        {{ $auction->type }}
                    @endif
                </strong>
            </div>
        </div>
        
        <!-- گزارش تسویه - فقط برای حراج‌های تسویه شده -->
        @if(in_array($auction->status, ['settled', 'settling']) && isset($settlementStats))
        <div style="margin-bottom: 2rem;">
            <h4 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 1.5rem;">
                <i class="fas fa-file-chart-line ml-2"></i>
                گزارش تسویه حراج
            </h4>
            
            <!-- خلاصه آمار تسویه -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                <div style="background: linear-gradient(135deg, #10b981 0%, #047857 100%); border-radius: 12px; padding: 1.5rem; color: white;">
                    <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;">کل شرکت‌کنندگان</div>
                    <div style="font-size: 1.75rem; font-weight: 800;">{{ number_format($settlementStats['total_participants']) }}</div>
                </div>
                
                <div style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); border-radius: 12px; padding: 1.5rem; color: white;">
                    <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;">کل پیشنهادها</div>
                    <div style="font-size: 1.75rem; font-weight: 800;">{{ number_format($settlementStats['total_bids']) }}</div>
                </div>
                
                <div style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); border-radius: 12px; padding: 1.5rem; color: white;">
                    <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;">برندگان</div>
                    <div style="font-size: 1.75rem; font-weight: 800;">{{ number_format($settlementStats['total_winners']) }}</div>
                </div>
                
                <div style="background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); border-radius: 12px; padding: 1.5rem; color: white;">
                    <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;">بازندگان</div>
                    <div style="font-size: 1.75rem; font-weight: 800;">{{ number_format($settlementStats['total_losers']) }}</div>
                </div>
                
                <div style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-radius: 12px; padding: 1.5rem; color: white;">
                    <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;">سهام واگذار شده</div>
                    <div style="font-size: 1.75rem; font-weight: 800;">{{ number_format($settlementStats['total_shares_allocated']) }} <span style="font-size: 0.875rem; opacity: 0.8;">سهم</span></div>
                </div>
                
                <div style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); border-radius: 12px; padding: 1.5rem; color: white;">
                    <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;">کل درآمد</div>
                    <div style="font-size: 1.75rem; font-weight: 800;">{{ number_format($settlementStats['total_revenue']) }} <span style="font-size: 0.875rem; opacity: 0.8;">ریال</span></div>
                </div>
                
                @if($settlementStats['average_winning_price'] > 0)
                <div style="background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%); border-radius: 12px; padding: 1.5rem; color: white;">
                    <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;">میانگین قیمت برنده</div>
                    <div style="font-size: 1.75rem; font-weight: 800;">{{ number_format($settlementStats['average_winning_price']) }} <span style="font-size: 0.875rem; opacity: 0.8;">ریال</span></div>
                </div>
                @endif
                
                @if($settlementStats['clearing_price'])
                <div style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border-radius: 12px; padding: 1.5rem; color: white;">
                    <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;">قیمت تسویه (Clearing Price)</div>
                    <div style="font-size: 1.75rem; font-weight: 800;">{{ number_format($settlementStats['clearing_price']) }} <span style="font-size: 0.875rem; opacity: 0.8;">ریال</span></div>
                </div>
                @endif
            </div>
            
            <!-- لیست برندگان -->
            @if($settlementStats['winner_list']->count() > 0)
            <div style="margin-bottom: 2rem;">
                <h5 style="font-size: 1.125rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem;">
                    <i class="fas fa-trophy ml-2" style="color: #f59e0b;"></i>
                    فهرست برندگان و سهام واگذار شده
                </h5>
                <div style="overflow-x: auto;">
                    <table class="order-book-table">
                        <thead>
                            <tr>
                                <th>ردیف</th>
                                <th>کاربر</th>
                                <th>نام</th>
                                <th>ایمیل</th>
                                <th>قیمت پیشنهادی (ریال)</th>
                                <th>تعداد سهام</th>
                                <th>کل مبلغ (ریال)</th>
                                <th>زمان ثبت پیشنهاد</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($settlementStats['winner_list'] as $index => $winner)
                            <tr style="background: #f0fdf4;">
                                <td><strong>{{ $index + 1 }}</strong></td>
                                <td>
                                    <a href="{{ route('admin.users.show', $winner['user_id']) }}" style="color: #3b82f6; text-decoration: none;">
                                        #{{ $winner['user_id'] }}
                                    </a>
                                </td>
                                <td>{{ ($winner['user']->first_name ?? '') . ' ' . ($winner['user']->last_name ?? '') }}</td>
                                <td>{{ $winner['user']->email ?? '—' }}</td>
                                <td><strong style="color: #10b981;">{{ number_format($winner['price']) }}</strong></td>
                                <td><strong>{{ number_format($winner['quantity']) }}</strong> <span style="font-size: 0.75rem; color: #6b7280;">سهم</span></td>
                                <td><strong>{{ number_format($winner['total_value']) }}</strong></td>
                                <td>{{ $winner['created_at'] ? \Morilog\Jalali\Jalalian::fromCarbon($winner['created_at'])->format('Y/m/d H:i') : '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
            
            <!-- لیست بازندگان (اختیاری - برای شفافیت) -->
            @if($settlementStats['loser_list']->count() > 0 && $auction->status === 'settled')
            <details style="margin-bottom: 2rem;">
                <summary style="font-size: 1.125rem; font-weight: 700; color: #1e293b; cursor: pointer; padding: 1rem; background: #f9fafb; border-radius: 8px; margin-bottom: 1rem;">
                    <i class="fas fa-list ml-2" style="color: #6b7280;"></i>
                    فهرست بازندگان ({{ $settlementStats['loser_list']->count() }} نفر)
                </summary>
                <div style="overflow-x: auto;">
                    <table class="order-book-table">
                        <thead>
                            <tr>
                                <th>کاربر</th>
                                <th>نام</th>
                                <th>قیمت پیشنهادی (ریال)</th>
                                <th>تعداد سهام</th>
                                <th>کل مبلغ (ریال)</th>
                                <th>زمان ثبت</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($settlementStats['loser_list'] as $loser)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.users.show', $loser['user_id']) }}" style="color: #3b82f6; text-decoration: none;">
                                        #{{ $loser['user_id'] }}
                                    </a>
                                </td>
                                <td>{{ ($loser['user']->first_name ?? '') . ' ' . ($loser['user']->last_name ?? '') }}</td>
                                <td>{{ number_format($loser['price']) }}</td>
                                <td>{{ number_format($loser['quantity']) }} <span style="font-size: 0.75rem; color: #6b7280;">سهم</span></td>
                                <td>{{ number_format($loser['total_value']) }}</td>
                                <td>{{ $loser['created_at'] ? \Morilog\Jalali\Jalalian::fromCarbon($loser['created_at'])->format('Y/m/d H:i') : '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
            @endif
        </div>
        @endif
        
        <!-- صف خرید -->
        <div>
            <h4 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem;">
                <i class="fas fa-list ml-2"></i>
                صف خرید کامل
            </h4>
            
            @if($orderBook && $orderBook->count())
            <div style="overflow-x: auto;">
                <table class="order-book-table">
                    <thead>
                        <tr>
                            <th>قیمت (ریال)</th>
                            <th>تعداد</th>
                            <th>کل مبلغ (ریال)</th>
                            <th>کاربر</th>
                            <th>نام</th>
                            <th>وضعیت</th>
                            <th>زمان ثبت</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orderBook as $bid)
                        <tr style="{{ $bid->status === 'won' ? 'background: #f0fdf4;' : ($bid->status === 'lost' ? 'background: #fef2f2; opacity: 0.7;' : '') }}">
                            <td><strong>{{ number_format($bid->price) }}</strong></td>
                            <td>{{ number_format($bid->quantity) }} <span style="font-size: 0.75rem; color: #6b7280;">سهم</span></td>
                            <td><strong>{{ number_format(($bid->price ?? 0) * ($bid->quantity ?? 0)) }}</strong></td>
                            <td>
                                <a href="{{ route('admin.users.show', $bid->user_id) }}" style="color: #3b82f6; text-decoration: none;">
                                    #{{ $bid->user_id }}
                                </a>
                            </td>
                            <td>{{ ($bid->user->first_name ?? '') . ' ' . ($bid->user->last_name ?? '') }}</td>
                            <td>
                                <span class="auction-status-badge {{ $bid->status === 'active' ? 'success' : ($bid->status === 'won' ? 'info' : ($bid->status === 'lost' ? 'canceled' : 'warning')) }}">
                                    @if($bid->status === 'active')
                                        <i class="fas fa-clock text-xs"></i>
                                        فعال
                                    @elseif($bid->status === 'won')
                                        <i class="fas fa-check-circle text-xs"></i>
                                        برنده
                                    @elseif($bid->status === 'lost')
                                        <i class="fas fa-times-circle text-xs"></i>
                                        بازنده
                                    @else
                                        {{ $bid->status }}
                                    @endif
                                </span>
                            </td>
                            <td>
                                {{ $bid->created_at ? \Morilog\Jalali\Jalalian::fromCarbon($bid->created_at)->format('Y/m/d H:i') : '—' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="order-book-empty">
                <div class="order-book-empty-icon">
                    <i class="fas fa-inbox"></i>
                </div>
                <div class="order-book-empty-title">پیشنهادی ثبت نشده است</div>
                <p style="color: #6b7280; font-size: 0.875rem;">هنوز هیچ پیشنهادی برای این حراج ثبت نشده است.</p>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
