@extends('layouts.unified')
@section('title', 'جزئیات عرضه سهام - ' . config('app.name', 'EarthCoop'))
@push('styles')
<style>
.auction-show-container{max-width:1200px;margin:0 auto;padding:2rem 1rem;direction:rtl}.auction-card,.bid-form-card{background:#fff;border-radius:18px;box-shadow:0 4px 20px rgba(0,0,0,.08);padding:2rem;margin-bottom:1.5rem}.auction-title{display:flex;align-items:center;gap:.75rem;border-bottom:1px solid #e5e7eb;padding-bottom:1rem;margin-bottom:1.5rem}.auction-title h1{font-size:1.45rem;font-weight:800;margin:0}.canonical-badges{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.5rem}.canonical-badge{padding:.45rem .8rem;border-radius:999px;background:#ecfdf5;color:#047857;font-size:.82rem;font-weight:700}.info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem}.info-item{padding:1rem;border:1px solid #e5e7eb;border-radius:12px}.info-label{display:block;color:#64748b;font-size:.8rem;margin-bottom:.4rem}.info-value{font-size:1.15rem;font-weight:800;color:#0f172a}.subvalue{display:block;color:#64748b;font-size:.78rem;margin-top:.25rem}.notice{margin-top:1.25rem;padding:1rem 1.25rem;border-radius:12px;background:#eff6ff;color:#1e3a8a;line-height:1.9}.notice.warning{background:#fff7ed;color:#9a3412}.status{display:inline-flex;padding:.4rem .75rem;border-radius:999px;background:#f1f5f9;font-weight:700}.status.running{background:#dcfce7;color:#166534}.status.expired{background:#ffedd5;color:#9a3412}.empty-state{padding:1.5rem;text-align:center;color:#64748b;border:1px dashed #cbd5e1;border-radius:12px}.form-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem}.form-group{margin-bottom:1rem}.form-group label{display:block;font-weight:700;margin-bottom:.45rem}.form-control{width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:.75rem}.btn-primary{border:0;border-radius:999px;padding:.75rem 1.5rem;background:#059669;color:white;font-weight:700}.btn-primary:disabled{opacity:.55;cursor:not-allowed}@media(max-width:768px){.auction-card,.bid-form-card{padding:1rem}}
</style>
@endpush
@section('content')
@php
$baseGol=(int)($auction->base_price_gol??0);$baseBahar=$baseGol/100;
$isPrimaryTreasury=strtolower((string)$auction->market_type)==='primary'&&strtolower((string)$auction->supply_source)==='treasury';
$isExternal=$isPrimaryTreasury&&in_array(strtolower((string)$auction->settlement_channel),['external_irr','external_usd'],true);
$externalCheckoutReady=(bool)($externalCheckoutReady??false);
$isExpiredRunning=$auction->status==='running'&&$auction->isExpired();
$statusLabels=['scheduled'=>'برنامه‌ریزی‌شده','running'=>'در حال اجرا','settling'=>'در حال تسویه','settled'=>'تسویه‌شده','completed'=>'تکمیل‌شده','canceled'=>'لغوشده','cancelled'=>'لغوشده'];
$displayStatus=$isExpiredRunning?'پایان‌یافته؛ در انتظار بستن و تسویه':($statusLabels[$auction->status]??'نامشخص');
@endphp
<div class="auction-show-container">
<div class="auction-card">
<div class="auction-title"><i class="fas fa-gavel"></i><h1>{{ $isPrimaryTreasury ? 'عرضه اولیه خزانه EarthCoop' : 'جزئیات عرضه سهام' }}</h1></div>
<div class="canonical-badges">@if($isPrimaryTreasury)<span class="canonical-badge">بازار اولیه</span><span class="canonical-badge">خزانه EarthCoop</span>@endif<span class="canonical-badge">واحد قیمت‌گذاری: گل</span><span class="canonical-badge">{{ $isExternal ? 'تسویه خارجی' : 'تسویه با بهار فعال' }}</span></div>
<div class="info-grid">
<div class="info-item"><span class="info-label">تعداد سهام</span><span class="info-value">{{ number_format($auction->shares_count) }} سهم</span></div>
<div class="info-item"><span class="info-label">قیمت پایه</span><span class="info-value">{{ number_format($baseGol) }} گل</span><span class="subvalue">{{ number_format($baseBahar,2) }} بهار</span></div>
<div class="info-item"><span class="info-label">نوع حراج</span><span class="info-value">@switch($auction->type)@case('single_winner') تک‌برنده @break @case('uniform_price') قیمت یکسان @break @case('pay_as_bid') پرداخت به قیمت پیشنهادی @break @default نامشخص @endswitch</span></div>
<div class="info-item"><span class="info-label">شروع</span><span class="info-value">{{ $auction->start_time?verta($auction->start_time)->format('Y/m/d H:i'):'-' }}</span></div>
<div class="info-item"><span class="info-label">پایان</span><span class="info-value">{{ $auction->ends_at?verta($auction->ends_at)->format('Y/m/d H:i'):'-' }}</span></div>
<div class="info-item"><span class="info-label">وضعیت</span><span class="status {{ $isExpiredRunning?'expired':($auction->status==='running'?'running':'') }}">{{ $displayStatus }}</span></div>
</div>
@if($isPrimaryTreasury&&$isExternal)<div class="notice">تسویه خارجی فقط برای عرضه اولیه خزانه EarthCoop مجاز است. مبلغ وجه خارجی در زمان پرداخت، از قیمت مرجع سهم بر حسب گل و با نرخ معتبر و زمان‌دار محاسبه می‌شود. این مبلغ وارد موجودی نجم بهار نمی‌شود و بهار جدیدی نیز ایجاد نمی‌کند.</div>@endif
@if($auction->info)<div class="notice"><strong>توضیحات:</strong> {{ $auction->info }}</div>@endif
@if($isExpiredRunning)<div class="notice warning"><strong>مهلت ثبت پیشنهاد پایان یافته است.</strong> این حراج دیگر پیشنهاد جدید نمی‌پذیرد و در انتظار بستن و ورود به فرایند تسویه است.</div>@endif
</div>
@if($auction->isActive())
<div class="bid-form-card"><h2 style="font-size:1.2rem;font-weight:800;margin-bottom:1rem;">ثبت پیشنهاد خرید</h2>
@if($isExternal)
<div class="notice" style="margin-bottom:1rem;"><strong>آمادگی تسویه خارجی:</strong> {{ $externalCheckoutReady ? 'آماده' : 'غیرفعال تا تکمیل الزامات عملیاتی' }}. مبلغ ریالی/دلاری از قیمت گل و نرخ مرجع معتبر در سمت سرور محاسبه می‌شود؛ مرورگر مبلغ وجه یا ارز تسویه را تعیین نمی‌کند.</div>
<form method="POST" action="{{ url('/auctions/'.$auction->id.'/external-checkout') }}">@csrf
<div class="form-row">
<div class="form-group"><label for="quantity">تعداد سهم</label><input class="form-control" id="quantity" name="quantity" type="number" min="1" max="{{ $auction->shares_count }}" required></div>
<div class="form-group"><label for="price_gol">قیمت پیشنهادی هر سهم (گل)</label><input class="form-control" id="price_gol" name="price_gol" type="number" min="{{ max(1,$baseGol) }}" step="1" value="{{ $baseGol }}" required><span class="subvalue">معادل نمایشی: {{ number_format($baseBahar,2) }} بهار برای هر سهم در قیمت پایه</span></div>
</div>
<button class="btn-primary" type="submit" {{ $externalCheckoutReady ? '' : 'disabled' }}>{{ $externalCheckoutReady ? 'ادامه به درگاه پرداخت' : 'تسویه خارجی هنوز آماده نیست' }}</button>
</form>
@else
<form method="POST" action="{{ route('auction.bid',$auction) }}">@csrf<div class="form-row"><div class="form-group"><label for="quantity">تعداد سهم</label><input class="form-control" id="quantity" name="quantity" type="number" min="1" max="{{ $auction->shares_count }}" required></div><div class="form-group"><label for="price">قیمت پیشنهادی (گل)</label><input class="form-control" id="price" name="price" type="number" min="1" step="1" value="{{ $baseGol }}" required></div></div><button class="btn-primary" type="submit">ثبت پیشنهاد</button></form>
@endif
</div>
@endif
<div class="auction-card"><h2 style="font-size:1.1rem;font-weight:800;margin-bottom:1rem;">پیشنهادهای شما</h2>@forelse($userBids??[] as $bid)<div class="info-item" style="margin-bottom:.75rem;">{{ number_format((int)($bid->quantity??0)) }} سهم — {{ number_format((int)($bid->price_gol??0)) }} گل</div>@empty<div class="empty-state">هنوز پیشنهادی ثبت نکرده‌اید.</div>@endforelse</div>
</div>
@endsection
