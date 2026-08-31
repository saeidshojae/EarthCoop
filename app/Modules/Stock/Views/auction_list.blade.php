@extends('layouts.unified')

@section('title', 'حراج‌های سهام EarthCoop - ' . config('app.name', 'EarthCoop'))

@php
    $valuationGol = (int) ($stock->startup_valuation_gol ?? 0);
    $valuationBahar = $valuationGol / 100;
    $sharePriceGol = (int) ($stock->base_share_price_gol ?? 0);
    $sharePriceBahar = $sharePriceGol / 100;
@endphp

@push('styles')
<style>
    .auction-list-container{max-width:1400px;margin:0 auto;padding:2rem 1rem;direction:rtl}
    .header-card{background:linear-gradient(135deg,#064e3b,#0f766e);color:#fff;padding:2rem;border-radius:20px;margin-bottom:2rem;box-shadow:0 10px 30px rgba(6,78,59,.22)}
    .header-content{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:2rem}.header-title{font-size:2rem;font-weight:800;margin:0 0 .75rem}.header-info{font-size:.9rem;line-height:2}.header-actions{display:flex;gap:.75rem;flex-wrap:wrap}
    .btn-header{padding:.75rem 1.3rem;border-radius:999px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;background:#fff;color:#047857;border:2px solid #fff}.btn-header:hover{background:#ecfdf5;color:#065f46}
    .auctions-card{background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,.08);padding:2rem;overflow:hidden}.auctions-table{width:100%;border-collapse:collapse}.auctions-table thead{background:#f9fafb}.auctions-table th{padding:1rem;text-align:right;font-weight:700;color:#111827;border-bottom:2px solid #e5e7eb}.auctions-table td{padding:1rem;text-align:right;color:#4b5563;border-bottom:1px solid #e5e7eb}.auctions-table tr:hover{background:#f9fafb}.auctions-table tr:last-child td{border-bottom:none}
    .price-stack{display:grid;gap:.2rem}.price-stack strong{color:#0f172a}.price-stack small{color:#64748b}.meta-badge{display:inline-flex;border-radius:999px;padding:.35rem .65rem;background:#f1f5f9;color:#475569;font-size:.75rem;font-weight:700;margin:.12rem}
    .status-badge{display:inline-block;padding:.5rem 1rem;border-radius:9999px;font-size:.875rem;font-weight:600}.status-badge.scheduled{background:#f59e0b;color:#fff}.status-badge.running{background:#047857;color:#fff}.status-badge.settling{background:#2563eb;color:#fff}.status-badge.settled{background:#7c3aed;color:#fff}.status-badge.canceled,.status-badge.cancelled{background:#6b7280;color:#fff}
    .btn-action{padding:.5rem 1.25rem;background:#047857;color:#fff;border-radius:999px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem}.empty-state{text-align:center;padding:3rem 1rem;color:#9ca3af}.empty-state i{font-size:3rem;margin-bottom:1rem;opacity:.5}.empty-state p{font-size:1.1rem}
    .dark .auctions-card{background:#0f172a}.dark .auctions-table thead{background:#1e293b}.dark .auctions-table th,.dark .price-stack strong{color:#f8fafc}.dark .auctions-table td,.dark .price-stack small{color:#cbd5e1}.dark .auctions-table td{border-color:#334155}.dark .auctions-table tr:hover{background:#1e293b}.dark .meta-badge{background:#1e293b;color:#cbd5e1}
    @media(max-width:768px){.auction-list-container{padding:1rem}.header-card{padding:1.35rem}.header-content{flex-direction:column}.header-title{font-size:1.5rem}.header-actions{width:100%}.btn-header{flex:1;justify-content:center}.auctions-card{padding:1rem}.auctions-table thead{display:none}.auctions-table tr{display:block;margin-bottom:1rem;border:1px solid #e5e7eb;border-radius:12px;padding:1rem}.dark .auctions-table tr{border-color:#334155}.auctions-table td{display:block;text-align:right;padding:.5rem 0;border-bottom:none}.auctions-table td::before{content:attr(data-label) ': ';font-weight:700;color:#334155;margin-left:.5rem}.dark .auctions-table td::before{color:#cbd5e1}}
</style>
@endpush

@section('content')
<div class="auction-list-container">
    <section class="header-card">
        <div class="header-content">
            <div>
                <h1 class="header-title"><i class="fas fa-gavel ml-2"></i>حراج‌های سهام EarthCoop</h1>
                @if(isset($stock))
                    <div class="header-info">
                        <div><strong>ارزش‌گذاری کل:</strong> {{ number_format($valuationGol) }} گل · {{ number_format($valuationBahar, 0) }} بهار</div>
                        <div><strong>تعداد کل سهام:</strong> {{ number_format($stock->total_shares) }} سهم</div>
                        <div><strong>قیمت پایه هر سهم:</strong> {{ number_format($sharePriceGol) }} گل · {{ number_format($sharePriceBahar, 2) }} بهار</div>
                    </div>
                @endif
            </div>
            <div class="header-actions">
                <a href="{{ route('stock.book') }}" class="btn-header"><i class="fas fa-book-open"></i>دفتر سهام</a>
                <a href="{{ route('holding.index') }}" class="btn-header"><i class="fas fa-briefcase"></i>کیف سهام</a>
            </div>
        </div>
    </section>

    <section class="auctions-card">
        @if($auctions->count())
            <div style="overflow-x:auto;">
                <table class="auctions-table">
                    <thead><tr><th>تعداد سهام</th><th>قیمت پایه</th><th>بازار و تسویه</th><th>نوع حراج</th><th>زمان شروع</th><th>زمان پایان</th><th>وضعیت</th><th>پیشنهادها</th><th>عملیات</th></tr></thead>
                    <tbody>
                    @foreach($auctions as $auction)
                        @php
                            $auctionGol = (int) ($auction->base_price_gol ?? 0);
                            $auctionBahar = $auctionGol / 100;
                            $effectiveStatus = $auction->isExpired() && $auction->status === 'running' ? 'expired' : $auction->status;
                        @endphp
                        <tr>
                            <td data-label="تعداد سهام">{{ number_format($auction->shares_count) }}</td>
                            <td data-label="قیمت پایه"><div class="price-stack"><strong>{{ number_format($auctionGol) }} گل</strong><small>{{ number_format($auctionBahar, 2) }} بهار</small></div></td>
                            <td data-label="بازار و تسویه">
                                <span class="meta-badge">{{ ($auction->market_type ?? '') === 'primary' ? 'بازار اولیه' : 'بازار ثانویه' }}</span>
                                <span class="meta-badge">{{ ($auction->supply_source ?? '') === 'treasury' ? 'خزانه EarthCoop' : 'منبع سهامدار' }}</span>
                                <span class="meta-badge">@switch($auction->settlement_channel) @case('external_irr') تسویه خارجی با ریال @break @case('external_usd') تسویه خارجی با دلار @break @default تسویه با بهار فعال @endswitch</span>
                            </td>
                            <td data-label="نوع حراج">@switch($auction->type) @case('single_winner') تک برنده @break @case('uniform_price') قیمت یکسان @break @case('pay_as_bid') پرداخت به قیمت پیشنهادی @break @default {{ $auction->type ?? '-' }} @endswitch</td>
                            <td data-label="زمان شروع">{{ $auction->start_time ? verta($auction->start_time)->format('Y/m/d H:i') : '-' }}</td>
                            <td data-label="زمان پایان">{{ $auction->ends_at ? verta($auction->ends_at)->format('Y/m/d H:i') : '-' }}</td>
                            <td data-label="وضعیت">
                                @if($effectiveStatus === 'expired')
                                    <span class="status-badge canceled">پایان‌یافته</span>
                                @else
                                    <span class="status-badge {{ $auction->status }}">@switch($auction->status) @case('scheduled') برنامه‌ریزی‌شده @break @case('running') فعال @break @case('settling') در حال تسویه @break @case('settled') تسویه‌شده @break @case('canceled') @case('cancelled') لغوشده @break @default {{ $auction->status ?? '-' }} @endswitch</span>
                                @endif
                            </td>
                            <td data-label="پیشنهادها">{{ $auction->bids()->count() }}</td>
                            <td data-label="عملیات"><a href="{{ route('auction.show', $auction) }}" class="btn-action"><i class="fas fa-eye"></i>{{ $auction->isActive() ? 'شرکت در حراج' : 'مشاهده' }}</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state"><i class="fas fa-gavel"></i><p>حراجی برای نمایش وجود ندارد.</p></div>
        @endif
    </section>
</div>
@endsection
