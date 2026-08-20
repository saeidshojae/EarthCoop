@extends('layouts.unified')

@section('title', 'حراج canonical سهام - ' . config('app.name', 'EarthCoop'))

@push('styles')
<style>
.canonical-auction{max-width:1280px;margin:0 auto;padding:2rem 1rem;direction:rtl}.cardx{background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,.08);padding:1.5rem;margin-bottom:1.5rem}.gridx{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:1rem}.labelx{font-size:.85rem;color:#6b7280;font-weight:600}.valuex{font-size:1.1rem;font-weight:700;color:#111827}.badge-x{display:inline-block;padding:.35rem .8rem;border-radius:999px;background:#ecfdf5;color:#065f46;font-weight:700}.notice{padding:1rem;border-radius:12px;background:#eff6ff;border-right:4px solid #2563eb;margin-top:1rem}.notice.warn{background:#fff7ed;border-color:#ea580c}.formx input{width:100%;padding:.75rem;border:1px solid #d1d5db;border-radius:10px}.btnx{padding:.7rem 1.2rem;border:0;border-radius:999px;background:#047857;color:#fff;font-weight:700;cursor:pointer}.btnx-danger{background:#b91c1c}.bidrow{display:flex;justify-content:space-between;gap:1rem;padding:.8rem 0;border-bottom:1px solid #e5e7eb}.bidrow:last-child{border-bottom:0}@media(max-width:700px){.bidrow{flex-direction:column}}
</style>
@endpush

@section('content')
<div class="canonical-auction">
    @if(session('success'))<div class="notice">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="notice warn">{{ session('error') }}</div>@endif

    <div class="cardx">
        <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap">
            <h3 style="margin:0">حراج سهام #{{ $auction->id }}</h3>
            <span class="badge-x">مسیر canonical / Gol</span>
        </div>
        <div class="gridx" style="margin-top:1.25rem">
            <div><div class="labelx">تعداد سهام عرضه</div><div class="valuex">{{ number_format($auction->shares_count) }}</div></div>
            <div><div class="labelx">قیمت پایه</div><div class="valuex">{{ number_format($auction->base_price_gol) }} Gol</div></div>
            <div><div class="labelx">کانال تسویه</div><div class="valuex">{{ $auction->settlement_channel }}</div></div>
            <div><div class="labelx">نوع بازار</div><div class="valuex">{{ $auction->market_type }}</div></div>
            <div><div class="labelx">منبع عرضه</div><div class="valuex">{{ $auction->supply_source }}</div></div>
            <div><div class="labelx">وضعیت</div><div class="valuex">{{ $auction->status }}</div></div>
            @if($auction->market_type === \App\Modules\Stock\Settlement\SettlementEligibilityPolicy::MARKET_SECONDARY)
                <div><div class="labelx">فروشنده</div><div class="valuex">کاربر #{{ $auction->seller_user_id }}</div></div>
            @endif
        </div>
        @if($auction->min_bid_gol || $auction->max_bid_gol)
            <div class="notice">
                محدوده canonical:
                @if($auction->min_bid_gol) حداقل {{ number_format($auction->min_bid_gol) }} Gol @endif
                @if($auction->max_bid_gol) — حداکثر {{ number_format($auction->max_bid_gol) }} Gol @endif
            </div>
        @endif

        @if(auth()->check() && (int)$auction->seller_user_id === (int)auth()->id() && in_array($auction->status,['running','scheduled'],true))
            <div class="notice warn">
                این عرضه متعلق به شماست. تا زمانی که Bid فعال وجود نداشته باشد می‌توانید آن را لغو کنید و reservation سهام آزاد می‌شود.
                @if(!$auction->activeBids()->exists())
                    <form method="POST" action="{{ route('stock.secondary-listing.cancel', $auction) }}" style="margin-top:.75rem">
                        @csrf @method('DELETE')
                        <button class="btnx btnx-danger" type="submit" onclick="return confirm('عرضه لغو و سهام رزروشده آزاد شود؟')">لغو عرضه</button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    @if($auction->isActive() && $auction->settlement_channel === \App\Modules\Stock\Settlement\SettlementChannel::ACTIVE_BAHAR)
        <div class="cardx">
            <h4>ثبت پیشنهاد با بهار فعال</h4>
            @auth
                @if((int)$auction->seller_user_id === (int)auth()->id())
                    <div class="notice warn">فروشنده نمی‌تواند روی عرضه ثانویه خودش پیشنهاد ثبت کند.</div>
                @elseif($najmAccount)
                    <div class="notice" style="margin-bottom:1rem">
                        موجودی Active: <strong>{{ number_format($najmAccount->balance_active) }} Gol</strong> — قابل خرج پس از رزروهای باز: <strong>{{ number_format($availableActive ?? 0) }} Gol</strong>
                    </div>
                    <form class="formx" method="POST" action="{{ route('auction.canonical-bid', $auction) }}">
                        @csrf
                        <input type="hidden" name="acceptance_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <div class="gridx">
                            <div>
                                <label class="labelx">قیمت پیشنهادی هر سهم (Gol)</label>
                                <input type="number" name="price_gol" min="{{ $auction->min_bid_gol ?? 1 }}" @if($auction->max_bid_gol) max="{{ $auction->max_bid_gol }}" @endif value="{{ old('price_gol', $auction->base_price_gol) }}" required>
                            </div>
                            <div>
                                <label class="labelx">تعداد سهام</label>
                                <input type="number" name="quantity" min="1" max="{{ $auction->lot_size }}" value="{{ old('quantity', 1) }}" required>
                            </div>
                        </div>
                        <button class="btnx" type="submit" style="margin-top:1rem">ثبت و رزرو بهار فعال</button>
                    </form>
                @else
                    <div class="notice warn">برای شرکت در این حراج، حساب اصلی فعال نجم بهار لازم است.</div>
                @endif
            @else
                <div class="notice">برای ثبت پیشنهاد ابتدا وارد حساب خود شوید.</div>
            @endauth
        </div>
    @elseif($auction->isActive())
        <div class="cardx"><div class="notice warn">این حراج از settlement خارجی استفاده می‌کند. مسیر پرداخت provider تا زمان پیکربندی واقعی و تأیید Readiness فعال نشده است.</div></div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="cardx">
            <h4>پیشنهادهای شما</h4>
            @forelse($userBids as $bid)
                <div class="bidrow">
                    <div>
                        <strong>{{ number_format($bid->price_gol ?? 0) }} Gol</strong>
                        <div>تعداد: {{ number_format($bid->quantity) }} — وضعیت: {{ $bid->status }}</div>
                    </div>
                    @if($bid->status === 'active' && $bid->reservation_key)
                        <form method="POST" action="{{ route('bid.canonical.destroy', $bid) }}">
                            @csrf @method('DELETE')
                            <button class="btnx btnx-danger" type="submit" onclick="return confirm('پیشنهاد لغو و رزرو Active Bahar آزاد شود؟')">لغو پیشنهاد</button>
                        </form>
                    @endif
                </div>
            @empty
                <p>هنوز پیشنهادی ثبت نکرده‌اید.</p>
            @endforelse
        </div>

        <div class="cardx">
            <h4>Order Book canonical</h4>
            @forelse($orderBook as $bid)
                <div class="bidrow">
                    <div><strong>{{ number_format($bid->price_gol) }} Gol</strong><div>{{ number_format($bid->quantity) }} سهم</div></div>
                    <div>کاربر #{{ $bid->user_id }}</div>
                </div>
            @empty
                <p>پیشنهاد فعالی وجود ندارد.</p>
            @endforelse
        </div>
    </div>

    @if(auth()->check() && (auth()->user()->is_admin ?? false) && $auction->hasCanonicalGolPricing() && in_array($auction->status,['running','settling'],true))
        <div class="cardx">
            <h4>تسویه canonical ادمین</h4>
            <div class="notice warn">این عملیات فقط از close engine canonical متناظر با نوع بازار عبور می‌کند و در شرایط ناقص fail-closed است.</div>
            <form method="POST" action="{{ route('admin.stock.canonical-auction.close', $auction) }}" style="margin-top:1rem">
                @csrf
                <button class="btnx" type="submit" onclick="return confirm('تسویه canonical این حراج اجرا شود؟')">اجرای تسویه canonical</button>
            </form>
        </div>
    @endif
</div>
@endsection
