@extends('layouts.admin')

@section('title', (isset($auction) ? 'ویرایش' : 'ایجاد') . ' حراج سهام - ' . config('app.name', 'EarthCoop'))
@section('page-title', (isset($auction) ? 'ویرایش' : 'ایجاد') . ' حراج سهام')
@section('page-description', (isset($auction) ? 'ویرایش اطلاعات' : 'ایجاد') . ' حراج جدید سهام')

@php
    use Carbon\Carbon;
    use Morilog\Jalali\Jalalian;

    $isEdit = isset($auction);
    $pageTitle = $isEdit ? 'ویرایش حراج سهام' : 'ایجاد حراج جدید سهام';
    $formAction = $isEdit ? route('admin.auction.update', $auction) : route('admin.auction.store');

    $startIso = old('start_time', isset($auction) && $auction->start_time ? $auction->start_time->format('Y-m-d\TH:i') : '');
    $endIso = old('end_time', isset($auction) && $auction->end_time ? $auction->end_time->format('Y-m-d\TH:i') : '');
    $closeIso = old('ends_at', isset($auction) && $auction->ends_at ? $auction->ends_at->format('Y-m-d\TH:i') : '');

    $formatVisible = static function (?string $iso): ?string {
        if (!$iso) {
            return null;
        }

        try {
            return Jalalian::fromCarbon(Carbon::parse($iso))->format('Y/m/d H:i');
        } catch (Throwable $e) {
            return null;
        }
    };

    $startVisible = old('start_time_visible', $formatVisible($startIso));
    $endVisible = old('end_time_visible', $formatVisible($endIso));
    $closeVisible = old('ends_at_visible', $formatVisible($closeIso));
