@extends('layouts.admin')

@section('title', 'گزارش سرمایه‌گذاران - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'گزارش سرمایه‌گذاران')
@section('page-description', 'لیست کامل سرمایه‌گذاران و میزان سرمایه‌گذاری آن‌ها')

@push('styles')
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
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
    
    .report-summary-card.success {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
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
        
        .report-summary-card {
            background: linear-gradient(135deg, #4c1d95 0%, #5b21b6 100%) !important;
        }
        
        .report-summary-card.success {
            background: linear-gradient(135deg, #064e3b 0%, #065f46 100%) !important;
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
                <i class="fas fa-users ml-2"></i>
                گزارش سرمایه‌گذاران
            </h3>
            <a href="{{ route('admin.stock-reports.export-investors') }}" 
               class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-500 text-white rounded-lg text-sm font-semibold hover:bg-emerald-600 transition">
                <i class="fas fa-download"></i>
                خروجی CSV
            </a>
        </div>
        
        <!-- خلاصه -->
        <div class="report-summary">
            <div class="report-summary-card">
                <div class="report-summary-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="report-summary-label">تعداد کل سرمایه‌گذاران</div>
                <div class="report-summary-value">{{ number_format($totalInvestors) }}</div>
            </div>
            
            <div class="report-summary-card success">
                <div class="report-summary-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="report-summary-label">کل سرمایه‌گذاری</div>
                <div class="report-summary-value">{{ number_format($totalInvestment) }} <span style="font-size: 0.75rem; opacity: 0.8;">تومان</span></div>
            </div>
        </div>
        
        <!-- جدول سرمایه‌گذاران -->
        @if($investors->count() > 0)
        <div style="overflow-x: auto;">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>شناسه کاربر</th>
                        <th>نام</th>
                        <th>ایمیل</th>
                        <th>شناسه سهام</th>
                        <th>تعداد کل سهام</th>
                        <th>کل سرمایه‌گذاری (تومان)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($investors as $investor)
                    <tr>
                        <td>
                            <a href="{{ route('admin.users.show', $investor->user_id) }}" style="color: #3b82f6; text-decoration: none;">
                                #{{ $investor->user_id }}
                            </a>
                        </td>
                        <td>{{ ($investor->user->first_name ?? '') . ' ' . ($investor->user->last_name ?? '') }}</td>
                        <td>{{ $investor->user->email ?? '—' }}</td>
                        <td>#{{ $investor->stock_id }}</td>
                        <td>{{ number_format($investor->total_shares) }}</td>
                        <td><strong>{{ number_format($investor->total_investment) }}</strong></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 2rem;">
            {{ $investors->links('pagination::bootstrap-5') }}
        </div>
        @else
        <div style="text-align: center; padding: 3rem 1rem; background: #f9fafb; border-radius: 12px; border: 2px dashed #e5e7eb;">
            <i class="fas fa-inbox" style="font-size: 4rem; color: #9ca3af; margin-bottom: 1rem;"></i>
            <div style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem;">سرمایه‌گذاری ثبت نشده است</div>
            <p style="color: #6b7280; font-size: 0.875rem;">هنوز هیچ سرمایه‌گذاری انجام نشده است.</p>
        </div>
        @endif
    </div>
</div>
@endsection

