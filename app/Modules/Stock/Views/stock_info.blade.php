@extends('layouts.unified')

@php
    $hasStock = (bool) $stock;
    $valuation = $hasStock ? number_format($stock->startup_valuation) . ' ریال' : '—';
    $totalShares = $hasStock ? number_format($stock->total_shares) : '—';
    $basePrice = $hasStock ? number_format($stock->base_share_price) . ' ریال' : '—';
    $availableShares = $hasStock && isset($stock->available_shares) ? number_format($stock->available_shares) : '—';
@endphp

@section('title', 'اطلاعات پایه سهام - ' . config('app.name', 'EarthCoop'))

@section('content')
    <div class="bg-slate-50 dark:bg-slate-950/80 min-h-full">
        <div class="container mx-auto max-w-5xl px-5 md:px-10 py-12 space-y-8" dir="rtl">
            <section class="relative overflow-hidden rounded-3xl border border-emerald-200/60 dark:border-emerald-500/30 bg-white dark:bg-slate-900/90 shadow-xl">
                <div class="absolute inset-0 pointer-events-none">
                    <div class="absolute -top-28 -left-16 w-64 h-64 rounded-full bg-emerald-400/20 blur-3xl"></div>
                    <div class="absolute -bottom-32 -right-20 w-72 h-72 rounded-full bg-sky-400/20 blur-3xl"></div>
                </div>
                <div class="relative z-10 px-6 md:px-10 py-10 space-y-6">
                    <span class="inline-flex items-center gap-2 rounded-full bg-emerald-500/15 px-4 py-2 text-xs font-semibold text-emerald-700 dark:text-emerald-200 border border-emerald-500/30">
                        <i class="fas fa-chart-pie text-sm"></i>
                        <span>پروفایل مالی سهام</span>
                    </span>
                    <div class="space-y-3">
                        <h1 class="font-vazirmatn text-3xl md:text-4xl font-extrabold text-slate-900 dark:text-white leading-tight">
                            وضعیت پایه سهام EarthCoop
                        </h1>
                        <p class="text-sm md:text-base text-slate-600 dark:text-slate-300 leading-relaxed max-w-3xl">
                            اطلاعات زیر تصویر فعلی ساختار سرمایه و ارزش‌گذاری سهام را نشان می‌دهد. در صورت نیاز به بروزرسانی یا ثبت اولیه، از دکمه پایین استفاده کنید.
                        </p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-4">
                        <div class="rounded-2xl border border-slate-200/70 dark:border-slate-700/70 bg-slate-50/70 dark:bg-slate-900/70 px-4 py-4 shadow-sm">
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">ارزش پایه استارتاپ</span>
                            <p class="mt-2 text-xl font-bold text-slate-900 dark:text-white">{{ $valuation }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200/70 dark:border-slate-700/70 bg-slate-50/70 dark:bg-slate-900/70 px-4 py-4 shadow-sm">
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">تعداد کل سهام</span>
                            <p class="mt-2 text-xl font-bold text-slate-900 dark:text-white">{{ $totalShares }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200/70 dark:border-slate-700/70 bg-slate-50/70 dark:bg-slate-900/70 px-4 py-4 shadow-sm">
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">قیمت پایه هر سهم</span>
                            <p class="mt-2 text-xl font-bold text-slate-900 dark:text-white">{{ $basePrice }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200/70 dark:border-slate-700/70 bg-slate-50/70 dark:bg-slate-900/70 px-4 py-4 shadow-sm">
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">سهام قابل عرضه</span>
                            <p class="mt-2 text-xl font-bold text-slate-900 dark:text-white">{{ $availableShares }}</p>
                        </div>
                    </div>

                    @if($hasStock && $stock->info)
                        <div class="rounded-2xl border border-slate-200/70 dark:border-slate-700/70 bg-slate-50/70 dark:bg-slate-900/70 px-5 py-4 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                            {!! nl2br(e($stock->info)) !!}
                        </div>
                    @else
                        <div class="rounded-2xl border border-amber-200/60 dark:border-amber-500/30 bg-amber-50/70 dark:bg-amber-500/10 px-5 py-4 text-sm text-amber-700 dark:text-amber-200 leading-relaxed">
                            هنوز توضیحی برای این سهام ثبت نشده است. افزودن جزئیات به سرمایه‌گذاران کمک می‌کند تصویر بهتری از اهداف طرح داشته باشند.
                        </div>
                    @endif

                    <div class="flex flex-wrap gap-3 pt-2">
                        <a href="{{ route('stock.create') }}"
                           class="inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-earth-green to-emerald-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-500/25 transition hover:from-emerald-500 hover:to-earth-green focus:outline-none focus:ring-4 focus:ring-emerald-400/30">
                            <i class="fas fa-pen-to-square text-sm"></i>
                            <span>{{ $hasStock ? 'ویرایش اطلاعات پایه' : 'ثبت اطلاعات پایه سهام' }}</span>
                        </a>
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