@endphp

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
    <style>
        .auction-form-shell {
            border-radius: 1.75rem;
            border: 1px solid rgba(226,232,240,0.65);
            background: rgba(255,255,255,0.96);
            box-shadow: 0 28px 90px rgba(15,23,42,0.16);
            overflow: hidden;
        }

        .auction-form-shell::before {
            content: '';
            position: absolute;
            inset-block-start: -120px;
            inset-inline-end: -160px;
            width: 360px;
            height: 360px;
            background: radial-gradient(circle, rgba(59,130,246,0.18), transparent 60%);
            filter: blur(75px);
            pointer-events: none;
        }

        .dark .auction-form-shell {
            background: rgba(15,23,42,0.92);
            border-color: rgba(148,163,184,0.35);
            box-shadow: 0 24px 70px rgba(7,11,19,0.55);
        }

        .auction-form-input {
            width: 100%;
            border-radius: 1.1rem;
            border: 1px solid rgba(148,163,184,0.45);
            background: rgba(255,255,255,0.94);
            padding: 0.85rem 1rem;
            font-size: 0.95rem;
            color: #0f172a;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .auction-form-input:focus {
            outline: none;
            border-color: rgba(16,185,129,0.6);
            box-shadow: 0 0 0 4px rgba(16,185,129,0.18);
        }

        .dark .auction-form-input {
            background: rgba(15,23,42,0.85);
            border-color: rgba(148,163,184,0.35);
            color: #f8fafc;
        }

        .dark .auction-form-input:focus {
            border-color: rgba(45,212,191,0.9);
            box-shadow: 0 0 0 4px rgba(45,212,191,0.22);
        }

        .auction-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 999px;
            padding: 0.55rem 1.1rem;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
        }

        .jalali-datetime {
            cursor: pointer;
        }
    </style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <div class="bg-slate-50 dark:bg-slate-950/85" style="margin: -2rem; padding: 2rem; border-radius: 16px;">
        <div class="container mx-auto max-w-5xl space-y-10" dir="rtl">
            <div class="relative auction-form-shell">
                <div class="relative z-10 px-6 py-10 md:px-12 md:py-12 space-y-10">
                    <header class="space-y-4">
                        <span class="auction-badge {{ $isEdit ? 'bg-sky-100 text-sky-600 border border-sky-300 dark:bg-sky-500/15 dark:border-sky-500/40 dark:text-sky-200' : 'bg-emerald-100 text-emerald-600 border border-emerald-300 dark:bg-emerald-500/15 dark:border-emerald-500/40 dark:text-emerald-200' }}">
                            <i class="fas {{ $isEdit ? 'fa-pen-to-square' : 'fa-plus-circle' }} text-sm"></i>
                            <span>{{ $pageTitle }}</span>
                        </span>
                        <div class="space-y-3">
                            <h1 class="font-vazirmatn text-3xl font-extrabold text-slate-900 dark:text-white leading-tight">
                                {{ $isEdit ? 'ویرایش پارامترهای حراج در حال اجرا' : 'حراجی تازه برای عرضه سهام برنامه‌ریزی کنید' }}
                            </h1>
                            <p class="text-sm md:text-base leading-7 text-slate-600 dark:text-slate-300 max-w-3xl">
                                سهام قابل عرضه، حداقل قیمت و بازه زمانی حراج را مشخص کنید. این تنظیمات مستقیماً روی تجربه سرمایه‌گذاران تأثیر می‌گذارد، پس قبل از ذخیره، جزئیات را به دقت مرور کنید.
                            </p>
                        </div>
                    </header>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-2xl border border-emerald-200/70 bg-emerald-50/60 px-4 py-3 text-sm font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-200 dark:border-emerald-500/40">
                            <i class="fas fa-layer-group ms-2"></i>
                            سهام قابل عرضه: {{ number_format($stock->available_shares ?? 0) }}
                        </div>
                        <div class="rounded-2xl border border-sky-200/70 bg-sky-50/60 px-4 py-3 text-sm font-semibold text-sky-700 dark:bg-sky-500/10 dark:text-sky-200 dark:border-sky-500/40">
                            <i class="fas fa-coins ms-2"></i>
                            ارزش پایه هر سهم: {{ number_format($stock->base_share_price ?? 0) }} ریال
                        </div>
                        <div class="rounded-2xl border border-slate-200/70 bg-white/80 px-4 py-3 text-sm font-semibold text-slate-600 dark:bg-slate-900/70 dark:text-slate-200 dark:border-slate-600/40">
                            <i class="fas fa-clock ms-2"></i>
                            وضعیت حراج: {{ $isEdit ? __($auction->status === 'running' ? 'در حال اجرا' : 'برنامه‌ریزی‌شده') : 'در حال برنامه‌ریزی' }}
                        </div>
                    </div>

                    @if ($errors->any())
                        <div class="rounded-2xl border border-red-200 bg-red-50/60 px-4 py-3 text-sm font-semibold text-red-600 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-200">
                            <i class="fas fa-exclamation-circle ms-2"></i>
                            لطفاً خطاهای مشخص‌شده را بررسی و اصلاح کنید.
                        </div>
                    @endif

                    <form method="POST" action="{{ $formAction }}" class="space-y-8">
                        @csrf
                        @if($isEdit)
                            @method('PUT')
                        @endif

                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="space-y-2">
                                <label for="shares_count" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">تعداد سهام قابل عرضه در حراج</label>
                                <input
                                    id="shares_count"
                                    type="number"
                                    name="shares_count"
                                    min="1"
                                    class="auction-form-input @error('shares_count') border-red-400 focus:border-red-400 focus:shadow-none dark:border-red-400 @enderror"
                                    placeholder="مثلاً ۵۰٬۰۰۰ سهم"
                                    value="{{ old('shares_count', $auction->shares_count ?? '') }}"
                                    required
                                >
                                <p class="text-xs text-slate-500 dark:text-slate-400">این مقدار باید کمتر یا مساوی سهام قابل عرضه باقی‌مانده باشد.</p>
                                @error('shares_count')
                                    <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label for="base_price" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">قیمت پایه هر سهم (ریال)</label>
                                <input
                                    id="base_price"
                                    type="number"
                                    name="base_price"
                                    min="0"
                                    class="auction-form-input @error('base_price') border-red-400 focus:border-red-400 focus:shadow-none dark	border-red-400 @enderror"
                                    placeholder="مثلاً ۱۲۰٬۰۰۰ ریال"
                                    value="{{ old('base_price', $auction->base_price ?? ($stock->base_share_price ?? '')) }}"
                                    required
                                >
                                <p class="text-xs text-slate-500 dark:text-slate-400">می‌توانید قیمتی متفاوت از ارزش پایه سهام تعیین کنید.</p>
                                @error('base_price')
                                    <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="space-y-2">
                                <label for="type" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">نوع حراج</label>
                                <select
                                    id="type"
                                    name="type"
                                    class="auction-form-input @error('type') border-red-400 focus:border-red-400 focus:shadow-none dark	border-red-400 @enderror"
                                    required
                                >
                                    <option value="single_winner" {{ old('type', $auction->type ?? '') === 'single_winner' ? 'selected' : '' }}>تک برنده</option>
                                    <option value="uniform_price" {{ old('type', $auction->type ?? '') === 'uniform_price' ? 'selected' : '' }}>قیمت یکسان</option>
                                    <option value="pay_as_bid" {{ old('type', $auction->type ?? '') === 'pay_as_bid' ? 'selected' : '' }}>پرداخت به قیمت پیشنهادی</option>
                                </select>
                                @error('type')
                                    <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div class="space-y-2">
                                <label for="settlement_mode" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">نوع تسویه</label>
                                <select
                                    id="settlement_mode"
                                    name="settlement_mode"
                                    class="auction-form-input @error('settlement_mode') border-red-400 focus:border-red-400 focus:shadow-none dark	border-red-400 @enderror"
                                    required
                                >
                                    <option value="auto" {{ old('settlement_mode', $auction->settlement_mode ?? 'auto') === 'auto' ? 'selected' : '' }}>خودکار</option>
                                    <option value="manual" {{ old('settlement_mode', $auction->settlement_mode ?? '') === 'manual' ? 'selected' : '' }}>دستی (نیاز به تایید ادمین)</option>
                                </select>
                                <p class="text-xs text-slate-500 dark:text-slate-400">در حالت خودکار، تسویه بلافاصله پس از بستن حراج انجام می‌شود. در حالت دستی، نیاز به تایید ادمین است.</p>
                                @error('settlement_mode')
                                    <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label for="lot_size" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">حداکثر سهام در هر پیشنهاد (اندازه لات)</label>
                                <input
                                    id="lot_size"
                                    type="number"
                                    name="lot_size"
                                    min="1"
                                    class="auction-form-input @error('lot_size') border-red-400 focus:border-red-400 focus:shadow-none dark	border-red-400 @enderror"
                                    value="{{ old('lot_size', $auction->lot_size ?? 1) }}"
                                    required
                                >
                                @error('lot_size')
                                    <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid gap-6 md:grid-cols-3">
                            <div class="space-y-2">
                                <label for="start_time_visible" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">زمان شروع حراج</label>
                                <div class="relative">
                                    <input
                                        id="start_time_visible"
                                        type="text"
                                        name="start_time_visible"
                                        class="auction-form-input jalali-datetime @error('start_time') border-red-400 focus:border-red-400 focus:shadow-none dark	border-red-400 @enderror"
                                        placeholder="مثلاً 1404/09/18 14:30"
                                        value="{{ $startVisible }}"
                                        data-alt="start_time"
                                        required
                                    >
                                    <input type="hidden" name="start_time" value="{{ $startIso }}">
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
                                        class="auction-form-input jalali-datetime @error('end_time') border-red-400 focus:border-red-400 focus:shadow-none dark	border-red-400 @enderror"
                                        placeholder="مثلاً 1404/09/20 18:00"
                                        value="{{ $endVisible }}"
                                        data-alt="end_time"
                                        required
                                    >
                                    <input type="hidden" name="end_time" value="{{ $endIso }}">
                                </div>
                                @error('end_time')
                                    <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label for="ends_at_visible" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">زمان بسته شدن خودکار</label>
                                <div class="relative">
                                    <input
                                        id="ends_at_visible"
                                        type="text"
                                        name="ends_at_visible"
                                        class="auction-form-input jalali-datetime @error('ends_at') border-red-400 focus:border-red-400 focus:shadow-none dark	border-red-400 @enderror"
                                        placeholder="مثلاً 1404/09/21 10:00"
                                        value="{{ $closeVisible }}"
                                        data-alt="ends_at"
                                        {{ $isEdit ? '' : 'required' }}
                                    >
                                    <input type="hidden" name="ends_at" value="{{ $closeIso }}">
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400">پس از این زمان، سامانه به‌صورت خودکار حراج را می‌بندد.</p>
                                @error('ends_at')
                                    <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="space-y-2">
                                <label for="min_bid" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">حداقل قیمت پیشنهادی (اختیاری - ریال)</label>
                                <input
                                    id="min_bid"
                                    type="number"
                                    name="min_bid"
                                    min="0"
                                    class="auction-form-input @error('min_bid') border-red-400 focus:border-red-400 focus:shadow-none dark	border-red-400 @enderror"
                                    value="{{ old('min_bid', $auction->min_bid ?? '') }}"
                                    placeholder="مثلاً ۱۰۰٬۰۰۰ ریال"
                                >
                                @error('min_bid')
                                    <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label for="max_bid" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">سقف قیمت پیشنهادی (اختیاری - ریال)</label>
                                <input
                                    id="max_bid"
                                    type="number"
                                    name="max_bid"
                                    min="0"
                                    class="auction-form-input @error('max_bid') border-red-400 focus:border-red-400 focus:shadow-none dark	border-red-400 @enderror"
                                    value="{{ old('max_bid', $auction->max_bid ?? '') }}"
                                    placeholder="مثلاً ۱۵۰٬۰۰۰ ریال"
                                >
                                @error('max_bid')
                                    <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label for="info" class="block text-sm font-semibold text-slate-600 dark:text-slate-300">توضیحات تکمیلی (نمایش برای شرکت‌کنندگان)</label>
                            <textarea
                                id="info"
                                name="info"
                                rows="4"
                                class="auction-form-input resize-none @error('info') border-red-400 focus:border-red-400 focus:shadow-none dark	border-red-400 @enderror"
                                placeholder="خلاصه‌ای از هدف حراج، شرایط پرداخت یا نکات مهم..."
                            >{{ old('info', $auction->info ?? '') }}</textarea>
                            @error('info')
                                <p class="text-xs font-medium text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <input type="hidden" name="stock_id" value="{{ $stock->id }}">

                        <div class="flex flex-wrap gap-3 pt-2">
                            <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-earth-green to-emerald-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-500/25 transition hover:from-emerald-500 hover:to-earth-green">
                                <i class="fas {{ $isEdit ? 'fa-save' : 'fa-check' }} text-sm"></i>
                                <span>{{ $isEdit ? 'ذخیره تغییرات' : 'ثبت حراج' }}</span>
                            </button>

                            <a href="{{ route('admin.auction.index') }}"
                               class="inline-flex items-center gap-2 rounded-full border border-slate-300 px-6 py-3 text-sm font-semibold text-slate-600 transition hover:border-slate-400 hover:text-slate-800 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-400">
                                <i class="fas fa-arrow-right text-xs"></i>
                                <span>بازگشت به فهرست حراج‌ها</span>
                            </a>

                            @if($isEdit)
                                <a href="{{ route('auction.show', $auction) }}"
                                   class="inline-flex items-center gap-2 rounded-full bg-sky-100 px-6 py-3 text-sm font-semibold text-sky-600 transition hover:bg-sky-200 dark:bg-sky-500/15 dark:text-sky-200 dark:hover:bg-sky-500/25">
                                    <i class="fas fa-eye text-xs"></i>
                                    <span>مشاهده جزئیات حراج</span>
                                </a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
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
                                const g = pd.toDate();
                                const iso = g.getFullYear() + '-' +
                                    String(g.getMonth() + 1).padStart(2, '0') + '-' +
                                    String(g.getDate()).padStart(2, '0') + 'T' +
                                    String(g.getHours()).padStart(2, '0') + ':' +
                                    String(g.getMinutes()).padStart(2, '0');

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
