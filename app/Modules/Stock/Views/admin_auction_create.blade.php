@extends('layouts.admin')

@section('title', (isset($auction) ? 'ویرایش' : 'ایجاد') . ' عرضه اولیه سهام - ' . config('app.name', 'EarthCoop'))
@section('page-title', (isset($auction) ? 'ویرایش' : 'ایجاد') . ' عرضه اولیه سهام')
@section('page-description', 'عرضه اولیه خزانه EarthCoop با قیمت‌گذاری پایه در گل و انتخاب مسیر تسویه')

@php
    $isEdit = isset($auction);
    $formAction = $isEdit ? route('admin.auction.update', $auction) : route('admin.auction.store');
    $basePriceGol = old('base_price_gol', $auction->base_price_gol ?? $stock->base_share_price_gol ?? '');
    $basePriceBahar = is_numeric($basePriceGol) ? ((int) $basePriceGol / 100) : null;
    $maxPrimaryShares = $stock ? intdiv(((int) $stock->total_shares) * 1000, 10000) : 0;
    $selectedSettlementChannel = old('settlement_channel', $auction->settlement_channel ?? 'active_bahar');
@endphp

@push('styles')
<style>
    .canonical-auction-shell{max-width:1050px;margin:0 auto;border:1px solid rgba(148,163,184,.28);border-radius:1.5rem;background:#fff;box-shadow:0 24px 70px rgba(15,23,42,.10);overflow:hidden}
    .canonical-auction-header{padding:1.75rem 2rem;background:linear-gradient(135deg,rgba(5,150,105,.10),rgba(14,165,233,.08));border-bottom:1px solid rgba(148,163,184,.22)}
    .canonical-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1.25rem;padding:2rem}
    .canonical-card{border:1px solid rgba(148,163,184,.24);border-radius:1rem;padding:1rem;background:rgba(248,250,252,.82)}
    .canonical-label{display:block;font-weight:700;margin-bottom:.5rem;color:#334155}
    .canonical-input{width:100%;border:1px solid #cbd5e1;border-radius:.85rem;padding:.8rem .9rem;background:#fff;color:#0f172a}
    .canonical-help{font-size:.78rem;color:#64748b;margin-top:.45rem;line-height:1.7}
    .canonical-note{margin:0 2rem 1.25rem;padding:1rem 1.1rem;border-radius:1rem;background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;line-height:1.9}
    .canonical-actions{display:flex;gap:.75rem;flex-wrap:wrap;padding:0 2rem 2rem}
    .canonical-btn{border:0;border-radius:999px;padding:.8rem 1.25rem;font-weight:700;text-decoration:none;cursor:pointer}
    .canonical-btn.primary{background:#047857;color:#fff}.canonical-btn.secondary{background:#f1f5f9;color:#334155}
    .dark .canonical-auction-shell{background:#0f172a;border-color:#334155}.dark .canonical-card{background:#111827;border-color:#334155}.dark .canonical-label{color:#e2e8f0}.dark .canonical-input{background:#1e293b;border-color:#475569;color:#f8fafc}.dark .canonical-help{color:#94a3b8}.dark .canonical-note{background:rgba(6,95,70,.22);border-color:#065f46;color:#a7f3d0}
    @media(max-width:768px){.canonical-grid{grid-template-columns:1fr;padding:1rem}.canonical-auction-header{padding:1.25rem}.canonical-note{margin:0 1rem 1rem}.canonical-actions{padding:0 1rem 1.25rem;flex-direction:column}.canonical-btn{text-align:center;width:100%}}
</style>
@endpush

@section('content')
<div dir="rtl" class="space-y-6">
    <div class="canonical-auction-shell">
        <header class="canonical-auction-header">
            <div class="text-sm font-bold text-emerald-700 dark:text-emerald-300 mb-2">عرضه اولیه خزانه EarthCoop</div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mb-2">{{ $isEdit ? 'ویرایش حراج عرضه اولیه' : 'ایجاد حراج عرضه اولیه' }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-300 leading-7 mb-0">قیمت پایه دارایی در دفتر سهام فقط با واحد صحیح «گل» ثبت می‌شود. معادل بهار صرفاً برای خوانایی نمایش داده می‌شود. مسیر تسویه را نیز صریحاً برای همین عرضه انتخاب کنید.</p>
        </header>

        <div class="canonical-note">
            <strong>حداکثر عرضه اولیه: ۱۰٪</strong> از کل سهام EarthCoop. تسویه خارجی فقط برای عرضه اولیه خزانه EarthCoop مجاز است؛ این مسیر Bahar جدید ایجاد نمی‌کند و موجودی Najm Bahar را با پول فیات مخلوط نمی‌کند. در حال حاضر فقط مسیر ریالی برای UAT خارجی قابل انتخاب است و USD عمداً بسته می‌ماند.
        </div>

        @if ($errors->any())
            <div class="mx-4 md:mx-8 mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200">
                لطفاً خطاهای فرم را اصلاح کنید.
            </div>
        @endif

        <form method="POST" action="{{ $formAction }}">
            @csrf
            @if($isEdit) @method('PUT') @endif

            <input type="hidden" name="stock_id" value="{{ $stock->id ?? '' }}">

            <div class="canonical-grid">
                <div class="canonical-card">
                    <label for="shares_count" class="canonical-label">تعداد سهام عرضه‌شده</label>
                    <input id="shares_count" class="canonical-input" type="number" name="shares_count" min="1" max="{{ $stock->available_shares ?? '' }}" value="{{ old('shares_count', $auction->shares_count ?? '') }}" required>
                    <div class="canonical-help">موجودی خزانه قابل عرضه: {{ number_format($stock->available_shares ?? 0) }} سهم؛ سقف برنامه‌ریزی‌شده اولیه: {{ number_format($maxPrimaryShares) }} سهم.</div>
                    @error('shares_count')<div class="text-sm text-red-500 mt-1">{{ $message }}</div>@enderror
                </div>

                <div class="canonical-card">
                    <label for="base_price_gol" class="canonical-label">قیمت پایه هر سهم (گل)</label>
                    <input id="base_price_gol" class="canonical-input" type="number" name="base_price_gol" min="1" step="1" value="{{ $basePriceGol }}" required>
                    <div class="canonical-help">هر ۱ بهار = ۱۰۰ گل. @if($basePriceBahar !== null) معادل فعلی: {{ rtrim(rtrim(number_format($basePriceBahar, 2, '.', ''), '0'), '.') }} بهار برای هر سهم. @endif</div>
                    @error('base_price_gol')<div class="text-sm text-red-500 mt-1">{{ $message }}</div>@enderror
                </div>

                <div class="canonical-card">
                    <label for="settlement_channel" class="canonical-label">روش پرداخت / مسیر تسویه</label>
                    <select id="settlement_channel" name="settlement_channel" class="canonical-input" required>
                        <option value="active_bahar" {{ $selectedSettlementChannel === 'active_bahar' ? 'selected' : '' }}>تسویه با بهار فعال</option>
                        <option value="external_irr" {{ $selectedSettlementChannel === 'external_irr' ? 'selected' : '' }}>تسویه خارجی با ریال (IRR)</option>
                    </select>
                    <div class="canonical-help">«بهار فعال» از موجودی Active Bahar خریدار تسویه می‌شود. «تسویه خارجی با ریال» فقط برای عرضه اولیه خزانه EarthCoop است و تا عبور readiness gate ممکن است در صفحه کاربر غیرفعال بماند.</div>
                    @error('settlement_channel')<div class="text-sm text-red-500 mt-1">{{ $message }}</div>@enderror
                </div>

                <div class="canonical-card">
                    <label for="type" class="canonical-label">روش حراج</label>
                    <select id="type" name="type" class="canonical-input" required>
                        <option value="uniform_price" {{ old('type', $auction->type ?? 'uniform_price') === 'uniform_price' ? 'selected' : '' }}>قیمت یکسان</option>
                        <option value="pay_as_bid" {{ old('type', $auction->type ?? '') === 'pay_as_bid' ? 'selected' : '' }}>پرداخت به قیمت پیشنهادی</option>
                        <option value="single_winner" {{ old('type', $auction->type ?? '') === 'single_winner' ? 'selected' : '' }}>تک‌برنده</option>
                    </select>
                </div>

                <div class="canonical-card">
                    <label for="lot_size" class="canonical-label">اندازه لات</label>
                    <input id="lot_size" class="canonical-input" type="number" name="lot_size" min="1" step="1" value="{{ old('lot_size', $auction->lot_size ?? 1) }}" required>
                    <div class="canonical-help">حداکثر تعداد سهم در هر پیشنهاد.</div>
                </div>

                <div class="canonical-card">
                    <label for="start_time" class="canonical-label">زمان شروع</label>
                    <input id="start_time" class="canonical-input" type="datetime-local" name="start_time" value="{{ old('start_time', isset($auction) && $auction->start_time ? $auction->start_time->format('Y-m-d\TH:i') : '') }}" required>
                </div>

                <div class="canonical-card">
                    <label for="end_time" class="canonical-label">زمان پایان</label>
                    <input id="end_time" class="canonical-input" type="datetime-local" name="end_time" value="{{ old('end_time', isset($auction) && $auction->end_time ? $auction->end_time->format('Y-m-d\TH:i') : '') }}" required>
                </div>

                <div class="canonical-card">
                    <label for="ends_at" class="canonical-label">زمان بسته‌شدن خودکار</label>
                    <input id="ends_at" class="canonical-input" type="datetime-local" name="ends_at" value="{{ old('ends_at', isset($auction) && $auction->ends_at ? $auction->ends_at->format('Y-m-d\TH:i') : '') }}" {{ $isEdit ? '' : 'required' }}>
                </div>

                <div class="canonical-card">
                    <label for="settlement_mode" class="canonical-label">نحوه اجرای تسویه</label>
                    <select id="settlement_mode" name="settlement_mode" class="canonical-input" required>
                        <option value="manual" {{ old('settlement_mode', $auction->settlement_mode ?? 'manual') === 'manual' ? 'selected' : '' }}>دستی / پس از تأیید</option>
                        <option value="auto" {{ old('settlement_mode', $auction->settlement_mode ?? '') === 'auto' ? 'selected' : '' }}>خودکار</option>
                    </select>
                    <div class="canonical-help">فعال‌شدن پرداخت خارجی مستقل از این گزینه و مشروط به readiness gate است.</div>
                </div>

                <div class="canonical-card" style="grid-column:1/-1">
                    <label for="info" class="canonical-label">توضیحات عرضه</label>
                    <textarea id="info" name="info" rows="4" class="canonical-input">{{ old('info', $auction->info ?? '') }}</textarea>
                </div>
            </div>

            <div class="canonical-actions">
                <button type="submit" class="canonical-btn primary">{{ $isEdit ? 'ذخیره تغییرات' : 'ثبت حراج' }}</button>
                <a href="{{ route('admin.auction.index') }}" class="canonical-btn secondary">بازگشت به فهرست حراج‌ها</a>
            </div>
        </form>
    </div>
</div>
@endsection