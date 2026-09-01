@extends('layouts.admin')

@section('title', 'گزارش مالی سهام - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'گزارش مالی سهام')
@section('page-description', 'ارزش فروش تسویه‌شده سهام بر حسب گل و معادل بهار')

@php
    $fa = static function ($value, int $decimals = 0): string {
        return strtr(number_format((float) $value, $decimals, '.', ','), [
            '0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹','.'=>'٫',','=>'٬'
        ]);
    };
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/persian-datepicker/persian-datepicker.min.css') }}">
<style>
.report-card{background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,.08);padding:2rem;margin-bottom:2rem}.report-head{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:2rem}.report-filter{background:#f8fafc;border-radius:12px;padding:1.5rem;margin-bottom:2rem}.report-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.25rem}.summary-card{border-radius:12px;padding:1.5rem;color:#fff;background:linear-gradient(135deg,#10b981,#047857)}.summary-card.info{background:linear-gradient(135deg,#667eea,#6d28d9)}.summary-card.warning{background:linear-gradient(135deg,#f59e0b,#d97706)}.summary-label{font-size:.8rem;opacity:.9}.summary-value{font-size:1.45rem;font-weight:800;margin-top:.45rem}.summary-sub{font-size:.78rem;opacity:.88;margin-top:.25rem}.empty{margin-top:2rem;padding:3rem 1rem;text-align:center;background:#f8fafc;border:2px dashed #e5e7eb;border-radius:12px;color:#64748b}.dark .report-card{background:#1e293b}.dark .report-filter{background:#334155}@media(max-width:700px){.report-card{padding:1rem}}
</style>
@endpush

@section('content')
<div style="direction:rtl">
    <div class="report-card">
        <div class="report-head">
            <h3 class="text-2xl font-bold"><i class="fas fa-chart-pie ml-2"></i>گزارش مالی سهام</h3>
            <a href="{{ route('admin.stock-reports.export-financial', request()->all()) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-500 text-white rounded-lg text-sm font-semibold"><i class="fas fa-download"></i> خروجی CSV</a>
        </div>

        <div class="report-filter">
            <form method="GET" class="grid gap-4 md:grid-cols-3 items-end">
                <div><label class="block text-sm font-semibold mb-2">از تاریخ</label><input type="text" name="date_from" value="{{ request('date_from', $dateFrom ? \Morilog\Jalali\Jalalian::fromCarbon($dateFrom)->format('Y/m/d') : '') }}" class="jalali-date w-full px-3 py-2 border rounded-lg" placeholder="1404/01/01"></div>
                <div><label class="block text-sm font-semibold mb-2">تا تاریخ</label><input type="text" name="date_to" value="{{ request('date_to', $dateTo ? \Morilog\Jalali\Jalalian::fromCarbon($dateTo)->format('Y/m/d') : '') }}" class="jalali-date w-full px-3 py-2 border rounded-lg" placeholder="1404/12/29"></div>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg font-semibold"><i class="fas fa-filter ml-2"></i>اعمال فیلتر</button>
            </form>
        </div>

        <div class="report-summary">
            <div class="summary-card">
                <div class="summary-label">ارزش فروش تسویه‌شده</div>
                <div class="summary-value">{{ $fa($sales['total_sales_gol']) }} گل</div>
                <div class="summary-sub">معادل {{ $fa($sales['total_sales_gol'] / 100, 2) }} بهار</div>
            </div>
            <div class="summary-card info"><div class="summary-label">سهام فروخته‌شده</div><div class="summary-value">{{ $fa($sales['total_shares_sold']) }} سهم</div></div>
            <div class="summary-card warning">
                <div class="summary-label">میانگین قیمت فروش</div>
                <div class="summary-value">{{ $fa($sales['average_price_gol'], 2) }} گل</div>
                <div class="summary-sub">معادل {{ $fa($sales['average_price_gol'] / 100, 4) }} بهار</div>
            </div>
            <div class="summary-card info"><div class="summary-label">تعداد تراکنش‌های فروش</div><div class="summary-value">{{ $fa($sales['total_transactions']) }}</div></div>
        </div>

        <p class="text-sm text-slate-500 mt-6">این گزارش ارزش داراییِ فروش‌های تسویه‌شده را در واحد حسابداری سهام نشان می‌دهد. مبالغ وجه خارجی، در صورت وجود، باید جداگانه از سوابق پرداخت تأییدشده گزارش شوند و از این مقدار استنباط نمی‌شوند.</p>

        @if($auctions->count() === 0)
            <div class="empty"><i class="fas fa-inbox text-5xl mb-4"></i><div class="font-bold text-lg">در این بازه فروش تسویه‌شده‌ای ثبت نشده است</div></div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/persian-date/persian-date.min.js') }}"></script>
<script src="{{ asset('vendor/persian-datepicker/persian-datepicker.min.js') }}"></script>
<script>document.addEventListener('DOMContentLoaded',function(){if(typeof window.jQuery!=='undefined'&&typeof $.fn.persianDatepicker!=='undefined'){$('.jalali-date').each(function(){$(this).persianDatepicker({format:'YYYY/MM/DD',initialValue:!!$(this).val(),calendar:{persian:{locale:'fa'}},autoClose:true});});}});</script>
@endpush
