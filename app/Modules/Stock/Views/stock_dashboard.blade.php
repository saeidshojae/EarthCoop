@extends('layouts.unified')

@section('title', 'دفتر سهام EarthCoop - ' . config('app.name', 'EarthCoop'))

@php
    $fa = static function ($value, int $decimals = 0): string {
        $formatted = number_format((float) $value, $decimals, '.', ',');
        return strtr($formatted, [
            '0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹',
            '.'=>'٫', ','=>'٬',
        ]);
    };

    $valuationGol = (int) ($stock->startup_valuation_gol ?? 0);
    $valuationBahar = $valuationGol / 100;
    $sharePriceGol = (int) ($stock->base_share_price_gol ?? 0);
    $sharePriceBahar = $sharePriceGol / 100;
    $totalShares = (int) ($stock->total_shares ?? 0);
    $availableShares = (int) ($stock->available_shares ?? 0);
    $maxPrimaryShares = $totalShares > 0 ? intdiv($totalShares * 1000, 10000) : 0;
    $visibleAuctions = collect($auctions ?? [])->filter(fn($auction) => !$auction->isExpired())->values();
    $primaryOpenShares = $visibleAuctions->filter(fn($auction) => ($auction->market_type ?? null) === 'primary' && ($auction->supply_source ?? null) === 'treasury')->sum('shares_count');
    $primaryRemainingEnvelope = max(0, $maxPrimaryShares - $primaryOpenShares);
@endphp

