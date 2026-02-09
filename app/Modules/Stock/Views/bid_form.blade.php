@extends('layouts.unified')

@php
    $stockName = trim(optional($auction->stock)->info ?? optional($auction->stock)->name ?? '') ?: 'سهام EarthCoop';
    $basePrice = $auction->base_price ? number_format($auction->base_price) . ' ریال' : '—';
    $availableShares = $auction->shares_count ? number_format($auction->shares_count) : '—';
    $highestBid = $auction->highest_bid ? number_format($auction->highest_bid) . ' ریال' : '—';
@endphp

@section('title', 'ثبت پیشنهاد برای حراج ' . $stockName . ' - ' . config('app.name', 'EarthCoop'))

@push('styles')
    <style>
        .bid-shell {
            border-radius: 1.75rem;
            border: 1px solid rgba(226, 232, 240, 0.65);
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.14);
            overflow: hidden;
        }

        .dark .bid-shell {
            background: rgba(15, 23, 42, 0.92);
            border-color: rgba(148, 163, 184, 0.35);
            box-shadow: 0 24px 70px rgba(7, 11, 19, 0.55);
        }

        .bid-form-input {
            width: 100%;
            border-radius: 1.1rem;
            border: 1px solid rgba(148, 163, 184, 0.45);
            background: rgba(255, 255, 255, 0.94);
            padding: 0.9rem 1rem;
            font-size: 0.95rem;
            color: #0f172a;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .bid-form-input:focus {
            outline: none;
            border-color: rgba(16, 185, 129, 0.6);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.18);
        }

        .dark .bid-form-input {
            background: rgba(15, 23, 42, 0.85);
            border-color: rgba(148, 163, 184, 0.35);
            color: #f8fafc;
        }

        .dark .bid-form-input:focus {
            border-color: rgba(45, 212, 191, 0.9);
            box-shadow: 0 0 0 4px rgba(45, 212, 191, 0.22);
        }
    </style>
@endpush

@section('content')
    <div class="bg-slate-50 dark:bg-slate-950/85 min-h-full">
        <div class="container mx-auto max-w-4xl px-5 md:px-8 py-12 space-y-8" dir="rtl">
            <section class="bid-shell relative">
                <div class="absolute inset-0 pointer-events-none">
                    <div class="absolute -top-28 -left-16 w-64 h-64 rounded-full bg-emerald-400/20 blur-3xl"></div>
                    <div class="absolute -bottom-32 -right-20 w-72 h-72 rounded-full bg-sky-400/20 blur-3xl"></div>
                </div>
                <div class="relative z-10 px-6 md:px-10 py-8 space-y-6">
                    <span class="inline-flex items-center gap-2 rounded-full bg-emerald-500/15 px-4 py-2 text-xs font-semibold text-emerald-700 dark:text-emerald-200 border border-emerald-500/30">
                        <i class="fas fa-hand-pointer text-sm"></i>
                        <span>ثبت پیشنهاد خرید سهام</span>
                    </span>
                    <div class="space-y-3">
                        <h1 class="font-vazirmatn text-3xl font-extrabold text-slate-900 dark:text-white leading-tight">
                            حراج {{ $stockName }}
                        </h1>
                        <p class="text-sm md:text-base text-slate-600 dark:text-slate-300 leading-relaxed">
                            با انتخاب تعداد سهام و قیمت پیشنهادی خود در این حراج شرکت کنید. مبلغ پیشنهادی متناسب با قیمت و تعداد سهم، در کیف پول شما به حالت رزرو در می‌آید.
                        </p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200/70 dark:border-slate-700/70 bg-slate-50/70 dark:bg-slate-900/70 px-4 py-3">
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">قیمت پایه هر سهم</span>
                            <p class="mt-2 text-xl font-bold text-slate-900 dark:text-white">{{ $basePrice }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200/70 dark:border-slate-700/70 bg-slate-50/70 dark:bg-slate-900/70 px-4 py-3">
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">سهام قابل عرضه</span>
                            <p class="mt-2 text-xl font-bold text-slate-900 dark:text-white">{{ $availableShares }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200/70 dark:border-slate-700/70 bg-slate-50/70 dark:bg-slate-900/70 px-4 py-3">
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">بالاترین پیشنهاد فعلی</span>
                            <p class="mt-2 text-xl font-bold text-slate-900 dark:text-white">{{ $highestBid }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200/70 dark:border-slate-700/70 bg-white dark:bg-slate-900/90 shadow-xl px-6 md:px-10 py-10">
                <form method="POST" action="{{ route('auction.bid', $auction) }}" class="space-y-8">
                    @csrf
                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="space-y-2">
                            <label for="shares_count" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">تعداد سهام موردنظر</label>
                            <input
                                id="shares_count"
                                type="number"
                                min="1"
                                name="shares_count"
                                value="{{ old('shares_count') }}"
                                class="bid-form-input @error('shares_count') border-red-400 @enderror"
                                required
                                placeholder="مثلاً ۱۵۰ سهم">
                            @error('shares_count')
                                <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="bid_price" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">قیمت پیشنهادی هر سهم (ریال)</label>
                            <input
                                id="bid_price"
                                type="number"
                                min="1"
                                name="bid_price"
                                value="{{ old('bid_price', $auction->base_price) }}"
                                class="bid-form-input @error('bid_price') border-red-400 @enderror"
                                required
                                placeholder="مثلاً ۱۲۰٬۰۰۰ ریال">
                            @error('bid_price')
                                <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200/60 dark:border-slate-700/60 bg-slate-50/70 dark:bg-slate-900/70 px-5 py-4 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                        <strong class="text-slate-700 dark:text-slate-200">یادآوری:</strong>
                        قبل از ثبت پیشنهاد، از موجودی کافی در کیف پول خود اطمینان حاصل کنید. در صورت برنده شدن، مبلغ بر اساس قیمت پیشنهادی نهایی از موجودی شما کسر می‌شود.
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-earth-green to-emerald-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-500/25 transition hover:from-emerald-500 hover:to-earth-green focus:outline-none focus:ring-4 focus:ring-emerald-400/30">
                            <i class="fas fa-paper-plane text-sm"></i>
                            <span>ثبت پیشنهاد</span>
                        </button>

                        <a href="{{ route('auction.index') }}"
                           class="inline-flex items-center gap-2 rounded-full border border-slate-300 px-6 py-3 text-sm font-semibold text-slate-600 hover:border-slate-400 hover:text-slate-800 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-400">
                            <i class="fas fa-arrow-right text-xs"></i>
                            <span>بازگشت</span>
                        </a>
                    </div>
                </form>
            </section>
        </div>
    </div>
@endsection
