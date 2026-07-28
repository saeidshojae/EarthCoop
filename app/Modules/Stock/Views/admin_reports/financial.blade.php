@extends('layouts.admin')

@section('title', 'گزارش مالی - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'گزارش مالی')
@section('page-description', 'تحلیل مالی حراج‌ها و فروش سهام')

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
    
    .report-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .report-summary-card {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
        border-radius: 12px;
        padding: 1.5rem;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .report-summary-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    }
    
    .report-summary-card.info {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    }
    
    .report-summary-card.warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
    
    .report-summary-icon {
        font-size: 2rem;
        margin-bottom: 0.75rem;
        opacity: 0.9;
        position: relative;
        z-index: 10;
    }
    
    .report-summary-label {
        font-size: 0.75rem;
        opacity: 0.9;
        margin-bottom: 0.5rem;
        position: relative;
        z-index: 10;
    }
    
    .report-summary-value {
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
    
    @media (prefers-color-scheme: dark) {
        .report-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
        }
        
        .report-summary-card {
            background: linear-gradient(135deg, #064e3b 0%, #065f46 100%) !important;
        }
        
        .report-summary-card.info {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%) !important;
        }
        
        .report-summary-card.warning {
            background: linear-gradient(135deg, #78350f 0%, #92400e 100%) !important;
        }
        
        .report-filter-form {
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
                <i class="fas fa-chart-pie ml-2"></i>
                گزارش مالی
            </h3>
            <a href="{{ route('admin.stock-reports.export-financial', request()->all()) }}" 
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
        
        <!-- خلاصه مالی -->
        <div class="report-summary">
            <div class="report-summary-card">
                <div class="report-summary-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="report-summary-label">کل درآمد فروش</div>
                <div class="report-summary-value">{{ number_format($sales['total_sales']) }} <span style="font-size: 0.75rem; opacity: 0.8;">تومان</span></div>
            </div>
            
            <div class="report-summary-card info">
                <div class="report-summary-icon">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="report-summary-label">کل سهام فروخته شده</div>
                <div class="report-summary-value">{{ number_format($sales['total_shares_sold']) }} <span style="font-size: 0.75rem; opacity: 0.8;">سهم</span></div>
            </div>
            
            @if($sales['average_price'] > 0)
            <div class="report-summary-card warning">
                <div class="report-summary-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="report-summary-label">میانگین قیمت فروش</div>
                <div class="report-summary-value">{{ number_format($sales['average_price']) }} <span style="font-size: 0.75rem; opacity: 0.8;">تومان</span></div>
            </div>
            @endif
            
            <div class="report-summary-card info">
                <div class="report-summary-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="report-summary-label">تعداد تراکنش‌ها</div>
                <div class="report-summary-value">{{ number_format($sales['total_transactions']) }}</div>
            </div>
        </div>
        
        @if($auctions->count() === 0)
        <div style="text-align: center; padding: 3rem 1rem; background: #f9fafb; border-radius: 12px; border: 2px dashed #e5e7eb;">
            <i class="fas fa-inbox" style="font-size: 4rem; color: #9ca3af; margin-bottom: 1rem;"></i>
            <div style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem;">فروشی ثبت نشده است</div>
            <p style="color: #6b7280; font-size: 0.875rem;">در بازه زمانی انتخاب شده فروشی انجام نشده است.</p>
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

