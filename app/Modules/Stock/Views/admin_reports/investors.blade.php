@extends('layouts.admin')

@section('title', 'گزارش دارندگان سهام - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'گزارش دارندگان سهام')
@section('page-description', 'دارایی سهامداران و ارزش پایه آن بر حسب گل و بهار')

@php
    $fa = static function ($value, int $decimals = 0): string {
        return strtr(number_format((float) $value, $decimals, '.', ','), [
            '0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹','.'=>'٫',','=>'٬'
        ]);
    };
@endphp

@push('styles')
<style>
.report-card{background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,.08);padding:2rem;margin-bottom:2rem}.report-head{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:2rem}.report-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1.5rem;margin-bottom:2rem}.summary-card{border-radius:12px;padding:1.5rem;color:#fff;background:linear-gradient(135deg,#667eea,#764ba2)}.summary-card.success{background:linear-gradient(135deg,#10b981,#047857)}.summary-label{font-size:.8rem;opacity:.9}.summary-value{font-size:1.5rem;font-weight:800;margin-top:.45rem}.summary-sub{font-size:.78rem;opacity:.88;margin-top:.25rem}.report-table{width:100%;border-collapse:collapse}.report-table th,.report-table td{padding:1rem;text-align:right;border-bottom:1px solid #e5e7eb}.report-table th{background:#f8fafc;color:#1e293b}.money-primary{font-weight:800;color:#047857}.money-sub{display:block;font-size:.75rem;color:#64748b;margin-top:.2rem}.empty{padding:3rem 1rem;text-align:center;background:#f8fafc;border:2px dashed #e5e7eb;border-radius:12px;color:#64748b}.dark .report-card{background:#1e293b}.dark .report-table th{background:#334155;color:#f8fafc}.dark .report-table td{border-color:#475569;color:#cbd5e1}@media(max-width:700px){.report-card{padding:1rem}}
</style>
@endpush

@section('content')
<div style="direction:rtl">
    <div class="report-card">
        <div class="report-head">
            <h3 class="text-2xl font-bold"><i class="fas fa-users ml-2"></i>گزارش دارندگان سهام</h3>
            <a href="{{ route('admin.stock-reports.export-investors') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-500 text-white rounded-lg text-sm font-semibold">
                <i class="fas fa-download"></i> خروجی CSV
            </a>
        </div>

        <div class="report-summary">
            <div class="summary-card">
                <div class="summary-label">تعداد کل سهامداران</div>
                <div class="summary-value">{{ $fa($totalInvestors) }}</div>
            </div>
            <div class="summary-card success">
                <div class="summary-label">ارزش پایه کل دارایی‌ها</div>
                <div class="summary-value">{{ $fa($totalAssetValueGol) }} گل</div>
                <div class="summary-sub">معادل {{ $fa($totalAssetValueGol / 100, 2) }} بهار</div>
            </div>
        </div>

        <p class="text-sm text-slate-500 mb-5">این رقم «ارزش پایه دارایی» بر مبنای قیمت پایه فعلی هر سهم است و به معنی مبلغ تاریخی پرداخت‌شده توسط سهامدار نیست.</p>

        @if($investors->count() > 0)
            <div style="overflow-x:auto">
                <table class="report-table">
                    <thead><tr><th>شناسه کاربر</th><th>نام</th><th>ایمیل</th><th>شناسه سهام</th><th>تعداد سهام</th><th>ارزش پایه دارایی</th></tr></thead>
                    <tbody>
                    @foreach($investors as $investor)
                        <tr>
                            <td><a href="{{ route('admin.users.show', $investor->user_id) }}" class="text-blue-500">#{{ $fa($investor->user_id) }}</a></td>
                            <td>{{ trim(($investor->user->first_name ?? '') . ' ' . ($investor->user->last_name ?? '')) ?: '—' }}</td>
                            <td>{{ $investor->user->email ?? '—' }}</td>
                            <td>#{{ $fa($investor->stock_id) }}</td>
                            <td>{{ $fa($investor->total_shares) }} سهم</td>
                            <td><span class="money-primary">{{ $fa($investor->base_asset_value_gol) }} گل</span><span class="money-sub">{{ $fa($investor->base_asset_value_gol / 100, 2) }} بهار</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-8">{{ $investors->links('pagination::bootstrap-5') }}</div>
        @else
            <div class="empty"><i class="fas fa-inbox text-5xl mb-4"></i><div class="font-bold text-lg">هنوز دارایی سهامی ثبت نشده است</div></div>
        @endif
    </div>
</div>
@endsection
