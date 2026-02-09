@extends('layouts.unified')

@section('title', 'دفتر سهام - ' . config('app.name', 'EarthCoop'))

@push('styles')
<style>
    /* Stock Book Page Styles */
    .stock-book-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 2rem 1rem;
        direction: rtl;
    }

    /* Hero Section */
    .stock-hero {
        background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        color: white;
        padding: 3rem 2rem;
        border-radius: 20px;
        margin-bottom: 2rem;
        text-align: center;
        box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
    }

    .stock-hero h1 {
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 1rem;
    }

    .stock-hero p {
        font-size: 1.25rem;
        opacity: 0.95;
    }

    /* Info Cards */
    .info-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .info-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    }

    .info-card-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e5e7eb;
    }

    .info-card-header i {
        font-size: 2rem;
        color: var(--color-earth-green);
    }

    .info-card-header h4 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-gentle-black);
        margin: 0;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-top: 1.5rem;
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
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-gentle-black);
    }

    .info-value.highlight {
        color: var(--color-earth-green);
    }

    /* Auction Cards */
    .auction-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 1.5rem;
        border-right: 4px solid var(--color-earth-green);
        transition: all 0.3s ease;
    }

    .auction-card:hover {
        transform: translateX(-5px);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    }

    .auction-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .auction-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--color-gentle-black);
    }

    .auction-status {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .auction-status.running {
        background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        color: white;
    }

    .auction-status.scheduled {
        background: linear-gradient(135deg, var(--color-ocean-blue) 0%, var(--color-dark-blue) 100%);
        color: white;
    }

    .auction-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .auction-detail-item {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .auction-detail-label {
        font-size: 0.875rem;
        color: #6b7280;
        font-weight: 600;
    }

    .auction-detail-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--color-gentle-black);
    }

    .countdown-timer {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        background: linear-gradient(135deg, var(--color-digital-gold) 0%, #f59e0b 100%);
        color: white;
        border-radius: 9999px;
        font-weight: 700;
        font-size: 1.1rem;
    }

    .countdown-timer i {
        font-size: 1.25rem;
    }

    .auction-actions {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .btn-auction {
        padding: 0.75rem 2rem;
        border-radius: 9999px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-auction-primary {
        background: linear-gradient(135deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        color: white;
    }

    .btn-auction-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        color: white;
    }

    .btn-auction-secondary {
        background: white;
        color: var(--color-earth-green);
        border: 2px solid var(--color-earth-green);
    }

    .btn-auction-secondary:hover {
        background: var(--color-earth-green);
        color: white;
    }

    /* Sidebar Cards */
    .sidebar-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .sidebar-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
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

    .wallet-balance {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .balance-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        background: #f9fafb;
        border-radius: 12px;
    }

    .balance-label {
        font-size: 0.875rem;
        color: #6b7280;
        font-weight: 600;
    }

    .balance-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--color-gentle-black);
    }

    .balance-value.available {
        color: var(--color-earth-green);
    }

    .balance-value.held {
        color: var(--color-digital-gold);
    }

    .holdings-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .holding-item {
        padding: 1rem;
        background: #f9fafb;
        border-radius: 12px;
        border-right: 3px solid var(--color-earth-green);
    }

    .holding-quantity {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-earth-green);
        margin-bottom: 0.5rem;
    }

    .holding-value {
        font-size: 1rem;
        color: #6b7280;
    }

    .empty-state {
        text-align: center;
        padding: 2rem;
        color: #9ca3af;
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .empty-state p {
        font-size: 1.1rem;
    }

    /* Rules Section */
    .rules-section {
        margin-top: 2rem;
        padding-top: 2rem;
        border-top: 2px solid #e5e7eb;
    }

    .rules-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--color-gentle-black);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .rules-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .rules-list li {
        padding: 0.75rem 0;
        padding-right: 2rem;
        position: relative;
        color: #4b5563;
        line-height: 1.6;
    }

    .rules-list li::before {
        content: '✓';
        position: absolute;
        right: 0;
        top: 0.75rem;
        color: var(--color-earth-green);
        font-weight: 700;
        font-size: 1.25rem;
    }

    /* Responsive */
    @media screen and (max-width: 768px) {
        .stock-hero h1 {
            font-size: 1.75rem;
        }

        .stock-hero p {
            font-size: 1rem;
        }

        .info-grid {
            grid-template-columns: 1fr;
        }

        .auction-details {
            grid-template-columns: 1fr;
        }

        .auction-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }

    /* Dark Mode Support */
    @media (prefers-color-scheme: dark) {
        .info-card,
        .auction-card,
        .sidebar-card {
            background: #1f2937;
            color: #f9fafb;
        }

        .info-card-header h4,
        .auction-title,
        .sidebar-card-header h5 {
            color: #f9fafb;
        }

        .info-value,
        .auction-detail-value,
        .balance-value {
            color: #f9fafb;
        }

        .balance-item,
        .holding-item {
            background: #374151;
        }
    }
