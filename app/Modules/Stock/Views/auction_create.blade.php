@extends('layouts.unified')

@php
    $stockName = trim($stock->info ?? $stock->name ?? '') ?: 'سهام EarthCoop';
    $valuation = isset($stock->startup_valuation) ? number_format($stock->startup_valuation * 10) . ' ریال' : '—';
    $baseSharePrice = isset($stock->base_share_price) ? number_format($stock->base_share_price * 10) . ' ریال' : '—';
    $availableShares = isset($stock->available_shares) ? number_format($stock->available_shares) : '—';
@endphp

@section('title', 'ایجاد حراج جدید برای ' . $stockName . ' - ' . config('app.name', 'EarthCoop'))

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
    <style>
        .auction-shell {
            border-radius: 1.75rem;
            border: 1px solid rgba(226, 232, 240, 0.65);
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 28px 80px rgba(15, 23, 42, 0.14);
            overflow: hidden;
        }

        .dark .auction-shell {
            background: rgba(15, 23, 42, 0.92);
            border-color: rgba(148, 163, 184, 0.35);
            box-shadow: 0 24px 70px rgba(7, 11, 19, 0.55);
        }

        .auction-form-input {
            width: 100%;
            border-radius: 1.1rem;
            border: 1px solid rgba(148, 163, 184, 0.45);
            background: rgba(255, 255, 255, 0.94);
            padding: 0.9rem 1rem;
            font-size: 0.95rem;
            color: #0f172a;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .auction-form-input:focus {
            outline: none;
            border-color: rgba(16, 185, 129, 0.6);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.18);
        }

        .dark .auction-form-input {
            background: rgba(15, 23, 42, 0.85);
            border-color: rgba(148, 163, 184, 0.35);
            color: #f8fafc;
        }

        .dark .auction-form-input:focus {
            border-color: rgba(45, 212, 191, 0.9);
            box-shadow: 0 0 0 4px rgba(45, 212, 191, 0.22);
        }

        .jalali-datetime {
            cursor: pointer;
        }
    </style>
@endpush

