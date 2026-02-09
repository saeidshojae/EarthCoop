@extends('layouts.unified')

@section('title', 'ثبت اطلاعات پایه سهام - ' . config('app.name', 'EarthCoop'))

@push('styles')
    <style>
        .stock-create-shell {
            border-radius: 1.75rem;
            border: 1px solid rgba(226, 232, 240, 0.65);
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.14);
            overflow: hidden;
        }

        .dark .stock-create-shell {
            background: rgba(15, 23, 42, 0.92);
            border-color: rgba(148, 163, 184, 0.35);
            box-shadow: 0 24px 70px rgba(7, 11, 19, 0.55);
        }

        .stock-form-input {
            width: 100%;
            border-radius: 1.1rem;
            border: 1px solid rgba(148, 163, 184, 0.45);
            background: rgba(255, 255, 255, 0.94);
            padding: 0.9rem 1rem;
            font-size: 0.95rem;
            color: #0f172a;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .stock-form-input:focus {
            outline: none;
            border-color: rgba(16, 185, 129, 0.6);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.18);
        }

        .dark .stock-form-input {
            background: rgba(15, 23, 42, 0.85);
            border-color: rgba(148, 163, 184, 0.35);
            color: #f8fafc;
        }

        .dark .stock-form-input:focus {
            border-color: rgba(45, 212, 191, 0.9);
            box-shadow: 0 0 0 4px rgba(45, 212, 191, 0.22);
        }
    </style>
@endpush

@section('content')
    <div class="bg-slate-50 dark:bg-slate-950/85 min-h-full">
        <div class="container mx-auto max-w-4xl px-5 md:px-8 py-12 space-y-8" dir="rtl">
            <section class="stock-create-shell relative">
                <div class="absolute inset-0 pointer-events-none">
                    <div class="absolute -top-28 -left-16 w-64 h-64 rounded-full bg-emerald-400/20 blur-3xl"></div>
                    <div class="absolute -bottom-32 -right-20 w-72 h-72 rounded-full bg-sky-400/20 blur-3xl"></div>
                </div>
                <div class="relative z-10 px-6 md:px-10 py-10 space-y-6">
                    <span class="inline-flex items-center gap-2 rounded-full bg-emerald-500/15 px-4 py-2 text-xs font-semibold text-emerald-700 dark:text-emerald-200 border border-emerald-500/30">
                        <i class="fas fa-database text-sm"></i>
                        <span>ثبت اطلاعات پایه سهام</span>
                    </span>
                    <div class="space-y-3">
                        <h1 class="font-vazirmatn text-3xl md:text-4xl font-extrabold text-slate-900 dark:text-white leading-tight">
                            تنظیم ساختار سرمایه برای EarthCoop
                        </h1>
                        <p class="text-sm md:text-base text-slate-600 dark:text-slate-300 leading-relaxed max-w-3xl">
                            این اطلاعات مبنای نمایش در داشبورد بازار و حراج‌ها است. اعداد را با دقت و به واحد <strong>ریال</strong> وارد کنید تا تحلیل‌ها و گزارش‌ها دقیق بمانند.
                        </p>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200/70 dark:border-slate-700/70 bg-white dark:bg-slate-900/90 shadow-xl px-6 md:px-10 py-10">
                <form method="POST" action="{{ route('stock.store') }}" class="space-y-8">
                    @csrf

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="space-y-2">
                            <label for="startup_valuation" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">
                                ارزش پایه استارتاپ (ریال)
                            </label>
                            <input
                                id="startup_valuation"
                                type="number"
                                min="0"
                                name="startup_valuation"
                                value="{{ old('startup_valuation') }}"
                                class="stock-form-input @error('startup_valuation') border-red-400 @enderror"
                                required
                                placeholder="مثلاً ۲۵٬۰۰۰٬۰۰۰٬۰۰۰ ریال">
                            @error('startup_valuation')
                                <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="total_shares" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">
                                تعداد کل سهام
                            </label>
                            <input
                                id="total_shares"
                                type="number"
                                min="1"
                                name="total_shares"
                                value="{{ old('total_shares') }}"
                                class="stock-form-input @error('total_shares') border-red-400 @enderror"
                                required
                                placeholder="مثلاً ۱٬۰۰۰٬۰۰۰ سهم">
                            @error('total_shares')
                                <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="space-y-2">
                            <label for="available_shares" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">
                                تعداد سهام قابل عرضه (اختیاری)
                            </label>
                            <input
                                id="available_shares"
                                type="number"
                                min="0"
                                name="available_shares"
                                value="{{ old('available_shares') }}"
                                class="stock-form-input @error('available_shares') border-red-400 @enderror"
                                placeholder="مثلاً ۶۰۰٬۰۰۰ سهم">
                            @error('available_shares')
                                <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="base_share_price" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">
                                ارزش پایه هر سهم (ریال)
                            </label>
                            <input
                                id="base_share_price"
                                type="number"
                                min="0"
                                name="base_share_price"
                                value="{{ old('base_share_price') }}"
                                class="stock-form-input @error('base_share_price') border-red-400 @enderror"
                                required
                                placeholder="مثلاً ۱۲۰٬۰۰۰ ریال">
                            @error('base_share_price')
                                <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label for="info" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">
                            توضیحات تکمیلی (اختیاری)
                        </label>
                        <textarea
                            id="info"
                            name="info"
                            rows="4"
                            class="stock-form-input resize-none @error('info') border-red-400 @enderror"
                            placeholder="می‌توانید درباره اهداف افزایش سرمایه، کاربردها یا نکات مهم توضیح دهید."
                        >{{ old('info') }}</textarea>
                        @error('info')
                            <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="rounded-2xl border border-slate-200/60 dark:border-slate-700/60 bg-slate-50/70 dark:bg-slate-900/70 px-5 py-4 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                        <strong class="text-slate-700 dark:text-slate-200">یادآوری:</strong>
                        پس از ثبت این اطلاعات، کارت‌های داشبورد و حراج‌ها بر اساس آن نمایش داده می‌شوند. در صورت تغییر در برنامه سرمایه‌گذاری می‌توانید به همین فرم بازگردید و اطلاعات را بروزرسانی کنید.
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-earth-green to-emerald-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-500/25 transition hover:from-emerald-500 hover:to-earth-green focus:outline-none focus:ring-4 focus:ring-emerald-400/30">
                            <i class="fas fa-check-circle text-sm"></i>
                            <span>ثبت اطلاعات</span>
                        </button>

                        <a href="{{ route('stock.index') }}"
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