</style>
@endpush

@section('content')
<div class="stock-book-container">
    <!-- Hero Section -->
    <div class="stock-hero">
        <h1><i class="fas fa-chart-line ml-2"></i> دفتر سهام EarthCoop</h1>
        <p>بازار سهام تعاونی - خرید و فروش سهام با شفافیت کامل</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-3">
            <!-- Stock Info Card -->
            <div class="info-card">
                <div class="info-card-header">
                    <i class="fas fa-info-circle"></i>
                    <h4>اطلاعات پایه سهام ارثکوپ</h4>
                </div>

                @if($stock)
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">ارزش پایه استارتاپ</span>
                            <span class="info-value highlight">{{ number_format($stock->startup_valuation ?? 0) }} ریال</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">تعداد کل سهام</span>
                            <span class="info-value">{{ number_format($stock->total_shares ?? 0) }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">سهام قابل عرضه</span>
                            <span class="info-value">{{ number_format($stock->available_shares ?? 0) }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">ارزش پایه هر سهم</span>
                            <span class="info-value highlight">{{ number_format($stock->base_share_price ?? 0) }} ریال</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">سهام فروخته شده</span>
                            <span class="info-value">{{ number_format($soldShares ?? 0) }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">باقی‌مانده</span>
                            <span class="info-value highlight">{{ number_format((($stock->available_shares ?? 0) - ($soldShares ?? 0))) }}</span>
                        </div>
                    </div>

                    @if($stock->info)
                        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #e5e7eb;">
                            <div style="color: #4b5563; line-height: 1.8;">{{ $stock->info }}</div>
                        </div>
                    @endif

                    <div class="rules-section">
                        <h6 class="rules-title">
                            <i class="fas fa-gavel"></i>
                            قوانین و شرایط فروش سهام
                        </h6>
                        <ul class="rules-list">
                            <li>خرید سهام منوط به داشتن کیف پول معتبر و داشتن موجودی کافی است.</li>
                            <li>تا پایان زمان حراج، کاربران می‌توانند پیشنهادهای خود را ویرایش یا لغو کنند.</li>
                            <li>تسویه حراج‌ها مطابق قوانین حراج انجام می‌شود و ممکن است پس از پایان حراج چند دقیقه زمان ببرد.</li>
                        </ul>
                    </div>
                @else
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>اطلاعات سهام تعریف نشده است.</p>
                    </div>
                @endif
            </div>

            <!-- Auctions List -->
            <div class="info-card">
                <div class="info-card-header">
                    <i class="fas fa-gavel"></i>
                    <h4>فهرست حراج‌ها</h4>
                </div>

                @if($auctions && $auctions->count())
                    @foreach($auctions as $auction)
                        <div class="auction-card">
                            <div class="auction-header">
                                <div>
                                    <h5 class="auction-title">
                                        حراج {{ number_format($auction->shares_count) }} سهم
                                    </h5>
                                    <p style="color: #6b7280; margin-top: 0.5rem;">
                                        ارزش پایه هر سهم: <strong>{{ number_format($auction->base_price) }} ریال</strong>
                                    </p>
                                </div>
                                <span class="auction-status {{ $auction->status === 'running' ? 'running' : 'scheduled' }}">
                                    {{ $auction->status === 'running' ? 'در حال اجرا' : 'زمان‌بندی شده' }}
                                </span>
                            </div>

                            <div class="auction-details">
                                @php $endsAtIso = $auction->ends_at ? $auction->ends_at->toIso8601String() : null; @endphp
                                @if($endsAtIso)
                                    <div class="auction-detail-item">
                                        <span class="auction-detail-label">زمان باقی‌مانده</span>
                                        <span class="countdown-timer auction-countdown" data-ends-at="{{ $endsAtIso }}">
                                            <i class="fas fa-clock"></i>
                                            در حال بارگذاری...
                                        </span>
                                    </div>
                                @else
                                    <div class="auction-detail-item">
                                        <span class="auction-detail-label">وضعیت</span>
                                        <span class="auction-detail-value" style="color: #9ca3af;">زمان پایان مشخص یا گذشته است</span>
                                    </div>
                                @endif

                                <div class="auction-detail-item">
                                    <span class="auction-detail-label">بالاترین پیشنهاد</span>
                                    @if($auction->highest_price)
                                        <span class="auction-detail-value" style="color: var(--color-earth-green);">
                                            {{ number_format($auction->highest_quantity) }} سهم
                                            <span style="font-size: 0.875rem; color: #6b7280;">به قیمت</span>
                                            {{ number_format($auction->highest_price) }} ریال
                                        </span>
                                    @else
                                        <span class="auction-detail-value" style="color: #9ca3af;">فعلاً پیشنهادی ثبت نشده است</span>
                                    @endif
                                </div>
                            </div>

                            <div class="auction-actions">
                                <a href="{{ route('auction.show', $auction) }}" class="btn-auction btn-auction-primary">
                                    <i class="fas fa-hand-holding-usd"></i>
                                    مشاهده و شرکت در حراج
                                </a>
                                <a href="{{ route('auction.show', $auction) }}" class="btn-auction btn-auction-secondary">
                                    <i class="fas fa-info-circle"></i>
                                    جزئیات
                                </a>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="empty-state">
                        <i class="fas fa-gavel"></i>
                        <p>فعلاً هیچ حراجی برای نمایش وجود ندارد.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <!-- Secondary Market (Placeholder) -->
            <div class="sidebar-card">
                <div class="sidebar-card-header">
                    <i class="fas fa-exchange-alt"></i>
                    <h5>بازار جانبی</h5>
                </div>
                <div style="text-align: center; padding: 1rem 0;">
                    <i class="fas fa-clock" style="font-size: 2rem; color: #9ca3af; margin-bottom: 1rem;"></i>
                    <p style="color: #6b7280; margin-bottom: 1rem;">این بخش برای معاملات ثانویه در آینده فعال خواهد شد.</p>
                    <button style="padding: 0.75rem 1.5rem; background: #e5e7eb; color: #6b7280; border: none; border-radius: 9999px; cursor: not-allowed;" disabled>
                        بازار جانبی (مهیا می‌شود)
                    </button>
                </div>
            </div>

            <!-- Holdings / کیف سهام -->
            <div class="sidebar-card">
                <div class="sidebar-card-header">
                    <i class="fas fa-wallet"></i>
                    <h5>کیف سهام شما</h5>
                </div>
                <div>
                    @if(auth()->check())
                        @if($userHoldings && $userHoldings->count())
                            <div class="holdings-list">
                                @foreach($userHoldings as $h)
                                    <div class="holding-item">
                                        <div class="holding-quantity">{{ number_format($h->quantity) }} سهم</div>
                                        <div class="holding-value">ارزش جاری: {{ number_format($h->total_value) }} ریال</div>
                                    </div>
                                @endforeach
                            </div>
                            <a href="{{ route('holding.index') }}" class="btn-auction btn-auction-secondary" style="width: 100%; justify-content: center; margin-top: 1rem;">
                                <i class="fas fa-eye"></i>
                                جزئیات کیف سهام
                            </a>
                        @else
                            <div class="empty-state" style="padding: 1rem;">
                                <i class="fas fa-wallet"></i>
                                <p style="font-size: 0.9rem;">هنوز سهامی در کیف شما نیست.</p>
                            </div>
                        @endif
                    @else
                        <div class="empty-state" style="padding: 1rem;">
                            <i class="fas fa-sign-in-alt"></i>
                            <p style="font-size: 0.9rem;">برای مشاهده کیف سهام، لطفاً وارد شوید.</p>
                            <a href="{{ route('login') }}" class="btn-auction btn-auction-primary" style="margin-top: 1rem;">
                                <i class="fas fa-sign-in-alt"></i>
                                ورود
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Wallet / کیف پول -->
            <div class="sidebar-card">
                <div class="sidebar-card-header">
                    <i class="fas fa-money-bill-wave"></i>
                    <h5>کیف پول</h5>
                </div>
                <div>
                    @if(auth()->check())
                        @if($walletData)
                            <div class="wallet-balance">
                                <div class="balance-item">
                                    <span class="balance-label">موجودی قابل برداشت</span>
                                    <span class="balance-value available">{{ number_format($walletData['available_balance'] ?? 0) }} ریال</span>
                                </div>
                                <div class="balance-item">
                                    <span class="balance-label">مبلغ رزرو شده</span>
                                    <span class="balance-value held">{{ number_format($walletData['held_amount'] ?? 0) }} ریال</span>
                                </div>
                            </div>
                            <a href="{{ route('wallet.index') }}" class="btn-auction btn-auction-secondary" style="width: 100%; justify-content: center; margin-top: 1rem;">
                                <i class="fas fa-eye"></i>
                                مشاهده کیف پول
                            </a>
                        @else
                            <div class="empty-state" style="padding: 1rem;">
                                <i class="fas fa-exclamation-triangle"></i>
                                <p style="font-size: 0.9rem;">خطا در بارگذاری کیف پول.</p>
                            </div>
                        @endif
                    @else
                        <div class="empty-state" style="padding: 1rem;">
                            <i class="fas fa-sign-in-alt"></i>
                            <p style="font-size: 0.9rem;">برای مشاهده کیف پول، لطفاً وارد شوید.</p>
                            <a href="{{ route('login') }}" class="btn-auction btn-auction-primary" style="margin-top: 1rem;">
                                <i class="fas fa-sign-in-alt"></i>
                                ورود
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Countdown timers for auctions
    (function(){
        function pad(n){ return n < 10 ? '0'+n : n; }

        function updateCountdown(el){
            var endsAt = el.getAttribute('data-ends-at');
            if(!endsAt) return;
            var end = new Date(endsAt);
            var now = new Date();
            var diff = end - now;
            if(diff <= 0){
                el.innerHTML = '<i class="fas fa-check-circle"></i> پایان یافت';
                el.style.background = 'linear-gradient(135deg, #6b7280 0%, #4b5563 100%)';
                return;
            }
            var seconds = Math.floor(diff / 1000);
            var days = Math.floor(seconds / 86400);
            seconds %= 86400;
            var hours = Math.floor(seconds / 3600);
            seconds %= 3600;
            var minutes = Math.floor(seconds / 60);
            seconds %= 60;
            
            var timeStr = '';
            if(days > 0) timeStr += days + ' روز ';
            timeStr += pad(hours) + ':' + pad(minutes) + ':' + pad(seconds);
            
            el.innerHTML = '<i class="fas fa-clock"></i> ' + timeStr;
        }

        var els = Array.prototype.slice.call(document.querySelectorAll('.auction-countdown'));
        if(els.length){
            els.forEach(function(el){ updateCountdown(el); });
            setInterval(function(){ els.forEach(function(el){ updateCountdown(el); }); }, 1000);
        }
    })();
</script>
@endpush