@section('content')
    <div class="bg-slate-50 dark:bg-slate-950/80 min-h-full">
        <div class="container mx-auto max-w-5xl px-5 md:px-10 py-12 space-y-10" dir="rtl">
            <section class="relative auction-shell">
                <div class="absolute inset-0 pointer-events-none">
                    <div class="absolute -top-32 -left-20 w-72 h-72 rounded-full bg-emerald-400/20 blur-3xl"></div>
                    <div class="absolute -bottom-32 -right-24 w-80 h-80 rounded-full bg-sky-400/20 blur-3xl"></div>
                </div>
                <div class="relative z-10 px-6 md:px-10 py-10 space-y-6">
                    <span class="inline-flex items-center gap-2 rounded-full bg-emerald-500/15 px-4 py-2 text-xs font-semibold text-emerald-700 dark:text-emerald-200 border border-emerald-500/30">
                        <i class="fas fa-gavel text-sm"></i>
                        <span>ایجاد حراج تازه برای سرمایه‌گذاران</span>
                    </span>
                    <div class="space-y-3">
                        <h1 class="font-vazirmatn text-3xl md:text-4xl font-extrabold text-slate-900 dark:text-white leading-tight">
                            حراج جدید سهام {{ $stockName }}
                        </h1>
                        <p class="text-sm md:text-base leading-relaxed text-slate-600 dark:text-slate-300 max-w-3xl">
                            با تعیین زمان‌بندی مناسب و مشخص کردن تعداد سهامی که قصد عرضه دارید، روند جذب سرمایه را آغاز کنید. جزئیات زیر به سرمایه‌گذاران کمک می‌کند تصمیم آگاهانه بگیرند.
                        </p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200/70 dark:border-slate-700/70 bg-slate-50/60 dark:bg-slate-900/70 px-5 py-4 shadow-sm">
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">ارزش پایه استارتاپ</span>
                            <p class="mt-2 text-xl font-bold text-slate-900 dark:text-white">{{ $valuation }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200/70 dark:border-slate-700/70 bg-slate-50/60 dark:bg-slate-900/70 px-5 py-4 shadow-sm">
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">قیمت پایه هر سهم</span>
                            <p class="mt-2 text-xl font-bold text-slate-900 dark:text-white">{{ $baseSharePrice }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200/70 dark:border-slate-700/70 bg-slate-50/60 dark:bg-slate-900/70 px-5 py-4 shadow-sm">
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">سهام قابل عرضه</span>
                            <p class="mt-2 text-xl font-bold text-slate-900 dark:text-white">{{ $availableShares }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200/70 dark:border-slate-700/70 bg-white dark:bg-slate-900/90 shadow-xl px-6 md:px-10 py-10">
                <form method="POST" action="{{ route('auction.store') }}" class="space-y-8">
                    @csrf

                    <input type="hidden" name="stock_id" value="{{ $stock->id }}">

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="space-y-2">
                            <label for="shares_count" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">تعداد سهام قابل عرضه</label>
                            <input
                                id="shares_count"
                                type="number"
                                min="1"
                                name="shares_count"
                                value="{{ old('shares_count') }}"
                                class="auction-form-input @error('shares_count') border-red-400 @enderror"
                                required
                                placeholder="مثلاً ۵۰۰ سهم">
                            @error('shares_count')
                                <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="base_price" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">قیمت پایه هر سهم (ریال)</label>
                            <input
                                id="base_price"
                                type="number"
                                min="0"
                                name="base_price"
                                value="{{ old('base_price', isset($stock->base_share_price) ? $stock->base_share_price * 10 : null) }}"
                                class="auction-form-input @error('base_price') border-red-400 @enderror"
                                required
                                placeholder="مثلاً ۱۲۰٬۰۰۰ ریال">
                            @error('base_price')
                                <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="space-y-2">
                            <label for="start_time_visible" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">زمان شروع حراج</label>
                            <div class="relative">
                                <input
                                    id="start_time_visible"
                                    type="text"
                                    name="start_time_visible"
                                    value="{{ old('start_time_visible', $startVisible) }}"
                                    class="auction-form-input jalali-datetime @error('start_time') border-red-400 @enderror"
                                    placeholder="مثلاً 1404/09/18 14:30"
                                    autocomplete="off"
                                >
                                <input type="hidden" name="start_time" value="{{ old('start_time', $startIso) }}">
                            </div>
                            @error('start_time')
                                <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="end_time_visible" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">زمان پایان حراج</label>
                            <div class="relative">
                                <input
                                    id="end_time_visible"
                                    type="text"
                                    name="end_time_visible"
                                    value="{{ old('end_time_visible', $endVisible) }}"
                                    class="auction-form-input jalali-datetime @error('end_time') border-red-400 @enderror"
                                    placeholder="مثلاً 1404/09/20 18:00"
                                    autocomplete="off"
                                >
                                <input type="hidden" name="end_time" value="{{ old('end_time', $endIso) }}">
                            </div>
                            @error('end_time')
                                <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label for="info" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">توضیحات تکمیلی</label>
                        <textarea
                            id="info"
                            name="info"
                            rows="4"
                            class="auction-form-input resize-none @error('info') border-red-400 @enderror"
                            placeholder="خلاصه‌ای از اهداف حراج، شرایط پرداخت یا نکات مهم برای سرمایه‌گذاران."
                        >{{ old('info') }}</textarea>
                        @error('info')
                            <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="rounded-2xl border border-amber-200/60 dark:border-amber-500/30 bg-amber-50/70 dark:bg-amber-500/10 px-5 py-4 text-sm text-amber-700 dark:text-amber-200">
                        <span class="font-semibold">نکته:</span>
                        <span class="leading-relaxed">
                            پس از ثبت حراج، سرمایه‌گذاران می‌توانند پیشنهادهای خود را ارسال کنند. در صورت نیاز به ویرایش، همیشه می‌توانید به بخش مدیریت حراج‌ها بازگردید.
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-full bg-gradient-to-r	from-earth-green to-emerald-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-500/25 transition hover:from-emerald-500 hover:to-earth-green focus:outline-none focus:ring-4 focus:ring-emerald-400/30">
                            <i class="fas fa-check-circle text-sm"></i>
                            <span>ایجاد حراج</span>
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

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/persian-date@1.0.6/dist/persian-date.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
    <script>
        (function () {
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof window.jQuery === 'undefined' || typeof $.fn.persianDatepicker === 'undefined') {
                    console.warn('Persian datepicker could not be initialised because dependencies are missing.');
                    return;
                }

                $('.jalali-datetime').each(function () {
                    const $input = $(this);
                    const altField = $input.data('alt');
                    const $hidden = $('input[name="' + altField + '"]');

                    if (!$hidden.length) {
                        return;
                    }

                    $input.persianDatepicker({
                        format: 'YYYY/MM/DD HH:mm',
                        initialValue: !!$input.val(),
                        timePicker: {
                            enabled: true,
                            meridiem: { enabled: false }
                        },
                        calendar: { persian: { locale: 'fa' } },
                        autoClose: true,
                        onSelect: function () {
                            const state = $input.data('datepicker')?.getState?.();
                            const selected = state?.selected?.[0];

                            if (!selected) {
                                return;
                            }

                            try {
                                const pd = new persianDate(selected.moment);
                                const date = pd.toDate();
                                const iso = date.getFullYear() + '-' +
                                    String(date.getMonth() + 1).padStart(2, '0') + '-' +
                                    String(date.getDate()).padStart(2, '0') + 'T' +
                                    String(date.getHours()).padStart(2, '0') + ':' +
                                    String(date.getMinutes()).padStart(2, '0');

                                $hidden.val(iso);
                            } catch (error) {
                                console.error('Failed to convert Jalali date', error);
                            }
                        }
                    });
                });
            });
        })();
    </script>
@endpush
