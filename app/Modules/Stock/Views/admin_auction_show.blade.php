@extends('layouts.admin')

@section('title', 'جزئیات عرضه #' . $auction->id . ' - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'جزئیات عرضه #' . $auction->id)
@section('page-description', 'هویت دارایی، قیمت‌گذاری بر حسب گل و وضعیت تسویه عرضه سهام')

@php
    $fa = static function ($value, int $decimals = 0): string {
        return strtr(number_format((float)$value, $decimals, '.', ','), [
            '0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹','.'=>'٫',','=>'٬'
        ]);
    };
    $priceGol = (int) ($auction->base_price_gol ?? 0);
    $priceBahar = $priceGol / 100;
    $isPrimaryTreasury = ($auction->market_type ?? '') === 'primary' && ($auction->supply_source ?? '') === 'treasury';
    $statusLabels = ['scheduled'=>'برنامه‌ریزی‌شده','running'=>'در حال اجرا','settling'=>'در حال تسویه','settled'=>'تسویه‌شده','completed'=>'تکمیل‌شده','canceled'=>'لغوشده','cancelled'=>'لغوشده'];
    $typeLabels = ['single_winner'=>'تک‌برنده','uniform_price'=>'قیمت یکسان','pay_as_bid'=>'پرداخت به قیمت پیشنهادی'];
    $settlementModeLabels = ['manual'=>'تسویه دستی','automatic'=>'تسویه خودکار','auto'=>'تسویه خودکار'];
    $marketLabels = ['primary'=>'بازار اولیه','secondary'=>'بازار ثانویه'];
    $sourceLabels = ['treasury'=>'خزانه EarthCoop'];
    $settlementLabels = ['external_capital'=>'تسویه خارجی','active_bahar'=>'تسویه با بهار فعال'];
@endphp

@push('styles')
<style>
    .auction-detail-wrap{direction:rtl;display:grid;gap:1rem}.detail-hero,.detail-card{background:#fff;border:1px solid #e2e8f0;border-radius:1.2rem;padding:1.25rem;box-shadow:0 10px 30px rgba(15,23,42,.06)}.detail-hero{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap}.detail-title{font-size:1.35rem;font-weight:900;color:#0f172a}.detail-tags{display:flex;gap:.45rem;flex-wrap:wrap;margin-top:.7rem}.detail-tag{font-size:.78rem;padding:.4rem .7rem;border-radius:999px;background:#f1f5f9;color:#475569}.detail-price{text-align:left}.detail-price strong{display:block;font-size:1.65rem;color:#047857}.detail-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.85rem}.detail-metric{background:#f8fafc;border:1px solid #e2e8f0;border-radius:1rem;padding:1rem}.detail-metric span{font-size:.76rem;color:#64748b}.detail-metric strong{display:block;margin-top:.35rem;color:#0f172a}.detail-card h2{font-size:1.1rem;font-weight:900;color:#0f172a;margin-bottom:.8rem}.detail-note{border-radius:1rem;padding:1rem;background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;line-height:1.9}.detail-actions{display:flex;gap:.55rem;flex-wrap:wrap}.detail-actions a,.detail-actions button{border:0;border-radius:999px;padding:.65rem .95rem;text-decoration:none;font-weight:800;cursor:pointer}.primary{background:#047857;color:#fff}.secondary{background:#f1f5f9;color:#334155}.dark .detail-hero,.dark .detail-card{background:#0f172a;border-color:#334155}.dark .detail-title,.dark .detail-card h2,.dark .detail-metric strong{color:#f8fafc}.dark .detail-tag,.dark .secondary,.dark .detail-metric{background:#1e293b;color:#cbd5e1;border-color:#334155}@media(max-width:900px){.detail-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.detail-grid{grid-template-columns:1fr}.detail-price{text-align:right}}
</style>
@endpush

@section('content')
<div class="auction-detail-wrap">
    <section class="detail-hero">
        <div>
            <div class="detail-title">{{ $isPrimaryTreasury ? 'عرضه اولیه خزانه EarthCoop' : 'حراج سهام' }}</div>
            <div class="detail-tags">
                <span class="detail-tag">{{ $marketLabels[$auction->market_type] ?? 'بازار تعیین‌نشده' }}</span>
                <span class="detail-tag">{{ $sourceLabels[$auction->supply_source] ?? 'منبع تعیین‌نشده' }}</span>
                <span class="detail-tag">{{ $settlementLabels[$auction->settlement_channel] ?? 'روش تسویه تعیین‌نشده' }}</span>
                <span class="detail-tag">واحد قیمت‌گذاری: گل</span>
            </div>
        </div>
        <div class="detail-price">
            <span class="text-xs text-slate-500">قیمت پایه هر سهم</span>
            <strong>{{ $fa($priceGol) }} گل</strong>
            <span class="text-sm text-slate-500">{{ $fa($priceBahar, 2) }} بهار</span>
        </div>
    </section>

    <section class="detail-card">
        <div class="detail-grid">
            <div class="detail-metric"><span>تعداد سهام عرضه</span><strong>{{ $fa($auction->shares_count ?? 0) }} سهم</strong></div>
            <div class="detail-metric"><span>وضعیت</span><strong>{{ $statusLabels[$auction->status] ?? 'وضعیت نامشخص' }}</strong></div>
            <div class="detail-metric"><span>روش حراج</span><strong>{{ $typeLabels[$auction->type] ?? 'نامشخص' }}</strong></div>
            <div class="detail-metric"><span>روش اجرای تسویه</span><strong>{{ $settlementModeLabels[$auction->settlement_mode] ?? 'نامشخص' }}</strong></div>
            <div class="detail-metric"><span>شروع</span><strong>{{ $auction->start_time ? verta($auction->start_time)->format('Y/m/d H:i') : '—' }}</strong></div>
            <div class="detail-metric"><span>پایان</span><strong>{{ $auction->end_time ? verta($auction->end_time)->format('Y/m/d H:i') : '—' }}</strong></div>
            <div class="detail-metric"><span>بسته‌شدن</span><strong>{{ $auction->ends_at ? verta($auction->ends_at)->format('Y/m/d H:i') : '—' }}</strong></div>
            <div class="detail-metric"><span>اندازه هر بخش سفارش</span><strong>{{ $fa($auction->lot_size ?? 0) }}</strong></div>
        </div>
    </section>

    <section class="detail-card">
        <h2>قرارداد تسویه</h2>
        <div class="detail-note">تسویه خارجی فقط برای عرضه اولیه خزانه EarthCoop مجاز است. قیمت ریالی از قیمت پایه دارایی جداست و تنها در مرحله پرداخت، با نرخ معتبر و زمان‌دار محاسبه می‌شود. این مسیر بهار جدید ایجاد نمی‌کند و وجه ریالی وارد موجودی نجم بهار نمی‌شود.</div>
    </section>

    <section class="detail-card">
        <h2>دفتر سفارش</h2>
        @if($orderBook && $orderBook->count())
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr><th class="text-right p-2">کاربر</th><th class="text-right p-2">قیمت</th><th class="text-right p-2">تعداد</th><th class="text-right p-2">وضعیت</th></tr></thead>
                    <tbody>
                    @foreach($orderBook as $bid)
                        <tr class="border-t border-slate-200 dark:border-slate-700">
                            <td class="p-2">{{ $bid->user_id }}</td>
                            <td class="p-2">{{ $fa($bid->price_gol ?? $bid->bid_price_gol ?? 0) }} گل</td>
                            <td class="p-2">{{ $fa($bid->quantity ?? 0) }}</td>
                            <td class="p-2">{{ ['active'=>'فعال','won'=>'برنده','lost'=>'ناموفق','cancelled'=>'لغوشده','canceled'=>'لغوشده'][$bid->status] ?? 'نامشخص' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-sm text-slate-500">هنوز سفارشی ثبت نشده است.</div>
        @endif
    </section>

    <div class="detail-actions">
        <a href="{{ route('admin.auction.index') }}" class="secondary">بازگشت</a>
        @if($auction->status === 'scheduled')
            <a href="{{ route('admin.auction.edit', $auction) }}" class="secondary">ویرایش</a>
            <form method="POST" action="{{ route('admin.auction.start', $auction) }}" style="display:inline">@csrf<button type="submit" class="primary">شروع حراج</button></form>
        @endif
    </div>
</div>
@endsection
