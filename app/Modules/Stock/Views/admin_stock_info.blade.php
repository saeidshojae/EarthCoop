@extends('layouts.admin')

@section('title', 'مدیریت سهام EarthCoop - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'مدیریت سهام EarthCoop')
@section('page-description', 'مشاهده و مدیریت اطلاعات پایه سهام در واحد canonical گل و بهار')

@push('styles')
<style>
    .stock-management-card { background:white; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.08); padding:2rem; margin-bottom:2rem; }
    .stock-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; padding-bottom:1.5rem; border-bottom:2px solid #e5e7eb; flex-wrap:wrap; gap:1rem; }
    .stock-header h3 { font-size:1.5rem; font-weight:700; color:#1e293b; }
    .stock-header-actions { display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; }
    .stock-action-btn { padding:.75rem 1.5rem; border:none; border-radius:.75rem; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:.5rem; transition:all .2s ease; color:white; }
    .stock-action-btn.primary { background:linear-gradient(135deg,#3b82f6 0%,#1d4ed8 100%); }
    .stock-action-btn.success { background:linear-gradient(135deg,#10b981 0%,#047857 100%); }
    .stock-action-btn.info { background:linear-gradient(135deg,#3b82f6 0%,#1d4ed8 100%); }
    .stock-action-btn.operations { background:linear-gradient(135deg,#475569 0%,#1e293b 100%); }
    .stock-info-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1.5rem; margin-bottom:2rem; }
    .stock-info-card { border-radius:12px; padding:1.5rem; color:white; position:relative; overflow:hidden; }
    .stock-info-card.primary { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); }
    .stock-info-card.success { background:linear-gradient(135deg,#10b981 0%,#047857 100%); }
    .stock-info-card.info { background:linear-gradient(135deg,#3b82f6 0%,#1d4ed8 100%); }
    .stock-info-card.warning { background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%); }
    .stock-info-icon { font-size:2.25rem; margin-bottom:1rem; opacity:.9; }
    .stock-info-label { font-size:.875rem; opacity:.9; margin-bottom:.5rem; }
    .stock-info-value { font-size:1.5rem; font-weight:800; }
    .stock-info-subvalue { margin-top:.35rem; font-size:.8rem; opacity:.88; }
    .stock-details-card { background:#f9fafb; border-radius:12px; padding:1.5rem; margin-top:2rem; }
    .stock-details-title { font-size:1.125rem; font-weight:700; color:#1e293b; margin-bottom:1rem; }
    .stock-details-content { color:#4b5563; font-size:.875rem; line-height:1.8; white-space:pre-wrap; }
    .alert { border-radius:.75rem; padding:1rem 1.5rem; margin-bottom:.75rem; }
    .alert-success { background:#d1fae5; color:#065f46; }
    .alert-warning { background:#fef3c7; color:#92400e; }
    .alert-info { background:#dbeafe; color:#1e40af; }
    .alert-danger { background:#fee2e2; color:#991b1b; }
    .stock-stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1.25rem; margin-top:1rem; }
    .stock-stat-card { border-radius:12px; padding:1.25rem; background:#f8fafc; border:1px solid #e2e8f0; }
    .stock-stat-label { color:#64748b; font-size:.8rem; margin-bottom:.4rem; }
    .stock-stat-value { color:#0f172a; font-size:1.35rem; font-weight:800; }
    .stock-reporting-section { margin-top:2rem; padding-top:1.5rem; border-top:1px solid #e2e8f0; }
    .stock-reporting-title { display:flex; align-items:center; gap:.6rem; color:#1e293b; font-size:1.2rem; font-weight:800; margin-bottom:.4rem; }
    .stock-reporting-description { color:#64748b; font-size:.875rem; margin-bottom:1rem; }
    .stock-reporting-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:1rem; }
    .stock-report-card { display:flex; min-height:150px; flex-direction:column; justify-content:space-between; border-radius:14px; padding:1.25rem; color:white; text-decoration:none; transition:transform .2s ease, box-shadow .2s ease; box-shadow:0 4px 14px rgba(15,23,42,.12); }
    .stock-report-card:hover { transform:translateY(-3px); color:white; box-shadow:0 8px 22px rgba(15,23,42,.18); }
    .stock-report-card.auctions { background:linear-gradient(135deg,#3b82f6 0%,#1d4ed8 100%); }
    .stock-report-card.shareholders { background:linear-gradient(135deg,#10b981 0%,#047857 100%); }
    .stock-report-card.financial { background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%); }
    .stock-report-icon { font-size:1.8rem; opacity:.95; }
    .stock-report-name { font-size:1.05rem; font-weight:800; margin-top:1rem; }
    .stock-report-copy { font-size:.8rem; line-height:1.7; opacity:.9; margin-top:.35rem; }
    .dark .stock-management-card { background:#0f172a; }
    .dark .stock-header { border-bottom-color:#334155; }
    .dark .stock-header h3,.dark .stock-details-title,.dark .stock-reporting-title,.dark .stock-stat-value { color:#f8fafc; }
    .dark .stock-details-card,.dark .stock-stat-card { background:#111827; border-color:#334155; }
    .dark .stock-details-content,.dark .stock-reporting-description,.dark .stock-stat-label { color:#cbd5e1; }
    .dark .stock-reporting-section { border-top-color:#334155; }
    @media(max-width:900px){ .stock-reporting-grid{grid-template-columns:1fr} }
    @media(max-width:768px){ .stock-management-card{padding:1rem}.stock-header{align-items:flex-start}.stock-header-actions{width:100%}.stock-action-btn{flex:1 1 auto;justify-content:center} }
</style>
@endpush

@section('content')
@php
    $valuationGol = (int) ($stock->startup_valuation_gol ?? 0);
    $valuationBahar = $valuationGol / 100;
    $baseGol = (int) ($stock->base_share_price_gol ?? 0);
    $baseBahar = $baseGol / 100;
@endphp
<div class="space-y-6" style="direction:rtl;">
    <div class="stock-management-card">
        <div class="stock-header">
            <h3><i class="fas fa-chart-line ml-2"></i> اطلاعات پایه سهام</h3>
            <div class="stock-header-actions">
                <a href="{{ route('admin.stock.gift') }}" class="stock-action-btn success"><i class="fas fa-gift"></i> هدیه دادن سهام</a>
                <a href="{{ route('admin.stock.shareholders') }}" class="stock-action-btn primary"><i class="fas fa-users"></i> لیست سهامداران</a>
                <a href="{{ route('admin.auction.create') }}" class="stock-action-btn" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);"><i class="fas fa-plus-circle"></i> ایجاد عرضه اولیه</a>
                <a href="{{ route('admin.auction.index') }}" class="stock-action-btn primary"><i class="fas fa-gavel"></i> مدیریت عرضه‌ها</a>
                <a href="{{ route('admin.stock.external-payments.index') }}" class="stock-action-btn operations"><i class="fas fa-receipt"></i> تسویه‌های خارجی</a>
                <a href="{{ route('admin.stock.create') }}" class="stock-action-btn success"><i class="fas fa-edit"></i> ویرایش اطلاعات</a>
            </div>
        </div>

        @if($stock)
            @if(isset($alerts) && count($alerts) > 0)
                <div style="margin-bottom:2rem;">
                    @foreach($alerts as $alert)
                        <div class="alert alert-{{ $alert['type'] }}">
                            <strong>{{ $alert['title'] }}</strong>
                            <div style="font-size:.875rem;margin-top:.25rem;">{{ $alert['message'] }}</div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="stock-info-grid">
                <div class="stock-info-card primary">
                    <div class="stock-info-icon"><i class="fas fa-building"></i></div>
                    <div class="stock-info-label">ارزش‌گذاری کل</div>
                    <div class="stock-info-value">{{ number_format($valuationBahar, 0) }} بهار</div>
                    <div class="stock-info-subvalue">{{ number_format($valuationGol) }} گل</div>
                </div>
                <div class="stock-info-card success">
                    <div class="stock-info-icon"><i class="fas fa-coins"></i></div>
                    <div class="stock-info-label">تعداد کل سهام</div>
                    <div class="stock-info-value">{{ number_format($stock->total_shares) }} سهم</div>
                </div>
                <div class="stock-info-card info">
                    <div class="stock-info-icon"><i class="fas fa-box-open"></i></div>
                    <div class="stock-info-label">سهام خزانه قابل عرضه</div>
                    <div class="stock-info-value">{{ number_format($stock->available_shares ?? 0) }} سهم</div>
                    <div class="stock-info-subvalue">موجودی دارایی؛ نه موجودی پول</div>
                </div>
                <div class="stock-info-card warning">
                    <div class="stock-info-icon"><i class="fas fa-tag"></i></div>
                    <div class="stock-info-label">قیمت پایه هر سهم</div>
                    <div class="stock-info-value">{{ number_format($baseGol) }} گل</div>
                    <div class="stock-info-subvalue">{{ number_format($baseBahar, 2) }} بهار</div>
                </div>
            </div>

            <div class="alert alert-info">
                واحد مرجع ارزش‌گذاری و قیمت سهم «گل» است و معادل بهار برای خوانایی نمایش داده می‌شود. تبدیل به وجه خارجی فقط در مسیر تسویه مجاز عرضه اولیه خزانه و با نرخ معتبر همان لحظه انجام می‌شود؛ این مسیر Bahar جدید ایجاد نمی‌کند.
            </div>

            @if($stock->info)
                <div class="stock-details-card">
                    <div class="stock-details-title"><i class="fas fa-info-circle ml-2"></i> توضیحات تکمیلی</div>
                    <div class="stock-details-content">{{ $stock->info }}</div>
                </div>
            @endif

            @if(isset($stats))
                <div style="margin-top:2rem;">
                    <h4 style="font-size:1.25rem;font-weight:700;color:#1e293b;">آمار عرضه‌ها</h4>
                    <div class="stock-stats-grid">
                        <div class="stock-stat-card"><div class="stock-stat-label">کل حراج‌ها</div><div class="stock-stat-value">{{ number_format($stats['total_auctions'] ?? 0) }}</div></div>
                        <div class="stock-stat-card"><div class="stock-stat-label">در حال اجرا</div><div class="stock-stat-value">{{ number_format($stats['active_auctions'] ?? 0) }}</div></div>
                        <div class="stock-stat-card"><div class="stock-stat-label">برنامه‌ریزی‌شده</div><div class="stock-stat-value">{{ number_format($stats['scheduled_auctions'] ?? 0) }}</div></div>
                        <div class="stock-stat-card"><div class="stock-stat-label">تسویه‌شده</div><div class="stock-stat-value">{{ number_format($stats['settled_auctions'] ?? 0) }}</div></div>
                    </div>
                </div>
            @endif

            <section class="stock-reporting-section" aria-labelledby="stock-reporting-title">
                <h4 id="stock-reporting-title" class="stock-reporting-title"><i class="fas fa-chart-bar"></i> گزارش‌گیری و تحلیل</h4>
                <p class="stock-reporting-description">دسترسی مستقیم به گزارش‌های اصلی عملکرد عرضه، سهامداران و وضعیت مالی سهام.</p>
                <div class="stock-reporting-grid">
                    <a href="{{ route('admin.stock-reports.auction-performance') }}" class="stock-report-card auctions">
                        <div class="stock-report-icon"><i class="fas fa-chart-line"></i></div>
                        <div>
                            <div class="stock-report-name">گزارش عملکرد حراج‌ها</div>
                            <div class="stock-report-copy">تحلیل عملکرد عرضه‌ها و حراج‌ها در بازه زمانی انتخابی.</div>
                        </div>
                    </a>

                    <a href="{{ route('admin.stock-reports.investors') }}" class="stock-report-card shareholders">
                        <div class="stock-report-icon"><i class="fas fa-user-friends"></i></div>
                        <div>
                            <div class="stock-report-name">گزارش سهامداران</div>
                            <div class="stock-report-copy">نمای کلی سهامداران، میزان مالکیت و مشارکت آنان در عرضه‌ها.</div>
                        </div>
                    </a>

                    <a href="{{ route('admin.stock-reports.financial') }}" class="stock-report-card financial">
                        <div class="stock-report-icon"><i class="fas fa-chart-pie"></i></div>
                        <div>
                            <div class="stock-report-name">گزارش مالی</div>
                            <div class="stock-report-copy">تحلیل مالی عرضه‌ها، فروش سهام و جریان‌های مرتبط با سرمایه.</div>
                        </div>
                    </a>
                </div>
            </section>
        @else
            <div class="stock-details-card">
                <div class="stock-details-title">اطلاعات سهام هنوز ثبت نشده است.</div>
                <a href="{{ route('admin.stock.create') }}" class="stock-action-btn success">ثبت اطلاعات پایه سهام</a>
            </div>
        @endif
    </div>
</div>
@endsection