@push('styles')
<style>
    .stockbook{max-width:1320px;margin:0 auto;padding:2rem 1rem;direction:rtl}
    .stockbook-hero{border-radius:1.75rem;padding:2rem;background:linear-gradient(135deg,#064e3b,#0f766e);color:#fff;box-shadow:0 24px 70px rgba(6,78,59,.22);margin-bottom:1.5rem}
    .stockbook-hero h1{font-size:2rem;font-weight:900;margin:0 0 .65rem}.stockbook-hero p{margin:0;line-height:1.9;opacity:.92}
    .stockbook-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem;margin-bottom:1.5rem}
    .stockbook-card{background:#fff;border:1px solid #e2e8f0;border-radius:1.25rem;padding:1.25rem;box-shadow:0 10px 30px rgba(15,23,42,.06)}
    .stockbook-label{font-size:.78rem;font-weight:700;color:#64748b;margin-bottom:.5rem}.stockbook-value{font-size:1.45rem;font-weight:900;color:#0f172a}.stockbook-sub{font-size:.76rem;color:#64748b;margin-top:.35rem;line-height:1.7}
    .stockbook-section{background:#fff;border:1px solid #e2e8f0;border-radius:1.35rem;padding:1.4rem;margin-bottom:1.25rem;box-shadow:0 12px 34px rgba(15,23,42,.05)}
    .stockbook-section h2{font-size:1.2rem;font-weight:900;color:#0f172a;margin:0 0 .85rem}.stockbook-section p{color:#475569;line-height:1.9}
    .policy-strip{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem;margin-top:1rem}.policy-pill{border-radius:1rem;padding:1rem;background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;font-weight:700;line-height:1.8}
    .auction-list{display:grid;gap:1rem}.auction-item{border:1px solid #e2e8f0;border-radius:1rem;padding:1rem}.auction-top{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start}.auction-title{font-weight:900;color:#0f172a}.auction-meta{display:flex;gap:.7rem;flex-wrap:wrap;margin-top:.7rem}.auction-meta span{font-size:.78rem;background:#f1f5f9;border-radius:999px;padding:.4rem .7rem;color:#475569}.auction-actions{margin-top:1rem}.auction-link{display:inline-flex;text-decoration:none;border-radius:999px;background:#047857;color:#fff;padding:.65rem 1rem;font-weight:700}
    .holdings{display:grid;gap:.75rem}.holding{border:1px solid #e2e8f0;border-radius:1rem;padding:1rem}.muted-box{border:1px dashed #cbd5e1;border-radius:1rem;padding:1rem;background:#f8fafc;color:#64748b;line-height:1.9}
    .status-off{display:inline-flex;border-radius:999px;background:#f1f5f9;color:#475569;padding:.45rem .8rem;font-size:.8rem;font-weight:800}
    .dark .stockbook-card,.dark .stockbook-section{background:#0f172a;border-color:#334155}.dark .stockbook-value,.dark .stockbook-section h2,.dark .auction-title{color:#f8fafc}.dark .stockbook-label,.dark .stockbook-sub,.dark .stockbook-section p{color:#94a3b8}.dark .auction-item,.dark .holding{border-color:#334155}.dark .auction-meta span,.dark .muted-box,.dark .status-off{background:#1e293b;color:#cbd5e1;border-color:#475569}
    @media(max-width:1024px){.stockbook-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.policy-strip{grid-template-columns:1fr}}
    @media(max-width:640px){.stockbook{padding:1rem}.stockbook-grid{grid-template-columns:1fr}.stockbook-hero{padding:1.35rem}.stockbook-hero h1{font-size:1.55rem}.auction-top{flex-direction:column}}
</style>
@endpush

@section('content')
<div class="stockbook">
    <section class="stockbook-hero">
        <h1>دفتر سهام EarthCoop</h1>
        <p>این صفحه دفتر دارایی سهام است، نه یک سامانه پولی دوم. ارزش‌گذاری و قیمت پایه سهام در واحد قراردادی EarthCoop یعنی «گل» ثبت می‌شود و معادل بهار برای خوانایی نمایش داده می‌شود.</p>
    </section>

    @if($stock)
        <div class="stockbook-grid">
            <article class="stockbook-card">
                <div class="stockbook-label">ارزش‌گذاری کل (بهار)</div>
                <div class="stockbook-value">{{ $fa($valuationBahar) }} بهار</div>
                <div class="stockbook-sub">{{ $fa($valuationGol) }} گل</div>
            </article>
            <article class="stockbook-card">
                <div class="stockbook-label">تعداد کل سهام</div>
                <div class="stockbook-value">{{ $fa($totalShares) }} سهم</div>
                <div class="stockbook-sub">مبنای محاسبه سهم از ارزش‌گذاری کل</div>
            </article>
            <article class="stockbook-card">
                <div class="stockbook-label">قیمت پایه هر سهم (گل)</div>
                <div class="stockbook-value">{{ $fa($sharePriceGol) }} گل</div>
                <div class="stockbook-sub">{{ $fa($sharePriceBahar, 2) }} بهار</div>
            </article>
            <article class="stockbook-card">
                <div class="stockbook-label">سهام خزانه قابل عرضه</div>
                <div class="stockbook-value">{{ $fa($availableShares) }} سهم</div>
                <div class="stockbook-sub">موجودی دارایی؛ نه موجودی پول</div>
            </article>
        </div>

        <section class="stockbook-section">
            <h2>عرضه اولیه خزانه</h2>
            <p>حداکثر ۱۰٪ از کل سهام EarthCoop برای عرضه اولیه برنامه‌ریزی شده است. تسویه خارجی فقط در عرضه اولیه خزانه مجاز است و ورود وجه خارجی به سرمایه EarthCoop باعث ایجاد Bahar جدید یا افزایش موجودی Najm Bahar نمی‌شود.</p>
            <div class="policy-strip">
                <div class="policy-pill">سقف عرضه اولیه<br>{{ $fa($maxPrimaryShares) }} سهم</div>
                <div class="policy-pill">تعهد باز حراج‌های فعلی<br>{{ $fa($primaryOpenShares) }} سهم</div>
                <div class="policy-pill">ظرفیت باقی‌مانده این envelope<br>{{ $fa($primaryRemainingEnvelope) }} سهم</div>
            </div>
        </section>

        <section class="stockbook-section">
            <h2>حراج‌های فعال و برنامه‌ریزی‌شده</h2>
            @if($visibleAuctions->count())
                <div class="auction-list">
                    @foreach($visibleAuctions as $auction)
                        <article class="auction-item">
                            <div class="auction-top">
                                <div>
                                    <div class="auction-title">{{ $fa($auction->shares_count ?? 0) }} سهم</div>
                                    <div class="auction-meta">
                                        <span>{{ ($auction->market_type ?? '') === 'primary' ? 'بازار اولیه' : 'بازار دیگر' }}</span>
                                        <span>{{ ($auction->supply_source ?? '') === 'treasury' ? 'خزانه EarthCoop' : 'منبع دیگر' }}</span>
                                        <span>قیمت پایه: {{ $fa($auction->base_price_gol ?? 0) }} گل</span>
                                        <span>معادل: {{ $fa(((int)($auction->base_price_gol ?? 0))/100, 2) }} بهار</span>
                                        <span>{{ ($auction->settlement_channel ?? '') === 'external_irr' ? 'تسویه خارجی با ریال' : 'تسویه با بهار فعال' }}</span>
                                    </div>
                                </div>
                                <span class="status-off">{{ $auction->status === 'running' ? 'در حال اجرا' : 'برنامه‌ریزی‌شده' }}</span>
                            </div>
                            <div class="auction-actions">
                                <a href="{{ route('auction.show', $auction) }}" class="auction-link">مشاهده جزئیات حراج</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="muted-box">در حال حاضر حراج فعال یا برنامه‌ریزی‌شده‌ای برای نمایش وجود ندارد.</div>
            @endif
        </section>

        <section class="stockbook-section">
            <h2>دارایی سهام شما</h2>
            @if(auth()->check() && $userHoldings && $userHoldings->count())
                <div class="holdings">
                    @foreach($userHoldings as $holding)
                        <div class="holding">
                            <strong>{{ $fa($holding->quantity ?? 0) }} سهم</strong>
                            <div class="stockbook-sub">این مقدار یک دارایی مالکیتی است و به‌عنوان موجودی پول قابل خرج تعریف نمی‌شود.</div>
                        </div>
                    @endforeach
                </div>
            @elseif(auth()->check())
                <div class="muted-box">هنوز سهمی در دفتر دارایی شما ثبت نشده است.</div>
            @else
                <div class="muted-box">برای مشاهده دارایی شخصی سهام، وارد حساب خود شوید.</div>
            @endif
        </section>

        <section class="stockbook-section">
            <h2>بازار ثانویه</h2>
            <span class="status-off">غیرفعال</span>
            <p class="mt-3">بازار ثانویه هنوز فعال نشده است. پس از فعال‌سازی رسمی، معاملات ثانویه مطابق معماری اقتصادی EarthCoop با Active Bahar انجام می‌شوند و مسیر تسویه خارجی عرضه اولیه به آن تعمیم داده نمی‌شود.</p>
        </section>

        <section class="stockbook-section">
            <h2>وضعیت تسویه خارجی</h2>
            <span class="status-off">{{ config('stock.external_capital.enabled', false) ? 'فعال در سطح feature flag؛ مشروط به readiness' : 'غیرفعال تا تکمیل readiness' }}</span>
            <p class="mt-3">قیمت فیات بخشی از قیمت پایه سهام نیست. در صورت فعال‌شدن کامل مسیر عرضه اولیه، quote فیات در زمان پرداخت از منبع نرخ معتبر و دارای زمان ثبت دریافت می‌شود.</p>
        </section>
    @else
        <section class="stockbook-section">
            <div class="muted-box">اطلاعات پایه سهام هنوز تعریف نشده است.</div>
        </section>
    @endif
</div>
@endsection
