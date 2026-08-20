@extends('layouts.unified')

@section('title', 'عرضه سهام در بازار ثانویه - ' . config('app.name', 'EarthCoop'))

@section('content')
<div class="container py-4" dir="rtl" style="max-width:900px">
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
                <div>
                    <h3 class="mb-2">عرضه سهام در بازار ثانویه</h3>
                    <div class="text-muted">{{ $holding->stock->info ?? 'سهام' }}</div>
                </div>
                <a href="{{ route('holding.index') }}" class="btn btn-outline-secondary">بازگشت به کیف سهام</a>
            </div>

            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            @if(!$secondaryEnabled)
                <div class="alert alert-warning">
                    بازار ثانویه canonical هنوز برای کاربران فعال نشده است. این فرم آماده است اما ایجاد عرضه تا عبور Readiness Audit و فعال‌شدن feature flag مسدود می‌ماند.
                </div>
            @endif

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="text-muted small mb-1">کل سهام شما</div>
                        <div class="fs-4 fw-bold">{{ number_format($holding->quantity) }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="text-muted small mb-1">قابل عرضه</div>
                        <div class="fs-4 fw-bold">{{ number_format($availableQuantity) }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="text-muted small mb-1">واحد قیمت</div>
                        <div class="fs-5 fw-bold">Gol / Active Bahar</div>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('stock.secondary-listing.store', $holding) }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">تعداد سهام برای فروش</label>
                        <input class="form-control" type="number" name="quantity" min="1" max="{{ $availableQuantity }}" value="{{ old('quantity') }}" required @disabled(!$secondaryEnabled || $availableQuantity < 1)>
                        @error('quantity')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">قیمت پایه هر سهم (Gol)</label>
                        <input class="form-control" type="number" name="base_price_gol" min="1" value="{{ old('base_price_gol', $holding->stock->base_share_price_gol ?? null) }}" required @disabled(!$secondaryEnabled)>
                        @error('base_price_gol')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">حداقل پیشنهاد (Gol)</label>
                        <input class="form-control" type="number" name="min_bid_gol" min="1" value="{{ old('min_bid_gol') }}" @disabled(!$secondaryEnabled)>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">حداکثر پیشنهاد (Gol)</label>
                        <input class="form-control" type="number" name="max_bid_gol" min="1" value="{{ old('max_bid_gol') }}" @disabled(!$secondaryEnabled)>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">نوع حراج</label>
                        <select class="form-select" name="type" required @disabled(!$secondaryEnabled)>
                            <option value="uniform_price" @selected(old('type')==='uniform_price')>قیمت یکسان</option>
                            <option value="pay_as_bid" @selected(old('type')==='pay_as_bid')>پرداخت به قیمت پیشنهادی</option>
                            <option value="single_winner" @selected(old('type')==='single_winner')>تک برنده</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">مدت حراج (ساعت)</label>
                        <input class="form-control" type="number" name="duration_hours" min="1" max="720" value="{{ old('duration_hours',24) }}" required @disabled(!$secondaryEnabled)>
                    </div>
                    <div class="col-12">
                        <label class="form-label">توضیحات اختیاری</label>
                        <textarea class="form-control" name="info" rows="3" maxlength="1000" @disabled(!$secondaryEnabled)>{{ old('info') }}</textarea>
                    </div>
                </div>

                <div class="alert alert-info mt-4 mb-3">
                    با ایجاد عرضه، تعداد سهام انتخاب‌شده فوراً برای همین حراج رزرو می‌شود و تا پایان، لغو معتبر یا تسویه قابل فروش مجدد نیست. پرداخت خریدار فقط با Active Bahar انجام می‌شود و پس از تسویه، وجه به حساب نجم بهار شما و سهم به Holding خریدار منتقل می‌شود.
                </div>

                <button type="submit" class="btn btn-success px-4" @disabled(!$secondaryEnabled || $availableQuantity < 1)>
                    ایجاد عرضه ثانویه
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
