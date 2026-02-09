@extends('layouts.unified')

@section('title', 'جزئیات حراج - ' . config('app.name', 'EarthCoop'))

@push('styles')
<style>
    /* Auction Show Page Styles */
    .auction-show-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 2rem 1rem;
        direction: rtl;
    }

    /* Alert Messages */
    .alert-message {
        padding: 1rem 1.5rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .alert-success {
        background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        color: white;
    }

    .alert-error {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
    }

    /* Auction Info Card */
    .auction-info-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }

    .auction-info-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e5e7eb;
    }

    .auction-info-header i {
        font-size: 2rem;
        color: var(--color-earth-green);
    }

    .auction-info-header h4 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-gentle-black);
        margin: 0;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .info-item {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .info-label {
        font-size: 0.875rem;
        color: #6b7280;
        font-weight: 600;
    }

    .info-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--color-gentle-black);
    }

    .info-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        font-size: 0.875rem;
        font-weight: 600;
        background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        color: white;
    }

    .info-alert {
        padding: 1rem 1.5rem;
        border-radius: 12px;
        margin-top: 1.5rem;
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(59, 130, 246, 0.05) 100%);
        border-right: 4px solid var(--color-ocean-blue);
    }

    .info-alert strong {
        color: var(--color-ocean-blue);
    }

    /* Bid Form Card */
    .bid-form-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }

    .bid-form-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e5e7eb;
    }

    .bid-form-header i {
        font-size: 1.5rem;
        color: var(--color-earth-green);
    }

    .bid-form-header h5 {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--color-gentle-black);
        margin: 0;
    }

    .form-group-enhanced {
        margin-bottom: 1.5rem;
    }

    .form-label-enhanced {
        display: block;
        font-weight: 600;
        color: var(--color-gentle-black);
        margin-bottom: 0.5rem;
    }

    .form-input-enhanced {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-size: 1rem;
        transition: border-color 0.3s ease;
    }

    .form-input-enhanced:focus {
        outline: none;
        border-color: var(--color-earth-green);
    }

    .btn-submit {
        padding: 0.75rem 2rem;
        background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        color: white;
        border: none;
        border-radius: 9999px;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    /* Sidebar Cards */
    .sidebar-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .sidebar-card-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e5e7eb;
    }

    .sidebar-card-header i {
        font-size: 1.5rem;
        color: var(--color-earth-green);
    }

    .sidebar-card-header h5 {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--color-gentle-black);
        margin: 0;
    }

    .user-bid-item {
        padding: 1rem;
        background: #f9fafb;
        border-radius: 12px;
        margin-bottom: 1rem;
        border-right: 3px solid var(--color-earth-green);
    }

    .user-bid-item:last-child {
        margin-bottom: 0;
    }

    .bid-price {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--color-earth-green);
        margin-bottom: 0.5rem;
    }

    .bid-quantity {
        font-size: 1rem;
        color: #6b7280;
        margin-bottom: 0.5rem;
    }

    .bid-status {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.875rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .bid-status.active {
        background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        color: white;
    }

    .bid-status.inactive {
        background: #e5e7eb;
        color: #6b7280;
    }

    .bid-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 0.75rem;
    }

    .btn-edit {
        padding: 0.5rem 1rem;
        background: white;
        color: var(--color-ocean-blue);
        border: 2px solid var(--color-ocean-blue);
        border-radius: 9999px;
        font-weight: 600;
        text-decoration: none;
        font-size: 0.875rem;
        transition: all 0.3s ease;
    }

    .btn-edit:hover {
        background: var(--color-ocean-blue);
        color: white;
    }

    .btn-delete {
        padding: 0.5rem 1rem;
        background: white;
        color: #ef4444;
        border: 2px solid #ef4444;
        border-radius: 9999px;
        font-weight: 600;
        cursor: pointer;
        font-size: 0.875rem;
        transition: all 0.3s ease;
    }

    .btn-delete:hover {
        background: #ef4444;
        color: white;
    }

    .order-book-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .order-book-item {
        padding: 0.75rem 1rem;
        background: #f9fafb;
        border-radius: 12px;
        margin-bottom: 0.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .order-book-item:last-child {
        margin-bottom: 0;
    }

    .order-book-price {
        font-weight: 700;
        color: var(--color-earth-green);
        font-size: 1.1rem;
    }

    .order-book-quantity {
        color: #6b7280;
    }

    .order-book-user {
        font-size: 0.875rem;
        color: #9ca3af;
    }

    .empty-state {
        text-align: center;
        padding: 2rem;
        color: #9ca3af;
    }

    .empty-state i {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        opacity: 0.5;
    }

    /* Responsive */
    @media screen and (max-width: 768px) {
        .info-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Dark Mode Support */
    @media (prefers-color-scheme: dark) {
        .auction-info-card,
        .bid-form-card,
        .sidebar-card {
            background: #1f2937;
            color: #f9fafb;
        }

        .auction-info-header h4,
        .bid-form-header h5,
        .sidebar-card-header h5 {
            color: #f9fafb;
        }

        .info-value {
            color: #f9fafb;
        }

        .user-bid-item,
        .order-book-item {
            background: #374151;
        }
    }
</style>
@endpush

@section('content')
<div class="auction-show-container">
    <!-- Alert Messages -->
    @if(session('success'))
        <div class="alert-message alert-success">
            <i class="fas fa-check-circle"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    
    @if(session('error'))
        <div class="alert-message alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-3">
            <!-- Auction Info Card -->
            <div class="auction-info-card">
                <div class="auction-info-header">
                    <i class="fas fa-gavel"></i>
                    <h4>حراج سهام: {{ $auction->stock->info ?? 'سهام' }}</h4>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">تعداد سهام</span>
                        <span class="info-value">{{ number_format($auction->shares_count) }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">قیمت پایه</span>
                        <span class="info-value">{{ number_format($auction->base_price) }} ریال</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">نوع حراج</span>
                        <span class="info-badge">
                            @switch($auction->type)
                                @case('single_winner') تک برنده @break
                                @case('uniform_price') قیمت یکسان @break
                                @case('pay_as_bid') پرداخت به قیمت پیشنهادی @break
                                @default {{ $auction->type ?? '-' }}
                            @endswitch
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">شروع</span>
                        <span class="info-value">{{ $auction->start_time ? verta($auction->start_time)->format('Y/m/d H:i') : '-' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">پایان</span>
                        <span class="info-value">{{ $auction->ends_at ? verta($auction->ends_at)->format('Y/m/d H:i') : '-' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">وضعیت</span>
                        <span class="info-badge" style="background: {{ $auction->status === 'running' ? 'linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%)' : 'linear-gradient(135deg, #6b7280 0%, #4b5563 100%)' }};">
                            {{ $auction->status === 'running' ? 'فعال' : 'غیرفعال' }}
                        </span>
                    </div>
                </div>

                @if($auction->min_bid || $auction->max_bid)
                    <div class="info-alert">
                        <strong>محدوده قیمت:</strong>
                        @if($auction->min_bid) حداقل: {{ number_format($auction->min_bid) }} ریال @endif
                        @if($auction->max_bid) حداکثر: {{ number_format($auction->max_bid) }} ریال @endif
                    </div>
                @endif

                @if($auction->info)
                    <div class="info-alert" style="margin-top: 1.5rem;">
                        <strong>توضیحات:</strong><br>
                        <div style="margin-top: 0.5rem; line-height: 1.8;">{{ $auction->info }}</div>
                    </div>
                @endif
            </div>

            <!-- Bid Form -->
            @if($auction->isActive())
                <div class="bid-form-card">
                    <div class="bid-form-header">
                        <i class="fas fa-hand-holding-usd"></i>
                        <h5>ثبت پیشنهاد</h5>
                    </div>
                    <form method="POST" action="{{ route('auction.bid', $auction) }}">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="form-group-enhanced">
                                <label class="form-label-enhanced">قیمت پیشنهادی (ریال)</label>
                                <input type="number" 
                                       name="price" 
                                       class="form-input-enhanced" 
                                       min="{{ $auction->min_bid ?? 0 }}" 
                                       max="{{ $auction->max_bid ?? '' }}"
                                       required>
                            </div>
                            <div class="form-group-enhanced">
                                <label class="form-label-enhanced">تعداد سهام</label>
                                <input type="number" 
                                       name="quantity" 
                                       class="form-input-enhanced" 
                                       min="1" 
                                       max="{{ $auction->lot_size }}"
                                       required>
                            </div>
                        </div>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i>
                            ثبت پیشنهاد
                        </button>
                    </form>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <!-- User Bids -->
            <div class="sidebar-card">
                <div class="sidebar-card-header">
                    <i class="fas fa-user-check"></i>
                    <h5>پیشنهادات شما</h5>
                </div>
                <div>
                    @if($userBids->count() > 0)
                        @foreach($userBids as $bid)
                            <div class="user-bid-item">
                                <div class="bid-price">{{ number_format($bid->price) }} ریال</div>
                                <div class="bid-quantity">تعداد: {{ number_format($bid->quantity) }}</div>
                                <span class="bid-status {{ $bid->status === 'active' ? 'active' : 'inactive' }}">
                                    {{ $bid->status === 'active' ? 'فعال' : 'غیرفعال' }}
                                </span>
                                <div style="font-size: 0.875rem; color: #9ca3af; margin-top: 0.5rem;">
                                    {{ $bid->created_at ? verta($bid->created_at)->format('Y/m/d H:i') : '-' }}
                                </div>
                                @if($auction->isActive() && $bid->status === 'active')
                                    <div class="bid-actions">
                                        <a href="{{ route('bid.edit', $bid) }}" class="btn-edit">
                                            <i class="fas fa-edit"></i>
                                            ویرایش
                                        </a>
                                        <form method="POST" action="{{ route('bid.destroy', $bid) }}" style="display:inline-block;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-delete" onclick="return confirm('آیا برای حذف پیشنهاد مطمئن هستید؟')">
                                                <i class="fas fa-trash"></i>
                                                حذف
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    @else
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p style="font-size: 0.9rem;">هنوز پیشنهادی ثبت نکرده‌اید.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Order Book -->
            <div class="sidebar-card">
                <div class="sidebar-card-header">
                    <i class="fas fa-list"></i>
                    <h5>صف پیشنهادات (عمومی)</h5>
                </div>
                <div>
                    @php $orderBook = $auction->bids->where('status', 'active')->sortByDesc('price'); @endphp
                    @if($orderBook->count())
                        <ul class="order-book-list">
                            @foreach($orderBook as $b)
                                <li class="order-book-item">
                                    <div>
                                        <div class="order-book-price">{{ number_format($b->price) }} ریال</div>
                                        <div class="order-book-quantity">{{ number_format($b->quantity) }} سهم</div>
                                    </div>
                                    <div class="order-book-user">کاربر #{{ $b->user_id }}</div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p style="font-size: 0.9rem;">هنوز پیشنهادی ثبت نشده است.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
