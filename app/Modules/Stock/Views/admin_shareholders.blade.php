@extends('layouts.admin')

@section('title', 'لیست سهامداران - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'لیست سهامداران ارثکوپ')
@section('page-description', 'مشاهده لیست کامل و پویای سهامداران')

@push('styles')
<style>
    .shareholders-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .shareholders-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 2px solid #e5e7eb;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .shareholders-header h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .shareholders-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .shareholder-stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        padding: 1.5rem;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .shareholder-stat-card.success {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
    }
    
    .shareholder-stat-card.info {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    }
    
    .shareholder-stat-card.warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
    
    .shareholder-stat-label {
        font-size: 0.875rem;
        opacity: 0.9;
        margin-bottom: 0.5rem;
    }
    
    .shareholder-stat-value {
        font-size: 1.75rem;
        font-weight: 800;
    }
    
    .shareholders-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1.5rem;
    }
    
    .shareholders-table thead {
        background: #f9fafb;
    }
    
    .shareholders-table th {
        padding: 1rem;
        text-align: right;
        font-weight: 600;
        color: #1e293b;
        font-size: 0.875rem;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .shareholders-table td {
        padding: 1rem;
        text-align: right;
        color: #4b5563;
        font-size: 0.875rem;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .shareholders-table tbody tr:hover {
        background: #f9fafb;
    }
    
    @media (prefers-color-scheme: dark) {
        .shareholders-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
        }
        
        .shareholders-header {
            border-bottom-color: #475569 !important;
        }
        
        .shareholders-header h3 {
            color: #f1f5f9 !important;
        }
        
        .shareholders-table thead {
            background: #334155 !important;
        }
        
        .shareholders-table th {
            color: #f1f5f9 !important;
            border-bottom-color: #475569 !important;
        }
        
        .shareholders-table td {
            color: #cbd5e1 !important;
            border-bottom-color: #475569 !important;
        }
        
        .shareholders-table tbody tr:hover {
            background: #334155 !important;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <div class="shareholders-card">
        <div class="shareholders-header">
            <h3>
                <i class="fas fa-users ml-2"></i>
                لیست سهامداران ارثکوپ
            </h3>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <a href="{{ route('admin.stock.index') }}" style="padding: 0.75rem 1.5rem; background: #6b7280; color: white; border-radius: 0.75rem; text-decoration: none; font-weight: 600;">
                    <i class="fas fa-arrow-right ml-2"></i>
                    بازگشت
                </a>
                <a href="{{ route('admin.stock.gift') }}" style="padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #10b981 0%, #047857 100%); color: white; border-radius: 0.75rem; text-decoration: none; font-weight: 600;">
                    <i class="fas fa-gift ml-2"></i>
                    هدیه دادن سهام
                </a>
            </div>
        </div>
        
        @if($stock && isset($stats))
        <!-- آمار کلی -->
        <div class="shareholders-stats">
            <div class="shareholder-stat-card success">
                <div class="shareholder-stat-label">تعداد کل سهامداران</div>
                <div class="shareholder-stat-value">{{ number_format($stats['total_shareholders']) }}</div>
            </div>
            
            <div class="shareholder-stat-card info">
                <div class="shareholder-stat-label">کل سهام توزیع شده</div>
                <div class="shareholder-stat-value">{{ number_format($stats['total_shares_distributed']) }} <span style="font-size: 0.875rem; opacity: 0.8;">سهم</span></div>
            </div>
            
            <div class="shareholder-stat-card warning">
                <div class="shareholder-stat-label">میانگین سهام هر سهامدار</div>
                <div class="shareholder-stat-value">{{ number_format($stats['average_shares_per_shareholder']) }} <span style="font-size: 0.875rem; opacity: 0.8;">سهم</span></div>
            </div>
            
            <div class="shareholder-stat-card">
                <div class="shareholder-stat-label">کل ارزش سهام (ریال)</div>
                <div class="shareholder-stat-value">{{ number_format($stats['total_value'] * 10) }} <span style="font-size: 0.875rem; opacity: 0.8;">ریال</span></div>
            </div>
        </div>
        @endif
        
        <!-- جدول سهامداران -->
        @if(isset($shareholders) && $shareholders->count() > 0)
        <div style="overflow-x: auto;">
            <table class="shareholders-table">
                <thead>
                    <tr>
                        <th>ردیف</th>
                        <th>کاربر</th>
                        <th>نام</th>
                        <th>ایمیل</th>
                        <th>تعداد کل سهام</th>
                        <th>ارزش کل (ریال)</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($shareholders as $index => $shareholder)
                    <tr>
                        <td><strong>{{ ($shareholders->currentPage() - 1) * $shareholders->perPage() + $index + 1 }}</strong></td>
                        <td>
                            <a href="{{ route('admin.users.show', $shareholder->user_id) }}" style="color: #3b82f6; text-decoration: none;">
                                #{{ $shareholder->user_id }}
                            </a>
                        </td>
                        <td><strong>{{ ($shareholder->user->first_name ?? '') . ' ' . ($shareholder->user->last_name ?? '') }}</strong></td>
                        <td>{{ $shareholder->user->email ?? '—' }}</td>
                        <td><strong style="color: #10b981;">{{ number_format($shareholder->total_shares) }}</strong> <span style="font-size: 0.75rem; color: #6b7280;">سهم</span></td>
                        <td><strong>{{ number_format($shareholder->total_value * 10) }}</strong></td>
                        <td>
                            <a href="{{ route('admin.users.show', $shareholder->user_id) }}" 
                               style="padding: 0.5rem 1rem; background: #3b82f6; color: white; border-radius: 0.5rem; text-decoration: none; font-size: 0.875rem; font-weight: 600;">
                                <i class="fas fa-eye ml-1"></i>
                                مشاهده
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 2rem;">
            {{ $shareholders->links('pagination::bootstrap-5') }}
        </div>
        @else
        <div style="text-align: center; padding: 3rem 1rem; background: #f9fafb; border-radius: 12px; border: 2px dashed #e5e7eb;">
            <i class="fas fa-inbox" style="font-size: 4rem; color: #9ca3af; margin-bottom: 1rem;"></i>
            <div style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem;">سهامدار ثبت نشده است</div>
            <p style="color: #6b7280; font-size: 0.875rem;">هنوز هیچ سهامداری در سیستم ثبت نشده است.</p>
        </div>
        @endif
    </div>
</div>
@endsection

