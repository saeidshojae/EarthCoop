@extends('layouts.unified')

@php
    $auctionTypeLabels = [
        'single_winner' => 'حراج تک برنده',
        'uniform_price' => 'حراج قیمت یکسان',
        'pay_as_bid' => 'پرداخت به قیمت پیشنهادی',
    ];
    $auctionType = $auctionTypeLabels[$auction->type] ?? 'حراج سهام';
    $bidTotal = $bid->price * $bid->quantity;
@endphp

@section('title', 'ویرایش پیشنهاد #' . $bid->id . ' - ' . config('app.name', 'EarthCoop'))

@section('content')
    <div class="bg-slate-50 dark:bg-slate-950/80 min-h-full">
        <div class="container mx-auto max-w-4xl px-5 md:px-8 py-12 space-y-8" dir="rtl">
            <section class="rounded-3xl border border-emerald-200/60 dark:border-emerald-500/30 bg-white dark:bg-slate-900/90 shadow-xl overflow-hidden">
                <div class="relative z-10 px-6 md:px-10 py-8 space-y-6">
                    <span class="inline-flex items-center gap-2 rounded-full bg-emerald-500/15 px-4 py-2 text-xs font-semibold text-emerald-700 dark:text-emerald-200 border border-emerald-500/30">
                        <i class="fas fa-pen-to-square text-sm"></i>
                        <span>ویرایش پیشنهاد ثبت شده</span>
                    </span>
                    <div class="space-y-2">
                        <h1 class="font-vazirmatn text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white">
                            پیشنهاد شماره {{ $bid->id }} | {{ $auctionType }}
                        </h1>
                        <p class="text-sm md:text-base text-slate-600 dark:text-slate-300 leading-relaxed">
                            می‌توانید قیمت و تعداد سهم‌های پیشنهادی خود را اصلاح کنید. پس از ذخیره، مبلغ معادل در کیف پول شما رزرو یا آزاد می‌شود.
                        </p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200/70 dark:border-slate-700/70 bg-slate-50/70 dark:bg-slate-900/70 px-4 py-3">
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">قیمت پیشنهادی فعلی</span>
                            <p class="mt-2 text-xl font-bold text-slate-900 dark:text-white">{{ number_format($bid->price) }} ریال</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200/70 dark:border-slate-700/70 bg-slate-50/70 dark:bg-slate-900/70 px-4 py-3">
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">تعداد سهم</span>
                            <p class="mt-2 text-xl font-bold text-slate-900 dark:text-white">{{ number_format($bid->quantity) }}</p>
                        </div>
                        <div class="rounded-2xl border border-emerald-200/70 dark:border-emerald-500/40 bg-emerald-50/60 dark:bg-emerald-500/10 px-4 py-3">
                            <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-300">ارزش کل پیشنهاد</span>
                            <p class="mt-2 text-xl font-bold text-emerald-600 dark:text-emerald-300">{{ number_format($bidTotal) }} ریال</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200/70 dark:border-slate-700/70 bg-white dark:bg-slate-900/90 shadow-xl px-6 md:px-10 py-10">
                <form method="POST" action="{{ route('bid.update', $bid) }}" class="space-y-8">
                    @csrf
                    @method('PUT')

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="space-y-2">
                            <label for="price" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">قیمت پیشنهادی هر سهم (ریال)</label>
                            <input
                                id="price"
                                type="number"
                                min="1"
                                name="price"
                                value="{{ old('price', $bid->price) }}"
                                class="auction-form-input @error('price') border-red-400 @enderror"
                                required
                                placeholder="مثلاً ۱۲۰٬۰۰۰ ریال">
                            @error('price')
                                <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="quantity" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">تعداد سهم</label>
                            <input
                                id="quantity"
                                type="number"
                                min="1"
                                name="quantity"
                                value="{{ old('quantity', $bid->quantity) }}"
                                class="auction-form-input @error('quantity') border-red-400 @enderror"
                                required
                                placeholder="مثلاً ۲۰۰ سهم">
                            @error('quantity')
                                <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200/60 dark:border-slate-700/60 bg-slate-50/70 dark:bg-slate-900/70 px-5 py-4 text-sm text-slate-600 dark:text-slate-300">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-amber-500/20 text-amber-600 dark:text-amber-300">
                                <i class="fas fa-lightbulb text-sm"></i>
                            </span>
                            <div>
                                <p class="font-semibold">یادآوری</p>
                                <p class="leading-relaxed">
                                    اگر قیمت یا تعداد را افزایش دهید، مبلغ مورد نیاز از کیف پول شما رزرو می‌شود. در صورت کاهش، مبلغ آزاد خواهد شد.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-earth-green to-emerald-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-500/25 transition hover:from-emerald-500 hover:to-earth-green focus:outline-none focus:ring-4 focus:ring-emerald-400/30">
                            <i class="fas fa-save text-sm"></i>
                            <span>ذخیره تغییرات</span>
                        </button>

                        <a href="{{ route('auction.show', $auction) }}"
                           class="inline-flex items-center gap-2 rounded-full border border-slate-300 px-6 py-3 text-sm font-semibold	text-slate-600 hover:border-slate-400 hover:text-slate-800 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-400">
                            <i class="fas fa-arrow-right text-xs"></i>
                            <span>بازگشت به صفحه حراج</span>
                        </a>
                    </div>
                </form>
            </section>
        </div>
    </div>
@endsection
