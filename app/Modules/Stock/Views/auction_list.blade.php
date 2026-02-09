@extends('layouts.unified')

@section('title', 'لیست حراج‌های سهام - ' . config('app.name', 'EarthCoop'))

@push('styles')
<style>
    /* Auction List Page Styles */
    .auction-list-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 2rem 1rem;
        direction: rtl;
    }

    /* Header Card */
    .header-card {
        background: linear-gradient(135deg, var(--color-ocean-blue) 0%, var(--color-dark-blue) 100%);
        color: white;
        padding: 2rem;
        border-radius: 20px;
        margin-bottom: 2rem;
        box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3);
    }

    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 2rem;
    }

    .header-title {
        font-size: 2rem;
        font-weight: 800;
        margin: 0;
    }

    .header-info {
        text-align: left;
        font-size: 0.9rem;
        opacity: 0.95;
    }

    .header-actions {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .btn-header {
        padding: 0.75rem 1.5rem;
        border-radius: 9999px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-header-warning {
        background: white;
        color: var(--color-digital-gold);
        border: 2px solid white;
    }

    .btn-header-warning:hover {
        background: var(--color-digital-gold);
        color: white;
    }

    .btn-header-success {
        background: white;
        color: var(--color-earth-green);
        border: 2px solid white;
    }

    .btn-header-success:hover {
        background: var(--color-earth-green);
        color: white;
    }

    /* Auctions Table Card */
    .auctions-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        overflow: hidden;
    }

    .auctions-table {
        width: 100%;
        border-collapse: collapse;
    }

    .auctions-table thead {
        background: #f9fafb;
    }

    .auctions-table th {
        padding: 1rem;
        text-align: right;
        font-weight: 700;
        color: var(--color-gentle-black);
        border-bottom: 2px solid #e5e7eb;
    }

    .auctions-table td {
        padding: 1rem;
        text-align: right;
        color: #4b5563;
        border-bottom: 1px solid #e5e7eb;
    }

    .auctions-table tr:hover {
        background-color: #f9fafb;
    }

    .auctions-table tr:last-child td {
        border-bottom: none;
    }

    .status-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .status-badge.scheduled {
        background: linear-gradient(135deg, var(--color-digital-gold) 0%, #f59e0b 100%);
        color: white;
    }

    .status-badge.running {
        background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        color: white;
    }

    .status-badge.settling {
        background: linear-gradient(135deg, var(--color-ocean-blue) 0%, var(--color-dark-blue) 100%);
        color: white;
    }

    .status-badge.settled {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        color: white;
    }

    .status-badge.canceled {
        background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
        color: white;
    }

    .btn-action {
        padding: 0.5rem 1.5rem;
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
        font-size: 0.875rem;
    }

    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
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

    .empty-state p {
        font-size: 1.25rem;
    }

    /* Responsive */
    @media screen and (max-width: 768px) {
        .header-content {
            flex-direction: column;
            align-items: flex-start;
        }

        .header-title {
            font-size: 1.5rem;
        }

        .auctions-table {
            font-size: 0.875rem;
        }

        .auctions-table th,
        .auctions-table td {
            padding: 0.75rem 0.5rem;
        }

        .auctions-table thead {
            display: none;
        }

        .auctions-table tr {
            display: block;
            margin-bottom: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1rem;
        }

        .auctions-table td {
            display: block;
            text-align: right;
            padding: 0.5rem 0;
            border-bottom: none;
        }

        .auctions-table td::before {
            content: attr(data-label) ': ';
            font-weight: 700;
            color: var(--color-gentle-black);
            margin-left: 0.5rem;
        }
    }

    /* Dark Mode Support */
    @media (prefers-color-scheme: dark) {
        .auctions-card {
            background: #1f2937;
            color: #f9fafb;
        }

        .auctions-table thead {
            background: #374151;
        }

        .auctions-table tr:hover {
            background-color: #374151;
        }
    }
</style>
@endpush

@section('content')
<div class="auction-list-container">
    <!-- Header Card -->
    <div class="header-card">
        <div class="header-content">
            <div>
                <h1 class="header-title">
                    <i class="fas fa-gavel ml-2"></i>
                    لیست حراج‌های سهام
                </h1>
                @if(isset($stock))
                    <div class="header-info" style="margin-top: 1rem;">
                        <div><strong>ارزش پایه پلتفرم:</strong> {{ number_format($stock->startup_valuation) }} ریال</div>
                        <div><strong>تعداد کل سهام:</strong> {{ number_format($stock->total_shares) }}</div>
                        <div><strong>ارزش پایه هر سهم:</strong> {{ number_format($stock->base_share_price) }} ریال</div>
                    </div>
                @endif
            </div>
            <div class="header-actions">
                <a href="{{ route('wallet.index') }}" class="btn-header btn-header-warning">
                    <i class="fas fa-wallet"></i>
                    کیف‌پول
                </a>
                <a href="{{ route('holding.index') }}" class="btn-header btn-header-success">
                    <i class="fas fa-briefcase"></i>
                    کیف‌سهام
                </a>
            </div>
        </div>
    </div>

    <!-- Auctions Table -->
    <div class="auctions-card">
        @if($auctions->count())
            <div style="overflow-x: auto;">
                <table class="auctions-table">
                    <thead>
                        <tr>
                            <th>تعداد سهام</th>
                            <th>قیمت پایه</th>
                            <th>نوع حراج</th>
                            <th>زمان شروع</th>
                            <th>زمان پایان</th>
                            <th>وضعیت</th>
                            <th>پیشنهادات</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($auctions as $auction)
                        <tr>
                            <td data-label="تعداد سهام">{{ number_format($auction->shares_count) }}</td>
                            <td data-label="قیمت پایه">{{ number_format($auction->base_price) }} ریال</td>
                            <td data-label="نوع حراج">
                                @switch($auction->type)
                                    @case('single_winner') تک برنده @break
                                    @case('uniform_price') قیمت یکسان @break
                                    @case('pay_as_bid') پرداخت به قیمت پیشنهادی @break
                                    @default {{ $auction->type ?? '-' }}
                                @endswitch
                            </td>
                            <td data-label="زمان شروع">{{ $auction->start_time ? verta($auction->start_time)->format('Y/m/d H:i') : '-' }}</td>
                            <td data-label="زمان پایان">{{ $auction->ends_at ? verta($auction->ends_at)->format('Y/m/d H:i') : '-' }}</td>
                            <td data-label="وضعیت">
                                <span class="status-badge {{ $auction->status }}">
                                    @switch($auction->status)
                                        @case('scheduled') برنامه‌ریزی شده @break
                                        @case('running') فعال @break
                                        @case('settling') در حال تسویه @break
                                        @case('settled') تسویه شده @break
                                        @case('canceled') لغو شده @break
                                        @default {{ $auction->status ?? '-' }}
                                    @endswitch
                                </span>
                            </td>
                            <td data-label="پیشنهادات">{{ $auction->bids()->count() }}</td>
                            <td data-label="عملیات">
                                <a href="{{ route('auction.show', $auction) }}" class="btn-action">
                                    <i class="fas fa-eye"></i>
                                    {{ $auction->isActive() ? 'شرکت در حراج' : 'مشاهده' }}
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">
                <i class="fas fa-gavel"></i>
                <p>هیچ حراج فعالی وجود ندارد.</p>
            </div>
        @endif
    </div>
</div>
@endsection
