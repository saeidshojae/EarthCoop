@extends('layouts.admin')

@section('title', 'گزارش عملکرد حراج‌ها - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'گزارش عملکرد حراج‌ها')
@section('page-description', 'تحلیل عملکرد حراج‌ها در بازه زمانی مشخص')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
<style>
    .report-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .report-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .report-stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        padding: 1.5rem;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .report-stat-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    }
    
    .report-stat-card.success {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
    }
    
    .report-stat-card.info {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    }
    
    .report-stat-card.warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
    
    .report-stat-icon {
        font-size: 2rem;
        margin-bottom: 0.75rem;
        opacity: 0.9;
        position: relative;
        z-index: 10;
    }
    
    .report-stat-label {
        font-size: 0.75rem;
        opacity: 0.9;
        margin-bottom: 0.5rem;
        position: relative;
        z-index: 10;
    }
    
    .report-stat-value {
        font-size: 1.5rem;
        font-weight: 800;
        position: relative;
        z-index: 10;
    }
    
    .report-filter-form {
        background: #f9fafb;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .report-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1.5rem;
    }
    
    .report-table thead {
        background: #f9fafb;
    }
    
    .report-table th {
        padding: 1rem;
        text-align: right;
        font-weight: 600;
        color: #1e293b;
        font-size: 0.875rem;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .report-table td {
        padding: 1rem;
        text-align: right;
        color: #4b5563;
        font-size: 0.875rem;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .report-table tbody tr:hover {
        background: #f9fafb;
    }
    
    @media (prefers-color-scheme: dark) {
        .report-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
        }
        
        .report-stat-card {
            background: linear-gradient(135deg, #4c1d95 0%, #5b21b6 100%) !important;
        }
        
        .report-stat-card.success {
            background: linear-gradient(135deg, #064e3b 0%, #065f46 100%) !important;
        }
        
        .report-stat-card.info {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%) !important;
        }
        
        .report-stat-card.warning {
            background: linear-gradient(135deg, #78350f 0%, #92400e 100%) !important;
        }
        
        .report-filter-form {
            background: #334155 !important;
        }
        
        .report-table thead {
            background: #334155 !important;
        }
        
        .report-table th {
            color: #f1f5f9 !important;
            border-bottom-color: #475569 !important;
        }
        
        .report-table td {
            color: #cbd5e1 !important;
            border-bottom-color: #475569 !important;
        }
        
        .report-table tbody tr:hover {
            background: #334155 !important;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <div class="report-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
            <h3 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">
                <i class="fas fa-chart-bar ml-2"></i>
                گزارش عملکرد حراج‌ها
            </h3>
            <a href="{{ route('admin.stock-reports.export-auction-performance', request()->all()) }}" 
               class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-500 text-white rounded-lg text-sm font-semibold hover:bg-emerald-600 transition">
                <i class="fas fa-download"></i>
                خروجی CSV
            </a>
        </div>
        
        <!-- فرم فیلتر -->
        <div class="report-filter-form">
            <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; align-items: end;">
                <div>
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #4b5563; margin-bottom: 0.5rem;">از تاریخ</label>
                    <input type="text" 
                           name="date_from" 
                           value="{{ request('date_from', $dateFrom ? \Morilog\Jalali\Jalalian::fromCarbon($dateFrom)->format('Y/m/d') : '') }}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 jalali-date"
                           placeholder="1404/01/01">
                </div>
                <div>
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #4b5563; margin-bottom: 0.5rem;">تا تاریخ</label>
                    <input type="text" 
                           name="date_to" 
                           value="{{ request('date_to', $dateTo ? \Morilog\Jalali\Jalalian::fromCarbon($dateTo)->format('Y/m/d') : '') }}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 jalali-date"
                           placeholder="1404/12/29">
                </div>
                <div>
                    <button type="submit" 
                            class="w-full px-4 py-2 bg-blue-500 text-white rounded-lg font-semibold hover:bg-blue-600 transition">
                        <i class="fas fa-filter ml-2"></i>
                        اعمال فیلتر
                    </button>
                </div>
            </form>
        </div>
        
        <!-- آمار کلی -->
        <div class="report-stats-grid">
            <div class="report-stat-card info">
                <div class="report-stat-icon">
                    <i class="fas fa-gavel"></i>
                </div>
                <div class="report-stat-label">کل حراج‌ها</div>
                <div class="report-stat-value">{{ number_format($stats['total_auctions']) }}</div>
            </div>
            
            <div class="report-stat-card success">
                <div class="report-stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="report-stat-label">حراج‌های تکمیل شده</div>
                <div class="report-stat-value">{{ number_format($stats['completed_auctions']) }}</div>
            </div>
            
            <div class="report-stat-card warning">
                <div class="report-stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="report-stat-label">حراج‌های لغو شده</div>
                <div class="report-stat-value">{{ number_format($stats['canceled_auctions']) }}</div>
            </div>
            
            <div class="report-stat-card info">
                <div class="report-stat-icon">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="report-stat-label">کل سهام عرضه شده</div>
                <div class="report-stat-value">{{ number_format($stats['total_shares_offered']) }} <span style="font-size: 0.75rem; opacity: 0.8;">سهم</span></div>
            </div>
            
            <div class="report-stat-card success">
                <div class="report-stat-icon">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <div class="report-stat-label">سهام فروخته شده</div>
                <div class="report-stat-value">{{ number_format($stats['total_shares_sold']) }} <span style="font-size: 0.75rem; opacity: 0.8;">سهم</span></div>
            </div>
            
            <div class="report-stat-card info">
                <div class="report-stat-icon">
                    <i class="fas fa-hand-pointer"></i>
                </div>
                <div class="report-stat-label">کل پیشنهادها</div>
                <div class="report-stat-value">{{ number_format($stats['total_bids']) }}</div>
            </div>
            
            <div class="report-stat-card success">
                <div class="report-stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="report-stat-label">کل سرمایه جذب شده</div>
                <div class="report-stat-value">{{ number_format($stats['total_capital']) }} <span style="font-size: 0.75rem; opacity: 0.8;">تومان</span></div>
            </div>
            
            @if($stats['average_price'] > 0)
            <div class="report-stat-card warning">
                <div class="report-stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="report-stat-label">میانگین قیمت پیشنهادی</div>
                <div class="report-stat-value">{{ number_format($stats['average_price']) }} <span style="font-size: 0.75rem; opacity: 0.8;">تومان</span></div>
            </div>
            @endif
        </div>
        
        <!-- جدول حراج‌ها -->
        @if($auctions->count() > 0)
        <div style="overflow-x: auto;">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>شناسه</th>
                        <th>تعداد سهام</th>
                        <th>قیمت پایه</th>
                        <th>وضعیت</th>
                        <th>نوع</th>
                        <th>تعداد پیشنهادها</th>
                        <th>بالاترین پیشنهاد</th>
                        <th>میانگین قیمت</th>
                        <th>تاریخ شروع</th>
                        <th>تاریخ پایان</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($auctions as $auction)
                    @php
                        $bids = $auction->bids;
                        $highestBid = $bids->max('price') ?? 0;
                        $avgPrice = $bids->pluck('price')->filter()->avg() ?? 0;
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ route('admin.auction.show', $auction) }}" style="color: #3b82f6; text-decoration: none;">
                                #{{ $auction->id }}
                            </a>
                        </td>
                        <td>{{ number_format($auction->shares_count) }}</td>
                        <td>{{ number_format($auction->base_price) }} تومان</td>
                        <td>
                            <span style="padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600;
                                @if($auction->status === 'running') background: #d1fae5; color: #065f46;
                                @elseif($auction->status === 'settled') background: #dbeafe; color: #1e40af;
                                @elseif($auction->status === 'scheduled') background: #fef3c7; color: #92400e;
                                @else background: #fee2e2; color: #991b1b;
                                @endif">
                                {{ $auction->status }}
                            </span>
                        </td>
                        <td>{{ $auction->type }}</td>
                        <td>{{ number_format($bids->count()) }}</td>
                        <td>{{ $highestBid > 0 ? number_format($highestBid) . ' تومان' : '—' }}</td>
                        <td>{{ $avgPrice > 0 ? number_format($avgPrice) . ' تومان' : '—' }}</td>
                        <td>{{ $auction->start_time ? \Morilog\Jalali\Jalalian::fromCarbon($auction->start_time)->format('Y/m/d H:i') : '—' }}</td>
                        <td>{{ $auction->ends_at ? \Morilog\Jalali\Jalalian::fromCarbon($auction->ends_at)->format('Y/m/d H:i') : '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div style="text-align: center; padding: 3rem 1rem; background: #f9fafb; border-radius: 12px; border: 2px dashed #e5e7eb;">
            <i class="fas fa-inbox" style="font-size: 4rem; color: #9ca3af; margin-bottom: 1rem;"></i>
            <div style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem;">حراجی یافت نشد</div>
            <p style="color: #6b7280; font-size: 0.875rem;">در بازه زمانی انتخاب شده حراجی وجود ندارد.</p>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/persian-date@1.0.6/dist/persian-date.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.jQuery !== 'undefined' && typeof $.fn.persianDatepicker !== 'undefined') {
            $('.jalali-date').each(function () {
                $(this).persianDatepicker({
                    format: 'YYYY/MM/DD',
                    initialValue: !!$(this).val(),
                    calendar: { persian: { locale: 'fa' } },
                    autoClose: true
                });
            });
        }
    });
</script>
@endpush

