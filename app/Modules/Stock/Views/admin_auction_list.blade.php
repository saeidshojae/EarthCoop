@extends('layouts.admin')

@section('title', 'مدیریت عرضه‌های سهام - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'مدیریت عرضه‌های سهام')
@section('page-description', 'نمایش حراج‌ها با هویت بازار، منبع عرضه و قیمت‌گذاری مرجع بر حسب گل')

@php
    $fa = static function ($value, int $decimals = 0): string {
        return strtr(number_format((float)$value, $decimals, '.', ','), [
            '0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹','.'=>'٫',','=>'٬'
        ]);
    };
    $statusLabels = ['scheduled'=>'برنامه‌ریزی‌شده','running'=>'در حال اجرا','settling'=>'در حال تسویه','settled'=>'تسویه‌شده','completed'=>'تکمیل‌شده','canceled'=>'لغوشده','cancelled'=>'لغوشده'];
    $typeLabels = ['single_winner'=>'تک‌برنده','uniform_price'=>'قیمت یکسان','pay_as_bid'=>'پرداخت به قیمت پیشنهادی'];
    $marketLabels = ['primary'=>'بازار اولیه','secondary'=>'بازار ثانویه'];
    $sourceLabels = ['treasury'=>'خزانه EarthCoop'];
    $settlementLabels = ['external_irr'=>'تسویه خارجی ریالی','external_usd'=>'تسویه خارجی دلاری','active_bahar'=>'تسویه با بهار فعال'];
@endphp

@push('styles')
<style>
    .auction-admin-wrap{direction:rtl;display:grid;gap:1rem}.auction-admin-head{display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap}.auction-actions{display:flex;gap:.5rem;flex-wrap:wrap}.auction-create{border-radius:999px;background:#047857;color:#fff;padding:.75rem 1.1rem;text-decoration:none;font-weight:800}.auction-ops{border-radius:999px;background:#f1f5f9;color:#334155;padding:.75rem 1.1rem;text-decoration:none;font-weight:800}.auction-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.auction-summary>div,.auction-row{background:#fff;border:1px solid #e2e8f0;border-radius:1rem;padding:1rem}.summary-label{font-size:.75rem;color:#64748b}.summary-value{font-size:1.3rem;font-weight:900;color:#0f172a;margin-top:.35rem}.auction-table{display:grid;gap:.75rem}.auction-row-top{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start}.auction-id{font-weight:900;color:#0f172a}.auction-tags{display:flex;gap:.45rem;flex-wrap:wrap;margin-top:.65rem}.auction-tag{font-size:.75rem;padding:.35rem .65rem;border-radius:999px;background:#f1f5f9;color:#475569}.auction-price{font-size:1.15rem;font-weight:900;color:#047857}.auction-meta{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem;margin-top:1rem}.auction-meta div{font-size:.8rem;color:#64748b}.auction-meta strong{display:block;color:#0f172a;margin-top:.25rem}.auction-links{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem}.auction-links a{border-radius:999px;padding:.55rem .85rem;text-decoration:none;font-weight:700;background:#f1f5f9;color:#334155}.dark .auction-summary>div,.dark .auction-row{background:#0f172a;border-color:#334155}.dark .summary-value,.dark .auction-id,.dark .auction-meta strong{color:#f8fafc}.dark .auction-tag,.dark .auction-links a,.dark .auction-ops{background:#1e293b;color:#cbd5e1}@media(max-width:900px){.auction-summary,.auction-meta{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.auction-summary,.auction-meta{grid-template-columns:1fr}.auction-row-top{flex-direction:column}}
</style>
@endpush

@section('content')
<div class="auction-admin-wrap">
    <div class="auction-admin-head">
        <div>
            <h2 class="text-xl font-extrabold text-slate-900 dark:text-white">عرضه‌ها و حراج‌های سهام</h2>
            <p class="text-sm text-slate-500 mt-1">قیمت پایه بر حسب گل، منبع اصلی قیمت‌گذاری است؛ معادل بهار فقط برای خوانایی نمایش داده می‌شود.</p>
        </div>
        <div class="auction-actions">
            <a href="{{ route('admin.stock.external-payments.index') }}" class="auction-ops">تسویه‌های خارجی</a>
            <a href="{{ route('admin.auction.create') }}" class="auction-create">ایجاد عرضه اولیه</a>
        </div>
    </div>

    <div class="auction-summary">
        <div><div class="summary-label">کل حراج‌ها</div><div class="summary-value">{{ $fa($stats['total_auctions'] ?? 0) }}</div></div>
        <div><div class="summary-label">در حال اجرا</div><div class="summary-value">{{ $fa($stats['running_auctions'] ?? 0) }}</div></div>
        <div><div class="summary-label">برنامه‌ریزی‌شده</div><div class="summary-value">{{ $fa($stats['scheduled_auctions'] ?? 0) }}</div></div>
        <div><div class="summary-label">تسویه‌شده</div><div class="summary-value">{{ $fa($stats['settled_auctions'] ?? 0) }}</div></div>
    </div>

    <div class="auction-table">
        @forelse($auctions as $auction)
            <article class="auction-row">
                <div class="auction-row-top">
                    <div>
                        <div class="auction-id">حراج #{{ $fa($auction->id) }}</div>
                        <div class="auction-tags">
                            <span class="auction-tag">{{ $marketLabels[$auction->market_type] ?? 'بازار تعیین‌نشده' }}</span>
                            <span class="auction-tag">{{ $sourceLabels[$auction->supply_source] ?? 'منبع تعیین‌نشده' }}</span>
                            <span class="auction-tag">{{ $settlementLabels[$auction->settlement_channel] ?? 'روش تسویه تعیین‌نشده' }}</span>
                            <span class="auction-tag">{{ $statusLabels[$auction->status] ?? 'وضعیت نامشخص' }}</span>
                        </div>
                    </div>
                    <div><div class="summary-label">قیمت پایه (گل)</div><div class="auction-price">{{ $fa($auction->base_price_gol ?? 0) }} گل</div><div class="summary-label">{{ $fa(((int)($auction->base_price_gol ?? 0))/100, 2) }} بهار</div></div>
                </div>
                <div class="auction-meta">
                    <div>تعداد سهام<strong>{{ $fa($auction->shares_count ?? 0) }}</strong></div><div>نوع حراج<strong>{{ $typeLabels[$auction->type] ?? 'نامشخص' }}</strong></div><div>زمان شروع<strong>{{ $auction->start_time ? verta($auction->start_time)->format('Y/m/d H:i') : '—' }}</strong></div><div>زمان پایان<strong>{{ $auction->ends_at ? verta($auction->ends_at)->format('Y/m/d H:i') : '—' }}</strong></div>
                </div>
                <div class="auction-links"><a href="{{ route('admin.auction.show', $auction) }}">جزئیات</a>@if(in_array($auction->status, ['scheduled']))<a href="{{ route('admin.auction.edit', $auction) }}">ویرایش</a>@endif</div>
            </article>
        @empty<div class="auction-row text-slate-500">هنوز حراجی ثبت نشده است.</div>@endforelse
    </div>
    @if(method_exists($auctions, 'links'))<div>{{ $auctions->links() }}</div>@endif
</div>
@endsection
