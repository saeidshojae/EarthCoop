@extends('layouts.unified')

@section('title', 'کیف سهام - ' . config('app.name', 'EarthCoop'))

@push('styles')
<style>
    /* Holdings Page Styles */
    .holdings-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem 1rem;
        direction: rtl;
    }

    /* Holdings Hero */
    .holdings-hero {
        background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        color: white;
        padding: 3rem 2rem;
        border-radius: 20px;
        margin-bottom: 2rem;
        text-align: center;
        box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
    }

    .holdings-hero h1 {
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 1rem;
    }

    /* Holdings Table Card */
    .holdings-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        overflow: hidden;
    }

    .holdings-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e5e7eb;
    }

    .holdings-header i {
        font-size: 2rem;
        color: var(--color-earth-green);
    }

    .holdings-header h4 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-gentle-black);
        margin: 0;
    }

    .holdings-table {
        width: 100%;
        border-collapse: collapse;
    }

    .holdings-table thead {
        background: #f9fafb;
    }

    .holdings-table th {
        padding: 1rem;
        text-align: right;
        font-weight: 700;
        color: var(--color-gentle-black);
        border-bottom: 2px solid #e5e7eb;
    }

    .holdings-table td {
        padding: 1.5rem 1rem;
        text-align: right;
        color: #4b5563;
        border-bottom: 1px solid #e5e7eb;
    }

    .holdings-table tr:hover {
        background-color: #f9fafb;
    }

    .holdings-table tr:last-child td {
        border-bottom: none;
    }

    .stock-name {
        font-weight: 600;
        color: var(--color-gentle-black);
        font-size: 1.1rem;
    }

    .stock-quantity {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--color-earth-green);
    }

    .stock-price {
        font-weight: 600;
        color: #6b7280;
    }

    .stock-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--color-ocean-blue);
    }

    .btn-details {
        padding: 0.75rem 1.5rem;
        background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        color: white;
        border: none;
        border-radius: 9999px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
    }

    .btn-details:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        color: white;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
    }

    .empty-state-icon {
        font-size: 5rem;
        color: #d1d5db;
        margin-bottom: 1.5rem;
    }

    .empty-state h5 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #6b7280;
        margin-bottom: 0.5rem;
    }

    .empty-state p {
        font-size: 1.1rem;
        color: #9ca3af;
        margin-bottom: 2rem;
    }

    .btn-primary-large {
        padding: 1rem 2rem;
        background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        color: white;
        border: none;
        border-radius: 9999px;
        font-weight: 600;
        font-size: 1.1rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
        transition: all 0.3s ease;
    }

    .btn-primary-large:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        color: white;
    }

    /* Responsive */
    @media screen and (max-width: 768px) {
        .holdings-hero h1 {
            font-size: 1.75rem;
        }

        .holdings-table {
            font-size: 0.875rem;
        }

        .holdings-table th,
        .holdings-table td {
            padding: 0.75rem 0.5rem;
        }

        .holdings-table thead {
            display: none;
        }

        .holdings-table tr {
            display: block;
            margin-bottom: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1rem;
        }

        .holdings-table td {
            display: block;
            text-align: right;
            padding: 0.5rem 0;
            border-bottom: none;
        }

        .holdings-table td::before {
            content: attr(data-label) ': ';
            font-weight: 700;
            color: var(--color-gentle-black);
            margin-left: 0.5rem;
        }
    }

    /* Dark Mode Support */
    @media (prefers-color-scheme: dark) {
        .holdings-card {
            background: #1f2937;
            color: #f9fafb;
        }

        .holdings-header h4,
        .stock-name {
            color: #f9fafb;
        }

        .holdings-table thead {
            background: #374151;
        }

        .holdings-table tr:hover {
            background-color: #374151;
        }
    }
</style>
@endpush

@section('content')
<div class="holdings-container">
    <!-- Hero Section -->
    <div class="holdings-hero">
        <h1><i class="fas fa-wallet ml-2"></i> کیف سهام من</h1>
        <p>نمایش و مدیریت سهام‌های شما</p>
    </div>

    <!-- Holdings Table -->
    <div class="holdings-card">
        <div class="holdings-header">
            <i class="fas fa-chart-pie"></i>
            <h4>سهام‌های من</h4>
        </div>

        @if($holdings->count() > 0)
            <div style="overflow-x: auto;">
                <table class="holdings-table">
                    <thead>
                        <tr>
                            <th>نام سهام</th>
                            <th>تعداد</th>
                            <th>قیمت پایه</th>
                            <th>ارزش کل</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($holdings as $holding)
                            <tr>
                                <td>
                                    <span class="stock-name">{{ $holding->stock->info ?? 'سهام' }}</span>
                                </td>
                                <td>
                                    <span class="stock-quantity">{{ number_format($holding->quantity) }}</span>
                                </td>
                                <td>
                                    <span class="stock-price">{{ number_format($holding->stock->base_share_price) }} ریال</span>
                                </td>
                                <td>
                                    <span class="stock-value">{{ number_format($holding->total_value) }} ریال</span>
                                </td>
                                <td>
                                    <a href="{{ route('holding.show', $holding->stock_id) }}" class="btn-details">
                                        <i class="fas fa-eye"></i>
                                        جزئیات
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">
                <i class="fas fa-wallet empty-state-icon"></i>
                <h5>هنوز سهامی ندارید</h5>
                <p>برای خرید سهام در حراج‌ها شرکت کنید</p>
                <a href="{{ route('auction.index') }}" class="btn-primary-large">
                    <i class="fas fa-gavel"></i>
                    مشاهده حراج‌ها
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
