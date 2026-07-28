@extends('layouts.admin')

@section('title', 'مدیریت حراج‌های سهام - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'مدیریت حراج‌های سهام')
@section('page-description', 'مشاهده، ایجاد و مدیریت حراج‌های سهام')

@php
    // استفاده از متغیرهای ارسال شده از controller
    $statusCounts = $statusCounts ?? [];
    $totalVolume = $totalVolume ?? 0;
    $stats = $stats ?? [];
    
    // محاسبه بالاترین پیشنهاد از لیست فعلی
    $highestBid = $auctions->getCollection()->max('highest_bid') ?? 0;

    $statusLabels = [
        'scheduled' => 'برنامه‌ریزی شده',
        'running' => 'فعال',
        'settling' => 'در حال تسویه',
        'settled' => 'تسویه شده',
        'canceled' => 'لغو شده',
    ];

    $typeLabels = [
        'single_winner' => 'تک برنده',
        'uniform_price' => 'قیمت یکسان',
        'pay_as_bid' => 'پرداخت به قیمت پیشنهادی',
    ];
    
    $sortOptions = [
        'id' => 'شناسه',
        'shares_count' => 'تعداد سهام',
        'base_price' => 'قیمت پایه',
        'start_time' => 'زمان شروع',
        'ends_at' => 'زمان پایان',
        'status' => 'وضعیت',
        'type' => 'نوع حراج',
        'created_at' => 'تاریخ ایجاد',
    ];
    
    $sortOrders = [
        'asc' => 'صعودی',
        'desc' => 'نزولی',
    ];
@endphp

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .chart-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
        }
        
        @media (prefers-color-scheme: dark) {
            .chart-container {
                background: #334155 !important;
            }
            
            .chart-title {
                color: #f1f5f9 !important;
            }
        }
    </style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <div class="bg-slate-50/70 dark:bg-slate-950/80 min-h-full" style="margin: -2rem; padding: 2rem; border-radius: 16px;">
        <div class="space-y-10" dir="rtl">
            <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-500/10 via-white to-sky-100 dark:from-emerald-500/15 dark:via-slate-900 dark:to-sky-500/10 shadow-xl border border-emerald-200/60 dark:border-emerald-500/30">
                <div class="absolute inset-0 pointer-events-none">
                    <div class="absolute -top-32 -left-16 w-72 h-72 rounded-full bg-emerald-400/25 blur-3xl"></div>
                    <div class="absolute -bottom-36 -right-24 w-80 h-80 rounded-full bg-sky-400/20 blur-3xl"></div>
                </div>
                <div class="relative z-10 px-6 md:px-10 py-10 space-y-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                        <div class="space-y-3">
                            <span class="inline-flex items-center gap-2 rounded-full bg-emerald-500/15 px-4 py-2 text-xs font-semibold tracking-wide text-emerald-700 dark:text-emerald-200 border border-emerald-500/30">
                                <i class="fas fa-chart-line text-sm"></i>
                                <span>پنل مدیریتی حراج‌ها</span>
                            </span>
                            <h1 class="font-vazirmatn text-3xl md:text-4xl font-extrabold text-slate-900 dark:text-white leading-tight">
                                پایش و مدیریت جریان‌های سرمایه‌گذاری EarthCoop
                            </h1>
                            <p class="md:text-lg text-slate-600 dark:text-slate-300 leading-relaxed max-w-3xl">
                                نمایی کامل از حراج‌های در حال برگزاری، وضعیت جذب سرمایه و رفتار پیشنهاددهندگان. از اینجا می‌توانید حراج‌های جدید را برنامه‌ریزی کنید، داده‌ها را خروجی بگیرید و روندها را زیر نظر بگیرید.
                            </p>
                        </div>
                        <a href="{{ route('admin.auction.create') }}"
                           class="inline-flex items-center justify-center gap-2 rounded-full bg-gradient-to-r from-earth-green to-emerald-500 px-7 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-500/25 transition hover:from-emerald-500 hover:to-earth-green focus:outline-none focus:ring-4 focus:ring-emerald-400/30">
                            <i class="fas fa-plus-circle text-base"></i>
                            <span>ایجاد حراج جدید</span>
                        </a>
                    </div>

                    <div class="grid gap-4 md:grid-cols-4">
                        <div class="rounded-2xl border border-emerald-200/70 bg-white/80 dark:bg-slate-900/80 dark:border-emerald-500/30 px-5 py-4 shadow">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">حراج‌های فعال</span>
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-500 text-lg">
                                    <i class="fas fa-play"></i>
                                </span>
                            </div>
                            <p class="mt-3 text-3xl font-bold text-slate-900 dark:text-white">{{ $statusCounts['running'] }}</p>
                        </div>

                        <div class="rounded-2xl border border-sky-200/70 bg-white/80 dark:bg-slate-900/80 dark:border-sky-500/40 px-5 py-4 shadow">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">سهام برنامه‌ریزی‌شده</span>
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-sky-500/15 text-sky-500 text-lg">
                                    <i class="fas fa-calendar-week"></i>
                                </span>
                            </div>
                            <p class="mt-3 text-3xl font-bold text-slate-900 dark:text-white">{{ $statusCounts['scheduled'] }}</p>
                        </div>

                        <div class="rounded-2xl border border-amber-200/70 bg-white/80 dark:bg-slate-900/80 dark:border-amber-500/30 px-5 py-4 shadow">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">حجم کل پیشنهادها</span>
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-amber-500/15 text-amber-500 text-lg">
                                    <i class="fas fa-layer-group"></i>
                                </span>
                            </div>
                            <p class="mt-3 text-3xl font-bold text-slate-900 dark:text-white">{{ number_format($totalVolume) }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">تعداد سهام پیشنهاد شده در این صفحه</p>
                        </div>

                        <div class="rounded-2xl border border-purple-200/70 bg-white/80 dark:bg-slate-900/80 dark:border-purple-500/30 px-5 py-4 shadow">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">بالاترین پیشنهاد ثبت‌شده</span>
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-purple-500/15 text-purple-500 text-lg">
                                    <i class="fas fa-trophy"></i>
                                </span>
                            </div>
                            <p class="mt-3 text-3xl font-bold text-slate-900 dark:text-white">
                                {{ $highestBid ? number_format($highestBid) . ' ریال' : '—' }}
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">در بین حراج‌های لیست شده</p>
                        </div>
                    </div>
                </div>
            </section>
            
            @if(isset($chartData) && count($chartData['labels']) > 0)
            <!-- نمودارها -->
            <section class="rounded-3xl bg-white dark:bg-slate-900/90 border border-slate-200/70 dark:border-slate-700/70 shadow-xl px-6 md:px-8 py-8 space-y-8">
                <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-4">
                    <i class="fas fa-chart-line ml-2"></i>
                    نمودارهای تحلیلی
                </h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem;">
                    <!-- نمودار تعداد حراج‌ها -->
                    <div class="chart-container">
                        <div class="chart-title">
                            <i class="fas fa-chart-bar ml-2"></i>
                            روند تعداد حراج‌ها (12 ماه گذشته)
                        </div>
                        <canvas id="auctionCountChart" style="max-height: 300px;"></canvas>
                    </div>
                    
                    <!-- نمودار حجم پیشنهادها -->
                    <div class="chart-container">
                        <div class="chart-title">
                            <i class="fas fa-chart-area ml-2"></i>
                            روند حجم پیشنهادها (12 ماه گذشته)
                        </div>
                        <canvas id="volumeChart" style="max-height: 300px;"></canvas>
                    </div>
                    
                    <!-- نمودار قیمت‌ها -->
                    <div class="chart-container">
                        <div class="chart-title">
                            <i class="fas fa-chart-line ml-2"></i>
                            روند قیمت‌های پیشنهادی (12 ماه گذشته)
                        </div>
                        <canvas id="priceChart" style="max-height: 300px;"></canvas>
                    </div>
                </div>
            </section>
            @endif

            <section class="rounded-3xl bg-white dark:bg-slate-900/90 border border-slate-200/70 dark:border-slate-700/70 shadow-xl px-6 md:px-8 py-8 space-y-8">
                <form method="GET" class="space-y-6">
                    <!-- ردیف اول: فیلترهای اصلی -->
                    <div class="grid gap-4 md:grid-cols-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-300 mb-2">وضعیت حراج</label>
                            <select name="status" class="auction-form-input @error('status') border-red-400 @enderror">
                                <option value="">همه وضعیت‌ها</option>
                                @foreach($statusLabels as $value => $label)
                                    <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-300 mb-2">نوع حراج</label>
                            <select name="type" class="auction-form-input">
                                <option value="">همه انواع</option>
                                @foreach($typeLabels as $value => $label)
                                    <option value="{{ $value }}" {{ request('type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-300 mb-2">از تاریخ</label>
                            <input type="text"
                                   name="date_from"
                                   value="{{ request('date_from') }}"
                                   class="auction-form-input jalali-date"
                                   placeholder="مثلاً 1404/09/01">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-300 mb-2">تا تاریخ</label>
                            <input type="text"
                                   name="date_to"
                                   value="{{ request('date_to') }}"
                                   class="auction-form-input jalali-date"
                                   placeholder="مثلاً 1404/09/30">
                        </div>
                    </div>
                    
                    <!-- ردیف دوم: جستجو -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-300 mb-2">جستجو در توضیحات و نوع</label>
                        <input type="text"
                               name="q"
                               value="{{ request('q') }}"
                               class="auction-form-input"
                               placeholder="مثلاً: افزایش سرمایه یا تک برنده">
                    </div>
                    
                    <!-- ردیف سوم: فیلترهای قیمت و حجم -->
                    <div class="grid gap-4 md:grid-cols-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-300 mb-2">حداقل قیمت پایه (ریال)</label>
                            <input type="number"
                                   name="price_min"
                                   value="{{ request('price_min') }}"
                                   class="auction-form-input"
                                   placeholder="مثلاً: 100000"
                                   min="0"
                                   step="1000">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-300 mb-2">حداکثر قیمت پایه (ریال)</label>
                            <input type="number"
                                   name="price_max"
                                   value="{{ request('price_max') }}"
                                   class="auction-form-input"
                                   placeholder="مثلاً: 500000"
                                   min="0"
                                   step="1000">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-300 mb-2">حداقل حجم سهام</label>
                            <input type="number"
                                   name="volume_min"
                                   value="{{ request('volume_min') }}"
                                   class="auction-form-input"
                                   placeholder="مثلاً: 1000"
                                   min="0"
                                   step="1">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-300 mb-2">حداکثر حجم سهام</label>
                            <input type="number"
                                   name="volume_max"
                                   value="{{ request('volume_max') }}"
                                   class="auction-form-input"
                                   placeholder="مثلاً: 10000"
                                   min="0"
                                   step="1">
                        </div>
                    </div>
                    
                    <!-- ردیف چهارم: فیلتر پیشنهادها و مرتب‌سازی -->
                    <div class="grid gap-4 md:grid-cols-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-300 mb-2">حداقل تعداد پیشنهادها</label>
                            <input type="number"
                                   name="bids_min"
                                   value="{{ request('bids_min') }}"
                                   class="auction-form-input"
                                   placeholder="مثلاً: 5"
                                   min="0"
                                   step="1">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-300 mb-2">حداکثر تعداد پیشنهادها</label>
                            <input type="number"
                                   name="bids_max"
                                   value="{{ request('bids_max') }}"
                                   class="auction-form-input"
                                   placeholder="مثلاً: 50"
                                   min="0"
                                   step="1">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-300 mb-2">مرتب‌سازی بر اساس</label>
                            <select name="sort_by" class="auction-form-input">
                                @foreach($sortOptions as $value => $label)
                                    <option value="{{ $value }}" {{ request('sort_by', 'id') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-300 mb-2">ترتیب مرتب‌سازی</label>
                            <select name="sort_order" class="auction-form-input">
                                @foreach($sortOrders as $value => $label)
                                    <option value="{{ $value }}" {{ request('sort_order', 'desc') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- دکمه‌ها -->
                    <div class="flex flex-wrap gap-3 pt-2">
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-earth-green to-emerald-500 px-5 py-2.5 text-sm font-semibold text-white shadow hover:from-emerald-500 hover:to-earth-green">
                            <i class="fas fa-filter text-xs"></i>
                            <span>اعمال فیلتر</span>
                        </button>
                        <a href="{{ route('admin.auction.index') }}"
                           class="inline-flex items-center gap-2 rounded-full border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-600 hover:border-slate-400 hover:text-slate-800 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-400">
                            <i class="fas fa-undo text-xs"></i>
                            <span>ریست فیلتر</span>
                        </a>
                    </div>
                </form>

                <!-- عملیات دسته‌ای -->
                <form id="bulkActionForm" method="POST" action="{{ route('admin.auction.bulk-action') }}" style="display: none;">
                    @csrf
                    <input type="hidden" name="action" id="bulkAction">
                </form>
                
                <div class="flex flex-wrap items-center justify-between gap-4 p-4 bg-slate-50 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" 
                               id="selectAll" 
                               class="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500">
                        <label for="selectAll" class="text-sm font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
                            انتخاب همه
                        </label>
                        <span id="selectedCount" class="text-xs text-slate-500 dark:text-slate-400">(0 مورد انتخاب شده)</span>
                    </div>
                    
                    <div class="flex flex-wrap gap-2" id="bulkActions" style="display: none;">
                        <button type="button" 
                                onclick="performBulkAction('start')"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-500 text-white rounded-lg text-sm font-semibold hover:bg-emerald-600 transition">
                            <i class="fas fa-play text-xs"></i>
                            شروع انتخاب شده‌ها
                        </button>
                        
                        <button type="button" 
                                onclick="performBulkAction('close')"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500 text-white rounded-lg text-sm font-semibold hover:bg-amber-600 transition">
                            <i class="fas fa-stop text-xs"></i>
                            بستن انتخاب شده‌ها
                        </button>
                        
                        <button type="button" 
                                onclick="performBulkAction('delete')"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-rose-500 text-white rounded-lg text-sm font-semibold hover:bg-rose-600 transition">
                            <i class="fas fa-trash text-xs"></i>
                            حذف انتخاب شده‌ها
                        </button>
                    </div>
                </div>

                <div class="space-y-5">
                    @forelse($auctions as $auction)
                        @php
                            $statusColor = match($auction->status) {
                                'running' => 'bg-emerald-500/15 text-emerald-700 border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200 dark:border-emerald-500/30',
                                'scheduled' => 'bg-amber-500/15 text-amber-700 border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200 dark:border-amber-500/30',
                                'settled' => 'bg-blue-500/15 text-blue-700 border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-200 dark:border-blue-500/30',
                                'canceled' => 'bg-rose-500/15 text-rose-700 border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200 dark:border-rose-500/30',
                                default => 'bg-slate-500/15 text-slate-700 border-slate-500/30 dark:bg-slate-500/10 dark:text-slate-200 dark:border-slate-500/30'
                            };
                        @endphp
                        <article class="rounded-2xl border border-slate-200/70 dark:border-slate-700/70 bg-white dark:bg-slate-900/80 shadow p-6 space-y-6">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                <div class="flex items-start gap-3">
                                    <input type="checkbox" 
                                           name="auction_checkbox" 
                                           value="{{ $auction->id }}" 
                                           class="auction-checkbox mt-1 w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500">
                                    <div class="space-y-2 flex-1">
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold {{ $statusColor }}">
                                            <i class="fas fa-circle-info text-[10px]"></i>
                                            {{ $statusLabels[$auction->status] ?? 'نامشخص' }}
                                        </span>
                                        <span class="text-xs font-medium text-slate-500 dark:text-slate-400">کد حراج: #{{ $auction->id }}</span>
                                    </div>
                                    <h2 class="font-vazirmatn text-2xl font-bold text-slate-900 dark:text-white">
                                        {{ $typeLabels[$auction->type] ?? 'نوع ناشناخته' }}
                                    </h2>
                                    @if($auction->info)
                                        <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">{{ Str::limit($auction->info, 180) }}</p>
                                    @else
                                        <p class="text-sm text-slate-500 dark:text-slate-400 italic">توضیحی برای این حراج ثبت نشده است.</p>
                                    @endif
                                </div>

                                <div class="flex flex-wrap gap-3">
                                    <a href="{{ route('admin.auction.show', $auction) }}"
                                       class="inline-flex items-center gap-2 rounded-full bg-sky-500/15 text-sky-600 dark:text-sky-200 px-4 py-2 text-xs font-semibold border border-sky-500/30 hover:bg-sky-500/25">
                                        <i class="fas fa-eye text-xs"></i>
                                        مشاهده
                                    </a>

                                    <a href="{{ route('admin.auction.edit', $auction) }}"
                                       class="inline-flex items-center gap-2 rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-slate-400 hover:text-slate-800 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-400">
                                        <i class="fas fa-pen text-xs"></i>
                                        ویرایش
                                    </a>

                                    @if($auction->status === 'scheduled')
                                        <form method="POST" action="{{ route('admin.auction.start', $auction) }}" class="inline-flex">
                                            @csrf
                                            <button type="submit"
                                                    class="inline-flex items-center gap-2 rounded-full bg-emerald-500/90 px-4 py-2 text-xs font-semibold text-white shadow hover:bg-emerald-600">
                                                <i class="fas fa-play text-xs"></i>
                                                شروع
                                            </button>
                                        </form>
                                    @endif

                                    @if($auction->status === 'running')
                                        <form method="POST" action="{{ route('admin.auction.close', $auction) }}" class="inline-flex">
                                            @csrf
                                            <button type="submit"
                                                    class="inline-flex items-center gap-2 rounded-full bg-amber-500/90 px-4 py-2 text-xs font-semibold	text-white shadow hover:bg-amber-600">
                                                <i class="fas fa-stop text-xs"></i>
                                                بستن
                                            </button>
                                        </form>
                                    @endif

                                    <a href="{{ route('admin.auction.export', $auction) }}"
                                       class="inline-flex items-center gap-2 rounded-full border border-emerald-400 px-4 py-2 text-xs font-semibold text-emerald-600 hover:bg-emerald-50 dark:border-emerald-500/40 dark:text-emerald-200">
                                        <i class="fas fa-file-arrow-down text-xs"></i>
                                        دریافت CSV
                                    </a>

                                    <form method="POST" action="{{ route('admin.auction.destroy', $auction) }}"
                                          onsubmit="return confirm('آیا از حذف این حراج مطمئن هستید؟')" class="inline-flex">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="inline-flex items-center gap-2 rounded-full bg-rose-500/90 px-4 py-2 text-xs font-semibold text-white shadow hover:bg-rose-600">
                                            <i class="fas fa-trash text-xs"></i>
                                            حذف
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-4">
                                <div class="rounded-xl border border-slate-200/70 dark:border-slate-700/70 bg-slate-50/60 dark:bg-slate-900/60 px-4 py-3">
                                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">سهام عرضه شده</span>
                                    <p class="mt-2 text-xl font-bold text-slate-900 dark:text-white">{{ number_format($auction->shares_count) }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-200/70 dark:border-slate-700/70 bg-slate-50/60 dark:bg-slate-900/60 px-4 py-3">
                                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">قیمت پایه هر سهم</span>
                                    <p class="mt-2 text-xl font-bold text-slate-900 dark:text-white">{{ number_format($auction->base_price) }} ریال</p>
                                </div>
                                <div class="rounded-xl border border-slate-200/70 dark:border-slate-700/70 bg-slate-50/60 dark:bg-slate-900/60 px-4 py-3">
                                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">قیمت‌های ثبت‌شده</span>
                                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                                        بالاترین: <strong>{{ $auction->highest_bid ? number_format($auction->highest_bid) . ' ریال' : '—' }}</strong><br>
                                        پایین‌ترین: <strong>{{ $auction->lowest_bid ? number_format($auction->lowest_bid) . ' ریال' : '—' }}</strong>
                                    </p>
                                </div>
                                <div class="rounded-xl border border-slate-200/70 dark:border-slate-700/70 bg-slate-50/60 dark:bg-slate-900/60 px-4 py-3">
                                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">پیشنهادها</span>
                                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                                        تعداد پیشنهادها: <strong>{{ $auction->bids_count }}</strong><br>
                                        حجم پیشنهادها: <strong>{{ number_format($auction->total_bid_volume) }}</strong>
                                    </p>
                                </div>
                            </div>

                            <div class="rounded-xl border border-slate-200/60 dark:border-slate-700/60 bg-slate-50/60 dark:bg-slate-900/60 px-4 py-4">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">شروع حراج</span>
                                        <p class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-200">{{ $auction->start_time ? verta($auction->start_time)->format('Y/m/d H:i') : '—' }}</p>
                                    </div>
                                    <div>
                                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">پایان حراج</span>
                                        <p class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-200">{{ $auction->ends_at ? verta($auction->ends_at)->format('Y/m/d H:i') : '—' }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-xl border border-slate-200/60 dark:border-slate-700/60 bg-slate-50/60 dark:bg-slate-900/60 px-4 py-4">
                                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">صف خرید (پنج پیشنهاد برتر)</span>
                                @if(!empty($auction->order_book) && $auction->order_book->count())
                                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                                        @foreach($auction->order_book as $order)
                                            <div class="rounded-lg border border-slate-200/70 dark:border-slate-700/70 px-3 py-2 text-xs text-slate-600 dark:text-slate-300">
                                                <div class="flex items-center justify-between">
                                                    <span>کاربر #{{ $order->user_id }}</span>
                                                    <span class="font-semibold text-emerald-600 dark:text-emerald-300">{{ number_format($order->price) }} ریال</span>
                                                </div>
                                                <div class="mt-1 text-slate-500 dark:text-slate-400">تعداد: {{ number_format($order->quantity) }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">صف خریدی برای این حراج ثبت نشده است.</p>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-amber-200/70 bg-amber-50/80 dark:border-amber-500/30 dark:bg-amber-500/10 px-6 py-10 text-center">
                            <div class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-amber-500/20 text-amber-500 text-2xl">
                                <i class="fas fa-folder-open"></i>
                            </div>
                            <h3 class="mt-4 text-lg font-bold text-slate-800 dark:text-slate-100">هنوز حراجی ثبت نشده است</h3>
                            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">برای شروع، روی «ایجاد حراج جدید» کلیک کنید و اولین عرضه سهام را برنامه‌ریزی کنید.</p>
                        </div>
                    @endforelse
                </div>

                <div class="pt-4">
                    {{ $auctions->links('pagination::bootstrap-5') }}
                </div>
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/persian-date@1.0.6/dist/persian-date.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
    <script>
        (function () {
            document.addEventListener('DOMContentLoaded', function () {
                // Persian Datepicker - وابسته به jQuery
                if (typeof window.jQuery !== 'undefined' && typeof $.fn.persianDatepicker !== 'undefined') {
                    $('.jalali-date').each(function () {
                        $(this).persianDatepicker({
                            format: 'YYYY/MM/DD',
                            initialValue: !!$(this).val(),
                            calendar: { persian: { locale: 'fa' } },
                            autoClose: true
                        });
                    });
                } else {
                    console.warn('Persian datepicker could not be initialised because dependencies are missing.');
                }
                
                // عملیات دسته‌ای - مستقل از jQuery
                function initBulkActions() {
                    const selectAllCheckbox = document.getElementById('selectAll');
                    const bulkActions = document.getElementById('bulkActions');
                    const selectedCount = document.getElementById('selectedCount');
                    const bulkActionForm = document.getElementById('bulkActionForm');
                    const bulkActionInput = document.getElementById('bulkAction');
                    
                    if (!selectAllCheckbox || !bulkActionForm) {
                        console.warn('Bulk action elements not found');
                        return;
                    }
                    
                    function getCheckboxes() {
                        return document.querySelectorAll('.auction-checkbox');
                    }
                    
                    // به‌روزرسانی نمایش دکمه‌های عملیات دسته‌ای
                    function updateBulkActions() {
                        const checkboxes = getCheckboxes();
                        const selected = Array.from(checkboxes).filter(cb => cb.checked);
                        const count = selected.length;
                        
                        if (selectedCount) {
                            selectedCount.textContent = `(${count} مورد انتخاب شده)`;
                        }
                        
                        if (bulkActions) {
                            if (count > 0) {
                                bulkActions.style.display = 'flex';
                            } else {
                                bulkActions.style.display = 'none';
                            }
                        }
                    }
                    
                    // انتخاب همه
                    selectAllCheckbox.addEventListener('change', function() {
                        const checkboxes = getCheckboxes();
                        checkboxes.forEach(checkbox => {
                            checkbox.checked = this.checked;
                        });
                        updateBulkActions();
                    });
                    
                    // انتخاب تک‌تک - استفاده از event delegation
                    if (document.body) {
                        document.body.addEventListener('change', function(e) {
                            if (e.target && e.target.classList.contains('auction-checkbox')) {
                                updateBulkActions();
                                const checkboxes = getCheckboxes();
                                selectAllCheckbox.checked = checkboxes.length > 0 && Array.from(checkboxes).every(cb => cb.checked);
                            }
                        });
                    }
                    
                    // اجرای عملیات دسته‌ای - تابع global
                    window.performBulkAction = function(action) {
                        const checkboxes = getCheckboxes();
                        const selected = Array.from(checkboxes).filter(cb => cb.checked).map(cb => cb.value);
                        
                        if (selected.length === 0) {
                            alert('لطفاً حداقل یک حراج را انتخاب کنید');
                            return;
                        }
                        
                        let confirmMessage = '';
                        switch(action) {
                            case 'start':
                                confirmMessage = `آیا از شروع ${selected.length} حراج انتخاب شده مطمئن هستید؟`;
                                break;
                            case 'close':
                                confirmMessage = `آیا از بستن ${selected.length} حراج انتخاب شده مطمئن هستید؟`;
                                break;
                            case 'delete':
                                confirmMessage = `آیا از حذف ${selected.length} حراج انتخاب شده مطمئن هستید؟ این عمل قابل بازگشت نیست!`;
                                break;
                            default:
                                confirmMessage = `آیا از انجام این عمل روی ${selected.length} حراج انتخاب شده مطمئن هستید؟`;
                        }
                        
                        if (!confirm(confirmMessage)) {
                            return;
                        }
                        
                        // تنظیم فرم
                        if (bulkActionInput) {
                            bulkActionInput.value = action;
                        }
                        
                        // حذف inputهای قبلی
                        const existingIds = bulkActionForm.querySelectorAll('input[name="auction_ids[]"]');
                        existingIds.forEach(input => input.remove());
                        
                        // افزودن inputهای جدید
                        selected.forEach(id => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'auction_ids[]';
                            input.value = id;
                            bulkActionForm.appendChild(input);
                        });
                        
                        // ارسال فرم
                        bulkActionForm.submit();
                    };
                }
                
                // راه‌اندازی عملیات دسته‌ای
                initBulkActions();
                
                // نمودارها
                @if(isset($chartData) && count($chartData['labels']) > 0)
                // نمودار تعداد حراج‌ها
                const countCtx = document.getElementById('auctionCountChart');
                if (countCtx) {
                    new Chart(countCtx, {
                        type: 'bar',
                        data: {
                            labels: [@foreach($chartData['labels'] as $label)'{{ $label }}',@endforeach],
                            datasets: [{
                                label: 'تعداد حراج‌ها',
                                data: [@foreach($chartData['counts'] as $count){{ $count }},@endforeach],
                                backgroundColor: 'rgba(59, 130, 246, 0.5)',
                                borderColor: 'rgb(59, 130, 246)',
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top',
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }
                
                // نمودار حجم پیشنهادها
                const volumeCtx = document.getElementById('volumeChart');
                if (volumeCtx) {
                    new Chart(volumeCtx, {
                        type: 'line',
                        data: {
                            labels: [@foreach($chartData['labels'] as $label)'{{ $label }}',@endforeach],
                            datasets: [{
                                label: 'حجم پیشنهادها (سهم)',
                                data: [@foreach($chartData['volumes'] as $volume){{ $volume }},@endforeach],
                                borderColor: 'rgb(16, 185, 129)',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top',
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }
                
                // نمودار قیمت‌ها
                const priceCtx = document.getElementById('priceChart');
                if (priceCtx) {
                    new Chart(priceCtx, {
                        type: 'line',
                        data: {
                            labels: [@foreach($chartData['labels'] as $label)'{{ $label }}',@endforeach],
                            datasets: [{
                                label: 'میانگین قیمت پیشنهادی (ریال)',
                                data: [@foreach($chartData['prices'] as $price){{ $price }},@endforeach],
                                borderColor: 'rgb(245, 158, 11)',
                                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top',
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }
                @endif
            });
        })();
    </script>
@endpush
