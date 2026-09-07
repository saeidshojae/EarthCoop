@extends('layouts.admin')

@section('title', ($stock ? 'ویرایش' : 'ثبت') . ' اطلاعات پایه سهام - ' . config('app.name', 'EarthCoop'))
@section('page-title', ($stock ? 'ویرایش' : 'ثبت') . ' اطلاعات پایه سهام')
@section('page-description', 'ارزش‌گذاری و پارامترهای پایه سهام EarthCoop بر مبنای بهار و گل')

@php
    $valuationBahar = old(
        'startup_valuation_bahar',
        $stock && $stock->startup_valuation_gol !== null
            ? rtrim(rtrim(number_format(((int) $stock->startup_valuation_gol) / 100, 2, '.', ''), '0'), '.')
            : ''
    );
    $baseShareGol = $stock && $stock->base_share_price_gol !== null ? (int) $stock->base_share_price_gol : null;
@endphp

@section('content')
<div class="mx-auto max-w-4xl space-y-6" dir="rtl">
    <div class="rounded-3xl border border-emerald-200/70 bg-white p-6 shadow-sm dark:border-emerald-900/50 dark:bg-slate-900 md:p-8">
        <div class="mb-8 space-y-3">
            <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-4 py-2 text-sm font-bold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                <i class="fas fa-scale-balanced"></i>
                قرارداد canonical سهام EarthCoop
            </div>
            <h1 class="text-2xl font-black text-slate-900 dark:text-white">ارزش‌گذاری EarthCoop (بهار)</h1>
            <p class="leading-7 text-slate-600 dark:text-slate-300">
                ارزش‌گذاری فقط به بهار ثبت می‌شود و سامانه آن را بدون اعشار شناور به گل تبدیل می‌کند. قیمت پایه هر سهم به‌صورت خودکار از ارزش‌گذاری کل و تعداد سهام محاسبه می‌شود.
            </p>
        </div>

        <form action="{{ route('admin.stock.store') }}" method="POST" class="space-y-6">
            @csrf

            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm leading-7 text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200">
                <strong>مبنای حسابداری:</strong> هر ۱ بهار = ۱۰۰ گل. هیچ قیمت ریالی در این فرم ثبت نمی‌شود؛ تبدیل ریالی فقط در زمان تسویه خارجی و بر اساس quote معتبر انجام می‌شود.
            </div>

            <div>
                <label for="startup_valuation_bahar" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">
                    ارزش‌گذاری EarthCoop (بهار) <span class="text-red-500">*</span>
                </label>
                <input
                    id="startup_valuation_bahar"
                    name="startup_valuation_bahar"
                    type="text"
                    inputmode="decimal"
                    value="{{ $valuationBahar }}"
                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    placeholder="مثلاً 12000000"
                    required
                >
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">حداکثر دو رقم اعشار پذیرفته می‌شود تا تبدیل به گل همیشه دقیق باشد.</p>
                @error('startup_valuation_bahar')
                    <p class="mt-2 text-sm font-semibold text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="total_shares" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">تعداد کل سهام <span class="text-red-500">*</span></label>
                    <input id="total_shares" name="total_shares" type="number" min="1" step="1" value="{{ old('total_shares', $stock->total_shares ?? '') }}" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-950 dark:text-white" required>
                    @error('total_shares')<p class="mt-2 text-sm font-semibold text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="available_shares" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">سهام خزانه قابل عرضه</label>
                    <input id="available_shares" name="available_shares" type="number" min="0" step="1" value="{{ old('available_shares', $stock->available_shares ?? '') }}" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">این موجودی، سقف سیاست عرضه اولیه ۱۰٪ را دور نمی‌زند؛ policy حراج جداگانه آن سقف را enforce می‌کند.</p>
                    @error('available_shares')<p class="mt-2 text-sm font-semibold text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950/60">
                <div class="text-xs font-bold uppercase tracking-wide text-slate-500">قیمت پایه هر سهم به‌صورت خودکار</div>
                <div class="mt-2 text-lg font-black text-slate-900 dark:text-white">
                    @if($baseShareGol !== null)
                        {{ number_format($baseShareGol) }} گل
                        <span class="text-sm font-medium text-slate-500">({{ rtrim(rtrim(number_format($baseShareGol / 100, 2, '.', ''), '0'), '.') }} بهار)</span>
                    @else
                        پس از ذخیره ارزش‌گذاری و تعداد سهام محاسبه می‌شود.
                    @endif
                </div>
            </div>

            <div>
                <label for="info" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">توضیحات تکمیلی</label>
                <textarea id="info" name="info" rows="6" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="مبنای ارزش‌گذاری، تاریخ ارزیابی و توضیحات تکمیلی...">{{ old('info', $stock->info ?? '') }}</textarea>
                @error('info')<p class="mt-2 text-sm font-semibold text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 dark:border-slate-700 sm:flex-row sm:justify-end">
                <a href="{{ route('admin.stock.index') }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-100 px-5 py-3 font-bold text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200">انصراف</a>
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-6 py-3 font-black text-white hover:bg-emerald-700">
                    <i class="fas fa-save"></i>
                    ذخیره اطلاعات canonical
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
