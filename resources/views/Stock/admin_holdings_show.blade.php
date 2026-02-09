@extends('layouts.admin')

@section('title', 'جزئیات کیف سهام - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'جزئیات کیف سهام')
@section('page-description', 'مشاهده جزئیات holding و تراکنش‌ها')
<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
@push('styles')
<style>
    .holding-detail-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .holding-detail-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 2px solid #e5e7eb;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .holding-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .holding-info-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        padding: 1.5rem;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .holding-info-card.success {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
    }
    
    .holding-info-card.info {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    }
    
    .holding-info-label {
        font-size: 0.875rem;
        opacity: 0.9;
        margin-bottom: 0.5rem;
    }
    
    .holding-info-value {
        font-size: 1.75rem;
        font-weight: 800;
    }
    
    .transaction-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1.5rem;
    }
    
    .transaction-table thead {
        background: #f9fafb;
    }
    
    .transaction-table th {
        padding: 1rem;
        text-align: right;
        font-weight: 600;
        color: #1e293b;
        font-size: 0.875rem;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .transaction-table td {
        padding: 1rem;
        text-align: right;
        color: #4b5563;
        font-size: 0.875rem;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .transaction-table tbody tr:hover {
        background: #f9fafb;
    }
    
    .transaction-badge {
        padding: 0.375rem 0.75rem;
        border-radius: 0.5rem;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .transaction-badge.buy {
        background: #d1fae5;
        color: #065f46;
    }
    
    .transaction-badge.sell {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .transaction-badge.gift {
        background: #fef3c7;
        color: #92400e;
    }
    
    @media (prefers-color-scheme: dark) {
        .holding-detail-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
        }
        
        .holding-detail-header {
            border-bottom-color: #475569 !important;
        }
        
        .transaction-table thead {
            background: #334155 !important;
        }
        
        .transaction-table th {
            color: #f1f5f9 !important;
            border-bottom-color: #475569 !important;
        }
        
        .transaction-table td {
            color: #cbd5e1 !important;
            border-bottom-color: #475569 !important;
        }
        
        .transaction-table tbody tr:hover {
            background: #334155 !important;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <div class="holding-detail-card">
        <div class="holding-detail-header">
            <h3>
                <i class="fas fa-briefcase ml-2"></i>
                جزئیات کیف سهام
            </h3>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <a href="{{ route('admin.holdings.index') }}" style="padding: 0.75rem 1.5rem; background: #6b7280; color: white; border-radius: 0.75rem; text-decoration: none; font-weight: 600;">
                    <i class="fas fa-arrow-right ml-2"></i>
                    بازگشت
                </a>
                <a href="{{ route('admin.users.show', $holding->user_id) }}" style="padding: 0.75rem 1.5rem; background: #3b82f6; color: white; border-radius: 0.75rem; text-decoration: none; font-weight: 600;">
                    <i class="fas fa-user ml-2"></i>
                    مشاهده کاربر
                </a>
            </div>
        </div>
        
        <!-- اطلاعات کاربر -->
        <div style="margin-bottom: 2rem; padding: 1.5rem; background: #f9fafb; border-radius: 12px;">
            <h4 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem;">اطلاعات کاربر</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div>
                    <strong>نام:</strong> {{ ($holding->user->first_name ?? '') . ' ' . ($holding->user->last_name ?? '') }}
                </div>
                <div>
                    <strong>ایمیل:</strong> {{ $holding->user->email ?? '—' }}
                </div>
                <div>
                    <strong>ID کاربر:</strong> #{{ $holding->user_id }}
                </div>
                <div>
                    <strong>ID سهام:</strong> #{{ $holding->stock_id }}
                </div>
            </div>
        </div>
        
        <!-- آمار holding -->
        <div class="holding-info-grid">
            <div class="holding-info-card success">
                <div class="holding-info-label">تعداد سهام</div>
                <div class="holding-info-value">{{ number_format($holding->quantity) }} <span style="font-size: 0.875rem; opacity: 0.8;">سهم</span></div>
            </div>
            
            <div class="holding-info-card info">
                <div class="holding-info-label">ارزش کل (تومان)</div>
                <div class="holding-info-value">{{ number_format($holding->quantity * ($holding->stock->base_share_price ?? 0)) }}</div>
            </div>
            
            <div class="holding-info-card">
                <div class="holding-info-label">قیمت پایه هر سهم (تومان)</div>
                <div class="holding-info-value">{{ number_format($holding->stock->base_share_price ?? 0) }}</div>
            </div>
        </div>
        
        <!-- جدول تراکنش‌ها -->
        <div>
            <h4 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem;">
                <i class="fas fa-list ml-2"></i>
                تاریخچه تراکنش‌ها
            </h4>
            
            @if($transactions->count() > 0)
            <div style="overflow-x: auto;">
                <table class="transaction-table">
                    <thead>
                        <tr>
                            <th>ردیف</th>
                            <th>نوع</th>
                            <th>تعداد</th>
                            <th>توضیحات</th>
                            <th>تاریخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $index => $transaction)
                        <tr>
                            <td><strong>{{ ($transactions->currentPage() - 1) * $transactions->perPage() + $index + 1 }}</strong></td>
                            <td>
                                @php
                                    $typeLabels = [
                                        'buy' => 'خرید',
                                        'sell' => 'فروش',
                                        'gift' => 'هدیه'
                                    ];
                                    $typeLabel = $typeLabels[$transaction->type] ?? $transaction->type;
                                @endphp
                                <span class="transaction-badge {{ $transaction->type }}">{{ $typeLabel }}</span>
                            </td>
                            <td><strong style="color: {{ $transaction->type === 'buy' || $transaction->type === 'gift' ? '#10b981' : '#ef4444' }};">{{ number_format($transaction->quantity) }}</strong> <span style="font-size: 0.75rem; color: #6b7280;">سهم</span></td>
                            <td>{{ $transaction->info ?? '—' }}</td>
                            <td>
                                @php
                                    $createdAt = $transaction->created_at instanceof \Carbon\Carbon 
                                        ? $transaction->created_at 
                                        : (is_string($transaction->created_at) && !empty(trim($transaction->created_at)) 
                                            ? \Carbon\Carbon::parse($transaction->created_at) 
                                            : null);
                                @endphp
                                @if($createdAt)
                                    {{ \Morilog\Jalali\Jalalian::fromCarbon($createdAt)->format('Y/m/d H:i') }}
                                @else
                                    {{ $transaction->created_at ?? '—' }}
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 2rem;">
                {{ $transactions->links('pagination::bootstrap-5') }}
            </div>
            @else
            <div style="text-align: center; padding: 3rem 1rem; background: #f9fafb; border-radius: 12px; border: 2px dashed #e5e7eb;">
                <i class="fas fa-inbox" style="font-size: 4rem; color: #9ca3af; margin-bottom: 1rem;"></i>
                <div style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem;">تراکنشی وجود ندارد</div>
                <p style="color: #6b7280; font-size: 0.875rem;">هنوز هیچ تراکنشی برای این holding ثبت نشده است.</p>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

