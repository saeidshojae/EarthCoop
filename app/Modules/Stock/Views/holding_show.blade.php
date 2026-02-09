@extends('layouts.unified')

@section('title', 'جزئیات سهم - ' . config('app.name', 'EarthCoop'))

@push('styles')
<style>
    /* Holding Show Page Styles */
    .holding-show-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem 1rem;
        direction: rtl;
    }

    /* Holding Hero */
    .holding-hero {
        background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        color: white;
        padding: 3rem 2rem;
        border-radius: 20px;
        margin-bottom: 2rem;
        text-align: center;
        box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
    }

    .holding-hero h1 {
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 1rem;
    }

    /* Holding Info Cards */
    .holding-info-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .holding-info-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        text-align: center;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border-top: 4px solid var(--color-earth-green);
    }

    .holding-info-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    }

    .holding-info-card-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
        color: var(--color-earth-green);
    }

    .holding-info-card-label {
        font-size: 1rem;
        color: #6b7280;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .holding-info-card-value {
        font-size: 2rem;
        font-weight: 800;
        color: var(--color-gentle-black);
    }

    /* Transactions Table */
    .transactions-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }

    .transactions-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e5e7eb;
    }

    .transactions-header-left {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .transactions-header i {
        font-size: 2rem;
        color: var(--color-earth-green);
    }

    .transactions-header h4 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-gentle-black);
        margin: 0;
    }

    .btn-back {
        padding: 0.75rem 1.5rem;
        background: linear-gradient(135deg, var(--color-ocean-blue) 0%, var(--color-dark-blue) 100%);
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
    }

    .btn-back:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        color: white;
    }

    .transactions-table {
        width: 100%;
        border-collapse: collapse;
    }

    .transactions-table thead {
        background: #f9fafb;
    }

    .transactions-table th {
        padding: 1rem;
        text-align: right;
        font-weight: 700;
        color: var(--color-gentle-black);
        border-bottom: 2px solid #e5e7eb;
    }

    .transactions-table td {
        padding: 1rem;
        text-align: right;
        color: #4b5563;
        border-bottom: 1px solid #e5e7eb;
    }

    .transactions-table tr:hover {
        background-color: #f9fafb;
    }

    .transaction-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .transaction-badge.buy {
        background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        color: white;
    }

    .transaction-badge.sell {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
    }

    .transaction-badge.transfer {
        background: linear-gradient(135deg, var(--color-ocean-blue) 0%, var(--color-dark-blue) 100%);
        color: white;
    }

    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: #9ca3af;
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    /* Responsive */
    @media screen and (max-width: 768px) {
        .holding-hero h1 {
            font-size: 1.75rem;
        }

        .holding-info-cards {
            grid-template-columns: 1fr;
        }

        .transactions-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }

        .transactions-table {
            font-size: 0.875rem;
        }

        .transactions-table th,
        .transactions-table td {
            padding: 0.75rem 0.5rem;
        }
    }
</style>
@endpush

@section('content')
<div class="holding-show-container">
    <!-- Hero Section -->
    <div class="holding-hero">
        <h1><i class="fas fa-chart-line ml-2"></i> جزئیات سهم</h1>
        <p>نمایش اطلاعات و تراکنش‌های سهم شما</p>
    </div>

    <!-- Holding Info Cards -->
    <div class="holding-info-cards">
        <div class="holding-info-card">
            <div class="holding-info-card-icon">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="holding-info-card-label">تعداد سهام</div>
            <div class="holding-info-card-value">{{ number_format($holding->quantity) }}</div>
        </div>

        <div class="holding-info-card">
            <div class="holding-info-card-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="holding-info-card-label">قیمت پایه</div>
            <div class="holding-info-card-value">{{ number_format($holding->stock->base_share_price ?? 0) }} ریال</div>
        </div>

        <div class="holding-info-card">
            <div class="holding-info-card-icon">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="holding-info-card-label">ارزش کل</div>
            <div class="holding-info-card-value">{{ number_format($holding->total_value ?? 0) }} ریال</div>
        </div>
    </div>

    <!-- Transactions History -->
    <div class="transactions-card">
        <div class="transactions-header">
            <div class="transactions-header-left">
                <i class="fas fa-history"></i>
                <h4>تاریخچه تراکنش‌ها</h4>
            </div>
            <a href="{{ route('holding.index') }}" class="btn-back">
                <i class="fas fa-arrow-right"></i>
                بازگشت به لیست سهام
            </a>
        </div>

        @if($transactions->count() > 0)
            <div style="overflow-x: auto;">
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th>نوع</th>
                            <th>تعداد</th>
                            <th>قیمت</th>
                            <th>مبلغ</th>
                            <th>توضیحات</th>
                            <th>تاریخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $transaction)
                            <tr>
                                <td>
                                    <span class="transaction-badge {{ $transaction->type ?? 'transfer' }}">
                                        @switch($transaction->type ?? 'transfer')
                                            @case('buy') خرید @break
                                            @case('sell') فروش @break
                                            @default انتقال @break
                                        @endswitch
                                    </span>
                                </td>
                                <td style="font-weight: 600;">{{ number_format($transaction->quantity ?? 0) }}</td>
                                <td>{{ number_format($transaction->price ?? 0) }} ریال</td>
                                <td style="font-weight: 600;">{{ number_format($transaction->amount ?? 0) }} ریال</td>
                                <td>{{ $transaction->description ?? '-' }}</td>
                                <td>{{ $transaction->created_at ? verta($transaction->created_at)->format('Y/m/d H:i') : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 2rem; display: flex; justify-content: center;">
                {{ $transactions->links() }}
            </div>
        @else
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>هنوز تراکنشی برای این سهم ثبت نشده است.</p>
            </div>
        @endif
    </div>
</div>
@endsection

