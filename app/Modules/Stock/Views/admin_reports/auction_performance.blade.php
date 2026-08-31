@extends('layouts.admin')

@section('title', 'گزارش عملکرد حراج‌ها - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'گزارش عملکرد حراج‌ها')
@section('page-description', 'عملکرد عرضه‌ها با قیمت‌گذاری گل و معادل بهار')

@php
    $fa = static function ($value, int $decimals = 0): string {
        return strtr(number_format((float) $value, $decimals, '.', ','), [
            '0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹','.'=>'٫',','=>'٬'
        ]);
    };
    $statusLabels = [
        'scheduled'=>'برنامه‌ریزی‌شده','running'=>'در حال اجرا','settling'=>'در حال تسویه','settled'=>'تسویه‌شده',
        'completed'=>'تکمیل‌شده','canceled'=>'لغوشده','cancelled'=>'لغوشده',
    ];
    $typeLabels = ['single_winner'=>'تک‌برنده','uniform_price'=>'قیمت یکسان','pay_as_bid'=>'پرداخت به قیمت پیشنهادی'];
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/persian-datepicker/persian-datepicker.min.css') }}">
<style>
.report-card{background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,.08);padding:2rem;margin-bottom:2rem}.report-head{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:2rem}.report-filter{background:#f8fafc;border-radius:12px;padding:1.5rem;margin-bottom:2rem}.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:2rem}.stat{border-radius:12px;padding:1.25rem;color:#fff;background:linear-gradient(135deg,#667eea,#6d28d9)}.stat.success{background:linear-gradient(135deg,#10b981,#047857)}.stat.warning{background:linear-gradient(135deg,#f59e0b,#d97706)}.stat-label{font-size:.78rem;opacity:.9}.stat-value{font-size:1.35rem;font-weight:900;margin-top:.4rem}.stat-sub{font-size:.75rem;opacity:.85;margin-top:.2rem}.report-table{width:100%;border-collapse:collapse}.report-table th,.report-table td{padding:.85rem;text-align:right;border-bottom:1px solid #e5e7eb;white-space:nowrap}.report-table th{background:#f8fafc;color:#1e293b}.money-sub{display:block;font-size:.72rem;color:#64748b;margin-top:.2rem}.status-chip{display:inline-flex;padding:.3rem .55rem;border-radius:999px;background:#f1f5f9;font-weight:700;font-size:.75rem}.status-chip.running{background:#dcfce7;color:#166534}.empty{padding:3rem 1rem;text-align:center;background:#f8fafc;border:2px dashed #e5e7eb;border-radius:12px;color:#64748b}.dark .report-card{background:#1e293b}.dark .report-filter,.dark .report-table th{background:#334155}.dark .report-table th,.dark .report-table td{color:#cbd5e1;border-color:#475569}@media(max-width:700px){.report-card{padding:1rem}}
</style>
@endpush

@section('content')
<div style="direction:rtl">
    <div class="report-card">
        <div class="report-head">
            <h3 class="text-2xl font-bold"><i class="fas fa-chart-bar ml-2"></i>گزارش عملکرد حراج‌ها</h3>
            <a href="{{ route('admin.stock-reports.export-auction-performance', request()->all()) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-500 text-white rounded-lg text-sm font-semibold"><i class="fas fa-download"></i> خروجی CSV</a>
        </div>

        <div class="report-filter">
            <form method="GET" class="grid gap-4 md:grid-cols-3 items-end">
                <div><label class="block text-sm font-semibold mb-2">از تاریخ</label><input type="text" name="date_from" value="{{ request('date_from', $dateFrom ? \Morilog\Jalali\Jalalian::fromCarbon($dateFrom)->format('Y/m/d') : '') }}" class="jalali-date w-full px-3 py-2 border rounded-lg" placeholder="1404/01/01"></div>
                <div><label class="block text-sm font-semibold mb-2">تا تاریخ</label><input type="text" name="date_to" value="{{ request('date_to', $dateTo ? \Morilog\Jalali\Jalalian::fromCarbon($dateTo)->format('Y/m/d') : '') }}" class="jalali-date w-full px-3 py-2 border rounded-lg" placeholder="1404/12/29"></div>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg font-semibold"><i class="fas fa-filter ml-2"></i>اعمال فیلتر</button>
            </form>
        </div>

        <div class="stats">
            <div class="stat"><div class="stat-label">کل حراج‌ها</div><div class="stat-value">{{ $fa($stats['total_auctions']) }}</div></div>
            <div class="stat success"><div class="stat-label">حراج‌های تکمیل‌شده</div><div class="stat-value">{{ $fa($stats['completed_auctions']) }}</div></div>
            <div class="stat warning"><div class="stat-label">حراج‌های لغوشده</div><div class="stat-value">{{ $fa($stats['canceled_auctions']) }}</div></div>
            <div class="stat"><div class="stat-label">کل سهام عرضه‌شده</div><div class="stat-value">{{ $fa($stats['total_shares_offered']) }} سهم</div></div>
            <div class="stat success"><div class="stat-label">سهام فروخته‌شده</div><div class="stat-value">{{ $fa($stats['total_shares_sold']) }} سهم</div></div>
            <div class="stat"><div class="stat-label">کل پیشنهادها</div><div class="stat-value">{{ $fa($stats['total_bids']) }}</div></div>
            <div class="stat success"><div class="stat-label">ارزش فروش ثبت‌شده</div><div class="stat-value">{{ $fa($stats['total_capital_gol']) }} گل</div><div class="stat-sub">معادل {{ $fa($stats['total_capital_gol'] / 100, 2) }} بهار</div></div>
            <div class="stat warning"><div class="stat-label">میانگین قیمت پیشنهادی</div><div class="stat-value">{{ $fa($stats['average_price_gol'], 2) }} گل</div><div class="stat-sub">معادل {{ $fa($stats['average_price_gol'] / 100, 4) }} بهار</div></div>
        </div>

        @if($auctions->count() > 0)
            <div style="overflow-x:auto">
                <table class="report-table">
                    <thead><tr><th>شناسه</th><th>تعداد سهام</th><th>قیمت پایه</th><th>وضعیت</th><th>نوع</th><th>پیشنهادها</th><th>بالاترین پیشنهاد</th><th>میانگین قیمت</th><th>شروع</th><th>پایان</th></tr></thead>
                    <tbody>
                    @foreach($auctions as $auction)
                        @php
                            $bids = $auction->bids;
                            $canonicalBids = $bids->filter(fn($bid) => (int)($bid->price_gol ?? 0) > 0);
                            $highestBidGol = (int)($canonicalBids->max('price_gol') ?? 0);
                            $avgPriceGol = (float)($canonicalBids->avg('price_gol') ?? 0);
                            $baseGol = (int)($auction->base_price_gol ?? 0);
                        @endphp
                        <tr>
                            <td><a href="{{ route('admin.auction.show', $auction) }}" class="text-blue-500">#{{ $fa($auction->id) }}</a></td>
                            <td>{{ $fa($auction->shares_count) }} سهم</td>
                            <td><strong>{{ $fa($baseGol) }} گل</strong><span class="money-sub">{{ $fa($baseGol / 100, 2) }} بهار</span></td>
                            <td><span class="status-chip {{ $auction->status === 'running' ? 'running' : '' }}">{{ $statusLabels[$auction->status] ?? 'نامشخص' }}</span></td>
                            <td>{{ $typeLabels[$auction->type] ?? 'نامشخص' }}</td>
                            <td>{{ $fa($bids->count()) }}</td>
                            <td>{{ $highestBidGol > 0 ? $fa($highestBidGol) . ' گل' : '—' }}</td>
                            <td>{{ $avgPriceGol > 0 ? $fa($avgPriceGol, 2) . ' گل' : '—' }}</td>
                            <td>{{ $auction->start_time ? \Morilog\Jalali\Jalalian::fromCarbon($auction->start_time)->format('Y/m/d H:i') : '—' }}</td>
                            <td>{{ $auction->ends_at ? \Morilog\Jalali\Jalalian::fromCarbon($auction->ends_at)->format('Y/m/d H:i') : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty"><i class="fas fa-inbox text-5xl mb-4"></i><div class="font-bold text-lg">در این بازه حراجی ثبت نشده است</div></div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/persian-date/persian-date.min.js') }}"></script>
<script src="{{ asset('vendor/persian-datepicker/persian-datepicker.min.js') }}"></script>
<script>document.addEventListener('DOMContentLoaded',function(){if(typeof window.jQuery!=='undefined'&&typeof $.fn.persianDatepicker!=='undefined'){$('.jalali-date').each(function(){$(this).persianDatepicker({format:'YYYY/MM/DD',initialValue:!!$(this).val(),calendar:{persian:{locale:'fa'}},autoClose:true});});}});</script>
@endpush
