@extends('layouts.unified')

@section('title', 'پاسخ به دعوت مسئولیت - ' . config('app.name', 'EarthCoop'))

@section('content')
<div class="container py-4" dir="rtl">
    <div class="card shadow-sm mx-auto" style="max-width: 760px;">
        <div class="card-body p-4">
            <h1 class="h4 mb-3">دعوت به پذیرش مسئولیت</h1>
            <p class="mb-2">گروه: <strong>{{ $offer->election->group->name ?? '—' }}</strong></p>
            <p class="mb-2">مسئولیت: <strong>{{ $offer->position === 'manager' ? 'مدیر' : 'بازرس' }}</strong></p>
            <p class="mb-2">رتبه در نتیجه قطعی: <strong>{{ $offer->ranking_position }}</strong></p>
            <p class="mb-4">مهلت پاسخ: <strong>{{ verta($offer->expires_at)->format('Y-m-d H:i') }}</strong></p>
            <div class="border rounded p-3 mb-3 bg-light" style="white-space: pre-wrap;">{{ $offer->contractVersion->body }}</div>
            <p class="text-muted small mb-2">نسخه قرارداد: {{ $offer->contractVersion->version }} — این نسخه پس از انتشار تغییرپذیر نیست.</p>
            <p class="mb-4"><a href="{{ route('elections.responsibility-contracts.download', $offer->contractVersion) }}">دانلود همین نسخه قرارداد</a></p>
            <form method="POST" action="{{ route('elections.responsibility-offers.respond', ['offer'=>$offer->id,'decision'=>$decision]) }}">
                @csrf
                @if($decision === 'accept')
                    <input type="hidden" name="contract_version_id" value="{{ $offer->contract_version_id }}">
                    <label class="d-flex gap-2 align-items-start mb-4">
                        <input type="checkbox" name="contract_confirmed" value="1" required>
                        <span>این نسخه مشخص قرارداد را به‌طور کامل خواندم و با آگاهی می‌پذیرم.</span>
                    </label>
                @endif
                <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn {{ $decision === 'accept' ? 'btn-success' : 'btn-danger' }}">{{ $decision === 'accept' ? 'تأیید و پذیرش مسئولیت' : 'تأیید عدم پذیرش' }}</button>
                    <a href="{{ route('profile.show') }}" class="btn btn-outline-secondary">بازگشت بدون تغییر</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
