@extends('layouts.unified')

@section('title', 'کیف پول - ' . config('app.name', 'EarthCoop'))

@push('styles')
<style>
    /* Wallet Page Styles */
    .wallet-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem 1rem;
        direction: rtl;
    }

    /* Wallet Hero */
    .wallet-hero {
        background: linear-gradient(135deg, var(--color-ocean-blue) 0%, var(--color-dark-blue) 100%);
        color: white;
        padding: 3rem 2rem;
        border-radius: 20px;
        margin-bottom: 2rem;
        text-align: center;
        box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3);
    }

    .wallet-hero h1 {
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 1rem;
    }

    /* Balance Cards */
    .balance-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .balance-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        text-align: center;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border-top: 4px solid;
    }

    .balance-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    }

    .balance-card.available {
        border-top-color: var(--color-earth-green);
    }

    .balance-card.total {
        border-top-color: var(--color-ocean-blue);
    }

    .balance-card.held {
        border-top-color: var(--color-digital-gold);
    }

    .balance-card-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
    }

    .balance-card.available .balance-card-icon {
        color: var(--color-earth-green);
    }

    .balance-card.total .balance-card-icon {
        color: var(--color-ocean-blue);
    }

    .balance-card.held .balance-card-icon {
        color: var(--color-digital-gold);
    }

    .balance-card-label {
        font-size: 1rem;
        color: #6b7280;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .balance-card-value {
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
        gap: 1rem;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e5e7eb;
    }

    .transactions-header i {
        font-size: 2rem;
        color: var(--color-ocean-blue);
    }

    .transactions-header h4 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-gentle-black);
        margin: 0;
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

    .transaction-badge.credit {
        background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        color: white;
    }

    .transaction-badge.debit {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
    }

    .transaction-badge.hold {
        background: linear-gradient(135deg, var(--color-digital-gold) 0%, #f59e0b 100%);
        color: white;
    }

    .transaction-badge.release {
        background: linear-gradient(135deg, var(--color-ocean-blue) 0%, var(--color-dark-blue) 100%);
        color: white;
    }

    .transaction-badge.settlement {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        color: white;
    }

    /* Quick Links */
    .quick-links-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
    }

    .quick-links-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e5e7eb;
    }

    .quick-links-header i {
        font-size: 1.5rem;
        color: var(--color-earth-green);
    }

    .quick-links-header h5 {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--color-gentle-black);
        margin: 0;
    }

    .quick-link-btn {
        display: block;
        width: 100%;
        padding: 1rem;
        margin-bottom: 1rem;
        background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        text-decoration: none;
        text-align: center;
        transition: all 0.3s ease;
    }

    .quick-link-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        color: white;
    }

    .quick-link-btn.secondary {
        background: linear-gradient(135deg, var(--color-ocean-blue) 0%, var(--color-dark-blue) 100%);
    }

    .quick-link-btn.secondary:hover {
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
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
        .wallet-hero h1 {
            font-size: 1.75rem;
        }

        .balance-cards {
            grid-template-columns: 1fr;
        }

        .transactions-table {
            font-size: 0.875rem;
        }

        .transactions-table th,
        .transactions-table td {
            padding: 0.75rem 0.5rem;
        }
    }

    /* Dark Mode Support */
    @media (prefers-color-scheme: dark) {
        .balance-card,
        .transactions-card,
        .quick-links-card {
            background: #1f2937;
            color: #f9fafb;
        }

        .transactions-header h4,
        .quick-links-header h5 {
            color: #f9fafb;
        }

        .balance-card-value {
            color: #f9fafb;
        }

        .transactions-table thead {
            background: #374151;
        }

        .transactions-table tr:hover {
            background-color: #374151;
        }
    }
</style>
@endpush

@section('content')
<div class="wallet-container">
    <!-- Hero Section -->
    <div class="wallet-hero">
        <h1><i class="fas fa-wallet ml-2"></i> کیف پول من</h1>
        <p>مدیریت موجودی و تراکنش‌های مالی شما</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-3">
            <!-- Balance Cards -->
            <div class="balance-cards">
                <div class="balance-card available">
                    <div class="balance-card-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="balance-card-label">موجودی قابل استفاده</div>
                    <div class="balance-card-value" style="color: var(--color-earth-green);">
                        {{ number_format($wallet->available_balance) }} ریال
                    </div>
                </div>

                <div class="balance-card total">
                    <div class="balance-card-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="balance-card-label">موجودی کل</div>
                    <div class="balance-card-value" style="color: var(--color-ocean-blue);">
                        {{ number_format($wallet->balance) }} ریال
                    </div>
                </div>

                <div class="balance-card held">
                    <div class="balance-card-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="balance-card-label">مبلغ رزرو شده</div>
                    <div class="balance-card-value" style="color: var(--color-digital-gold);">
                        {{ number_format($wallet->held_amount) }} ریال
                    </div>
                </div>
            </div>

            <!-- Transactions History -->
            <div class="transactions-card">
                <div class="transactions-header">
                    <i class="fas fa-history"></i>
                    <h4>تاریخچه تراکنش‌ها</h4>
                </div>

                @if($transactions->count() > 0)
                    <div style="overflow-x: auto;">
                        <table class="transactions-table">
                            <thead>
                                <tr>
                                    <th>نوع</th>
                                    <th>مبلغ</th>
                                    <th>توضیحات</th>
                                    <th>تاریخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transactions as $transaction)
                                    <tr>
                                        <td>
                                            <span class="transaction-badge {{ $transaction->type }}">
                                                @switch($transaction->type)
                                                    @case('credit') واریز @break
                                                    @case('debit') برداشت @break
                                                    @case('hold') رزرو @break
                                                    @case('release') آزادسازی @break
                                                    @case('settlement') تسویه @break
                                                @endswitch
                                            </span>
                                        </td>
                                        <td style="font-weight: 600;">{{ number_format($transaction->amount) }} ریال</td>
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
                        <p>هنوز تراکنشی انجام نداده‌اید.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <div class="quick-links-card">
                <div class="quick-links-header">
                    <i class="fas fa-link"></i>
                    <h5>دسترسی سریع</h5>
                </div>
                <a href="{{ route('auction.index') }}" class="quick-link-btn">
                    <i class="fas fa-gavel ml-2"></i>
                    مشاهده حراج‌ها
                </a>
                <a href="{{ route('holding.index') }}" class="quick-link-btn secondary">
                    <i class="fas fa-wallet ml-2"></i>
                    کیف سهام من
                </a>
                <a href="{{ route('stock.book') }}" class="quick-link-btn">
                    <i class="fas fa-chart-line ml-2"></i>
                    دفتر سهام
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
